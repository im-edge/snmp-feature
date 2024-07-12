<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\Metrics\MetricDatatype;
use IMEdge\SnmpFeature\DataStructure\Measurement;
use IMEdge\SnmpFeature\DataStructure\Metric;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;

#[SnmpTable([new SnmpTableIndex('ifIndex', new Oid('1.3.6.1.2.1.2.2.1.1'))])]
#[PollingTask(name: 'interfacePacket', defaultInterval: 60)]
#[Measurement('if_packets', 'ifIndex')]
class PollInterfacePackets
{
    public function __construct(
        #[SnmpTableIndexValue('ifIndex')]
        public readonly int $ifIndex,

        #[Oid('1.3.6.1.2.1.2.2.1.11')]
        #[Metric('ifInUcastPkts', MetricDatatype::COUNTER)]
        public readonly int $ifInUcastPkts,

        #[Oid('1.3.6.1.2.1.2.2.1.17')]
        #[Metric('ifOutUcastPkts', MetricDatatype::COUNTER)]
        public readonly int $ifOutUcastPkts,

        #[Oid('1.3.6.1.2.1.2.2.1.12')]
        #[Metric('ifInNUcastPkts', MetricDatatype::COUNTER)]
        public readonly ?int $ifInNUcastPkts = null,

        #[Oid('1.3.6.1.2.1.2.2.1.18')]
        #[Metric('ifOutNUcastPkts', MetricDatatype::COUNTER)]
        public readonly ?int $ifOutNUcastPkts = null,
    ) {
    }
}
