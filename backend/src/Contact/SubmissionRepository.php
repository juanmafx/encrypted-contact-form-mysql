<?php

declare(strict_types=1);

namespace FormularioDorado\Contact;

use DateTimeImmutable;

interface SubmissionRepository
{
    public function save(StoredSubmission $submission): void;

    public function find(string $id): ?StoredSubmission;

    public function page(int $limit, int $offset): array;

    public function count(): int;

    public function markNotified(string $id, DateTimeImmutable $at): void;

    public function delete(string $id): bool;

    public function deleteOlderThan(DateTimeImmutable $cutoff): int;
}
