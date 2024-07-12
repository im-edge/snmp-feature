<?php

namespace IMEdge\SnmpFeature;

class RequestedOidList
{
    public function __construct(
        public readonly array $oidList
    ) {
    }
}
