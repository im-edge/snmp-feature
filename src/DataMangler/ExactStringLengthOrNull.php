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
        if ($string === null) {
            return null;
        }

        if (is_string($string) && strlen($string) === $this->stringLength) {
            return $this->stringLength;
        }

        return null;
    }
}
