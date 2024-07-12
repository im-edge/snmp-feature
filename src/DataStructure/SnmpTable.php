<?php

namespace IMEdge\SnmpFeature\DataStructure;

use Attribute;

#[Attribute]
class SnmpTable
{
    /**
     * @param SnmpTableIndex[] $indexes
     */
    public function __construct(
        public readonly array $indexes,
    ) {
    }
}
