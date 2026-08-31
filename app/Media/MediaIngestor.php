<?php

declare(strict_types=1);

namespace ReaCms\Media;

use finfo;

final class MediaIngestor
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
        'application/pdf' => 'pdf', 'audio/mpeg' => 'mp3', 'video/mp4' => 'mp4',
        'video/webm' => 'webm', 'video/quicktime' => 'mov',
    ];

    /** @var callable(string, string): bool */
    private $scanner;

    /** @param callable(string, string): bool|null $scanner */
    public function __construct(
        private readonly string $storageRoot,
        private readonly int $maximumBytes = 25_000_000,
        ?callable $scanner = null
    ) {
        $this->scanner = $scanner ?? static fn (): bool => true;
    }

    /** @return array{storedName: string, mime: string, size: int, hash: string, originalName: string} */
    public function ingest(string $temporaryPath, string $originalName): array
    {
        $size = filesize($temporaryPath);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (
            !is_int($size) || $size < 1 || $size > $this->maximumBytes || !is_string($mime)
            || !isset(self::MIME_EXTENSIONS[$mime]) || !($this->scanner)($temporaryPath, $mime)
        ) {
            throw new MediaException('The media file failed content, size, or malware validation.');
        }
        $hash = hash_file('sha256', $temporaryPath);
        if ($hash === false) {
            throw new MediaException('The media checksum could not be calculated.');
        }
        if (!is_dir($this->storageRoot) && !mkdir($this->storageRoot, 0700, true) && !is_dir($this->storageRoot)) {
            throw new MediaException('Private media storage could not be created.');
        }
        $storedName = bin2hex(random_bytes(24)) . '.' . self::MIME_EXTENSIONS[$mime];
        $destination = rtrim($this->storageRoot, '/') . '/' . $storedName;
        if (!copy($temporaryPath, $destination) || !chmod($destination, 0600)) {
            throw new MediaException('The validated media file could not be stored.');
        }
        return ['storedName' => $storedName, 'mime' => $mime, 'size' => $size, 'hash' => $hash,
            'originalName' => basename(str_replace('\\', '/', $originalName))];
    }
}
