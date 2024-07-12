<?php

namespace IMEdge\SnmpFeature\DataStructure;

use Attribute;

#[Attribute]
class DbTable
{
    public function __construct(
        public readonly string $tableName,
        /**
         * db column -> instance property
         */
        public readonly array $keyProperties = []
    ) {
    }
}
