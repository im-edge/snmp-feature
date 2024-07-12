<?php

namespace IMEdge\SnmpFeature\DataStructure;

// Mib: SNMPv2-TC::RowStatus
enum RowStatus: int implements EnumInterface
{
    case ACTIVE          = 1;
    case NOT_IN_SERVICE  = 2;
    case NOT_READY       = 3;
    case CREATE_AND_GO   = 4;
    case CREATE_AND_WAIT = 5;
    case DESTROY         = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE          => 'active',
            self::NOT_IN_SERVICE  => 'notInService',
            self::NOT_READY       => 'notReady',
            self::CREATE_AND_GO   => 'createAndGo',
            self::CREATE_AND_WAIT => 'createAndWait',
            self::DESTROY         => 'destroy',
        };
    }
}
