<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\SnmpPacket\VarBindValue\OctetString;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use InvalidArgumentException;

#[Attribute]
class OctetStringToIp extends SimpleSnmpDataMangler implements DataManglerInterface
{
    public const SHORT_NAME = 'octetStringToIp';

    public function transform(mixed $string): mixed
    {
        if ($string === null || $string === '') {
            return null;
        }

        // return $string;
        return inet_ntop($string);
    }

    public function transformVarBindValue(VarBindValue $value): ?VarBindValue
    {
        if ($value instanceof OctetString) {
            return new OctetString(inet_ntop($value->value));
        }

        throw new InvalidArgumentException("OctetString expected, got " . get_class($value));
    }
}
