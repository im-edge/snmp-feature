<?php

namespace IMEdge\SnmpFeature;

use IMEdge\Snmp\SocketAddress;
use React\Promise\PromiseInterface;

use function hrtime;

final class SnmpRequestHandler
{
    public static function appendResultHandlers(
        PromiseInterface $promise,
        SocketAddress $source
    ): PromiseInterface {
        $start = hrtime(true);
        return $promise->then(function ($result) use ($start, $source) {
            return self::handleRemoteResult($result, $start, $source);
        }, function ($reason) use ($start, $source) {
            return self::handleRemoteFailure($reason, $start, $source);
        });
    }

    protected static function handleRemoteResult($result, float $startTime, SocketAddress $source): SnmpResponse
    {
        return new SnmpResponse(
            success:  true,
            source:   $source,
            result:   $result,
            duration: hrtime(true) - $startTime
        );
    }

    protected static function handleRemoteFailure($reason, float $startTime, SocketAddress $source): SnmpResponse
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
