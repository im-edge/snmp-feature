<?php

namespace IMEdge\SnmpFeature;

use IMEdge\Json\JsonSerialization;
use IMEdge\Snmp\SocketAddress;
use Throwable;

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

    public static function success(SocketAddress $source, int $startTime, mixed $result): SnmpResponse
    {
        return new SnmpResponse(
            success:  true,
            source:   $source,
            result:   $result,
            duration: hrtime(true) - $startTime
        );
    }

    public static function failure(SocketAddress $source, int $startTime, $reason): SnmpResponse
    {
        $duration = hrtime(true) - $startTime;
        if ($reason instanceof Throwable) {
            $reason = $reason->getMessage();
        }
        return new SnmpResponse(
            success:      false,
            source:       $source,
            errorMessage: is_string($reason) ? $reason : var_export($reason, true),
            duration:     $duration
        );
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
