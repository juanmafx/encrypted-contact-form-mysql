<?php

declare(strict_types=1);

namespace FormularioDorado\Persistence;

use FormularioDorado\Admin\AdminRepository;
use FormularioDorado\Admin\AdminUser;
use PDO;

final readonly class PdoAdminRepository implements AdminRepository
{
    private const string COLUMNS = 'id, username, password_hash, totp_secret_ciphertext, last_totp_step, session_version';

    public function __construct(private PDO $pdo)
    {
    }

    public function findByUsername(string $username): ?AdminUser
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM admin_users WHERE username = ?');
        $statement->execute([$username]);

        return $this->hydrate($statement->fetch());
    }

    public function findById(int $id): ?AdminUser
    {
        $statement = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM admin_users WHERE id = ?');
        $statement->execute([$id]);

        return $this->hydrate($statement->fetch());
    }

    public function create(string $username, string $passwordHash, string $totpSecretCiphertext): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, totp_secret_ciphertext) VALUES (?, ?, ?)',
        );
        $statement->execute([$username, $passwordHash, $totpSecretCiphertext]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
        $statement->execute([$passwordHash, $id]);
    }

    public function changePassword(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE admin_users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?',
        );
        $statement->execute([$passwordHash, $id]);
    }

    public function replaceTotpSecret(int $id, string $totpSecretCiphertext): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE admin_users SET totp_secret_ciphertext = ?, last_totp_step = NULL, session_version = session_version + 1 WHERE id = ?',
        );
        $statement->execute([$totpSecretCiphertext, $id]);
    }

    public function claimTotpStep(int $id, int $step): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE admin_users SET last_totp_step = ? WHERE id = ? AND (last_totp_step IS NULL OR last_totp_step < ?)',
        );
        $statement->execute([$step, $id, $step]);

        return $statement->rowCount() === 1;
    }

    private function hydrate(mixed $row): ?AdminUser
    {
        if (!is_array($row)) {
            return null;
        }

        return new AdminUser(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['password_hash'],
            (string) $row['totp_secret_ciphertext'],
            $row['last_totp_step'] === null ? null : (int) $row['last_totp_step'],
            (int) $row['session_version'],
        );
    }
}
