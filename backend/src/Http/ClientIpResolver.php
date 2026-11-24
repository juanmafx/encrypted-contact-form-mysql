<?php

declare(strict_types=1);

namespace FormularioDorado\Http;

use FormularioDorado\Security\IpAddress;

final readonly class ClientIpResolver
{
    public function __construct(private array $trustedProxies)
    {
    }

    public function resolve(Request $request): string
    {
        $remote = $request->remoteAddress;
        if (!$this->isTrusted($remote)) {
            return $remote;
        }

        $chain = array_reverse(array_map('trim', explode(',', (string) $request->header('x-forwarded-for'))));
        foreach ($chain as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                return $remote;
            }
            if (!$this->isTrusted($candidate)) {
                return $candidate;
            }
        }

        return $remote;
    }

    private function isTrusted(string $address): bool
    {
        $canonical = IpAddress::canonical($address);
        if ($canonical === null) {
            return false;
        }

        foreach ($this->trustedProxies as $proxy) {
            if (IpAddress::canonical($proxy) === $canonical) {
                return true;
            }
        }

        return false;
    }
}
