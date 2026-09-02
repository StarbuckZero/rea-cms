<?php

declare(strict_types=1);

namespace ReaCms\Support;

final class PlainText
{
    public static function fromHtml(string $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $value);
        for ($pass = 0; $pass < 3; $pass++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $text) {
                break;
            }
            $text = $decoded;
        }
        $text = preg_replace('/<!--.*?-->/s', '', $text) ?? '';
        $text = preg_replace(
            '#<(script|style|noscript|template|svg)\b[^>]*>.*?(?:</\1\s*>|$)#is',
            '',
            $text,
        ) ?? '';
        $text = preg_replace('#<br\b[^>]*?/?>#i', "\n", $text) ?? '';
        $text = preg_replace('#<hr\b[^>]*?/?>#i', "\n\n", $text) ?? '';
        $text = preg_replace('#<li\b[^>]*>#i', '- ', $text) ?? '';
        $text = preg_replace('#</li\s*>#i', "\n", $text) ?? '';
        $text = preg_replace(
            '#</?(?:p|div|section|article|header|footer|main|aside|nav|h[1-6]|blockquote|pre|ul|ol|table|tr)\b[^>]*>#i',
            "\n\n",
            $text,
        ) ?? '';
        $text = strip_tags($text);
        $text = str_replace("\u{00A0}", ' ', $text);

        $lines = explode("\n", $text);
        foreach ($lines as &$line) {
            $line = preg_replace('/[\t ]+/u', ' ', trim($line)) ?? '';
        }
        unset($line);
        $text = trim(implode("\n", $lines));
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? '';

        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            $text = is_string($ascii) ? $ascii : $text;
        }
        $text = preg_replace('/[^\x09\x0A\x20-\x7E]/', '', $text) ?? '';

        return trim($text);
    }
}
