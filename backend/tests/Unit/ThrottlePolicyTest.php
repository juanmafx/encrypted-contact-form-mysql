<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Security\ThrottlePolicy;
use FormularioDorado\Tests\Support\FrozenClock;
use FormularioDorado\Tests\Support\InMemoryRateLimiter;
use PHPUnit\Framework\TestCase;

final class ThrottlePolicyTest extends TestCase
{
    public function testContactLimitAppliesPerAddressAndResetsAfterWindow(): void
    {
        $clock = new FrozenClock(1000);
        $policy = new ThrottlePolicy(new InMemoryRateLimiter($clock), random_bytes(32));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertTrue($policy->allowContact('192.0.2.1'));
        }
        self::assertFalse($policy->allowContact('192.0.2.1'));
        self::assertTrue($policy->allowContact('192.0.2.2'));

        $clock->advance(901);
        self::assertTrue($policy->allowContact('192.0.2.1'));
    }

    public function testLoginPairLimitAndClear(): void
    {
        $policy = new ThrottlePolicy(new InMemoryRateLimiter(new FrozenClock(1000)), random_bytes(32));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertTrue($policy->allowLogin('192.0.2.1', 'admin'));
        }
        self::assertFalse($policy->allowLogin('192.0.2.1', 'admin'));

        $policy->clearLogin('192.0.2.1', 'admin');
        self::assertTrue($policy->allowLogin('192.0.2.1', 'admin'));
    }

    public function testIpv6AddressesInOneNetworkShareABucket(): void
    {
        $policy = new ThrottlePolicy(new InMemoryRateLimiter(new FrozenClock(1000)), random_bytes(32));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            self::assertTrue($policy->allowContact('2001:db8:1:1::' . dechex($attempt)));
        }

        self::assertFalse($policy->allowContact('2001:db8:1:1:ffff::1'));
        self::assertTrue($policy->allowContact('2001:db8:1:2::1'));
    }

    public function testIpv4MappedAddressSharesBucketWithIpv4(): void
    {
        $policy = new ThrottlePolicy(new InMemoryRateLimiter(new FrozenClock(1000)), random_bytes(32));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertTrue($policy->allowContact('192.0.2.1'));
        }

        self::assertFalse($policy->allowContact('::ffff:192.0.2.1'));
    }

    public function testBlockedAddressCannotExhaustTheUsernameBudget(): void
    {
        $policy = new ThrottlePolicy(new InMemoryRateLimiter(new FrozenClock(1000)), random_bytes(32));

        for ($attempt = 0; $attempt < 40; $attempt++) {
            $policy->allowLogin('192.0.2.1', 'admin');
        }

        self::assertTrue($policy->allowLogin('198.51.100.7', 'admin'));
    }

    public function testTotpLimit(): void
    {
        $policy = new ThrottlePolicy(new InMemoryRateLimiter(new FrozenClock(1000)), random_bytes(32));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            self::assertTrue($policy->allowTotp(1));
        }
        self::assertFalse($policy->allowTotp(1));
        $policy->clearTotp(1);
        self::assertTrue($policy->allowTotp(1));
    }
}
