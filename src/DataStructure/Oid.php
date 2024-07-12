<?php

namespace IMEdge\SnmpFeature\DataStructure;

use Attribute;
use Stringable;

#[Attribute]
class Oid implements Stringable
{
    public function __construct(
        public readonly string $oid
    ) {
    }

    public function __toString(): string
    {
        return $this->oid;
    }
}
