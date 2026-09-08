<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

use ReaCms\Jobs\JobQueue;
use Throwable;

final class WebhookWorker
{
    public function __construct(
        private readonly JobQueue $queue,
        private readonly PdoWebhookRepository $hooks,
        private readonly WebhookDelivery $sender,
    ) {
    }

    public function runOne(): bool
    {
        $job = $this->queue->reserve('webhooks');
        if ($job === null) {
            return false;
        }
        $id = is_string($job->payload['delivery_id'] ?? null) ? $job->payload['delivery_id'] : '';
        $httpStatus = null;
        try {
            if ($job->type !== 'webhook.deliver' || preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
                throw new WebhookException('Invalid webhook job.');
            }
            $delivery = $this->hooks->delivery($id);
            if ($delivery === null || $delivery['delivery_status'] === 'delivered') {
                $this->queue->complete($job);
                return true;
            }
            if ($delivery['hook_status'] !== 'active') {
                $this->hooks->atomic(function () use ($id, $job): void {
                    $this->hooks->result($id, 'cancelled');
                    $this->queue->complete($job);
                });
                return true;
            }
            if ($job->attempts > $job->maxAttempts) {
                throw new WebhookException('Webhook attempt limit reached.');
            }
            $this->hooks->attempted($id);
            $response = $this->sender->deliver(
                (string) $delivery['url'],
                $this->hooks->secret((string) $delivery['secret_ciphertext']),
                $id,
                (string) $delivery['payload_json'],
                time()
            );
            $httpStatus = $response['status'];
            if ($httpStatus < 200 || $httpStatus >= 300) {
                throw new WebhookException('Webhook returned a non-success status.');
            }
            $this->hooks->atomic(function () use ($id, $job, $httpStatus): void {
                $this->hooks->result($id, 'delivered', $httpStatus);
                $this->queue->complete($job);
            });
        } catch (Throwable) {
            // Keep failure diagnostics secret-safe, including failures in injected senders.
            $this->hooks->atomic(function () use ($id, $job, $httpStatus): void {
                $this->queue->fail($job, 'Webhook delivery failed.');
                if ($id !== '') {
                    $status = $job->attempts >= $job->maxAttempts ? 'failed' : 'retrying';
                    $this->hooks->result($id, $status, $httpStatus);
                }
            });
        }
        return true;
    }
}
