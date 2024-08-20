<?php

namespace IMEdge\SnmpFeature;

use Amp\Cancellation;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use IMEdge\Metrics\Ci;
use IMEdge\Metrics\Measurement;
use IMEdge\Metrics\Metric;
use IMEdge\Metrics\MetricDatatype;
use IMEdge\SnmpFeature\Scenario\PollSysInfo;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Throwable;

use function Amp\async;
use function Amp\Future\await;
use function Amp\Future\awaitAll;

class PeriodicScenarioRunner implements EventEmitterInterface
{
    use EventEmitterTrait;

    public const ON_RESULT = 'result';
    public const ON_IDLE = 'idle';
    public const ON_MEASUREMENT = 'measurement';

    private const MAX_PENDING = 10_000;
    private const METRIC_INTERVAL = 5;
    private const REQUESTS_TO_LAUNCH_AT_ONCE = 50;
    private const DELAY_BETWEEN_REQUEST_BATCHES = 0.05;

    protected SnmpSocket $socket;
    protected PeriodicScenarioSlots $slots;
    protected ?string $enqueuingTimer = null;
    /** @var PeriodicScenarioSingleRequest[] */
    protected array $pendingRequests = [];
    /** @var PeriodicScenarioSingleRequest[] */
    protected array $runningRequests = [];
    /** @var Cancellation[] */
    protected array $runningCancellations = [];
    protected int $nextSlot;
    protected float $interval;
    protected float $timeForSend;
    protected ?string $slotEnqueuer = null;
    protected ?string $mainTimer = null;
    protected ?string $statsTimer = null;
    protected int $scheduledRunsTotal = 0;

    public function __construct(
        public readonly SnmpRunner $runner,
        public readonly PeriodicScenario $scenario,
        protected readonly LoggerInterface $logger,
    ) {
        $this->socket = new SnmpSocket();
        $this->socket->setLogger($this->logger);
        $targetCount = count($scenario->targets->targets);
        $this->timeForSend = $this->calculateTimeForSend($scenario);
        $slotCount = $targetCount > 1000 ? 200 : 20; // TODO: should depend on time
        $this->nextSlot = 0;
        $this->slots = new PeriodicScenarioSlots($slotCount, $scenario);
    }

    public function start(): void
    {
        $this->logConfigurationInfo();
        $this->mainTimer = EventLoop::repeat($this->scenario->interval, $this->runTimeSlot(...));
        $this->statsTimer = EventLoop::repeat(self::METRIC_INTERVAL, $this->emitMetrics(...));
        EventLoop::queue($this->runTimeSlot(...));
        EventLoop::queue($this->emitMetrics(...));
    }

    public function stop(): void
    {
        if ($this->mainTimer) {
            EventLoop::cancel($this->mainTimer);
            $this->mainTimer = null;
        }
        if ($this->statsTimer) {
            EventLoop::cancel($this->statsTimer);
            $this->statsTimer = null;
        }
        $this->pendingRequests = [];
        $this->runningRequests = [];
        $this->stopEnqueueingTimer();
        foreach ($this->runningCancellations as $cancellation) {
            $cancellation->cancel(); // TODO: shutting down exception, don't log
        }
        $this->runningCancellations = [];
    }

    protected function logConfigurationInfo(): void
    {
        $this->logger->notice(
            sprintf(
                'Starting runner with interval %.02Fms, time for sending: %.02Fms, %d targets in %d slots',
                $this->scenario->interval * 1000,
                $this->timeForSend * 1000,
                count($this->scenario->targets->targets),
                $this->slots->slotCount,
            )
        );
    }

    protected function emitMetrics(): void
    {
        // TOOD: rename some properties, metric names might be a good fit
        $metrics = [
            new Metric('scenarioRunsScheduledTotal', $this->scheduledRunsTotal, MetricDatatype::COUNTER),
            new Metric('scenarioRunsScheduled', count($this->pendingRequests)),
            new Metric('scenarioRunsActive', count($this->runningRequests)),
            new Metric('schedulingSlots', $this->slots->slotCount),
            new Metric('scheduledTargets', count($this->scenario->targets->targets)),
        ];
        foreach ($this->socket->stats->getStats() as $name => $value) {
            $metrics[] = new Metric($name, $value, MetricDatatype::COUNTER);
        }
        $this->emit(self::ON_MEASUREMENT, [new Measurement(
            new Ci($this->runner->nodeIdentifier->uuid->toString(), 'SnmpScenario', $this->scenario->name),
            time(),
            $metrics
        )]);
    }

    protected function calculateTimeForSend(PeriodicScenario $scenario): float
    {
        $snmpTimeout = 30;
        return max(floor($scenario->interval / 2), floor($scenario->interval - $snmpTimeout * 2));
    }

    protected function runTimeSlot(): void
    {
        if ($this->slotEnqueuer === null) {
            $this->slotEnqueuer = EventLoop::repeat($this->getTimeForSendPerSlot(), $this->sendSlot(...));
        } else {
            $this->logger->warning(
                sprintf(
                    'Skipping time slot for %s, former one is still unfinished',
                    $this->scenario->name
                )
            );
        }
    }

    public function trigger(SnmpTarget $target, ?int $delay = null): void
    {
        EventLoop::delay($delay ?? 0, function () use ($target) {
// TODO: normal log level, or remove
            $this->logger->emergency('Running on-demand ' . $this->scenario->name);
            $this->pendingRequests[] = new PeriodicScenarioSingleRequest(
                $target,
                $this->scenario->oidList,
                $this->scenario->requestType
            );
        });
    }

    protected function sendSlot(): void
    {
        // $this->logger->debug('Current slot: ' . $this->nextSlot);
        $requests = $this->slots->getSlot($this->nextSlot);
        foreach ($requests as $request) {
            $this->pendingRequests[] = $request;
        }
        $this->nextSlot++;
        // debug('Next slot: ' . var_export($this->nextSlot, true) . ' / ' . var_export($this->slotCount, true));
        if ($this->nextSlot === $this->slots->slotCount) {
            if ($this->slotEnqueuer) {
                EventLoop::cancel($this->slotEnqueuer);
            }
            $this->slotEnqueuer = null;
            $this->nextSlot = 0;
        }
        $this->enableEnqueuingTimer();
    }

    protected function getTimeForSendPerSlot(): float|int
    {
        return $this->timeForSend / $this->slots->slotCount;
    }

    protected function enqueueNextRequests($count): void
    {
        if (count($this->runningRequests) >= self::MAX_PENDING) {
            return;
        }
        $isSysInfo = $this->scenario->scenarioClass === PollSysInfo::class;
        $count = min($count, count($this->pendingRequests));
        for ($i = 0; $i < $count; $i++) {
            $request = array_shift($this->pendingRequests);
            if (!$isSysInfo && !$this->runner->health->isReachable($request->target->identifier)) {
                /*
                $this->logger->notice(sprintf(
                    'Skipping %s for %s',
                    $this->scenario->name,
                    $request->target->address->ip
                ));
                */
                continue;
            }
            $idx = spl_object_id($request);
            $this->runningRequests[$idx] = $request;
            EventLoop::queue(fn () => $this->sendRequest($request));
            $this->scheduledRunsTotal++;
        }
        if (empty($this->pendingRequests)) {
            $this->stopEnqueueingTimer();
        }
    }

    protected function reallySendRequest(PeriodicScenarioSingleRequest $request): array
    {
        $method = $request->requestType;
        // TODO: Accept target and credential in SnmpSocket
        $address = $request->target->address->ip;
        $community = $this->runner->credentials->requireCredential($request->target->credentialUuid)->securityName;
        if ($method === 'get') {
            return await([async(fn () => $this->socket->get($request->oidList->oidList, $address, $community))])[0];
        }
        $tables = [];
        foreach ($request->oidList->oidList as $oid => $alias) {
            $tables[$alias] = async(fn () => $this->socket->walk($oid, $address, $community));
        }
        [$errors, $responses] = awaitAll($tables);
        foreach ($errors as $alias => $error) {
            $this->logger->error("Walk for $alias failed: " . $error->getMessage());
        }

        return $this->scenario->resultHandler->fixResult($responses);
    }

    protected function sendRequest(PeriodicScenarioSingleRequest $request): void
    {
        // TODO: $this->runningCancellations[$idx] =
        $name = $this->scenario->name;
        $target = $request->target;
        try {
            $result = $this->reallySendRequest($request);
            $this->emit(self::ON_RESULT, [
                new Result($name, $request->requestType, $target, $result)
            ]);
        } catch (Throwable $reason) {
            try {
                $this->emit(self::ON_RESULT, [
                    new Result($name, $request->requestType, $target, null, $reason->getMessage())
                ]);
            } catch (Throwable $e) {
                $this->logger->error(sprintf(
                    'Processing Scenario failure failed for %s (%s): %s',
                    $target->address,
                    $name,
                    $e->getMessage()
                ));
            }
        }
        $this->forget($request);
    }

    protected function forget(PeriodicScenarioSingleRequest $request): void
    {
        $idx = spl_object_id($request);
        unset($this->pendingRequests[$idx]); // is not set
        unset($this->runningRequests[$idx]);
        unset($this->runningCancellations[$idx]); // TODO: unsubscribe?
        if (empty($this->runningRequests) && empty($this->pendingRequests)) {
            $this->emit(self::ON_IDLE);
        }
    }

    protected function enableEnqueuingTimer(): void
    {
        $this->enqueuingTimer ??= EventLoop::repeat(
            self::DELAY_BETWEEN_REQUEST_BATCHES,
            fn () => $this->enqueueNextRequests(self::REQUESTS_TO_LAUNCH_AT_ONCE)
        );
    }

    protected function stopEnqueueingTimer(): void
    {
        if ($this->enqueuingTimer !== null) {
            EventLoop::cancel($this->enqueuingTimer);
            $this->enqueuingTimer = null;
        }
    }
}
