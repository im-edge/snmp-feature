<?php

namespace IMEdge\SnmpFeature;

use Amp\DeferredFuture;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceUdpSocket;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use Exception;
use IMEdge\Snmp\DataType\DataType;
use IMEdge\Snmp\DataType\DataTypeContextSpecific;
use IMEdge\Snmp\ErrorStatus;
use IMEdge\Snmp\GetBulkRequest;
use IMEdge\Snmp\GetNextRequest;
use IMEdge\Snmp\GetRequest;
use IMEdge\Snmp\RequestIdConsumer;
use IMEdge\Snmp\SimpleRequestIdGenerator;
use IMEdge\Snmp\SnmpMessage;
use IMEdge\Snmp\SnmpMessageInspector;
use IMEdge\Snmp\SnmpV2Message;
use IMEdge\Snmp\SocketAddress;
use IMEdge\Snmp\TrapV2;
use IMEdge\Snmp\VarBind;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use RuntimeException;
use Throwable;

use function Amp\Socket\bindUdpSocket;

class SnmpSocket implements EventEmitterInterface, LoggerAwareInterface, RequestIdConsumer
{
    use EventEmitterTrait;
    use LoggerAwareTrait;

    public const ON_TRAP = 'trap';

    public SnmpSocketStats $stats;
    protected ?ResourceUdpSocket $socket = null;

    /** @var array<int, DeferredFuture> */
    protected array $pendingRequests = [];

    /** @var array<int, array<string, ?string>> */
    protected array $pendingRequestOidLists = [];

    /** @var array<int, string> */
    protected array $timers = [];

    public function __construct(
        public readonly string $socketAddress = '0.0.0.0:0',
        public readonly SimpleRequestIdGenerator $idGenerator = new SimpleRequestIdGenerator(),
    ) {
        $this->logger = new NullLogger();
        $this->idGenerator->registerConsumer($this);
        $this->stats = new SnmpSocketStats();
    }

    public function hasPendingRequests(): bool
    {
        return ! empty($this->pendingRequests);
    }

    public function listen(): void
    {
        $this->socket();
    }

    /**
     * @param array<string, string> $oidList oid => alias
     */
    public function get(
        array $oidList,
        string $target,
        #[\SensitiveParameter] string $community
    ): array {
        $id = $this->idGenerator->getNextId();
        $varBinds = $this->prepareAndScheduleOidList($id, $oidList);
        $request = new SnmpV2Message($community, new GetRequest($varBinds, $id));
        $this->stats->cntGetRequests++;
        $this->stats->oidsRequestedGet += count($varBinds);
        return $this->send($request, self::getInternetAddress($target));
    }

    /**
     * @param array<int|string, string> $oidList oid => alias
     */
    public function getNext(
        array $oidList,
        string $target,
        #[\SensitiveParameter] string $community
    ): array {
        $requestedOidList = [];
        foreach ($oidList as $oid) {
            $requestedOidList[$oid] = null;
        }
        $id = $this->idGenerator->getNextId();
        $varBinds = $this->prepareAndScheduleOidList($id, $requestedOidList);
        $request = new SnmpV2Message($community, new GetNextRequest($varBinds, $id));
        $this->stats->oidsRequestedGetNext += count($varBinds);
        $this->stats->cntGetNextRequests++;

        return $this->send($request, self::getInternetAddress($target));
    }

    public function getBulk(
        string $oid,
        string $target,
        #[\SensitiveParameter] string $community,
        int $maxRepetitions = 10
    ): array {
        $id = $this->idGenerator->getNextId();
        $varBinds = $this->prepareAndScheduleOidList($id, [$oid => null]);
        $request = new SnmpV2Message($community, new GetBulkRequest($varBinds, $id, $maxRepetitions));
        $this->stats->cntGetBulkRequests++;
        $this->stats->oidsRequestedGetBulk += count($varBinds);

        return $this->send($request, self::getInternetAddress($target));
    }

    public function walk(
        string $oid,
        string $target,
        #[\SensitiveParameter] string $community,
        ?int $limit = null,
        ?string $nextOid = null
    ): array {
        $walk = new SnmpWalk($this, $this->logger, $limit);
        if ($nextOid !== null) {
            $walk->setNextOid($nextOid);
        }
        $this->stats->cntWalkRequests++;
        $this->stats->oidsRequestedWalk++;

        return $walk->walk($oid, $target, $community);
    }

    /**
     * @param array<int|string, string> $columns
     */
    public function table(
        string $oid,
        array $columns,
        SocketAddress|string $target,
        #[\SensitiveParameter] string $community
    ): array {
        $fetchTable = new FetchTable($this, $this->logger);
        // No stats, seems unused
        return $fetchTable->fetchTable($oid, $columns, SocketAddress::detect($target), $community);
    }

    public function walkBulk(
        string $oid,
        string $ip,
        string $community,
        int $maxRepetitions = 10
    ) {
        // TODO: Multiple OIDs
        $results = [];
        $deferred = new DeferredFuture();
        $error = function ($reason) use ($deferred) {
            $deferred->error($reason);
        };
        $handle = function ($result) use (
            $oid,
            $ip,
            $community,
            $maxRepetitions,
            $error,
            &$results,
            $deferred,
            &$handle
        ) {
            if (empty($result)) {
                $deferred->complete($results);

                return;
            }
            $newOid = null;

            /** @var DataType $value */
            foreach ($result as $newOid => $value) {
                if (str_starts_with($newOid, $oid)) {
                    $results[$newOid] = $value;
                } else {
                    $deferred->complete($results);

                    return;
                }
            }

            try {
                $handle($this->getBulk($newOid, $ip, $community, $maxRepetitions));
            } catch (Exception $e) {
                $error($e);
            }
        };
        try {
            $handle($this->getBulk($oid, $ip, $community, $maxRepetitions));
        } catch (Exception $e) {
            $error($e);
        }

        return $deferred->getFuture()->await();
    }

    public function sendTrap(SnmpMessage $trap, SocketAddress|string $destination): void
    {
        $this->send($trap, self::getInternetAddress($destination, 162));
    }

    public function hasId(int $id): bool
    {
        return isset($this->pendingRequests[$id]);
    }

    protected function send(SnmpMessage $message, InternetAddress $destination)
    {
        $pdu = $message->getPdu();
        $wantsResponse = $pdu->wantsResponse();
        if ($wantsResponse) {
            $deferred = new DeferredFuture();
            $id = $pdu->requestId;
            if ($id === null) {
                throw new RuntimeException('Cannot send a request w/o id');
            }
            $this->pendingRequests[$id] = $deferred;
            $this->scheduleTimeout($id);
            $result = $deferred->getFuture();
            $this->socket()->send($destination, $message->toBinary());

            return $result->await();
        } else {
            $this->socket()->send($destination, $message->toBinary());
            return null;
        }
    }

    protected static function getInternetAddress(string $target, int $defaultPort = 161): InternetAddress
    {
        if (str_contains($target, ':')) {
            return InternetAddress::fromString($target);
        }

        return InternetAddress::fromString("$target:$defaultPort");
    }

    /**
     * @param int $id
     * @param array<string, ?string> $oidList oid => alias
     * @return VarBind[]
     */
    protected function prepareAndScheduleOidList(int $id, array $oidList): array
    {
        $this->pendingRequestOidLists[$id] = $oidList;
        $binds = [];
        foreach ($oidList as $oid => $target) {
            $binds[] = new VarBind($oid);
        }

        return $binds;
    }

    protected function scheduleTimeout(int $id, int $timeout = 45): void
    {
        $this->timers[$id] = EventLoop::delay($timeout, fn () => $this->triggerTimeout($id));
    }

    protected function triggerTimeout(int $id): void
    {
        if (isset($this->pendingRequests[$id])) {
            $deferred = $this->pendingRequests[$id];
            unset($this->pendingRequests[$id]);
            unset($this->pendingRequestOidLists[$id]);
            unset($this->timers[$id]);
            $this->stats->cntTimeouts++;
            $deferred->error(new Exception('Timeout triggered')); // TODO: ErrorStatus, Exception?
        }
    }

    protected function clearPendingRequest(int $id): void
    {
        unset($this->pendingRequests[$id]);
        unset($this->pendingRequestOidLists[$id]);
        EventLoop::cancel($this->timers[$id]);
        unset($this->timers[$id]);
    }

    protected function rejectAllPendingRequests(Throwable $error): void
    {
        foreach ($this->listPendingIds() as $id) {
            $this->rejectPendingRequest($id, $error);
        }
    }

    protected function rejectPendingRequest(int $id, Throwable $error): void
    {
        $deferred = $this->pendingRequests[$id];
        unset($this->pendingRequests[$id]);
        unset($this->pendingRequestOidLists[$id]);
        EventLoop::cancel($this->timers[$id]);
        unset($this->timers[$id]);
        EventLoop::queue(function () use ($deferred, $error) {
            $deferred->error($error);
        });
    }

    /**
     * @return int[]
     */
    protected function listPendingIds(): array
    {
        return array_keys($this->pendingRequests);
    }

    protected function handleData(string $data, InternetAddress $peer): void
    {
        // TODO: Logger::debug("Got message from $peer");
        try {
            $message = SnmpMessage::fromBinary($data);
        } catch (Exception $e) {
            $this->logger->error(
                "Ignoring SNMP message from $peer: " . $e->getMessage()
            );
            return;
        }

        $pdu = $message->getPdu();

        if ($pdu instanceof TrapV2) {
            $this->emit(self::ON_TRAP, [$message, $peer]);
            return;
        }
        $requestId = $pdu->requestId;
        if ($requestId !== null && isset($this->pendingRequests[$requestId])) {
            $deferred = $this->pendingRequests[$requestId];
            $oidList = $this->pendingRequestOidLists[$requestId];
            $this->clearPendingRequest($requestId);
            $this->stats->oidsReceived += count($pdu->varBinds);
            $this->stats->responsesReceived++;
            // We're skipping this noSuchName, as otherwise PollSysInfo would fail on some devices
            if ($pdu->isError() && $pdu->getErrorStatus() !== ErrorStatus::NO_SUCH_NAME) {
                $out = '';
                foreach ($message->getPdu()->varBinds as $varBind) {
                    $out .= $varBind->oid . ': ' . $varBind->value->getReadableValue() . "\n";
                }
                $deferred->error(new Exception(sprintf(
                    "errorStatus: %d, errorIndex: %d\n",
                    $pdu->getErrorStatus(),
                    $pdu->getErrorIndex()
                ) . $out));
            } else {
                $result = [];
                foreach ($pdu->varBinds as $varBind) {
                    $oid = $varBind->oid;
                    if (isset($oidList[$oid])) {
                        $result[$oidList[$oid]] = $varBind->value;
                        unset($oidList[$oid]);
                    } else {
                        $result[$oid] = $varBind->value;
                    }
                }
                foreach ($oidList as $missing) {
                    if ($missing !== null) {
                        $result[$missing] = DataTypeContextSpecific::noSuchObject();
                    }
                }
                $deferred->complete($result);
            }
        } else {
            $this->logger->error(
                "Ignoring response for unknown requestId=$requestId" . SnmpMessageInspector::dump($message)
            );
        }
    }

    protected function socket(): ResourceUdpSocket
    {
        if ($this->socket === null) {
            $this->socket = bindUdpSocket($this->socketAddress);
            EventLoop::queue($this->keepReadingFromSocket(...));
        }

        return $this->socket;
    }

    protected function keepReadingFromSocket(): void
    {
        if ($this->socket === null) {
            throw new RuntimeException('Cannot register socket handlers w/o socket');
        }
        try {
            while ([$address, $data] = $this->socket->receive()) {
                $this->handleData($data, $address);
            }
            $this->socket = null;
            $this->rejectAllPendingRequests(new Exception('Socket has been closed'));
        } catch (Throwable $error) {
            $this->rejectAllPendingRequests($error);
            if ($this->socket !== null) {
                $this->socket->close();
                $this->socket = null;
            }
        }
    }
}
