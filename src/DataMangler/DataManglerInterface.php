<?php

namespace IMEdge\SnmpFeature\DataMangler;

interface DataManglerInterface
{
    public function transform(mixed $string): mixed;
}
