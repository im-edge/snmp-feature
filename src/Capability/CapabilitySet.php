<?php

namespace IMEdge\SnmpFeature\Capability;

use IMEdge\Json\JsonSerialization;
use RuntimeException;

class CapabilitySet implements JsonSerialization
{
    public function __construct(
        /** @var SupportedCapability[] */
        public readonly array $capabilities
    ) {
    }

    public static function fromSerialization($any): CapabilitySet
    {
        if (! is_array($any)) {
            throw new RuntimeException('CapabilitySet expects an array');
        }

        $capabilities = [];
        foreach ($any as $capability) {
            if (! isset($capability[0]) || ! is_string($capability[0])) {
                throw new RuntimeException('Capability must be a string');
            }
            $type = CapabilityType::from($capability[0]);
            $class = $type->getImplementation();
            $capabilities[] = $class::fromSerialization($capability);
        }

        return new CapabilitySet($capabilities);
    }

    public function hasCapability(SupportedCapability $checkCapability): bool
    {
        return in_array($checkCapability, $this->capabilities, true);
    }

    public function supports(CapabilitySet $checkSet): bool
    {
        foreach ($checkSet->capabilities as $checkCapability) {
            if (!$this->hasCapability($checkCapability)) {
                return false;
            }
        }

        return true;
    }

    public function jsonSerialize(): array
    {
        return $this->capabilities;
    }
}
