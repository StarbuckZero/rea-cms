<?php

declare(strict_types=1);

namespace ReaCms\Events;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOException;
use ReaCms\Api\Query\ApiQuery;
use ReaCms\Content\Slugger;
use Throwable;

final class PdoEventRepository
{
    public function __construct(private readonly PDO $pdo, private readonly string $prefix = 'rea_')
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $prefix) !== 1) {
            throw new InvalidArgumentException('Invalid database prefix.');
        }
    }

    /** @return array{data:list<array<string,mixed>>,total:int} */
    public function search(ApiQuery $query, bool $public = true): array
    {
        $where = [$public ? 'e.published = 1' : '1 = 1'];
        $params = [];
        foreach ($query->filters as $field => $value) {
            switch ($field) {
                case 'search':
                    $where[] = "e.title LIKE :search ESCAPE '!'";
                    $params['search'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value) . '%';
                    break;
                case 'type':
                    $where[] = '(t.slug = :type OR t.id = :type_id)';
                    $params['type'] = $value;
                    $params['type_id'] = ctype_digit($value) ? (int) $value : 0;
                    break;
                case 'date':
                    $where[] = 'e.start_date <= :date_end AND e.end_date >= :date_start';
                    $params['date_end'] = $params['date_start'] = $value;
                    break;
                case 'start':
                    $where[] = 'e.end_date >= :start';
                    $params['start'] = $value;
                    break;
                case 'end':
                    $where[] = 'e.start_date <= :end';
                    $params['end'] = $value;
                    break;
                case 'upcoming':
                case 'past':
                    if ($value === 'true') {
                        $where[] = 'e.end_utc ' . ($field === 'past' ? '<=' : '>') . ' :now';
                        $params['now'] = EventDates::utc(new DateTimeImmutable('now'));
                    }
                    break;
            }
        }
        $from = ' FROM `plugin_events_events` e LEFT JOIN `plugin_events_types` t ON t.id = e.type_id';
        $filter = ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT(*)' . $from . $filter);
        $count->execute($params);
        $sort = str_starts_with($query->sort ?? 'date', 'name') ? 'e.title' : 'e.start_utc';
        $direction = str_ends_with($query->sort ?? '', '-desc') ? 'DESC' : strtoupper($query->direction);
        $statement = $this->pdo->prepare($this->select() . $filter
            . ' ORDER BY ' . $sort . ' ' . $direction . ', e.id ' . $direction
            . ' LIMIT ' . $query->perPage . ' OFFSET ' . (($query->page - 1) * $query->perPage));
        $statement->execute($params);
        return ['data' => array_values($statement->fetchAll(PDO::FETCH_ASSOC)), 'total' => (int) $count->fetchColumn()];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id, bool $public = false): ?array
    {
        $statement = $this->pdo->prepare(
            $this->select() . ' WHERE e.id = :id' . ($public ? ' AND e.published = 1' : ''),
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function select(): string
    {
        return 'SELECT e.*, t.name AS type_name, t.slug AS type_slug, '
            . 'm.id AS public_image_id, d.id AS default_image_id '
            . 'FROM `plugin_events_events` e LEFT JOIN `plugin_events_types` t ON t.id = e.type_id '
            . 'LEFT JOIN `plugin_events_settings` s ON s.id = 1 '
            . 'LEFT JOIN `' . $this->prefix . "media` m ON m.id = e.image_id AND m.visibility = 'public' "
            . 'LEFT JOIN `' . $this->prefix . "media` d ON d.id = s.default_image_id AND d.visibility = 'public'";
    }

    /** @param array<string,mixed> $input */
    public function save(array $input, ?int $id = null): int
    {
        $values = (new EventValidator())->validate($input);
        $this->pdo->beginTransaction();
        try {
            if ($values['type_id'] !== null && $this->type((int) $values['type_id'], true) === null) {
                throw new InvalidArgumentException('The selected event type no longer exists.');
            }
            $this->requireImage($values['image_id']);
            $values['updated_at'] = EventDates::utc(new DateTimeImmutable('now'));
            if ($id === null) {
                $values['created_at'] = $values['updated_at'];
                $columns = array_keys($values);
                $statement = $this->pdo->prepare('INSERT INTO `plugin_events_events` (`'
                    . implode('`, `', $columns) . '`) VALUES (:' . implode(', :', $columns) . ')');
                $statement->execute($values);
                $id = (int) $this->pdo->lastInsertId();
            } else {
                $assignments = array_map(
                    static fn (string $key): string => '`' . $key . '` = :' . $key,
                    array_keys($values),
                );
                $statement = $this->pdo->prepare('UPDATE `plugin_events_events` SET '
                    . implode(', ', $assignments) . ' WHERE id = :id');
                $statement->execute([...$values, 'id' => $id]);
            }
            $this->usage('event', $id, $values['image_id']);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('DELETE FROM `plugin_events_events` WHERE id = :id');
            $statement->execute(['id' => $id]);
            $this->usage('event', $id, null);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /** @return list<array<string,mixed>> */
    public function types(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, name, slug, description FROM `plugin_events_types` ORDER BY name, id',
        );
        return $statement === false ? [] : array_values($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<string,mixed>|null */
    public function type(int $id, bool $lock = false): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, slug, description FROM `plugin_events_types` WHERE id = :id'
            . ($lock ? ' FOR UPDATE' : ''),
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $input */
    public function saveType(array $input, ?int $id): int
    {
        foreach (['name', 'slug', 'description'] as $key) {
            if (isset($input[$key]) && !is_string($input[$key])) {
                throw new InvalidArgumentException('Event type fields must be strings.');
            }
        }
        $name = trim($input['name'] ?? '');
        try {
            $slug = (new Slugger())->slug(($input['slug'] ?? '') ?: $name);
        } catch (\ReaCms\Content\ContentException $exception) {
            throw new InvalidArgumentException('Enter a usable event type name or slug.', previous: $exception);
        }
        $description = trim($input['description'] ?? '');
        if ($name === '' || strlen($name) > 191 || strlen($slug) > 191 || strlen($description) > 60000) {
            throw new InvalidArgumentException('Enter a type name up to 191 characters and a shorter description.');
        }
        $values = ['name' => $name, 'slug' => $slug, 'description' => $description];
        $sql = $id === null
            ? 'INSERT INTO `plugin_events_types` (name, slug, description) VALUES (:name, :slug, :description)'
            : 'UPDATE `plugin_events_types` SET name = :name, slug = :slug, description = :description WHERE id = :id';
        if ($id !== null) {
            $values['id'] = $id;
        }
        try {
            $this->pdo->prepare($sql)->execute($values);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new InvalidArgumentException('Another event type already uses that slug.', previous: $exception);
            }
            throw $exception;
        }
        return $id ?? (int) $this->pdo->lastInsertId();
    }

    public function deleteType(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->type($id, true);
            $this->pdo->prepare('UPDATE `plugin_events_events` SET type_id = NULL, updated_at = :now '
                . 'WHERE type_id = :id')
                ->execute(['id' => $id, 'now' => EventDates::utc(new DateTimeImmutable('now'))]);
            $this->pdo->prepare('DELETE FROM `plugin_events_types` WHERE id = :id')->execute(['id' => $id]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function defaultImage(): ?int
    {
        $statement = $this->pdo->query('SELECT default_image_id FROM `plugin_events_settings` WHERE id = 1');
        $value = $statement === false ? false : $statement->fetchColumn();
        return $value === false || $value === null ? null : (int) $value;
    }

    public function saveDefaultImage(?int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->requireImage($id);
            $this->pdo->exec('DELETE FROM `plugin_events_settings` WHERE id = 1');
            $this->pdo->prepare('INSERT INTO `plugin_events_settings` (id, default_image_id) VALUES (1, :image)')
                ->execute(['image' => $id]);
            $this->usage('settings', 1, $id);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    private function requireImage(?int $id): void
    {
        if ($id === null) {
            return;
        }
        $statement = $this->pdo->prepare('SELECT id FROM `' . $this->prefix . 'media` '
            . "WHERE id = :id AND mime_type LIKE 'image/%' AND visibility = 'public' FOR UPDATE");
        $statement->execute(['id' => $id]);
        if ($statement->fetchColumn() === false) {
            throw new InvalidArgumentException('Choose an existing public image from the media library.');
        }
    }

    private function usage(string $resource, int $id, ?int $image): void
    {
        $this->pdo->prepare('DELETE FROM `' . $this->prefix . 'media_usage` '
            . 'WHERE plugin_id = :plugin AND resource = :resource AND content_id = :id')
            ->execute(['plugin' => 'events', 'resource' => $resource, 'id' => $id]);
        if ($image !== null) {
            $this->pdo->prepare('INSERT INTO `' . $this->prefix . 'media_usage` '
                . '(media_id, plugin_id, resource, content_id, field) '
                . 'VALUES (:image, :plugin, :resource, :id, :field)')
                ->execute(['image' => $image, 'plugin' => 'events', 'resource' => $resource,
                    'id' => $id, 'field' => 'image_id']);
        }
    }
}
