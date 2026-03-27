<?php

namespace IMEdge\SnmpFeature\Polling\Worker\ResultHandler;

use IMEdge\Json\JsonString;
use IMEdge\Metrics\Measurement;
use IMEdge\MetricsFeature\MetricStore;
use IMEdge\Node\ApplicationContext;
use IMEdge\RedisUtils\LuaScriptRunner;
use IMEdge\SnmpFeature\Redis\ImedgeRedis;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class MetricWriter
{
    protected ?LuaScriptRunner $luaScriptRunner = null;

    public function __construct(string $path, protected LoggerInterface $logger)
    {
        $this->initialize($path);
    }

    /**
     * @param Measurement[] $measurements
     */
    public function shipMeasurements(array $measurements): void
    {
        if ($this->luaScriptRunner === null) {
            return;
        }

        if (! empty($measurements)) {
            $this->luaScriptRunner?->runScript(
                'shipMeasurements',
                array_map(JsonString::encode(...), $measurements)
            );
        }
    }

    protected function initialize(string $path): void
    {
        // TODO: Node contract for metrics
        if (class_exists(MetricStore::class)) {
            try {
                $store = new MetricStore($path, new NullLogger());
                $store->requireBeingConfigured();
                $redisSocket = 'unix://' . $store->getRedisSocketPath();
                $this->luaScriptRunner = new LuaScriptRunner(
                    ImedgeRedis::client('snmp/metricWriter', $redisSocket),
                    ApplicationContext::getEnabledFeaturesDirectory() . '/metrics/lua',
                    $this->logger
                );
            } catch (Throwable $e) {
                $this->luaScriptRunner =  null;
                $this->logger->error($e->getMessage());
            }
        }
    }
}
