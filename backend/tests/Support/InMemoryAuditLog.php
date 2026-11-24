<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Support;

use FormularioDorado\Admin\AuditLog;

final class InMemoryAuditLog implements AuditLog
{
    public array $entries = [];

    public function record(int $adminId, string $action, ?string $submissionId): void
    {
        $this->entries[] = [$adminId, $action, $submissionId];
    }

    public function actions(): array
    {
        return array_column($this->entries, 1);
    }
}
