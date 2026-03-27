<?php

namespace IMEdge\SnmpFeature\Polling\Worker\ResultHandler;

use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Json\JsonString;
use IMEdge\RedisTables\RedisTables;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\DbTableDefinition;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioDefinition;
use IMEdge\SnmpFeature\Redis\ImedgeRedis;
use IMEdge\SnmpFeature\Scenario\SnmpTableHelper;
use IMEdge\SnmpFeature\SnmpResponse;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTarget;
use IMEdge\SnmpPacket\Message\VarBind;
use IMEdge\SnmpPacket\Message\VarBindList;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;

/**
 * Result processing
 *
 * There should be only instance per scenario, to keep our memory footprint small
 */
class ScenarioResultProcessor
{
    /** @var ScenarioPropertyProcessor[] */
    protected array $propertyProcessors = [];
    protected RedisTables $redisTables;
    protected ?MetricWriter $metricWriter = null;
    protected ?array $dbColumnMap = null;

    public function __construct(
        protected ScenarioDefinition $scenario,
        protected NodeIdentifier $nodeIdentifier,
        protected LoggerInterface $logger,
    ) {
        $this->redisTables = ImedgeRedis::tables($this->nodeIdentifier, $this->logger, 'snmp/scenarioResultProcessor');

        foreach ($this->scenario->properties as $property) {
            if ($property->dbColumn) {
                $this->dbColumnMap ??= [];
                $this->dbColumnMap[$property->dbColumn] = $property->name;
            }
            $this->propertyProcessors[] = new ScenarioPropertyProcessor(
                $property,
                $this->scenario,
                $this->nodeIdentifier,
                $this->logger,
            );
        }
    }

    public function setMetricWriter(?MetricWriter $writer): void
    {
        $this->metricWriter = $writer;
    }

    public function processResponse(SnmpResponse $response, SnmpTarget $target): void
    {
        if ($response->success) {
            if ($this->scenario->requestType === 'get') { // TODO: enum / restrict!!
                $this->processSuccessSimple($response, $target);
            } else {
                $this->processSuccessTable($response, $target);
            }
        } else {
            $this->processFailure($response, $target);
        }
    }

    protected function processSuccessSimple(SnmpResponse $response, SnmpTarget $target): void
    {
        // response->result: {
        //   "errorStatus":0,
        //   "errorIndex":0,
        //   "varBinds": [
        //       {"oid":"1.3.6.1.2.1.1.5.0","value":{"type":"octet_string","value":"CS1-FRA"}},
        //       {"oid":"1.3.6.1.2.1.1.1.0","value":{"type":"octet_string","value":"HP J8693A Switch 3500yl-48G..."}},
        //       {"oid":"1.3.6.1.2.1.1.6.0","value":{"type":"octet_string","value":"NX4 Networks | Frankfurt"}},
        //       {"oid":"1.3.6.1.2.1.1.4.0","value":{"type":"octet_string","value":"noc@nx4-networks.de"}},
        //       {"oid":"1.3.6.1.2.1.1.7.0","value":{"type":"integer32","value":74}},
        //       {"oid":"1.3.6.1.2.1.1.2.0","value":{"type":"oid","value":"1.3.6.1.4.1.11.2.3.7.11.59"}},
        //       {"oid":"1.3.6.1.6.3.10.2.1.1.0","value":{"type":"octet_string","value":"0x0000000b00000016b90d6840"}},
        //       {"oid":"1.3.6.1.6.3.10.2.1.2.0","value":{"type":"integer32","value":68}},
        //       {"oid":"1.3.6.1.6.3.10.2.1.4.0","value":{"type":"integer32","value":1472}},
        //       {"oid":"1.3.6.1.2.1.25.1.1.0","value":{"type":"context_specific","value":0}},
        //       {"oid":"1.3.6.1.2.1.17.1.1.0","value":{"type":"octet_string","value":"0x0016b90d6840"}}
        //   ],
        //   "requestId":248445891
        // }
        $values = new ProcessedScenarioProperties();
        $varBinds = VarBindList::fromSerialization($response->result->varBinds);
        foreach ($this->propertyProcessors as $propertyProcessor) {
            $propertyProcessor->process($varBinds, $target, $values);
            if ($this->wantsMetrics()) {
                $measurement = $this->scenario->measurement?->createMeasurement(
                    $target,
                    $values
                );
                if ($measurement) {
                    $this->metricWriter?->shipMeasurements([$measurement]);
                }
            }
        }
        if ($dbTablesRow = $this->scenario->dbTable?->prepareRedisTableRow($values)) {
            $this->logger->notice('ROW: ' . var_export($dbTablesRow, 1));
            try {
                $result = $this->redisTables->setTableEntry(...$dbTablesRow);
            } catch (\Exception $e) {
                $this->logger->error('Updating Redis table failed: ' . $e->getMessage());
            }
        }
        // $this->logger->notice(JsonString::encode($values->properties));
        // mb_substitute_character('long'); // Hex format
        // $this->logger->notice(mb_convert_encoding(var_export($values->phpValues, true), 'latin1', 'utf8'));
    }

    protected function processSuccessTable(SnmpResponse $response, SnmpTarget $target): void
    {
        // response->result: CombinedResult (SnmpEngine)
        // return;
        $rows = [];
        $dbRows = [];
        $measurements = [];
        $result = SnmpTableHelper::flipTableResult($response->result->repeaters);
        foreach ($result as $instanceKey => $varBinds) {
            $values = new ProcessedScenarioProperties();
            if ($this->scenario->snmpTableIndexes) {
                SnmpTableHelper::appendTableIndexesToVarBindList(
                    $instanceKey,
                    $varBinds,
                    $this->scenario->snmpTableIndexes
                );
            }
            foreach ($this->propertyProcessors as $propertyProcessor) {
                $propertyProcessor->process($varBinds, $target, $values);
            }
            $rows[$instanceKey] = $values;
            if ($this->wantsMetrics()) {
                $measurement = $this->scenario->measurement?->createMeasurement(
                    $target,
                    $values
                );
                if ($measurement) {
                    $measurements[] = $measurement;
                }
            }
            if ($this->scenario->dbTable && $values->hasDbValues()) {
                $dbRows[$this->scenario->dbTable->getDbUpdateKey($values)] = $values->getDbValues();
            }
        }
        $this->metricWriter?->shipMeasurements($measurements);
        if (!empty($dbRows)) {
            try {
                $result = $this->redisTables->setTableForDevice(
                    $this->scenario->dbTable->tableName,
                    $target->identifier,
                    $this->scenario->dbTable->keyProperties,
                    $dbRows
                );
            } catch (\Exception $e) {
                $this->logger->error('Setting Redis table failed: ' . $e->getMessage());
            }
        }
    }

    protected function processFailure(SnmpResponse $response, SnmpTarget $target): void
    {
        if ($this->isHealthCheck()) {
            $this->logger->error(sprintf(
                'Will set health to fail for %s (%s)%s',
                $target->identifier,
                $target->address->ip,
                $response->errorMessage ? ': ' . $response->errorMessage : ''
            ));
        }
    }

    protected function wantsMetrics(): bool
    {
        return null !== $this->metricWriter && null !== $this->scenario->measurement;
    }

    protected function isHealthCheck(): bool
    {
        return $this->scenario->name === 'sysInfo';
    }
}
