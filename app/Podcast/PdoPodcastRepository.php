<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use DateTimeImmutable;
use PDO;
use Throwable;

final class PdoPodcastRepository implements PodcastRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function feeds(bool $enabledOnly = false): array
    {
        $sql = 'SELECT * FROM `plugin_podcast_feeds`';
        if ($enabledOnly) {
            $sql .= ' WHERE enabled = 1';
        }
        $sql .= ' ORDER BY title, slug';
        $statement = $this->pdo->query($sql);
        return $statement === false ? [] : array_values(array_map($this->hydrateFeed(...), $statement->fetchAll()));
    }

    public function feedById(int $id): ?PodcastFeed
    {
        return $this->oneFeed('id = :value', $id);
    }

    public function feedBySlug(string $slug): ?PodcastFeed
    {
        return $this->oneFeed('slug = :value', $slug);
    }

    public function createFeed(
        string $slug,
        string $rssUrl,
        ?int $refreshIntervalMinutes,
        bool $automaticRefresh,
    ): PodcastFeed {
        if ($this->feedBySlug($slug) !== null) {
            throw new PodcastException('A podcast feed already uses that slug.');
        }
        $now = $this->format(new DateTimeImmutable('now'));
        $statement = $this->pdo->prepare(
            'INSERT INTO `plugin_podcast_feeds` '
            . '(slug, rss_url, enabled, refresh_interval, automatic_refresh, title, description, link, language, '
            . 'author, image_url, explicit, refresh_status, created_at, updated_at) VALUES '
            . '(:slug, :rss_url, 1, :refresh_interval, :automatic_refresh, :title, :description, :link, :language, '
            . ':author, :image_url, 0, :refresh_status, :created_at, :updated_at)',
        );
        $statement->execute([
            'slug' => $slug,
            'rss_url' => $rssUrl,
            'refresh_interval' => $refreshIntervalMinutes,
            'automatic_refresh' => $automaticRefresh ? 1 : 0,
            'title' => '',
            'description' => '',
            'link' => '',
            'language' => '',
            'author' => '',
            'image_url' => '',
            'refresh_status' => 'current',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $feed = $this->feedById((int) $this->pdo->lastInsertId());
        if ($feed === null) {
            throw new PodcastException('The podcast feed could not be created.');
        }
        return $feed;
    }

    public function updateFeed(
        int $id,
        string $slug,
        string $rssUrl,
        bool $enabled,
        ?int $refreshIntervalMinutes,
        bool $automaticRefresh,
    ): void {
        $existing = $this->feedBySlug($slug);
        if ($existing !== null && $existing->id !== $id) {
            throw new PodcastException('A podcast feed already uses that slug.');
        }
        $current = $this->feedById($id);
        $sourceChanged = $current !== null && $current->rssUrl !== $rssUrl;
        $statement = $this->pdo->prepare(
            'UPDATE `plugin_podcast_feeds` SET slug = :slug, rss_url = :rss_url, enabled = :enabled, '
            . 'refresh_interval = :refresh_interval, automatic_refresh = :automatic_refresh, '
            . 'next_refresh_at = NULL, etag = :etag, last_modified = :last_modified, '
            . 'content_hash = :content_hash, updated_at = :updated_at WHERE id = :id',
        );
        $statement->execute([
            'slug' => $slug,
            'rss_url' => $rssUrl,
            'enabled' => $enabled ? 1 : 0,
            'refresh_interval' => $refreshIntervalMinutes,
            'automatic_refresh' => $automaticRefresh ? 1 : 0,
            'etag' => $sourceChanged ? null : $current?->etag,
            'last_modified' => $sourceChanged ? null : $current?->lastModified,
            'content_hash' => $sourceChanged ? null : $current?->contentHash,
            'updated_at' => $this->format(new DateTimeImmutable('now')),
            'id' => $id,
        ]);
    }

    public function deleteFeed(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $episodes = $this->pdo->prepare('DELETE FROM `plugin_podcast_episodes` WHERE feed_id = :id');
            $episodes->execute(['id' => $id]);
            $feed = $this->pdo->prepare('DELETE FROM `plugin_podcast_feeds` WHERE id = :id');
            $feed->execute(['id' => $id]);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollBack();
            throw $exception;
        }
    }

    public function settings(): PodcastSettings
    {
        $statement = $this->pdo->query('SELECT setting_key, setting_value FROM `plugin_podcast_settings`');
        $values = [];
        if ($statement !== false) {
            foreach ($statement->fetchAll() as $row) {
                if (is_array($row)) {
                    $values[(string) $row['setting_key']] = (string) $row['setting_value'];
                }
            }
        }
        return new PodcastSettings(
            $this->boundedInt($values['default_refresh_interval'] ?? null, 1, 1440, 10),
            ($values['automatic_refresh'] ?? '1') === '1',
            $this->boundedInt($values['request_timeout'] ?? null, 1, 60, 10),
            $this->boundedInt($values['maximum_download_size'] ?? null, 65_536, 52_428_800, 5_242_880),
        );
    }

    public function saveSettings(PodcastSettings $settings): void
    {
        $values = [
            'default_refresh_interval' => (string) $settings->defaultRefreshIntervalMinutes,
            'automatic_refresh' => $settings->automaticRefresh ? '1' : '0',
            'request_timeout' => (string) $settings->requestTimeoutSeconds,
            'maximum_download_size' => (string) $settings->maximumDownloadBytes,
        ];
        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM `plugin_podcast_settings` WHERE setting_key = :key');
            $insert = $this->pdo->prepare(
                'INSERT INTO `plugin_podcast_settings` (setting_key, setting_value, updated_at) '
                . 'VALUES (:key, :value, :updated_at)',
            );
            $updatedAt = $this->format(new DateTimeImmutable('now'));
            foreach ($values as $key => $value) {
                $delete->execute(['key' => $key]);
                $insert->execute(['key' => $key, 'value' => $value, 'updated_at' => $updatedAt]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollBack();
            throw $exception;
        }
    }

    public function episodes(?int $feedId, int $limit, int $offset): array
    {
        $sql = 'SELECT e.*, f.slug AS feed_slug, f.title AS feed_title FROM `plugin_podcast_episodes` e '
            . 'INNER JOIN `plugin_podcast_feeds` f ON f.id = e.feed_id WHERE f.enabled = 1';
        if ($feedId !== null) {
            $sql .= ' AND e.feed_id = :feed_id';
        }
        $sql .= ' ORDER BY e.published_at DESC, e.id DESC LIMIT :limit OFFSET :offset';
        $statement = $this->pdo->prepare($sql);
        if ($feedId !== null) {
            $statement->bindValue('feed_id', $feedId, PDO::PARAM_INT);
        }
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();
        return array_values(array_map($this->hydrateEpisode(...), $statement->fetchAll()));
    }

    public function countEpisodes(?int $feedId): int
    {
        $sql = 'SELECT COUNT(*) FROM `plugin_podcast_episodes` e INNER JOIN `plugin_podcast_feeds` f '
            . 'ON f.id = e.feed_id WHERE f.enabled = 1';
        if ($feedId !== null) {
            $sql .= ' AND e.feed_id = :feed_id';
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($feedId === null ? [] : ['feed_id' => $feedId]);
        return (int) $statement->fetchColumn();
    }

    public function episode(int $feedId, string $episode): ?PodcastEpisode
    {
        $statement = $this->pdo->prepare(
            'SELECT e.*, f.slug AS feed_slug, f.title AS feed_title FROM `plugin_podcast_episodes` e '
            . 'INNER JOIN `plugin_podcast_feeds` f ON f.id = e.feed_id '
            . 'WHERE f.enabled = 1 AND e.feed_id = :feed_id AND (e.slug = :slug OR e.id = :id) LIMIT 1',
        );
        $id = ctype_digit($episode) ? (int) $episode : 0;
        $statement->execute(['feed_id' => $feedId, 'slug' => $episode, 'id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrateEpisode($row) : null;
    }

    public function acquireRefreshLock(int $feedId, DateTimeImmutable $now, int $seconds = 120): ?string
    {
        $token = bin2hex(random_bytes(16));
        $statement = $this->pdo->prepare(
            'UPDATE `plugin_podcast_feeds` SET refresh_status = :status, refresh_lock_token = :token, '
            . 'refresh_lock_expires_at = :expires WHERE id = :id AND '
            . '(refresh_lock_token IS NULL OR refresh_lock_expires_at IS NULL OR refresh_lock_expires_at <= :now)',
        );
        $statement->execute([
            'status' => 'checking',
            'token' => $token,
            'expires' => $this->format($now->modify('+' . max(1, $seconds) . ' seconds')),
            'id' => $feedId,
            'now' => $this->format($now),
        ]);
        return $statement->rowCount() === 1 ? $token : null;
    }

    public function storeUpdatedFeed(
        PodcastFeed $feed,
        ParsedPodcast $podcast,
        FeedFetchResult $result,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $nextRefreshAt,
        string $lockToken,
    ): void {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'UPDATE `plugin_podcast_feeds` SET title = :title, description = :description, link = :link, '
                . 'language = :language, author = :author, image_url = :image_url, explicit = :explicit, '
                . 'last_checked_at = :checked, last_successful_refresh_at = :successful, last_changed_at = :changed, '
                . 'next_refresh_at = :next_refresh, etag = :etag, last_modified = :last_modified, last_error = NULL, '
                . 'last_http_status = :http_status, refresh_status = :status, content_hash = :content_hash, '
                . 'refresh_lock_token = NULL, refresh_lock_expires_at = NULL, updated_at = :updated '
                . 'WHERE id = :id AND refresh_lock_token = :token',
            );
            $formatted = $this->format($checkedAt);
            $statement->execute([
                'title' => $podcast->title,
                'description' => $podcast->description,
                'link' => $podcast->link,
                'language' => $podcast->language,
                'author' => $podcast->author,
                'image_url' => $podcast->imageUrl,
                'explicit' => $podcast->explicit ? 1 : 0,
                'checked' => $formatted,
                'successful' => $formatted,
                'changed' => $formatted,
                'next_refresh' => $this->format($nextRefreshAt),
                'etag' => $result->etag,
                'last_modified' => $result->lastModified,
                'http_status' => $result->status,
                'status' => 'updated',
                'content_hash' => $podcast->contentHash,
                'updated' => $formatted,
                'id' => $feed->id,
                'token' => $lockToken,
            ]);
            if ($statement->rowCount() !== 1) {
                throw new PodcastException('The podcast refresh lock expired before the feed could be stored.');
            }
            $delete = $this->pdo->prepare('DELETE FROM `plugin_podcast_episodes` WHERE feed_id = :feed_id');
            $delete->execute(['feed_id' => $feed->id]);
            $insert = $this->pdo->prepare(
                'INSERT INTO `plugin_podcast_episodes` (feed_id, guid, slug, title, description, content, link, '
                . 'audio_url, audio_length, audio_type, duration_seconds, image_url, explicit, episode_type, '
                . 'published_at, created_at, updated_at) VALUES (:feed_id, :guid, :slug, :title, :description, '
                . ':content, :link, :audio_url, :audio_length, :audio_type, :duration_seconds, :image_url, '
                . ':explicit, :episode_type, :published_at, :created_at, :updated_at)',
            );
            foreach ($podcast->episodes as $episode) {
                $insert->execute([
                    'feed_id' => $feed->id,
                    'guid' => $episode->guid,
                    'slug' => $episode->slug,
                    'title' => $episode->title,
                    'description' => $episode->description,
                    'content' => $episode->content,
                    'link' => $episode->link,
                    'audio_url' => $episode->audioUrl,
                    'audio_length' => $episode->audioLength,
                    'audio_type' => $episode->audioType,
                    'duration_seconds' => $episode->durationSeconds,
                    'image_url' => $episode->imageUrl,
                    'explicit' => $episode->explicit ? 1 : 0,
                    'episode_type' => $episode->episodeType,
                    'published_at' => $episode->publishedAt === null ? null : $this->format($episode->publishedAt),
                    'created_at' => $formatted,
                    'updated_at' => $formatted,
                ]);
            }
            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->rollBack();
            throw $exception;
        }
    }

    public function storeUnchangedFeed(
        PodcastFeed $feed,
        FeedFetchResult $result,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $nextRefreshAt,
        string $lockToken,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE `plugin_podcast_feeds` SET last_checked_at = :checked, '
            . 'last_successful_refresh_at = :successful, next_refresh_at = :next_refresh, etag = :etag, '
            . 'last_modified = :last_modified, last_error = NULL, last_http_status = :http_status, '
            . 'refresh_status = :status, refresh_lock_token = NULL, refresh_lock_expires_at = NULL, '
            . 'updated_at = :updated WHERE id = :id AND refresh_lock_token = :token',
        );
        $formatted = $this->format($checkedAt);
        $statement->execute([
            'checked' => $formatted,
            'successful' => $formatted,
            'next_refresh' => $this->format($nextRefreshAt),
            'etag' => $result->etag ?? $feed->etag,
            'last_modified' => $result->lastModified ?? $feed->lastModified,
            'http_status' => $result->status,
            'status' => 'unchanged',
            'updated' => $formatted,
            'id' => $feed->id,
            'token' => $lockToken,
        ]);
    }

    public function storeRefreshFailure(
        int $feedId,
        string $message,
        ?int $httpStatus,
        DateTimeImmutable $checkedAt,
        DateTimeImmutable $nextRefreshAt,
        string $lockToken,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE `plugin_podcast_feeds` SET last_checked_at = :checked, next_refresh_at = :next_refresh, '
            . 'last_error = :error, last_http_status = COALESCE(:http_status, last_http_status), '
            . 'refresh_status = :status, '
            . 'refresh_lock_token = NULL, refresh_lock_expires_at = NULL, updated_at = :updated '
            . 'WHERE id = :id AND refresh_lock_token = :token',
        );
        $formatted = $this->format($checkedAt);
        $statement->execute([
            'checked' => $formatted,
            'next_refresh' => $this->format($nextRefreshAt),
            'error' => $message,
            'http_status' => $httpStatus,
            'status' => 'error',
            'updated' => $formatted,
            'id' => $feedId,
            'token' => $lockToken,
        ]);
    }

    private function oneFeed(string $where, int|string $value): ?PodcastFeed
    {
        $statement = $this->pdo->prepare('SELECT * FROM `plugin_podcast_feeds` WHERE ' . $where . ' LIMIT 1');
        $statement->execute(['value' => $value]);
        $row = $statement->fetch();
        return is_array($row) ? $this->hydrateFeed($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrateFeed(array $row): PodcastFeed
    {
        return new PodcastFeed(
            (int) $row['id'],
            (string) $row['slug'],
            (string) $row['rss_url'],
            (bool) $row['enabled'],
            $row['refresh_interval'] === null ? null : (int) $row['refresh_interval'],
            (bool) $row['automatic_refresh'],
            (string) $row['title'],
            (string) $row['description'],
            (string) $row['link'],
            (string) $row['language'],
            (string) $row['author'],
            (string) $row['image_url'],
            (bool) $row['explicit'],
            $this->date($row['last_checked_at']),
            $this->date($row['last_successful_refresh_at']),
            $this->date($row['last_changed_at']),
            $this->date($row['next_refresh_at']),
            is_string($row['etag']) ? $row['etag'] : null,
            is_string($row['last_modified']) ? $row['last_modified'] : null,
            is_string($row['last_error']) ? $row['last_error'] : null,
            $row['last_http_status'] === null ? null : (int) $row['last_http_status'],
            (string) $row['refresh_status'],
            is_string($row['content_hash']) ? $row['content_hash'] : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateEpisode(array $row): PodcastEpisode
    {
        return new PodcastEpisode(
            (int) $row['id'],
            (int) $row['feed_id'],
            (string) $row['feed_slug'],
            (string) $row['feed_title'],
            (string) $row['guid'],
            (string) $row['slug'],
            (string) $row['title'],
            (string) $row['description'],
            (string) $row['content'],
            (string) $row['link'],
            (string) $row['audio_url'],
            $row['audio_length'] === null ? null : (int) $row['audio_length'],
            (string) $row['audio_type'],
            $row['duration_seconds'] === null ? null : (int) $row['duration_seconds'],
            (string) $row['image_url'],
            (bool) $row['explicit'],
            (string) $row['episode_type'],
            $this->date($row['published_at']),
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }

    private function format(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }

    private function boundedInt(?string $value, int $minimum, int $maximum, int $default): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return is_int($number) && $number >= $minimum && $number <= $maximum ? $number : $default;
    }

    private function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
