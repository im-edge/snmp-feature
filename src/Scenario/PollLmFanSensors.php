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

#[PollingTask(name: 'lmFanSensors', defaultInterval: 300)]
#[SnmpTable([new SnmpTableIndex('lmFanSensorsEntry', new Oid('1.3.6.1.4.1.2021.13.16.3.1.1'))])]
#[DbTable(tableName: 'snmp_lm_fan_sensor', keyProperties: [
    'system_uuid'  => 'systemUuid',
    'sensor_index' => 'sensorIndex',
])]
class PollLmFanSensors
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('system_uuid')]
        public readonly UuidInterface $systemUuid,

        #[SnmpTableIndexValue('lmFanSensorsEntry')]
        #[DbColumn('sensor_index')]
        public readonly int $sensorIndex,

        #[Oid('1.3.6.1.4.1.2021.13.16.3.1.2')]
        #[DbColumn('device')]
        public readonly string $device,

        // The rotation speed of the fan in RPM
        #[Oid('1.3.6.1.4.1.2021.13.16.3.1.3')]
        #[DbColumn('value')]
        public readonly string $value,
    ) {
    }
}
