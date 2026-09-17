<?php

declare(strict_types=1);

namespace ReaCms\TextBlock;

use DateTimeImmutable;
use ReaCms\Support\TemplateDateTime;

final class TextBlock
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $content,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }

    /** @return array<string, mixed> */
    public function api(?string $timezone = null): array
    {
        $created = TemplateDateTime::local($this->createdAt, $timezone);
        $updated = TemplateDateTime::local($this->updatedAt, $timezone);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'content' => $this->content,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'createdDate' => $created?->format('F j, Y') ?? '',
            'createdTime' => $created?->format('g:i A') ?? '',
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
            'updatedDate' => $updated?->format('F j, Y') ?? '',
            'updatedTime' => $updated?->format('g:i A') ?? '',
        ];
    }
}
