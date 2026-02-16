<?php

namespace IMEdge\SnmpFeature\DataMangler;

use Attribute;
use IMEdge\SnmpPacket\VarBindValue\Integer32;
use IMEdge\SnmpPacket\VarBindValue\Unsigned32;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;

#[Attribute]
class MultiplyInteger extends SimpleSnmpDataMangler implements DataManglerInterface
{
    public const SHORT_NAME = 'multiplyInteger';

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

    public function transformVarBindValue(VarBindValue $value): ?VarBindValue
    {
        if ($value instanceof Integer32) {
            return new Integer32($this->transform($value->getReadableValue()));
        } elseif ($value instanceof Unsigned32) {
            return new Unsigned32($this->transform($value->getReadableValue()));
        }

        throw new \ValueError("Integer32/Unsigned32 expected, got " . get_class($value));
    }

    #[\Override]
    protected function serializeSettings(): array
    {
        return [$this->factor];
    }
}
