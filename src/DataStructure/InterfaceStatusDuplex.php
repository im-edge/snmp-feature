<?php

namespace IMEdge\SnmpFeature\DataStructure;

#[Oid('1.3.6.1.2.1.10.7.2.1.19')]
enum InterfaceStatusDuplex: int implements EnumInterface
{
    case UNKNOWN     = 1;
    case HALF_DUPLEX = 2;
    case FULL_DUPLEX = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::UNKNOWN     => 'unknown',
            self::HALF_DUPLEX => 'halfDuplex',
            self::FULL_DUPLEX => 'fullDuplex',
        };
    }
}
