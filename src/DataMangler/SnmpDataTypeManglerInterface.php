<?php

namespace IMEdge\SnmpFeature\DataMangler;

use IMEdge\Snmp\DataType\DataType;

interface SnmpDataTypeManglerInterface
{
    public function transform(DataType $value): ?DataType;
}
