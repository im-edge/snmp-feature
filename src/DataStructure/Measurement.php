<?php

namespace IMEdge\SnmpFeature\DataStructure;

use Attribute;

#[Attribute]
class Measurement
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $instanceProperty
    ) {
    }
}
