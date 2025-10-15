<?php

namespace IMEdge\SnmpFeature\DataStructure;

class SpecialValueRegistry
{
    protected const TYPE_MAP = [
        'deviceIdentifier'    => DeviceIdentifier::class,
        'snmpTableIndexValue' => SnmpTableIndexValue::class,
        'nodeIdentifier'      => DataNodeIdentifier::class,
    ];

    /**
     * @return class-string<SpecialValueInterface>
     */
    public static function getClass(string $shortName): string
    {
        return self::TYPE_MAP[$shortName] ?? throw new \RuntimeException(sprintf(
            'There is no SpecialValue implementation for "%s"',
            $shortName
        ));
    }

    /**
     * @param class-string<SpecialValueInterface> $className
     */
    public static function getShortName(string $className): string
    {
        $result = array_search($className, self::TYPE_MAP, true);

        if ($result === false) {
            throw new \RuntimeException(sprintf(
                'There is no SpecialValue shortcut for "%s"',
                $className
            ));
        }

        return $result;
    }
}
