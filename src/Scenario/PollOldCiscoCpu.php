<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\Measurement;
use IMEdge\SnmpFeature\DataStructure\Metric;
use IMEdge\SnmpFeature\DataStructure\Oid;

#[PollingTask('oldCiscoCpu', 60)]
#[Measurement('ciscoCpu', null)]
class PollOldCiscoCpu
{
    public function __construct(
        #[Metric('avgBusy1')]
        #[Oid('1.3.6.1.4.1.9.2.1.57')]
        public readonly ?string $avgBusy1,
    ) {
    }
}
