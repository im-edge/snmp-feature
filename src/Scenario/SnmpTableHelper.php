<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\Snmp\DataType\DataType;
use IMEdge\Snmp\DataType\DataTypeContextSpecific;
use IMEdge\Snmp\DataType\ObjectIdentifier;
use IMEdge\SnmpFeature\DataStructure\SnmpTableIndex;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SnmpTableHelper
{
    /**
     * @param SnmpTableIndex[] $indexes
     */
    public static function flattenResult(LoggerInterface $logger, array $indexes, array $result, array $keys): array
    {
        $final = [];
        foreach ($result as $table => $results) {
            /** @var DataType $value */
            foreach ($results as $oid => $value) {
                $combinedIndex = '';
                $row = [];

                $matchedRequest = null;
                foreach ($keys as $requestedOid => $requestedProperty) {
                    if (str_starts_with($oid, $requestedOid . '.')) {
                        $matchedRequest = $requestedOid;
                        $oidSuffix = substr($oid, strlen($requestedOid) + 1);
                    }
                }
                if ($matchedRequest === null) {
                    // $value->getTag()-> DataTypeContextSpecific::NO_SUCH_OBJECT, ::NO_SUCH_INSTANCE, ::END_OF_MIB_VIEW
                    if (!($value instanceof DataTypeContextSpecific)) {
                        $logger->debug(sprintf(
                            'Unexpected OID in result for %s: %s (%s)',
                            $table,
                            $oid,
                            $value->getReadableValue()
                        ));
                    }
                    continue;
                }

                foreach ($indexes as $index) {
                    if ($index->implicit) {
                        $idxValue = self::stripFromOid($oidSuffix, $index->length);
                    } else {
                        $length = self::stripFromOid($oidSuffix, 1);
                        if (! ctype_digit($length)) {
                            throw new RuntimeException("Got invalid implicit index length: '$length'");
                        }
                        $idxValue = self::stripFromOid($oidSuffix, intval($length));
                    }

                    if ($combinedIndex !== '') {
                        $combinedIndex .= '.';
                    }
                    $combinedIndex .= $idxValue;
                    $row[$index->name] = ObjectIdentifier::fromString($idxValue);
                }
                foreach ($row as $k => $v) {
                    $final[$combinedIndex][$k] ??= $v; // TODO: Object with type
                }

                $final[$combinedIndex][$keys[$matchedRequest]] = $value;
            }
        }

        return $final;
    }

    protected static function stripFromOid(string &$oid, int $length): string
    {
        $pos = 0;
        for ($i = 0; $i < $length; $i++) {
            $pos = strpos($oid, '.', $pos + 1);
        }
        if ($pos === false) {
            $prefix = $oid;
            $oid = '';
            return $prefix;
        }
        $prefix = substr($oid, 0, $pos);
        $oid = substr($oid, $pos + 1);

        return $prefix;
    }
}
