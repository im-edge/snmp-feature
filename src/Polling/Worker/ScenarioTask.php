<?php

namespace IMEdge\SnmpFeature\Polling\Worker;

use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;

class ScenarioTask
{
    public function __construct(
        public readonly ScenarioDefinition $scenario,
        public readonly SnmpTargets $targets,
    ) {
    }
}
