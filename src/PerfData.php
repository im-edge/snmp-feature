<?php

namespace IMEdge\SnmpFeature;

class PerfData
{
    public function __construct(
        public int $timestamp,
        public string $agent,
        public string $service,
        public ?string $instance = null,
    ) {
    }
}
