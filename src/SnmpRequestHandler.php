<?php

namespace IMEdge\SnmpFeature;

use IMEdge\Snmp\SocketAddress;

use function hrtime;

final class SnmpRequestHandler
{
    public static function handleRemoteResult($result, float $startTime, SocketAddress $source): SnmpResponse
    {
        return new SnmpResponse(
            success:  true,
            source:   $source,
            result:   $result,
            duration: hrtime(true) - $startTime
        );
    }

    public static function handleRemoteFailure($reason, float $startTime, SocketAddress $source): SnmpResponse
    {
        $duration = hrtime(true) - $startTime;
        if ($reason instanceof \Throwable) {
            $reason = $reason->getMessage();
        }
        return new SnmpResponse(
            success:      false,
            source:       $source,
            errorMessage: is_string($reason) ? $reason : var_export($reason, true),
            duration:     $duration
        );
    }
}
