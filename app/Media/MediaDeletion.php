<?php

declare(strict_types=1);

namespace ReaCms\Media;

final class MediaDeletion
{
    public function __construct(private readonly MediaUsage $usage)
    {
    }

    /** @param callable(int): void $delete */
    public function delete(int $mediaId, callable $delete): void
    {
        if ($this->usage->count($mediaId) > 0) {
            throw new MediaException('Media cannot be deleted while content still references it.');
        }
        $delete($mediaId);
    }
}
