<?php

namespace IMEdge\SnmpFeature\DataStructure;

enum InetAddressType: int implements EnumInterface
{
    case UNKNOWN = 0;
    case IPv4    = 1;
    case IPv6    = 2;
    case IPv4z   = 3;
    case IPv6z   = 4;
    case DNS     = 16;

    public function getLabel(): string
    {
        return match ($this) {
            // An unknown address type.  This value MUST be used if the value of the corresponding
            // InetAddress object is a zero-length string. It may also be used to indicate an IP address
            // that is not in one of the formats defined below
            self::UNKNOWN => 'unknown',
            // An IPv4 address as defined by the InetAddressIPv4 textual convention
            self::IPv4    => 'IPv4',
            // An IPv6 address as defined by the InetAddressIPv6 textual convention
            self::IPv6    => 'IPv6',
            // A non-global IPv4 address including a zone index as defined by the InetAddressIPv4z textual convention
            self::IPv4z   => 'IPv4 (non-global)',
            // A non-global IPv6 address including a zone index as defined by the InetAddressIPv6z textual convention
            self::IPv6z   => 'IPv6 (non-global)',
            // A DNS domain name as defined by the InetAddressDNS textual convention
            self::DNS     => 'DNS domain name',
        };
    }
}
