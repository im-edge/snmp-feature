<?php

namespace IMEdge\SnmpFeature\SnmpScenario;

use function hrtime;

class AggregatedCounter
{
    protected int $currentPeriodStart;
    protected array $initialCounters;

    public function __construct(
        public readonly int $interval,
        public array $currentCounters,
    ) {
        $this->currentPeriodStart = (int) floor(hrtime()[0] / $this->interval);
        $this->initialCounters = $this->currentCounters;
    }

    public function update(array $counters): ?array
    {
        $time = hrtime()[0];
        $currentPeriodStart = (int) floor($time / $this->interval);
        if ($currentPeriodStart !== $this->currentPeriodStart) {
            $timeDiff = $time - $this->currentPeriodStart;
            $result = [
                (int) round(round((time() - $timeDiff) / $this->interval) * $this->interval),
                $this->getFormerPeriodValues()
            ];
            $this->initialCounters = $this->currentCounters;

            if ($timeDiff > (2 * $this->interval)) {
                // Outdated former counters should be forgotten
                $this->initialCounters = [];
            }
        } else {
            $result = null;
        }

        // We keep former keys missing in this update, and add unknown initial counters
        $this->currentCounters = $counters + $this->currentCounters;
        $this->initialCounters += $counters;

        return $result;
    }

    protected function getFormerPeriodValues(): array
    {
        $values = [];
        foreach ($this->initialCounters as $key => $value) {
            if (array_key_exists($key, $this->currentCounters)) {
                $diff = gmp_sub($this->currentCounters[$key], $this->initialCounters[$key]);
                if (gmp_cmp($diff, 0) === -1) {
                    // Counter rolled over. We could subscribe boot events to keep former diff,
                    // but for now we report the last counter value only
                    $values[$key] = $this->currentCounters[$key];
                } else {
                    $values[$key] = $diff->serialize();
                }
            }
        }

        return $values;
    }
}
