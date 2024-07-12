<?php

namespace IMEdge\SnmpFeature\DataStructure;

#[Oid('1.3.6.1.2.1.2.2.1.8')]
enum InterfaceStatusOperational: int implements EnumInterface
{
    case UP              = 1;
    case DOWN            = 2;
    case TESTING         = 3;
    case UNKNOWN         = 4;
    case DORMANT         = 5;
    case NOT_PRESENT     = 6;
    case LOWER_LAYER_DOWN = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::UP               => 'up',
            self::DOWN             => 'down',
            self::TESTING          => 'testing',
            self::UNKNOWN          => 'unknown',
            self::DORMANT          => 'dormant',
            self::NOT_PRESENT      => 'notPresent',
            self::LOWER_LAYER_DOWN => 'lowerLayerDown',
        };
    }
}
