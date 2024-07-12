<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

use gipfl\Json\JsonSerialization;

class SnmpTargets implements JsonSerialization
{
    /**
     * @param SnmpTarget[] $targets
     */
    public function __construct(
        public readonly array $targets = []
    ) {
    }

    public static function fromSerialization($any): SnmpTargets|static
    {
        $targets = [];
        foreach ((array) $any as $item) {
            $target = SnmpTarget::fromSerialization($item);
            $targets[$target->identifier] = SnmpTarget::fromSerialization($item);
        }

        return new static($targets);
    }

    /**
     * @param SnmpTargets $newTargets
     * @return SnmpTarget[]
     */
    public function listRemovedTargets(SnmpTargets $newTargets): array
    {
        return array_diff_key($this->targets, $newTargets->targets);
    }

    public function jsonSerialize(): object
    {
        return (object) $this->targets;
    }
}
