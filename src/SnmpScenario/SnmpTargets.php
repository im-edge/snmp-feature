<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

use Amp\Socket\InternetAddress;
use IMEdge\Json\JsonSerialization;
use Ramsey\Uuid\UuidInterface;

class SnmpTargets implements JsonSerialization
{
    public array $targets;
    /**
     * @param SnmpTarget[] $targets
     */
    public function __construct(
        array $targets = []
    ) {
        $this->targets = [];
        foreach ($targets as $target) {
            $this->add($target);
        }
    }

    public function add(SnmpTarget $target): void
    {
        $this->targets[$target->identifier] = $target;
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

    public function findForAddress(InternetAddress $address, UuidInterface $credentialUuid): ?SnmpTarget
    {
        foreach ($this->targets as $t) {
            if ($t->address->toString() === $address->toString() && $t->credentialUuid->equals($credentialUuid)) {
                return $t;
            }
        }

        return null;
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
