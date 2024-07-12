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
 * Software installed on this host
 */
#[SnmpTable([new SnmpTableIndex('hrSWRunIndex', new Oid('1.3.6.1.2.1.25.4.2.1.1'))])]
#[PollingTask(name: 'processList', defaultInterval: 300)]
#[DbTable('device_process_list', [
    'device_uuid' => 'deviceUuid',
    'ps_index'    => 'psIndex',
])]
class PollProcessList
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        /**
         * A unique value for each piece of software installed
         * on the host.  This value shall be in the range from 1
         * to the number of pieces of software installed on the
         * host.
         */
        #[SnmpTableIndexValue('hrSWRunIndex')]
        #[DbColumn('ps_index')]
        public readonly int $psIndex,

        /**
         * A textual description of this running piece of
         * software, including the manufacturer, revision,  and
         * the name by which it is commonly known.  If this
         * software was installed locally, this should be the
         * same string as used in the corresponding
         */
        #[Oid('1.3.6.1.2.1.25.4.2.1.2')] // hrSWRunName
        #[DbColumn('name')]
        public readonly ?string $name,

        /**
         * A description of the location on long-term storage
         * (e.g. a disk drive) from which this software was
         * loaded.
         */
        #[Oid('1.3.6.1.2.1.25.4.2.1.4')] // hrSWRunPath
        #[DbColumn('path')]
        public readonly ?string $path,

        /**
         * A description of the parameters supplied to this
         * software when it was initially loaded.
         */
        #[Oid('1.3.6.1.2.1.25.4.2.1.5')] // hrSWRunParameters
        #[DbColumn('parameters')]
        public readonly ?string $parameters,

        /**
         * The status of this running piece of software.
         * Setting this value to invalid(4) shall cause this
         * software to stop running and to be unloaded. Sets to
         * other values are not valid.
         *
         * running (1)
         * runnable (2)
         * notRunnable (3)
         * invalid (4)
         *
         * TODO: enum
         */
        #[Oid('1.3.6.1.2.1.25.4.2.1.7')] // hrSWRunStatus
        #[DbColumn('status')]
        public readonly ?int $status,

        /**
         * The number of centi-seconds of the total system's CPU
         * resources consumed by this process.  Note that on a
         * multi-processor system, this value may increment by
         * more than one centi-second in one centi-second of real
         * (wall clock) time.
         */
        #[Oid('1.3.6.1.2.1.25.5.1.1.1')] // hrSWRunPerfCPU
        #[DbColumn('perf_cpu')]
        public readonly ?int $perfCpu,

        /**
         * The total amount of real system memory allocated to
         * this process.
         */
        #[Oid('1.3.6.1.2.1.25.5.1.1.2')] // hrSWRunPerfMem
        #[DbColumn('perf_mem')]
        public readonly ?int $perfMem,
    ) {
    }
}
