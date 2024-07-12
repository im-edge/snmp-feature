<?php

namespace IMEdge\SnmpFeature;

class Request
{
    public function __construct(
        public readonly string $ip,
        public readonly string $security_name,
        public readonly RequestedOidList $oidList,
        public readonly string $requestType = 'get',
    ) {
    }
}
