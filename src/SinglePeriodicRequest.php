<?php

namespace IMEdge\SnmpFeature;

use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;

class SinglePeriodicRequest
{
    public function __construct(
        public readonly SnmpTarget $target,
        public readonly RequestedOidList $oidList,
        public readonly string $requestType = 'get',
    ) {
    }
}
