<?php

namespace IMEdge\SnmpFeature\DataStructure;

use Attribute;

#[Attribute]
class SnmpTableIndexValue
{
    public function __construct(
        public readonly string $indexName,
    ) {
    }
}
