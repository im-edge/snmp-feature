<?php

namespace IMEdge\SnmpFeature\Scenario;

use Attribute;

#[Attribute]
class PollingTask
{
    public function __construct(
        public readonly string $name,
        public readonly int $defaultInterval,
        public readonly ?int $defaultMaxRepetitions = null,
    ) {
    }
}
