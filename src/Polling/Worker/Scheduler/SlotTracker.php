<?php

namespace IMEdge\SnmpFeature\Polling\Worker\Scheduler;

/**
 * Monotonic clock should be preferred, but we want slots to be reliable and keep
 * their interval even after the system reboots.
 */
class SlotTracker
{
    /** @var int unix timestamp, first possible tick before boot time */
    public readonly int $firstTick;
    protected float $timePerSlot;

    public function __construct(
        protected int $slotCount,
        protected int $interval,
        protected float $bootTimeMs,
        protected int $tsOffset = 0,
        protected ?int $currentSlot = null,
    ) {
        $firstTick = (int) floor(($bootTimeMs / 1000) / $interval) * $interval + $tsOffset;
        if ($firstTick * 1000 > $this->bootTimeMs) {
            $firstTick -= $this->interval;
        }

        $this->firstTick = $firstTick;
        $this->timePerSlot = $this->interval / $this->slotCount;
    }

    public function advance(?float $hrTimeNow = null): ?int
    {
        $hrTimeNow ??= hrtime(true);
        $currentSlot = $this->getSlotForHrTime($hrTimeNow);

        if ($this->currentSlot === null) {
            return $this->currentSlot = $currentSlot;
        }
        if ($currentSlot === $this->currentSlot) {
            return null;
        }
        if ($this->currentSlot < $currentSlot) {
            $diff = $currentSlot - $this->currentSlot;
        } else {
            $diff = $currentSlot + $this->slotCount - $this->currentSlot;
        }
        if ($diff > 1 && $diff < 5) {
            // diff > 1? Hiccup, try to catch up - but only, if we didn't lose more than 3 slots
            // printf("Trying to catch up, we lost %d slots\n", $diff - 1);
            $currentSlot = $this->currentSlot + 1;
            if ($currentSlot >= $this->slotCount) {
                $currentSlot = 0;
            }
        }

        return $this->currentSlot = $currentSlot;
    }

    public function getSlotForHrTime(float $hrTime): int
    {
        // total offset
        $tickOffset = $this->bootTimeMs / 1000 + $hrTime / 1_000_000_000 - $this->firstTick;
        // relative offset
        $tickOffset -= floor($tickOffset / $this->interval) * $this->interval;

        return (int) floor($tickOffset / $this->timePerSlot);
    }
}
