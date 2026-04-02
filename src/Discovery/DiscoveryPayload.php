<?php

namespace IMEdge\SnmpFeature\Discovery;

class DiscoveryPayload
{
    public const OID_LIST = [
        '1.3.6.1.2.1.1.5.0' => 'sysName',
        '1.3.6.1.2.1.1.6.0' => 'sysLocation',
        '1.3.6.1.2.1.1.1.0' => 'sysDescr',
        '1.3.6.1.2.1.1.4.0' => 'sysContact',
        '1.3.6.1.2.1.1.7.0' => 'sysServices',
        '1.3.6.1.2.1.1.2.0' => 'sysObjectID',
        // TODO: Timetick handling. 100stel-Sekunden, 32bit: läuft nach 497 Tagen über
        '1.3.6.1.2.1.1.3.0' => 'sysUpTime',

        // --- SNMP-FRAMEWORK-MIB ---
        '1.3.6.1.6.3.10.2.1.1.0' => 'systemEngineId', // snmpEngineId?
        '1.3.6.1.6.3.10.2.1.2.0' => 'snmpEngineBoots',
        // '1.3.6.1.6.3.10.2.1.3.0' => 'snmpEngineTime // Use instead of sysUptime, if available? Fallback?
        '1.3.6.1.6.3.10.2.1.4.0' => 'snmpEngineMaxMessageSize',
        // --- HOST-RESOURCES-MIB ---
        // '1.3.6.1.2.1.25.1.1.0'  => 'hrSystemUptime', // Use instead of sysUptime, if available? Fallback?
    ];
}
