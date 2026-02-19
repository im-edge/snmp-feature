<?php

namespace IMEdge\SnmpFeature\Polling\ScenarioDefinition;

use IMEdge\SnmpFeature\Scenario\ScenarioRegistry;

class SnmpScenarioConverter
{
    public static function dumpPhpBasedToJsonString(): string
    {
        $string = "[\n";
        $first = true;
        foreach (ScenarioRegistry::CLASSES as $class) {
            $ref = ScenarioReflection::scenario($class);
            if ($first) {
                $string .= "    ";
            } else {
                $string .= ",\n    ";
            }
            $string .= json_encode($ref);
            $first = false;
        }
        $string .= "\n]\n";

        return $string;
    }
}
