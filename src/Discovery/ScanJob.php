<?php

namespace IMEdge\SnmpFeature\Discovery;

use IMEdge\IpListGenerator\IpListGenerator;
use IMEdge\Snmp\IncrementingRequestIdGenerator;
use IMEdge\SnmpFeature\SnmpCredential;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use RuntimeException;
use Sop\ASN1\DERData;
use Sop\ASN1\Type\Constructed\Sequence;
use Sop\ASN1\Type\Primitive\Integer;
use Sop\ASN1\Type\Primitive\OctetString;
use Sop\ASN1\Type\Tagged\ImplicitlyTaggedType;
use stdClass;

class ScanJob implements JsonSerializable
{
    protected Integer $snmpVersion;
    protected OctetString $communityString;
    protected DERData $payload;
    protected int $burst = 250; // 250
    protected float $delay = 0.05; // 0.02
    protected int $cntSent = 0;
    protected int $sentPayloadBytes = 0;
    protected ?int $startTimeMs = null;
    protected ?int $startHrTime = null;
    protected ?int $stopHrTime = null;
    protected ScanJobStatus $status = ScanJobStatus::PENDING;
    protected ?Suspension $suspension = null;

    public function __construct(
        protected SnmpCredential $credential,
        protected IpListGenerator $generator,
        protected IncrementingRequestIdGenerator $idGenerator,
        protected LoggerInterface $logger,
    ) {
        $this->snmpVersion = new Integer(1 /* 1 = v2c */);
        $this->communityString = new OctetString($this->credential->securityName);
        $this->payload = (new DiscoveryPayload())->getDERData();
    }

    /**
     * @param \Socket $socket
     * @throws \Amp\Socket\SocketException
     */
    public function run($socket): void
    {
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
        if ($this->status === ScanJobStatus::RUNNING) {
            $this->status = ScanJobStatus::FINISHED;
        }
        $this->stopHrTime = hrtime(true);
        $this->suspension = null;
        $this->logger->notice('SNMP Job generator finished');
    }

    protected function nextPacket(): string
    {
        $pdu = new ImplicitlyTaggedType(0 /* request */, new Sequence(
            new Integer($this->idGenerator->getNextId()), // requestId
            new Integer(0), // errorStatus
            new Integer(0), // errorIndex
            $this->payload
        ));
        $v2Message = new Sequence($this->snmpVersion, $this->communityString, $pdu);
        return $v2Message->toDER();
    }

    public function stop(): void
    {
        if ($this->status === ScanJobStatus::RUNNING || $this->status === ScanJobStatus::PENDING) {
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
