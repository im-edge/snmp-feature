<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

use IMEdge\SnmpFeature\SnmpScenario\Event\DbUpdateEvent;
use IMEdge\SnmpFeature\SnmpScenario\Event\EventDispatcher;
use IMEdge\SnmpFeature\SnmpScenario\Event\PerfDataEvent;

class InterfaceTrafficCountersPoller
{
    protected const COUNTER_SET = 'if_traffic';
    protected const STATUS_TABLE = 'network_interface_status';
    protected const KILO_BIT = 8 * 1024; // TODO: Check unit
    protected const FAST_INTERVAL = 15;
    protected const SLOW_INTERVAL = 60;
    protected const AGGREGATION_INTERVAL = 600;
    protected EventDispatcher $events;
    protected array $agg = [];
    protected string $agentKey;
    protected PropertySet $set;

    public function __construct(public readonly SnmpTarget $target)
    {
        // TODO: estimated varbind size
        $this->set = new PropertySet(optionalTables: [
            'ifInOctets'        => '.1.3.6.1.2.1.2.2.1.10',
            'ifOutOctets'       => '.1.3.6.1.2.1.2.2.1.16',
            'ifOutQLen'         => '.1.3.6.1.2.1.2.2.1.21',
            'ifHCInOctets'      => '.1.3.6.1.2.1.31.1.1.1.6',
            'ifHCOutOctets'     => '.1.3.6.1.2.1.31.1.1.1.10',
        ]);
        $this->agentKey = $this->target->identifier;
    }

    protected function splitInstances($result): array
    {
        var_dump($result);
        return [];
    }

    public function processResult($result): void
    {
        foreach ($this->splitInstances($result) as $instance => $instanceResult) {
            $this->processInstanceResult($instance, $instanceResult);
        }
    }

    public function processInstanceResult($instance, $result): void
    {
        // aggs per instance!
        if ($this->agg[$instance] === null) {
            $this->agg[$instance] = new AggregatedCounter(self::AGGREGATION_INTERVAL, $result);
        } else {
            if ($update = $this->agg[$instance]->update($result)) {
                $this->emitInstanceUpdate($instance, $update[0], $update[1]);
            }
        }

        $now = time();
        $this->events->emit(PerfDataEvent::NAME, [
            new PerfDataEvent($this->agentKey, self::COUNTER_SET, $instance, $now, $result)
        ]);
    }

    protected function emitInstanceUpdate($instance, $timestamp, $counters): void
    {
        $in = $counters['ifHCInOctets'] ?? $counters['ifInOctets'] ?? null;
        if ($in !== null) {
            $in = floor($in / self::KILO_BIT / self::AGGREGATION_INTERVAL);
        }
        $out = $counters['ifHCOutOctets'] ?? $counters['ifOutOctets'] ?? null;
        if ($out !== null) {
            $out = floor($out / self::KILO_BIT / self::AGGREGATION_INTERVAL);
        }
        $this->events->emit(DbUpdateEvent::NAME, [new DbUpdateEvent(
            $this->agentKey,
            (int) $instance,
            self::STATUS_TABLE,
            [
                'ts_usage'         => $timestamp * 1000,
                'current_kbit_in'  => $in,
                'current_kbit_out' => $out,
            ]
        )]);
    }
}
