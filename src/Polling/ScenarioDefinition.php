<?php

namespace IMEdge\SnmpFeature\Polling;

use IMEdge\Json\JsonSerialization;
use IMEdge\SnmpFeature\Capability\CapabilityHasChildOid;
use IMEdge\SnmpFeature\Capability\CapabilityHasMib;
use IMEdge\SnmpFeature\Capability\CapabilitySet;
use IMEdge\SnmpFeature\RequestedOidList;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use stdClass;

class ScenarioDefinition implements JsonSerialization
{
    public function __construct(
        public readonly UuidInterface $uuid,
        public readonly string $name,
        public readonly int $interval,
        public ?array $dbTable,
        public readonly string $requestType,
        /** @param ScenarioPropertyDefinition[] $properties */
        public readonly array $properties,
    ) {
    }

    public function listOids(): array
    {
        $oids = [];
        foreach ($this->properties as $name => $property) {
            if ($oid = $property->oid) {
                $oids[$name] = $oid;
            }
        }

        return $oids;
    }

    public function getOidList(): RequestedOidList
    {
        return new RequestedOidList($this->listOids());
    }

    public function getRequiredCapabilities(): CapabilitySet
    {
        $capabilities = [];
        foreach ($this->getRequiredMibs() as $mib) {
            $capabilities[] = new CapabilityHasMib($mib);
        }
        foreach ($this->getRequiredChildOids() as $oid) {
            $capabilities[] = new CapabilityHasChildOid($oid);
        }

        return new CapabilitySet($capabilities);
    }

    public function getRequiredMibs(): array
    {
        return [];
    }

    public function getRequiredChildOids(): array
    {
        return [];
    }

    public function getOffset(): int
    {
        return ConsistencyHelper::uuidToNumber($this->uuid) % $this->interval;
    }

    public static function fromSerialization($any): ScenarioDefinition
    {
        $properties = [];
        foreach ($any->properties ?? [] as $property) {
            $properties[] = ScenarioPropertyDefinition::fromSerialization($property);
        }

        return new ScenarioDefinition(
            Uuid::fromString($any->uuid),
            $any->name,
            $any->interval,
            $any->dbTable ? (array) $any->dbTable : null,
            $any->requestType,
            $properties
        );
    }

    public function jsonSerialize(): stdClass
    {
        $object = [
            'uuid'        => $this->uuid,
            'name'        => $this->name,
            'interval'    => $this->interval,
            'requestType' => $this->requestType,
            'properties'  => $this->properties,
        ];
        if ($this->dbTable) {
            $object['dbTable'] = $this->dbTable;
        }

        return (object) $object;
    }
}
