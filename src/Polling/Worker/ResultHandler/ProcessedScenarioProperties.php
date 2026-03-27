<?php

namespace IMEdge\SnmpFeature\Polling\Worker\ResultHandler;

use IMEdge\Metrics\Metric;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;

/**
 * All processed properties of a single scenario call
 *
 * Instantiated once we process a result row, filled property by property
 */
class ProcessedScenarioProperties
{
    /** @var array<string, ?VarBindValue> VarBind values indexed by property name */
    public array $properties = [];
    /** @var array<string, mixed> values indexed by property name, PHP data types */
    public array $phpValues = [];
    /** @var array<string, Metric> Metric values indexed by property name */
    public array $metrics = [];
    public array $dbValues = [];

    public function __construct()
    {
    }

    public function getValue(string $property, ?VarBindValue $default = null): ?VarBindValue
    {
        return $this->properties[$property] ?? $default;
    }

    public function setValue(string $property, ?VarBindValue $value): void
    {
        $this->properties[$property] = $value;
    }

    public function setPhpValue(string $property, mixed $value): void
    {
        $this->phpValues[$property] = $value;
    }

    public function getPhpValue(string $property, mixed $default = null): mixed
    {
        return $this->phpValues[$property] ?? $default;
    }

    public function setDbValue(string $name, mixed $value): void
    {
        $this->dbValues[$name] = $value;
    }

    public function getDbValue(string $property, mixed $default = null): mixed
    {
        return $this->dbValues[$property] ?? $default;
    }

    public function getDbValues(): ?array
    {
        if (empty($this->dbValues)) {
            return null;
        }

        return $this->dbValues;
    }

    public function hasDbValues(): bool
    {
        return ! empty($this->dbValues);
    }

    public function setMetric(string $property, Metric $value): void
    {
        $this->metrics[$property] = $value;
    }

    /**
     * @return Metric[]
     */
    public function getMetrics(): array
    {
        return array_values($this->metrics);
    }

    public function hasMetrics(): bool
    {
        return ! empty($this->metrics);
    }
}
