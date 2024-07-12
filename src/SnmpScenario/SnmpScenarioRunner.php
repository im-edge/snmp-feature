<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

use Evenement\EventEmitter;

class SnmpScenarioRunner
{
    protected EventEmitter $eventDispatcher;

    public function __construct(
        public readonly ObjectsList $credentials = new ObjectsList(),
        public readonly ObjectsList $knownAgents = new ObjectsList(),
        public readonly ObjectsList $reachableAgents = new ObjectsList(),
    ) {
        $this->eventDispatcher = new EventEmitter();
    }

    protected function init(): void
    {
    }
}
