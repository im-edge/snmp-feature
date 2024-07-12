<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;

#[Attribute]
class MangleToUtf8 implements DataManglerInterface
{
    public function transform(mixed $string): mixed
    {
        if (null !== $string && ! mb_check_encoding($string, 'UTF-8')) {
            return \utf8_encode($string);
        }

        return $string;
    }
}
