<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;

#[Attribute]
class ExactStringLengthOrNull implements DataManglerInterface
{
    public function __construct(protected int $stringLength)
    {
    }

    public function transform(mixed $string): mixed
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
}
