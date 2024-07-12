<?php

namespace IMEdge\SnmpFeature\SnmpScenario\Event;

class IfUpEvent
{
    public const NAME = 'IF_UP';

    public function __construct(
        public readonly string $agentKey,
        public readonly int $ifIndex,
    ) {
    }
}
