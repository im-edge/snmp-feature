<?php

namespace IMEdge\SnmpFeature;

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
use IMEdge\Snmp\UdpSocketFactory;
use IMEdge\Snmp\VarBind;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Datagram\Socket as DatagramSocket;
use Revolt\EventLoop;
use RuntimeException;
use Throwable;

use function React\Promise\resolve;

class SnmpSocket implements EventEmitterInterface, LoggerAwareInterface, RequestIdConsumer
{
    use EventEmitterTrait;
    use LoggerAwareTrait;

    public const ON_TRAP = 'trap';

    /** @var array<int, Deferred> */
    protected array $pendingRequests = [];

    /** @var array<int, array<string, ?string>> */
    protected array $pendingRequestOidLists = [];

    /** @var array<int, TimerInterface> */
    protected array $timers = [];

    protected ?DatagramSocket $socket = null;

    protected int $oidsRequestedGet = 0;
    protected int $oidsRequestedGetNext = 0;
    protected int $oidsRequestedGetBulk = 0;
    protected int $oidsRequestedWalk = 0;
    protected int $oidsReceived = 0;
    protected int $cntGetRequests = 0;
    protected int $cntGetBulkRequests = 0;
    protected int $cntGetNextRequests = 0;
    protected int $cntWalkRequests = 0;
    protected int $cntTimeouts = 0;
    protected int $responsesReceived = 0;

    public function __construct(
        public readonly SocketAddress $socketAddress = new SocketAddress('0.0.0.0'),
        public readonly SimpleRequestIdGenerator $idGenerator = new SimpleRequestIdGenerator(),
    ) {
        $this->logger = new NullLogger();
        $this->idGenerator->registerConsumer($this);
    }

    public function getStats(): array
    {
        return [
            'GetRequests'          => $this->cntGetRequests,
            'GetBulkRequests'      => $this->cntGetBulkRequests,
            'GetNextRequests'      => $this->cntGetNextRequests,
            'WalkRequests'         => $this->cntWalkRequests,
            'RequestedOidsGet'     => $this->oidsRequestedGet,
            'RequestedOidsGetBulk' => $this->oidsRequestedGetBulk,
            'RequestedOidsGetNext' => $this->oidsRequestedGetNext,
            'RequestedOidsWalk'    => $this->oidsRequestedWalk,
            'ReceivedOids'         => $this->oidsReceived,
            'ReceivedResponses'    => $this->responsesReceived,
            'Timeouts'             => $this->cntTimeouts,
        ];
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
    ): PromiseInterface {
        $id = $this->idGenerator->getNextId();
        $varBinds = $this->prepareAndScheduleOidList($id, $oidList);
        $request = new SnmpV2Message($community, new GetRequest($varBinds, $id));
        $this->cntGetRequests++;
        $this->oidsRequestedGet += count($varBinds);

        return $this->send($request, $target);
    }

    /**
     * @param array<int|string, string> $oidList oid => alias
     */
    public function getNext(
        array $oidList,
        string $ip,
        #[\SensitiveParameter] string $community
    ): PromiseInterface {
        $requestedOidList = [];
        foreach ($oidList as $oid) {
            $requestedOidList[$oid] = null;
        }
        $id = $this->idGenerator->getNextId();
        $varBinds = $this->prepareAndScheduleOidList($id, $requestedOidList);
        $request = new SnmpV2Message($community, new GetNextRequest($varBinds, $id));
        $this->oidsRequestedGetNext += count($varBinds);
        $this->cntGetNextRequests++;

        return $this->send($request, $ip);
    }

    public function getBulk(
        string $oid,
        string $target,
        #[\SensitiveParameter] string $community,
        int $maxRepetitions = 10
    ): PromiseInterface {
        $id = $this->idGenerator->getNextId();
        $varBinds = $this->prepareAndScheduleOidList($id, [$oid => null]);
        $request = new SnmpV2Message($community, new GetBulkRequest($varBinds, $id, $maxRepetitions));
        $this->cntGetBulkRequests++;
        $this->oidsRequestedGetBulk += count($varBinds);

        return $this->send($request, $target);
    }

    public function walk(
        string $oid,
        string $target,
        #[\SensitiveParameter] string $community,
        ?int $limit = null,
        ?string $nextOid = null
    ): PromiseInterface {
        $walk = new SnmpWalk($this, $this->logger, $limit);
        if ($nextOid !== null) {
            $walk->setNextOid($nextOid);
        }
        $this->cntWalkRequests++;
        $this->oidsRequestedWalk++;

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
    ): PromiseInterface {
        $fetchTable = new FetchTable($this);
        // No stats, seems unused
        return $fetchTable->fetchTable($oid, $columns, SocketAddress::detect($target), $community);
    }

    public function walkBulk(
        string $oid,
        string $ip,
        string $community,
        int $maxRepetitions = 10
    ): PromiseInterface {
        // TODO: Multiple OIDs
        $results = [];
        $deferred = new Deferred();
        $error = function ($reason) use ($deferred) {
            $deferred->reject($reason);
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
                $deferred->resolve($results);

                return;
            }
            $newOid = null;

            /** @var DataType $value */
            foreach ($result as $newOid => $value) {
                if (str_starts_with($newOid, $oid)) {
                    $results[$newOid] = $value;
                } else {
                    $deferred->resolve($results);

                    return;
                }
            }

            $this->getBulk($newOid, $ip, $community, $maxRepetitions)
                ->then($handle, $error);
        };
        $this->getBulk($oid, $ip, $community, $maxRepetitions)
            ->then($handle, $error);

        return $deferred->promise();
    }

    public function sendTrap(SnmpMessage $trap, SocketAddress|string $destination): void
    {
        $this->send($trap, SocketAddress::detect($destination, 162));
    }

    public function hasId(int $id): bool
    {
        return isset($this->pendingRequests[$id]);
    }

    protected function send(SnmpMessage $message, SocketAddress|string $destination): PromiseInterface
    {
        $pdu = $message->getPdu();
        $wantsResponse = $pdu->wantsResponse();
        if ($wantsResponse) {
            $deferred = new Deferred();
            $id = $pdu->requestId;
            if ($id === null) {
                throw new RuntimeException('Cannot send a request w/o id');
            }
            $this->pendingRequests[$id] = $deferred;
            $this->scheduleTimeout($id);
            $result = $deferred->promise();
        } else {
            $result = resolve();
        }
        $this->socket()->send($message->toBinary(), SocketAddress::detect($destination, 161));

        return $result;
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
        $this->timers[$id] = Loop::addTimer($timeout, function () use ($id) {
            if (isset($this->pendingRequests[$id])) {
                $deferred = $this->pendingRequests[$id];
                unset($this->pendingRequests[$id]);
                unset($this->pendingRequestOidLists[$id]);
                unset($this->timers[$id]);
                $this->cntTimeouts++;
                $deferred->reject(new Exception('Timeout')); // TODO: ErrorStatus, Exception?
            }
        });
    }

    protected function clearPendingRequest(int $id): void
    {
        unset($this->pendingRequests[$id]);
        unset($this->pendingRequestOidLists[$id]);
        Loop::cancelTimer($this->timers[$id]);
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
        if (! $error instanceof Exception) {
            $error = new RuntimeException($error->getMessage(), $error->getCode(), $error);
        }
        $deferred = $this->pendingRequests[$id];

        unset($this->pendingRequests[$id]);
        unset($this->pendingRequestOidLists[$id]);
        Loop::cancelTimer($this->timers[$id]);
        unset($this->timers[$id]);
        EventLoop::queue(function () use ($deferred, $error) {
            $deferred->reject($error);
        });
    }

    /**
     * @return int[]
     */
    protected function listPendingIds(): array
    {
        return array_keys($this->pendingRequests);
    }

    protected function handleData(string $data, SocketAddress $peer, DatagramSocket $socket): void
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
            $this->oidsReceived += count($pdu->varBinds);
            $this->responsesReceived++;
            // We're skipping this noSuchName, as otherwise PollSysInfo would fail on some devices
            if ($pdu->isError() && $pdu->getErrorStatus() !== ErrorStatus::NO_SUCH_NAME) {
                $out = '';
                foreach ($message->getPdu()->varBinds as $varBind) {
                    $out .= $varBind->oid . ': ' . $varBind->value->getReadableValue() . "\n";
                }
                $deferred->reject(new Exception(sprintf(
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
                $deferred->resolve($result);
            }
        } else {
            $this->logger->error(
                "Ignoring response for unknown requestId=$requestId" . SnmpMessageInspector::dump($message)
            );
        }
    }

    protected function socket(): DatagramSocket
    {
        if ($this->socket === null) {
            $this->socket = UdpSocketFactory::prepareUdpSocket($this->socketAddress);
            $this->registerUdpSocketHandlers();
            assert($this->socket instanceof DatagramSocket); // this should not be necessary, but phpstan complains
        }

        return $this->socket;
    }

    protected function registerUdpSocketHandlers(): void
    {
        if ($this->socket === null) {
            throw new RuntimeException('Cannot register socket handlers w/o socket');
        }
        $socket = $this->socket;
        $socket->on('message', function ($data, $peer, DatagramSocket $socket) {
            $this->handleData($data, SocketAddress::parse($peer), $socket);
        });
        $socket->on('error', function (Throwable $error) {
            $this->rejectAllPendingRequests($error);
            if ($this->socket !== null) {
                $this->socket->close();
                $this->socket = null;
            }
        });
        $socket->on('close', function () {
            $this->socket = null;
            $this->rejectAllPendingRequests(new Exception('Socket has been closed'));
        });
    }
}
