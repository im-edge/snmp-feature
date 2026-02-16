<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataMangler\MangleToUtf8;
use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\EntityPhysicalClass;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use IMEdge\SnmpFeature\DataStructure\TruthValue;
use Ramsey\Uuid\UuidInterface;

#[PollingTask('entity', 900)]
#[DbTable('inventory_physical_entity', [
    'device_uuid'  => 'deviceUuid',
    'entity_index' => 'entityIndex'
])]
#[SnmpTable([new SnmpTableIndex('entPhysicalIndex', new Oid('1.3.6.1.2.1.47.1.1.1.1.1'))])]
class PollEntity
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('entPhysicalIndex')]
        #[DbColumn('entity_index')]
        public readonly int $entityIndex,

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.2')]
        #[DbColumn('description')]
        public readonly ?string $description = null, // entPhysicalDescr

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.4')]
        #[DbColumn('parent_index')]
        public readonly ?string $parentIndex = null, // entPhysicalContainedIn

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.5')]
        #[DbColumn('class')]
        public readonly ?EntityPhysicalClass $class = null, // entPhysicalClass

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.6')]
        #[DbColumn('relative_position')]
        public readonly ?string $relativePosition = null, // entPhysicalParentRelPos

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.7')]
        #[DbColumn('name')]
        public readonly ?string $name = null, // entPhysicalName

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.16')]
        #[DbColumn('field_replaceable_unit')]
        public readonly ?TruthValue $fieldReplaceableUnit = null, // entPhysicalIsFRU

        // Cisco: Version identifier—Version Identifier (VID) is the version of the PID. The VID indicates the number of
        // times a product has versioned in ways that are reported to a customer. For example, the product identifier
        // NM-1FE-TX may have a VID of V04. VID is limited to three alphanumeric characters and must be stored in the
        // entPhysicalHardwareRev object.
        #[Oid('1.3.6.1.2.1.47.1.1.1.1.8')]
        #[DbColumn('revision_hardware')]
        public readonly ?string $revisionHardware = null, // entPhysicalHardwareRev

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.9')]
        #[DbColumn('revision_firmware')]
        public readonly ?string $revisionFirmware = null, // entPhysicalFirmwareRev

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.10')]
        #[DbColumn('revision_software')]
        public readonly ?string $revisionSoftware = null, // entPhysicalSoftwareRev

        // Cisco: Serial number—Serial number (SN) is the 11-character identifier used to identify a specific part
        // within a product and must be stored in the entPhysicalSerialNum object. Serial number content is defined by
        // manufacturing part number 7018060-0000.
        //
        // Serial number format is defined in four fields:
        // – Location (L)
        // – Year (Y)
        // – Workweek (W)
        // – Sequential serial ID (S)
        //
        // The SN label is represented as: LLLYYWWSSS.
        #[Oid('1.3.6.1.2.1.47.1.1.1.1.11')]
        #[DbColumn('serial_number')]
        public readonly ?string $serialNumber = null, // entPhysicalSerialNum

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.12')]
        #[DbColumn('manufacturer_name')]
        #[MangleToUtf8]
        public readonly ?string $manufacturerName = null, // entPhysicalMfgName

        // Cisco: Order-able product identifier—Product Identifier (PID) is the alphanumeric identifier used by
        // customers to order Cisco products. Two examples include NM-1FE-TX and CISCO3745. PID is limited to
        // 18 characters and must be stored in the entPhysicalModelName object.
        #[Oid('1.3.6.1.2.1.47.1.1.1.1.13')]
        #[DbColumn('model_name')]
        #[MangleToUtf8]
        public readonly ?string $modelName = null, // entPhysicalModelName

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.14')]
        #[DbColumn('alias')]
        public readonly ?string $alias = null, // entPhysicalAlias

        #[Oid('1.3.6.1.2.1.47.1.1.1.1.15')]
        #[DbColumn('asset_id')]
        public readonly ?string $assetId = null, // entPhysicalAssetID

        /** TODO: manufacturing_date:
         'manufacturing_date'     => '17', //entPhysicalMfgDate
         field octets contents range
         ----- ------ -------- -----
         1 1-2 year 0..65536
         2 3 month 1..12
         3 4 day 1..31
         4 5 hour 0..23
         5 6 minutes 0..59
         6 7 seconds 0..60
         (use 60 for leap-second)
         7 8 deci-seconds 0..9
         8 9 direction from UTC '+' / '-'
         9 10 hours from UTC 0..11
         10 11 minutes from UTC 0..59
         For example, Tuesday May 26, 1992 at 1:30:15 PM EDT would be
         displayed as:
         1992-5-26,13:30:15.0,-4:0
         Note that if only local time is known, then timezone
         information (fields 8-10) is not present."
         */
    ) {
    }
}
