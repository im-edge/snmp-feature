<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\Snmp\DataType\DataType;
use IMEdge\Snmp\DataType\ObjectIdentifier;
use IMEdge\Snmp\DataType\OctetString;
use RuntimeException;

#[Attribute]
class OidToOctetString implements SnmpDataTypeManglerInterface
{
    public function transform(DataType $value): ?DataType
    {
        if (!$value instanceof ObjectIdentifier) {
            throw new RuntimeException(
                'Expected instance of ObjectIdentifier in LastOidOctetToInteger32, got ' . get_class($value)
            );
        }
        $result = '';
        foreach (explode('.', $value->getReadableValue()) as $chr) {
            if (! ctype_digit($chr)) {
                throw new RuntimeException('OID-based Octet string expected, got ' . $value->getReadableValue());
            }
            $result .= chr(intval($chr));
        }

        return OctetString::fromString($result);
    }
}
