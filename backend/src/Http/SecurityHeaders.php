<?php

declare(strict_types=1);

namespace FormularioDorado\Http;

final readonly class SecurityHeaders
{
    private const string CONTENT_SECURITY_POLICY = "default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'";

    public function __construct(private bool $strictTransport)
    {
    }

    public function apply(Response $response): Response
    {
        $headers = [
            'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control' => 'no-store',
        ];
        if ($this->strictTransport) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $response->withHeaders($headers);
    }
}
