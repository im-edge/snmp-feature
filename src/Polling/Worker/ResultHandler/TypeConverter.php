<?php

namespace IMEdge\SnmpFeature\Polling\Worker\ResultHandler;

use IMEdge\Json\JsonString;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioPropertyDefinition;
use IMEdge\SnmpFeature\Polling\ScenarioDefinition\ScenarioPropertyType;
use IMEdge\SnmpPacket\VarBindValue\ContextSpecific;
use IMEdge\SnmpPacket\VarBindValue\Counter64;
use IMEdge\SnmpPacket\VarBindValue\Integer32;
use IMEdge\SnmpPacket\VarBindValue\NullValue;
use IMEdge\SnmpPacket\VarBindValue\ObjectIdentifier;
use IMEdge\SnmpPacket\VarBindValue\OctetString;
use IMEdge\SnmpPacket\VarBindValue\Unsigned32;
use IMEdge\SnmpPacket\VarBindValue\VarBindValue;
use Ramsey\Uuid\Uuid;
use ValueError;

class TypeConverter
{
    public static function createNativePhpType(?VarBindValue $value, ScenarioPropertyDefinition $definition): mixed
    {
        if ($value === null || $value instanceof NullValue || $value instanceof ContextSpecific) {
            if ($definition->nullable) {
                return null;
            } else {
                throw new ValueError(sprintf('%s does not allow NULL values', $definition->name));
            }
        }

        // TODO: ScenarioPropertyType might return a converter instance, load it once
        switch ($definition->type) {
            case ScenarioPropertyType::TYPE_INT:
                if ($value instanceof Integer32 || $value instanceof Unsigned32 || $value instanceof Counter64) {
                    // might not work for large counter64 values, but... then this should not be int
                    $phpValue = (int)$value->getReadableValue();
                } elseif ($value instanceof OctetString || $value instanceof ObjectIdentifier) {
                    if (ctype_digit($value->value)) {
                        $phpValue = (int) $value->value;
                    } else {
                        throw new ValueError(sprintf(
                            'Cannot cast OctetString %s to int',
                            $value->getReadableValue()
                        ));
                    }
                } else {
                    throw new ValueError(sprintf('Cannot cast %s to int', JsonString::encode($value)));
                }
                break;
            case ScenarioPropertyType::TYPE_UUID:
                if ($value instanceof OctetString) {
                    $phpValue = Uuid::fromBytes($value->value);
                } else {
                    throw new ValueError(sprintf('Cannot cast %s to UUID', JsonString::encode($value)));
                }
                break;
            case ScenarioPropertyType::TYPE_STRING:
                if ($value instanceof OctetString || $value instanceof ObjectIdentifier) {
                    $phpValue = self::createUtf8SafeString($value->value);
                } elseif ($value instanceof Integer32 || $value instanceof Unsigned32) {
                    $phpValue = (string) $value->getReadableValue();
                } else {
                    throw new ValueError(sprintf('Cannot cast %s to String', JsonString::encode($value)));
                }
                break;
            case ScenarioPropertyType::TYPE_OID:
                if ($value instanceof ObjectIdentifier) {
                    $phpValue = $value->getReadableValue();
                } else {
                    throw new ValueError(sprintf('Cannot cast %s to OID/String', JsonString::encode($value)));
                }
                break;
            case ScenarioPropertyType::TYPE_ENUM:
                $phpValue = $definition->enumProperties[$value->getReadableValue()] ?? null;
                if ($phpValue === null) {
                    throw new ValueError(sprintf('%s is not a valid enum property', $value->getReadableValue()));
                }
                break;
            case ScenarioPropertyType::TYPE_BOOLEAN:
                if ($value instanceof Integer32 || $value instanceof Unsigned32) {
                    switch ($value->getReadableValue()) {
                        case 1:
                            $phpValue = true;
                            break;
                        case 2:
                            $phpValue = false;
                            break;
                        default:
                            throw new ValueError('%s is not a valid TruthValue', $value->getReadableValue());
                    }
                } else {
                    throw new ValueError(sprintf('Cannot cast %s to boolean', JsonString::encode($value)));
                }
        }

        return $phpValue;
    }

    protected static function createUtf8SafeString(string $string): string
    {
        if (ctype_print($string)) {
            return $string;
        }
        if (mb_check_encoding($string, 'UTF-8')) {
            return $string;
        }

        return '0x' . bin2hex($string);
    }
}
