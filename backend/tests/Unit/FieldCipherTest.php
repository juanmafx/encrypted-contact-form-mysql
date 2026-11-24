<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Security\DecryptionFailed;
use FormularioDorado\Security\FieldCipher;
use PHPUnit\Framework\TestCase;

final class FieldCipherTest extends TestCase
{
    public function testRoundTripPreservesUnicode(): void
    {
        $cipher = new FieldCipher(['v1' => random_bytes(32)], 'v1');

        self::assertSame('Zoë 日本', $cipher->decrypt($cipher->encrypt('Zoë 日本', 'ctx'), 'ctx'));
    }

    public function testSamePlaintextProducesDifferentCiphertexts(): void
    {
        $cipher = new FieldCipher(['v1' => random_bytes(32)], 'v1');

        self::assertNotSame($cipher->encrypt('same', 'ctx'), $cipher->encrypt('same', 'ctx'));
    }

    public function testCiphertextDoesNotContainPlaintext(): void
    {
        $cipher = new FieldCipher(['v1' => random_bytes(32)], 'v1');

        self::assertStringNotContainsString('secret-value', $cipher->encrypt('secret-value', 'ctx'));
    }

    public function testCiphertextCannotMoveBetweenContexts(): void
    {
        $cipher = new FieldCipher(['v1' => random_bytes(32)], 'v1');
        $envelope = $cipher->encrypt('value', 'submission:a:email');

        $this->expectException(DecryptionFailed::class);
        $cipher->decrypt($envelope, 'submission:b:email');
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $cipher = new FieldCipher(['v1' => random_bytes(32)], 'v1');
        $envelope = $cipher->encrypt('value', 'ctx');
        $last = $envelope[strlen($envelope) - 2];
        $envelope[strlen($envelope) - 2] = $last === 'A' ? 'B' : 'A';

        $this->expectException(DecryptionFailed::class);
        $cipher->decrypt($envelope, 'ctx');
    }

    public function testRotationKeepsOldCiphertextReadable(): void
    {
        $oldKey = random_bytes(32);
        $old = new FieldCipher(['v1' => $oldKey], 'v1');
        $envelope = $old->encrypt('value', 'ctx');
        $rotated = new FieldCipher(['v1' => $oldKey, 'v2' => random_bytes(32)], 'v2');

        self::assertSame('value', $rotated->decrypt($envelope, 'ctx'));
        self::assertStringStartsWith('v2:', $rotated->encrypt('value', 'ctx'));
    }

    public function testUnknownKeyIsRejected(): void
    {
        $cipher = new FieldCipher(['v1' => random_bytes(32)], 'v1');

        $this->expectException(DecryptionFailed::class);
        $cipher->decrypt('v9:AAAA', 'ctx');
    }

    public function testMalformedPayloadIsRejected(): void
    {
        $cipher = new FieldCipher(['v1' => random_bytes(32)], 'v1');

        $this->expectException(DecryptionFailed::class);
        $cipher->decrypt('v1:not base64!', 'ctx');
    }
}
