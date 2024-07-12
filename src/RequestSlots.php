<?php

namespace IMEdge\SnmpFeature;

use function hexdec;
use function sha1;
use function substr;

class RequestSlots
{
    protected const SALT = 'A_PREFIX'; // TODO: Different prefix per scenario

    /** @var array<int, SinglePeriodicRequest[]> */
    protected array $slots = [];

    public function __construct(
        public readonly int $slotCount,
        PeriodicRequest $request
    ) {
        foreach ($request->targets->targets as $target) {
            $slot = hexdec(substr(sha1(self::SALT . $target->address->toUdpUri()), 0, 15)) % $slotCount;
            $this->slots[$slot][] = new SinglePeriodicRequest(
                $target,
                $request->oidList,
                'get'
            );
        }
    }

    /**
     * @return SinglePeriodicRequest[]
     */
    public function getSlot(int $idx): array
    {
        return $this->slots[$idx] ?? [];
    }
}
