<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataMangler\MangleToBinaryIp;
use IMEdge\SnmpFeature\DataStructure\DataNodeIdentifier;
use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\Icom\IcomWmacBsTsCfgAdminStatus;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

/**
 * PollIcomBsTsConfig
 *
 * The icomWmacBsTsCfgTable table provides BS-side TS-specific configuration parameters
 */
#[SnmpTable([
    new SnmpTableIndex('icomWmacBsId', new Oid('1.3.6.1.4.1.1807.112.1.1.1.1')),
    new SnmpTableIndex('icomWmacBsTsId', new Oid('1.3.6.1.4.1.1807.112.1.3.1.1')),
])]
#[PollingTask(name: 'icomBsTsConfig', defaultInterval: 180)]
#[DbTable(tableName: 'icom_bs_ts_config', keyProperties: [
    'system_uuid' => 'systemUuid',
    'bs_id'       => 'bsId',
    'bs_ts_id'    => 'bsTsId',
])]
class PollIcomBsTsConfig
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('system_uuid')]
        public readonly UuidInterface $systemUuid,

        #[DataNodeIdentifier]
        #[DbColumn('datanode_uuid')]
        public readonly UuidInterface $datanodeUuid,

        #[SnmpTableIndexValue('icomWmacBsId')]
        #[DbColumn('bs_id')]
        public readonly int $bsId,

        #[SnmpTableIndexValue('icomWmacBsTsId')]
        #[DbColumn('bs_ts_id')]
        public readonly int $bsTsId,

        #[DbColumn('status_admin')]
        public readonly ?IcomWmacBsTsCfgAdminStatus $statusAdmin = null,

        #[Oid('1.3.6.1.4.1.1807.112.1.3.1.3')]
        #[DbColumn('config_mac_address')]
        public readonly ?string $cfgMacAddress = null,

        #[Oid('1.3.6.1.4.1.1807.112.1.4.1.17')]
        #[MangleToBinaryIp]
        #[DbColumn('status_ip_address')]
        public readonly ?string $statusIpAddress = null,
    ) {
    }
}
