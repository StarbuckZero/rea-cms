<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

final class WebhookDelivery
{
    /** @var callable(string, array<string, string>, string, int, int): array{status: int, body: string} */
    private $send;

    /** @param callable(string, array<string, string>, string, int, int): array{status: int, body: string} $send */
    public function __construct(
        private readonly DestinationValidator $destinations,
        private readonly WebhookSigner $signer,
        callable $send,
        private readonly int $timeoutSeconds = 5,
        private readonly int $maximumResponseBytes = 65_536,
    ) {
        $this->send = $send;
    }

    /** @return array{status: int, body: string} */
    public function deliver(string $url, string $secret, string $deliveryId, string $body, int $timestamp): array
    {
        $addresses = $this->destinations->validate($url);
        $this->destinations->validateRebinding($url, $addresses);
        $headers = [
            'Content-Type' => 'application/json',
            'X-Rea-Delivery' => $deliveryId,
            'X-Rea-Timestamp' => (string) $timestamp,
            'X-Rea-Signature' => $this->signer->sign($secret, (string) $timestamp, $deliveryId, $body),
        ];
        $response = ($this->send)($url, $headers, $body, $this->timeoutSeconds, $this->maximumResponseBytes);
        if (strlen($response['body']) > $this->maximumResponseBytes) {
            throw new WebhookException('The webhook response exceeded the configured bound.');
        }
        return $response;
    }

    public function retryDelay(int $attempt): int
    {
        return min(3600, max(1, 2 ** max(0, $attempt - 1) * 30));
    }
}
