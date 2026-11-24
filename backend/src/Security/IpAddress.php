<?php

declare(strict_types=1);

namespace FormularioDorado\Security;

final class IpAddress
{
    private const string IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    public static function canonical(string $address): ?string
    {
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $packed = (string) inet_pton($address);

        return str_starts_with($packed, self::IPV4_MAPPED_PREFIX) ? substr($packed, 12) : $packed;
    }

    public static function rateLimitSubject(string $address): string
    {
        $canonical = self::canonical($address);
        if ($canonical === null) {
            return $address;
        }

        return bin2hex(strlen($canonical) === 16 ? substr($canonical, 0, 8) : $canonical);
    }
}
