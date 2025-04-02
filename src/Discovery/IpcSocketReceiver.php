<?php

namespace IMEdge\SnmpFeature\Discovery;

use Amp\DeferredFuture;
use Amp\TimeoutException;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use RuntimeException;
use Socket;

class IpcSocketReceiver
{
    protected ?Socket $unixSocket = null;
    protected ?string $unixSocketPath = null;

    public function __construct(
        protected LoggerInterface $logger,
    ) {
    }

    public function getUnixSocketPath(): string
    {
        if ($this->unixSocketPath === null) {
            $this->createTemporaryUnixSocket();
        }

        return $this->unixSocketPath;
    }

    public function acceptRemoteSocket(): Socket
    {
        $unixSocket = $this->unixSocket ?? $this->createTemporaryUnixSocket();
        $stream = socket_export_stream($unixSocket);

        $deferred = new DeferredFuture();
        $handle = null;
        $timeout = EventLoop::delay(5, function () use ($deferred, &$handle) {
            EventLoop::cancel($handle);
            $this->close();
            $deferred->error(new TimeoutException('Timeout, got no socket'));
        });
        $handle = EventLoop::onReadable($stream, function () use ($deferred, $timeout, $unixSocket) {
            $this->logger->notice('Data ready for SocketReceiver');
            EventLoop::cancel($timeout);
            try {
                $socket = $this->receiveRemoteSocket($unixSocket);
                $this->close();
                $deferred->complete($socket);
            } catch (\Throwable $e) {
                $this->close();
                $deferred->error($e);
            }
        });

        return $deferred->getFuture()->await();
    }

    protected function createTemporaryUnixSocket(): Socket
    {
        $unixSocketPath = tempnam(sys_get_temp_dir(), 'imedge-pass-socket');
        unlink($unixSocketPath);
        if ($unixSocketPath === false) {
            throw new RuntimeException('tempnam failed for createTemporaryUnixSocket()');
        }
        $unixSocket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
        if ($unixSocket === false) {
            throw new RuntimeException(sprintf(
                'Failed to create Unix socket: %s',
                socket_strerror(socket_last_error())
            ));
        }

        if (!socket_bind($unixSocket, $unixSocketPath)) {
            throw new RuntimeException(sprintf(
                "Binding Unix socket on %s failed: %s",
                $unixSocketPath,
                socket_strerror(socket_last_error($unixSocket))
            ));
        }

        $this->unixSocketPath = $unixSocketPath;
        $this->unixSocket = $unixSocket;

        return $unixSocket;
    }

    protected function receiveRemoteSocket(Socket $socket): Socket
    {
        $msg = [
            'controllen' => socket_cmsg_space(SOL_SOCKET, SCM_RIGHTS, 1) // Others add 4 or similar. Verify this.
        ];
        $result = socket_recvmsg($socket, $msg, 0); // Hint: there is MSG_DONTWAIT, requires different call order
        if ($result === false) {
            throw new RuntimeException(sprintf(
                'Receiving remote socket failed: %s',
                socket_strerror(socket_last_error($socket))
            ));
        }

        return self::extractReceivedFileDescriptor($msg);
    }

    /**
     * @param array{name: ?array, control: array{0: array{level: int, type: int, data: array{0: resource}}}} $msg
     */
    protected static function extractReceivedFileDescriptor(array $msg): Socket
    {
        if (!isset($msg['control'][0]['data'][0])) {
            throw new RuntimeException(sprintf('Socket expected, got unexpected structure: ' . var_export($msg, true)));
        }
        $fd = $msg['control'][0]['data'][0];
        if ($fd instanceof Socket) { // Should we allow resources too?
            return $fd;
        }

        throw new RuntimeException(sprintf('Socket expected, got ' . get_debug_type($msg)));
    }

    protected function close(): void
    {
        if ($this->unixSocket) {
            @socket_close($this->unixSocket);
            $this->unixSocket = null;
        }
        if ($this->unixSocketPath) {
            @unlink($this->unixSocketPath);
            $this->unixSocketPath = null;
        }
    }
}
