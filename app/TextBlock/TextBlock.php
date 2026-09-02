<?php

declare(strict_types=1);

namespace ReaCms\TextBlock;

use DateTimeImmutable;

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

    /** @return array{id:int,name:string,content:string,createdAt:string,updatedAt:string} */
    public function api(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'content' => $this->content,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
