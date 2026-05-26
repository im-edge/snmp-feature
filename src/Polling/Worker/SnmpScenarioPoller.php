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
use IMEdge\RedisTables\RedisTables;
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
use IMEdge\SnmpFeature\SnmpScenario\TargetState;
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
    protected RedisTables $redisTables;

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
        $this->redisTables = new RedisTables(
            $this->nodeIdentifier->uuid->toString(),
            $this->redisClient,
            $this->logger
        );
        // TODO: ship from DB
        $this->scenarios = ScenarioDefinitionLoader::fromJsonFile(dirname(__DIR__, 3) . '/data/scenarios.json');
    }

    #[ApiMethod]
    public function setCredentials(SnmpCredentials $credentials): bool
    {
        $this->credentials = $credentials;
        $this->logger->notice('ScenarioPoller got credentials');
        foreach ($credentials->credentials as $credential) {
            $credNew = $credential->toEngineCredential();
            foreach ($this->targets->targets as $target) {
                $address = InternetAddress::fromString($target->address);
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
            $address = InternetAddress::fromString($target->address);
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

        return $this->pollScenarioDefinition($target, $scenario);
    }

    public function start(): void
    {
        // $this->logger->notice('SNMP Scenario Poller has been started');
        EventLoop::queue($this->launchSubscription(...));
    }

    public function stop(): void
    {
        $this->subscription->unsubscribe();
        $this->subscription = null;
        // $this->logger->notice('SNMP Scenario Poller has been stopped');
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
            }
        }

        return null;
    }

    private function launchSubscription(): void
    {
        if ($this->subscription) {
            return;
        }
        $this->subscription = $this->subscriber->subscribe(TaskMessage::STREAM_NAME_TASKS);
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
        foreach (TaskMessage::parse($message, $this->logger, $this->targets, $this->scenarios)->tasks as $task) {
            foreach ($task->targets->targets as $target) {
                // TODO: temporarily avoids too many requests. We need better reachability/health logic
                if ($task->scenario->name !== 'sysInfo' && $target->state !== TargetState::REACHABLE) {
                    continue;
                }
                $this->logger->debug(sprintf(
                    "Polling %s on %s (task triggered)",
                    $task->scenario->name,
                    $target->address
                ));
                $this->runScenarioAndShip($task->scenario, $target);
            }
        }
    }

    protected function runScenarioAndShip(ScenarioDefinition $scenario, SnmpTarget $target): void
    {
        async(function () use ($scenario, $target) {
            try {
                $this->activeTasks++;
                $this->shipResult($target, $scenario, $this->pollScenarioDefinition($target, $scenario));
                if ($target->state !== TargetState::REACHABLE) {
                    $this->redisTables->setTableEntry('snmp_target_health', $target->identifier, ['uuid'], [
                        'uuid'  => $target->identifier,
                        'state' => TargetState::REACHABLE->value,
                    ]);
                    $target->state = TargetState::REACHABLE;
                }
            } catch (Throwable $e) {
                if ($target->state !== TargetState::FAILING) {
                    if ($scenario->name === 'sysInfo') {
                        $target->state = TargetState::FAILING;
                        $this->logger->error(sprintf(
                            'Polling %s on %s failed, disabling non-health checks: %s',
                            $scenario->name,
                            $target->address,
                            $e->getMessage()
                        ));
                        $this->redisTables->setTableEntry('snmp_target_health', $target->identifier, ['uuid'], [
                            'uuid'  => $target->identifier,
                            'state' => TargetState::FAILING->value,
                        ]);
                    } else {
                        $this->logger->error(sprintf(
                            'Polling %s on %s failed: %s',
                            $scenario->name,
                            $target->address,
                            $e->getMessage()
                        ));
                    }
                }
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
            20_000,
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
