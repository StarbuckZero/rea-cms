<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Api\Template;

use PHPUnit\Framework\TestCase;
use ReaCms\Api\Template\PluginApiRenderer;
use ReaCms\Tests\Support\InMemoryPluginApiTemplateRepository;

final class PluginApiRendererTest extends TestCase
{
    public function testListTemplateRendersOncePerRecordWhileJsonStaysStructured(): void
    {
        $templates = new InMemoryPluginApiTemplateRepository();
        $templates->templates['blog'] = [
            'html_list' => '<h2>{blog.title}</h2>',
            'html_detail' => '<h1>{blog.title}</h1>',
            'txt_list' => '[{blog.title}]',
            'txt_detail' => 'Title: {blog.title}',
        ];
        $renderer = new PluginApiRenderer($templates);
        $document = ['data' => [
            ['id' => 1, 'title' => 'First'],
            ['id' => 2, 'title' => 'Second'],
        ], 'meta' => ['total' => 2]];

        $html = $renderer->render('blog', 'blog', 'html', 'list', $document);
        $json = $renderer->render('blog', 'blog', 'json', 'list', $document);

        self::assertNotNull($html);
        self::assertSame("<h2>First</h2>\n<h2>Second</h2>", $html->body());
        self::assertNotNull($json);
        self::assertSame($document, json_decode($json->body(), true, 32, JSON_THROW_ON_ERROR));
    }

    public function testDetailHtmlEscapesByDefaultAndAllowsOnlyExplicitSanitizedHtml(): void
    {
        $templates = new InMemoryPluginApiTemplateRepository();
        $templates->templates['blog'] = [
            'html_list' => '{blog.title}',
            'html_detail' => '<h1>{blog.title}</h1>{blog.content | sanitized_html}',
            'txt_list' => '{blog.title}',
            'txt_detail' => '{blog.content}',
        ];
        $response = (new PluginApiRenderer($templates))->render('blog', 'blog', 'html', 'detail', [
            'data' => [
                'title' => '<img src=x onerror=alert(1)>',
                'content' => '<p onclick="bad()">Readable</p><script>bad()</script>',
            ],
        ]);

        self::assertNotNull($response);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $response->body());
        self::assertStringContainsString('<p>Readable</p>', $response->body());
        self::assertStringNotContainsString('bad()', $response->body());
        self::assertStringNotContainsString('onclick', $response->body());
    }

    public function testTxtConvertsHtmlFieldsToReadableAsciiAndContainsNoMarkup(): void
    {
        $templates = new InMemoryPluginApiTemplateRepository();
        $templates->templates['blog'] = [
            'html_list' => '{blog.title}',
            'html_detail' => '{blog.title}',
            'txt_list' => '[{blog.title}]' . "\n" . '{blog.content}',
            'txt_detail' => 'Title: {blog.title}' . "\n" . '{blog.content}',
        ];
        $response = (new PluginApiRenderer($templates))->render('blog', 'blog', 'txt', 'list', [
            'data' => [[
                'title' => 'Crème brûlée',
                'content' => '<p>Hello world.</p><br><p>Final paragraph.</p><style>bad</style>'
                    . '&lt;script&gt;encodedBad()&lt;/script&gt;',
            ]],
        ]);

        self::assertNotNull($response);
        self::assertSame('text/plain; charset=US-ASCII', $response->header('Content-Type'));
        self::assertStringContainsString('[Creme brulee]', $response->body());
        self::assertStringContainsString("Hello world.\n\nFinal paragraph.", $response->body());
        self::assertStringNotContainsString('<', $response->body());
        self::assertStringNotContainsString('bad', $response->body());
        self::assertStringNotContainsString('encodedBad', $response->body());
        self::assertMatchesRegularExpression('/^[\\x09\\x0A\\x20-\\x7E]*$/D', $response->body());
    }

    public function testDetailCanBindAPluginObjectNestedInsideTheReturnedData(): void
    {
        $templates = new InMemoryPluginApiTemplateRepository();
        $templates->templates['podcast'] = [
            'html_list' => '{podcast.title}',
            'html_detail' => '<h1>{podcast.title}</h1>',
            'txt_list' => '{podcast.title}',
            'txt_detail' => '{podcast.title}',
        ];
        $response = (new PluginApiRenderer($templates))->render('podcast', 'podcast', 'html', 'detail', [
            'data' => [
                'podcast' => ['id' => 4, 'title' => 'Nested feed'],
                'episodes' => [['id' => 9, 'title' => 'Episode']],
            ],
        ]);

        self::assertNotNull($response);
        self::assertSame('<h1>Nested feed</h1>', $response->body());
    }
}
