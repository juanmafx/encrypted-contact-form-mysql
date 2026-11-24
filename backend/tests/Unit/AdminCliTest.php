<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Admin\AdminAuthenticator;
use FormularioDorado\Cli\AdminCli;
use FormularioDorado\Contact\SubmissionService;
use FormularioDorado\Security\FieldCipher;
use FormularioDorado\Security\PasswordHasher;
use FormularioDorado\Security\TotpService;
use FormularioDorado\Tests\Support\FrozenClock;
use FormularioDorado\Tests\Support\InMemoryAdminRepository;
use FormularioDorado\Tests\Support\InMemoryRateLimiter;
use FormularioDorado\Tests\Support\InMemorySubmissionRepository;
use FormularioDorado\Tests\Support\RecordingNotifier;
use PHPUnit\Framework\TestCase;

final class AdminCliTest extends TestCase
{
    private InMemoryAdminRepository $admins;
    private FieldCipher $cipher;
    private AdminCli $cli;

    protected function setUp(): void
    {
        $clock = new FrozenClock(1_800_000_000);
        $this->admins = new InMemoryAdminRepository();
        $this->cipher = new FieldCipher(['v1' => random_bytes(32)], 'v1');
        $service = new SubmissionService(new InMemorySubmissionRepository(), $this->cipher, new RecordingNotifier(), $clock);
        $this->cli = new AdminCli($this->admins, new PasswordHasher(), new TotpService($clock), $this->cipher, $service, new InMemoryRateLimiter($clock), 30);
    }

    private function run_(array $arguments, string $stdin = ''): array
    {
        $input = fopen('php://memory', 'r+');
        fwrite($input, $stdin);
        rewind($input);
        $output = fopen('php://memory', 'r+');
        $code = $this->cli->run(['admin.php', ...$arguments], $input, $output);
        rewind($output);

        return [$code, (string) stream_get_contents($output)];
    }

    public function testCreateStoresHashedPasswordAndEncryptedSecret(): void
    {
        [$code, $output] = $this->run_(['create', 'Admin'], "a-long-enough-password\n");
        $admin = $this->admins->findByUsername('admin');

        self::assertSame(0, $code);
        self::assertStringContainsString('otpauth://totp/', $output);
        self::assertStringStartsWith('$argon2id$', $admin->passwordHash);
        self::assertNotEmpty($this->cipher->decrypt($admin->totpSecretCiphertext, AdminAuthenticator::totpContext('admin')));
    }

    public function testCreateRejectsWeakPasswordAndBadUsername(): void
    {
        self::assertSame(1, $this->run_(['create', 'admin'], "short\n")[0]);
        self::assertSame(1, $this->run_(['create', 'x'], "a-long-enough-password\n")[0]);
        self::assertSame([], $this->admins->rows);
    }

    public function testResetTotpClearsReplayState(): void
    {
        $this->run_(['create', 'admin'], "a-long-enough-password\n");
        $this->admins->claimTotpStep(1, 99);

        self::assertSame(0, $this->run_(['reset-totp', 'admin'])[0]);
        self::assertNull($this->admins->findById(1)->lastTotpStep);
    }

    public function testCredentialCommandsRevokeSessions(): void
    {
        $this->run_(['create', 'admin'], "a-long-enough-password\n");
        self::assertSame(1, $this->admins->findById(1)->sessionVersion);

        self::assertSame(0, $this->run_(['set-password', 'admin'], "another-long-password\n")[0]);
        self::assertSame(2, $this->admins->findById(1)->sessionVersion);

        self::assertSame(0, $this->run_(['reset-totp', 'admin'])[0]);
        self::assertSame(3, $this->admins->findById(1)->sessionVersion);
    }

    public function testUnknownCommandPrintsUsage(): void
    {
        [$code, $output] = $this->run_(['nope']);

        self::assertSame(1, $code);
        self::assertStringContainsString('Usage:', $output);
    }

    public function testPurgeReportsCounts(): void
    {
        [$code, $output] = $this->run_(['purge']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Purged 0 submissions', $output);
    }
}
