<?php

namespace IMEdge\SnmpFeature;

enum SnmpSecurityLevel: string
{
    case NO_AUTH_NO_PRIV  = 'noAuthNoPriv';
    case AUTH_NO_PRIV = 'authNoPriv';
    case AUTH_PRIV  = 'authPriv';
}
