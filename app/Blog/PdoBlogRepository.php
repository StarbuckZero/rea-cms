<?php

declare(strict_types=1);

namespace ReaCms\Blog;

use DateTimeImmutable;
use PDO;

final class PdoBlogRepository implements BlogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function published(string $locale, DateTimeImmutable $now, int $limit, int $offset): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, slug, excerpt, content, status, visibility, locale, publish_at '
            . 'FROM `plugin_blog_posts` WHERE status = :status AND visibility = :visibility AND locale = :locale '
            . 'AND deleted_at IS NULL AND (publish_at IS NULL OR publish_at <= :publish_now) '
            . 'AND (unpublish_at IS NULL OR unpublish_at > :unpublish_now) '
            . 'ORDER BY pinned DESC, publish_at DESC, id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue('status', 'published');
        $statement->bindValue('visibility', 'public');
        $statement->bindValue('locale', $locale);
        $statement->bindValue('publish_now', $now->format('Y-m-d H:i:s.u'));
        $statement->bindValue('unpublish_now', $now->format('Y-m-d H:i:s.u'));
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return array_values(array_map($this->hydrate(...), $statement->fetchAll()));
    }

    public function countPublished(string $locale, DateTimeImmutable $now): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `plugin_blog_posts` WHERE status = :status AND visibility = :visibility '
            . 'AND locale = :locale AND deleted_at IS NULL AND (publish_at IS NULL OR publish_at <= :publish_now) '
            . 'AND (unpublish_at IS NULL OR unpublish_at > :unpublish_now)',
        );
        $statement->execute(['status' => 'published', 'visibility' => 'public', 'locale' => $locale,
            'publish_now' => $now->format('Y-m-d H:i:s.u'),
            'unpublish_now' => $now->format('Y-m-d H:i:s.u')]);
        return (int) $statement->fetchColumn();
    }

    public function findPublishedById(int $id, string $locale, DateTimeImmutable $now): ?BlogPost
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, slug, excerpt, content, status, visibility, locale, publish_at '
            . 'FROM `plugin_blog_posts` WHERE id = :id AND status = :status AND visibility = :visibility '
            . 'AND locale = :locale AND deleted_at IS NULL AND (publish_at IS NULL OR publish_at <= :publish_now) '
            . 'AND (unpublish_at IS NULL OR unpublish_at > :unpublish_now) LIMIT 1',
        );
        $statement->execute(['id' => $id, 'status' => 'published', 'visibility' => 'public', 'locale' => $locale,
            'publish_now' => $now->format('Y-m-d H:i:s.u'),
            'unpublish_now' => $now->format('Y-m-d H:i:s.u')]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findPublishedBySlug(string $slug, string $locale, DateTimeImmutable $now): ?BlogPost
    {
        $statement = $this->pdo->prepare(
            'SELECT id, title, slug, excerpt, content, status, visibility, locale, publish_at '
            . 'FROM `plugin_blog_posts` WHERE slug = :slug AND status = :status AND visibility = :visibility '
            . 'AND locale = :locale AND deleted_at IS NULL AND (publish_at IS NULL OR publish_at <= :publish_now) '
            . 'AND (unpublish_at IS NULL OR unpublish_at > :unpublish_now) LIMIT 1',
        );
        $statement->execute(['slug' => $slug, 'status' => 'published', 'visibility' => 'public',
            'locale' => $locale,
            'publish_now' => $now->format('Y-m-d H:i:s.u'),
            'unpublish_now' => $now->format('Y-m-d H:i:s.u')]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): BlogPost
    {
        return new BlogPost(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['excerpt'],
            (string) $row['content'],
            (string) $row['status'],
            (string) $row['visibility'],
            (string) $row['locale'],
            $row['publish_at'] === null ? null : new DateTimeImmutable((string) $row['publish_at'])
        );
    }
}
