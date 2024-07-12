<?php

namespace IMEdge\SnmpFeature;

enum SnmpVersion: string
{
    case v1  = '1';
    case v2c = '2c';
    case v3  = '3';
}
