<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use Psr\Log\LoggerInterface;

class ScenarioPoller
{
    protected bool $running = false;

    public function __construct(
        protected LoggerInterface $logger,
        public readonly object $scenario,
        public readonly SnmpTargets $targets,
        public readonly int $interval,
    ) {
    }

    public function start(): void
    {
        $this->running = true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function pause(): void
    {
        $this->running = false;
    }
}
