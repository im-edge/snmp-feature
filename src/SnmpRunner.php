<?php

namespace IMEdge\SnmpFeature;

use Amp\Redis\RedisClient;
use gipfl\Json\JsonString;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Metrics\Measurement;
use IMEdge\Node\Events;
use IMEdge\Node\Services;
use IMEdge\RedisTables\RedisTables;
use IMEdge\SnmpFeature\NextGen\DedicatedResultHandler;
use IMEdge\SnmpFeature\SnmpScenario\KnownTargetsHealth;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Throwable;

class SnmpRunner
{
    /** @var ?PeriodicScenarioRunner[] */
    protected array $periodicScenarios = [];
    protected ?RedisTables $redisTables = null;
    protected bool $shuttingDown = false;
    protected ?RedisClient $redisClientForMetrics = null;
    protected bool $startedRecently = true;
    protected DedicatedResultHandler $resultHandler;

    public function __construct(
        public readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
        public readonly Events $events,
        public readonly Services $services,
        public SnmpCredentials $credentials = new SnmpCredentials([]),
        public SnmpTargets $targets = new SnmpTargets(),
        public KnownTargetsHealth $health = new KnownTargetsHealth(),
    ) {
        $this->resultHandler = new DedicatedResultHandler(
            $this->nodeIdentifier,
            $this->health,
            $this->events,
            $this->services,
            $this->logger
        );
    }

    public function run(): void
    {
        $this->startedRecently = true;
        EventLoop::delay(20, function () {
            // There is a race condition on run/stop/run, affects log lines only
            $this->startedRecently = false;
        });
        $this->redisTables = $this->services->getRedisTables('snmp/runner');
        $this->logger->notice('SnmpRunner: redis tables are ready');
        $this->redisClientForMetrics = $this->services->getRedisClient('snmp/internalMetrics');
        $this->logger->notice('SnmpRunner: Redis connection for internal metrics is ready');
    }

    public function stop(): void
    {
        $this->shuttingDown = true;
        foreach ($this->periodicScenarios as $scenario) {
            $scenario->stop();
        }
        $this->periodicScenarios = [];
    }

    public function launchPeriodicScenarios(array $scenarioClasses): void
    {
        foreach ($scenarioClasses as $class) {
            $this->stopPeriodicScenario($class);
        }
        if (empty($this->targets->targets)) {
            $this->logger->notice('Got no targets');
            return;
        }
        foreach ($scenarioClasses as $class) {
            $this->launchPeriodicScenario($class);
        }
    }

    public function getPeriodicScenarioRunner(string $class): PeriodicScenarioRunner
    {
        return $this->periodicScenarios[$class]
            ?? throw new \RuntimeException('Periodic scenario not loaded: ' . $class);
    }

    protected function stopPeriodicScenario(string $class): void
    {
        if (isset($this->periodicScenarios[$class])) {
            $this->logger->notice("Stopping periodic scenario runner instance for $class");
            $this->periodicScenarios[$class]->stop();
            unset($this->periodicScenarios[$class]);
        }
    }

    protected function shipScenarioMeasurement(Measurement $measurement): void
    {
        EventLoop::queue(function () use ($measurement) {
            $this->redisClientForMetrics?->execute(
                'XADD',
                'internalMetrics',
                'MAXLEN',
                '~',
                10_000,
                '*',
                'measurement',
                JsonString::encode($measurement)
            );
        });
    }

    protected function launchPeriodicScenario(string $class): void
    {
        $scenario = new PeriodicScenario($class, $this->targets, $this->logger);
        $this->periodicScenarios[$class] = $runner = new PeriodicScenarioRunner($this, $scenario, $this->logger);

        // Internal measurements, not result-related
        $runner->on(PeriodicScenarioRunner::ON_MEASUREMENT, $this->shipScenarioMeasurement(...));
        $runner->on(PeriodicScenarioRunner::ON_MEASUREMENT, function (Measurement $measurement) {
            $this->events->emit('measurements', [[$measurement]]);
        });
        $scenarioName = $scenario->name;
        $runner->on(PeriodicScenarioRunner::ON_RESULT, function (Result $result) use ($scenarioName) {
            // $this->logger->notice($scenario->name . ' shipped a result');
            try {
                $this->resultHandler->processResult($scenarioName, $result);
            } catch (Throwable $e) {
                $this->logger->error('Processing result failed: ' . $e->getMessage());
            }
        });
        $runner->start();
    }
}
