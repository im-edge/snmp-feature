<?php

namespace IMEdge\SnmpFeature\Scenario;

use Ramsey\Uuid\UuidInterface;

class GlobalLookupMaps
{
    /**
     * @var array<string, array> Device UUID -> map
     */
    protected array $maps;

    public function lookup(string $mapName, UuidInterface $deviceUuid, string $key, mixed $defaultValue = null): mixed
    {
        return $defaultValue;
    }
}
