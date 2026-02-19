<?php

namespace IMEdge\SnmpFeature\Polling\ScenarioDefinition;

use IMEdge\Metrics\MetricDatatype;
use IMEdge\SnmpFeature\DataMangler\DataManglerRegistry;
use IMEdge\SnmpFeature\DataMangler\SnmpDataTypeManglerInterface;
use IMEdge\SnmpFeature\DataStructure\DbColumn;
use IMEdge\SnmpFeature\DataStructure\DbTable;
use IMEdge\SnmpFeature\DataStructure\Measurement;
use IMEdge\SnmpFeature\DataStructure\Metric;
use IMEdge\SnmpFeature\DataStructure\Oid;
use IMEdge\SnmpFeature\DataStructure\SnmpTable;
use IMEdge\SnmpFeature\DataStructure\SpecialValueInterface;
use IMEdge\SnmpFeature\DataStructure\SpecialValueRegistry;
use IMEdge\SnmpFeature\DataStructure\TruthValue;
use IMEdge\SnmpFeature\Scenario\PollingTask;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * Has been implemented in order to convert legacy code-based Scenario Definitions
 */
class ScenarioReflection
{
    public static function scenario(string $scenarioClass): ScenarioDefinition
    {
        $reflection = new ReflectionClass($scenarioClass);
        $name = null;

        foreach ($reflection->getAttributes(PollingTask::class) as $attribute) {
            /** @var PollingTask $task */
            $task = $attribute->newInstance();
            $name = $task->name;
            $interval = $task->defaultInterval ?: 600;
        }
        if ($interval === null) {
            throw new RuntimeException("Scenario $name has no interval");
        }
        if ($name === null) {
            throw new RuntimeException('ScenarioReflection expects a PollingTask, got ' . $scenarioClass);
        }
        if ($dbTable = ReflectionHelper::getAttributeInstanceByInterface($reflection, DbTable::class)) {
            $dbTable = new DbTableDefinition($dbTable->tableName, array_keys($dbTable->keyProperties));
        }
        if ($snmpTableIndexes = ReflectionHelper::getAttributeInstanceByInterface($reflection, SnmpTable::class)) {
            $snmpTableIndexes = new SnmpTableIndexes($snmpTableIndexes->indexes);
        }
        if ($measurement = ReflectionHelper::getAttributeInstanceByInterface($reflection, Measurement::class)) {
            $measurement = new MeasurementDefinition($measurement->name, $measurement->instanceProperty);
        }

        $needsWalk = !empty($reflection->getAttributes(SnmpTable::class));
        $requestType = $needsWalk ? 'walk' : 'get';
        $properties = self::getProps($reflection);

        $ns = Uuid::fromString('6926f443-42cd-48db-b5d7-83cb29a540b2'); // Random, internal

        return new ScenarioDefinition(
            uuid: Uuid::uuid5($ns, $name),
            name: $name,
            interval: $interval,
            requestType: $requestType,
            properties: $properties,
            snmpTableIndexes: $snmpTableIndexes,
            dbTable: $dbTable,
            measurement: $measurement
        );
    }

    /**
     * @deprecated -> ScenarioDefinition is able to tell this
     */
    protected static function getScenarioOids(ReflectionClass $class): array
    {
        $result = [];
        foreach ($class->getProperties() as $property) {
            $hasOid = false;
            foreach ($property->getAttributes(Oid::class) as $oid) {
                $args = $oid->getArguments();
                $result[$args['oid'] ?? $args[0]] = $property->getName();
                $hasOid = true;
            }
            if (! $hasOid) {
                $typeClass = $property->getType()->getName();
                if (class_exists($typeClass)) {
                    $typeRef = new ReflectionClass($typeClass);
                    foreach ($typeRef->getAttributes(Oid::class) as $oid) {
                        $args = $oid->getArguments();
                        $result[$args['oid'] ?? $args[0]] = $property->getName();
                    };
                }
            }
        }

        return $result;
    }

    protected static function getEarlyManglers(ReflectionProperty $property): array
    {
        return ReflectionHelper::getAttributesByInterface($property, SnmpDataTypeManglerInterface::class);
    }

    protected static function getDefaultValue(ReflectionProperty $property): ?array
    {
        $values = ReflectionHelper::getAttributesByInterface($property, SpecialValueInterface::class);
        if (count($values) === 0) {
            return null;
        }

        if (count($values) === 1) {
            return $values[0];
        }

        throw new RuntimeException('ScenarioReflection: there can be only one default value');
    }

    protected static function typeMap(string $type): ScenarioPropertyType
    {
        $map = [
            Oid::class => 'oid',
            UuidInterface::class => 'uuid',
            TruthValue::class => 'bool', // enum
        ];

        if (isset($map[$type])) {
            return ScenarioPropertyType::from($map[$type]);
        }

        if (enum_exists($type)) {
            return ScenarioPropertyType::TYPE_ENUM;
        }

        return ScenarioPropertyType::from($type);
    }

    private static function optionalEnumValues(string $getName): ?array
    {
        if (self::typeMap($getName) === ScenarioPropertyType::TYPE_ENUM) {
            if (enum_exists($getName)) {
                if (is_a($getName, \BackedEnum::class, true)) {
                    $list = [];
                    foreach ($getName::cases() as $case) {
                        $list[$case->value] = $case->getLabel($case);
                    }

                    return $list;
                }
            }

            throw new RuntimeException("Unable to get cases for $getName");
        }

        return null;
    }

    /**
     * @return ScenarioPropertyDefinition[]
     */
    protected static function getProps(ReflectionClass $class): array
    {
        $properties = [];
        foreach ($class->getProperties() as $property) {
            $propertyName = $property->getName();
            $type = $property->getType();
            $current = [
                'name'     => $propertyName,
                'type'     => self::typeMap($type->getName())->value,
                'enum'     => self::optionalEnumValues($type->getName()),
                'nullable' => $type->allowsNull(),
            ];

            $manglers = ReflectionHelper::getAttributesByInterface($property, SnmpDataTypeManglerInterface::class);
            foreach ($manglers as &$mangler) {
                $mangler[0] = DataManglerRegistry::getShortName($mangler[0]);
            }
            if (! empty($manglers)) {
                $current['manglers'] = $manglers;
            }
            if ($default = ReflectionHelper::getAttributeByInterface($property, SpecialValueInterface::class)) {
                $current['value'] = [SpecialValueRegistry::getShortName($default[0]), $default[1]];
            }
            if ($column = ReflectionHelper::getAttributeByInterface($property, DbColumn::class)) {
                $current['dbColumn'] = $column[1][0]; // [0] is DbColumn::class, [1] are parameters
            }
            if ($oid = ReflectionHelper::getAttributeByInterface($property, Oid::class)) {
                $current['oid'] = $oid[1][0] ?? $oid[1]['oid'];
            } else {
                // e.g. ?IMEdge\SnmpFeature\DataStructure\InterfaceStatusConfigured
                $typeClass = ltrim($property->getType(), '?');
                if (class_exists($typeClass)) {
                    $typeRef = new ReflectionClass($typeClass);

                    if ($oid = ReflectionHelper::getAttributeByInterface($typeRef, Oid::class)) {
                        $current['oid'] = $oid[1][0] ?? $oid[1]['oid'];
                    }
                }
            }
            if ($metric = ReflectionHelper::getAttributeByInterface($property, Metric::class)) {
                $args = $metric[1];
                $current['metric'] = [
                    $args['dataSource'] ?? $args[0],
                    ($args['dataType'] ?? $args[1] ?? MetricDatatype::GAUGE)->value,
                    $args['unit'] ?? $args[2] ?? null,
                ];
            }

            // Not indexed by name, order is important
            $properties[] = ScenarioPropertyDefinition::fromSerialization((object) $current);
        }

        return $properties;
    }
}
