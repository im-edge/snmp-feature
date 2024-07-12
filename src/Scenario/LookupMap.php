<?php

namespace IMEdge\SnmpFeature\Scenario;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class LookupMap
{
    public function __construct(
        public readonly string $mapName,
        public readonly string $keyProperty,
        public readonly string $valueProperty,
    ) {
    }
}
