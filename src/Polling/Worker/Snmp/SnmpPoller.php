<?php

namespace IMEdge\SnmpFeature\Polling\Worker\Snmp;

use Amp\Socket\InternetAddress;
use IMEdge\Config\Settings;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Node\ImedgeWorker;
use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiNamespace;
use IMEdge\SnmpEngine\Dispatcher\SnmpDispatcher;
use IMEdge\SnmpEngine\SnmpPoller as Engine;
use IMEdge\SnmpFeature\SnmpCredentials;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use IMEdge\SnmpPacket\Error\SnmpAuthenticationException;
use IMEdge\SnmpPacket\Pdu\Pdu;
use Psr\Log\LoggerInterface;

/**
 * Responsible for interactive SNMP Requests
 */
#[ApiNamespace('snmpPoller')]
class SnmpPoller implements ImedgeWorker
{
    protected SnmpCredentials $credentials;
    protected SnmpTargets $targets;
    protected Engine $engine;

    public function __construct(
        protected readonly Settings $settings,
        protected readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
    ) {
        $this->targets = new SnmpTargets();
        $this->credentials = new SnmpCredentials([]);
        $this->engine = new Engine(new SnmpDispatcher($this->logger));
    }

    /**
     * @param array<string, ?string> $oids
     * @throws SnmpAuthenticationException
     */
    #[ApiMethod]
    public function get(string $target, array $oids): Pdu
    {
        return $this->engine->get($target, self::flipOidsForRequest($oids));
    }

    /**
     * @param array<string, ?string> $oids
     * @throws SnmpAuthenticationException
     */
    #[ApiMethod]
    public function getNext(string $target, array $oids): Pdu
    {
        return $this->engine->getNext($target, self::flipOidsForRequest($oids));
    }

    /**
     * @param array<string, ?string> $oids
     * @throws SnmpAuthenticationException
     */
    #[ApiMethod]
    public function getBulk(
        string $target,
        array $oids,
        int $maxRepetitions = 10,
        int $nonRepeaters = 0
    ): Pdu {
        return $this->engine->getBulk($target, self::flipOidsForRequest($oids), $maxRepetitions, $nonRepeaters);
    }

    public function start(): void
    {
        $this->logger->notice('SNMP Poller has been started');
    }

    public function stop(): void
    {
        $this->logger->notice('SNMP  Poller has been stopped');
    }

    public function getApiInstances(): array
    {
        return [$this];
    }

    #[ApiMethod]
    public function setTargets(SnmpTargets $targets): bool
    {
        $this->targets = $targets;
        $this->logger->notice('SnmpPoller got targets');
        foreach ($targets->targets as $target) {
            $credential = $this->credentials->credentials[$target->credentialUuid->getBytes()] ?? null;
            if ($credential === null) {
                $this->logger->notice('No credential for ' . $target->identifier);
                continue;
            }
            $credNew = $credential->toEngineCredential();
            $this->logger->notice('Registering ' . $target->identifier);
            $address = new InternetAddress($target->address->ip, $target->address->port);
            if ($target->credentialUuid->equals($credential->uuid)) {
                $this->engine->registerClient($target->identifier, $address, $credNew);
            }
        }

        return true;
    }

    #[ApiMethod]
    public function setCredentials(SnmpCredentials $credentials): bool
    {
        $this->credentials = $credentials;
        $this->logger->notice('Poller got credentials');
        foreach ($credentials->credentials as $credential) {
            $credNew = $credential->toEngineCredential();
            foreach ($this->targets->targets as $target) {
                $address = new InternetAddress($target->address->ip, $target->address->port);
                if ($target->credentialUuid->equals($credential->uuid)) {
                    $this->logger->notice('Registering ' . $target->identifier);
                    $this->engine->registerClient($target->identifier, $address, $credNew);
                }
            }
        }

        return true;
    }

    /**
     * I do not really like this
     */
    protected static function flipOidsForRequest(array $oids): array
    {
        return array_combine(array_values($oids), array_values($oids));
    }
}
