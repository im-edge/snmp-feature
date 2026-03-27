<?php

namespace IMEdge\SnmpFeature;

use IMEdge\Config\Settings;
use IMEdge\Inventory\NodeIdentifier;
use IMEdge\Node\Events;
use IMEdge\Node\Services;
use IMEdge\Node\Worker\WorkerInstance;
use IMEdge\Node\Worker\WorkerInstances;
use IMEdge\SnmpFeature\Discovery\SnmpDiscoveryReceiver;
use IMEdge\SnmpFeature\Discovery\SnmpDiscoverySender;
use IMEdge\SnmpFeature\Polling\Worker\Snmp\SnmpPoller;
use IMEdge\SnmpFeature\Polling\Worker\SnmpScenarioController;
use IMEdge\SnmpFeature\Polling\Worker\SnmpScenarioPoller;
use IMEdge\SnmpFeature\Polling\Worker\SnmpScenarioResultHandler;
use IMEdge\SnmpFeature\SnmpScenario\KnownTargetsHealth;
use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Revolt\EventLoop;

class SnmpRunner
{
    protected bool $shuttingDown = false;
    protected bool $startedRecently = true;

    public ?WorkerInstance $discoverySender = null;
    public ?WorkerInstance $discoveryReceiver = null;
    public ?WorkerInstance $scenarioController = null;
    public ?WorkerInstance $snmpPoller = null;
    public ?WorkerInstance $scenarioPoller = null;
    public ?WorkerInstance $scenarioResultHandler = null;

    public function __construct(
        public readonly NodeIdentifier $nodeIdentifier,
        protected readonly LoggerInterface $logger,
        public readonly Events $events,
        public readonly Services $services,
        protected readonly Settings $settings,
        protected readonly WorkerInstances $workerInstances,
        public SnmpCredentials $credentials = new SnmpCredentials([]),
        public SnmpTargets $targets = new SnmpTargets(),
        public KnownTargetsHealth $health = new KnownTargetsHealth(),
    ) {
    }

    public function run(): void
    {
        $this->startedRecently = true;
        EventLoop::delay(20, function () {
            // There is a race condition on run/stop/run, affects log lines only
            $this->startedRecently = false;
        });
        $this->startDiscoveryWorkers();
        $this->startScenarioWorkers();
    }

    public function stop(): void
    {
        $this->shuttingDown = true;
        $this->stopDiscoveryWorkers();
        $this->stopScenarioWorkers();
    }

    public function setTargets(SnmpTargets $targets): void
    {
        $diff = $this->targets->listRemovedTargets($targets);
        $this->targets = $targets;
        foreach ($diff as $target) {
            $this->health->forget($target->identifier);
        }
        foreach ($targets->targets as $newTarget) {
            if (! $this->health->has($newTarget->identifier)) {
                $this->health->setCurrentResult($newTarget->identifier, $newTarget->state);
            }
        }

        /** @see SnmpScenarioPoller::setTargets() */
        $this->scenarioPoller->jsonRpc->request('snmpScenarioPoller.setTargets', [$this->targets]);
        $this->snmpPoller->jsonRpc->request('snmpPoller.setTargets', [$this->targets]);
        $this->scenarioResultHandler->jsonRpc->request('snmpScenarioResultHandler.setTargets', [$this->targets]);
        $this->scenarioController->jsonRpc->request('snmpScenarioController.setTargets', [$this->targets]);
    }

    public function setCredentials(SnmpCredentials $credentials): void
    {
        $this->credentials = $credentials;
        /** @see SnmpScenarioPoller::setCredentials() */
        $this->scenarioPoller->jsonRpc->request('snmpScenarioPoller.setCredentials', [$credentials]);
        $this->snmpPoller->jsonRpc->request('snmpPoller.setCredentials', [$credentials]);
    }

    protected function startDiscoveryWorkers(): void
    {
        $worker = $this->workerInstances->launchWorker('snmp-discovery-receiver', Uuid::uuid4());
        $worker->run(SnmpDiscoveryReceiver::class);
        $this->discoveryReceiver = $worker;

        $worker = $this->workerInstances->launchWorker('snmp-discovery-sender', Uuid::uuid4());
        $worker->run(SnmpDiscoverySender::class);
        $this->discoverySender = $worker;

        $this->logger->debug('Launched Sender and Receiver for Discovery Tasks');
    }

    protected function stopDiscoveryWorkers(): void
    {
        if ($this->discoverySender) {
            $this->discoverySender->stop();
            $this->discoverySender = null;
        }
        if ($this->discoveryReceiver) {
            $this->discoveryReceiver->stop();
            $this->discoveryReceiver = null;
        }
        $this->logger->debug('Stopped Sender and Receiver for Discovery Tasks');
    }

    protected function startScenarioWorkers(): void
    {
        $worker = $this->workerInstances->launchWorker('snmp-scenario-controller', Uuid::uuid4());
        $worker->run(SnmpScenarioController::class);
        $this->scenarioController = $worker;

        $poller = $this->workerInstances->launchWorker('snmp-scenario-poller', Uuid::uuid4());
        $poller->run(SnmpScenarioPoller::class);
        $this->scenarioPoller = $poller;

        $worker = $this->workerInstances->launchWorker('snmp-poller', Uuid::uuid4());
        $worker->run(SnmpPoller::class);
        $this->snmpPoller = $worker;

        $worker = $this->workerInstances->launchWorker('snmp-scenario-result-handler', Uuid::uuid4());
        $worker->run(SnmpScenarioResultHandler::class);
        $this->scenarioResultHandler = $worker;

        if ($pathToMetricStore = $this->settings->get('metricStore')) {
            $this->scenarioResultHandler->jsonRpc->request(
                'snmpScenarioResultHandler.setMetricStorePath',
                [$pathToMetricStore]
            );
        } else {
            $this->logger->notice('SNMP feature is running w/o metric store');
        }

        $this->logger->debug('Launched SNMP/Scenario Workers');
    }

    protected function stopScenarioWorkers(): void
    {
        if ($this->scenarioController) {
            $this->scenarioController->stop();
            $this->scenarioController = null;
        }
        if ($this->snmpPoller) {
            $this->snmpPoller->stop();
            $this->snmpPoller = null;
        }
        if ($this->scenarioPoller) {
            $this->scenarioPoller->stop();
            $this->scenarioPoller = null;
        }
        if ($this->scenarioResultHandler) {
            $this->scenarioResultHandler->stop();
            $this->scenarioResultHandler = null;
        }
        $this->logger->debug('Stopped SNMP/Scenario Workers');
    }
}
