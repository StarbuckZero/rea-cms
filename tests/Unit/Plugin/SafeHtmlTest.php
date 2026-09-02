<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use ReaCms\Plugin\SafeHtml;

final class SafeHtmlTest extends TestCase
{
    public function testItKeepsSemanticEditorMarkupAndApprovedPresentationClasses(): void
    {
        $html = SafeHtml::sanitize(
            '<h1 class="align-center ignored">Title</h1>'
            . '<figure class="align-left size-medium unknown"><a href="https://example.com">'
            . '<img src="/media/7" alt="A tree" title="Tree"></a><figcaption>Caption</figcaption></figure>'
            . '<pre><code>echo &quot;hello&quot;;</code></pre>',
        )->value;

        self::assertStringContainsString('<h1 class="align-center">Title</h1>', $html);
        self::assertStringContainsString('<figure class="align-left size-medium">', $html);
        self::assertStringContainsString(
            '<img src="/media/7" alt="A tree" title="Tree" loading="lazy">',
            $html,
        );
        self::assertStringContainsString('<figcaption>Caption</figcaption>', $html);
        self::assertStringContainsString('<pre><code>echo "hello";</code></pre>', $html);
    }

    public function testItRemovesExecutableMarkupAttributesAndUnsafeUrls(): void
    {
        $html = SafeHtml::sanitize(
            '<script>alert(1)</script><style>body{display:none}</style>'
            . '<p onclick="alert(1)" style="color:red">Safe</p>'
            . '<a href="java&#x0A;script:alert(1)" target="_blank" rel="opener">Link</a>'
            . '<img src="data:image/png;base64,abc" onerror="alert(1)" alt="x">',
        )->value;

        self::assertStringNotContainsString('alert', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<style', $html);
        self::assertStringNotContainsString('onclick', $html);
        self::assertStringNotContainsString('onerror', $html);
        self::assertStringNotContainsString('style=', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('data:', $html);
        self::assertStringContainsString('<p>Safe</p>', $html);
        self::assertStringContainsString('<a target="_blank" rel="noopener noreferrer">Link</a>', $html);
    }

    public function testItCleansPastedMarkupAndNormalizesLegacyTags(): void
    {
        $html = SafeHtml::sanitize(
            '<div class="MsoNormal"><span style="font-family:Arial"><b>Bold</b> and <i>italic</i></span></div>'
            . '<custom-element><u>Underlined</u></custom-element><!-- hidden -->',
        )->value;

        self::assertSame(
            '<p><strong>Bold</strong> and <em>italic</em></p><u>Underlined</u>',
            $html,
        );
    }

    public function testItSecuresNewWindowLinks(): void
    {
        $html = SafeHtml::sanitize(
            '<a href="https://example.com" target="_blank" rel="opener nofollow">Example</a>',
        )->value;

        self::assertSame(
            '<a href="https://example.com" target="_blank" rel="noopener noreferrer">Example</a>',
            $html,
        );
    }
}
