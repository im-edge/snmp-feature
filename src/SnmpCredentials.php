<?php

namespace IMEdge\SnmpFeature;

use IMEdge\Json\JsonSerialization;
use OutOfBoundsException;
use Ramsey\Uuid\UuidInterface;

class SnmpCredentials implements JsonSerialization
{
    public function __construct(
        /** @var SnmpCredential[] */
        public readonly array $credentials,
    ) {
    }

    public static function fromSerialization($any): SnmpCredentials
    {
        $credentials = [];
        foreach ($any as $credential) {
            $credential = SnmpCredential::fromSerialization($credential);
            $credentials[$credential->uuid->getBytes()] = $credential;
        }

        return new static($credentials);
    }

    public function requireCredential(UuidInterface $uuid): SnmpCredential
    {
        return $this->credentials[$uuid->getBytes()] ?? throw new OutOfBoundsException("Got no credential for $uuid");
    }

    public function jsonSerialize(): array
    {
        return array_values($this->credentials);
    }
}
