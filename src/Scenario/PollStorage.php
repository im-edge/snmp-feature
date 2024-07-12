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

#[SnmpTable([new SnmpTableIndex('hrStorageIndex', new Oid('1.3.6.1.2.1.25.2.3.1.1'))])]
#[PollingTask(name: 'storage', defaultInterval: 600)]
#[DbTable('device_storage', [
    'device_uuid'   => 'deviceUuid',
    'storage_index' => 'hrStorageIndex'
])]
class PollStorage
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('hrStorageIndex')]
        #[DbColumn('storage_index')]
        public readonly int $hrStorageIndex,

        /**
         * hrStorageType
         *
         * The type of storage represented by this entry
         *
         * This is an OID, see HOST-RESOURCES-TYPES:
         *
         * hrStorageOther          1.3.6.1.2.1.25.2.1.1   when no other defined type is appropriate
         * hrStorageRam            1.3.6.1.2.1.25.2.1.2   RAM
         * hrStorageVirtualMemory  1.3.6.1.2.1.25.2.1.3   virtual memory, temporary storage of swapped or paged memory
         * hrStorageFixedDisk      1.3.6.1.2.1.25.2.1.4   non-removable rigid rotating magnetic storage devices
         * hrStorageRemovableDisk  1.3.6.1.2.1.25.2.1.5   removable rigid rotating magnetic storage devices
         * hrStorageFloppyDisk     1.3.6.1.2.1.25.2.1.6   non-rigid rotating magnetic storage devices
         * hrStorageCompactDisc    1.3.6.1.2.1.25.2.1.7   read-only rotating optical storage devices
         * hrStorageRamDisk        1.3.6.1.2.1.25.2.1.8   a file system that is stored in RAM
         * hrStorageFlashMemory    1.3.6.1.2.1.25.2.1.9   flash memory
         * hrStorageNetworkDisk    1.3.6.1.2.1.25.2.1.10  networked file system
         */
        #[Oid('1.3.6.1.2.1.25.2.3.1.2')]
        #[DbColumn('storage_type')]
        public readonly ?Oid $storageType,

        /**
         * hrStorageDescr
         *
         * A description of the type and instance of the storage described by this entry.
         */
        #[Oid('1.3.6.1.2.1.25.2.3.1.3')]
        #[DbColumn('description')]
        public readonly ?string $description = null,

        /**
         * hrStorageAllocationUnits
         *
         * The size, in bytes, of the data objects allocated from this pool.  If this entry is monitoring sectors,
         * blocks, buffers, or packets, for example, this number will commonly be greater than one. Otherwise, this
         * number will typically be one.
         */
        #[Oid('1.3.6.1.2.1.25.2.3.1.4')]
        #[DbColumn('storage_allocation_units')]
        public readonly ?int $storageAllocationUnits = null,

        /**
         * hrStorageSize
         *
         * The size of the storage represented by this entry, in
         * units of hrStorageAllocationUnits. This object is
         * writable to allow remote configuration of the size of
         * the storage area in those cases where such an
         * operation makes sense and is possible on the
         * underlying system. For example, the amount of main
         * memory allocated to a buffer pool might be modified or
         * the amount of disk space allocated to virtual memory
         * might be modified.
         */
        #[Oid('1.3.6.1.2.1.25.2.3.1.5')]
        #[DbColumn('storage_size')]
        public readonly ?int $storageSize = null,

        /**
         * hrStorageUsed
         *
         * The amount of the storage represented by this entry
         * that is allocated, in units of hrStorageAllocationUnits.
         */
        #[Oid('1.3.6.1.2.1.25.2.3.1.6')]
        #[DbColumn('storage_used')]
        public readonly ?int $storageUsed = null,

        /**
         * hrStorageAllocationFailures
         *
         * The number of requests for storage represented by
         * this entry that could not be honored due to not enough
         * storage.  It should be noted that as this object has a
         * SYNTAX of Counter32, that it does not have a defined
         * initial value.  However, it is recommended that this
         * object be initialized to zero, even though management
         * stations must not depend on such an initialization.
         */
        #[Oid('1.3.6.1.2.1.25.2.3.1.7')]
        #[DbColumn('storage_allocation_failures')]
        public readonly ?int $storageAllocationFailures = null,
    ) {
    }
}
