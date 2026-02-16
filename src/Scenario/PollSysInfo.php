<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\DataNodeIdentifier;
use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\Oid;
use Ramsey\Uuid\UuidInterface;

#[PollingTask('sysInfo', 10)]
#[DbTable('snmp_system_info', [
    'uuid' => 'uuid'
])]
class PollSysInfo
{
    public function __construct(
        // TODO: like DeviceIdentifier -> ip , port , credentialsUuid, protocol. Or: connectionDefinitionUuid
        #[DeviceIdentifier]
        #[DbColumn('uuid')]
        public readonly UuidInterface $uuid,

        #[DataNodeIdentifier]
        #[DbColumn('datanode_uuid')]
        public readonly UuidInterface $datanodeUuid,

        // SMMPv2-MIB RFC1213

        #[Oid('1.3.6.1.2.1.1.5.0')]
        #[DbColumn('system_name')]
        public readonly ?string $sysName = null,

        #[Oid('1.3.6.1.2.1.1.1.0')]
        #[DbColumn('system_description')]
        public readonly ?string $sysDescr = null,

        #[Oid('1.3.6.1.2.1.1.6.0')]
        #[DbColumn('system_location')]
        public readonly ?string $sysLocation = null,

        #[Oid('1.3.6.1.2.1.1.4.0')]
        #[DbColumn('system_contact')]
        public readonly ?string $sysContact = null,

        #[Oid('1.3.6.1.2.1.1.7.0')]
        #[DbColumn('system_services')]
        public readonly ?int $sysServices = null,

        #[Oid('1.3.6.1.2.1.1.2.0')]
        #[DbColumn('system_oid')]
        public readonly ?Oid $sysObjectID = null,

        // TODO: Timetick handling. 100stel-Sekunden, 32bit: läuft nach 497 Tagen über
        // #[Oid('1.3.6.1.2.1.1.3.0')]
        // #[Metric('sysUpTime')]
        public readonly ?int $sysUpTime = null,

        // --- SNMP-FRAMEWORK-MIB ---

        #[Oid('1.3.6.1.6.3.10.2.1.1.0')]
        #[DbColumn('system_engine_id')]
        public readonly ?string $snmpEngineId = null,

        #[Oid('1.3.6.1.6.3.10.2.1.2.0')]
        #[DbColumn('system_engine_boot_count')]
        public readonly ?int $snmpEngineBoots = null,

        #[xOid('1.3.6.1.6.3.10.2.1.3.0')]
        #[DbColumn('system_engine_boot_time')]
        public readonly ?int $snmpEngineTime = null, // Use instead of sysUptime, if available? Fallback?

        #[Oid('1.3.6.1.6.3.10.2.1.4.0')]
        #[DbColumn('system_engine_max_message_size')]
        public readonly ?int $snmpEngineMaxMessageSize = null,

        // --- HOST-RESOURCES-MIB ---

        #[Oid('1.3.6.1.2.1.25.1.1.0')]
        public readonly ?int $hrSystemUptime = null, // Use instead of sysUptime, if available? Fallback?

        /**
         * The MAC address used by this bridge when it must be
         * referred to in a unique fashion.  It is recommended
         * that this be the numerically smallest MAC address of
         * all ports that belong to this bridge.  However, it is only
         * required to be unique.  When concatenated with
         * dot1dStpPriority, a unique BridgeIdentifier is formed,
         * which is used in the Spanning Tree Protocol.
         *
         * Syntax for MacAddress is defined in SNMPv2-TC: OctetString, 6 Bytes
         */
        #[Oid('1.3.6.1.2.1.17.1.1.0')]
        #[DbColumn('dot1d_base_bridge_address')]
        public readonly ?string $dot1dBaseBridgeAddress = null,

        /**
         * TODO: ENTITY-MIB::entLastChangeTime, 1.3.6.1.2.1.47.1.4.1
         *
         * The value of sysUpTime at the time a conceptual row is
         * created, modified, or deleted in any of these tables:
         * - entPhysicalTable
         * - entLogicalTable
         * - entLPMappingTable
         * - entAliasMappingTable
         * - entPhysicalContainsTable
         */
    ) {
    }
}
