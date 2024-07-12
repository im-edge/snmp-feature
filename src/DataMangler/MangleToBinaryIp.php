<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use InvalidArgumentException;

#[Attribute]
class MangleToBinaryIp implements DataManglerInterface
{
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
}
