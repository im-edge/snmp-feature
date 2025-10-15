<?php

namespace IMEdge\SnmpFeature;

enum SnmpVersion: string
{
    case v1  = '1';
    case v2c = '2c';
    case v3  = '3';

    public function toNewVersion(): \IMEdge\SnmpPacket\SnmpVersion
    {
        return match ($this) {
            self::v1 => \IMEdge\SnmpPacket\SnmpVersion::v1,
            self::v2c => \IMEdge\SnmpPacket\SnmpVersion::v2c,
            self::v3 => \IMEdge\SnmpPacket\SnmpVersion::v3,
        };
    }
}
