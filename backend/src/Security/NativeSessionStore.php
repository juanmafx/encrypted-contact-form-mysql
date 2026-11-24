<?php

declare(strict_types=1);

namespace FormularioDorado\Security;

use RuntimeException;

final class NativeSessionStore implements SessionStore
{
    public function __construct(private readonly bool $secure)
    {
    }

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        session_regenerate_id(true);
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($this->secure ? '__Host-fd_session' : 'fd_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        if (!session_start(['use_strict_mode' => true, 'cache_limiter' => ''])) {
            throw new RuntimeException('The session could not be started.');
        }
    }
}
