<?php

namespace IMEdge\SnmpFeature\Polling\Worker\Scheduler;

use Amp\Redis\RedisClient;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use IMEdge\SnmpFeature\Polling\Worker\SnmpScenarioPoller;
use IMEdge\SnmpFeature\Redis\ImedgeRedis;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use IMEdge\SnmpFeature\SnmpScenario\TargetState;
use Revolt\EventLoop;
use RuntimeException;

class SnmpScenarioScheduler implements EventEmitterInterface
{
    use EventEmitterTrait;

    public const ON_CHANGES = 'change';
    public const ON_SLOTS = 'slots';

    protected SnmpTargets $targets;
    /** @var array<string, ScenarioDefinition> Scenarios by UUID */
    protected array $scenarios = [];
    /** @var array<string, ScenarioDefinition> */
    protected array $scenariosByName = [];

    /** @var array<string, array<string, SnmpTarget>> */
    protected array $scenarioTargets = [];

    protected bool $hasChanges = false;
    protected int $slotCount = 0;
    protected ?string $slotTicker = null;
    protected RedisClient $redis;

    public function __construct()
    {
        $this->targets = new SnmpTargets();
        $this->initializeSlotTicker();
        $this->redis = ImedgeRedis::client('snmp/scenarioScheduler');
        EventLoop::repeat(1, $this->emitOnChanges(...)); // lab only
    }

    /**
     * We only need descriptions for active/used scenarios
     *
     * @return ScenarioDefinition[]
     */
    public function getAllUsedScenarios(): array
    {
        $scenarios = [];
        foreach ($this->scenarioTargets as $scenarioKey => $scenarioTargets) {
            $scenarios[$scenarioKey] = $this->scenarios[$scenarioKey];
        }

        return $scenarios;
    }

    public function triggerScenario(ScenarioDefinition $scenario, SnmpTarget $target): void
    {
        $this->redis->publish(
            SnmpScenarioPoller::STREAM_NAME_TASKS,
            $scenario->uuid->toString() . ':' . $target->identifier
        );
    }

    public function requireTarget(string $targetIdentifier): SnmpTarget
    {
        return $this->targets->targets[$targetIdentifier]
            ?? throw new RuntimeException("SNMP target '$targetIdentifier' not found");
    }

    public function requireScenarioByName(string $scenarioName): ScenarioDefinition
    {
        return $this->scenariosByName[$scenarioName]
            ?? throw new RuntimeException("Scenario '$scenarioName' not found");
    }

    protected function initializeSlotTicker(): void
    {
        $slotCount = count($this->targets->targets) > 1_000 ? 200 : 20;
        if ($slotCount !== $this->slotCount) {
            $this->slotCount = $slotCount;
            $this->initializeTickTimer();
        }
    }

    protected function getSingleSlotDuration(ScenarioDefinition $scenario): int
    {
        return $scenario->interval / $this->slotCount;
    }

    protected function initializeTickTimer(): void
    {
        if ($this->slotTicker !== null) {
            EventLoop::cancel($this->slotTicker);
        }
        // $this->slotTicker = EventLoop::repeat(0.2, $this->tickNextSlots(...));
    }

    protected function tickNextSlots(): void
    {
        // TODO
    }

    protected function tickAllScenarioSlots(int $tsStart, int $duration): void
    {
        $allSlots = [];
        foreach ($this->scenarios as $key => $scenario) {
            $slots = $this->getScenarioSlotsForPeriod($scenario, $tsStart, $duration);
            if (! empty($slots)) {
                $allSlots[$key] = $slots;
            }
        }

        if (! empty($allSlots)) {
            $this->emit(self::ON_SLOTS, [$allSlots]);
        }
    }

    /**
     * @return int[]
     */
    protected function getScenarioSlotsForPeriod(ScenarioDefinition $scenario, int $tsStart, int $duration): array
    {
        return TimeSlotCalculator::getSlots(
            $this->slotCount,
            $scenario->interval,
            $tsStart,
            $duration,
            $scenario->getOffset()
        );
    }

    public function getSchedule()
    {
        // jedes scenario hat eine Anzahl Slots und Targets pro slot
        // abhängig von der Dauer des Szenarios ist die "duration pro slot" unterschiedlich
        // die Einordnung target -> Slot ändert sich nicht, kann nach Redis geschrieben werden
        // Daemon -> sagt "gib mir Tasks VON - BIS"
        //   Dadurch passiert via LUA ein PUBLISH der scenario/target-Paare an den (später die) Worker.
        //   So staut sich nichts auf. Worker nicht da: passiert halt nichts
        // Preisfrage: wie berechne ich die Elemente für Zeitraum x-y?
        // now % scenarioInterval = relNow
        // Wir sagen "gib mir alles von - bis"
        // wollen aber nichts 2x senden. Darum wollen wir nur die Slots, die in dem Zeitraum BEGINNEN.
        // Jedes Szenario sollte ggf einen Offset haben, der auch immer gleich bleibt. UUID gekürzt als Zahl
        //   Offset = ConsistencyHelper::uuidToNumber(scenarioUuid) % scenarioInterval
        // formerSlot = relNow
    }

    protected function emitOnChanges(): void
    {
        if ($this->hasChanges) {
            $this->hasChanges = false;
            $this->emit(self::ON_CHANGES);
        }
    }

    public function recheckAll(): void
    {
        $this->scenarioTargets = [];
        foreach ($this->targets as $target) {
            $this->recheckTarget($target);
        }
    }

    public function addScenario(ScenarioDefinition $scenario): void
    {
        $this->scenarios[$scenario->uuid->toString()] = $scenario;
        $this->scenariosByName[$scenario->name] = $scenario;
        $this->recheckScenario($scenario);
    }

    public function removeScenario(ScenarioDefinition $scenario): void
    {
        unset($this->scenarios[$scenario->uuid->toString()]);
        unset($this->scenariosByName[$scenario->name]);
        unset($this->scenarioTargets[$scenario->uuid->toString()]);
        $this->hasChanges = true;
    }

    public function setTargets(SnmpTargets $targets): void
    {
        $removed = $this->targets->listRemovedTargets($targets);
        $added = $targets->listRemovedTargets($this->targets);
        $this->targets = $targets;
        foreach ($removed as $target) {
            $this->removeTarget($target);
        }
        foreach ($added as $target) {
            $this->addTarget($target);
        }
    }

    public function addTarget(SnmpTarget $target): void
    {
        $this->recheckTarget($target);
    }

    public function removeTarget(SnmpTarget $target): void
    {
        $targetKey = (string) $target->address;
        $this->removeTargetFromAllScenarios($targetKey);
    }

    protected function recheckScenario(ScenarioDefinition $scenario): void
    {
        $this->scenarioTargets[$scenario->name] = [];
        foreach ($this->targets->targets as $target) {
            $this->checkScenarioTarget($scenario, $target);
        }
    }

    protected function recheckTarget(SnmpTarget $target): void
    {
        if ($target->state !== TargetState::REACHABLE) {
            // TODO: do not skip keep-alive scenario(s)
            $this->removeTargetFromAllScenarios((string) $target->address);
            return;
        }

        foreach ($this->scenarios as $scenario) {
            $this->checkScenarioTarget($scenario, $target);
        }
    }

    protected function checkScenarioTarget(ScenarioDefinition $scenario, SnmpTarget $target): void
    {
        $targetKey = (string) $target->address;
        if ($target->wants($scenario)) {
            if (!$this->hasChanges && !isset($this->scenarioTargets[$scenario->name][$targetKey])) {
                $this->hasChanges = true;
            }
            $this->scenarioTargets[$scenario->name][$targetKey] = $target; // it's a reference, should be fine
        }
    }

    protected function removeTargetFromAllScenarios(string $targetKey): void
    {
        foreach ($this->scenarioTargets as &$scenarioTargets) { // key is scenarioName
            if (!$this->hasChanges && isset($scenarioTargets[$targetKey])) {
                $this->hasChanges = true;
            }
            unset($scenarioTargets[$targetKey]);
        }
    }
}
