<?php

declare(strict_types=1);

namespace ReaCms\Gallery;

final class GalleryApiPresenter
{
    public const DEFAULT_ALBUM_COVER = '/assets/gallery-default-album-cover.svg';

    /** @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    public function item(array $item): array
    {
        $albumId = (int) ($item['album_id'] ?? 0);
        $mediaType = self::mediaType((string) ($item['mime_type'] ?? ''))
            ?? (string) ($item['media_type'] ?? '');
        $url = '/media/' . (int) $item['media_id'];

        return [
            'id' => (int) $item['id'],
            'albumId' => $albumId > 0 ? $albumId : null,
            'title' => (string) ($item['title'] ?? ''),
            'caption' => (string) ($item['caption'] ?? ''),
            'altText' => (string) ($item['alt_text'] ?? ''),
            'mediaType' => $mediaType,
            'mimeType' => (string) ($item['mime_type'] ?? ''),
            'media' => $url,
            'image' => $url,
            'displayOrder' => (int) ($item['position'] ?? 0),
            'createdAt' => $item['created_at'] ?? null,
            'updatedAt' => $item['updated_at'] ?? null,
        ];
    }

    /** @param array<string, mixed> $album
     * @return array<string, mixed>
     */
    public function album(array $album): array
    {
        $hasPublicImage = str_starts_with((string) ($album['cover_mime_type'] ?? ''), 'image/')
            && ($album['cover_visibility'] ?? '') === 'public';
        $cover = $hasPublicImage
            ? '/media/' . (int) $album['cover_media_id']
            : self::DEFAULT_ALBUM_COVER;
        $id = (int) $album['id'];

        return [
            'id' => $id,
            'title' => (string) $album['title'],
            'name' => (string) $album['title'],
            'slug' => (string) $album['slug'],
            'description' => (string) $album['description'],
            'cover' => $cover,
            'itemCount' => (int) $album['active_item_count'],
            'createdAt' => $album['created_at'],
            'updatedAt' => $album['updated_at'],
            'status' => (string) $album['status'],
            'active' => ($album['status'] ?? '') === 'published',
            'links' => [
                'self' => '/api/v1/gallery/albums/' . $id . '.json',
                'items' => '/api/v1/gallery/albums/' . $id . '/items.json',
            ],
        ];
    }

    public static function mediaType(string $mimeType): ?string
    {
        return str_starts_with($mimeType, 'image/') ? 'image'
            : (str_starts_with($mimeType, 'video/') ? 'video' : null);
    }
}
