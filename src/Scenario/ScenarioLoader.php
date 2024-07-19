<?php

namespace IMEdge\SnmpFeature\Scenario;

use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;

class ScenarioLoader
{
    /** @var array<string, class-string> */
    protected array $scenarios;

    /** @var array<string, ScenarioResultHandler> */
    protected array $resultHandler = [];

    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {
        $this->scenarios = $this->loadScenarios();
    }

    public function resultHandler(string $scenarioName): ScenarioResultHandler
    {
        return $this->resultHandler[$scenarioName] ??= new ScenarioResultHandler(
            $scenarioName,
            new ReflectionClass($this->scenarios[$scenarioName]),
            $this->logger
        );
    }

    /**
     * @return class-string
     */
    public function getClass(string $name): string
    {
        return $this->scenarios[$name] ?? throw new \RuntimeException("There is no such scenario: $name");
    }

    public function listScenarios(): array
    {
        return $this->scenarios;
    }

    protected function loadScenarios(): array
    {
        $implementations = [];
        foreach (ScenarioRegistry::CLASSES as $class) {
            try {
                $ref = new ReflectionClass($class);
            } catch (ReflectionException $e) {
                $this->logger->error("Failed to load scenario $class: " . $e->getMessage());
                continue;
            } catch (\Throwable $e) {
                // Parse error in class
                $this->logger->error("Failed to load scenario $class: " . $e->getMessage());
                continue;
            }
            foreach ($ref->getAttributes(PollingTask::class) as $attribute) {
                $arg = $attribute->getArguments();
                $name = $arg['name'] ?? $arg[0];
                $implementations[$name] = $class;
                // $this->logger->debug("Scenario loaded: $name");
            }
        }

        return $implementations;
    }
}
