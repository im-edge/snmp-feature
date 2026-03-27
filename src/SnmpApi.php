<?php

namespace IMEdge\SnmpFeature;

use Amp\Socket\InternetAddress;
use Exception;
use IMEdge\Config\Settings;
use IMEdge\IpListGenerator\IpListGenerator;
use IMEdge\SnmpFeature\SnmpScenario\KnownTargetsHealth;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiNamespace;
use IMEdge\SnmpPacket\Message\VarBindList;
use IMEdge\SnmpPacket\Pdu\Response;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use stdClass;

#[ApiNamespace('snmp')]
class SnmpApi
{
    protected ?SnmpSocket $socket;
    protected bool $shuttingDown = false;

    public function __construct(
        protected readonly SnmpRunner $runner,
        protected readonly LoggerInterface $logger
    ) {
        // TODO: v6 socket, socket pool?
        $this->socket = new SnmpSocket();
    }

    public function shutdown(): void
    {
        $this->shuttingDown = true;
    }

    // Used "live" from UI
    #[ApiMethod]
    public function scenario(
        UuidInterface $credentialUuid,
        InternetAddress $address,
        string $name,
        ?UuidInterface $deviceUuid = null,
    ): SnmpResponse {
        return $this->getScenarioNew($address, $name);
    }

    #[ApiMethod]
    public function triggerScenario(
        string $name,
        UuidInterface $deviceUuid,
        ?int $delay = null,
    ): bool {
        return $this->runner->scenarioController->jsonRpc->request('snmpScenarioController.triggerScenarioByName', [
            $name,
            $deviceUuid,
            $delay
        ]);
    }

    // live from UI
    #[ApiMethod]
    public function scenarioObject(
        UuidInterface $credentialUuid,
        InternetAddress $address,
        string $name,
        ?UuidInterface $deviceUuid = null,
    ): SnmpResponse {
        throw new RuntimeException('This method is deprecated');
        // $result = $this->getScenarioNew($address, $name);
        // -> there is no mor getResultObjectInstances() logic. Should we ship db updates and metrics?
    }

    #[ApiMethod]
    public function getScenarioDefinitions(): \stdClass
    {
        return $this->runner->scenarioController->jsonRpc->request('snmpScenarioController.getScenarios');
    }

    protected function getScenarioNew(
        InternetAddress $address,
        string $name,
    ): SnmpResponse {
        return SnmpResponse::fromSerialization(
            $this->runner->scenarioPoller->jsonRpc->request('snmpScenarioPoller.runScenarioByName', [
                (string) $address,
                $name
            ])
        );
    }

    #[ApiMethod]
    public function getKnownTargetsHealth(): KnownTargetsHealth
    {
        return $this->runner->health;
    }

    #[ApiMethod]
    public function setCredentials(SnmpCredentials $credentials): bool
    {
        foreach ($credentials->credentials as $credential) {
            $this->logger->notice(sprintf('Got credential %s(%s)', $credential->name, $credential->uuid->toString()));
        }

        $this->runner->setCredentials($credentials);

        return true;
    }

    #[ApiMethod]
    public function setKnownTargets(SnmpTargets $targets): bool
    {
        // 1788 targets -> 180kB
        // {"address":{"ip":"194.244.15.28","port":161},"credentialUuid":"92a9178c-6dee-432c-bc67-1d67776454a5"}]},"target":"730345e8-559b-45f3-b89d-184d866964cf","id":4058410},
        // 170 Bytes per target
        $this->runner->setTargets($this->appendLabTarget($targets));

        return true;
    }

    #[ApiMethod]
    public function get(
        UuidInterface $credentialUuid,
        InternetAddress $address,
        object $oidList,
    ): SnmpResponse {
        $community = $this->runner->credentials->requireCredential($credentialUuid)->securityName;
        $start = hrtime(true);
        try {
            return SnmpResponse::success(
                $address,
                $start,
                $this->socket->get((array) $oidList, $address, $community),
            );
        } catch (Exception $e) {
            return SnmpResponse::failure($address, $start, $e);
        }
    }

    #[ApiMethod]
    public function walk(
        UuidInterface $credentialUuid, // differs from @param!!
        InternetAddress $address,
        string $oid,
        ?int $limit = null,
        ?string $nextOid = null
    ): SnmpResponse {
        $community = $this->runner->credentials->requireCredential($credentialUuid)->securityName;
        $start = hrtime(true);
        try {
            return SnmpResponse::success(
                $address,
                $start,
                $this->socket->walk($oid, (string) $address, $community, $limit, $nextOid),
            );
        } catch (Exception $e) {
            return SnmpResponse::failure($address, $start, $e);
        }
    }

    /**
     * @param class-string<IpListGenerator> $generatorClass
     */
    #[ApiMethod]
    public function scanRanges(UuidInterface $credentialUuid, string $generatorClass, Settings $settings): int
    {
        $credential = $this->runner->credentials->requireCredential($credentialUuid);

        $sender = $this->runner->discoverySender;
        $receiver = $this->runner->discoveryReceiver;
        if ($sender === null) {
            throw new RuntimeException('SNMP Discovery Sender is not ready');
        }
        if ($receiver === null) {
            throw new RuntimeException('SNMP Discovery Receiver is not ready');
        }

        $ipcSocket = $sender->jsonRpc->request('snmpDiscoverySender.getIpcSocket');
        $receiver->jsonRpc->request('snmpDiscoveryReceiver.passUdpSocket', [$ipcSocket, $credentialUuid]);

        $key = $sender->jsonRpc->request('snmpDiscoverySender.enqueue', [
            $credential,
            $generatorClass,
            $settings
        ]);

        return $key;
    }

    #[ApiMethod]
    public function getDiscoveryJobs(): stdClass
    {
        return $this->runner->discoverySender->jsonRpc->request('snmpDiscoverySender.getJobs');
    }

    #[ApiMethod]
    public function getDiscoveryJobResults(int $jobId): stdClass
    {
        return $this->runner->discoverySender->jsonRpc->request('snmpDiscoverySender.getResults', [
            $jobId
        ]);
    }

    #[ApiMethod]
    public function streamDiscoveryJobResults(int $jobId, int $blockMs, string $offset = '0-0'): stdClass
    {
        return $this->runner->discoverySender->jsonRpc->request('snmpDiscoverySender.streamResults', [
            $jobId,
            $blockMs,
            $offset
        ]);
    }

    #[ApiMethod]
    public function deleteDiscoveryJobResults(int $jobId): bool
    {
        return $this->runner->discoverySender->jsonRpc->request('snmpDiscoverySender.deleteJob', [
            $jobId
        ]);
    }

    #[ApiMethod]
    public function stopDiscoveryJob(int $jobId): bool
    {
        return $this->runner->discoverySender->jsonRpc->request('snmpDiscoverySender.stopJob', [
            $jobId
        ]);
    }
}
