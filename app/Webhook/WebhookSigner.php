<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

final class WebhookSigner
{
    public function sign(string $secret, string $timestamp, string $deliveryId, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $deliveryId . '.' . $body, $secret);
    }

    public function verify(
        string $secret,
        string $timestamp,
        string $deliveryId,
        string $body,
        string $signature,
        int $now,
        int $maximumAge = 300,
    ): bool {
        $sentAt = filter_var($timestamp, FILTER_VALIDATE_INT);
        return is_int($sentAt) && abs($now - $sentAt) <= $maximumAge
            && preg_match('/^[a-f0-9]{64}$/D', $signature) === 1
            && hash_equals($this->sign($secret, $timestamp, $deliveryId, $body), $signature);
    }
}
