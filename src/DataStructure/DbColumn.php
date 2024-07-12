<?php

namespace IMEdge\SnmpFeature\DataStructure;

use Attribute;

#[Attribute]
class DbColumn
{
    public function __construct(
        public readonly string $columnName,
    ) {
    }
}
