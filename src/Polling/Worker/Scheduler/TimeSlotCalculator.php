<?php

namespace IMEdge\SnmpFeature\Polling\Worker\Scheduler;

class TimeSlotCalculator
{
    public static bool $debug = false;

    /**
     * This methode returns a list of slot numbers that should be triggered during a given $duration,
     * starting from $now with offset $tsOffset, given $slotCount slots that should be triggered every
     * $interval seconds
     *
     *   $slotCount: total number of slots for one iteration with length $interval
     *   $interval:  duration in seconds, in which all slots must be triggered
     *   $now:       time in seconds, determines where we are
     *   $duration:  we ship a list of all slots that should be triggered between $now + $duration
     *   $tsOffset:  shift iteration start
     *
     * TODO: test, whether this doesn't drop slots that would have happened before $tsOffset
     */
    public static function getSlots(int $slotCount, int $interval, int $now, int $duration, int $tsOffset = 0): array
    {
        $tsStart = $now + $tsOffset;
        $tsEnd = $now + $duration;
        $durationPerSlot = $interval / $slotCount;
        $relStart = $tsStart % $interval;
        $relEnd = $tsEnd % $interval;

        // first timestamp matching or before our start ts
        $current = floor($relStart / $durationPerSlot) * $durationPerSlot;
        if ($current < $relStart) {
            $current += $durationPerSlot;
        }

        $limitEnd = $relEnd;
        if ($limitEnd <= $relStart) {
            $limitEnd += $interval;
        }

        $steps = [];
        while ($current < $limitEnd) {
            // Modulo is required, because we allow the limit to overflow, but want to ship every slot only once
            // One might argue, that rolling over and continuing would be more correct - but for our use-case we
            // should never face this condition
            $slot = ((int) round($current / $durationPerSlot) % $slotCount);
            if (! in_array($slot, $steps)) {
                $steps[] = $slot;
            }
            $current += $durationPerSlot;
        }

        return $steps;
    }
}
