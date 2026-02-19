<?php

namespace IMEdge\SnmpFeature\Polling\ScenarioDefinition;

use IMEdge\Json\JsonSerialization;
use IMEdge\Metrics\Metric;
use IMEdge\Metrics\MetricDatatype;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;

class MetricDefinition implements JsonSerialization
{
    public function __construct(
        public readonly string $metricName,
        public readonly MetricDatatype $dataType = MetricDatatype::GAUGE,
        public readonly ?string $unit = null,
    ) {
    }

    public function createMetric(string $key, ?VarBindValue $value): Metric
    {
        return new Metric($key, $value->getReadableValue(), $this->dataType, $this->unit);
    }

    public static function fromSerialization($any): MetricDefinition
    {
        return new MetricDefinition(
            $any[0],
            isset($any[1]) ? MetricDatatype::from($any[1]) : MetricDatatype::GAUGE,
            $any[2] ?? null
        );
    }

    public function jsonSerialize(): array
    {
        if ($this->unit === null) {
            return [$this->metricName, $this->dataType];
        } else {
            return [$this->metricName, $this->dataType, $this->unit];
        }
    }
}
