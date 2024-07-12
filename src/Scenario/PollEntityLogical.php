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

#[PollingTask('entityLogical', 300)]
#[DbTable('inventory_logical_entity', [
    'device_uuid'   => 'deviceUuid',
    'logical_index' => 'logicalIndex'
])]
#[SnmpTable([new SnmpTableIndex('entLogicalIndex', new Oid('1.3.6.1.2.1.47.1.2.1.1.1'))])]
class PollEntityLogical
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('entLogicalIndex')]
        #[DbColumn('logical_index')]
        public readonly int $logicalIndex,

        /**
         * A textual description of the logical entity.  This object
         * should contain a string that identifies the manufacturer's
         * name for the logical entity, and should be set to a distinct
         * value for each version of the logical entity.
         *
         * e.g. FastEthernet0, vlan1002, mst-0
         */
        #[Oid('1.3.6.1.2.1.47.1.2.1.1.2')]
        #[DbColumn('description')]
        public readonly ?string $description, // entLogicalDescr

        /**An indication of the type of logical entity.  This will
         * typically be the OBJECT IDENTIFIER name of the node in the
         * SMI's naming hierarchy which represents the major MIB
         * module, or the majority of the MIB modules, supported by the
         * logical entity.  For example:
         *
         * a logical entity of a regular host/router -> mib-2
         * a logical entity of a 802.1d bridge -> dot1dBridge
         * a logical entity of a 802.3 repeater -> snmpDot3RptrMgmt
         *
         * If an appropriate node in the SMI's naming hierarchy cannot
         * be identified, the value 'mib-2' should be used.
         *
         * Hint: BRIDGE-MIB::dot1dBridge -> value = 1.3.6.1.2.1.17
         * Other example:
         *   1.3.6.1.4.1.9.12.3.1.10.48 (CISCO-ENTITY-VENDORTYPE-OID-MIB::cevPortNIC100)
         */
        #[Oid('1.3.6.1.2.1.47.1.2.1.1.3')]
        #[DbColumn('logical_type')]
        public readonly ?string $logicalType, // entLogicalType

        /**
         * e.g. public, public@1002, public@mst-0
         */
        #[Oid('1.3.6.1.2.1.47.1.2.1.1.4')]
        #[DbColumn('logical_community')]
        public readonly ?string $logicalCommunity, // entLogicalCommunity

        /**
         * The transport service address by which the logical entity
         * receives network management traffic, formatted according to
         * the corresponding value of entLogicalTDomain.
         * For snmpUDPDomain, a TAddress is 6 octets long: the initial
         * 4 octets contain the IP-address in network-byte order and
         * the last 2 contain the UDP port in network-byte order.
         * Consult 'Transport Mappings for the Simple Network
         * Management Protocol' (STD 62, RFC 3417 [RFC3417]) for
         * further information on snmpUDPDomain.
         *
         * <code>
         * $logicalTAddress = 'd5158f4f00a1';
         * $port = hexdec(substr($logicalTAddress, -4)); // 161
         * $ip = inet_ntop(hex2bin(substr('d5158f4f00a1', 0, -4))); // 213.21.143.79
         * </code>
         *
         */
        #[Oid('1.3.6.1.2.1.47.1.2.1.1.5')]
        public readonly ?string $logicalTAddress, // entLogicalTAddress

        /**
         * Indicates the kind of transport service by which the
         * logical entity receives network management traffic.
         * Possible values for this object are presently found in the
         * Transport Mappings for Simple Network Management Protocol'
         * (STD 62, RFC 3417 [RFC3417]).
         *
         * e.g. 1.3.6.1.6.1.1 -> SNMPv2-TM::snmpUDPDomain
         */
        // #[Oid('1.3.6.1.2.1.47.1.2.1.1.6')]
        // public readonly ?string $logicalTDomain, // entLogicalTDomain

        /**
         * The authoritative contextEngineID that can be used to send
         * an SNMP message concerning information held by this logical
         * entity, to the address specified by the associated
         * 'entLogicalTAddress/entLogicalTDomain' pair.
         * This object, together with the associated
         * entLogicalContextName object, defines the context associated
         * with a particular logical entity, and allows access to SNMP
         * engines identified by a contextEngineId and contextName
         * pair.
         * If no value has been configured by the agent, a zero-length
         * string is returned, or the agent may choose not to
         * instantiate this object at all.
         *
         * e.g. 0x80000009030000082f601401
         */
        #[Oid('1.3.6.1.2.1.47.1.2.1.1.7')]
        #[DbColumn('logical_context_engine_id')]
        public readonly ?string $logicalContextEngineID, // entLogicalContextEngineID

        /**
         * The contextName that can be used to send an SNMP message
         * concerning information held by this logical entity, to the
         * address specified by the associated
         * 'entLogicalTAddress/entLogicalTDomain' pair.
         * This object, together with the associated
         * entLogicalContextEngineID object, defines the context
         * associated with a particular logical entity, and allows
         * access to SNMP engines identified by a contextEngineId and
         * contextName pair.
         * If no value has been configured by the agent, a zero-length
         * string is returned, or the agent may choose not to
         * instantiate this object at all.
         *
         * e.g. '', vlan-1002, mst-0
         */
        #[Oid('1.3.6.1.2.1.47.1.2.1.1.8')]
        #[DbColumn('logical_context_name')]
        public readonly ?string $logicalContextName, // entLogicalContextName
    ) {
    }
}
