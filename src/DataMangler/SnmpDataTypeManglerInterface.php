<?php

namespace IMEdge\SnmpFeature\DataMangler;

use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use JsonSerializable;

interface SnmpDataTypeManglerInterface extends JsonSerializable
{
    public function transformVarBindValue(VarBindValue $value): ?VarBindValue;

    public static function getShortName(): string;
}
