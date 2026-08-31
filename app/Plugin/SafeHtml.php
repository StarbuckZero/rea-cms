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
        $allowedTags = '<p><br><h2><h3><h4><strong><b><em><i><u>'
            . '<ul><ol><li><a><blockquote><code><img><div>';
        $clean = strip_tags($value, $allowedTags);
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';
        $clean = preg_replace('/(href\s*=\s*["\'])\s*(?:javascript|data):[^"\']*(["\'])/i', '$1#$2', $clean) ?? '';
        $clean = preg_replace('/(src\s*=\s*["\'])\s*(?:javascript|data):[^"\']*(["\'])/i', '$1#$2', $clean) ?? '';
        $clean = preg_replace('/\s+(?:class|id|style)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? '';

        return new self($clean);
    }
}
