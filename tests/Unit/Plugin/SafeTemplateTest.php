<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\PluginException;
use ReaCms\Plugin\SafeTemplate;

final class SafeTemplateTest extends TestCase
{
    public function testValuesEscapeByDefaultAndSanitizedHtmlIsExplicit(): void
    {
        $renderer = new SafeTemplate();
        $result = $renderer->render(
            '<h1>{{ post.title }}</h1>{{ post.body | sanitized_html }}',
            ['post' => ['title' => '<script>x</script>', 'body' => '<p onclick="x">Hi</p><script>x</script>']],
        );

        self::assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $result);
        self::assertStringContainsString('<p>Hi</p>', $result);
        self::assertStringNotContainsString('<p>Hi</p>x', $result);
        self::assertStringNotContainsString('onclick', $result);
    }

    public function testExecutableAndArbitraryTemplateSyntaxIsRejected(): void
    {
        $this->expectException(PluginException::class);
        (new SafeTemplate())->render('<?php system("id"); ?>', []);
    }

    public function testSingleBraceBindingsSupportMissingAndNullValues(): void
    {
        $result = (new SafeTemplate())->render(
            '<p>{blog.title}</p><p>{blog.subtitle}</p><p>{blog.missing}</p>',
            ['blog' => ['title' => 'A & B', 'subtitle' => null]],
        );

        self::assertSame('<p>A &amp; B</p><p></p><p></p>', $result);
    }

    public function testTextRenderingRemovesMarkupAndProducesAscii(): void
    {
        $result = (new SafeTemplate())->renderText(
            "Title: {blog.title}\n{blog.body}",
            ['blog' => [
                'title' => 'Café — news',
                'body' => '<p>Hello &amp; welcome.</p><script>bad()</script><p>Final.</p>',
            ]],
        );

        self::assertSame("Title: Cafe -- news\nHello & welcome.\n\nFinal.", $result);
        self::assertMatchesRegularExpression('/^[\\x09\\x0A\\x20-\\x7E]*$/D', $result);
    }

    public function testLiteralBrowserExecutableMarkupIsRejected(): void
    {
        $this->expectException(PluginException::class);
        (new SafeTemplate())->render('<script>alert(1)</script>', []);
    }

    public function testHyphenatedPluginResourceNamesCanBindFields(): void
    {
        $result = (new SafeTemplate())->render(
            '<h1>{knowledge-base.title}</h1>',
            ['knowledge-base' => ['title' => 'Article']],
        );

        self::assertSame('<h1>Article</h1>', $result);
    }
}
