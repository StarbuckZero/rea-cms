<?php

declare(strict_types=1);

namespace ReaCms\Content;

use PDO;

final class PdoContentRepository implements ContentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(ResourceDefinition $definition, int $id): ?array
    {
        $columns = ['id', ...array_keys($definition->fields)];
        $statement = $this->pdo->prepare(sprintf(
            'SELECT %s FROM `%s` WHERE id = :id LIMIT 1',
            implode(', ', array_map(self::quote(...), $columns)),
            $definition->table,
        ));
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(ResourceDefinition $definition, array $values): int
    {
        $columns = array_keys($values);
        $statement = $this->pdo->prepare(sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $definition->table,
            implode(', ', array_map(self::quote(...), $columns)),
            implode(', ', array_map(static fn (string $field): string => ':' . $field, $columns)),
        ));
        $statement->execute($this->databaseValues($definition, $values));
        return (int) $this->pdo->lastInsertId();
    }

    public function update(ResourceDefinition $definition, int $id, array $values): void
    {
        $assignments = array_map(
            static fn (string $field): string => sprintf('`%s` = :%s', $field, $field),
            array_keys($values),
        );
        $statement = $this->pdo->prepare(sprintf(
            'UPDATE `%s` SET %s WHERE id = :content_id',
            $definition->table,
            implode(', ', $assignments),
        ));
        $statement->execute([...$this->databaseValues($definition, $values), 'content_id' => $id]);
    }

    private static function quote(string $identifier): string
    {
        return '`' . $identifier . '`';
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function databaseValues(ResourceDefinition $definition, array $values): array
    {
        foreach ($values as $field => $value) {
            if ($definition->fields[$field] === 'json') {
                $values[$field] = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            } elseif (is_bool($value)) {
                $values[$field] = $value ? 1 : 0;
            }
        }
        return $values;
    }
}
