<?php

namespace IMEdge\SnmpFeature\NextGen;

use IMEdge\SnmpFeature\Scenario\PollEntity;
use IMEdge\SnmpFeature\Scenario\PollEntityIfMap;
use IMEdge\SnmpFeature\Scenario\PollInterfaceConfig;
use IMEdge\SnmpFeature\Scenario\PollInterfacePackets;
use IMEdge\SnmpFeature\Scenario\PollInterfaceStatus;
use IMEdge\SnmpFeature\Scenario\PollInterfaceTraffic;
use IMEdge\SnmpFeature\Scenario\PollSensors;
use IMEdge\SnmpFeature\Scenario\PollSysInfo;

class PeriodicScenarioRegistry
{
    /**
     * @return class-string[]
     */
    public function listScenarios(): array
    {
        return [
            PollSysInfo::class,
            PollEntity::class,
            PollSensors::class,
            PollEntityIfMap::class,
            PollInterfaceConfig::class,
            PollInterfaceStatus::class,
            PollInterfaceTraffic::class,
            PollInterfacePackets::class,
            //PollIcomBsTsStatus::class,
            //PollIcomBsTsConfig::class,
            //PollIcomSensors::class,
        ];
    }
}
