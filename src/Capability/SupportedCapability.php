<?php

namespace IMEdge\SnmpFeature\Capability;

use IMEdge\Json\JsonSerialization;

interface SupportedCapability extends JsonSerialization
{
    public function getType(): CapabilityType;
    public function getKey(): string;
    public static function fromSerialization($any): SupportedCapability;
}
