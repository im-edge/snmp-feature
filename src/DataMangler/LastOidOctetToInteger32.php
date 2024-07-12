<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\Snmp\DataType\DataType;
use IMEdge\Snmp\DataType\Integer32;
use IMEdge\Snmp\DataType\ObjectIdentifier;
use RuntimeException;

#[Attribute]
class LastOidOctetToInteger32 implements SnmpDataTypeManglerInterface
{
    public function transform(DataType $value): ?DataType
    {
        if (!$value instanceof ObjectIdentifier) {
            throw new RuntimeException(
                'Expected instance of ObjectIdentifier in LastOidOctetToInteger32, got ' . get_class($value)
            );
        }
        if (preg_match('/\.(\d+)$/', $value->getReadableValue(), $m)) {
            return Integer32::fromInteger((int) $m[1]);
        }

        throw new RuntimeException('Could not determine last numeric octet of ' . $value->getReadableValue());
    }
}
