<?php

namespace IMEdge\SnmpFeature\Capability;

use RuntimeException;

class UnspecifiedCapabilityType extends SimpleCapability
{
    protected const TYPE = CapabilityType::HAS_MIB;
    public function __construct(string $key)
    {
        parent::__construct($key);
        throw new RuntimeException('TYPE for SimpleCapability must be specified');
    }
}
