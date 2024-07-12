<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\EntitySensorDataScale;
use IMEdge\SnmpFeature\DataStructure\Measurement;
use IMEdge\SnmpFeature\DataStructure\Metric;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

#[PollingTask('ciscoSensors', 300)]
#[DbTable('inventory_physical_entity_sensor', [
    'device_uuid'  => 'deviceUuid',
    'entity_index' => 'entityIndex'
])]
#[Measurement('entity_sensor', 'entityIndex')]
#[SnmpTable([new SnmpTableIndex('entPhysicalIndex', new Oid('1.3.6.1.2.1.47.1.1.1.1.1'))])]
class PollCiscoSensors
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('entPhysicalIndex')]
        #[DbColumn('entity_index')]
        public readonly int $entityIndex,

        #[Oid('1.3.6.1.2.1.99.1.1.1.1')] // entPhySensorType
        #[DbColumn('sensor_type')]
        public readonly ?string $sensorType,

        #[DbColumn('sensor_scale')]
        public readonly ?EntitySensorDataScale $sensorScale,

        #[Oid('1.3.6.1.2.1.99.1.1.1.3')] // entPhySensorPrecision
        #[DbColumn('sensor_precision')]
        public readonly ?int $sensorPrecision,

        #[Oid('1.3.6.1.2.1.99.1.1.1.4')] // entPhySensorValue
        #[DbColumn('sensor_value')]
        #[Metric('value')]
        public readonly ?int $sensorValue,

        #[Oid('1.3.6.1.2.1.99.1.1.1.5')] // entPhySensorOperStatus
        #[DbColumn('sensor_status')]
        public readonly ?int $sensorStatus,

        #[Oid('1.3.6.1.2.1.99.1.1.1.6')] // entPhySensorUnitsDisplay
        #[DbColumn('sensor_units_display')]
        public readonly ?string $sensorUnitsDisplay,

        // '1.3.6.1.2.1.99.1.1.1.7' => 'entPhySensorValueTimeStamp',
        // '1.3.6.1.2.1.99.1.1.1.8' => 'entPhySensorValueUpdateRate',
    ) {
    }
}
