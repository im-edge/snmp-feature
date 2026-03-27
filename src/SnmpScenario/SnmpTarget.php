<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

use Amp\Socket\InternetAddress;
use IMEdge\Json\JsonSerialization;
use IMEdge\SnmpFeature\Capability\CapabilitySet;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class SnmpTarget implements JsonSerialization
{
    public function __construct(
        public readonly string $identifier,
        public readonly InternetAddress $address,
        public readonly UuidInterface $credentialUuid,
        public TargetState $state = TargetState::PENDING,
        public CapabilitySet $capabilities = new CapabilitySet([]),
        // lastError?
    ) {
    }

    public function wants(ScenarioDefinition $scenario): bool
    {
        return $this->capabilities->supports($scenario->getRequiredCapabilities());
    }

    public static function fromSerialization($any): SnmpTarget|static
    {
        return new static(
            identifier: $any->identifier,
            address: InternetAddress::fromString($any->address),
            credentialUuid: Uuid::fromString($any->credentialUuid),
            state: isset($any->state) ? TargetState::from($any->state) : TargetState::PENDING,
            capabilities: isset($any->capabilities)
                ? CapabilitySet::fromSerialization($any->capabilities)
                : new CapabilitySet([]),
        );
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'identifier'     => $this->identifier,
            'address'        => (string) $this->address,
            'credentialUuid' => $this->credentialUuid,
            'state'          => $this->state,
            'capabilities'   => $this->capabilities,
        ];
    }
}
