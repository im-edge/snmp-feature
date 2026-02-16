<?php

namespace IMEdge\SnmpFeature\DataMangler;

abstract class SimpleSnmpDataMangler implements SnmpDataTypeManglerInterface
{
    public const SHORT_NAME = 'unnamedSimpleDataMangler';

    public static function getShortName(): string
    {
        return static::SHORT_NAME;
    }

    protected function serializeSettings(): array
    {
        return [];
    }

    public function jsonSerialize(): array
    {
        $settings = self::serializeSettings();
        if (empty($settings)) {
            return [static::SHORT_NAME];
        }

        return [static::SHORT_NAME, $settings];
    }
}
