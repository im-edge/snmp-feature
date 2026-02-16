<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\SnmpPacket\VarBindValue\Integer32;
use IMEdge\SnmpPacket\VarBindValue\ObjectIdentifier;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use InvalidArgumentException;

/**
 * @deprecated Seems not to be in use
 */
#[Attribute]
class ExtractIfIndexFromOid extends SimpleSnmpDataMangler implements DataManglerInterface
{
    public const SHORT_NAME = 'extractIfIndexFromOid';

    protected const IF_INDEX_PREFIX = '1.3.6.1.2.1.2.2.1.1.';
    protected const IF_INDEX_LENGTH = 20;

    public function transform(mixed $string): mixed
    {
        if (null !== $string && str_starts_with($string, self::IF_INDEX_PREFIX)) {
            return (int) substr($string, self::IF_INDEX_LENGTH);
        }

        return $string;
    }

    public function transformVarBindValue(VarBindValue $value): ?VarBindValue
    {
        if (!$value instanceof ObjectIdentifier) {
            throw new InvalidArgumentException(
                'Expected instance of ObjectIdentifier in LastOidOctetToInteger32, got ' . get_class($value)
            );
        }
        return new Integer32($this->transform($value->value));
    }
}
