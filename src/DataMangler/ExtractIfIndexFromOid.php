<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;

#[Attribute]
class ExtractIfIndexFromOid implements DataManglerInterface
{
    protected const IF_INDEX_PREFIX = '1.3.6.1.2.1.2.2.1.1.';
    protected const IF_INDEX_LENGTH = 20;

    public function transform(mixed $string): mixed
    {
        if (null !== $string && str_starts_with($string, self::IF_INDEX_PREFIX)) {
            return (int) substr($string, self::IF_INDEX_LENGTH);
        }

        return $string;
    }
}
