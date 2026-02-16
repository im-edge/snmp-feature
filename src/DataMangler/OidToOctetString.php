<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\SnmpPacket\VarBindValue\ObjectIdentifier;
use IMEdge\SnmpPacket\VarBindValue\OctetString;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use RuntimeException;

#[Attribute]
class OidToOctetString extends SimpleSnmpDataMangler
{
    public const SHORT_NAME = 'oidToOctetString';

    public function transformVarBindValue(VarBindValue $value): ?VarBindValue
    {
        if (!$value instanceof ObjectIdentifier) {
            throw new RuntimeException(
                'Expected instance of ObjectIdentifier in OidToOctetString, got ' . get_class($value)
            );
        }
        $result = '';
        foreach (explode('.', $value->getReadableValue()) as $chr) {
            if (! ctype_digit($chr)) {
                throw new RuntimeException('OID-based Octet string expected, got ' . $value->getReadableValue());
            }
            $result .= chr(intval($chr));
        }

        return new OctetString($result);
    }
}
