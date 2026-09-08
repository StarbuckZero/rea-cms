<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

use PDO;
use ReaCms\Jobs\JobQueue;
use ReaCms\Security\SecretCipher;

final class PdoWebhookRepository
{
    private string $hooks;
    private string $deliveries;

    public function __construct(
        private readonly PDO $pdo,
        private readonly JobQueue $queue,
        private readonly SecretCipher $cipher,
        string $prefix = 'rea_',
    ) {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $prefix) !== 1) {
            throw new WebhookException('The database table prefix is invalid.');
        }
        $this->hooks = $prefix . 'webhooks';
        $this->deliveries = $prefix . 'webhook_deliveries';
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->rows('SELECT id, name, url, events_json, status FROM `' . $this->hooks . '` ORDER BY id DESC');
    }

    /** @param list<string> $events
     * @return array{id: int, secret: string}
     */
    public function create(string $name, string $url, array $events, DestinationValidator $validator): array
    {
        if ($name === '' || strlen($name) > 191 || strlen($url) > 2048) {
            throw new WebhookException('Enter a name (up to 191 bytes) and a valid HTTPS URL.');
        }
        $this->validateEvents($events);
        $validator->validate($url);
        $secret = bin2hex(random_bytes(32));
        $statement = $this->pdo->prepare('INSERT INTO `' . $this->hooks . '` '
            . '(name, url, secret_ciphertext, events_json) VALUES (:name, :url, :secret, :events)');
        $statement->execute(['name' => $name, 'url' => $url, 'secret' => $this->cipher->encrypt($secret),
            'events' => json_encode(array_values(array_unique($events)), JSON_THROW_ON_ERROR)]);
        return ['id' => (int) $this->pdo->lastInsertId(), 'secret' => $secret];
    }

    /** Destinations and secrets are immutable; create a new hook to replace them.
     * @param list<string> $events
     */
    public function configure(int $id, array $events, bool $active): void
    {
        $this->validateEvents($events);
        $statement = $this->pdo->prepare('UPDATE `' . $this->hooks . '` '
            . 'SET events_json=:events, status=:status WHERE id=:id');
        $statement->execute(['id' => $id,
            'events' => json_encode(array_values(array_unique($events)), JSON_THROW_ON_ERROR),
            'status' => $active ? 'active' : 'disabled']);
    }

    /** @param array<string, mixed> $data */
    public function emit(string $event, array $data): void
    {
        if (!in_array($event, WebhookEvents::all(), true)) {
            throw new WebhookException('Unknown webhook event.');
        }
        if (!$this->pdo->inTransaction()) {
            throw new WebhookException('Content events must be recorded inside the content transaction.');
        }
        $eventId = bin2hex(random_bytes(16));
        foreach ($this->rows('SELECT id, events_json FROM `' . $this->hooks . "` WHERE status='active'") as $hook) {
            $events = json_decode((string) $hook['events_json'], true, flags: JSON_THROW_ON_ERROR);
            if (is_array($events) && in_array($event, $events, true)) {
                $this->enqueue((int) $hook['id'], $event, $data, $eventId);
            }
        }
    }

    public function test(int $id): void
    {
        $this->atomic(function () use ($id): void {
            $hook = $this->rows(
                'SELECT id FROM `' . $this->hooks . "` WHERE id=:id AND status='active'",
                ['id' => $id]
            );
            if ($hook === []) {
                throw new WebhookException('Enable the webhook before sending a test.');
            }
            $this->enqueue(
                $id,
                'webhook.test',
                ['message' => 'REA webhook connection test.'],
                bin2hex(random_bytes(16))
            );
        });
    }

    /** @return list<array<string, mixed>> */
    public function history(): array
    {
        return $this->rows('SELECT d.delivery_id, d.webhook_id, h.name, d.event_type, d.attempts, '
            . 'd.response_status, d.delivery_status, d.created_at, d.delivered_at '
            . 'FROM `' . $this->deliveries . '` d JOIN `' . $this->hooks . '` h ON h.id=d.webhook_id '
            . 'ORDER BY d.created_at DESC, d.delivery_id DESC LIMIT 100');
    }

    /** @return array<string, mixed>|null */
    public function delivery(string $id): ?array
    {
        return $this->rows('SELECT d.*, h.url, h.secret_ciphertext, h.status AS hook_status '
            . 'FROM `' . $this->deliveries . '` d JOIN `' . $this->hooks . '` h ON h.id=d.webhook_id '
            . 'WHERE d.delivery_id=:id', ['id' => $id])[0] ?? null;
    }

    public function secret(string $ciphertext): string
    {
        return $this->cipher->decrypt($ciphertext);
    }

    public function attempted(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE `' . $this->deliveries . '` '
            . "SET attempts=attempts+1, delivery_status='delivering' WHERE delivery_id=:id");
        $statement->execute(['id' => $id]);
    }

    public function result(string $id, string $status, ?int $httpStatus = null): void
    {
        $statement = $this->pdo->prepare('UPDATE `' . $this->deliveries . '` '
            . 'SET delivery_status=:status, response_status=:http, '
            . 'delivered_at=' . ($status === 'delivered' ? 'UTC_TIMESTAMP(6)' : 'NULL') . ' WHERE delivery_id=:id');
        $statement->execute(['id' => $id, 'status' => $status, 'http' => $httpStatus]);
    }

    public function retry(string $id): void
    {
        $this->atomic(function () use ($id): void {
            $statement = $this->pdo->prepare('UPDATE `' . $this->deliveries . '` '
                . "SET delivery_status='pending' WHERE delivery_id=:id AND delivery_status IN ('failed', 'cancelled') "
                . 'AND webhook_id IN (SELECT id FROM `' . $this->hooks . "` WHERE status='active')");
            $statement->execute(['id' => $id]);
            if ($statement->rowCount() !== 1) {
                throw new WebhookException('Only failed or cancelled deliveries for an active webhook can be retried.');
            }
            $this->queue->push('webhooks', 'webhook.deliver', ['delivery_id' => $id], $id);
        });
    }

    /** @param array<string, mixed> $data */
    private function enqueue(int $hookId, string $event, array $data, string $eventId): void
    {
        $id = bin2hex(random_bytes(16));
        $payload = json_encode(['version' => 1, 'event_id' => $eventId, 'event' => $event,
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'), 'data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $statement = $this->pdo->prepare('INSERT INTO `' . $this->deliveries . '` '
            . '(delivery_id, webhook_id, event_type, payload_json) VALUES (:id, :hook, :event, :payload)');
        $statement->execute(['id' => $id, 'hook' => $hookId, 'event' => $event, 'payload' => $payload]);
        $this->queue->push('webhooks', 'webhook.deliver', ['delivery_id' => $id], $id);
    }

    /** @param list<string> $events */
    private function validateEvents(array $events): void
    {
        if ($events === [] || array_diff($events, WebhookEvents::all()) !== []) {
            throw new WebhookException('Select at least one supported content event.');
        }
    }

    /** @param callable(): void $operation */
    public function atomic(callable $operation): void
    {
        (new ContentWebhookRecorder($this->pdo, $this))->transaction($operation);
    }

    /** @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
