<?php

namespace IMEdge\SnmpFeature\Polling\Worker\ResultHandler;

use Closure;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\SnmpFeature\DataStructure\DataNodeIdentifier;
use IMEdge\SnmpFeature\DataStructure\DeviceIdentifier;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndexValue;
use IMEdge\SnmpFeature\DataStructure\SpecialValueRegistry;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioPropertyDefinition;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;
use IMEdge\SnmpPacket\Message\VarBindList;
use IMEdge\SnmpPacket\VarBindValue\OctetString;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

/**
 * Applies transformations and special values
 *
 * Should be instantiated only once per scenario and property to keep our memory footprint small
 */
class ScenarioPropertyProcessor
{
    /** @var Closure<VarBindValue>|null */
    protected ?Closure $value = null;
    protected ?ProcessedScenarioProperties $currentProperties = null;
    protected ?SnmpTarget $currentTarget = null;
    protected ?VarBindList $currentVarBinds = null;

    public function __construct(
        public readonly ScenarioPropertyDefinition $definition,
        public readonly ScenarioDefinition $scenario,
        protected NodeIdentifier $nodeIdentifier,
        protected LoggerInterface $logger,
    ) {
        $this->initialize();
    }

    public function process(VarBindList $varBinds, SnmpTarget $target, ProcessedScenarioProperties $processed): void
    {
        // set current properties, to avoid passing them around
        $this->currentProperties = $processed;
        $this->currentTarget = $target;
        $this->currentVarBinds = $varBinds;
        $hasValue = false;

        if ($this->value) {
            // Apply a special value, if any
            $value = ($this->value)();
            $hasValue = true;
        } else {
            // Otherwise pick a value by oid from the given result
            if ($this->definition->oid) {
                $value = $varBinds->getOptionalValueForOid($this->definition->oid);
                $hasValue = true;
            } else {
                $value = null;
            }
        }

        $name = $this->definition->name;
        // Apply configured manglers
        foreach ($this->definition->manglers as $mangler) {
            if ($value === null) {
                break;
            }

            $value = $mangler->transformVarBindValue($value);
        }

        // persist the value for the current row, might be referenced by other properties
        $processed->setValue($name, $value);
        // $this->logger->notice(sprintf('Setting %s to %s', $this->definition->name, var_export($value, true)));
        $processed->setPhpValue($name, TypeConverter::createNativePhpType($value, $this->definition));

        if ($this->definition->dbColumn) {
            if ($hasValue) {
                $processed->setDbValue($this->definition->dbColumn, $processed->getPhpValue($name));
            }
        }
        if ($value) {
            if ($metric = $this->definition->metric?->createMetric($name, $value)) {
                $processed->setMetric($name, $metric);
            }
        }
    }

    protected function initialize(): void
    {
        if ($value = $this->definition->value) {
            [$className, $arguments] = $value;
            $className = SpecialValueRegistry::getClass($className);
            $instance = new $className(...$arguments);

            switch ($className) {
                case DeviceIdentifier::class:
                    $this->value = fn() => new OctetString(
                        Uuid::fromString($this->currentTarget->identifier)->getBytes()
                    );
                    break;
                case SnmpTableIndexValue::class:
                    foreach ($this->scenario->snmpTableIndexes->indexes ?? [] as $index) {
                        if ($index->name === $instance->indexName) {
                            $this->value = fn() => $this->currentVarBinds->getOptionalValueForOid($index->oid->oid);
                        }
                    }
                    break;
                case DataNodeIdentifier::class:
                    $this->value = fn() => new OctetString($this->nodeIdentifier->uuid->getBytes());
                    break;
            }
        }
    }
}
