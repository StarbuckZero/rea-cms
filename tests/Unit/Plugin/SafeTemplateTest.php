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
        self::assertStringContainsString('<p>Hi</p>x', $result);
        self::assertStringNotContainsString('onclick', $result);
    }

    public function testExecutableAndArbitraryTemplateSyntaxIsRejected(): void
    {
        $this->expectException(PluginException::class);
        (new SafeTemplate())->render('<?php system("id"); ?>', []);
    }
}
