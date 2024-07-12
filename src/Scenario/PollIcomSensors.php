<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\IcomEntitySensorDataScale;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

#[PollingTask('icomSensors', 300)]
#[DbTable('inventory_physical_entity_sensor', [
    'device_uuid'  => 'deviceUuid',
    'entity_index' => 'entityIndex'
])]
#[SnmpTable([new SnmpTableIndex('entPhysicalIndex', new Oid('1.3.6.1.2.1.47.1.1.1.1.1'))])]
// #[Confine([
//    new HasOidConfinement('1.3.6.1.4.1.1807.30.1.1'),
// ])]
// #[ScenarioPriority(1)]
class PollIcomSensors
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('entPhysicalIndex')]
        #[DbColumn('entity_index')]
        public readonly int $entityIndex,

        #[Oid('1.3.6.1.4.1.1807.30.1.1.1.1')] // entPhySensorType
        #[DbColumn('sensor_type')]
        public readonly ?string $sensorType = null,

        // TODO: Check, whether we can override the OID for given ENUM
        #[DbColumn('sensor_scale')]
        public readonly ?IcomEntitySensorDataScale $sensorScale = null,

        #[Oid('1.3.6.1.4.1.1807.30.1.1.1.3')] // entPhySensorPrecision
        #[DbColumn('sensor_precision')]
        public readonly ?int $sensorPrecision = null,

        #[Oid('1.3.6.1.4.1.1807.30.1.1.1.4')] // entPhySensorValue
        #[DbColumn('sensor_value')]
        public readonly ?int $sensorValue = null,

        #[Oid('1.3.6.1.4.1.1807.30.1.1.1.5')] // entPhySensorOperStatus
        #[DbColumn('sensor_status')]
        public readonly ?int $sensorStatus = null,

        // Does not exist
        public readonly ?string $sensorUnitsDisplay = null,

        // '1.3.6.1.4.1.1807.30.1.1.1.8' => icomEntSensorFailureStatus (Power supply status)
        // '1.3.6.1.4.1.1807.30.1.1.1.6' => icomEntSensorValueTimeStamp
        // '1.3.6.1.4.1.1807.30.1.1.1.7' => icomEntSensorValueUpdateRate
    ) {
    }
}
