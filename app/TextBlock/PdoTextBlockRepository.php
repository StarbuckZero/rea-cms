<?php

declare(strict_types=1);

namespace ReaCms\TextBlock;

use DateTimeImmutable;
use PDO;
use PDOException;

final class PdoTextBlockRepository implements TextBlockRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(?string $search = null): array
    {
        $search = trim($search ?? '');
        if ($search === '') {
            $statement = $this->pdo->query(
                'SELECT id, name, content, created_at, updated_at '
                . 'FROM `plugin_text_block_blocks` ORDER BY name, id',
            );
        } else {
            $statement = $this->pdo->prepare(
                'SELECT id, name, content, created_at, updated_at FROM `plugin_text_block_blocks` '
                . 'WHERE LOCATE(:search_name, name) > 0 OR LOCATE(:search_content, content) > 0 '
                . 'ORDER BY name, id',
            );
            $statement->execute(['search_name' => $search, 'search_content' => $search]);
        }

        return $statement === false
            ? []
            : array_values(array_map($this->hydrate(...), $statement->fetchAll()));
    }

    public function findById(int $id): ?TextBlock
    {
        return $this->one('id = :value', $id);
    }

    public function findByName(string $name): ?TextBlock
    {
        return $this->one('name = :value', $name);
    }

    public function create(string $name, string $content): TextBlock
    {
        $now = $this->timestamp();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO `plugin_text_block_blocks` (name, content, created_at, updated_at) '
                . 'VALUES (:name, :content, :created_at, :updated_at)',
            );
            $statement->execute([
                'name' => $name,
                'content' => $content,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new TextBlockException('A text block already uses that name.', previous: $exception);
            }
            throw $exception;
        }

        $block = $this->findById((int) $this->pdo->lastInsertId());
        if ($block === null) {
            throw new TextBlockException('The text block could not be created.');
        }

        return $block;
    }

    public function update(int $id, string $name, string $content): void
    {
        try {
            $statement = $this->pdo->prepare(
                'UPDATE `plugin_text_block_blocks` SET name = :name, content = :content, '
                . 'updated_at = :updated_at WHERE id = :id',
            );
            $statement->execute([
                'id' => $id,
                'name' => $name,
                'content' => $content,
                'updated_at' => $this->timestamp(),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new TextBlockException('A text block already uses that name.', previous: $exception);
            }
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM `plugin_text_block_blocks` WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private function one(string $where, int|string $value): ?TextBlock
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, content, created_at, updated_at FROM `plugin_text_block_blocks` '
            . 'WHERE ' . $where . ' LIMIT 1',
        );
        $statement->execute(['value' => $value]);
        $row = $statement->fetch();

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): TextBlock
    {
        return new TextBlock(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['content'],
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
        );
    }

    private function timestamp(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s.u');
    }
}
