<?php

namespace IMEdge\SnmpFeature\DataStructure\Icom;

use IMEdge\SnmpFeature\DataStructure\EnumInterface;
use IMEdge\SnmpFeature\DataStructure\Oid;

#[Oid('1.3.6.1.4.1.1807.112.1.4.1.5')]
enum IcomWmacBsTsStatusDiuc: int implements EnumInterface
{
    case MODE_4QAM_LOW     = 1;
    case MODE_4QAM_MED     = 2;
    case MODE_4QAM_HIGH    = 3;

    case MODE_16QAM_LOW    = 4;
    case MODE_16QAM_HIGH   = 5;

    case MODE_64QAM        = 6;
    case MODE_128QAM       = 7;
    case MODE_256QAM       = 8;
    case MODE_512QAM       = 9;

    case MODE_1024QAM_LOW  = 10;
    case MODE_1024QAM_HIGH = 11;

    public function getLabel(): string
    {
        return match ($this) {
            self::MODE_4QAM_LOW     => '4-QAM low',
            self::MODE_4QAM_MED     => '4-QAM med',
            self::MODE_4QAM_HIGH    => '4-QAM high',
            self::MODE_16QAM_LOW    => '16-QAM low',
            self::MODE_16QAM_HIGH   => '16-QAM high',
            self::MODE_64QAM        => '64-QAM',
            self::MODE_128QAM       => '128-QAM',
            self::MODE_256QAM       => '256-QAM',
            self::MODE_512QAM       => '512-QAM',
            self::MODE_1024QAM_LOW  => '1024-QAM low',
            self::MODE_1024QAM_HIGH => '1024-QAM high',
        };
    }
}
