<?php

declare(strict_types=1);

namespace FormularioDorado\Admin;

interface AdminRepository
{
    public function findByUsername(string $username): ?AdminUser;

    public function findById(int $id): ?AdminUser;

    public function create(string $username, string $passwordHash, string $totpSecretCiphertext): int;

    public function updatePasswordHash(int $id, string $passwordHash): void;

    public function changePassword(int $id, string $passwordHash): void;

    public function replaceTotpSecret(int $id, string $totpSecretCiphertext): void;

    public function claimTotpStep(int $id, int $step): bool;
}
