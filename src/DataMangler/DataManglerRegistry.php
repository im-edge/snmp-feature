<?php

namespace IMEdge\SnmpFeature\DataMangler;

class DataManglerRegistry
{
    /** @var class-string<SnmpDataTypeManglerInterface>[] */
    protected const MANGLER_CLASSES = [
        ExactStringLengthOrNull::class,
        LastOidOctetToInteger32::class,
        MangleToBinaryIp::class,
        MangleToUtf8::class,
        OctetStringToIp::class,
        OidToOctetString::class,
        MultiplyInteger::class,
    ];

    /** @var ?array<string, class-string<SnmpDataTypeManglerInterface>> */
    protected static ?array $manglerClassMap = null;

    /**
     * @return class-string<SnmpDataTypeManglerInterface>
     */
    public static function getClass(string $shortName): string
    {
        return self::getMap()[$shortName] ?? throw new \RuntimeException(sprintf(
            'There is no DataMangler implementation for "%s"',
            $shortName
        ));
    }

    public static function fromSerialization(array $any): SnmpDataTypeManglerInterface
    {
        return new (self::getClass($any[0]))(...($any[1] ?? []));
    }

    /**
     * @param class-string<SnmpDataTypeManglerInterface> $className
     */
    public static function getShortName(string $className): string
    {
        $result = array_search($className, self::getMap(), true);

        if ($result === false) {
            throw new \RuntimeException(sprintf(
                'There is no DataMangler shortcut for "%s"',
                $className
            ));
        }

        return $result;
    }

    /**
     * @return array<string, class-string<SnmpDataTypeManglerInterface>>
     */
    protected static function getMap(): array
    {
        return static::$manglerClassMap ??= self::prepareMap();
    }

    /**
     * @return array<string, class-string<SnmpDataTypeManglerInterface>>
     */
    protected static function prepareMap(): array
    {
        $map = [];
        foreach (self::MANGLER_CLASSES as $className) {
            $map[$className::getShortName()] = $className;
        }

        return $map;
    }
}
