<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;

#[Attribute]
class DivideInteger implements DataManglerInterface
{
    public function __construct(protected int $divisor)
    {
    }

    public function transform(mixed $string): ?int
    {
        if ($string === null) {
            return null;
        }

        return (int) round($string / $this->divisor);
    }
}
