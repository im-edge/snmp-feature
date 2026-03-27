<?php

namespace IMEdge\SnmpFeature\Polling\Worker;

use Amp\Redis\RedisClient;
use IMEdge\Config\Settings;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Node\ImedgeWorker;
use IMEdge\RedisUtils\LuaScriptRunner;
use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiNamespace;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinitionLoader;
use IMEdge\SnmpFeature\Polling\Worker\Scheduler\SnmpScenarioScheduler;
use IMEdge\SnmpFeature\Redis\ImedgeRedis;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Revolt\EventLoop;

#[ApiNamespace('snmpScenarioController')]
class SnmpScenarioController implements ImedgeWorker
{
    protected RedisClient $redis;
    protected SnmpScenarioScheduler $scheduler;
    protected LuaScriptRunner $luaRunner;
    protected ?int $lastEndTs = null;
    protected float $timeOffset;
    /** @var ScenarioDefinition[] */
    protected array $scenarios;

    public function __construct(
        protected readonly Settings $settings,
        protected readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
    ) {
        $this->timeOffset = microtime(true) - hrtime(true) / 1_000_000_000;
        $this->redis = ImedgeRedis::client('snmpScenarioController');
        $this->luaRunner = new LuaScriptRunner($this->redis, dirname(__DIR__, 3) . '/lua', $this->logger);
        // TODO: ship from DB
        $this->scenarios = ScenarioDefinitionLoader::fromJsonFile(dirname(__DIR__, 3) . '/data/scenarios.json');
        $this->scheduler = new SnmpScenarioScheduler();
        $this->scheduler->on(SnmpScenarioScheduler::ON_CHANGES, $this->pushScenariosToRedis(...));
        $this->scheduler->on(SnmpScenarioScheduler::ON_SLOTS, $this->pushScenariosSlotsToRedis(...));
        foreach ($this->scenarios as $scenario) {
            $this->scheduler->addScenario($scenario);
        }
    }

    /**
     * @return ScenarioDefinition[]
     */
    #[ApiMethod]
    public function getScenarios(): array
    {
        return $this->scenarios;
    }

    protected function pushScenariosToRedis(): void
    {
        // Not yet
        // $this->luaRunner->runScript('pushScenarios', $this->scheduler->xxx());
    }

    protected function pushScenariosSlotsToRedis(): void
    {
        // Not yet
        // $this->luaRunner->runScript('pushScenarios', $this->scheduler->xxx());
    }

    #[ApiMethod]
    public function triggerScenarioByName(string $scenarioName, UuidInterface $targetUuid, ?int $delay = null): bool
    {
        $this->logger->notice("Triggering scenario $scenarioName for " . $targetUuid->toString());
        $scenario = $this->scheduler->requireScenarioByName($scenarioName);
        $target = $this->scheduler->requireTarget($targetUuid->toString());
        EventLoop::delay($delay, fn() => $this->scheduler->triggerScenario($scenario, $target));

        return true;
    }

    /**
     * @param ScenarioDefinition[] $scenarios
     */
    #[ApiMethod]
    public function setScenarios(array $scenarios): bool
    {
        return true;
    }

    #[ApiMethod]
    public function setTargets(SnmpTargets $targets): bool
    {
        $this->logger->notice('Scenario controller got targets');
        $this->scheduler->setTargets($targets);

        return true;
    }

    public function start(): void
    {
        $this->logger->notice('SNMP Scenario Controller has been started');
    }

    public function stop(): void
    {
        $this->logger->notice('SNMP Scenario Controller has been stopped');
    }

    public function getApiInstances(): array
    {
        return [$this];
    }
}
