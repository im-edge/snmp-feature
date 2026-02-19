<?php

namespace IMEdge\SnmpFeature\Polling\ScenarioDefinition;

enum ScenarioPropertyType: string
{
    case TYPE_INT = 'int';
    case TYPE_UUID = 'uuid';
    case TYPE_STRING = 'string';
    case TYPE_ENUM = 'enum'; // Which one?
    case TYPE_BOOLEAN = 'bool';
    case TYPE_OID = 'oid';
}
