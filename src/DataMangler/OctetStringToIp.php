<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;

#[Attribute]
class OctetStringToIp implements DataManglerInterface
{
    public function transform(mixed $string): mixed
    {
        if ($string === null || $string === '') {
            return null;
        }

        // return $string;
        return inet_ntop($string);
    }
}
