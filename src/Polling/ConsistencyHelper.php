<?php

namespace IMEdge\SnmpFeature\Polling;

use Ramsey\Uuid\UuidInterface;

class ConsistencyHelper
{
    public static function uuidToNumber(UuidInterface $uuid): int
    {
        return self::uuidStringToNumber($uuid->toString());
    }

    public static function uuidStringToNumber(string $toString): int
    {
        // Still fit's into positive 64bit number:
        return hexdec(substr($toString, 0, 15));
    }
}
