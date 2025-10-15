<?php

namespace IMEdge\SnmpFeature\DataStructure;

use Attribute;

#[Attribute]
class SnmpTableIndexValue implements SpecialValueInterface
{
    public function __construct(
        public readonly string $indexName,
    ) {
    }
}
