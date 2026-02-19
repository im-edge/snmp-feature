<?php

namespace IMEdge\SnmpFeature\Polling\ScenarioDefinition;

use IMEdge\Json\JsonSerialization;
use IMEdge\Metrics\Ci;
use IMEdge\Metrics\Measurement;
use IMEdge\SnmpFeature\Polling\Worker\ResultHandler\ProcessedScenarioProperties;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;

class MeasurementDefinition implements JsonSerialization
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $instanceProperty
    ) {
    }

    public function createMeasurement(SnmpTarget $target, ProcessedScenarioProperties $props): ?Measurement
    {
        if (! $props->hasMetrics()) {
            return null;
        }

        if ($this->instanceProperty === null) {
            $instanceKey = null;
        } else {
            $instanceKey = $props->getPhpValue($this->instanceProperty);
        }

        return new Measurement(new Ci($target->identifier, $this->name, $instanceKey), time(), $props->getMetrics());
    }

    public static function fromSerialization($any): MeasurementDefinition
    {
        return new MeasurementDefinition($any[0], $any[1] ?? null);
    }

    public function jsonSerialize(): array
    {
        if ($this->instanceProperty === null) {
            return [$this->name];
        } else {
            return [$this->name, $this->instanceProperty];
        }
    }
}
