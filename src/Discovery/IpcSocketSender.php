<?php

namespace IMEdge\SnmpFeature\Discovery;

use RuntimeException;
use Socket;

class IpcSocketSender
{
    /**
     * @param resource|Socket $socket
     */
    public static function sendSocket($socketToSend, string $targetUnixSocket): void
    {
        $target = self::connectToUnixSocket($targetUnixSocket);
        $msg = [
            'iov' => ['Just a socket, I have nothing to tell'],
            'control' => [
                0 => [
                    'level' => SOL_SOCKET,
                    'type'  => SCM_RIGHTS, // control message type,  send or receive a set of open file descriptors
                    'data'  => [0 => $socketToSend] // Set of file descriptors
                ]
            ],
        ];

        if (false === socket_sendmsg($target, $msg)) { // TODO: check flags
            throw new RuntimeException("Failed sending socket to $targetUnixSocket");
        }
    }

    protected static function connectToUnixSocket(string $unixSocketPath): Socket
    {
        $unixSocket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        if ($unixSocket === false) {
            throw new RuntimeException(sprintf(
                'Failed to create Unix socket: %s',
                socket_strerror(socket_last_error())
            ));
        }

        if (!socket_connect($unixSocket, $unixSocketPath)) {
            throw new RuntimeException(sprintf(
                "Failed connecting to Unix socket %s: %s",
                $unixSocketPath,
                socket_strerror(socket_last_error($unixSocket))
            ));
        }

        return $unixSocket;
    }
}
