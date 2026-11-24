<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Support;

use FormularioDorado\Admin\AdminRepository;
use FormularioDorado\Admin\AdminUser;

final class InMemoryAdminRepository implements AdminRepository
{
    public array $rows = [];

    public function findByUsername(string $username): ?AdminUser
    {
        foreach ($this->rows as $row) {
            if ($row->username === $username) {
                return $row;
            }
        }

        return null;
    }

    public function findById(int $id): ?AdminUser
    {
        return $this->rows[$id] ?? null;
    }

    public function create(string $username, string $passwordHash, string $totpSecretCiphertext): int
    {
        $id = count($this->rows) + 1;
        $this->rows[$id] = new AdminUser($id, $username, $passwordHash, $totpSecretCiphertext, null);

        return $id;
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $row = $this->rows[$id];
        $this->rows[$id] = new AdminUser($id, $row->username, $passwordHash, $row->totpSecretCiphertext, $row->lastTotpStep);
    }

    public function replaceTotpSecret(int $id, string $totpSecretCiphertext): void
    {
        $row = $this->rows[$id];
        $this->rows[$id] = new AdminUser($id, $row->username, $row->passwordHash, $totpSecretCiphertext, null);
    }

    public function claimTotpStep(int $id, int $step): bool
    {
        $row = $this->rows[$id];
        if ($row->lastTotpStep !== null && $row->lastTotpStep >= $step) {
            return false;
        }
        $this->rows[$id] = new AdminUser($id, $row->username, $row->passwordHash, $row->totpSecretCiphertext, $step);

        return true;
    }
}
