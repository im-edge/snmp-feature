<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

class PropertySet
{
    public function __construct(
        public readonly array $requiredTables = [],
        public readonly array $optionalTables = [],
    ) {
    }
}
