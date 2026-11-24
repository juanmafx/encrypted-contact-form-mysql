<?php

declare(strict_types=1);

namespace FormularioDorado\Admin;

interface AuditLog
{
    public function record(int $adminId, string $action, ?string $submissionId): void;
}
