<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Support;

use FormularioDorado\Config\AppConfig;

final class TestConfig
{
    public static function environment(array $overrides = []): array
    {
        return $overrides + [
            'APP_ENV' => 'test',
            'APP_URL' => 'https://forms.example.test',
            'DB_DSN' => 'mysql:host=127.0.0.1;dbname=test',
            'DB_USER' => 'test',
            'DATA_ENCRYPTION_KEYS' => 'v1:' . base64_encode(random_bytes(32)),
            'DATA_ENCRYPTION_ACTIVE_KEY' => 'v1',
            'IP_HASH_KEY' => base64_encode(random_bytes(32)),
            'MAIL_TO' => 'inbox@example.test',
            'MAIL_FROM' => 'noreply@example.test',
            'SMTP_HOST' => 'smtp.example.test',
        ];
    }

    public static function create(array $overrides = []): AppConfig
    {
        return AppConfig::fromEnvironment(self::environment($overrides));
    }
}
