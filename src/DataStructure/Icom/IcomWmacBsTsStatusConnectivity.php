<?php

namespace IMEdge\SnmpFeature\DataStructure\Icom;

use IMEdge\SnmpFeature\DataStructure\EnumInterface;
use IMEdge\SnmpFeature\DataStructure\Oid;

#[Oid('1.3.6.1.4.1.1807.112.1.4.1.1')]
enum IcomWmacBsTsStatusConnectivity: int implements EnumInterface
{
    case DOWN_INIT       = 1;
    case RANGING         = 2;
    case RANGING_SUCCESS = 3;
    case REM_REQ         = 4;
    case CONNECTED       = 5;
    case STATE_RANGED    = 6;
    case STATE_INVALID  = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::DOWN_INIT       => 'TS is disconnected',
            self::RANGING         => 'TS is being ranged',
            self::RANGING_SUCCESS => 'Ranging procedure successful',
            self::REM_REQ         => 'A TS remove procedure is under way',
            self::CONNECTED       => 'TS is connected',
            self::STATE_RANGED    => 'TS is connected but has not reached specified PHY modulations',
            self::STATE_INVALID   => 'Invalid state',
        };
    }
}
