<?php

namespace IMEdge\SnmpFeature;

use IMEdge\Json\JsonSerialization;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class SnmpCredential implements JsonSerialization
{
    protected static array $propertyToSerialized = [
        'uuid'          => 'credential_uuid',
        'name'          => 'credential_name',
        'version'       => 'snmp_version',
        'securityName'  => 'security_name',
        'securityLevel' => 'security_level',
        'authProtocol'  => 'auth_protocol',
        'authKey'       => 'auth_key',
        'privProtocol'  => 'priv_protocol',
        'privKey'       => 'priv_key',
    ];
    protected static ?array $serializedToProperty = null;

    public function __construct(
        public readonly ?UuidInterface $uuid = null,
        public readonly ?string $name = null,
        public readonly ?SnmpVersion $version = null,
        public readonly ?string $securityName = null, // SNMPv1/2c community string, v3 user
        public readonly ?SnmpSecurityLevel $securityLevel = null,
        public readonly ?SnmpAuthProtocol $authProtocol = null,
        public readonly ?string $authKey = null,
        public readonly ?SnmpPrivProtocol $privProtocol = null,
        public readonly ?string $privKey = null,
    ) {
    }

    public static function fromSerialization($any): static
    {
        $any = self::unSerializeKeys($any);
        // PHPstan will complain!
        if (isset($any['uuid'])) {
            $any['uuid'] = Uuid::fromString($any['uuid']);
        }
        $any['version'] = SnmpVersion::from($any['version']);
        if (array_key_exists('securityLevel', $any)) {
            $any['securityLevel'] = SnmpSecurityLevel::from($any['securityLevel']);
        }
        if (array_key_exists('authProtocol', $any)) {
            $any['authProtocol'] = SnmpAuthProtocol::from($any['authProtocol']);
        }
        if (array_key_exists('privProtocol', $any)) {
            $any['privProtocol'] = SnmpPrivProtocol::from($any['privProtocol']);
        }

        return new static(...$any);
    }

    protected static function unSerializeKeys($any): array
    {
        $result = [];
        self::$serializedToProperty ??= array_flip(self::$propertyToSerialized);
        foreach ((array) $any as $key => $value) {
            $result[self::$serializedToProperty[$key] ?? $key] = $value;
        }

        return $result;
    }

    protected static function serializeKeys(array $properties): array
    {
        $result = [];
        foreach ($properties as $key => $value) {
            $result[self::$propertyToSerialized[$key] ?? $key] = $value;
        }

        return $result;
    }

    public function jsonSerialize(): object
    {
        return (object) self::serializeKeys(array_filter(get_object_vars($this), fn($v) => $v !== null));
    }
}
