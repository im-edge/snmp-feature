<?php

namespace IMEdge\SnmpFeature\DataStructure;

#[Oid('1.3.6.1.2.1.2.2.1.7')]
enum InterfaceStatusConfigured: int implements EnumInterface
{
    case UP      = 1;
    case DOWN    = 2;
    case TESTING = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::UP      => 'up',
            self::DOWN    => 'down',
            self::TESTING => 'testing',
        };
    }
}
