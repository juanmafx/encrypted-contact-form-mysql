<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Security\TotpService;
use FormularioDorado\Tests\Support\FrozenClock;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;

final class TotpServiceTest extends TestCase
{
    private const string RFC_6238_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testMatchesRfc6238VectorTruncatedToSixDigits(): void
    {
        $service = new TotpService(new FrozenClock(59));

        self::assertSame(1, $service->matchStep(self::RFC_6238_SECRET, '287082', null));
    }

    public function testRejectsWrongAndMalformedCodes(): void
    {
        $service = new TotpService(new FrozenClock(59));

        self::assertNull($service->matchStep(self::RFC_6238_SECRET, '000000', null));
        self::assertNull($service->matchStep(self::RFC_6238_SECRET, '28708', null));
        self::assertNull($service->matchStep(self::RFC_6238_SECRET, "287082\n", null));
        self::assertNull($service->matchStep(self::RFC_6238_SECRET, 'abcdef', null));
    }

    public function testRejectsReplayOfUsedStep(): void
    {
        $service = new TotpService(new FrozenClock(59));

        self::assertNull($service->matchStep(self::RFC_6238_SECRET, '287082', 1));
    }

    public function testAcceptsOneStepOfClockDriftOnly(): void
    {
        $clock = new FrozenClock(1_800_000_000);
        $service = new TotpService($clock);
        $totp = TOTP::createFromSecret(self::RFC_6238_SECRET, $clock);
        $step = intdiv(1_800_000_000, 30);

        self::assertSame($step - 1, $service->matchStep(self::RFC_6238_SECRET, $totp->at(($step - 1) * 30), null));
        self::assertSame($step + 1, $service->matchStep(self::RFC_6238_SECRET, $totp->at(($step + 1) * 30), null));
        self::assertNull($service->matchStep(self::RFC_6238_SECRET, $totp->at(($step + 2) * 30), null));
    }

    public function testProvisioningUriNamesIssuerAndAccount(): void
    {
        $uri = (new TotpService(new FrozenClock(59)))->provisioningUri(self::RFC_6238_SECRET, 'admin');

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('admin', $uri);
        self::assertStringContainsString('issuer=Formulario', $uri);
    }
}
