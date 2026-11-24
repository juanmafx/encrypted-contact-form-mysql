<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Support;

use FormularioDorado\Admin\AdminAuthenticator;
use FormularioDorado\Application;
use FormularioDorado\Config\AppConfig;
use FormularioDorado\Http\Kernel;
use FormularioDorado\Http\Request;
use FormularioDorado\Http\Response;
use FormularioDorado\Security\FieldCipher;
use FormularioDorado\Security\PasswordHasher;
use FormularioDorado\Security\TotpService;
use OTPHP\TOTP;

final class AppHarness
{
    public const string PASSWORD = 'correct-horse-battery';

    public readonly AppConfig $config;
    public readonly FrozenClock $clock;
    public readonly ArraySessionStore $session;
    public readonly InMemorySubmissionRepository $submissions;
    public readonly InMemoryAdminRepository $admins;
    public readonly InMemoryAuditLog $audit;
    public readonly RecordingNotifier $notifier;
    public readonly FieldCipher $cipher;
    public readonly Kernel $kernel;
    public string $totpSecret = '';

    public function __construct()
    {
        $this->config = TestConfig::create();
        $this->clock = new FrozenClock(1_800_000_000);
        $this->session = new ArraySessionStore();
        $this->submissions = new InMemorySubmissionRepository();
        $this->admins = new InMemoryAdminRepository();
        $this->audit = new InMemoryAuditLog();
        $this->notifier = new RecordingNotifier();
        $this->cipher = new FieldCipher($this->config->dataKeys, $this->config->activeDataKeyId);
        $this->kernel = Application::compose(
            $this->config,
            $this->submissions,
            $this->admins,
            $this->audit,
            new InMemoryRateLimiter($this->clock),
            $this->notifier,
            $this->session,
            $this->clock,
        );
    }

    public function seedAdmin(string $username = 'admin'): void
    {
        $this->totpSecret = (new TotpService($this->clock))->generateSecret();
        $this->admins->create(
            $username,
            (new PasswordHasher())->hash(self::PASSWORD),
            $this->cipher->encrypt($this->totpSecret, AdminAuthenticator::totpContext($username)),
        );
    }

    public function currentCode(): string
    {
        return TOTP::createFromSecret($this->totpSecret, $this->clock)->at($this->clock->now()->getTimestamp());
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->kernel->handle(new Request('GET', $path, $query, [], [], '203.0.113.10'));
    }

    public function post(string $path, array $form = [], array $headers = [], ?string $ip = null): Response
    {
        $this->get('/');
        $form += ['csrf_token' => (string) $this->session->get('csrf_token')];

        return $this->kernel->handle(new Request('POST', $path, [], $form, $headers, $ip ?? '203.0.113.10'));
    }

    public function signIn(): void
    {
        $this->post('/admin/login', ['username' => 'admin', 'password' => self::PASSWORD]);
        $this->post('/admin/verify', ['code' => $this->currentCode()]);
    }
}
