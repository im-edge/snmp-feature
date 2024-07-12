<?php

namespace IMEdge\SnmpFeature;

enum SnmpAuthProtocol: string
{
    case MD5  = 'md5';
    case SHA = 'sha';
}
