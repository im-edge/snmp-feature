<?php

namespace IMEdge\SnmpFeature\Polling\ScenarioDefinition;

use IMEdge\Json\JsonString;

class ScenarioDefinitionLoader
{
    /**
     * @return ScenarioDefinition[]
     */
    public static function fromJsonString(string $json): array
    {
        $scenarios = [];

        foreach (JsonString::decode($json) as $scenario) {
            $scenarios[$scenario->uuid] = ScenarioDefinition::fromSerialization($scenario);
        }

        return $scenarios;
    }

    /**
     * @return ScenarioDefinition[]
     */
    public static function fromJsonFile(string $filename): array
    {
        return self::fromJsonString(file_get_contents($filename));
    }
}
