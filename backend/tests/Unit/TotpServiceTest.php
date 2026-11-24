<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Security\TotpService;
use FormularioDorado\Tests\Support\FrozenClock;
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use PHPUnit\Framework\TestCase;

final class TotpServiceTest extends TestCase
{
    private const string RFC_6238_SEED = '12345678901234567890';

    private static function rfcSecret(): string
    {
        return Base32::encodeUpper(self::RFC_6238_SEED);
    }

    public function testMatchesRfc6238VectorTruncatedToSixDigits(): void
    {
        $service = new TotpService(new FrozenClock(59));

        self::assertSame(1, $service->matchStep(self::rfcSecret(), '287082', null));
    }

    public function testRejectsWrongAndMalformedCodes(): void
    {
        $service = new TotpService(new FrozenClock(59));

        self::assertNull($service->matchStep(self::rfcSecret(), '000000', null));
        self::assertNull($service->matchStep(self::rfcSecret(), '28708', null));
        self::assertNull($service->matchStep(self::rfcSecret(), "287082\n", null));
        self::assertNull($service->matchStep(self::rfcSecret(), 'abcdef', null));
    }

    public function testRejectsReplayOfUsedStep(): void
    {
        $service = new TotpService(new FrozenClock(59));

        self::assertNull($service->matchStep(self::rfcSecret(), '287082', 1));
    }

    public function testAcceptsOneStepOfClockDriftOnly(): void
    {
        $clock = new FrozenClock(1_800_000_000);
        $service = new TotpService($clock);
        $totp = TOTP::createFromSecret(self::rfcSecret(), $clock);
        $step = intdiv(1_800_000_000, 30);

        self::assertSame($step - 1, $service->matchStep(self::rfcSecret(), $totp->at(($step - 1) * 30), null));
        self::assertSame($step + 1, $service->matchStep(self::rfcSecret(), $totp->at(($step + 1) * 30), null));
        self::assertNull($service->matchStep(self::rfcSecret(), $totp->at(($step + 2) * 30), null));
    }

    public function testProvisioningUriNamesIssuerAndAccount(): void
    {
        $uri = (new TotpService(new FrozenClock(59)))->provisioningUri(self::rfcSecret(), 'admin');

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('admin', $uri);
        self::assertStringContainsString('issuer=Formulario', $uri);
    }
}
