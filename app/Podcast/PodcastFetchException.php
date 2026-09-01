<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

final class PodcastFetchException extends PodcastException
{
    public function __construct(string $message, public readonly ?int $httpStatus = null)
    {
        parent::__construct($message);
    }
}
