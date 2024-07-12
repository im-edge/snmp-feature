<?php

namespace IMEdge\SnmpFeature\SnmpScenario\Event;

class IfDownEvent
{
    public const NAME = 'IF_DOWN';

    public function __construct(
        public readonly string $agentKey,
        public readonly int $ifIndex,
    ) {
    }
}
