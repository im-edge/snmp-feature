<?php

namespace IMEdge\SnmpFeature\Discovery;

use Amp\Redis\RedisClient;
use IMEdge\Config\Settings;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Node\ApplicationContext;
use IMEdge\Node\ImedgeWorker;
use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiNamespace;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Revolt\EventLoop;

use function Amp\Redis\createRedisClient;

#[ApiNamespace('snmpDiscoveryReceiver')]
class SnmpDiscoveryReceiver implements ImedgeWorker
{
    protected const MAX_UDP_PAYLOAD = 65507;
    protected const REDIS_PREFIX = 'snmpScan:';

    protected RedisClient $redis;

    /** @var array<int, resource> */
    protected array $servers = [];

    /** @var array<int, string> */
    protected array $handles = [];

    /** @var array<int, UuidInterface> */
    protected array $credentials = [];

    public function __construct(
        protected readonly Settings $settings,
        protected readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
    ) {
        $this->redis = createRedisClient('unix://' . ApplicationContext::getRedisSocket());
        $this->redis->execute('CLIENT', 'SETNAME', 'discoveryReceiver');
    }

    #[ApiMethod]
    public function passUdpSocket(string $targetSocket, UuidInterface $credentialUuid): bool
    {
        $socket = DiscoveryUdpSocket::create();
        $udpPort = DiscoveryUdpSocket::getResourceStreamPort($socket);
        $this->servers[$udpPort] = $socket;
        $this->credentials[$udpPort] = $credentialUuid;
        $this->handles[$udpPort] = EventLoop::onReadable($socket, function () use ($udpPort) {
            $data = stream_socket_recvfrom($this->servers[$udpPort], self::MAX_UDP_PAYLOAD, STREAM_OOB, $address);
            $this->redis->execute(
                'HSET',
                self::REDIS_PREFIX . "$udpPort/candidates",
                $address,
                '[' . $this->credentials[$udpPort]->getBytes() . ',' . $data . ']'
            );
            $this->redis->execute(
                'XADD',
                self::REDIS_PREFIX . "$udpPort/progress",
                'MAXLEN',
                '~',
                1000,
                '*',
                'peer',
                $address,
                'credential',
                $this->credentials[$udpPort],
                'response',
                $data
            ); // gives: '1741188731539-0'
        });
        $this->logger->notice("SNMP Discovery Receiver has been initialized, listening on UDP port " . $udpPort);

        IpcSocketSender::sendSocket($socket, $targetSocket);
        return true;
    }

    public function start(): void
    {
    }

    public function stop(): void
    {
        foreach ($this->handles as $handle) {
            EventLoop::cancel($handle);
        }
        $this->handles = [];
        foreach ($this->servers as $server) {
            stream_socket_shutdown($server, STREAM_SHUT_RDWR);
        }
        $this->servers = [];
        $this->credentials = [];
        $this->logger->notice('Discovery Runner has been stopped');
    }

    public function getApiInstances(): array
    {
        return [$this];
    }
}
