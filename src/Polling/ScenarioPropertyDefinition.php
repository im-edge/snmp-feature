<?php

namespace IMEdge\SnmpFeature\Polling;

use IMEdge\Json\JsonSerialization;
use IMEdge\SnmpFeature\DataMangler\SnmpDataTypeManglerInterface;
use RuntimeException;
use stdClass;

class ScenarioPropertyDefinition implements JsonSerialization
{
    public function __construct(
        public readonly string $name,
        public readonly ScenarioPropertyType $type,
        // TODO: serializer needs to follow type for OID
        public readonly ?array $enumProperties = null, // TODO!
        // Which enum?
        // EntityPhysicalClass
        // InterfaceStatusOperational
        // InterfaceStatusDuplex
        // InterfaceStatusStp
        // IcomWmacBsTsCfgAdminStatus
        //... -> we need the list from the MIB!
        public readonly ?string $dbColumn = null,
        public readonly ?string $oid = null,
        // We could require named constructor properties
        /** [IMEdge\SnmpFeature\DataStructure\DeviceIdentifier, []] */
        /** or [IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue, ['cdpCacheIfIndex']] */
        public readonly ?array $value = null,
        /** ['ifInDiscards', 'COUNTER'] */
        /** or ['ifOutQLen', 'GAUGE'] */
        public readonly ?array $metric = null,
        /** @param SnmpDataTypeManglerInterface[] $manglers */
        /** [ [IMEdge\\SnmpFeature\\DataMangler\\LastOidOctetToInteger32, []], ...] */
        public readonly array $manglers = [],
    ) {
    }

    public static function fromSerialization($any): ScenarioPropertyDefinition
    {
        if (! $any instanceof stdClass) {
            throw new RuntimeException(
                'Cannot unserialize ScenarioPropertyDefinition, stdClass expected, got: '
                . get_debug_type($any)
            );
        }

        return new ScenarioPropertyDefinition(
            $any->name ?? throw new RuntimeException('ScenarioPropertyDefinition has no name'),
            ScenarioPropertyType::from(
                $any->type ?? throw new RuntimeException('ScenarioPropertyDefinition has no type')
            ),
            $any->enum ?? null ? (array) $any->enum : null,
            $any->dbColumn ?? null,
            $any->oid ?? null,
            $any->value ?? null,
            $any->metric ?? null,
            $any->manglers ?? [],
        );
    }

    public function jsonSerialize(): stdClass
    {
        $object = [
            'name' => $this->name,
            'type' => $this->type,
        ];
        if ($this->enumProperties !== null) {
            $object['enum'] = $this->enumProperties;
        }
        if ($this->oid !== null) {
            $object['oid'] = $this->oid;
        }
        if ($this->value !== null) {
            $object['value'] = $this->value;
        }
        if ($this->metric !== null) {
            $object['metric'] = $this->metric;
        }
        if (!empty($this->manglers)) {
            $object['manglers'] = $this->manglers;
        }

        return (object) $object;
    }
}
