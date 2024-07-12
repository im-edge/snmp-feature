<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\RowStatus;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use Ramsey\Uuid\UuidInterface;

#[SnmpTable([
    new SnmpTableIndex('ifStackHigherLayer', new Oid('1.3.6.1.2.1.31.1.2.1.1')),
    new SnmpTableIndex('ifStackLowerLayer', new Oid('1.3.6.1.2.1.31.1.2.1.2')),
])]
#[PollingTask(name: 'interfaceStack', defaultInterval: 60)]
#[DbTable('network_interface_stack', [
    'device_uuid'     => 'deviceUuid',
    'higher_if_index' => 'higherIfIndex',
    'lower_if_index'  => 'lowerIfIndex'
])]
class PollInterfaceStack
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        #[SnmpTableIndexValue('ifStackHigherLayer')]
        #[DbColumn('higher_if_index')]
        public readonly int $higherIfIndex,

        #[SnmpTableIndexValue('ifStackLowerLayer')]
        #[DbColumn('lower_if_index')]
        public readonly int $lowerIfIndex,

        #[DbColumn('if_stack_status')]
        #[Oid('1.3.6.1.2.1.31.1.2.1.3')]
        public readonly RowStatus $ifStackStatus,
    ) {
    }
}
