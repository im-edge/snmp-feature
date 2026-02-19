<?php

namespace IMEdge\SnmpFeature\Polling\ScenarioDefinition;

use IMEdge\Json\JsonSerialization;
use IMEdge\SnmpFeature\Polling\Worker\ResultHandler\ProcessedScenarioProperties;

class DbTableDefinition implements JsonSerialization
{
    /**
     * @param array<string, string> $keyProperties
     */
    public function __construct(
        public readonly string $tableName,
        public readonly array $keyProperties
    ) {
    }

    /**
     * @return array{0: string, 1: string, 2: int[]|string[], 3: ?array}
     */
    public function prepareRedisTableRow(ProcessedScenarioProperties $props): array
    {
        return [
            $this->tableName,
            $this->getDbUpdateKey($props),
            $this->keyProperties,
            $props->getDbValues()
        ];
    }

    public function getDbUpdateKey(ProcessedScenarioProperties $props): string
    {
        if (count($this->keyProperties) === 1) {
            $keyProperty = $this->keyProperties[array_key_first($this->keyProperties)];
            $keyValue = $props->getDbValue($keyProperty);

            return $keyValue ? (string)$keyValue : '- no key -';
        }
        $key = [];
        foreach ($this->keyProperties as $column) {
            $value = $props->getDbValue($column);
            $key[] = (string) $value;
        }

        return implode('/', $key);
    }

    public static function fromSerialization($any): DbTableDefinition
    {
        return new DbTableDefinition($any[0], (array) $any[1]);
    }

    public function jsonSerialize(): array
    {
        return [$this->tableName, $this->keyProperties];
    }
}
