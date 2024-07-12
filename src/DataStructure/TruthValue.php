<?php

namespace IMEdge\SnmpFeature\DataStructure;

// SNMPv2-TC::TruthValue
enum TruthValue: int implements EnumInterface
{
    case TRUE  = 1;
    case FALSE = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::TRUE  => 'true',
            self::FALSE => 'false',
        };
    }
}
