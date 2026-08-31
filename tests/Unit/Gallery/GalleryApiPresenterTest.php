<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Gallery;

use PHPUnit\Framework\TestCase;
use ReaCms\Api\Serialization\SerializerRegistry;
use ReaCms\Gallery\GalleryApiPresenter;

final class GalleryApiPresenterTest extends TestCase
{
    public function testItemResponsesIdentifyImagesAndVideosWhilePreservingTheLegacyImageUrl(): void
    {
        $presenter = new GalleryApiPresenter();
        $image = $presenter->item($this->item('image/webp', 8));
        $video = $presenter->item($this->item('video/mp4', 0));

        self::assertSame('image', $image['mediaType']);
        self::assertSame(8, $image['albumId']);
        self::assertSame('video', $video['mediaType']);
        self::assertNull($video['albumId']);
        self::assertSame('/media/12', $video['media']);
        self::assertSame('/media/12', $video['image']);
    }

    public function testAlbumResponsesAlwaysHaveAUsableCoverAndBrowserMetadata(): void
    {
        $presenter = new GalleryApiPresenter();
        $default = $presenter->album($this->album(null, null));
        $custom = $presenter->album($this->album('image/jpeg', 'public'));

        self::assertSame(GalleryApiPresenter::DEFAULT_ALBUM_COVER, $default['cover']);
        self::assertSame('/media/42', $custom['cover']);
        self::assertSame(3, $custom['itemCount']);
        self::assertTrue($custom['active']);
        self::assertSame('/api/v1/gallery/albums/5/items.json', $custom['links']['items']);
    }

    public function testGalleryDocumentsSerializeToEverySupportedFormat(): void
    {
        $document = ['data' => [(new GalleryApiPresenter())->album($this->album(null, null))]];

        foreach (['json', 'html', 'txt'] as $format) {
            $serializer = (new SerializerRegistry())->get($format);
            self::assertNotNull($serializer);
            $response = $serializer->serialize($document);
            self::assertSame(200, $response->status());
            self::assertStringContainsString('Summer album', $response->body());
            self::assertStringContainsString('gallery-default-album-cover.svg', $response->body());
        }
    }

    /** @return array<string, mixed> */
    private function item(string $mimeType, int $albumId): array
    {
        return [
            'id' => 2,
            'album_id' => $albumId,
            'media_id' => 12,
            'media_type' => null,
            'title' => 'Gallery item',
            'caption' => 'Caption',
            'alt_text' => 'Description',
            'mime_type' => $mimeType,
            'position' => 4,
            'created_at' => '2026-08-30 10:00:00',
            'updated_at' => '2026-08-31 10:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function album(?string $mimeType, ?string $visibility): array
    {
        return [
            'id' => 5,
            'title' => 'Summer album',
            'slug' => 'summer-album',
            'description' => 'A mixed-media album.',
            'cover_media_id' => 42,
            'cover_mime_type' => $mimeType,
            'cover_visibility' => $visibility,
            'active_item_count' => 3,
            'created_at' => '2026-08-30 10:00:00',
            'updated_at' => '2026-08-31 10:00:00',
            'status' => 'published',
        ];
    }
}
