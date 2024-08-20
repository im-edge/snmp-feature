<?php

namespace IMEdge\SnmpFeature\NextGen;

use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Metrics\MetricsEvent;
use IMEdge\Node\Events;
use IMEdge\Node\Services;
use IMEdge\RedisTables\RedisTables;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\Result;
use IMEdge\SnmpFeature\Scenario\PollingTask;
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

    public function processResult(Result $result): void
    {
        if ($result->succeeded()) {
            $this->processSuccess($result);
        } else {
            $this->processFailure($result);
        }
    }

    protected function processSuccess(Result $result): void
    {
        $target = $result->target;
        $state = $target->state;
        $targetId = $target->identifier;
        $name = $result->scenarioName;
        $isHealthCheck = $name === self::HEALTH_CHECK_SCENARIO;
        // $target points to our target object, it's state is still the former one!
        if ($state !== TargetState::REACHABLE && $isHealthCheck) {
            $formerState = $target->state;
            $target->state = TargetState::REACHABLE;
            // TODO: emit db update -> state
            // TODO: $result->target->error = null;

            if ($formerState !== TargetState::PENDING) {
                $this->logger->notice(sprintf(
                    'Target was %s, and is now reachable: %s (%s)',
                    $formerState->value,
                    $target->address->ip,
                    $name
                ));
            }
            $this->redisTables->setTableEntry('snmp_target_health', $targetId, ['uuid'], [
                'uuid'  => $targetId,
                'state' => TargetState::REACHABLE->value,
            ]);
            // TODO: $result->target->error = $result->error;
            // TODO: set failing on the third attempt only
        }

        $deviceUuid = RamseyUuid::fromString($targetId);
        $resultHandler = $this->scenarioResultHandlers[$name];
        if ($this->scenarioRequestType[$name] === 'get') {
            try {
                $this->sendResultToRedis($resultHandler, $resultHandler->getResultObjectInstance(
                    $this->nodeIdentifier->uuid,
                    $deviceUuid,
                    $result->result
                ));
            } catch (Throwable $e) {
                $this->logger->error('GET Failed: ' . $e->getMessage());
            }
        } else {
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
            }
            try {
                if ($measurements = $resultHandler->prepareMeasurements($deviceUuid, $instances)) {
                    $this->events->emit(MetricsEvent::ON_MEASUREMENTS, [$measurements]);
                }
            } catch (Throwable $e) {
                $this->logger->notice('Measurements failed: ' . $e->getMessage());
            }
        }
    }

    protected function processFailure(Result $result): void
    {
        $targetId = $result->target->identifier;
        $state = $result->target->state;
        $name = $result->scenarioName;
        $isHealthCheck = $name === self::HEALTH_CHECK_SCENARIO;
        if (!$isHealthCheck) {
            $this->logger->notice("Scenario failed: $targetId ($name)");
            return;
        }
        $this->health->setCurrentResult($targetId, $state);
        if ($state !== TargetState::FAILING) {
            $this->logger->notice("Scenario failed, setting failing: $targetId ($name)");
            $result->target->state = TargetState::FAILING;
            $this->redisTables->setTableEntry('snmp_target_health', $targetId, ['uuid'], [
                'uuid' => $targetId,
                'state' => TargetState::FAILING->value,
            ]);
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
        } catch (\Exception $e) {
            $this->logger->error('Setting Redis table failed: ' . $e->getMessage());
        }
    }

    protected function sendResultToRedis(ScenarioResultHandler $handler, object $instance): void
    {
        if ($update = $handler->prepareDbUpdate($instance)) {
            try {
                $result = $this->redisTables->setTableEntry(...$update);
            } catch (\Exception $e) {
                $this->logger->error('Updating Redis table failed: ' . $e->getMessage());
            }
        }
    }
}
