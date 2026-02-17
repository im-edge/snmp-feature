<?php

namespace IMEdge\SnmpFeature;

use Amp\Redis\RedisClient;
use IMEdge\Json\JsonString;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Metrics\Measurement;
use IMEdge\Node\Events;
use IMEdge\Node\Services;
use IMEdge\Node\Worker\WorkerInstance;
use IMEdge\Node\Worker\WorkerInstances;
use IMEdge\SnmpFeature\Discovery\SnmpDiscoveryReceiver;
use IMEdge\SnmpFeature\Discovery\SnmpDiscoverySender;
use IMEdge\SnmpFeature\NextGen\DedicatedResultHandler;
use IMEdge\SnmpFeature\SnmpScenario\KnownTargetsHealth;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Revolt\EventLoop;
use Throwable;

use function Amp\async;

class SnmpRunner
{
    /** @var ?PeriodicScenarioRunner[] */
    protected array $periodicScenarios = [];
    protected bool $shuttingDown = false;
    protected ?RedisClient $redisClientForMetrics = null;
    protected bool $startedRecently = true;
    protected DedicatedResultHandler $resultHandler;
    public ?WorkerInstance $discoverySender = null;
    public ?WorkerInstance $discoveryReceiver = null;

    public function __construct(
        public readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
        public readonly Events $events,
        public readonly Services $services,
        protected readonly WorkerInstances $workerInstances,
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
        $this->redisClientForMetrics = $this->services->getRedisClient('snmp/internalMetrics');
        $this->logger->notice('SnmpRunner: Redis connection for internal metrics is ready');
        $this->startDiscoveryWorkers();
    }

    public function stop(): void
    {
        $this->shuttingDown = true;
        $this->stopDiscoveryWorkers();
        $this->stopAllPeriodicScenarios();
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
        $runner->on(PeriodicScenarioRunner::ON_RESULT, function (Result $result) {
            // $this->logger->notice($scenario->name . ' shipped a result');
            try {
                $this->resultHandler->processResult($result);
            } catch (Throwable $e) {
                $this->logger->error('Processing result failed: ' . $e->getMessage());
            }
        });
        $runner->start();
    }

    protected function startDiscoveryWorkers(): void
    {
        $receiver = $this->workerInstances->launchWorker('snmp-discovery-receiver', Uuid::uuid4());
        $receiver->run(SnmpDiscoveryReceiver::class);
        $this->discoveryReceiver = $receiver;

        $sender = $this->workerInstances->launchWorker('snmp-discovery-sender', Uuid::uuid4());
        $sender->run(SnmpDiscoverySender::class);
        $this->discoverySender = $sender;

        $this->logger->notice('Lanched Sender and Receiver for Discovery Tasks');
    }

    protected function stopDiscoveryWorkers(): void
    {
        if ($this->discoverySender) {
            $this->discoverySender->stop();
            $this->discoverySender = null;
        }
        if ($this->discoveryReceiver) {
            $this->discoveryReceiver->stop();
            $this->discoveryReceiver = null;
        }
    }

    protected function stopAllPeriodicScenarios(): void
    {
        foreach ($this->periodicScenarios as $scenario) {
            $scenario->stop();
        }
        $this->periodicScenarios = [];
    }
}
