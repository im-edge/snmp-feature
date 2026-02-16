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
 * The cdpCacheTable contains the information received via CDP on one interface from one device.
 * Entries appear when a CDP advertisement is received from a neighbor device.
 * Entries disappear when CDP is disabled on the interface, or globally.
 */
#[SnmpTable([
    // Normally, the ifIndex value of the local interface.
    // For 802.3 Repeaters for which the repeater ports do not
    // have ifIndex values assigned, this value is a unique
    // value for the port, and greater than any ifIndex value
    // supported by the repeater; the specific port number in
    // this case, is given by the corresponding value of
    // cdpInterfacePort.
    new SnmpTableIndex('cdpCacheIfIndex', new Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.1')),
    new SnmpTableIndex('cdpCacheDeviceIndex', new Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.2')),
])]
#[PollingTask(name: 'cdpCache', defaultInterval: 300)]
#[DbTable(tableName: 'network_cdp_cache', keyProperties: [
    'device_uuid',
    'if_index',
    'cdp_cache_device_index'
])]
class PollCdpCache
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('cdpCacheIfIndex')]
        #[DbColumn('if_index')]
        public readonly int $cdpCacheIfIndex,

        #[SnmpTableIndexValue('cdpCacheDeviceIndex')]
        #[DbColumn('cdp_cache_device_index')]
        public readonly int $cdpCacheDeviceIndex,

        // ip(1), ipv6(20) -> CISCO-TC::CiscoNetworkProtocol
        // We should ignore others
        #[Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.3')]
        #[DbColumn('cdp_cache_address_type')]
        public readonly ?int $cdpCacheAddressType,

        // CISCO-TC::CiscoNetworkAddress, octet string
        // 4 bytes -> ipv4, 16 bytes -> ipv6
        #[Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.4')]
        #[DbColumn('cdp_cache_address')]
        public readonly ?string $cdpCacheAddress,

        // e.g. Cisco Nexus Operating System (NX-OS) Software, Version 9.3(11)
        #[Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.5')]
        #[DbColumn('cdp_cache_version')]
        public readonly ?string $cdpCacheVersion,

        #[Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.6')]
        #[DbColumn('cdp_cache_device_id')]
        public readonly ?string $cdpCacheDeviceId,

        #[Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.7')]
        #[DbColumn('cdp_cache_device_port')]
        public readonly ?string $cdpCacheDevicePort,

        #[Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.8')]
        #[DbColumn('cdp_cache_device_platform')]
        public readonly ?string $cdpCachePlatform,

        #[Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.11')]
        #[DbColumn('cdp_cache_native_vlan')]
        public readonly ?string $cdpCacheNativeVlan,

        #[Oid('1.3.6.1.4.1.9.9.23.1.2.1.1.12')]
        #[DbColumn('cdp_cache_duplex')]
        public readonly ?string $cdpCacheDuplex,
    ) {
    }
}
