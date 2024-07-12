<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;

#[Attribute]
class MultiplyInteger implements DataManglerInterface
{
    public function __construct(protected int $factor)
    {
    }

    public function transform(mixed $string): ?int
    {
        if ($string === null) {
            return null;
        }

        return (int) round($string * $this->factor);
    }
}
