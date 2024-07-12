<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

use JsonSerializable;

class KnownTargetsHealth implements JsonSerializable
{
    protected array $targets = [];

    public function setCurrentResult(string $target, TargetState $state): void
    {
        $this->targets[$target] = $state;
    }

    public function isReachable(string $target): bool
    {
        return ($this->targets[$target] ?? null) === TargetState::REACHABLE;
    }

    public function has(string $target): bool
    {
        return isset($this->targets[$target]);
    }

    public function forget(string $target): void
    {
        unset($this->targets[$target]);
    }

    public function jsonSerialize(): array
    {
        return $this->targets;
    }
}
