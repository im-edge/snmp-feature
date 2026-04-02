<?php

namespace IMEdge\SnmpFeature\Discovery;

use FreeDSx\Asn1\Encoder\BerEncoder;
use FreeDSx\Asn1\Type\AbstractStringType;
use IMEdge\SnmpPacket\Message\VarBind;
use IMEdge\SnmpPacket\Message\VarBindList;

class BinaryVarBinds extends AbstractStringType
{
    protected $tagNumber = self::TAG_TYPE_SEQUENCE;
    protected $isConstructed = true;

    public static function fromList(VarBindList $varBinds, BerEncoder $encoder): BinaryVarBinds
    {
        $binary = '';
        foreach ($varBinds->varBinds as $varBind) {
            $binary .= $encoder->encode($varBind->toAsn1());
        }

        return new BinaryVarBinds($binary);
    }

    public static function fromOidList(array $oids, BerEncoder $encoder): BinaryVarBinds
    {
        $binary = '';
        foreach ($oids as $oid) {
            $binary .= $encoder->encode((new VarBind($oid))->toAsn1());
        }

        return new BinaryVarBinds($binary);
    }
}
