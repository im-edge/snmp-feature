<?php

namespace IMEdge\SnmpFeature\DataStructure;

class SnmpTableIndex
{
    public function __construct(
        public string $name,
        public Oid $oid,
        public bool $implicit = true,
        public readonly ?int $length = 1,
    ) {
    }
}
