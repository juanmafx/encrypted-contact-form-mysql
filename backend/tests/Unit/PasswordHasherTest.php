<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Security\PasswordHasher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashUsesArgon2idWithExpectedCost(): void
    {
        $info = password_get_info((new PasswordHasher())->hash('a-long-enough-password'));

        self::assertSame('argon2id', $info['algoName']);
        self::assertSame(65536, $info['options']['memory_cost']);
        self::assertSame(3, $info['options']['time_cost']);
    }

    public function testHashesAreSaltedAndVerify(): void
    {
        $hasher = new PasswordHasher();
        $first = $hasher->hash('a-long-enough-password');
        $second = $hasher->hash('a-long-enough-password');

        self::assertNotSame($first, $second);
        self::assertTrue($hasher->verify('a-long-enough-password', $first));
        self::assertFalse($hasher->verify('another-long-password', $first));
    }

    public function testShortPasswordIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PasswordHasher())->hash('short');
    }

    public function testOversizedPasswordIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PasswordHasher())->hash(str_repeat('a', PasswordHasher::MAX_LENGTH + 1));
    }

    public function testWeakerHashNeedsRehash(): void
    {
        $weak = password_hash('a-long-enough-password', PASSWORD_ARGON2ID, ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);

        self::assertTrue((new PasswordHasher())->needsRehash($weak));
    }
}
