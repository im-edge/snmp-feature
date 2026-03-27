<?php

namespace IMEdge\SnmpFeature\Scenario;

use IMEdge\SnmpFeature\Polling\ScenarioDefinition\SnmpTableIndexes;
use IMEdge\SnmpPacket\Message\VarBind;
use IMEdge\SnmpPacket\Message\VarBindList;
use IMEdge\SnmpPacket\VarBindValue\ObjectIdentifier;
use RuntimeException;

class SnmpTableHelper
{
    public static function flipTableResult($result): array
    {
        $flipped = [];
        foreach ((array) $result as $propertyName => $rows) {
            // Ignoring propertyName, as we'll look it up again
            foreach ($rows as $relativeOid => $row) {
                $flipped[$relativeOid] ??= new VarBindList();
                if ($row instanceof Varbind) {
                    $requestedOid = preg_replace('/' . preg_quote('.' . $relativeOid, '/') . '$/', '', $row->oid);
                    $flipped[$relativeOid]->varBinds[] = new VarBind($requestedOid, $row->value);
                } else {
                    // result has full OID, we want to see the requested one
                    $requestedOid = preg_replace('/' . preg_quote('.' . $relativeOid, '/') . '$/', '', $row[0]);
                    $row[0] = $requestedOid;
                    $flipped[$relativeOid]->varBinds[] = VarBind::fromSerialization($row);
                }
            }
        }

        return $flipped;
    }

    public static function appendTableIndexesToVarBindList(
        string $oidSuffix,
        VarBindList $varBinds,
        SnmpTableIndexes $tableIndexes
    ): void {
        $combinedIndex = '';
        $extracted = [];
        foreach ($tableIndexes->indexes as $index) {
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
            $extracted[] = [$index->oid->oid, new ObjectIdentifier($idxValue)];
        }

        foreach ($extracted as $idx) {
            $varBinds->varBinds[] = new VarBind($idx[0], $idx[1]); // OID from Index
        }
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
