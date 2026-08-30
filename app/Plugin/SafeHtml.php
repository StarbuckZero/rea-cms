<?php

declare(strict_types=1);

namespace ReaCms\Plugin;

final class SafeHtml
{
    private function __construct(public readonly string $value)
    {
    }

    public static function sanitize(string $value): self
    {
        $clean = strip_tags($value, '<p><br><strong><em><ul><ol><li><a><blockquote><code>');
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace('/(href\s*=\s*["\'])\s*(?:javascript|data):[^"\']*(["\'])/i', '$1#$2', $clean) ?? '';

        return new self($clean);
    }
}
