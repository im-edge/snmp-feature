<?php

namespace IMEdge\SnmpFeature;

use Amp\Socket\InternetAddress;
use IMEdge\Json\JsonSerialization;
use Throwable;

class SnmpResponse implements JsonSerialization
{
    public function __construct(
        public readonly bool $success,
        public readonly InternetAddress $source,
        public readonly mixed $result = null,
        public readonly ?string $errorMessage = null,
        public readonly ?int $duration = null, // Nanoseconds
    ) {
    }

    public static function success(InternetAddress $source, int $startTime, mixed $result): SnmpResponse
    {
        return new SnmpResponse(
            success:  true,
            source:   $source,
            result:   $result,
            duration: hrtime(true) - $startTime
        );
    }

    public static function failure(InternetAddress $source, int $startTime, $reason): SnmpResponse
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
            InternetAddress::fromString($any->source),
            $any->result,
            $any->errorMessage ?? null,
            $any->duration ?? null,
        );
    }

    public function jsonSerialize(): object
    {
        $result = (object) [
            'success' => $this->success,
            'source' => (string) $this->source,
            'result' => $this->result,
        ];
        if ($this->errorMessage) {
            $result->errorMessage = $this->errorMessage;
        }
        if ($this->duration) {
            $result->duration = $this->duration;
        }

        return $result;
    }
}
