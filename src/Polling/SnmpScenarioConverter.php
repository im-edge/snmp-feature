<?php

namespace IMEdge\SnmpFeature\Polling;

class SnmpScenarioConverter
{
    public static function dumpPhpBasedToJsonString(): string
    {
        $string = "[\n";
        $first = true;
        foreach (\IMEdge\SnmpFeature\Scenario\ScenarioRegistry::CLASSES as $class) {
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
