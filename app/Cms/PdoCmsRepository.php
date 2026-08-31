<?php

declare(strict_types=1);

namespace ReaCms\Cms;

use PDO;
use RuntimeException;

final class PdoCmsRepository
{
    private readonly string $media;
    private readonly string $usage;

    public function __construct(private readonly PDO $pdo, string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $prefix) !== 1) {
            throw new RuntimeException('The database table prefix is invalid.');
        }
        $this->media = $prefix . 'media';
        $this->usage = $prefix . 'media_usage';
    }

    /** @return list<array<string, mixed>> */
    public function blogPosts(): array
    {
        return $this->rows('SELECT id, title, slug, excerpt, content, status, visibility, locale, '
            . 'featured_media_id, publish_at, updated_at FROM `plugin_blog_posts` '
            . 'WHERE deleted_at IS NULL ORDER BY updated_at DESC, id DESC');
    }

    /** @return array<string, mixed>|null */
    public function blogPost(int $id): ?array
    {
        return $this->row(
            'SELECT id, title, slug, excerpt, content, status, visibility, locale, '
                . 'featured_media_id, publish_at FROM `plugin_blog_posts` '
                . 'WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id],
        );
    }

    /** @param array<string, mixed> $values */
    public function saveBlog(?int $id, int $authorId, array $values): int
    {
        $params = [...$values, 'author_id' => $authorId];
        if ($id === null) {
            $statement = $this->pdo->prepare('INSERT INTO `plugin_blog_posts` '
                . '(title, slug, excerpt, content, status, locale, visibility, author_id, featured_media_id, '
                . 'seo_title, meta_description, canonical_url, robots, open_graph, structured_data, featured, pinned, '
                . 'position, publish_at, unpublish_at, created_at, updated_at) VALUES '
                . '(:title, :slug, :excerpt, :content, :status, :locale, :visibility, :author_id, :featured_media_id, '
                . "'', '', '', '', '{}', '{}', 0, 0, 0, :publish_at, NULL, NOW(6), NOW(6))");
            $statement->execute($params);
            return (int) $this->pdo->lastInsertId();
        }
        $statement = $this->pdo->prepare('UPDATE `plugin_blog_posts` SET title=:title, slug=:slug, excerpt=:excerpt, '
            . 'content=:content, status=:status, locale=:locale, visibility=:visibility, '
            . 'featured_media_id=:featured_media_id, publish_at=:publish_at, updated_at=NOW(6) WHERE id=:id');
        $statement->execute([...$params, 'id' => $id]);
        return $id;
    }

    public function deleteBlog(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE `plugin_blog_posts` SET deleted_at=NOW(6) WHERE id=:id');
        $statement->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function galleryItems(?int $albumId = null): array
    {
        $sql = 'SELECT items.id, items.album_id, items.media_id, items.media_type, items.title, items.caption, '
            . 'items.alt_text, items.position, items.status, items.created_at, items.updated_at, '
            . 'media.original_name, media.stored_name, media.mime_type, media.visibility '
            . 'FROM `plugin_gallery_items` AS items JOIN `' . $this->media . '` AS media ON media.id=items.media_id '
            . ($albumId === null ? '' : 'WHERE items.album_id=:album_id ')
            . 'ORDER BY items.position, items.id DESC';

        return $this->rows($sql, $albumId === null ? [] : ['album_id' => $albumId]);
    }

    /** @return array<string, mixed>|null */
    public function galleryItem(int $id): ?array
    {
        return $this->row('SELECT items.id, items.album_id, items.media_id, items.media_type, items.title, '
            . 'items.caption, items.alt_text, items.position, items.status, items.created_at, items.updated_at, '
            . 'media.original_name, media.mime_type, media.visibility '
            . 'FROM `plugin_gallery_items` AS items '
            . 'JOIN `' . $this->media . '` AS media ON media.id=items.media_id '
            . 'WHERE items.id=:id', ['id' => $id]);
    }

    /** @param array<string, mixed> $values */
    public function saveGallery(?int $id, array $values): int
    {
        if ($id === null) {
            $statement = $this->pdo->prepare('INSERT INTO `plugin_gallery_items` '
                . '(album_id, media_id, media_type, title, caption, alt_text, position, status, '
                . 'created_at, updated_at) '
                . 'VALUES (:album_id, :media_id, :media_type, :title, :caption, :alt_text, :position, :status, '
                . 'NOW(6), NOW(6))');
            $statement->execute($values);
            $id = (int) $this->pdo->lastInsertId();
        } else {
            $statement = $this->pdo->prepare('UPDATE `plugin_gallery_items` SET album_id=:album_id, '
                . 'media_id=:media_id, media_type=:media_type, title=:title, caption=:caption, '
                . 'alt_text=:alt_text, position=:position, status=:status, updated_at=NOW(6) WHERE id=:id');
            $statement->execute([...$values, 'id' => $id]);
        }
        $this->clearUsage('items', $id);
        $this->recordUsage((int) $values['media_id'], 'gallery', 'items', $id, 'media');
        return $id;
    }

    public function deleteGallery(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->clearUsage('items', $id);
            $delete = $this->pdo->prepare('DELETE FROM `plugin_gallery_items` WHERE id=:id');
            $delete->execute(['id' => $id]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function galleryAlbums(): array
    {
        return $this->rows('SELECT albums.id, albums.title, albums.slug, albums.description, albums.status, '
            . 'albums.cover_media_id, albums.position, albums.created_at, albums.updated_at, '
            . 'cover.mime_type AS cover_mime_type, cover.visibility AS cover_visibility, '
            . 'COUNT(items.id) AS item_count, '
            . "COALESCE(SUM(CASE WHEN items.status='active' AND item_media.visibility='public' "
            . "AND (item_media.mime_type LIKE 'image/%' OR item_media.mime_type LIKE 'video/%') "
            . 'THEN 1 ELSE 0 END), 0) AS active_item_count '
            . 'FROM `plugin_gallery_albums` AS albums '
            . 'LEFT JOIN `' . $this->media . '` AS cover ON cover.id=albums.cover_media_id '
            . 'LEFT JOIN `plugin_gallery_items` AS items ON items.album_id=albums.id '
            . 'LEFT JOIN `' . $this->media . '` AS item_media ON item_media.id=items.media_id '
            . 'GROUP BY albums.id, albums.title, albums.slug, albums.description, albums.status, '
            . 'albums.cover_media_id, albums.position, albums.created_at, albums.updated_at, '
            . 'cover.mime_type, cover.visibility ORDER BY albums.position, albums.created_at DESC, albums.id DESC');
    }

    /** @return array<string, mixed>|null */
    public function galleryAlbum(int $id): ?array
    {
        return $this->row('SELECT albums.id, albums.title, albums.slug, albums.description, albums.status, '
            . 'albums.cover_media_id, albums.position, albums.created_at, albums.updated_at, '
            . 'cover.mime_type AS cover_mime_type, cover.visibility AS cover_visibility, '
            . '(SELECT COUNT(*) FROM `plugin_gallery_items` AS all_items '
            . 'WHERE all_items.album_id=albums.id) AS item_count, '
            . '(SELECT COUNT(*) FROM `plugin_gallery_items` AS active_items '
            . 'JOIN `' . $this->media . '` AS active_media ON active_media.id=active_items.media_id '
            . "WHERE active_items.album_id=albums.id AND active_items.status='active' "
            . "AND active_media.visibility='public' AND (active_media.mime_type LIKE 'image/%' "
            . "OR active_media.mime_type LIKE 'video/%')) AS active_item_count "
            . 'FROM `plugin_gallery_albums` AS albums '
            . 'LEFT JOIN `' . $this->media . '` AS cover ON cover.id=albums.cover_media_id '
            . 'WHERE albums.id=:id', ['id' => $id]);
    }

    /** @param array<string, mixed> $values */
    public function saveGalleryAlbum(?int $id, array $values): int
    {
        if ($id === null) {
            $statement = $this->pdo->prepare('INSERT INTO `plugin_gallery_albums` '
                . '(title, slug, description, status, cover_media_id, position, created_at, updated_at) '
                . 'VALUES (:title, :slug, :description, :status, :cover_media_id, :position, NOW(6), NOW(6))');
            $statement->execute($values);
            $id = (int) $this->pdo->lastInsertId();
        } else {
            $statement = $this->pdo->prepare('UPDATE `plugin_gallery_albums` SET title=:title, slug=:slug, '
                . 'description=:description, status=:status, cover_media_id=:cover_media_id, '
                . 'position=:position, updated_at=NOW(6) WHERE id=:id');
            $statement->execute([...$values, 'id' => $id]);
        }
        $this->clearUsage('albums', $id);
        if (is_int($values['cover_media_id'])) {
            $this->recordUsage($values['cover_media_id'], 'gallery', 'albums', $id, 'cover');
        }
        return $id;
    }

    public function deleteGalleryAlbum(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $unassign = $this->pdo->prepare('UPDATE `plugin_gallery_items` '
                . 'SET album_id=0, updated_at=NOW(6) WHERE album_id=:id');
            $unassign->execute(['id' => $id]);
            $this->clearUsage('albums', $id);
            $delete = $this->pdo->prepare('DELETE FROM `plugin_gallery_albums` WHERE id=:id');
            $delete->execute(['id' => $id]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @param array<int, int> $positions */
    public function reorderGalleryAlbum(int $albumId, array $positions): void
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('UPDATE `plugin_gallery_items` '
                . 'SET position=:position, updated_at=NOW(6) WHERE id=:id AND album_id=:album_id');
            foreach ($positions as $itemId => $position) {
                $statement->execute(['position' => $position, 'id' => $itemId, 'album_id' => $albumId]);
            }
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string, mixed>> */
    public function media(): array
    {
        return $this->rows('SELECT id, stored_name, original_name, mime_type, file_size, '
            . 'alt_text, caption, description '
            . 'FROM `' . $this->media . "` WHERE mime_type LIKE 'image/%' OR mime_type LIKE 'video/%' "
            . 'ORDER BY created_at DESC, id DESC');
    }

    /** @return list<array<string, mixed>> */
    public function images(): array
    {
        return $this->rows('SELECT id, stored_name, original_name, mime_type, file_size, '
            . 'alt_text, caption, description '
            . 'FROM `' . $this->media . "` WHERE mime_type LIKE 'image/%' ORDER BY created_at DESC, id DESC");
    }

    /** @return array<string, mixed>|null */
    public function medium(int $id): ?array
    {
        return $this->row('SELECT id, stored_name, original_name, mime_type, visibility, '
            . 'alt_text, caption, description '
            . 'FROM `' . $this->media . '` WHERE id=:id', ['id' => $id]);
    }

    /** @param array{storedName:string,mime:string,size:int,hash:string,originalName:string} $file */
    public function addMedia(array $file, int $userId, string $altText = ''): int
    {
        $statement = $this->pdo->prepare('INSERT INTO `' . $this->media . '` '
            . '(stored_name, original_name, mime_type, file_size, file_hash, visibility, alt_text, caption, '
            . 'credit, description, created_by) VALUES '
            . "(:stored_name, :original_name, :mime, :size, :hash, 'public', :alt_text, '', '', '', :created_by)");
        $statement->execute(['stored_name' => $file['storedName'], 'original_name' => $file['originalName'],
            'mime' => $file['mime'], 'size' => $file['size'], 'hash' => $file['hash'], 'alt_text' => $altText,
            'created_by' => $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    private function recordUsage(int $mediaId, string $plugin, string $resource, int $contentId, string $field): void
    {
        $delete = $this->pdo->prepare('DELETE FROM `' . $this->usage . '` '
            . 'WHERE plugin_id=:plugin AND resource=:resource AND content_id=:content_id AND field=:field');
        $delete->execute(['plugin' => $plugin, 'resource' => $resource, 'content_id' => $contentId, 'field' => $field]);
        $insert = $this->pdo->prepare('INSERT INTO `' . $this->usage . '` '
            . '(media_id, plugin_id, resource, content_id, field) '
            . 'VALUES (:media_id, :plugin, :resource, :content_id, :field)');
        $insert->execute(['media_id' => $mediaId, 'plugin' => $plugin, 'resource' => $resource,
            'content_id' => $contentId, 'field' => $field]);
    }

    private function clearUsage(string $resource, int $contentId): void
    {
        $statement = $this->pdo->prepare('DELETE FROM `' . $this->usage . '` '
            . "WHERE plugin_id='gallery' AND resource=:resource AND content_id=:content_id");
        $statement->execute(['resource' => $resource, 'content_id' => $contentId]);
    }

    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>|null
     */
    private function row(string $sql, array $parameters = []): ?array
    {
        $statement = $this->pdo->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $parameters = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }
}
