<?php

namespace IMEdge\SnmpFeature;

use IMEdge\Snmp\SocketAddress;
use IMEdge\SnmpFeature\Discovery\IpListScanner;
use IMEdge\SnmpFeature\NextGen\PeriodicScenarioRegistry;
use IMEdge\SnmpFeature\Scenario\ScenarioLoader;
use IMEdge\SnmpFeature\SnmpScenario\KnownTargetsHealth;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiNamespace;
use IMEdge\SnmpFeature\SnmpScenario\TargetState;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

#[ApiNamespace('snmp')]
class SnmpApi
{
    protected ?SnmpSocket $socket;
    protected ScenarioLoader $loader;
    protected bool $shuttingDown = false;

    public function __construct(
        protected readonly SnmpRunner $runner,
        protected readonly LoggerInterface $logger
    ) {
        // TODO: v6 socket, socket pool?
        $this->socket = new SnmpSocket();
        $this->loader = new ScenarioLoader($this->logger);
    }

    public function shutdown(): void
    {
        $this->shuttingDown = true;
    }

    #[ApiMethod]
    public function scenario(
        UuidInterface $credentialUuid,
        SocketAddress $address,
        string $name,
        ?UuidInterface $deviceUuid = null,
    ): SnmpResponse {
        $start = hrtime(true);
        try {
            return SnmpRequestHandler::handleRemoteResult(
                $this->getScenario($credentialUuid, $address, $name),
                $start,
                $address
            );
        } catch (\Exception $e) {
            return SnmpRequestHandler::handleRemoteFailure($e, $start, $address);
        }
    }

    #[ApiMethod]
    public function triggerScenario(
        string $name,
        UuidInterface $deviceUuid,
        ?int $delay = null,
    ): bool {
        $this->runner->getPeriodicScenarioRunner($this->loader->getClass($name))->trigger(
            $this->runner->targets->targets[$deviceUuid->toString()]
                ?? throw new RuntimeException('There is no such target: ' . $deviceUuid->toString()),
            $delay
        );

        return true;
    }

    #[ApiMethod]
    public function scenarioObject(
        UuidInterface $credentialUuid,
        SocketAddress $address,
        string $name,
        UuidInterface $deviceUuid = null,
    ): SnmpResponse {
        $nodeUuid = Uuid::fromString('00000000-0000-0000-0000-000000000000');

        $start = hrtime(true);
        try {
            $result = $this->getScenario($credentialUuid, $address, $name);
            $resultHandler = $this->loader->resultHandler($name);
            if ($resultHandler->needsWalk()) {
                return SnmpRequestHandler::handleRemoteResult(
                    $resultHandler->getResultObjectInstances($nodeUuid, $deviceUuid, $result),
                    $start,
                    $address
                );
            } else {
                return SnmpRequestHandler::handleRemoteResult(
                    $resultHandler->getResultObjectInstance($nodeUuid, $deviceUuid, $result),
                    $start,
                    $address
                );
            }
        } catch (\Exception $e) {
            return SnmpRequestHandler::handleRemoteFailure($e, $start, $address);
        }
    }

    protected function getScenario(
        UuidInterface $credentialUuid, // differs from @param!!
        SocketAddress $address,
        string $name,
    ): array {
        $loader = $this->loader;
        $resultHandler = $loader->resultHandler($name);
        $oids = $resultHandler->getScenarioOids();
        if (empty($oids)) {
            throw new InvalidArgumentException("Scenario $name has no OIDs");
        }

        $community = $this->runner->credentials->requireCredential($credentialUuid)->securityName;
        $tables = [];

        if ($resultHandler->needsWalk()) {
            foreach ($oids as $oid => $alias) {
                $tables[$alias] = $this->socket->walk($oid, (string) $address, $community);
            }
            return $resultHandler->fixResult($tables);
        } else {
            return $this->socket->get($oids, $address, $community);
        }
    }

    #[ApiMethod]
    public function listScenarios(): array
    {
        return $this->loader->listScenarios();
    }

    #[ApiMethod]
    public function getKnownTargetsHealth(): KnownTargetsHealth
    {
        return $this->runner->health;
    }

    #[ApiMethod]
    public function scanIpList(array $ips, SnmpCredential $credential): array
    {
        return IpListScanner::scan($ips, $credential->securityName, $this->logger);
    }

    #[ApiMethod]
    public function setCredentials(SnmpCredentials $credentials): bool
    {
        foreach ($credentials->credentials as $credential) {
            $this->logger->notice(sprintf('Got credential %s(%s)', $credential->name, $credential->uuid->toString()));
        }
        $this->runner->credentials = $credentials;

        return true;
    }

    #[ApiMethod]
    public function setKnownTargets(SnmpTargets $targets): bool
    {
        // 1788 targets -> 180kB
        // {"address":{"ip":"194.244.15.28","port":161},"credentialUuid":"92a9178c-6dee-432c-bc67-1d67776454a5"}]},"target":"730345e8-559b-45f3-b89d-184d866964cf","id":4058410},
        // 170 Bytes per target
        $diff = $this->runner->targets->listRemovedTargets($targets);
        $this->runner->targets = $targets;
        $health = $this->runner->health;
        foreach ($diff as $target) {
            $health->forget($target->identifier);
        }
        foreach ($targets->targets as $newTarget) {
            if (! $health->has($newTarget->identifier)) {
                $health->setCurrentResult($newTarget->identifier, TargetState::PENDING);
            }
        }
        $reg = new PeriodicScenarioRegistry();
        // $this->runner->launchPeriodicHealthChecks();
        $this->runner->launchPeriodicScenarios($reg->listScenarios());
        return true;
    }

    #[ApiMethod]
    public function get(
        UuidInterface $credentialUuid,
        SocketAddress $address,
        object $oidList,
    ): SnmpResponse {
        $community = $this->runner->credentials->requireCredential($credentialUuid)->securityName;
        $start = hrtime(true);
        try {
            return SnmpRequestHandler::handleRemoteResult(
                $this->socket->get((array) $oidList, $address, $community),
                $start,
                $address
            );
        } catch (\Exception $e) {
            return SnmpRequestHandler::handleRemoteFailure($e, $start, $address);
        }
    }

    #[ApiMethod]
    public function walk(
        UuidInterface $credentialUuid, // differs from @param!!
        SocketAddress $address,
        string $oid,
        ?int $limit = null,
        ?string $nextOid = null
    ): SnmpResponse {
        $community = $this->runner->credentials->requireCredential($credentialUuid)->securityName;
        $start = hrtime(true);
        try {
            return SnmpRequestHandler::handleRemoteResult(
                $this->socket->walk($oid, (string) $address, $community, $limit, $nextOid),
                $start,
                $address
            );
        } catch (\Exception $e) {
            return SnmpRequestHandler::handleRemoteFailure($e, $start, $address);
        }
    }
}
