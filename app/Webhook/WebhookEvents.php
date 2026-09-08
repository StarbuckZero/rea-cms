<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

final class WebhookEvents
{
    /** @return list<string> */
    public static function all(): array
    {
        $events = [];
        foreach (['blog.post', 'text_block.block', 'gallery.album', 'gallery.item'] as $resource) {
            foreach (['created', 'updated', 'deleted'] as $action) {
                $events[] = $resource . '.' . $action;
            }
        }
        return [...$events, 'blog.post.published', 'blog.post.unpublished',
            'gallery.album.published', 'gallery.album.unpublished', 'gallery.album.reordered'];
    }

    /** @param array<string, mixed>|null $row
     * @return array<string, mixed>|null
     */
    public static function identifiers(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        return array_intersect_key($row, array_flip([
            'id', 'slug', 'name', 'locale', 'status', 'visibility', 'album_id', 'media_id', 'position',
        ]));
    }
}
