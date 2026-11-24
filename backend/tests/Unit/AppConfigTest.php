<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Config\ConfigurationError;
use FormularioDorado\Tests\Support\TestConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppConfigTest extends TestCase
{
    public function testValidConfigurationLoads(): void
    {
        $config = TestConfig::create(['APP_URL' => 'https://Forms.Example.test:8443/']);

        self::assertSame('https://forms.example.test:8443', $config->origin());
        self::assertFalse($config->mailIncludesPersonalData);
        self::assertSame(365, $config->retentionDays);
    }

    public function testDefaultPortIsOmittedFromOrigin(): void
    {
        self::assertSame('https://forms.example.test', TestConfig::create(['APP_URL' => 'https://forms.example.test:443'])->origin());
        self::assertSame('http://forms.example.test', TestConfig::create(['APP_URL' => 'http://forms.example.test:80'])->origin());
    }

    #[DataProvider('invalidEnvironments')]
    public function testInvalidConfigurationFailsClosed(array $overrides): void
    {
        $this->expectException(ConfigurationError::class);
        TestConfig::create($overrides);
    }

    public static function invalidEnvironments(): array
    {
        return [
            'unknown environment' => [['APP_ENV' => 'staging']],
            'http in production' => [['APP_ENV' => 'production', 'APP_URL' => 'http://forms.example.test']],
            'plain SMTP in production' => [['APP_ENV' => 'production', 'SMTP_ENCRYPTION' => 'none']],
            'short data key' => [['DATA_ENCRYPTION_KEYS' => 'v1:' . base64_encode('short')]],
            'malformed key entry' => [['DATA_ENCRYPTION_KEYS' => 'nokeyseparator']],
            'active key missing' => [['DATA_ENCRYPTION_ACTIVE_KEY' => 'v2']],
            'short IP hash key' => [['IP_HASH_KEY' => base64_encode('short')]],
            'invalid recipient' => [['MAIL_TO' => 'nope']],
            'invalid proxy' => [['TRUSTED_PROXIES' => 'proxy.local']],
            'invalid retention' => [['RETENTION_DAYS' => '0']],
            'invalid port' => [['SMTP_PORT' => '70000']],
            'URL with path' => [['APP_URL' => 'https://forms.example.test/contact']],
            'URL with credentials' => [['APP_URL' => 'https://user:secret@forms.example.test']],
            'URL with query' => [['APP_URL' => 'https://forms.example.test?next=1']],
        ];
    }
}
