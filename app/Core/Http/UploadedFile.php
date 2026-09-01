<?php

declare(strict_types=1);

namespace ReaCms\Core\Http;

final class UploadedFile
{
    public function __construct(
        public readonly string $clientName,
        public readonly string $temporaryPath,
        public readonly int $error,
        public readonly int $size,
    ) {
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK
            && $this->temporaryPath !== ''
            && is_file($this->temporaryPath)
            && $this->size >= 0;
    }
}
