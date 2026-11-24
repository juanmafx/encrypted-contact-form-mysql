<?php

declare(strict_types=1);

namespace FormularioDorado\Security;

use InvalidArgumentException;

final readonly class PasswordHasher
{
    public const int MIN_LENGTH = 12;
    public const int MAX_LENGTH = 4096;

    private const array OPTIONS = ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1];

    public function hash(string $password): string
    {
        $length = strlen($password);
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Password must be between 12 and 4096 bytes.');
        }

        return password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    public function verify(string $password, string $hash): bool
    {
        $matches = password_verify(substr($password, 0, self::MAX_LENGTH), $hash);

        return $matches && strlen($password) <= self::MAX_LENGTH;
    }

    public function verifyDecoy(string $password): void
    {
        password_hash(substr($password, 0, self::MAX_LENGTH), PASSWORD_ARGON2ID, self::OPTIONS);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }
}
