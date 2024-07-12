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
#[SnmpTable([new SnmpTableIndex('swIndex', new Oid('1.3.6.1.2.1.25.3.8.1.1'))])] // hrSWInstalledIndex
#[PollingTask(name: 'softwareInstalled', defaultInterval: 900)]
#[DbTable('device_software_installed', [
    'device_uuid' => 'deviceUuid',
    'sw_index' => 'swIndex'
])]
class PollSoftwareInstalled
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
        #[SnmpTableIndexValue('swIndex')]
        #[DbColumn('sw_index')]
        public readonly int $swIndex,

        /**
         * A textual description of this installed piece of
         * software, including the manufacturer, revision, the
         * name by which it is commonly known, and optionally,
         * its serial number.
         */
        #[Oid('1.3.6.1.2.1.25.6.3.1.2')] // hrSWInstalledName
        #[DbColumn('name')]
        public readonly ?string $name,

        /**
         * The type of this software.
         *
         * unknown (1)
         * operatingSystem (2)
         * deviceDriver (3)
         * application (4)
         */
        #[Oid('1.3.6.1.2.1.25.6.3.1.4')] // hrSWInstalledType
        #[DbColumn('software_type')]
        public readonly ?int $type, // TODO: enum
    ) {
    }
}
