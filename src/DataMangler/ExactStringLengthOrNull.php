<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\SnmpPacket\VarBindValue\NullValue;
use IMEdge\SnmpPacket\VarBindValue\OctetString;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use InvalidArgumentException;

#[Attribute]
class ExactStringLengthOrNull extends SimpleSnmpDataMangler implements DataManglerInterface
{
    public const SHORT_NAME = 'exactStringLengthOrNull';

    public function __construct(protected int $stringLength)
    {
    }

    public function transform(mixed $string): ?string
    {
        if (!is_string($string)) {
            return null;
        }

        if (strlen($string) === $this->stringLength) {
            return $string;
        }

        if (str_starts_with($string, '0x') && strlen(hex2bin(substr($string, 2))) === $this->stringLength) {
            return $string;
        }

        return null;
    }

    public function transformVarBindValue(VarBindValue $value): ?VarBindValue
    {
        if ($value instanceof OctetString) {
            $result = $this->transform($value->value);
            if (is_string($result)) {
                return new OctetString($result);
            }

            return new NullValue();
        }

        throw new InvalidArgumentException("OctetString expected, got " . get_class($value));
    }

    #[\Override]
    protected function serializeSettings(): array
    {
        return  [$this->stringLength];
    }
}
