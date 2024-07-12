<?php

namespace IMEdge\SnmpFeature;

use function hexdec;
use function sha1;
use function substr;

class PeriodicScenarioSlots
{
    /** @var array<int, PeriodicScenarioSingleRequest[]> */
    protected array $slots = [];

    public function __construct(
        public readonly int $slotCount,
        PeriodicScenario $scenario
    ) {
        foreach ($scenario->targets->targets as $target) {
            $slot = hexdec(substr(sha1($scenario->name . $target->address->toUdpUri()), 0, 15)) % $slotCount;
            $this->slots[$slot][] = new PeriodicScenarioSingleRequest(
                $target,
                $scenario->oidList,
                $scenario->requestType
            );
        }
    }

    /**
     * @return PeriodicScenarioSingleRequest[]
     */
    public function getSlot(int $idx): array
    {
        return $this->slots[$idx] ?? [];
    }
}
