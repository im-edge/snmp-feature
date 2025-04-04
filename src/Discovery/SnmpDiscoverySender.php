<?php

namespace IMEdge\SnmpFeature\Discovery;

use Amp\Redis\RedisClient;
use IMEdge\Config\Settings;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\IpListGenerator\IpListGenerator;
use IMEdge\Json\JsonString;
use IMEdge\Node\ApplicationContext;
use IMEdge\Node\ImedgeWorker;
use IMEdge\RedisUtils\RedisResult;
use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiNamespace;
use IMEdge\Snmp\IncrementingRequestIdGenerator;
use IMEdge\Snmp\SnmpMessage;
use IMEdge\SnmpFeature\SnmpCredential;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Revolt\EventLoop;
use RuntimeException;
use Socket;
use stdClass;

use function Amp\Redis\createRedisClient;

#[ApiNamespace('snmpDiscoverySender')]
class SnmpDiscoverySender implements ImedgeWorker
{
    protected const REDIS_PREFIX = 'snmpScan:';

    protected IncrementingRequestIdGenerator $idGenerator;
    /** @var ScanJob[] */
    protected array $jobs = [];
    /** @var Socket[] */
    protected array $sockets = [];

    protected ?IpcSocketReceiver $socketReceiver = null;
    protected RedisClient $redis;

    public function __construct(
        protected Settings $settings,
        protected NodeIdentifier $nodeIdentifier,
        protected LoggerInterface $logger,
    ) {
        $this->idGenerator = new IncrementingRequestIdGenerator();
        $this->redis = createRedisClient('unix://' . ApplicationContext::getRedisSocket());
        $this->redis->execute('CLIENT', 'SETNAME', 'discoverySender');
    }

    #[ApiMethod]
    public function getIpcSocket(): string
    {
        $this->socketReceiver ??= new IpcSocketReceiver($this->logger);
        return $this->socketReceiver->getUnixSocketPath();
    }

    protected function launchWithReceivedSocket(): int
    {
        if ($this->socketReceiver === null) {
            throw new RuntimeException('Socket transmission has not been initialized');
        }
        $socket = $this->socketReceiver->acceptRemoteSocket();
        if (socket_getsockname($socket, $address, $port)) {
            $this->logger->notice("SNMP Discovery sender got a Socket on $address:$port");
            $this->sockets[$port] = $socket;
        } else {
            $this->logger->error('Failed to retrieve socket');
        }

        $this->socketReceiver = null;
        return $port;
    }

    public function getApiInstances(): array
    {
        return [$this];
    }

    /**
     * @param class-string<IpListGenerator> $generatorClass
     */
    #[ApiMethod]
    public function enqueue(SnmpCredential $credential, string $generatorClass, Settings $settings): int
    {
        if (!is_a($generatorClass, IpListGenerator::class, true)) {
            throw new InvalidArgumentException("$generatorClass is not an IpListGenerator");
        }
        $generator = new $generatorClass($settings);
        $port = $this->launchWithReceivedSocket();

        $job = new ScanJob($credential, $generator, $this->idGenerator, $this->logger);
        $this->redis->execute('HSET', self::REDIS_PREFIX . 'jobs', $port, JsonString::encode($job));
        $this->jobs[$port] = $job;
        EventLoop::queue(function () use ($job, $port) {
            $this->logger->notice("Starting Discovery job on port $port");
            $job->run($this->sockets[$port]);
            $this->logger->notice("Done with Discovery job on port $port");
            $this->redis->execute('HSET', self::REDIS_PREFIX . 'jobs', $port, JsonString::encode($job));
            unset($this->jobs[$port]);
            unset($this->sockets[$port]);
        });

        return $port;
    }

    /**
     * @return ScanJob[]
     */
    #[ApiMethod]
    public function getJobs(): stdClass
    {
        $finished = array_map(
            JsonString::decode(...),
            RedisResult::toArray($this->redis->execute('HGETALL', self::REDIS_PREFIX . 'jobs'))
        );
        $jobs = ($this->jobs + $finished);
        foreach ($jobs as $port => $job) {
            $job->results = (int) $this->redis->execute('HLEN', self::REDIS_PREFIX . "$port/candidates");
        }

        return (object) $jobs;
    }

    #[ApiMethod]
    public function deleteJob(int $jobId): bool
    {
        $this->stopJob($jobId);
        $this->redis->execute('DEL', self::REDIS_PREFIX . "$jobId/candidates");
        $this->redis->execute('DEL', self::REDIS_PREFIX . "$jobId/progress");
        $this->redis->execute('HDEL', self::REDIS_PREFIX . 'jobs', $jobId);

        return true;
    }

    #[ApiMethod]
    public function stopJob(int $jobId): bool
    {
        if (isset($this->jobs[$jobId])) {
            $this->jobs[$jobId]->stop();
            unset($this->jobs[$jobId]);
            unset($this->sockets[$jobId]);
            return true;
        }

        return false;
    }

    #[ApiMethod]
    public function getResults(int $jobId): stdClass
    {
        $this->logger->notice('Getting results');
        $results = RedisResult::toArray($this->redis->execute('HGETALL', self::REDIS_PREFIX . "$jobId/candidates"));
        $result = [];

        foreach ($results as $target => $message) {
            if (substr($message, 0, 1) === '[') {
                $credential = Uuid::fromBytes(substr($message, 1, 16))->toString();
                $message = substr($message, 18, -1);
            } else {
                $credential = null;
            }
            $message = SnmpMessage::fromBinary($message);
            $varBinds = $message->getPdu()->varBinds;
            $sysName = 'no sysName';
            $sysDescr = 'no sysDescr';
            foreach ($varBinds as $varBind) {
                switch ($varBind->oid) {
                    case '1.3.6.1.2.1.1.5.0':
                        $sysName = $varBind->value->getReadableValue();
                        break;
                    case '1.3.6.1.2.1.1.1.0':
                        $sysDescr = $varBind->value->getReadableValue();
                        break;
                }
            }
            $result[$target] = [
                'peer' => $target,
                'label' => sprintf('%s: %s', $sysName, $sysDescr),
                'credential' => $credential,
            ];
        }

        return (object) $result;
    }

    public function start(): void
    {
    }

    public function stop(): void
    {
        foreach ($this->jobs as $job) {
            $job->stop();
        }
        $this->jobs = [];
    }
}
