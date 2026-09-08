<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

use PDO;
use Throwable;

/** Records content and its notifications on the same connection and transaction. */
final class ContentWebhookRecorder
{
    public function __construct(private readonly PDO $pdo, private readonly PdoWebhookRepository $hooks)
    {
    }

    /** @template T
     * @param callable(): T $operation
     * @return T
     */
    public function transaction(callable $operation): mixed
    {
        $owner = !$this->pdo->inTransaction();
        if ($owner) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if ($owner) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($owner) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    public function snapshot(string $resource, ?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $table = match ($resource) {
            'blog.post' => 'plugin_blog_posts',
            'text_block.block' => 'plugin_text_block_blocks',
            'gallery.album' => 'plugin_gallery_albums',
            'gallery.item' => 'plugin_gallery_items',
            default => throw new WebhookException('Unknown webhook resource.'),
        };
        $statement = $this->pdo->prepare('SELECT * FROM `' . $table . '` WHERE id=:id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) && ($row['deleted_at'] ?? null) === null ? $row : null;
    }

    /** @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function change(string $resource, ?array $before, ?array $after, ?string $action = null): void
    {
        if ($before === null && $after === null) {
            return;
        }
        $action ??= $before === null ? 'created' : ($after === null ? 'deleted' : 'updated');
        $data = ['resource' => $resource, 'id' => (int) ($after['id'] ?? $before['id'] ?? 0),
            'before' => WebhookEvents::identifiers($before), 'after' => WebhookEvents::identifiers($after)];
        $this->hooks->emit($resource . '.' . $action, $data);
        if (in_array($resource, ['blog.post', 'gallery.album'], true)) {
            $wasPublic = $this->published($before);
            $isPublic = $this->published($after);
            if ($wasPublic !== $isPublic) {
                $this->hooks->emit($resource . ($isPublic ? '.published' : '.unpublished'), $data);
            }
        }
    }

    /** @param array<string, mixed>|null $row */
    private function published(?array $row): bool
    {
        return $row !== null && ($row['status'] ?? '') === 'published'
            && ($row['visibility'] ?? 'public') === 'public';
    }
}
