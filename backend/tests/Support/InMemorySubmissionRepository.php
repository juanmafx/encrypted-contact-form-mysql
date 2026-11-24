<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Support;

use DateTimeImmutable;
use FormularioDorado\Contact\StoredSubmission;
use FormularioDorado\Contact\SubmissionRepository;

final class InMemorySubmissionRepository implements SubmissionRepository
{
    public array $rows = [];

    public function save(StoredSubmission $submission): void
    {
        $this->rows[$submission->id] = $submission;
    }

    public function find(string $id): ?StoredSubmission
    {
        return $this->rows[$id] ?? null;
    }

    public function page(int $limit, int $offset): array
    {
        return array_slice(array_values($this->rows), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->rows);
    }

    public function markNotified(string $id, DateTimeImmutable $at): void
    {
        $row = $this->rows[$id];
        $this->rows[$id] = new StoredSubmission($row->id, $row->nameCiphertext, $row->phoneCiphertext, $row->emailCiphertext, $row->messageCiphertext, $row->createdAt, $at);
    }

    public function delete(string $id): bool
    {
        $exists = isset($this->rows[$id]);
        unset($this->rows[$id]);

        return $exists;
    }

    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        $before = count($this->rows);
        $this->rows = array_filter($this->rows, static fn (StoredSubmission $row): bool => $row->createdAt >= $cutoff);

        return $before - count($this->rows);
    }
}
