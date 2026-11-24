<?php

declare(strict_types=1);

namespace FormularioDorado\Security;

final readonly class ThrottlePolicy
{
    private const int WINDOW = 900;
    private const int LONG_WINDOW = 3600;

    public function __construct(private RateLimiter $limiter, private string $hashKey)
    {
    }

    public function allowContact(string $ip): bool
    {
        return $this->limiter->hit($this->bucket('contact', IpAddress::rateLimitSubject($ip)), 5, self::WINDOW);
    }

    public function allowLogin(string $ip, string $username): bool
    {
        $network = IpAddress::rateLimitSubject($ip);

        return $this->limiter->hit($this->bucket('login-ip', $network), 20, self::WINDOW)
            && $this->limiter->hit($this->bucket('login-pair', $network . '|' . $username), 5, self::WINDOW)
            && $this->limiter->hit($this->bucket('login-user', $username), 30, self::LONG_WINDOW);
    }

    public function clearLogin(string $ip, string $username): void
    {
        $this->limiter->clear($this->bucket('login-pair', IpAddress::rateLimitSubject($ip) . '|' . $username));
    }

    public function allowTotp(int $adminId): bool
    {
        return $this->limiter->hit($this->bucket('totp', (string) $adminId), 5, self::WINDOW);
    }

    public function clearTotp(int $adminId): void
    {
        $this->limiter->clear($this->bucket('totp', (string) $adminId));
    }

    private function bucket(string $scope, string $subject): string
    {
        return hash_hmac('sha256', $scope . "\0" . $subject, $this->hashKey, true);
    }
}
