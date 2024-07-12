<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use IMEdge\SnmpFeature\DataStructure\TruthValue;
use Ramsey\Uuid\UuidInterface;

#[SnmpTable([new SnmpTableIndex('fsIndex', new Oid('1.3.6.1.2.1.25.3.8.1.1'))])] // hrFSIndex
#[PollingTask(name: 'filesystems', defaultInterval: 600)]
#[DbTable('device_filesystem', [
    'device_uuid' => 'deviceUuid',
    'fs_index'    => 'fsIndex'
])]
class PollFilesystems
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('fsIndex')]
        #[DbColumn('fs_index')]
        public readonly int $fsIndex,

        /**
         * The path name of the root of this file system.
         *
         * Syntax: InternationalDisplayString, Size (range): min=0, max=128
         * Hint: max length is in octets, not number of symbols
         */
        #[Oid('1.3.6.1.2.1.25.3.8.1.2')]
        #[DbColumn('mount_point')]
        public readonly ?string $mountPoint,

        /**
         * The value of this object identifies the type of this
         * file system.
         *
         * AutonomousType -> this is an OID
         *
         * HOST-RESOURCES-TYPES::hrFSTypes -> 1.3.6.1.2.1.25.3.9.1 = other
         */
        #[Oid('1.3.6.1.2.1.25.3.8.1.4')]
        #[DbColumn('fs_type')]
        public readonly ?string $fsType,

        /**
         * An indication if this file system is logically
         * configured by the operating system to be readable and
         * writable or only readable.  This does not represent
         * any local access-control policy, except one that is
         * applied to the file system as a whole.
         *
         * readWrite (1)
         * readOnly (2)
         */
        #[Oid('1.3.6.1.2.1.25.3.8.1.5')]
        #[DbColumn('fs_access')]
        public readonly ?int $access,

        /**
         * A flag indicating whether this file system is bootable.
         */
        #[Oid('1.3.6.1.2.1.25.3.8.1.6')]
        #[DbColumn('fs_access')]
        public readonly ?TruthValue $bootable,

        /**
         * The index of the hrStorageEntry that represents
         * information about this file system.  If there is no
         * such information available, then this value shall be
         * zero.  The relevant storage entry will be useful in
         * tracking the percent usage of this file system and
         * diagnosing errors that may occur when it runs out of
         * space.
         */
        #[Oid('1.3.6.1.2.1.25.3.8.1.7')]
        #[DbColumn('storage_index')]
        public readonly ?int $storageIndex,
    ) {
    }
}
