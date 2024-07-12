<?php

namespace IMEdge\SnmpFeature\Scenario;

class ScenarioRegistry
{
    public const CLASSES = [
        PollCdpConfig::class,
        PollCdpCache::class,
        PollEntity::class,
        PollEntityLogical::class,
        PollEntityIfMap::class,
        PollInterfaceConfig::class,
        PollInterfaceErrors::class,
        PollInterfacePackets::class,
        PollInterfaceStack::class,
        PollInterfaceStatus::class,
        PollInterfaceTraffic::class,

        PollIpAddressTable::class,

        PollBgp4Peers::class,

        PollFilesystems::class,
        PollStorage::class,
        PollProcessList::class,
        // PollIpAddrTable::class,
        PollSensors::class,
        PollSoftwareInstalled::class,
        PollSysInfo::class,

        PollLmTempSensors::class,
        PollLmVoltSensors::class,
        PollLmFanSensors::class,

        PollIcomBsTsConfig::class,
        PollIcomBsTsStatus::class,
        PollIcomSensors::class,
    ];
}
