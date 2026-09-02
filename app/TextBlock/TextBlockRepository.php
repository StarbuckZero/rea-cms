<?php

declare(strict_types=1);

namespace ReaCms\TextBlock;

interface TextBlockRepository
{
    /** @return list<TextBlock> */
    public function all(?string $search = null): array;

    public function findById(int $id): ?TextBlock;

    public function findByName(string $name): ?TextBlock;

    public function create(string $name, string $content): TextBlock;

    public function update(int $id, string $name, string $content): void;

    public function delete(int $id): void;
}
