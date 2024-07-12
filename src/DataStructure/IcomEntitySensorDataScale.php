<?php

namespace IMEdge\SnmpFeature\DataStructure;

#[Oid('1.3.6.1.4.1.1807.30.1.1.1.2')]
enum IcomEntitySensorDataScale: int implements EnumInterface
{
    case YOCTO = 1;
    case ZEPTO = 2;
    case ATTO  = 3;
    case FEMTO = 4;
    case PICO  = 5;
    case NANO  = 6;
    case MICRO = 7;
    case MILLI = 8;
    case UNITS = 9;
    case KILO  = 10;
    case MEGA  = 11;
    case GIGA  = 12;
    case TERA  = 13;
    case EXA   = 14;
    case PETA  = 15;
    case ZETTA = 16;
    case YOTTA = 17;

    public function getLabel(): string
    {
        return match ($this) {
            self::YOCTO => 'yocto',
            self::ZEPTO => 'zepto',
            self::ATTO  => 'atto',
            self::FEMTO => 'femtp',
            self::PICO  => 'pico',
            self::NANO  => 'nano',
            self::MICRO => 'micro',
            self::MILLI => 'milli',
            self::UNITS => 'units',
            self::KILO  => 'kilo',
            self::MEGA  => 'mega',
            self::GIGA  => 'giga',
            self::TERA  => 'tera',
            self::EXA   => 'exa',
            self::PETA  => 'peta',
            self::ZETTA => 'zetta',
            self::YOTTA => 'yotta',
        };
    }
}
