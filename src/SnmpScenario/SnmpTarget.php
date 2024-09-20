<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

use IMEdge\Json\JsonSerialization;
use IMEdge\Snmp\SocketAddress;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class SnmpTarget implements JsonSerialization
{
    public function __construct(
        public readonly string $identifier,
        public readonly SocketAddress $address,
        public readonly UuidInterface $credentialUuid,
        public TargetState $state = TargetState::PENDING,
        protected array $supportedFeatures = [],
        // lastError?
    ) {
    }

    public static function fromSerialization($any): SnmpTarget|static
    {
        return new static(
            identifier: $any->identifier,
            address: SocketAddress::fromSerialization($any->address),
            credentialUuid: Uuid::fromString($any->credentialUuid),
            state: isset($any->state) ? TargetState::from($any->state) : TargetState::PENDING,
            supportedFeatures: $any->supportedFeatures ?? []
        );
    }

    /**
     * Currently unused. Idea: get told,
     * @param string $feature
     * @return bool
     */
    public function enableFeature(string $feature): bool
    {
        if (in_array($feature, $this->supportedFeatures, true)) {
            return false;
        }
        $this->supportedFeatures[] = $feature;

        return true;
    }

    public function jsonSerialize(): object
    {
        return (object) [
            'identifier'        => $this->identifier,
            'address'           => $this->address,
            'credentialUuid'    => $this->credentialUuid,
            'state'             => $this->state,
            'supportedFeatures' => $this->supportedFeatures,
        ];
    }
}
