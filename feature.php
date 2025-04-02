<?php

/**
 * This is an IMEdge Node feature
 *
 * @var Feature $this
 */

use IMEdge\Node\Feature;
use IMEdge\SnmpFeature\SnmpApi;
use IMEdge\SnmpFeature\SnmpRunner;

$runner = new SnmpRunner($this->nodeIdentifier, $this->logger, $this->events, $this->services, $this->workerInstances);
$api = new SnmpApi($runner, $this->logger);
$this->registerRpcApi($api);
$this->onShutdown($api->shutdown(...));
$this->onShutdown($runner->stop(...));
$runner->run();
