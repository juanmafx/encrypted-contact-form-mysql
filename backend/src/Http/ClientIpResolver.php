<?php

declare(strict_types=1);

namespace FormularioDorado\Http;

final readonly class ClientIpResolver
{
    public function __construct(private array $trustedProxies)
    {
    }

    public function resolve(Request $request): string
    {
        $remote = $request->remoteAddress;
        if (!in_array($remote, $this->trustedProxies, true)) {
            return $remote;
        }

        $chain = array_reverse(array_map('trim', explode(',', (string) $request->header('x-forwarded-for'))));
        foreach ($chain as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                return $remote;
            }
            if (!in_array($candidate, $this->trustedProxies, true)) {
                return $candidate;
            }
        }

        return $remote;
    }
}
