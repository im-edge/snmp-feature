<?php

namespace IMEdge\SnmpFeature\SnmpScenario\Event;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;

class EventDispatcher implements EventEmitterInterface
{
    use EventEmitterTrait;
}
