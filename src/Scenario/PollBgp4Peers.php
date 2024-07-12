<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\Metrics\MetricDatatype;
use IMEdge\SnmpFeature\DataMangler\MangleToBinaryIp;
use IMEdge\SnmpFeature\DataMangler\OctetStringToIp;
use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\Metric;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

/**
 * BGP4-MIB::bgpPeerEntry: Entry containing information about the connection with a BGP peer.
 *
 *
 * TODO:
 *
 * BGP4-MIB
 * BGP4v2-MIG
 * CISCO-BGP4-MIB
 * BGP4-V2-MIB-JUNIPER
 * -- and others!!
 *
 * local address
 * peer address
 * type eBGP / iBGP ?
 * Family
 * Remote AS
 * Peer description
 *
 * State: start (admin up?) / established
 * Last error: ...
 * Uptime / updates in/out
 *
 * remoteAs === localAs -> iBGP, else eBGP
 * remoteAs between 64512 and 65534, 4200000000 and 4294967294 -> Private ASN range
 *
 * TODO; 1.3.6.1.2.1.15.2, BGP4-MIB::bgpLocalAs: The local autonomous system number
 */
#[SnmpTable([
    new SnmpTableIndex('bgpPeerRemoteAddr', new Oid('1.3.6.1.2.1.15.3.1.7'), true, 4),
])]
#[PollingTask(name: 'bgp4Peers', defaultInterval: 60)]
#[DbTable(tableName: 'bgp_peer', keyProperties: ['device_uuid', 'if_index', 'cdp_cache_device_index'])]
class PollBgp4Peers
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('bgpPeerRemoteAddr')]
        #[DbColumn('remote_address')]
        public readonly int $remoteAddress,

        /**
         * The BGP Identifier of this entry's BGP peer.
         *
         * This entry MUST be 0.0.0.0 unless the bgpPeerState is in the openconfirm or the established state
         */
        #[Oid('1.3.6.1.2.1.15.3.1.1')]
        #[OctetStringToIp]
        #[MangleToBinaryIp]
        #[DbColumn('peer_identifier')]
        public readonly string $peerIdentifier,

        /**
         * The BGP peer connection state
         *
         * Integer, possible values:
         *
         *  - idle (1)
         *  - connect (2)
         *  - active (3)
         *  - opensent (4)
         *  - openconfirm (5)
         *  - established (6)
         */
        #[Oid('1.3.6.1.2.1.15.3.1.2')]
        #[DbColumn('peer_state')]
        public readonly int $peerState,

        /**
         * The desired state of the BGP connection
         *
         * A transition from 'stop' to 'start' will cause the BGP Manual Start Event to be generated.
         * A transition from 'start' to 'stop' will cause the BGP Manual Stop Event to be generated.
         *
         * This parameter can be used to restart BGP peer connections.  Care should be used in providing
         * write access to this object without adequate authentication.
         *
         * Integer, possible values:
         *
         *  - stop (1)
         *  - start (2)
         */
        #[Oid('1.3.6.1.2.1.15.3.1.3')]
        #[DbColumn('peer_admin_status')]
        public readonly int $peerAdminStatus,

        // The local IP address of this entry's BGP connection
        #[OctetStringToIp]
        #[MangleToBinaryIp]
        #[Oid('1.3.6.1.2.1.15.3.1.5')]
        #[DbColumn('peer_local_address')]
        public readonly string $peerLocalAddr,

        // The remote IP address of this entry's BGP connection
        #[OctetStringToIp]
        #[MangleToBinaryIp]
        #[Oid('1.3.6.1.2.1.15.3.1.7')]
        #[DbColumn('peer_remote_address')]
        public readonly string $peerRemoteAddr,

        // The remote autonomous system number received in the BGP OPEN message
        #[Oid('1.3.6.1.2.1.15.3.1.9')]
        #[DbColumn('peer_remote_as')]
        public readonly string $peerRemoteAs,

        // The number of BGP UPDATE messages received on this connection
        #[Oid('1.3.6.1.2.1.15.3.1.10')]
        #[Metric('bgpInUpdates', MetricDatatype::COUNTER)]
        public readonly string $peerInUpdates,

        // The number of BGP UPDATE messages transmitted on this connection
        #[Oid('1.3.6.1.2.1.15.3.1.11')]
        #[Metric('bgpOutUpdates', MetricDatatype::COUNTER)]
        public readonly string $peerOutUpdates,

        // The number of BGP UPDATE messages received on this connection
        #[Oid('1.3.6.1.2.1.15.3.1.12')]
        #[Metric('bgpPeerInTotalMessages', MetricDatatype::COUNTER)]
        public readonly string $peerInTotalMessages,

        // The number of BGP UPDATE messages transmitted on this connection
        #[Oid('1.3.6.1.2.1.15.3.1.13')]
        #[Metric('bgpPeerOutTotalMsg', MetricDatatype::COUNTER)]
        public readonly string $peerOutTotalMessages,

        /**
         * The last error code and subcode seen by this peer on this connection
         *
         * If no error has occurred, this field is zero. Otherwise, the first byte of this two byte OCTET STRING
         * contains the error code, and the second byte contains the subcode.
         */
        #[Oid('1.3.6.1.2.1.15.3.1.14')]
        #[DbColumn('peer_last_error')]
        public readonly string $peerLastError,

        // The total number of times the BGP FSM transitioned into the established state for this peer
        #[Oid('1.3.6.1.2.1.15.3.1.15')]
        #[Metric('bgpPeerFsmEstTrans', MetricDatatype::COUNTER)]
        public readonly string $peerFsmEstablishedTransitions,

        /**
         * This timer indicates how long (in seconds) this peer has been in the established state or how long
         * since this peer was last in the established state.
         *
         * It is set to zero when a new peer is configured or when the router is booted.
         */
        #[Oid('1.3.6.1.2.1.15.3.1.16')]
        #[Metric('bgpPeerFsmEstTime')]
        public readonly string $peerFsmEstablishedTime,

        /**
         * Elapsed time (in seconds) since the last BGP UPDATE message was received from the peer.
         * Each time bgpPeerInUpdates is incremented, the value of this object is set to zero (0).
         */
        #[Oid('1.3.6.1.2.1.15.3.1.24')]
        #[Metric('bgpPeerInUpdElTime')]
        public readonly string $peerInUpdateElapsedTime,

        // vrf_id, context_name?
    ) {
    }
}
