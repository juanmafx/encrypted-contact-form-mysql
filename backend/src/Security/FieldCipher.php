<?php

declare(strict_types=1);

namespace FormularioDorado\Security;

use InvalidArgumentException;

final readonly class FieldCipher
{
    public function __construct(private array $keys, private string $activeKeyId)
    {
        if (!isset($keys[$activeKeyId])) {
            throw new InvalidArgumentException('The active key is not configured.');
        }
    }

    public function encrypt(string $plaintext, string $context): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $this->associatedData($this->activeKeyId, $context),
            $nonce,
            $this->keys[$this->activeKeyId],
        );

        return $this->activeKeyId . ':' . sodium_bin2base64($nonce . $ciphertext, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    public function decrypt(string $envelope, string $context): string
    {
        $parts = explode(':', $envelope, 2);
        if (count($parts) !== 2 || !isset($this->keys[$parts[0]])) {
            throw new DecryptionFailed('Unknown encryption key.');
        }

        try {
            $payload = sodium_base642bin($parts[1], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException) {
            throw new DecryptionFailed('Malformed ciphertext.');
        }

        if (strlen($payload) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw new DecryptionFailed('Malformed ciphertext.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($payload, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES),
            $this->associatedData($parts[0], $context),
            substr($payload, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES),
            $this->keys[$parts[0]],
        );
        if ($plaintext === false) {
            throw new DecryptionFailed('Ciphertext authentication failed.');
        }

        return $plaintext;
    }

    private function associatedData(string $keyId, string $context): string
    {
        return 'formulario-dorado:v1:' . $keyId . ':' . $context;
    }
}
