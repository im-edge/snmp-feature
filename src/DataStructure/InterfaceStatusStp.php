<?php

namespace IMEdge\SnmpFeature\DataStructure;

#[Oid('1.3.6.1.2.1.17.2.15.1.3')]
enum InterfaceStatusStp: int implements EnumInterface
{
    case DISABLED   = 1;
    case BLOCKING   = 2;
    case LISTENING  = 3;
    case LEARNING   = 4;
    case FORWARDING = 5;
    case BROKEN     = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::DISABLED   => 'unknown',
            self::BLOCKING   => 'blocking',
            self::LISTENING  => 'listening',
            self::LEARNING   => 'learning',
            self::FORWARDING => 'forwarding',
            self::BROKEN     => 'broken',
        };
    }
}
