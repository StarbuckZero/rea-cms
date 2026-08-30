<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReaCms\Webhook\DestinationValidator;
use ReaCms\Webhook\WebhookDelivery;
use ReaCms\Webhook\WebhookException;
use ReaCms\Webhook\WebhookSigner;

final class WebhookSecurityTest extends TestCase
{
    #[DataProvider('unsafeDestinations')]
    public function testUnsafeAndInternalDestinationsAreRejected(string $url, string $address): void
    {
        $validator = new DestinationValidator(static fn (): array => [$address]);
        $this->expectException(WebhookException::class);
        $validator->validate($url);
    }

    public static function unsafeDestinations(): iterable
    {
        yield 'plain HTTP' => ['http://example.com/hook', '93.184.216.34'];
        yield 'loopback' => ['https://localhost/hook', '127.0.0.1'];
        yield 'private' => ['https://internal.test/hook', '10.0.0.4'];
        yield 'link local metadata' => ['https://metadata.test/hook', '169.254.169.254'];
        yield 'IPv6 loopback' => ['https://internal.test/hook', '::1'];
    }

    public function testDnsRebindingIsRejected(): void
    {
        $calls = 0;
        $validator = new DestinationValidator(static function () use (&$calls): array {
            return ++$calls === 1 ? ['93.184.216.34'] : ['93.184.216.35'];
        });
        $delivery = new WebhookDelivery(
            $validator,
            new WebhookSigner(),
            static fn (): array => ['status' => 200, 'body' => 'ok'],
        );

        $this->expectException(WebhookException::class);
        $delivery->deliver('https://example.com/hook', 'secret', 'delivery-1', '{}', 1788050000);
    }

    public function testSignaturesBindTimestampDeliveryAndBodyAndExpire(): void
    {
        $signer = new WebhookSigner();
        $signature = $signer->sign('secret', '1000', 'delivery-1', '{"ok":true}');

        self::assertTrue($signer->verify('secret', '1000', 'delivery-1', '{"ok":true}', $signature, 1100));
        self::assertFalse($signer->verify('secret', '1000', 'delivery-2', '{"ok":true}', $signature, 1100));
        self::assertFalse($signer->verify('secret', '1000', 'delivery-1', '{"ok":true}', $signature, 1400));
    }

    public function testResponseSizeIsBoundedAndRetryBackoffIsCapped(): void
    {
        $validator = new DestinationValidator(static fn (): array => ['93.184.216.34']);
        $delivery = new WebhookDelivery(
            $validator,
            new WebhookSigner(),
            static fn (): array => ['status' => 200, 'body' => str_repeat('x', 11)],
            maximumResponseBytes: 10,
        );

        self::assertSame(3600, $delivery->retryDelay(20));
        $this->expectException(WebhookException::class);
        $delivery->deliver('https://example.com/hook', 'secret', 'delivery-1', '{}', 1788050000);
    }
}
