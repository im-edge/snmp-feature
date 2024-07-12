<?php

namespace IMEdge\SnmpFeature\SnmpScenario\Event;

class PerfDataEvent
{
    public const NAME = 'PERF_DATA';

    public function __construct(
        public readonly string $agentKey,
        public readonly string $counterSet,
        public readonly string|int|null $instance,
        public readonly int $timestamp,
        public readonly array $counters,
    ) {
    }
}
