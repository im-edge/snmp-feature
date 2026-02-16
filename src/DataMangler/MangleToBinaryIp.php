<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\SnmpPacket\VarBindValue\OctetString;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use InvalidArgumentException;

#[Attribute]
class MangleToBinaryIp extends SimpleSnmpDataMangler implements DataManglerInterface
{
    public const SHORT_NAME = 'toBinaryIp';

    public function transform(mixed $string): ?string
    {
        if ($string === null) {
            return null;
        }
        $ip = inet_pton($string);

        if ($ip === false) {
            throw new InvalidArgumentException("$string is not a valid IP address");
        }

        return $ip;
    }

    public function transformVarBindValue(VarBindValue $value): ?VarBindValue
    {
        if ($value instanceof OctetString) {
            return new OctetString($this->transform($value->value));
        }

        throw new InvalidArgumentException("OctetString expected, got " . get_class($value));
    }
}
