<?php

namespace IMEdge\SnmpFeature\Discovery;

use FreeDSx\Asn1\Asn1;
use FreeDSx\Asn1\Encoder\BerEncoder;
use FreeDSx\Asn1\Type\AbstractStringType;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\SequenceType;
use IMEdge\IpListGenerator\IpListGenerator;
use IMEdge\SnmpEngine\Dispatcher\IncrementingRequestIdGenerator;
use IMEdge\SnmpEngine\SnmpCredential;
use IMEdge\SnmpPacket\Pdu\GetRequest;
use IMEdge\SnmpPacket\SnmpVersion;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use RuntimeException;
use stdClass;

use function Amp\delay;

class ScanJob implements JsonSerializable
{
    protected BerEncoder $encoder;
    protected IntegerType $snmpVersion;
    protected OctetStringType $communityString;
    protected AbstractStringType $payload;
    protected int $burst = 250; // 250
    protected float $delay = 0.05; // 0.02
    protected int $cntSent = 0;
    protected int $sentPayloadBytes = 0;
    protected ?int $startTimeMs = null;
    protected ?int $startHrTime = null;
    protected ?int $stopHrTime = null;
    protected ScanJobStatus $status = ScanJobStatus::PENDING;
    protected ?Suspension $suspension = null;
    /** @var \Socket|resource|null  */
    protected $socket = null;

    public function __construct(
        protected SnmpCredential $credential,
        protected IpListGenerator $generator,
        protected IncrementingRequestIdGenerator $idGenerator,
        protected LoggerInterface $logger,
    ) {
        $this->encoder = new BerEncoder();
        $this->snmpVersion = new IntegerType(SnmpVersion::v2c->value);
        $this->communityString = new OctetStringType($this->credential->securityName);
        $this->payload = BinaryVarBinds::fromOidList(array_keys(DiscoveryPayload::OID_LIST), $this->encoder);
    }

    /**
     * @param \Socket $socket
     * @throws \Amp\Socket\SocketException
     */
    public function run($socket): void
    {
        $this->socket = $socket;
        $this->status = ScanJobStatus::RUNNING;
        $this->suspension = EventLoop::getSuspension();
        $this->startTimeMs = (int) floor(microtime(true) * 1000);
        $this->startHrTime = hrtime(true);

        // TODO: try/catch for the whole block, set status = failed
        $generator = $this->generator->generate();
        $cnt = 0;
        while ($generator->valid()) {
            if ($this->status !== ScanJobStatus::RUNNING) {
                // I have been interrupted
                break;
            }
            $ip = $generator->current();
            $generator->next();
            $cnt++;
            // $dest = new InternetAddress($ip, 161);
            // stream_socket_sendto($server, $this->nextPacket(), 0, $dest->toString());
            $message = $this->nextPacket();
            $length = strlen($message);
            $bytes = socket_sendto($socket, $message, $length, 0, $ip, 161);
            $this->cntSent++;
            $this->sentPayloadBytes += $bytes;

            if ($bytes !== $length) {
                $this->stopHrTime = hrtime(true);
                $this->status = ScanJobStatus::FAILED;
                throw new RuntimeException("Only $bytes out of $length bytes have been sent");
            }
            if ($cnt % $this->burst === 0) {
                EventLoop::delay($this->delay, $this->suspension->resume(...));
                $this->suspension->suspend();
            }
        }
        delay(10);
        if ($this->status === ScanJobStatus::RUNNING) {
            $this->status = ScanJobStatus::FINISHED;
        }
        $this->stopHrTime = hrtime(true);
        $this->suspension = null;
        $this->closeSocket();

        $this->logger->notice('SNMP Job generator finished');
    }

    protected function closeSocket(): void
    {
        if ($this->socket) {
            try {
                socket_close($this->socket);
            } catch (\Exception $e) {
                $this->logger->warning('Error when closing scan socket: ' . $e->getMessage());
            }
            $this->socket = null;
        }
    }

    protected function nextPacket(): string
    {
        return $this->encoder->encode(new SequenceType(
            $this->snmpVersion,
            $this->communityString,
            Asn1::context(GetRequest::TAG, new SequenceType(
                new IntegerType($this->idGenerator->getNextId()),
                new IntegerType(0),
                new IntegerType(0),
                $this->payload,
            ))
        ));
    }

    public function hasBeenCompleted(): bool
    {
        return $this->status === ScanJobStatus::FINISHED;
    }

    public function hasBeenAborted(): bool
    {
        return $this->status === ScanJobStatus::FINISHED;
    }

    public function stop(): void
    {
        if ($this->status === ScanJobStatus::RUNNING || $this->status === ScanJobStatus::PENDING) {
            $this->closeSocket();
            $this->status = ScanJobStatus::ABORTED;
        }
        $this->stopHrTime = hrtime(true);
        $this->suspension = null;
    }

    protected static function nanoToMs(int $nanoSeconds): int
    {
        return (int) floor($nanoSeconds / 1_000_000);
    }

    public function jsonSerialize(): stdClass
    {
        if ($this->startHrTime) {
            if ($this->stopHrTime) {
                $duration = self::nanoToMs($this->stopHrTime - $this->startHrTime);
                $end = $this->startTimeMs + $duration;
            } else {
                $duration = self::nanoToMs(hrtime(true) - $this->startHrTime);
                $end = null;
            }
        } else {
            $end = null;
            $duration = 0;
        }

        return (object) [
            'status'           => $this->status->value,
            'tsSendStartMs'    => $this->startTimeMs,
            'tsSendEndMs'      => $end,
            'durationSendMs'   => $duration,
            'sentPackets'      => $this->cntSent,
            'sentPayloadBytes' => $this->sentPayloadBytes,
            'results'          => 0,
        ];
    }
}
