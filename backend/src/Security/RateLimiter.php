<?php

declare(strict_types=1);

namespace FormularioDorado\Security;

interface RateLimiter
{
    public function hit(string $bucketHash, int $limit, int $windowSeconds): bool;

    public function clear(string $bucketHash): void;

    public function purgeExpired(int $olderThanSeconds): int;
}
