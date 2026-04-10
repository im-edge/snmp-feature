<?php

namespace IMEdge\SnmpFeature\Polling\Worker\Scheduler;

use Amp\Redis\RedisClient;
use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ConsistencyHelper;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use IMEdge\SnmpFeature\Polling\Worker\SnmpScenarioPoller;
use IMEdge\SnmpFeature\Redis\ImedgeRedis;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use IMEdge\SnmpFeature\SnmpScenario\TargetState;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
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
    /** @var array<string, SlotTracker> */
    protected array $scenarioSlotTrackers = [];

    protected bool $hasChanges = false;
    protected int $slotCount = 0;
    protected ?string $timer = null;
    protected ?string $slotTicker = null;
    protected RedisClient $redis;
    protected int $bootTimeMs;
    /** @var array<string, array<int, string>> */
    protected array $slotTargets = [];

    public function __construct(
        protected LoggerInterface $logger
    ) {
        $this->targets = new SnmpTargets();
        $this->initializeSlotTicker();
        $this->redis = ImedgeRedis::client('snmp/scenarioScheduler');
        $this->timer = EventLoop::repeat(0.3, $this->emitOnChanges(...));
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
            if (! empty($scenarioTargets)) {
                $scenarios[$scenarioKey] = $this->scenarios[$scenarioKey];
            }
        }

        return $scenarios;
    }

    public function getScenario(string $id): ScenarioDefinition
    {
        return $this->scenarios[$id] ?? throw new InvalidArgumentException("Scheduler has no such scenario: $id");
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
        $this->bootTimeMs = (int) floor(microtime(true) * 1000 - hrtime(true) / 1_000_000);
        $slotCount = count($this->targets->targets) > 1_000 ? 200 : 20;
        if ($slotCount !== $this->slotCount) {
            $this->slotCount = $slotCount;
            $this->initializeTickTimer();
        }
    }

    protected function initializeTickTimer(): void
    {
        if ($this->slotTicker !== null) {
            EventLoop::cancel($this->slotTicker);
        }
        // tick more often than necessary
        $this->slotTicker = EventLoop::repeat(1 / ($this->slotCount * 1.2), $this->tickNextSlots(...));
    }

    protected function tickNextSlots(): void
    {
        $allSlots = [];
        $now = hrtime(true);
        foreach ($this->getAllUsedScenarios() as $scenario) {
            $id = $scenario->uuid->toString();
            $slot = $this->scenarioSlotTrackers[$id]->advance($now);
            if ($slot !== null) {
                if ($targetString = $this->slotTargets[$id][$slot] ?? null) {
                    $allSlots[$id] = $targetString;
                }
            }
        }

        if (! empty($allSlots)) {
            $this->emit(self::ON_SLOTS, [$allSlots]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function calculateSlots(): array
    {
        $slots = [];
        foreach ($this->scenarios as $scenario) {
            $slots[$scenario->uuid->toString()] = SnmpTargetSlots::calculate(
                $this->targets,
                $scenario,
                $this->slotCount
            );
        }

        return $slots;
    }

    protected function emitOnChanges(): void
    {
        if ($this->hasChanges) {
            $this->hasChanges = false;
            // $this->logger->notice('ScenarioScheduler notifies changes');
            $this->slotTargets = $this->calculateSlots();
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
        $allowed = [ // TODO: caps
            'interfaceTraffic',
            'interfaceError',
            'interfacePacket',
            'interfaceStatus',
            'sysInfo',
            'entity',
            'entityIfMap',
            'interfaceConfig',
            'sensors',
        ];
        if (! in_array($scenario->name, $allowed)) {
            return;
        }
        $id = $scenario->uuid->toString();
        $this->scenarios[$id] = $scenario;
        $this->scenarioSlotTrackers[$id] = new SlotTracker(
            $this->slotCount,
            $scenario->interval,
            $this->bootTimeMs,
            ConsistencyHelper::uuidToNumber($scenario->uuid) % min(110, $scenario->interval - 2),
        );
        $this->scenariosByName[$scenario->name] = $scenario;
        $this->recheckScenario($scenario);
    }

    public function removeScenario(ScenarioDefinition $scenario): void
    {
        $id = $scenario->uuid->toString();
        unset($this->scenarios[$id]);
        unset($this->scenarioSlotTrackers[$id]);
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
        $this->targets->add($target);
        $this->recheckTarget($target);
    }

    public function removeTarget(SnmpTarget $target): void
    {
        $targetKey = (string) $target->address;
        $this->removeTargetFromAllScenarios($targetKey);
    }

    protected function recheckScenario(ScenarioDefinition $scenario): void
    {
        $this->scenarioTargets[$scenario->uuid->toString()] = [];
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
            // $this->logger->debug(sprintf("Target %s wants scenario %s", $target->address, $scenario->name));
            $scenarioKey = $scenario->uuid->toString();
            if (!$this->hasChanges && !isset($this->scenarioTargets[$scenarioKey][$targetKey])) {
                $this->hasChanges = true;
            }
            $this->scenarioTargets[$scenarioKey][$targetKey] = $target; // it's a reference, should be fine
        } else {
            // $this->logger->debug(sprintf("Target %s does not want scenario %s", $target->address, $scenario->name));
        }
    }

    protected function removeTargetFromAllScenarios(string $targetKey): void
    {
        foreach ($this->scenarioTargets as $key => &$scenarioTargets) { // key is scenario uuid
            if ($this->scenarios[$key]->name === 'sysInfo') {
                continue;
            }
            // $this->logger->notice("Removing $targetKey from $key");
            if (!$this->hasChanges && isset($scenarioTargets[$targetKey])) {
                $this->hasChanges = true;
            }
            unset($scenarioTargets[$targetKey]);
        }
    }

    public function __destruct()
    {
        if ($this->timer) {
            EventLoop::cancel($this->timer);
            $this->timer = null;
        }
        if ($this->slotTicker) {
            EventLoop::cancel($this->slotTicker);
            $this->slotTicker = null;
        }
    }
}
