<?php

namespace IMEdge\SnmpFeature\DataStructure;

enum EntityPhysicalClass: int implements EnumInterface
{
    case OTHER         = 1;
    case UNKNOWN       = 2;
    case CHASSIS       = 3;
    case BACKPLANE     = 4;
    case CONTAINER     = 5;
    case POWER_SUPPLY  = 6;
    case FAN           = 7;
    case SENSOR        = 8;
    case MODULE        = 9;
    case PORT          = 10;
    case STACK         = 11;
    case CPU           = 12;
    case ENERGY_OBJECT = 13;
    case BATTERY       = 14;
    case STORAGE_DRIVE = 15;

    public function getLabel(): string
    {
        return match ($this) {
            self::OTHER         => 'other',
            self::UNKNOWN       => 'unknown',
            self::CHASSIS       => 'chassis',
            self::BACKPLANE     => 'backplane',
            self::CONTAINER     => 'container',
            self::POWER_SUPPLY  => 'powerSupply',
            self::FAN           => 'fan',
            self::SENSOR        => 'sensor',
            self::MODULE        => 'module',
            self::PORT          => 'port',
            self::STACK         => 'stack',
            self::CPU           => 'cpu',
            self::ENERGY_OBJECT => 'energyObject',
            self::BATTERY       => 'battery',
            self::STORAGE_DRIVE => 'storageDrive',
        };
    }
}
