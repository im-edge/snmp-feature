<?php

namespace IMEdge\SnmpFeature;

use Exception;
use IMEdge\SnmpFeature\Scenario\PollingTask;
use IMEdge\SnmpFeature\Scenario\ScenarioResultHandler;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Throwable;

class PeriodicScenario
{
    public readonly string $name;
    public readonly float $interval;
    public readonly string $requestType;
    public readonly ScenarioResultHandler $resultHandler;
    public readonly RequestedOidList $oidList;
    protected ReflectionClass $reflection;

    public function __construct(
        public readonly string $scenarioClass,
        public readonly SnmpTargets $targets,
        protected readonly LoggerInterface $logger,
    ) {
        $this->reflection = new ReflectionClass($this->scenarioClass);
        $name = null;
        foreach ($this->reflection->getAttributes(PollingTask::class) as $attribute) {
            /** @var PollingTask $task */
            $task = $attribute->newInstance();
            $name = $task->name;
            $this->interval = $task->defaultInterval ?: 600;
        }
        if ($name === null) {
            throw new RuntimeException('PeriodicScenario expects a PollingTask, got ' . $this->scenarioClass);
        }
        $this->name = $name;
        $this->resultHandler = new ScenarioResultHandler($name, $this->reflection, $this->logger);

        if ($this->resultHandler->needsWalk()) {
            $this->requestType = 'walk';
        } else {
            $this->requestType = 'get';
        }
        $this->oidList = new RequestedOidList($this->resultHandler->getScenarioOids());
    }
}
