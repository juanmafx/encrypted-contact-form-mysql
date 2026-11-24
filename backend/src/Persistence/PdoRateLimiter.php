<?php

declare(strict_types=1);

namespace FormularioDorado\Persistence;

use FormularioDorado\Security\RateLimiter;
use PDO;
use Psr\Clock\ClockInterface;

final readonly class PdoRateLimiter implements RateLimiter
{
    public function __construct(private PDO $pdo, private ClockInterface $clock)
    {
    }

    public function hit(string $bucketHash, int $limit, int $windowSeconds): bool
    {
        $now = $this->clock->now()->getTimestamp();
        $expiredBefore = $now - $windowSeconds;

        $upsert = $this->pdo->prepare(
            'INSERT INTO rate_limit_buckets (bucket_hash, attempts, window_started_at) VALUES (?, 1, ?) AS incoming '
            . 'ON DUPLICATE KEY UPDATE '
            . 'attempts = IF(rate_limit_buckets.window_started_at <= ?, 1, rate_limit_buckets.attempts + 1), '
            . 'window_started_at = IF(rate_limit_buckets.window_started_at <= ?, incoming.window_started_at, rate_limit_buckets.window_started_at)',
        );
        $upsert->bindValue(1, $bucketHash, PDO::PARAM_LOB);
        $upsert->bindValue(2, $now, PDO::PARAM_INT);
        $upsert->bindValue(3, $expiredBefore, PDO::PARAM_INT);
        $upsert->bindValue(4, $expiredBefore, PDO::PARAM_INT);
        $upsert->execute();

        $select = $this->pdo->prepare('SELECT attempts FROM rate_limit_buckets WHERE bucket_hash = ?');
        $select->bindValue(1, $bucketHash, PDO::PARAM_LOB);
        $select->execute();

        return (int) $select->fetchColumn() <= $limit;
    }

    public function clear(string $bucketHash): void
    {
        $statement = $this->pdo->prepare('DELETE FROM rate_limit_buckets WHERE bucket_hash = ?');
        $statement->bindValue(1, $bucketHash, PDO::PARAM_LOB);
        $statement->execute();
    }

    public function purgeExpired(int $olderThanSeconds): int
    {
        $statement = $this->pdo->prepare('DELETE FROM rate_limit_buckets WHERE window_started_at < ?');
        $statement->bindValue(1, $this->clock->now()->getTimestamp() - $olderThanSeconds, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }
}
