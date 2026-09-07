<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Gallery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReaCms\Core\Http\Request;

final class GalleryFormRequestTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>|null}> */
    public static function selections(): iterable
    {
        yield 'single image' => ['media_ids%5B%5D=42&album_id=2', ['42']];
        yield 'multiple images' => ['media_ids%5B%5D=42&media_ids%5B%5D=43&album_id=2', ['42', '43']];
        yield 'legacy single image' => ['media_id=42&album_id=2', null];
        yield 'no selection' => ['album_id=2', null];
        yield 'scalar is invalid' => ['media_ids=42', []];
        yield 'nested array is invalid' => ['media_ids[0][id]=42', []];
        yield 'associative array is invalid' => ['media_ids[id]=42', []];
    }

    /** @param list<string>|null $expected */
    #[DataProvider('selections')]
    public function testGallerySelectionSurvivesRequestParsing(string $body, ?array $expected): void
    {
        foreach (['/cms/gallery', '/cms/gallery/7'] as $uri) {
            $request = new Request('POST', $uri, body: $body);
            self::assertSame($expected, $request->formList('media_ids'));
            foreach ($request->form() as $value) {
                self::assertIsString($value);
            }
        }
    }

    public function testMultipartListsAndScalarFieldsAreBothAvailable(): void
    {
        $previous = $_POST;
        try {
            $_POST = ['media_ids' => ['42', '43'], 'album_id' => '2', '_csrf' => 'token'];
            $request = new Request('POST', '/cms/gallery', ['content-type' => 'multipart/form-data; boundary=test']);
            self::assertSame(['42', '43'], $request->formList('media_ids'));
            self::assertSame(['album_id' => '2', '_csrf' => 'token'], $request->form());
        } finally {
            $_POST = $previous;
        }
    }

    public function testLegacySingleImageFieldRemainsAvailable(): void
    {
        $request = new Request('POST', '/cms/gallery', body: 'media_id=42&album_id=2');
        self::assertSame(['media_id' => '42', 'album_id' => '2'], $request->form());
    }
}
