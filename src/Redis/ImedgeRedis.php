<?php

namespace IMEdge\SnmpFeature\Redis;

use Amp\Redis\RedisClient;
use IMEdge\Node\ApplicationContext;

use function Amp\Redis\createRedisClient;
use function Amp\Redis\createRedisConnector;

class ImedgeRedis
{
    public static function getDefaultUri(): string
    {
        return 'unix://' . ApplicationContext::getRedisSocket();
    }

    public static function client(string $clientName, ?string $redisUri = null): RedisClient
    {
        $redisUri ??= self::getDefaultUri();

        return createRedisClient(
            $redisUri,
            new NamedRedisConnector($clientName, createRedisConnector($redisUri))
        );
    }
}
