<?php

namespace IMEdge\SnmpFeature;

use Amp\Redis\RedisClient;
use gipfl\Json\JsonString;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Metrics\Measurement;
use IMEdge\Node\Events;
use IMEdge\Node\Services;
use IMEdge\RedisTables\RedisTables;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\Scenario\PollSysInfo;
use IMEdge\SnmpFeature\Scenario\ScenarioResultHandler;
use IMEdge\SnmpFeature\SnmpScenario\KnownTargetsHealth;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use IMEdge\SnmpFeature\SnmpScenario\TargetState;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;
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

    public function __construct(
        public readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
        public readonly Events $events,
        public readonly Services $services,
        public SnmpCredentials $credentials = new SnmpCredentials([]),
        public SnmpTargets $targets = new SnmpTargets(),
        public KnownTargetsHealth $health = new KnownTargetsHealth(),
    ) {
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
        $runner->on(PeriodicScenarioRunner::ON_MEASUREMENT, $this->shipScenarioMeasurement(...));
        $runner->on(PeriodicScenarioRunner::ON_MEASUREMENT, function (Measurement $measurement) {
            $this->events->emit('measurements', [[$measurement]]);
        });
        $runner->on(PeriodicScenarioRunner::ON_RESULT, function (Result $result) use ($scenario) {
            // $this->logger->notice($scenario->name . ' shipped a result');
            try {
                $this->processResult($result, $scenario);
            } catch (Throwable $e) {
                $this->logger->error('Processing result failed: ' . $e->getMessage());
            }
        });
        $runner->start();
    }

    protected function processResult(Result $result, PeriodicScenario $scenario): void
    {
        $target = $result->target;
        if ($result->succeeded()) {
            /*
            $this->logger->debug(sprintf(
                'Got %s scenario result from %s',
                $scenario->name,
                $target->address->ip
            ));
            */
            // $target points to our target object, it's state is still the former one!
            if ($target->state !== TargetState::REACHABLE && $scenario->scenarioClass === PollSysInfo::class) {
                $formerState = $target->state;
                $target->state = TargetState::REACHABLE;
                // TODO: emit db update -> state
                // TODO: $result->target->error = null;

                if (! ($this->startedRecently && $formerState === TargetState::PENDING)) {
                    $this->logger->notice(sprintf(
                        'Target was %s, and is now reachable: %s (%s)',
                        $formerState->value,
                        $target->address->ip,
                        $scenario->name
                    ));
                }
                $this->redisTables->setTableEntry('snmp_target_health', $target->identifier, [
                    'uuid'
                ], [
                    'uuid'  => $target->identifier,
                    'state' => TargetState::REACHABLE->value,
                ]);
                // TODO: emit db update -> state
                // TODO: $result->target->error = $result->error;
            }

            $deviceUuid = RamseyUuid::fromString($result->target->identifier);
            if ($scenario->requestType === 'get') {
                // $this->logger->notice('Processing GET');
                $scenario->processResult($result->target, $result->result);
                try {
                    $this->sendResultToRedis(
                        $scenario->resultHandler,
                        $scenario->resultHandler->getResultObjectInstance(
                            $this->nodeIdentifier->uuid,
                            $deviceUuid,
                            $result->result
                        )
                    );
                } catch (Throwable $e) {
                    $this->logger->error('GET Failed: ' . $e->getMessage());
                }
            } else {
                // walk
                $resultHandler = $scenario->resultHandler;
                // $this->logger->notice('Result: ' . var_export($result->result, 1));
                try {
                    $instances = $scenario->resultHandler->getResultObjectInstances(
                        $this->nodeIdentifier->uuid,
                        $deviceUuid,
                        $result->result
                    );
                } catch (Throwable $e) {
                    $this->logger->error($e->getMessage());
                    return;
                }
                // $this->logger->notice('Instances: ' . var_export($instances, 1));
                if ($dbTable = $resultHandler->getDbTable()) {
                    $tables = [];
                    try {
                        foreach ($instances as $instance) {
                            $tables[
                                $resultHandler->getDbUpdateKey($instance)
                            ] = $resultHandler->getInstanceDbProperties($instance);
                        }
                        $this->sendTableEntries($dbTable, $deviceUuid, array_keys($dbTable->keyProperties), $tables);
                    } catch (\Throwable $e) {
                        $this->logger->error(
                            'Sending table updates failed: ' . $e->getMessage() . $e->getFile() . $e->getLine()
                        );
                    }
                } else {
                    // $this->logger->notice('Scenario ' . $scenario->name . ' has no DB table');
                }
                try {
                    if ($measurements = $resultHandler->prepareMeasurements($deviceUuid, $instances)) {
                        // $this->logger->notice('Measurements: ' . JsonString::encode($measurements));
                        $this->events->emit('measurements', [$measurements]);
                    }
                } catch (\Throwable $e) {
                    $this->logger->notice('Measurements failed: ' . $e->getMessage());
                }
            }
        } else {
            if ($scenario->scenarioClass !== PollSysInfo::class) {
                $this->logger->notice(sprintf(
                    'Scenario failed: %s (%s)',
                    $target->identifier,
                    $scenario->name
                ));
            }
            if ($result->target->state !== TargetState::FAILING && $scenario->scenarioClass === PollSysInfo::class) {
                $this->logger->notice(sprintf(
                    'Scenario failed, setting failing: %s (%s)',
                    $target->identifier,
                    $scenario->name
                ));

                $result->target->state = TargetState::FAILING;
                $this->redisTables->setTableEntry('snmp_target_health', $result->target->identifier, [
                    'uuid'
                ], [
                    'uuid'  => $result->target->identifier,
                    'state' => TargetState::FAILING->value,
                ]);
            }
        }

        if ($scenario->scenarioClass === PollSysInfo::class) {
            $this->health->setCurrentResult($result->target->identifier, $result->target->state);
        }
        /*
        if ($result->succeeded()) {
            try {
                printf("OK %s: %s\n", $result->target->address->ip, self::octetString($result->result['sys_descr']));
            } catch (\Throwable $e) {
                echo $e->getMessage();
            }
        } else {
            printf("ERR %s: %s\n", $result->target->address->ip, $result->error);
        }
        */
        // echo JsonString::encode($result, JSON_PRETTY_PRINT) . "\n";
    }

    protected function sendTableEntries(
        DbTable $dbTable,
        UuidInterface $deviceUuid,
        array $keyProperties,
        array $tables
    ): void {
        if (empty($tables)) {
            return;
        }
        if (! $this->redisTables) {
            $this->logger->debug('No redis tables, skipping DB updates');
            return;
        }
        try {
            $result = $this->redisTables->setTableForDevice(
                $dbTable->tableName,
                $deviceUuid->toString(),
                $keyProperties,
                $tables
            );
            // $this->logger->notice("Redis said: $result");
        } catch (\Exception $e) {
            $this->logger->error('Setting Redis table failed: ' . $e->getMessage());
        }
    }

    protected function sendResultToRedis(ScenarioResultHandler $handler, object $instance): void
    {
        if (! $this->redisTables) {
            return;
        }
        if ($update = $handler->prepareDbUpdate($instance)) {
            // $this->logger->notice('Sending to Redis: ' . JsonString::encode($update));
            try {
                $result = $this->redisTables->setTableEntry(...$update);
                // $this->logger->notice("Redis said: $result");
            } catch (\Exception $e) {
                $this->logger->error('Updating Redis table failed: ' . $e->getMessage());
            }
        }
    }
}
