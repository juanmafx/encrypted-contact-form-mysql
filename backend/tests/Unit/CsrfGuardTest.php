<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Security\CsrfGuard;
use FormularioDorado\Tests\Support\ArraySessionStore;
use PHPUnit\Framework\TestCase;

final class CsrfGuardTest extends TestCase
{
    public function testTokenIsStableAndValid(): void
    {
        $guard = new CsrfGuard(new ArraySessionStore());
        $token = $guard->token();

        self::assertSame(64, strlen($token));
        self::assertSame($token, $guard->token());
        self::assertTrue($guard->isValid($token));
    }

    public function testInvalidTokensAreRejected(): void
    {
        $guard = new CsrfGuard(new ArraySessionStore());
        self::assertFalse($guard->isValid(''));
        $guard->token();
        self::assertFalse($guard->isValid(''));
        self::assertFalse($guard->isValid(str_repeat('0', 64)));
    }

    public function testRotationInvalidatesPreviousToken(): void
    {
        $guard = new CsrfGuard(new ArraySessionStore());
        $old = $guard->token();
        $new = $guard->rotate();

        self::assertNotSame($old, $new);
        self::assertFalse($guard->isValid($old));
    }
}
