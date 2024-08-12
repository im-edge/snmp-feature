<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\Metrics\MetricDatatype;
use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\Measurement;
use IMEdge\SnmpFeature\DataStructure\Metric;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

#[SnmpTable([new SnmpTableIndex('ifIndex', new Oid('1.3.6.1.2.1.2.2.1.1'))])]
#[PollingTask(name: 'interfaceTraffic', defaultInterval: 15)]
#[NotYetNoAggDbTable('network_interface_status', [
    'device_uuid' => 'deviceUuid',
    'if_index'    => 'ifIndex',
])]
#[Measurement('if_traffic', 'ifIndex')]
class PollInterfaceTraffic
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('ifIndex')]
        #[MapLookup('ifNames', 'ifIndex')]
        public readonly int $ifIndex,

        #[Oid('1.3.6.1.2.1.31.1.1.1.6')]
        #[Metric('ifOctetsIn', MetricDatatype::COUNTER)]
        public readonly int $ifInOctets,

        #[Oid('1.3.6.1.2.1.31.1.1.1.10')]
        #[Metric('ifOctetsOut', MetricDatatype::COUNTER)]
        public readonly int $ifOutOctets,

        #[Oid('1.3.6.1.2.1.2.2.1.21')]
        #[Metric('ifOutQLen', MetricDatatype::GAUGE)]
        public readonly ?int $ifOutQLen = null,
    ) {
    }
}
