<?php

namespace IMEdge\SnmpFeature\Discovery;

use RuntimeException;

use function sprintf;
use function stream_context_create;
use function stream_set_blocking;
use function stream_set_read_buffer;
use function stream_socket_get_name;
use function stream_socket_server;
use function strrpos;
use function substr;

class DiscoveryUdpSocket
{
    /**
     * @return resource
     */
    public static function create(int $port = 0)
    {
        $context = stream_context_create([
            'socket' => [
                'so_reuseaddr' => true,
                'so_reuseport' => true,
            ]
        ]);
        $uri = "udp://0.0.0.0:$port";
        $server = stream_socket_server($uri, $errNo, $errStr, STREAM_SERVER_BIND, $context);
        if (!$server || $errNo) {
            throw new RuntimeException(
                sprintf('Could not create datagram %s: [Error: #%d] %s', $uri, $errNo, $errStr),
                $errNo
            );
        }

        stream_set_blocking($server, false);
        stream_set_read_buffer($server, 0);

        return $server;
    }

    /**
     * @param resource $resource
     */
    public static function getResourceStreamPort($resource): int
    {
        $socketName = stream_socket_get_name($resource, false);
        if ($socketName === false) {
            throw new RuntimeException('DiscoveryRunner failed to open UDP socket');
        }

        return (int) substr($socketName, strrpos($socketName, ':') + 1);
    }
}
