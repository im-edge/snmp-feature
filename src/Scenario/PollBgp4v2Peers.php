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
 * BGP4-MIB::bgpPeerEntry: Entry containing information about the connection with a BGP peer.
 */
#[SnmpTable([
    new SnmpTableIndex('bgpPeerRemoteAddr', new Oid('1.3.6.1.2.1.15.3.1.7'), true, 4),
])]
#[PollingTask(name: 'bgp4v2Peers', defaultInterval: 60)]
#[DbTable(tableName: 'bgp_peer', keyProperties: ['device_uuid', 'if_index', 'cdp_cache_device_index'])]
class PollBgp4v2Peers
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('bgpPeerRemoteAddr')]
        #[DbColumn('remote_address')]
        public readonly int $remoteAddress,

        /**
         * The routing instance index - BGP4V2-MIB
         *
         * Some BGP implementations permit the creation of
         * multiple instances of a BGP routing process. An
         * example includes routers running BGP/MPLS IP Virtual
         * Private Networks.
         * Implementations that do not support multiple
         * routing instances should return 1 for this object.
         */
        // bgp4V2PeerInstance - 1.3.6.1.4.1.1991.3.5.1.1.2.1.1
        #[DbColumn('routing_instancee_idx')]
        public readonly ?int $routingInstanceIndex = null,
    ) {
    }
}
