<?php

namespace IMEdge\SnmpFeature\Polling\ScenarioDefinition;

use IMEdge\Json\JsonSerialization;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;

class SnmpTableIndexes implements JsonSerialization
{
    /**
     * @param SnmpTableIndex[] $indexes
     */
    public function __construct(
        public readonly array $indexes,
    ) {
    }

    public static function fromSerialization($any): SnmpTableIndexes
    {
        return new SnmpTableIndexes(array_map(SnmpTableIndex::fromSerialization(...), $any));
    }

    public function jsonSerialize(): array
    {
        return $this->indexes;
    }
}
