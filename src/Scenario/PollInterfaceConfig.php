<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataMangler\DivideInteger;
use IMEdge\SnmpFeature\DataMangler\ExactStringLengthOrNull;
use IMEdge\SnmpFeature\DataMangler\MangleToUtf8;
use IMEdge\SnmpFeature\DataMangler\MultiplyInteger;
use IMEdge\SnmpFeature\DataStructure\DataNodeIdentifier;
use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\InterfaceStatusConfigured;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use IMEdge\SnmpFeature\DataStructure\TruthValue;
use Ramsey\Uuid\UuidInterface;

#[SnmpTable([new SnmpTableIndex('ifIndex', new Oid('1.3.6.1.2.1.2.2.1.1'))])]
#[PollingTask(name: 'interfaceConfig', defaultInterval: 600)]
#[LookupMap(keyProperty: 'ifIndex', valueProperty: 'ifDescr')]
#[DbTable(tableName: 'snmp_interface_config', keyProperties: [
    'system_uuid' => 'systemUuid',
    'if_index'    => 'ifIndex',
])]
class PollInterfaceConfig
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('system_uuid')]
        public readonly UuidInterface $systemUuid,

        #[DataNodeIdentifier]
        #[DbColumn('datanode_uuid')]
        public readonly UuidInterface $datanodeUuid,

        #[SnmpTableIndexValue('ifIndex')]
        #[DbColumn('if_index')]
        public readonly int $ifIndex,

        #[DbColumn('status_admin')]
        public readonly ?InterfaceStatusConfigured $statusAdmin = null,

        #[Oid('1.3.6.1.2.1.2.2.1.3')]
        #[DbColumn('if_type')]
        public readonly ?string $ifType = null, // TODO: data?

        #[Oid('1.3.6.1.2.1.31.1.1.1.1')]
        #[MangleToUtf8]
        #[DbColumn('if_name')]
        public readonly ?string $ifName = null,

        #[Oid('1.3.6.1.2.1.31.1.1.1.18')]
        #[MangleToUtf8]
        #[DbColumn('if_alias')]
        public readonly ?string $ifAlias = null,

        #[Oid('1.3.6.1.2.1.2.2.1.2')]
        #[MangleToUtf8]
        #[DbColumn('if_description')]
        public readonly ?string $ifDescr = null,

        #[Oid('1.3.6.1.2.1.2.2.1.4')]
        #[DbColumn('mtu')]
        public readonly ?int $ifMtu = null,

        #[Oid('1.3.6.1.2.1.2.2.1.5')]
        #[Oid('1.3.6.1.2.1.31.1.1.1.15')] // highSpeed
        #[DbColumn('speed_kbit')]
        #[MultiplyInteger(1000)]
        public readonly ?int $ifSpeed = null,

        #[Oid('1.3.6.1.2.1.2.2.1.6')]
        #[DbColumn('physical_address')] // + physical_address_plain + oui
        #[ExactStringLengthOrNull(6)]
        public readonly ?string $physicalAddress = null,

        // false(2) if this interface only accepts packets/frames that are addressed to this station
        #[Oid('1.3.6.1.2.1.31.1.1.1.16')]
        #[DbColumn('promiscuous_mode')]
        public readonly ?TruthValue $promiscuousMode = null,
    ) {
    }
}

// 'ifSpecific'        => '.1.3.6.1.2.1.2.2.1.22',
