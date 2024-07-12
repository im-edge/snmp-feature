<?php

/**
 * This is an IMEdge Node feature
 *
 * @var Feature $this
 */

use IMEdge\Node\Feature;
use IMEdge\SnmpFeature\SnmpApi;
use IMEdge\SnmpFeature\SnmpRunner;

require __DIR__ . '/vendor/autoload.php';

$runner = new SnmpRunner($this->nodeIdentifier, $this->logger, $this->events, $this->services);
$api = new SnmpApi($runner, $this->logger);
$this->registerRpcApi($api);
$this->onShutdown($api->shutdown(...));
$this->onShutdown($runner->stop(...));
$runner->run();
