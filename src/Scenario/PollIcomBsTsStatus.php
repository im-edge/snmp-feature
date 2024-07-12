<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\DataNodeIdentifier;
use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\Icom\IcomWmacBsTsStatusConnectivity;
use IMEdge\SnmpFeature\DataStructure\Icom\IcomWmacBsTsStatusDiuc;
use IMEdge\SnmpFeature\DataStructure\Icom\IcomWmacBsTsStatusUiuc;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

/**
 * PollIcomBsTsConfig
 *
 * The icomWmacBsTsCfgTable table provides BS-side TS-specific current status parameters
 */
#[SnmpTable([
    new SnmpTableIndex('icomWmacBsId', new Oid('1.3.6.1.4.1.1807.112.1.1.1.1')),
    new SnmpTableIndex('icomWmacBsTsId', new Oid('1.3.6.1.4.1.1807.112.1.3.1.1')),
])]
#[PollingTask(name: 'icomBsTsStatus', defaultInterval: 180)]
#[DbTable(tableName: 'icom_bs_ts_status', keyProperties: [
    'system_uuid' => 'systemUuid',
    'bs_id'       => 'bsId',
    'bs_ts_id'    => 'bsTsId',
])]
class PollIcomBsTsStatus
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

        #[DbColumn('status_connectivity')]
        public readonly ?IcomWmacBsTsStatusConnectivity $statusConnectivity = null,

        // icomWmacBsTsStatusDlSnr
        // SNR: Signal / Noise Ratio
        #[Oid('1.3.6.1.4.1.1807.112.1.4.1.10')] //
        #[DbColumn('downlink_snr')]
        public readonly ?int $statusDlSnr = null,

        // icomWmacBsTsStatusUlSnr
        #[Oid('1.3.6.1.4.1.1807.112.1.4.1.13')]
        #[DbColumn('uplink_snr')]
        public readonly ?int $statusUlSnr = null,

        // icomWmacBsTsStatusUlRssi
        //
        // Current UL RSSI as reported by the TS
        // Available only in CONNECTED state
        // RSSI: "Received Signal Strength Indication"
        #[Oid('1.3.6.1.4.1.1807.112.1.4.1.12')]
        #[DbColumn('uplink_rssi')]
        public readonly ?string $statusUlRssi = null,

        // icomWmacBsTsStatusDlRssi
        //
        // Current DL RSSI as reported by the TS
        // Available only in CONNECTED state
        // hint -> seems to show only negative numbers
        #[Oid('1.3.6.1.4.1.1807.112.1.4.1.9')]
        #[DbColumn('downlink_rssi')]
        public readonly ?int $statusDlRssi = null,

        #[DbColumn('status_diuc')]
        public readonly ?IcomWmacBsTsStatusDiuc $statusDiuc = null,

        #[DbColumn('status_uiuc')]
        public readonly ?IcomWmacBsTsStatusUiuc $statusUiuc = null,

        // icomWmacBsTsStatusDlFecStress
        // Current DL FEC stress as reported by the TS
        // Available only in CONNECTED state
        #[Oid('1.3.6.1.4.1.1807.112.1.4.1.11')]
        #[DbColumn('downlink_fec_stress')]
        public readonly ?int $statusDlFecStress = null,

        // icomWmacBsTsStatusUlFecStress
        // Current UL FEC stress as reported by the TS
        // Available only in CONNECTED state
        #[Oid('1.3.6.1.4.1.1807.112.1.4.1.14')]
        #[DbColumn('uplink_fec_stress')]
        public readonly ?int $statusUlFecStress = null,

        // icomWmacBsTsStatusUpTime
        //
        // Uptime since the last time the TS entered the network
        // Available only in CONNECTED state
        // hint -> incrementing float, must be transformed into datetime before storing
        #[Oid('1.3.6.1.4.1.1807.112.1.4.1.3')]
        // #[DbColumn('status_uptime')]
        public readonly ?int $statusUptime = null,
    ) {
    }
}
