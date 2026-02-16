<?php

namespace IMEdge\SnmpFeature\DataStructure;

use IMEdge\Json\JsonSerialization;

class SnmpTableIndex implements JsonSerialization
{
    public function __construct(
        public string $name,
        public Oid $oid,
        public bool $implicit = true,
        public readonly ?int $length = 1,
    ) {
    }

    /**
     * @return static|SnmpTableIndex
     */
    public static function fromSerialization($any): SnmpTableIndex
    {
        return new SnmpTableIndex($any[0], new Oid($any[1]), $any[2], $any[3]);
    }

    public function jsonSerialize(): array
    {
        return [$this->name, (string) $this->oid, $this->implicit, $this->length];
    }
}
