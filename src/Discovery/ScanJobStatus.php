<?php

namespace IMEdge\SnmpFeature\Discovery;

enum ScanJobStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case FINISHED = 'finished';
    case ABORTED = 'aborted';
    case FAILED = 'failed';
}
