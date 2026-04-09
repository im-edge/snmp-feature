<?php

namespace IMEdge\SnmpFeature\Polling\Worker\Scheduler;

use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ConsistencyHelper;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use Ramsey\Uuid\Uuid;

class SnmpTargetSlots
{
    /**
     * @return array<int, string>
     */
    public static function calculate(
        SnmpTargets $targets,
        ScenarioDefinition $scenario,
        int $slotCount,
    ): array {
        $slots = [];

        foreach ($targets->targets as $target) {
            if (!$target->wants($scenario)) {
                continue;
            }
            $slot = ConsistencyHelper::uuidToNumber(
                Uuid::uuid5($scenario->uuid, $target->identifier)
            ) % $slotCount;
            if (array_key_exists($slot, $slots)) {
                $slots[$slot] .= ',' . $target->identifier;
            } else {
                $slots[$slot] = $target->identifier;
            }
        }

        return $slots;
    }
}
