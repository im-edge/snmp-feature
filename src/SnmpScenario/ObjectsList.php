<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

use IMEdge\Json\JsonSerialization;
use InvalidArgumentException;
use OutOfBoundsException;

use function array_keys;
use function is_array;
use function ksort;

class ObjectsList implements JsonSerialization
{
    /**
     * @param array<string, JsonSerialization> $members
     */
    public function __construct(
        protected array $members = [],
    ) {
        ksort($this->members);
    }

    public function keys(): array
    {
        return array_keys($this->members);
    }

    public function get(string $key): JsonSerialization
    {
        return $this->members[$key] ?? throw new OutOfBoundsException("There is no such member: $key");
    }

    public function has(string $key): bool
    {
        return isset($this->members[$key]);
    }

    public function add(string $key, JsonSerialization $member): void
    {
        $this->members[$key] = $member;
        ksort($this->members);
    }

    public function remove(string $key): void
    {
        unset($this->members[$key]);
        ksort($this->members);
    }

    public static function fromSerialization($any): ObjectsList
    {
        if (! is_array($any)) {
            throw new InvalidArgumentException('Array expected, got ' . get_debug_type($any));
        }

        return new ObjectsList($any);
    }

    public function jsonSerialize(): array
    {
        return $this->members;
    }
}
