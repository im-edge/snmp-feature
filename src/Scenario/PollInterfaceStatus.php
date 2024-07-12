<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\InterfaceStatusDuplex;
use IMEdge\SnmpFeature\DataStructure\InterfaceStatusOperational;
use IMEdge\SnmpFeature\DataStructure\InterfaceStatusStp;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use IMEdge\SnmpFeature\DataStructure\TruthValue;
use IMEdge\SnmpFeature\SnmpScenario\Event\BootEvent;
use Ramsey\Uuid\UuidInterface;

#[SnmpTable([new SnmpTableIndex('ifIndex', new Oid('1.3.6.1.2.1.2.2.1.1'))])]
#[PollingTask(name: 'interfaceStatus', defaultInterval: 60)]
#[DbTable('snmp_interface_status', [
    'system_uuid' => 'systemUuid',
    'if_index'    => 'ifIndex'
])]
#[EventSubscription(BootEvent::NAME)]
class PollInterfaceStatus
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('system_uuid')]
        public readonly UuidInterface $systemUuid,
        //
        #[SnmpTableIndexValue('ifIndex')]
        #[DbColumn('if_index')]
        public readonly int $ifIndex,
        //
        #[DbColumn('status_operational')]
        public readonly ?InterfaceStatusOperational $statusOperational = null,
        //
        // #[Oid('1.3.6.1.2.1.2.2.1.9')]
        // public readonly ?DateTime $ifLastChange; // TODO: reliable time-tick diff
        //
        #[DbColumn('status_duplex')]
        public readonly ?InterfaceStatusDuplex $statusDuplex = null,
        //
        #[DbColumn('status_stp')]
        public readonly ?InterfaceStatusStp $statusStp = null,
        //
        #[Oid('1.3.6.1.2.1.17.2.15.1.5')] // stp_port_path_cost
        // TODO: Implement a fallback mechanism, don't read one in case the other one is here
        // #[Oid('1.3.6.1.2.1.17.2.15.1.11')] // stp_port_path_cost32
        public readonly ?int $stpPortPathCost = null,
        //
        // stp_designated_root ???
        //
        #[Oid('1.3.6.1.2.1.17.2.15.1.8')]
        #[DbColumn('stp_designated_bridge')]
        public readonly ?string $stpDesignatedBridge = null,
        //
        #[Oid('1.3.6.1.2.1.17.2.15.1.9')]
        #[DbColumn('stp_designated_port')]
        public readonly ?string $stpDesignatedPort = null,
        //
        #[Oid('1.3.6.1.2.1.17.2.15.1.10')]
        #[DbColumn('stp_forward_transitions')]
        public readonly ?string $stpForwardTransitions = null,
        //
        /**
         * Promiscuous mode.
         *
         * - false(2) if this interface only accepts packets/frames that are addressed to this station
         */
        #[Oid('1.3.6.1.2.1.31.1.1.1.16')]
        #[DbColumn('promiscuous_mode')]
        public readonly ?TruthValue $promiscuousMode = null,
        //
        /**
         * Connector present
         *
         * - true(1) if the interface sublayer has a physical connector
         * - false(2) otherwise
         */
        #[Oid('1.3.6.1.2.1.31.1.1.1.17')]
        #[DbColumn('connector_present')]
        public readonly ?TruthValue $connectorPresent = null,
        //
        // TODO: ifCounterDiscontinuityTime is interesting
    ) {
    }
}

/**
TODO:
protected function onSuccess(): void
{
    if ('status_oper_went_down') {
        $this->events->emit(IfDownEvent::NAME);
    }
    if ('status_oper_went_up') {
        $this->events->emit(IfUpEvent::NAME);
    }
}
*/
