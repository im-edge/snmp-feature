<?php

namespace IMEdge\SnmpFeature\Capability;

enum CapabilityType: string
{
    case HAS_MIB = 'mib';
    case HAS_CHILD_OID = 'child_oid';
    case UNSPECIFIED = 'unspecified';

    /**
     * @return class-string<SupportedCapability>
     */
    public function getImplementation(): string
    {
        return match ($this) {
            self::HAS_MIB => CapabilityHasMib::class,
            self::HAS_CHILD_OID => CapabilityHasChildOid::class,
            self::UNSPECIFIED => UnspecifiedCapabilityType::class,
        };
    }
}
