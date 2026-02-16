<?php

namespace IMEdge\SnmpFeature;

use IMEdge\Json\JsonSerialization;
use IMEdge\Snmp\SocketAddress;

class SnmpResponse implements JsonSerialization
{
    public function __construct(
        public readonly bool $success,
        public readonly SocketAddress $source,
        public readonly mixed $result = null,
        public readonly ?string $errorMessage = null,
        public readonly ?int $duration = null, // Nanoseconds
    ) {
    }

    public static function fromSerialization($any): SnmpResponse
    {
        return new SnmpResponse(
            $any->success,
            SocketAddress::fromSerialization($any->source),
            $any->result,
            $any->errorMessage ?? null,
            $any->duration ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter(get_object_vars($this), fn($v) => $v !== null);
    }

    public function jsonSerialize(): object
    {
        return (object) $this->toArray();
    }
}
