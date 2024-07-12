<?php

namespace IMEdge\SnmpFeature;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use IMEdge\Metrics\Ci;
use IMEdge\Metrics\Measurement;
use IMEdge\Metrics\Metric;
use IMEdge\Metrics\MetricDatatype;
use IMEdge\SnmpFeature\Scenario\PollSysInfo;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;
use Psr\Log\LoggerInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use Revolt\EventLoop;
use Throwable;

use function React\Promise\all;
use function React\Promise\reject;

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
    /** @var Promise[] */
    protected array $runningPromises = [];
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
        foreach ($this->runningPromises as $promise) {
            $promise->cancel();
        }
        $this->runningPromises = [];
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
        foreach ($this->socket->getStats() as $name => $value) {
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
            $this->runningPromises[$idx] = $this->sendRequest($request);
            $this->scheduledRunsTotal++;
        }
        if (empty($this->pendingRequests)) {
            $this->stopEnqueueingTimer();
        }
    }

    protected function prepareRequest(PeriodicScenarioSingleRequest $request): PromiseInterface
    {
        $method = $request->requestType;
        try {
            // TODO: Accept target and credential in SnmpSocket
            $address = $request->target->address->ip;
            $community = $this->runner->credentials->requireCredential($request->target->credentialUuid)->securityName;
            if ($method === 'get') {
                // $this->logger->notice('Preparing GET');
                $snmpRequest = $this->socket->$method($request->oidList->oidList, $address, $community);
            } else {
                // $this->logger->notice('Preparing WALK');
                // walk
                try {
                    $tables = [];
                    foreach ($request->oidList->oidList as $oid => $alias) {
                        // $this->logger->notice("walking $oid => $alias");
                        $tables[$alias] = $this->socket->walk($oid, $address, $community);
                    }
                    $snmpRequest = all($tables)->then(function ($result) {
                        return $this->scenario->resultHandler->fixResult($result);
                    });
                    // notice(sprintf('WALKING %d tables', count($tables)) . implode(', ', array_keys($tables)));
                } catch (Throwable $e) {
                    $this->logger->notice('Preparing walk failed: ' . $e->getMessage());
                    return reject(new Exception($e->getMessage()));
                }
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->forget($request);
            $deferred = new Deferred();
            EventLoop::queue(fn () => $deferred->reject($e));
            return $deferred->promise();
        }
        // $this->logger->notice("Sending $method" . json_encode($request->oidList->oidList));
        return $snmpRequest;
    }

    protected function sendRequest(PeriodicScenarioSingleRequest $request): PromiseInterface
    {
        try {
            return $this->prepareRequest($request)->then(function ($result) use ($request) {
                $this->processResult($result, $request);
            }, function (Exception $reason) use ($request) {
                $this->processFailure($reason, $request);
            })->finally(function () use ($request) {
                $this->forget($request);
            });
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->forget($request);
            $deferred = new Deferred();
            EventLoop::queue(fn () => $deferred->reject($e));
            return $deferred->promise();
        }
    }

    protected function processResult($result, PeriodicScenarioSingleRequest $request): void
    {
        // $this->logger->notice('RESULT IMMEDIATE: ' . var_export($result, 1));
        try {
            $this->emit(self::ON_RESULT, [new Result($request->requestType, $request->target, $result)]);
            $this->scenario->processResult($request->target, $result);
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                'Processing Scenario result failed for %s (%s): %s',
                $request->target->address,
                $this->scenario->name,
                $e->getMessage()
            ));
        }
    }

    protected function processFailure(Exception $reason, PeriodicScenarioSingleRequest $request): void
    {
        $address = $request->target->address;
        try {
            if ($this->scenario->scenarioClass !== PollSysInfo::class) {
                $this->logger->error(sprintf(
                    'Scenario failed for %s (%s): %s',
                    $address,
                    $this->scenario->name,
                    $reason->getMessage()
                ));
            }
            $this->emit(
                self::ON_RESULT,
                [new Result($request->requestType, $request->target, null, $reason->getMessage())]
            );
            $this->scenario->processFailure($request->target, $reason);
        } catch (Throwable $e) {
            $this->logger->error(sprintf(
                'Error on Scenario result handling for %s (%s): %s',
                $address,
                $this->scenario->name,
                $e->getMessage()
            ));
        }
    }

    protected function forget(PeriodicScenarioSingleRequest $request): void
    {
        $idx = spl_object_id($request);
        unset($this->pendingRequests[$idx]); // is not set
        unset($this->runningRequests[$idx]);
        unset($this->runningPromises[$idx]);
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
