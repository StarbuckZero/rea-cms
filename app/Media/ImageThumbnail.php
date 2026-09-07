<?php

declare(strict_types=1);

namespace ReaCms\Media;

final class ImageThumbnail
{
    /** Generate a bounded PNG without modifying the uploaded original. */
    public function create(string $path): ?string
    {
        if (!function_exists('imagecreatefromstring') || !is_file($path) || !is_readable($path)) {
            return null;
        }
        $size = @getimagesize($path);
        // Bound decoder memory use, including malformed images with extreme dimensions.
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 12_000_000) {
            return null;
        }
        $bytes = @file_get_contents($path);
        $source = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($source === false) {
            return null;
        }
        $scale = min(1, 320 / $size[0], 320 / $size[1]);
        $width = max(1, (int) round($size[0] * $scale));
        $height = max(1, (int) round($size[1] * $scale));
        $target = imagecreatetruecolor($width, $height);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
        ob_start();
        try {
            $saved = imagepng($target);
            $result = ob_get_contents();
            return $saved && is_string($result) ? $result : null;
        } finally {
            ob_end_clean();
            imagedestroy($source);
            imagedestroy($target);
        }
    }
}
