<?php

namespace IMEdge\SnmpFeature\Polling\Worker;

use IMEdge\SnmpFeature\SnmpScenario\SnmpTargets;
use Psr\Log\LoggerInterface;

class TaskMessage
{
    public const STREAM_NAME_TASKS = 'snmp:tasks';

    public function __construct(
        public readonly array $tasks,
    ) {
    }

    // publish snmp:task 8702b50f-4686-5c3e-988c-6287b95d0d24:f550e741-0869-43d7-9123-82ed252550c3,
    //    1cb0212b-9bae-45ca-8328-94692159f1c9;
    public static function parse(
        string $message,
        LoggerInterface $logger,
        SnmpTargets $targets,
        array $scenarios
    ): TaskMessage {
        $tasks = [];
        foreach (preg_split('/;/', $message, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (!str_contains($part, ':')) {
                $logger->error("Got invalid message part on scenario poller subscription: $part");
                continue;
            }
            [$scenarioIndizes, $targetIndizes] = explode(':', $part, 2);
            $scenario = $scenarios[$scenarioIndizes] ?? null;
            if ($scenario === null) {
                $logger->warning('Poller has no such scenario: ' . $scenarioIndizes);
                continue;
            }
            $targetList = [];
            foreach (preg_split('/,/', $targetIndizes, -1, PREG_SPLIT_NO_EMPTY) as $targetIdx) {
                $target = $targets->targets[$targetIdx] ?? null;
                if ($target === null) {
                    $logger->warning('Poller has no such target: ' . $targetIdx);
                    continue;
                }
                $targetList[] = $target;
            }
            if (empty($targetList)) {
                continue;
            }

            $tasks[] = new ScenarioTask($scenario, new SnmpTargets($targetList));
        }

        return new TaskMessage($tasks);
    }
}
