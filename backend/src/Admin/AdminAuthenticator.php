<?php

declare(strict_types=1);

namespace FormularioDorado\Admin;

use FormularioDorado\Security\CsrfGuard;
use FormularioDorado\Security\FieldCipher;
use FormularioDorado\Security\PasswordHasher;
use FormularioDorado\Security\SessionStore;
use FormularioDorado\Security\ThrottlePolicy;
use FormularioDorado\Security\TotpService;
use Psr\Clock\ClockInterface;

final readonly class AdminAuthenticator
{
    public const int PENDING_TTL = 300;
    public const int IDLE_TTL = 900;
    public const int ABSOLUTE_TTL = 28800;

    private const string PENDING_ID = 'pending_admin_id';
    private const string PENDING_AT = 'pending_admin_at';
    private const string ADMIN_ID = 'admin_id';
    private const string STARTED_AT = 'admin_started_at';
    private const string SEEN_AT = 'admin_seen_at';

    public function __construct(
        private AdminRepository $admins,
        private PasswordHasher $hasher,
        private TotpService $totp,
        private FieldCipher $cipher,
        private SessionStore $session,
        private CsrfGuard $csrf,
        private ThrottlePolicy $throttle,
        private AuditLog $audit,
        private ClockInterface $clock,
    ) {
    }

    public static function totpContext(string $username): string
    {
        return 'admin-totp:' . $username;
    }

    public function attemptPassword(string $username, string $password, string $ip): AuthOutcome
    {
        $normalized = strtolower(trim($username));
        if (!$this->throttle->allowLogin($ip, $normalized)) {
            return AuthOutcome::Throttled;
        }

        $admin = preg_match('/^[a-z0-9._-]{3,64}$/D', $normalized) === 1
            ? $this->admins->findByUsername($normalized)
            : null;

        if ($admin === null) {
            $this->hasher->verifyDecoy($password);

            return AuthOutcome::Invalid;
        }
        if (!$this->hasher->verify($password, $admin->passwordHash)) {
            return AuthOutcome::Invalid;
        }

        if ($this->hasher->needsRehash($admin->passwordHash)) {
            $this->admins->updatePasswordHash($admin->id, $this->hasher->hash($password));
        }

        $this->throttle->clearLogin($ip, $normalized);
        $this->session->regenerate();
        $this->csrf->rotate();
        $this->session->set(self::PENDING_ID, $admin->id);
        $this->session->set(self::PENDING_AT, $this->now());

        return AuthOutcome::Success;
    }

    public function hasPendingLogin(): bool
    {
        return $this->pendingAdminId() !== null;
    }

    public function attemptTotp(string $code): AuthOutcome
    {
        $adminId = $this->pendingAdminId();
        if ($adminId === null) {
            return AuthOutcome::Invalid;
        }
        if (!$this->throttle->allowTotp($adminId)) {
            return AuthOutcome::Throttled;
        }

        $admin = $this->admins->findById($adminId);
        if ($admin === null) {
            return AuthOutcome::Invalid;
        }

        $secret = $this->cipher->decrypt($admin->totpSecretCiphertext, self::totpContext($admin->username));
        $step = $this->totp->matchStep($secret, trim($code), $admin->lastTotpStep);
        if ($step === null || !$this->admins->claimTotpStep($admin->id, $step)) {
            return AuthOutcome::Invalid;
        }

        $this->throttle->clearTotp($admin->id);
        $this->session->remove(self::PENDING_ID);
        $this->session->remove(self::PENDING_AT);
        $this->session->regenerate();
        $this->csrf->rotate();
        $now = $this->now();
        $this->session->set(self::ADMIN_ID, $admin->id);
        $this->session->set(self::STARTED_AT, $now);
        $this->session->set(self::SEEN_AT, $now);
        $this->audit->record($admin->id, 'login', null);

        return AuthOutcome::Success;
    }

    public function currentAdminId(): ?int
    {
        $adminId = $this->session->get(self::ADMIN_ID);
        $startedAt = $this->session->get(self::STARTED_AT);
        $seenAt = $this->session->get(self::SEEN_AT);
        if (!is_int($adminId) || !is_int($startedAt) || !is_int($seenAt)) {
            return null;
        }

        $now = $this->now();
        if ($now - $seenAt > self::IDLE_TTL || $now - $startedAt > self::ABSOLUTE_TTL) {
            $this->endSession();

            return null;
        }

        $this->session->set(self::SEEN_AT, $now);

        return $adminId;
    }

    public function logout(): void
    {
        $adminId = $this->currentAdminId();
        if ($adminId !== null) {
            $this->audit->record($adminId, 'logout', null);
        }
        $this->endSession();
    }

    private function pendingAdminId(): ?int
    {
        $adminId = $this->session->get(self::PENDING_ID);
        $startedAt = $this->session->get(self::PENDING_AT);
        if (!is_int($adminId) || !is_int($startedAt)) {
            return null;
        }
        if ($this->now() - $startedAt > self::PENDING_TTL) {
            $this->session->remove(self::PENDING_ID);
            $this->session->remove(self::PENDING_AT);

            return null;
        }

        return $adminId;
    }

    private function endSession(): void
    {
        $this->session->destroy();
        $this->csrf->rotate();
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
