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

// CISCO-VRF-MIB::cvVrfTable
#[SnmpTable([
    new SnmpTableIndex('cvVrfIndex', new Oid('1.3.6.1.4.1.9.9.711.1.1.1.1')),
])]
#[PollingTask(name: 'ciscoVrf', defaultInterval: 300)]
#[DbTable('snmp_vrf_cisco', [
    'device_uuid' => 'deviceUuid',
    'vrf_index'   => 'vrfIndex',
])]
class PollVrfCisco
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('cvVrfIndex')]
        #[DbColumn('vrf_index')]
        public readonly int $vrfIndex,

        #[DbColumn('vrf_name')]
        #[Oid('1.3.6.1.4.1.9.9.711.1.1.1.1.2')]
        public readonly string $name,

        #[DbColumn('vnet_tag')]
        #[Oid('1.3.6.1.4.1.9.9.711.1.1.1.1.3')]
        public readonly int $tag,

        #[DbColumn('vrf_operational_status')]
        // 1 -> up, 2 -> down. ENUM?
        #[Oid('1.3.6.1.4.1.9.9.711.1.1.1.1.4')]
        public readonly int $operationalStatus,

        /**
         * BITS: none (0), other (1), ospf (2), rip (3), isis (4), eigrp (5), bgp (6)
         */
        #[DbColumn('routing_protocols')]
        #[Oid('1.3.6.1.4.1.9.9.711.1.1.1.1.4')]
        public readonly string $routingProtocols, // cvVrfRouteDistProt
    ) {
    }
}
