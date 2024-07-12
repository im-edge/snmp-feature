<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

/**
 * ENTITY-MIB::entAliasMappingTable
 *
 * This table contains zero or more rows, representing
 * mappings of logical entity and physical component to
 * external MIB identifiers.  Each physical port in the system
 * may be associated with a mapping to an external identifier,
 * which itself is associated with a particular logical
 * entity's naming scope.  A 'wildcard' mechanism is provided
 * to indicate that an identifier is associated with more than
 * one logical entity.
 *
 * Information about a particular physical equipment, logical
 * entity to external identifier binding.  Each logical
 * entity/physical component pair may be associated with one
 * alias mapping.  The logical entity index may also be used as
 * a 'wildcard' (refer to the entAliasLogicalIndexOrZero object
 * DESCRIPTION clause for details.)
 * Note that only entPhysicalIndex values that represent
 * physical ports (i.e., associated entPhysicalClass value is
 * 'port(10)') are permitted to exist in this table.
 */
#[PollingTask('entityIfMap', 300)]
#[DbTable('inventory_entity_ifmap', [
    'device_uuid'  => 'deviceUuid',
    'entity_index' => 'entityIndex'
])]
#[SnmpTable([
    // TODO: Reihenfolge!!
    new SnmpTableIndex('entPhysicalIndex', new Oid('1.3.6.1.2.1.47.1.1.1.1.1')),
    new SnmpTableIndex('entAliasLogicalIndexOrZero', new Oid('1.3.6.1.2.1.47.1.3.2.1.1')),
])]
class PollEntityIfMap
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('entPhysicalIndex')]
        #[DbColumn('entity_index')]
        public readonly int $entityIndex,

        #[SnmpTableIndexValue('entAliasLogicalIndexOrZero')]
        #[DbColumn('if_index')]
        public readonly int $ifIndex,

        /**
         * The value of this object identifies a particular conceptual
         * row associated with the indicated entPhysicalIndex and
         * entLogicalIndex pair.
         * Because only physical ports are modeled in this table, only
         * entries that represent interfaces or ports are allowed.  If
         * an ifEntry exists on behalf of a particular physical port,
         * then this object should identify the associated 'ifEntry'.
         * For repeater ports, the appropriate row in the
         * 'rptrPortGroupTable' should be identified instead.
         * For example, suppose a physical port was represented by
         * entPhysicalEntry.3, entLogicalEntry.15 existed for a
         * repeater, and entLogicalEntry.22 existed for a bridge.  Then
         * there might be two related instances of
         * entAliasMappingIdentifier:
         * entAliasMappingIdentifier.3.15 == rptrPortGroupIndex.5.2
         * entAliasMappingIdentifier.3.22 == ifIndex.17
         * It is possible that other mappings (besides interfaces and
         * repeater ports) may be defined in the future, as required.
         * Bridge ports are identified by examining the Bridge MIB and
         * appropriate ifEntries associated with each 'dot1dBasePort',
         * and are thus not represented in this table.
         */
        #[Oid('1.3.6.1.2.1.47.1.3.2.1.2')]
        // e.g. 1.3.6.1.2.1.2.2.1.1.10101 = ifIndex.10101
        // Modifier: strip base OID 1.3.6.1.2.1.2.2.1.1 (ifIndex), ignore others for now
        public readonly string $entAliasMappingIdentifier,
    ) {
    }
}
