<?php

namespace IMEdge\SnmpFeature;

use gipfl\Json\JsonSerialization;
use IMEdge\Snmp\SocketAddress;

class SnmpResponse implements JsonSerialization
{
    public function __construct(
        public readonly bool $success,
        public readonly SocketAddress $source,
        public readonly mixed $result = null,
        public readonly ?string $errorMessage = null,
        public readonly ?float $duration = null,
    ) {
    }

    public static function fromSerialization($any): SnmpResponse
    {
        return new SnmpResponse(...(array) $any);
    }

    public function jsonSerialize(): object
    {
        return (object) array_filter(get_object_vars($this), fn($v) => $v !== null);
    }
}
