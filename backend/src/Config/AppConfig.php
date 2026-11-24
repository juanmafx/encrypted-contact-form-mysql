<?php

declare(strict_types=1);

namespace FormularioDorado\Config;

final readonly class AppConfig
{
    private const array ENVIRONMENTS = ['production', 'development', 'test'];
    private const array SMTP_ENCRYPTION = ['tls', 'smtps', 'none'];

    public function __construct(
        public string $environment,
        public string $baseUrl,
        public string $databaseDsn,
        public string $databaseUser,
        public string $databasePassword,
        public array $dataKeys,
        public string $activeDataKeyId,
        public string $ipHashKey,
        public string $mailTo,
        public string $mailFrom,
        public string $smtpHost,
        public int $smtpPort,
        public string $smtpUser,
        public string $smtpPassword,
        public string $smtpEncryption,
        public bool $mailIncludesPersonalData,
        public array $trustedProxies,
        public int $retentionDays,
    ) {
    }

    public static function fromEnvironment(array $env): self
    {
        $environment = self::required($env, 'APP_ENV');
        if (!in_array($environment, self::ENVIRONMENTS, true)) {
            throw new ConfigurationError('APP_ENV must be production, development or test.');
        }

        $baseUrl = rtrim(self::required($env, 'APP_URL'), '/');
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true) || parse_url($baseUrl, PHP_URL_HOST) === null) {
            throw new ConfigurationError('APP_URL must be an absolute http or https URL.');
        }
        if ($environment === 'production' && $scheme !== 'https') {
            throw new ConfigurationError('APP_URL must use https in production.');
        }

        $smtpEncryption = (string) ($env['SMTP_ENCRYPTION'] ?? 'tls');
        if (!in_array($smtpEncryption, self::SMTP_ENCRYPTION, true)) {
            throw new ConfigurationError('SMTP_ENCRYPTION must be tls, smtps or none.');
        }
        if ($environment === 'production' && $smtpEncryption === 'none') {
            throw new ConfigurationError('SMTP_ENCRYPTION cannot be none in production.');
        }

        $dataKeys = self::parseDataKeys(self::required($env, 'DATA_ENCRYPTION_KEYS'));
        $activeKeyId = self::required($env, 'DATA_ENCRYPTION_ACTIVE_KEY');
        if (!isset($dataKeys[$activeKeyId])) {
            throw new ConfigurationError('DATA_ENCRYPTION_ACTIVE_KEY must name a configured key.');
        }

        $mailTo = self::requiredEmail($env, 'MAIL_TO');
        $mailFrom = self::requiredEmail($env, 'MAIL_FROM');

        $smtpPort = filter_var($env['SMTP_PORT'] ?? '587', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if ($smtpPort === false) {
            throw new ConfigurationError('SMTP_PORT must be a valid port.');
        }

        $retentionDays = filter_var($env['RETENTION_DAYS'] ?? '365', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 3650],
        ]);
        if ($retentionDays === false) {
            throw new ConfigurationError('RETENTION_DAYS must be between 1 and 3650.');
        }

        return new self(
            $environment,
            $baseUrl,
            self::required($env, 'DB_DSN'),
            self::required($env, 'DB_USER'),
            (string) ($env['DB_PASSWORD'] ?? ''),
            $dataKeys,
            $activeKeyId,
            self::decodeKey(self::required($env, 'IP_HASH_KEY'), 'IP_HASH_KEY'),
            $mailTo,
            $mailFrom,
            self::required($env, 'SMTP_HOST'),
            $smtpPort,
            (string) ($env['SMTP_USER'] ?? ''),
            (string) ($env['SMTP_PASSWORD'] ?? ''),
            $smtpEncryption,
            filter_var($env['MAIL_INCLUDE_PERSONAL_DATA'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            self::parseTrustedProxies((string) ($env['TRUSTED_PROXIES'] ?? '')),
            $retentionDays,
        );
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function usesHttps(): bool
    {
        return str_starts_with($this->baseUrl, 'https://');
    }

    public function origin(): string
    {
        $parts = parse_url($this->baseUrl);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return strtolower(($parts['scheme'] ?? '') . '://' . ($parts['host'] ?? '')) . $port;
    }

    private static function required(array $env, string $name): string
    {
        $value = $env[$name] ?? '';
        if (!is_string($value) || $value === '') {
            throw new ConfigurationError($name . ' is required.');
        }

        return $value;
    }

    private static function requiredEmail(array $env, string $name): string
    {
        $value = self::required($env, $name);
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new ConfigurationError($name . ' must be a valid email address.');
        }

        return $value;
    }

    private static function parseDataKeys(string $raw): array
    {
        $keys = [];
        foreach (explode(',', $raw) as $entry) {
            $parts = explode(':', trim($entry), 2);
            if (count($parts) !== 2 || preg_match('/^[a-z0-9]{1,16}$/D', $parts[0]) !== 1) {
                throw new ConfigurationError('DATA_ENCRYPTION_KEYS must use the format id:base64key.');
            }
            $keys[$parts[0]] = self::decodeKey($parts[1], 'DATA_ENCRYPTION_KEYS');
        }

        return $keys;
    }

    private static function decodeKey(string $encoded, string $name): string
    {
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new ConfigurationError($name . ' must contain base64-encoded 32-byte keys.');
        }

        return $key;
    }

    private static function parseTrustedProxies(string $raw): array
    {
        $proxies = [];
        foreach (array_filter(array_map('trim', explode(',', $raw)), static fn (string $ip): bool => $ip !== '') as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                throw new ConfigurationError('TRUSTED_PROXIES must be a comma-separated list of IP addresses.');
            }
            $proxies[] = $ip;
        }

        return $proxies;
    }
}
