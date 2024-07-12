<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataMangler\LastOidOctetToInteger32;
use IMEdge\SnmpFeature\DataMangler\OctetStringToIp;
use IMEdge\SnmpFeature\DataMangler\OidToOctetString;
use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\InetAddressType;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use IMEdge\SnmpFeature\DataStructure\StorageType;
use Ramsey\Uuid\UuidInterface;

#[SnmpTable([
    new SnmpTableIndex('ipAddressAddrType', new Oid('1.3.6.1.2.1.4.34.1.1')),
    new SnmpTableIndex('ipAddressAddr', new Oid('1.3.6.1.2.1.4.34.1.2'), false),
])]
#[PollingTask(name: 'ipAddressTable', defaultInterval: 900)]
#[xxDbTable('network_ipaddress_...', ['device_uuid', 'if_index'])]
#[FilterIgnore('ipAddressAddrType = 16 | ipAddressAddrType = 0')] // TODO: ignore unknown and DNS IP address types
class PollIpAddressTable
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[DbColumn('if_index')]
        #[Oid('1.3.6.1.2.1.4.34.1.3')]
        public readonly int $ipAddressIfIndex,

        #[SnmpTableIndexValue('ipAddressAddrType')]
        public readonly InetAddressType $ipAddressAddrType,

        #[Oid('1.3.6.1.2.1.4.34.1.11')]
        public readonly StorageType $ipAddressStorageType,

        #[SnmpTableIndexValue('ipAddressAddr')]
        #[OidToOctetString]
        #[OctetStringToIp]
        public readonly string $ipAddressAddr,

        #[Oid('1.3.6.1.2.1.4.34.1.5')]
        #[LastOidOctetToInteger32]
        public readonly ?int $ipAddressPrefix = null,
    ) {
    }
}
