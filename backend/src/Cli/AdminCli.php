<?php

declare(strict_types=1);

namespace FormularioDorado\Cli;

use FormularioDorado\Admin\AdminAuthenticator;
use FormularioDorado\Admin\AdminRepository;
use FormularioDorado\Contact\SubmissionService;
use FormularioDorado\Security\FieldCipher;
use FormularioDorado\Security\PasswordHasher;
use FormularioDorado\Security\RateLimiter;
use FormularioDorado\Security\TotpService;
use InvalidArgumentException;

final readonly class AdminCli
{
    private const string USAGE = "Usage: admin.php create <username> | set-password <username> | reset-totp <username> | purge\n";
    private const int RATE_LIMIT_RETENTION = 86400;

    public function __construct(
        private AdminRepository $admins,
        private PasswordHasher $hasher,
        private TotpService $totp,
        private FieldCipher $cipher,
        private SubmissionService $submissions,
        private RateLimiter $limiter,
        private int $retentionDays,
    ) {
    }

    public function run(array $arguments, mixed $input, mixed $output): int
    {
        $command = $arguments[1] ?? '';
        $username = strtolower(trim((string) ($arguments[2] ?? '')));

        try {
            return match ($command) {
                'create' => $this->create($username, $input, $output),
                'set-password' => $this->setPassword($username, $input, $output),
                'reset-totp' => $this->resetTotp($username, $output),
                'purge' => $this->purge($output),
                default => $this->fail(self::USAGE, $output),
            };
        } catch (InvalidArgumentException $failure) {
            return $this->fail($failure->getMessage() . "\n", $output);
        }
    }

    private function create(string $username, mixed $input, mixed $output): int
    {
        if (preg_match('/^[a-z0-9._-]{3,64}$/D', $username) !== 1) {
            return $this->fail("Username must be 3-64 characters of a-z, 0-9, dot, underscore or hyphen.\n", $output);
        }
        if ($this->admins->findByUsername($username) !== null) {
            return $this->fail("That admin already exists.\n", $output);
        }

        $hash = $this->hasher->hash($this->readPassword($input, $output));
        $secret = $this->totp->generateSecret();
        $this->admins->create($username, $hash, $this->cipher->encrypt($secret, AdminAuthenticator::totpContext($username)));
        $this->printEnrollment($secret, $username, $output);

        return 0;
    }

    private function setPassword(string $username, mixed $input, mixed $output): int
    {
        $admin = $this->admins->findByUsername($username);
        if ($admin === null) {
            return $this->fail("Admin not found.\n", $output);
        }

        $this->admins->changePassword($admin->id, $this->hasher->hash($this->readPassword($input, $output)));
        fwrite($output, "Password updated.\n");

        return 0;
    }

    private function resetTotp(string $username, mixed $output): int
    {
        $admin = $this->admins->findByUsername($username);
        if ($admin === null) {
            return $this->fail("Admin not found.\n", $output);
        }

        $secret = $this->totp->generateSecret();
        $this->admins->replaceTotpSecret($admin->id, $this->cipher->encrypt($secret, AdminAuthenticator::totpContext($username)));
        $this->printEnrollment($secret, $username, $output);

        return 0;
    }

    private function purge(mixed $output): int
    {
        $submissions = $this->submissions->purgeOlderThanDays($this->retentionDays);
        $buckets = $this->limiter->purgeExpired(self::RATE_LIMIT_RETENTION);
        fwrite($output, sprintf("Purged %d submissions and %d rate limit buckets.\n", $submissions, $buckets));

        return 0;
    }

    private function readPassword(mixed $input, mixed $output): string
    {
        fwrite($output, 'Password (minimum 12 characters): ');
        $isTerminal = is_resource($input) && stream_isatty($input);
        if ($isTerminal) {
            shell_exec('stty -echo');
        }
        $line = fgets($input);
        if ($isTerminal) {
            shell_exec('stty echo');
            fwrite($output, "\n");
        }

        return rtrim((string) $line, "\r\n");
    }

    private function printEnrollment(string $secret, string $username, mixed $output): void
    {
        fwrite($output, "Add this URI to an authenticator app. It is shown only once:\n");
        fwrite($output, $this->totp->provisioningUri($secret, $username) . "\n");
    }

    private function fail(string $message, mixed $output): int
    {
        fwrite($output, $message);

        return 1;
    }
}
