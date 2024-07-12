<?php

namespace IMEdge\SnmpFeature\DataStructure\Icom;

use IMEdge\SnmpFeature\DataStructure\EnumInterface;
use IMEdge\SnmpFeature\DataStructure\Oid;

#[Oid('1.3.6.1.4.1.1807.112.1.3.1.2')]
enum IcomWmacBsTsCfgAdminStatus: int implements EnumInterface
{
    case DOWN    = 1;
    case UP      = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::DOWN    => 'down',
            self::UP      => 'up',
        };
    }
}
