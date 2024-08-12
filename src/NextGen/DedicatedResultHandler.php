<?php

namespace IMEdge\SnmpFeature\NextGen;

use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Node\Events;
use IMEdge\Node\Services;
use IMEdge\RedisTables\RedisTables;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\RequestedOidList;
use IMEdge\SnmpFeature\Result;
use IMEdge\SnmpFeature\Scenario\PollingTask;
use IMEdge\SnmpFeature\Scenario\PollSysInfo;
use IMEdge\SnmpFeature\Scenario\ScenarioResultHandler;
use IMEdge\SnmpFeature\SnmpScenario\KnownTargetsHealth;
use IMEdge\SnmpFeature\SnmpScenario\TargetState;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

class DedicatedResultHandler
{
    protected const HEALTH_CHECK_SCENARIO = 'sysInfo';

    /** @var array<string, ScenarioResultHandler> */
    protected array $scenarioResultHandlers = [];
    /** @var array<string, ReflectionClass> */
    protected array $scenarioReflections = [];
    /** @var array<string, string> */
    protected array $scenarioRequestType = [];
    protected RedisTables $redisTables;

    public function __construct(
        protected readonly NodeIdentifier $nodeIdentifier,
        protected readonly KnownTargetsHealth $health,
        protected readonly Events $events,
        protected readonly Services $services,
        protected LoggerInterface $logger
    ) {
        $this->redisTables = $services->getRedisTables('snmp/runner');
        foreach ((new PeriodicScenarioRegistry())->listScenarios() as $scenarioClass) {
            $this->prepareResultHandler($scenarioClass);
        }
    }

    protected function prepareResultHandler(string $scenarioClass): void
    {
        $reflection = new ReflectionClass($scenarioClass);
        $name = null;
        foreach ($reflection->getAttributes(PollingTask::class) as $attribute) {
            /** @var PollingTask $task */
            $task = $attribute->newInstance();
            $name = $task->name;
            // $interval = $task->defaultInterval ?: 600; // Unused here, it's for the scheduler
        }
        if ($name === null) {
            throw new RuntimeException('PeriodicScenario expects a PollingTask, got ' . $scenarioClass);
        }
        $this->scenarioReflections[$name] = $reflection;
        $resultHandler = new ScenarioResultHandler($name, $reflection, $this->logger);
        $this->scenarioResultHandlers[$name] = $resultHandler;
        $this->scenarioRequestType[$name] = $resultHandler->needsWalk() ? 'walk' : 'get'; // Unused here, it's for the scheduler
        // $oidList = new RequestedOidList($resultHandler->getScenarioOids());
    }

    public function processResult(string $scenarioName, Result $result): void
    {
        $target = $result->target;
        $isHealthCheck = $scenarioName === self::HEALTH_CHECK_SCENARIO;
        if ($result->succeeded()) {
            /*
            $this->logger->debug(sprintf(
                'Got %s scenario result from %s',
                $scenario->name,
                $target->address->ip
            ));
            */
            // $target points to our target object, it's state is still the former one!
            if ($target->state !== TargetState::REACHABLE && $isHealthCheck) {
                $formerState = $target->state;
                $target->state = TargetState::REACHABLE;
                // TODO: emit db update -> state
                // TODO: $result->target->error = null;

                if ($formerState !== TargetState::PENDING) {
                    $this->logger->notice(sprintf(
                        'Target was %s, and is now reachable: %s (%s)',
                        $formerState->value,
                        $target->address->ip,
                        $scenarioName
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
            $resultHandler = $this->scenarioResultHandlers[$scenarioName];
            if ($this->scenarioRequestType[$scenarioName] === 'get') {
                // $this->logger->notice('Processing GET');
// $scenario->processResult($result->target, $result->result); -> this does nothing
                try {
                    $this->sendResultToRedis(
                        $resultHandler,
                        $resultHandler->getResultObjectInstance(
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
                // $this->logger->notice('Result: ' . var_export($result->result, 1));
                try {
                    $instances = $resultHandler->getResultObjectInstances(
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
                    } catch (Throwable $e) {
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
                } catch (Throwable $e) {
                    $this->logger->notice('Measurements failed: ' . $e->getMessage());
                }
            }
        } else {
            if (!$isHealthCheck) {
                $this->logger->notice(sprintf(
                    'Scenario failed: %s (%s)',
                    $target->identifier,
                    $scenarioName
                ));
            }
            if ($result->target->state !== TargetState::FAILING && $isHealthCheck) {
                $this->logger->notice(sprintf(
                    'Scenario failed, setting failing: %s (%s)',
                    $target->identifier,
                    $scenarioName
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

        if ($isHealthCheck) {
            $this->health->setCurrentResult($result->target->identifier, $result->target->state);
        }
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