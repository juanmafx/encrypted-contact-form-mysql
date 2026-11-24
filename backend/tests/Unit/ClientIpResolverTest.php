<?php

declare(strict_types=1);

namespace FormularioDorado\Tests\Unit;

use FormularioDorado\Http\ClientIpResolver;
use FormularioDorado\Http\Request;
use PHPUnit\Framework\TestCase;

final class ClientIpResolverTest extends TestCase
{
    private function request(string $remote, string $forwarded): Request
    {
        return new Request('GET', '/', [], [], ['x-forwarded-for' => $forwarded], $remote);
    }

    public function testForwardedHeaderIsIgnoredFromUntrustedPeer(): void
    {
        $resolver = new ClientIpResolver(['10.0.0.1']);

        self::assertSame('198.51.100.9', $resolver->resolve($this->request('198.51.100.9', '203.0.113.1')));
    }

    public function testRightmostUntrustedAddressIsUsed(): void
    {
        $resolver = new ClientIpResolver(['10.0.0.1', '10.0.0.2']);

        self::assertSame('203.0.113.7', $resolver->resolve($this->request('10.0.0.1', '192.0.2.66, 203.0.113.7, 10.0.0.2')));
    }

    public function testMalformedForwardedHeaderFallsBackToPeer(): void
    {
        $resolver = new ClientIpResolver(['10.0.0.1']);

        self::assertSame('10.0.0.1', $resolver->resolve($this->request('10.0.0.1', 'not-an-ip')));
    }
}
