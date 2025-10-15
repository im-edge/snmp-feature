<?php

namespace IMEdge\SnmpFeature\Capability;

abstract class SimpleCapability implements SupportedCapability
{
    protected const TYPE = CapabilityType::UNSPECIFIED;

    public function __construct(
        protected readonly string $key,
    ) {
    }

    public function getType(): CapabilityType
    {
        return static::TYPE;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @param array{0: string, 1: string}$any
     * @return SupportedCapability
     */
    public static function fromSerialization($any): SupportedCapability
    {
        $class = CapabilityType::from($any[0])->getImplementation();
        return new $class($any[1]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function jsonSerialize(): array
    {
        return [$this->getType()->value, $this->getKey()];
    }
}
