<?php

namespace IMEdge\SnmpFeature;

use Amp\DeferredFuture;
use IMEdge\Snmp\DataType\DataType;
use IMEdge\Snmp\SocketAddress;
use Psr\Log\LoggerInterface;
use Revolt\EventLoop;

use function ltrim;
use function strlen;
use function substr;

class FetchTable
{
    protected DeferredFuture $deferred;

    /** @var array<int|string, array<string, mixed>> */
    protected array $results;

    /** @var array<int|string, string> */
    protected array $pendingColumns;

    /** @var array<int|string, string> */
    protected array $columns;
    protected SocketAddress $target;
    protected string $community;
    protected string $baseOid;
    protected string $currentPrefix;
    protected string $currentColumn;

    public function __construct(
        protected SnmpSocket $socket,
        protected LoggerInterface $logger,
        protected ?int $limit = null
    ) {
    }

    /**
     * @param array<int|string, string> $columns
     */
    public function fetchTable(
        string $oid,
        array $columns,
        SocketAddress $target,
        string $community
    ): array {
        $this->results = [];
        $this->baseOid = $oid;
        $this->target = $target;
        $this->community = $community;
        $this->columns = $this->pendingColumns = $columns;

        $this->deferred = new DeferredFuture();
        EventLoop::queue($this->next(...));

        return $this->deferred->getFuture()->await();
    }

    protected function next(): void
    {
        if (empty($this->pendingColumns)) {
            throw new \LogicException('Cannot call next() on empty pending columns');
        }
        $column = array_shift($this->pendingColumns);
        $this->currentColumn = $column;
        $this->currentPrefix = $this->baseOid . '.' . $column;
        try {
            $this->handleResult($this->fetchColumn($column));
        } catch (\Exception $e) {
            $this->resolve();
        }
    }

    /**
     * @param DataType[] $result
     */
    protected function handleResult(array $result): void
    {
        foreach ($result as $oid => $value) {
            [$idx, $key] = $this->splitAtFirstDot($this->stripPrefix($oid));
            // Dropping 1.
            [$idx, $key] = $this->splitAtFirstDot($key);
            // Now idx is the column. We don't care, as we already have it in currentColummn
            $this->results[$key][$this->currentColumn] = $value->jsonSerialize();
        }

        if (empty($this->pendingColumns)) {
            $this->resolve();
        } else {
            EventLoop::queue($this->next(...));
        }
    }

    /**
     * @param string $oid
     * @return array{0: string, 1: string}
     */
    protected function splitAtFirstDot(string $oid): array
    {
        $dot = strpos($oid, '.');
        if ($dot === false) {
            throw new \InvalidArgumentException("$oid has no dot");
        }

        return [
            substr($oid, 0, $dot),
            substr($oid, $dot + 1),
        ];
    }

    protected function hasPrefix(string $oid, string $prefix): bool
    {
        return str_starts_with($oid, $prefix);
    }

    protected function stripPrefix(string $oid, ?string $prefix = null): string
    {
        if ($prefix === null) {
            $prefix = $this->baseOid;
        }

        if (str_starts_with($oid, $prefix)) {
            $oid = substr($oid, strlen($prefix));
        }

        return ltrim($oid, '.');
    }

    protected function fetchIndex(): array
    {
        return $this->fetchColumn('1.1');
    }

    protected function fetchColumn(string $column): array
    {
        $walk = new SnmpWalk($this->socket, $this->logger);
        return $walk->walk($this->baseOid . '.' . $column, $this->target, $this->community);
    }

    protected function resolve(): void
    {
        $this->deferred->complete($this->results);
    }
}
