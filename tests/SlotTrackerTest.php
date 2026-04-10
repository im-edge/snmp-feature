<?php

namespace IMEdge\Tests\SnmpFeature;

use IMEdge\SnmpFeature\Polling\Worker\Scheduler\SlotTracker;
use PHPUnit\Framework\TestCase;

class SlotTrackerTest extends TestCase
{
    public function testItRunsWithNoOffset()
    {
        // System booted at 08:33:54.474 CEST
        $tracker = new SlotTracker(20, 15, 1775630034474, 0);
        // First tick with a 15s interval should have been at 08:33:45
        $this->assertEquals(1775630025, $tracker->firstTick);
    }

    public function testItRunsWithOffset()
    {
        $tracker = new SlotTracker(20, 15, 1775630034474, 10);
        // When running with a 10s offset, the first tick should have been at 08:33:40
        $this->assertEquals(1775630020, $tracker->firstTick);
    }

    public function testItCalculatesTheCorrectSlotImmediatelyAfterBootTime()
    {
        $tracker = new SlotTracker(20, 15, 1775630034474, 10);
        // with the first tick at 08:33:40, at 08:33:54.485
        // 9.485
        // boot time is 14.485 seconds after slot 0, we have one slot every 0.75s:
        $this->assertEquals(19, $tracker->getSlotForHrTime(11603865));
    }

    public function testItCalculatesTheCorrectSlotFiveSecondsAfterBootTime()
    {
        $tracker = new SlotTracker(20, 15, 1775630034474, 10);
        // 5.011 Seconds after boot it is 08:33:59.485, first tick was 08:33:40, this should be close to the end of #5
        $this->assertEquals(5, $tracker->getSlotForHrTime(5011603865)); // 5.011s after boot,
        // We have been at second 4.485 after the last slot, let's add 15ms
        $this->assertEquals(6, $tracker->getSlotForHrTime(5026603865)); // 5.026s after boot
    }

    public function testItCalculatesSomeMoreSlots()
    {
        $tracker = new SlotTracker(20, 15, 1775630034474, 10);
        $this->assertEquals(7, $tracker->getSlotForHrTime(5026603865 + 0.75 * 1 * 1_000_000_000));
        $this->assertEquals(8, $tracker->getSlotForHrTime(5026603865 + 0.75 * 2 * 1_000_000_000));
        $this->assertEquals(19, $tracker->getSlotForHrTime(5026603865 + 0.75 * 13 * 1_000_000_000));
        $this->assertEquals(0, $tracker->getSlotForHrTime(5026603865 + 0.75 * 14 * 1_000_000_000));
        $this->assertEquals(6, $tracker->getSlotForHrTime(5026603865 + 15 * 1_000_000_000));
    }
}
