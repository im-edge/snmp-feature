<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\SnmpPacket\VarBindValue\Integer32;
use IMEdge\SnmpPacket\VarBindValue\ObjectIdentifier;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use RuntimeException;

#[Attribute]
class LastOidOctetToInteger32 extends SimpleSnmpDataMangler
{
    public const SHORT_NAME = 'lastOidOctetToInteger32';

    public function transformVarBindValue(VarBindValue $value): ?VarBindValue
    {
        if (!$value instanceof ObjectIdentifier) {
            throw new RuntimeException(
                'Expected instance of ObjectIdentifier in LastOidOctetToInteger32, got ' . get_class($value)
            );
        }
        if (preg_match('/\.(\d+)$/', $value->getReadableValue(), $m)) {
            return new Integer32((int) $m[1]);
        }

        throw new RuntimeException('Could not determine last numeric octet of ' . $value->getReadableValue());
    }
}
