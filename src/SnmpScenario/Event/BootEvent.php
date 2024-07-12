<?php

namespace IMEdge\SnmpFeature\SnmpScenario\Event;

class BootEvent
{
    public const NAME = 'BOOT';

    public function __construct(
        public readonly string $agentKey,
    ) {
    }
}
