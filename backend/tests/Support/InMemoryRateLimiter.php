<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Support;

use FormularioDorado\Security\RateLimiter;
use Psr\Clock\ClockInterface;

final class InMemoryRateLimiter implements RateLimiter
{
    private array $buckets = [];

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function hit(string $bucketHash, int $limit, int $windowSeconds): bool
    {
        $now = $this->clock->now()->getTimestamp();
        $bucket = $this->buckets[$bucketHash] ?? null;
        if ($bucket === null || $bucket[1] <= $now - $windowSeconds) {
            $bucket = [0, $now];
        }
        $bucket[0]++;
        $this->buckets[$bucketHash] = $bucket;

        return $bucket[0] <= $limit;
    }

    public function clear(string $bucketHash): void
    {
        unset($this->buckets[$bucketHash]);
    }

    public function purgeExpired(int $olderThanSeconds): int
    {
        $before = count($this->buckets);
        $cutoff = $this->clock->now()->getTimestamp() - $olderThanSeconds;
        $this->buckets = array_filter($this->buckets, static fn (array $bucket): bool => $bucket[1] >= $cutoff);

        return $before - count($this->buckets);
    }
}
