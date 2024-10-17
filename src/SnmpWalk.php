<?php

namespace IMEdge\SnmpFeature;

use Amp\DeferredFuture;
use Exception;
use IMEdge\Snmp\DataType\DataType;
use IMEdge\Snmp\DataType\DataTypeContextSpecific;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

class SnmpWalk
{
    protected DeferredFuture $deferred;
    protected string $target;
    protected string $community;
    /** @var array<string, DataType> */
    protected array $results;
    protected string $baseOid;
    protected ?string $nextOid = null;
    protected bool $getBulk = true  ; // TODO: parameterize, false for v1
    protected int $timeoutCount = 0;

    public function __construct(
        protected readonly SnmpSocket $socket,
        protected readonly LoggerInterface $logger,
        protected ?int $limit = null,
    ) {
    }

    public function setNextOid(string $nextOid): void
    {
        $this->nextOid = $nextOid;
    }

    public function walk(
        string $oid,
        string $target,
        #[\SensitiveParameter] string $community
    ): array {
        $this->timeoutCount = 0;
        $this->results = [];
        $this->baseOid = $oid;
        if ($this->nextOid === null) {
            $this->nextOid = $oid;
        }
        $this->target = $target;
        $this->community = $community;
        // TODO: Multiple OIDs
        $this->deferred = new DeferredFuture();
        EventLoop::queue($this->next(...));
        return $this->deferred->getFuture()->await();
    }

    protected function useGetBulk(): bool
    {
        return $this->getBulk && ($this->target !== '192.168.178.88:161') && ($this->target !== '192.168.178.88');
    }

    protected function getMaxRepetitions(): int
    {
        $maxLimit = 16;
        if ($this->limit === null) {
            return $maxLimit;
        } else {
            return min($this->limit, $maxLimit);
        }
    }

    protected function next(): void
    {
        // TODO: Align max-repetitions with limit, try to not fetch more than required,
        //       and to avoid useless queries. Like: if we fetch 21 per default (for the
        //       "more" link), it would be a waste of roundtrips to ask for 20 twice
        if (!$this->nextOid) {
            throw new \LogicException('Running next() before $nextOid has been set');
        }
        try {
            if ($this->useGetBulk()) {
                $result = $this->socket->getBulk(
                    $this->nextOid,
                    $this->target,
                    $this->community,
                    $this->getMaxRepetitions()
                );
            } else {
                $result = $this->socket->getNext(
                    [$this->nextOid],
                    $this->target,
                    $this->community
                );
            }
            $this->handleResult($result);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handleError(Exception $e)
    {
        if (preg_match('/timeout/i', $e->getMessage())) {
            if ($this->timeoutCount < 3) {
                $this->timeoutCount++;
                /* $this->logger->notice(sprintf(
                    'Walk for %s timed out %d time(s), trying again',
                    $this->target,
                    $this->timeoutCount
                )); */
                EventLoop::queue($this->next(...));
                return;
            } else {
                /* $this->logger->notice(sprintf(
                    'Walk for %s timed out %d time(s), giving up',
                    $this->target,
                    $this->timeoutCount
                )); */
                $this->timeoutCount = 0;
            }
        }
        $this->logger->error('SnmpWalk::next: ' . $e->getMessage());
        // TODO: Do not resolve with half-complete result. How to deal with timeouts?
        $this->resolve();
    }

    protected function resolve(): void
    {
        $this->deferred->complete($this->results);
    }

    /**
     * @param array{type: string, value: DataType} $result
     */
    protected function handleResult(array $result): void
    {
        $oid = $this->baseOid;
        /**
         * @var string $newOid
         * @var DataType $value
         */
        foreach ($result as $newOid => $value) {
            if (
                ! str_starts_with($newOid, $this->baseOid) // Other prefix
                || ($value instanceof DataTypeContextSpecific
                && $value->getTag() === DataTypeContextSpecific::END_OF_MIB_VIEW) // End Of MIB
            ) {
                // TODO: resolve only when all branches completed (-> in the case of multiple OIDs)
                $this->resolve();
                return;
            }

            if ($newOid === $oid) {
                if (! isset($this->results[$newOid])) {
                    // Keep the value in case we started here
                    $this->results[$newOid] = $value;
                }
                $this->resolve();
                return;
            }

            $this->nextOid = $newOid;
            $this->results[$newOid] = $value;
        }

        if ($this->limit === null || count($this->results) < $this->limit) {
            EventLoop::queue($this->next(...));
        } else {
            $this->resolve();
        }
    }
}
