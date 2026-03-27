<?php

namespace IMEdge\SnmpFeature\Polling\Worker;

use Amp\Pipeline\DisposedException;
use Amp\Redis\RedisClient;
use Amp\Redis\RedisSubscriber;
use Amp\Redis\RedisSubscription;
use Amp\Socket\InternetAddress;
use IMEdge\Config\Settings;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Json\JsonString;
use IMEdge\Node\ImedgeWorker;
use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiNamespace;
use IMEdge\SnmpEngine\Dispatcher\SnmpDispatcher;
use IMEdge\SnmpEngine\SnmpPoller;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinitionLoader;
use IMEdge\SnmpFeature\Redis\ImedgeRedis;
use IMEdge\SnmpFeature\Scenario\SnmpTableHelper;
use IMEdge\SnmpFeature\SnmpCredentials;
use IMEdge\SnmpFeature\SnmpResponse;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Revolt\EventLoop;
use Throwable;

use function Amp\async;

#[ApiNamespace('snmpScenarioPoller')]
class SnmpScenarioPoller implements ImedgeWorker
{
    public const STREAM_NAME_RESULTS = 'snmp:result';
    public const STREAM_NAME_TASKS = 'snmp:task';

    protected RedisClient $client;
    protected RedisSubscriber $subscriber;
    protected ?RedisSubscription $subscription = null;
    protected SnmpTargets $targets;
    protected SnmpCredentials $credentials;
    /** @var array<string, ScenarioDefinition> indexed by human-readable UUID */
    protected array $scenarios;
    protected RedisClient $redisClient;
    protected SnmpPoller $engine;
    protected int $activeTasks = 0;

    public function __construct(
        protected readonly Settings $settings,
        protected readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
    ) {
        $this->engine = new SnmpPoller(new SnmpDispatcher($this->logger));
        $this->targets = new SnmpTargets();
        $this->credentials = new SnmpCredentials([]);
        $this->subscriber = ImedgeRedis::subscriber('snmpScenarioPoller/poller');
        $this->redisClient = ImedgeRedis::client('snmpScenarioPoller/resultShipper');
        // TODO: ship from DB
        $this->scenarios = ScenarioDefinitionLoader::fromJsonFile(dirname(__DIR__, 3) . '/data/scenarios.json');
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
                    // $this->logger->debug('Registering ' . $target->identifier);
                    $this->engine->registerClient(
                        $target->identifier,
                        $address,
                        $credNew
                    );
                }
            }
        }

        return true;
    }

    #[ApiMethod]
    public function setTargets(SnmpTargets $targets): bool
    {
        $this->targets = $targets;
        $this->logger->notice('Poller got targets');
        foreach ($targets->targets as $target) {
            $credential = $this->credentials->credentials[$target->credentialUuid->getBytes()] ?? null;
            if ($credential === null) {
                $this->logger->notice('No credential for ' . $target->identifier);
                continue;
            }
            $credNew = $credential->toEngineCredential();
            // $this->logger->debug('Registering ' . $target->identifier);
            $address = new InternetAddress($target->address->ip, $target->address->port);
            if ($target->credentialUuid->equals($credential->uuid)) {
                $this->engine->registerClient(
                    $target->identifier,
                    $address,
                    $credNew
                );
            }
        }

        return true;
    }

    #[ApiMethod]
    public function runScenario(InternetAddress $address, UuidInterface $scenarioUuid): SnmpResponse
    {
        // TODO: dynamic target
        $target = $this->getOptionalTargetByAddress($address)
            ?? throw new InvalidArgumentException('Poller has no target for: ' . $address);

        $scenario = $this->requireScenario($scenarioUuid);
        $this->logger->notice(sprintf("Polling %s on %s (on demand)", $scenario->name, $target->address));

        $response = $this->pollScenarioDefinition($target, $scenario);
        $result = SnmpTableHelper::flipTableResult($response->result);
        foreach ($result as $instanceKey => $varBinds) {
            if ($scenario->snmpTableIndexes) {
                SnmpTableHelper::appendTableIndexesToVarBindList(
                    $instanceKey,
                    $varBinds,
                    $scenario->snmpTableIndexes
                );
            }
        }

        return new SnmpResponse(
            $response->success,
            $response->source,
            $result,
            $response->errorMessage,
            $response->duration
        );
    }

    #[ApiMethod]
    public function runScenarioByName(InternetAddress $address, string $scenarioName): SnmpResponse
    {
        // TODO: dynamic target
        $target = $this->getOptionalTargetByAddress($address)
            ?? throw new InvalidArgumentException('Poller has no target for: ' . $address);

        $scenario = $this->requireScenarioByName($scenarioName);
        $this->logger->notice(sprintf("Polling %s on %s (on demand)", $scenario->name, $target->address));

        $response = $this->pollScenarioDefinition($target, $scenario);
        $result = SnmpTableHelper::flipTableResult($response->result);
        foreach ($result as $instanceKey => $varBinds) {
            if ($scenario->snmpTableIndexes) {
                SnmpTableHelper::appendTableIndexesToVarBindList(
                    $instanceKey,
                    $varBinds,
                    $scenario->snmpTableIndexes
                );
            }
        }

        return new SnmpResponse(
            $response->success,
            $response->source,
            $result,
            $response->errorMessage,
            $response->duration
        );
    }

    public function start(): void
    {
        $this->logger->notice('SNMP Scenario Poller has been started');
        EventLoop::queue($this->launchSubscription(...));
    }

    public function stop(): void
    {
        $this->subscription->unsubscribe();
        $this->subscription = null;
        $this->logger->notice('SNMP Scenario Poller has been stopped');
    }

    public function getApiInstances(): array
    {
        return [$this];
    }

    protected function requireScenario(UuidInterface $uuid): ScenarioDefinition
    {
        return $this->scenarios[$uuid->toString()]
            ?? throw new InvalidArgumentException('Got no such scenario: ' . $uuid->toString());
    }

    /**
     * @deprecated future: UUID only
     */
    protected function requireScenarioByName(string $name): ScenarioDefinition
    {
        foreach ($this->scenarios as $scenario) {
            if ($name === $scenario->name) {
                return $scenario;
            }
        }

        throw new InvalidArgumentException("Got no such scenario: $name");
    }

    protected function getOptionalTargetByAddress(InternetAddress $address): ?SnmpTarget
    {
        $addressDiff = $address->toString();
        foreach ($this->targets->targets as $target) {
            if ($target->address->toString() === $addressDiff) {
                return $target;
            } else {
                $this->logger->notice(sprintf('%s != %s', $addressDiff, $target->address->toString()));
            }
        }

        return null;
    }

    private function launchSubscription(): void
    {
        if ($this->subscription) {
            return;
        }
        $this->subscription = $this->subscriber->subscribe(self::STREAM_NAME_TASKS);
        try {
            foreach ($this->subscription as $message) {
                $this->processTaskMessage($message);
            }
        } catch (DisposedException) {
            // Ignoring this exception, @see https://github.com/amphp/redis/issues/100
        }
        if ($this->subscription !== null) {
            // We did not call stop()
            $this->logger->notice('SNMP Scenario Poller lost it\'s Redis subscription, well retry in 3 seconds');
            EventLoop::delay(3, $this->launchSubscription(...));
            $this->subscription = null;
        }
    }

    /**
     * Processes messages as we get them from the Redis subscription
     */
    protected function processTaskMessage(string $message): void
    {
        foreach ($this->parseTaskMessage($message) as [$scenario, $targets]) {
            foreach ($targets as $target) {
                $this->logger->debug(sprintf("Polling %s on %s (task triggered)", $scenario->name, $target->address));
                $this->runScenarioAndShip($scenario, $target);
            }
        }
    }

    // publish snmp:task 8702b50f-4686-5c3e-988c-6287b95d0d24:f550e741-0869-43d7-9123-82ed252550c3,
    //    1cb0212b-9bae-45ca-8328-94692159f1c9;
    /**
     * @param string $message
     * @return array<int, array{0: ScenarioDefinition, 1: SnmpTarget[]}>
     */
    protected function parseTaskMessage(string $message): array
    {
        $tasks = [];
        foreach (preg_split('/;/', $message, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (strpos($part, ':') === false) {
                $this->logger->error("Got invalid message part on scenario poller subscription: $part");
                continue;
            }
            [$scenarioIdx, $targetIdxs] = explode(':', $part, 2);
            $scenario = $this->scenarios[$scenarioIdx] ?? null;
            if ($scenario === null) {
                $this->logger->warning('Poller has no such scenario: ' . $scenarioIdx);
                continue;
            }
            $targets = [];
            foreach (preg_split('/,/', $targetIdxs, -1, PREG_SPLIT_NO_EMPTY) as $targetIdx) {
                $target = $this->targets->targets[$targetIdx] ?? null;
                if ($target === null) {
                    $this->logger->warning('Poller has no such target: ' . $targetIdx);
                    continue;
                }
                $targets[] = $target;
            }
            if (empty($targets)) {
                continue;
            }

            $tasks[] = [$scenario, $targets];
        }

        return $tasks;
    }

    protected function runScenarioAndShip(ScenarioDefinition $scenario, $target): void
    {
        async(function () use ($scenario, $target) {
            try {
                $this->activeTasks++;
                $this->shipResult($target, $scenario, $this->pollScenarioDefinition($target, $scenario));
            } catch (Throwable $e) {
                $this->logger->error(sprintf(
                    'Polling %s on %s failed: %s',
                    $scenario->name,
                    $target->address,
                    $e->getMessage()
                ));
            }
            $this->activeTasks--;
        });
    }

    private function pollScenarioDefinition(SnmpTarget $target, ScenarioDefinition $scenario): SnmpResponse
    {
        // assert($target->address instanceof InternetAddress);
        // $client->trace = new SnmpPacketTrace();
        $start = hrtime(true);
        if ($scenario->requestType === 'get') {
            $result = $this->engine->get($target->identifier, array_flip($scenario->listOids()));
        } else {
            // $this->logger->notice(print_r(array_flip($scenario->listOids()), true));
            $oids = array_values($scenario->listOids());
            $oids = array_combine($oids, $oids);
            // $this->logger->notice(print_r($oids, true));
            $result = $this->engine->getTables($target->identifier, $oids, $scenario->defaultMaxRepetitions ?? 10);
        }

        return SnmpResponse::success($target->address, $start, $result);
    }

    /**
     * Appends the
     */
    private function shipResult(SnmpTarget $target, ScenarioDefinition $scenario, SnmpResponse $response): void
    {
        // $this->logger->notice(JsonString::encode($response));
        $this->redisClient->execute(
            'XADD',
            self::STREAM_NAME_RESULTS,
            'MAXLEN',
            '~',
            100_000,
            '*',
            'scenario',
            $scenario->uuid->toString(),
            'target',
            $target->identifier,
            'response',
            JsonString::encode($response)
        );
    }
}
