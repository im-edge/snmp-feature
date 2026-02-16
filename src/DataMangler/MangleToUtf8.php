<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\SnmpPacket\VarBindValue\OctetString;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use InvalidArgumentException;

use function mb_check_encoding;
use function mb_convert_encoding;

#[Attribute]
class MangleToUtf8 extends SimpleSnmpDataMangler implements DataManglerInterface
{
    public const SHORT_NAME = 'toUtf8';

    public function transform(mixed $string): mixed
    {
        if (null !== $string && ! mb_check_encoding($string, 'UTF-8')) {
            return mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string));
        }

        return $string;
    }

    public function transformVarBindValue(VarBindValue $value): ?VarBindValue
    {
        if ($value instanceof OctetString) {
            return new OctetString($this->transform($value->value));
        }

        throw new InvalidArgumentException("OctetString expected, got " . get_class($value));
    }
}
