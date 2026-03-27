<?php

namespace IMEdge\SnmpFeature\Polling\Worker;

use Amp\Redis\RedisClient;
use IMEdge\Config\Settings;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Json\JsonString;
use IMEdge\Node\ImedgeWorker;
use IMEdge\RedisUtils\RedisResult;
use IMEdge\RpcApi\ApiMethod;
use IMEdge\RpcApi\ApiNamespace;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinitionLoader;
use IMEdge\SnmpFeature\Polling\Worker\ResultHandler\MetricWriter;
use IMEdge\SnmpFeature\Polling\Worker\ResultHandler\ScenarioResultProcessor;
use IMEdge\SnmpFeature\Redis\ImedgeRedis;
use IMEdge\SnmpFeature\SnmpResponse;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

#[ApiNamespace('snmpScenarioResultHandler')]
class SnmpScenarioResultHandler implements ImedgeWorker
{
    protected RedisClient $redis;
    protected array $targets = [];
    /** @var array<string, ScenarioDefinition> indexed by human-readable UUID */
    protected array $scenarios;
    /** @var ScenarioResultProcessor[] */
    protected array $scenarioProcessors = [];

    protected string $streamOffset = '0-0';
    protected bool $running = false;

    public function __construct(
        protected readonly Settings $settings,
        protected readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
    ) {
        $this->scenarios = ScenarioDefinitionLoader::fromJsonFile(dirname(__DIR__, 3) . '/data/scenarios.json');
        foreach ($this->scenarios as $key => $scenario) {
            $this->scenarioProcessors[$key] = new ScenarioResultProcessor(
                $scenario,
                $this->nodeIdentifier,
                $this->logger
            );
        }
        $this->redis = ImedgeRedis::client('snmpScenarioResultHandler');
    }

    #[ApiMethod]
    public function setTargets(SnmpTargets $targets): bool
    {
        $this->targets = $targets->targets;
        $this->logger->notice('Result handler got targets');

        return true;
    }

    #[ApiMethod]
    public function setMetricStorePath(?string $path): bool
    {
        if ($path === null) {
            $writer = null;
        } elseif (null === $writer = new MetricWriter($path, $this->logger)) {
            $this->logger->error("Got metric store path $path, but MetricStore is not available");
            return false;
        }

        $this->applyMetricWriter($writer);
        return true;
    }

    protected function applyMetricWriter(?MetricWriter $writer): void
    {
        foreach ($this->scenarioProcessors as $scenario) {
            $scenario->setMetricWriter($writer);
        }
    }

    public function start(): void
    {
        $this->logger->notice('SNMP Scenario Result Handler has been started');
        $this->running = true;
        $this->redis->execute('DEL', SnmpScenarioPoller::STREAM_NAME_RESULTS);
        // TODO: Remember former position instead, or use timeMs-0
        EventLoop::queue($this->readFromStream(...));
    }

    public function stop(): void
    {
        $this->running = false;
        $this->logger->notice('SNMP Scenario Result Handler has been stopped');
    }

    public function getApiInstances(): array
    {
        return [$this];
    }

    protected function readFromStream(): void
    {
        try {
            $streamResults = $this->fetchStreamResults();
        } catch (\Exception $e) {
            $this->logger->error('Stopping scenario result handler on error: ' . $e->getMessage());
            $this->running = false;
            return;
        }

        foreach ($streamResults as $streamResult) {
            try {
                $this->processStreamResult($streamResult);
            } catch (\Throwable $e) {
                $this->logger->error(
                    'Processing streamed SNMP result failed: ' . $e->getMessage() . $e->getFile() . $e->getLine()
                );
            }
        }

        if ($this->running) {
            EventLoop::queue($this->readFromStream(...));
        }
    }

    protected function fetchStreamResults(): array
    {
        return $this->redis->execute(
            'XREAD',
            'COUNT',
            1_000,
            'BLOCK',
            10_000, // ms
            'STREAMS',
            SnmpScenarioPoller::STREAM_NAME_RESULTS,
            $this->streamOffset
        )[0][1] ?? [];
    }

    protected function processStreamResult($streamResult): void
    {
        $this->streamOffset = $streamResult[0];
        $properties = RedisResult::toHash($streamResult[1]);
        $scenario = $this->scenarios[$properties->scenario] ?? null;
        if ($scenario === null) {
            $this->logger->notice('Ignoring result for unknown scenario: ' . $properties->scenario);
            return;
        }
        $target = $this->targets[$properties->target] ?? null;
        if ($target === null) {
            $this->logger->notice('Ignoring result for unknown target: ' . $properties->target);
            return;
        }
        $this->scenarioProcessors[$scenario->uuid->toString()]->processResponse(
            SnmpResponse::fromSerialization(JsonString::decode($properties->response)),
            $target
        );
    }
}
