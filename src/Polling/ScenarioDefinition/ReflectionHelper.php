<?php

namespace IMEdge\SnmpFeature\Polling\ScenarioDefinition;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

class ReflectionHelper
{
    /**
     * @param class-string $interface
     * @return ?array{0: class-string, 1: array<int|string, mixed>}
     */
    public static function getAttributeByInterface(
        ReflectionProperty|ReflectionClass $property,
        string $interface
    ): ?array {
        $values = self::getAttributesByInterface($property, $interface);
        if (count($values) === 0) {
            return null;
        }

        if (count($values) === 1) {
            return $values[0];
        }

        throw new RuntimeException("Reflection: there can be only one instance of $interface");
    }

    /**
     * @param class-string $interface
     * @return array{0: class-string, 1: array<int, mixed>}
     */
    public static function requireAttributeByInterface(
        ReflectionProperty|ReflectionClass $property,
        string $interface
    ): array {
        return self::getAttributeByInterface($property, $interface)
            ?? throw new RuntimeException("Reflection: there is no $interface");
    }

    /**
     * @param class-string $interface
     */
    public static function getAttributesByInterface(
        ReflectionProperty|ReflectionClass $property,
        string $interface
    ): array {
        $result = [];
        foreach (
            $property->getAttributes(
                $interface,
                ReflectionAttribute::IS_INSTANCEOF
            ) as $attribute
        ) {
            $result[] = [$attribute->getName(), $attribute->getArguments()];
        }

        return $result;
    }

    /**
     * @param class-string $interface
     */
    public static function getAttributeInstanceByInterface(
        ReflectionProperty|ReflectionClass $property,
        string $interface
    ): ?object {
        $values = self::getAttributeInstancesByInterface($property, $interface);
        if (count($values) === 0) {
            return null;
        }

        if (count($values) === 1) {
            return $values[0];
        }

        throw new RuntimeException("Reflection: there can be only one instance of $interface");
    }

    /**
     * @param class-string $interface
     */
    public static function getAttributeInstancesByInterface(
        ReflectionProperty|ReflectionClass $property,
        string $interface
    ): array {
        $result = [];
        foreach (
            $property->getAttributes(
                $interface,
                ReflectionAttribute::IS_INSTANCEOF
            ) as $attribute
        ) {
            $result[] = $attribute->newInstance();
        }

        return $result;
    }
}
