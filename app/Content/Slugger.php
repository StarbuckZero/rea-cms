<?php

declare(strict_types=1);

namespace ReaCms\Content;

final class Slugger
{
    public function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(is_string($ascii) ? $ascii : $value)) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            throw new ContentException('Content could not produce a usable slug.');
        }
        return substr($slug, 0, 191);
    }
}
