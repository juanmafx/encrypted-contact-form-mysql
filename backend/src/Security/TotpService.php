<?php

declare(strict_types=1);

namespace FormularioDorado\Security;

use OTPHP\TOTP;
use Psr\Clock\ClockInterface;

final readonly class TotpService
{
    private const int PERIOD = 30;
    private const string ISSUER = 'Formulario Dorado';

    public function __construct(private ClockInterface $clock)
    {
    }

    public function generateSecret(): string
    {
        return TOTP::generate($this->clock)->getSecret();
    }

    public function provisioningUri(string $secret, string $username): string
    {
        $totp = TOTP::createFromSecret($secret, $this->clock);
        $totp->setLabel($username);
        $totp->setIssuer(self::ISSUER);

        return $totp->getProvisioningUri();
    }

    public function matchStep(string $secret, string $code, ?int $lastUsedStep): ?int
    {
        if (preg_match('/^\d{6}$/D', $code) !== 1) {
            return null;
        }

        $totp = TOTP::createFromSecret($secret, $this->clock);
        $currentStep = intdiv($this->clock->now()->getTimestamp(), self::PERIOD);
        $matchedStep = null;

        foreach ([$currentStep - 1, $currentStep, $currentStep + 1] as $step) {
            if ($step < 0 || ($lastUsedStep !== null && $step <= $lastUsedStep)) {
                continue;
            }
            if (hash_equals($totp->at($step * self::PERIOD), $code)) {
                $matchedStep ??= $step;
            }
        }

        return $matchedStep;
    }
}
