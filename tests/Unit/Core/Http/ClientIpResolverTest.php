<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Core\Http;

use PHPUnit\Framework\TestCase;
use ReaCms\Core\Http\ClientIpResolver;

final class ClientIpResolverTest extends TestCase
{
    public function testForwardingHeadersAreIgnoredFromUntrustedPeers(): void
    {
        $resolver = new ClientIpResolver();

        self::assertSame('203.0.113.5', $resolver->resolve('203.0.113.5', '10.0.0.1', []));
    }

    public function testFirstForwardedAddressIsAcceptedFromExplicitTrustedProxy(): void
    {
        $resolver = new ClientIpResolver();

        self::assertSame(
            '198.51.100.8',
            $resolver->resolve('10.2.3.4', '198.51.100.8, 10.2.3.3', ['10.0.0.0/8']),
        );
    }
}
