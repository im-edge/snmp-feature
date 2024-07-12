<?php

namespace IMEdge\SnmpFeature\DataStructure;

use Attribute;
use IMEdge\Metrics\MetricDatatype;

#[Attribute]
class Metric
{
    public function __construct(
        public readonly string $metricName,
        public readonly MetricDatatype $dataType = MetricDatatype::GAUGE,
        public readonly ?string $unit = null,
    ) {
    }
}
