<?php

namespace IMEdge\SnmpFeature\DataStructure;

// Mib: SNMPv2-TC::StorageType
enum StorageType: int implements EnumInterface
{
    case OTHER        = 1;
    case VOLATILE     = 2;
    case NON_VOLATILE = 3;
    case PERMANENT    = 4;
    case READ_ONLY    = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::OTHER        => 'other',
            self::VOLATILE     => 'volatile',
            self::NON_VOLATILE => 'nonVolatile',
            self::PERMANENT    => 'permanent',
            self::READ_ONLY    => 'readOnly',
        };
    }
}
