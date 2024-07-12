<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\TruthValue;
use Ramsey\Uuid\UuidInterface;

/**
 * TODO: cdpInterfaceEntry for per-interface config. Not necessary, but might be helpful
 */
#[PollingTask(name: 'cdpConfig', defaultInterval: 300)]
#[DbTable(tableName: 'network_cdp_config', keyProperties: ['device_uuid'])]
class PollCdpConfig
{
    public function __construct(
        #[DeviceIdentifier]
        #[DbColumn('device_uuid')]
        public readonly UuidInterface $deviceUuid,

        /**
         * An indication of whether the Cisco Discovery Protocol
         * is currently running.  Entries in cdpCacheTable are
         * deleted when CDP is disabled.
         */
        #[Oid('1.3.6.1.4.1.9.9.23.1.3.1.0')]
        #[DbColumn('cdp_global_run')]
        public readonly ?TruthValue $cdpGlobalRun,

        /**
         * The interval at which CDP messages are to be generated.
         * The default value is 60 seconds.
         */
        #[Oid('1.3.6.1.4.1.9.9.23.1.3.2.0')]
        #[DbColumn('cdp_global_message_interval')]
        public readonly ?int $cdpMessageInterval,

        /**
         * The time for the receiving device holds CDP message.
         * The default value is 180 seconds.
         */
        #[Oid('1.3.6.1.4.1.9.9.23.1.3.3.0')]
        #[DbColumn('cdp_global_hold_time')]
        public readonly ?int $cdpGlobalHoldTime,

        /**
         * The device ID advertised by this device. The format of this
         * device id is characterized by the value of
         * cdpGlobalDeviceIdFormat object.
         */
        #[Oid('1.3.6.1.4.1.9.9.23.1.3.4.0')]
        #[DbColumn('cdp_global_device_id')]
        public readonly ?string $cdpGlobalDeviceId,

        /**
         * Indicates the time when the cache table was last changed. It
         * is the most recent time at which any row was last created,
         * modified or deleted.
         *
         * TODO: Timestamp
         */
        #[Oid('1.3.6.1.4.1.9.9.23.1.3.5.0')]
        #[DbColumn('cdp_global_last_change')]
        public readonly ?string $cdpGlobalLastChange,
    ) {
    }
}
