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
#[PollingTask(name: 'interfaceError', defaultInterval: 60)]
#[Measurement('if_error', 'ifIndex')]
class PollInterfaceErrors
{
    public function __construct(
        #[SnmpTableIndexValue('ifIndex')]
        public readonly int $ifIndex,

        #[Oid('1.3.6.1.2.1.2.2.1.13')]
        #[Metric('ifInDiscards', MetricDatatype::COUNTER)]
        public readonly int $ifInDiscards,

        #[Oid('1.3.6.1.2.1.2.2.1.14')]
        #[Metric('ifInErrors', MetricDatatype::COUNTER)]
        public readonly int $ifInErrors,

        #[Oid('1.3.6.1.2.1.2.2.1.19')]
        #[Metric('ifOutDiscards', MetricDatatype::COUNTER)]
        public readonly int $ifOutDiscards,

        #[Oid('1.3.6.1.2.1.2.2.1.20')]
        #[Metric('ifOutErrors', MetricDatatype::COUNTER)]
        public readonly int $ifOutErrors,

        // TODO: Check, if in use at all?!
        #[Oid('1.3.6.1.2.1.2.2.1.15')]
        #[Metric('ifInUnknownProtos', MetricDatatype::COUNTER)]
        public readonly int $ifInUnknownProtos,
    ) {
    }
}
