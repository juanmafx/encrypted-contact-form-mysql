<?php

declare(strict_types=1);

namespace FormularioDorado\Persistence;

use FormularioDorado\Admin\AuditLog;
use PDO;

final readonly class PdoAuditLog implements AuditLog
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(int $adminId, string $action, ?string $submissionId): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO admin_audit_log (admin_id, action, submission_id) VALUES (?, ?, ?)',
        );
        $statement->execute([$adminId, $action, $submissionId]);
    }
}
