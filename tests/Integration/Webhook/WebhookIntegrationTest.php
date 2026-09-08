<?php

declare(strict_types=1);

namespace ReaCms\Tests\Integration\Webhook;

use PDO;
use PHPUnit\Framework\TestCase;
use ReaCms\Cms\PdoCmsRepository;
use ReaCms\Database\Migrations\CoreMigrationRunner;
use ReaCms\Database\Migrations\PdoMigrationDatabase;
use ReaCms\Jobs\PdoJobQueue;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PdoPluginMigrationRunner;
use ReaCms\Plugin\PdoPluginRegistry;
use ReaCms\Plugin\StagedPackage;
use ReaCms\Security\SecretCipher;
use ReaCms\TextBlock\PdoTextBlockRepository;
use ReaCms\Webhook\ContentWebhookRecorder;
use ReaCms\Webhook\DestinationValidator;
use ReaCms\Webhook\PdoWebhookRepository;
use ReaCms\Webhook\WebhookDelivery;
use ReaCms\Webhook\WebhookEvents;
use ReaCms\Webhook\WebhookException;
use ReaCms\Webhook\WebhookSigner;
use ReaCms\Webhook\WebhookWorker;
use RuntimeException;

/** Uses a newly created disposable database; never reads the application's .env. */
final class WebhookIntegrationTest extends TestCase
{
    private PDO $pdo;
    private string $database;
    private PdoJobQueue $queue;
    private PdoWebhookRepository $hooks;
    private PdoTextBlockRepository $blocks;
    private PdoCmsRepository $cms;
    private DestinationValidator $destinations;
    private ContentWebhookRecorder $recorder;
    private int $hookId;
    private string $secret;

    protected function setUp(): void
    {
        $socket = getenv('REA_WEBHOOK_TEST_SOCKET');
        if (!is_string($socket) || $socket === '') {
            self::markTestSkipped('Set REA_WEBHOOK_TEST_SOCKET to a disposable MariaDB/MySQL server socket.');
        }
        $this->pdo = new PDO('mysql:unix_socket=' . $socket . ';charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->database = 'rea_webhook_test_' . bin2hex(random_bytes(8));
        $this->pdo->exec('CREATE DATABASE `' . $this->database . '`');
        $this->pdo->exec('USE `' . $this->database . '`');
        $root = dirname(__DIR__, 3);
        $migrations = new CoreMigrationRunner(
            new PdoMigrationDatabase($this->pdo),
            $root . '/database/migrations',
            'test_'
        );
        $migrations->migrate();
        foreach (['blog', 'gallery', 'text_block'] as $plugin) {
            $directory = $root . '/plugins/' . $plugin;
            $manifest = (new ManifestValidator())->validate(file_get_contents($directory . '/plugin.json'));
            $package = new StagedPackage($manifest, $directory, str_repeat('a', 64));
            (new PdoPluginRegistry($this->pdo, 'test_'))->install($package);
            (new PdoPluginMigrationRunner($this->pdo, prefix: 'test_'))->apply($package);
        }
        $this->queue = new PdoJobQueue($this->pdo, 'test_');
        $this->hooks = new PdoWebhookRepository(
            $this->pdo,
            $this->queue,
            new SecretCipher(str_repeat('k', 32)),
            'test_'
        );
        $this->recorder = new ContentWebhookRecorder($this->pdo, $this->hooks);
        $this->blocks = new PdoTextBlockRepository($this->pdo, $this->recorder);
        $this->cms = new PdoCmsRepository($this->pdo, 'test_', $this->recorder);
        $this->destinations = new DestinationValidator(static fn (): array => ['93.184.216.34']);
        $created = $this->hooks->create(
            'Test website',
            'https://example.com/hook',
            WebhookEvents::all(),
            $this->destinations
        );
        $this->hookId = $created['id'];
        $this->secret = $created['secret'];
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo, $this->database)) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->pdo->exec('DROP DATABASE `' . $this->database . '`');
        }
    }

    public function testTextBlockLifecycleAndRenamesDoNotLeakContent(): void
    {
        $block = $this->blocks->create('old-name', 'Private-looking body');
        $this->blocks->update($block->id, 'new-name', 'Updated body');
        $this->blocks->delete($block->id);
        $events = $this->events();
        self::assertSame(
            ['text_block.block.created', 'text_block.block.updated', 'text_block.block.deleted'],
            array_column($events, 'event')
        );
        self::assertSame('old-name', $events[1]['data']['before']['name']);
        self::assertSame('new-name', $events[1]['data']['after']['name']);
        self::assertNull($events[2]['data']['after']);
        self::assertStringNotContainsString('body', json_encode($events));
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM test_jobs')->fetchColumn());
    }

    public function testBlogPublicationAndVisibilityTransitions(): void
    {
        $values = ['title' => 'Title', 'slug' => 'old-slug', 'excerpt' => '', 'content' => 'Secret draft',
            'status' => 'draft', 'visibility' => 'public', 'locale' => 'en', 'featured_media_id' => null,
            'publish_at' => null];
        $id = $this->cms->saveBlog(null, 1, $values);
        $this->cms->saveBlog($id, 1, [...$values, 'status' => 'published', 'slug' => 'new-slug']);
        $this->cms->saveBlog($id, 1, [...$values, 'status' => 'published', 'visibility' => 'private']);
        $this->cms->deleteBlog($id);
        $events = $this->events();
        self::assertSame(['blog.post.created', 'blog.post.updated', 'blog.post.published',
            'blog.post.updated', 'blog.post.unpublished', 'blog.post.deleted'], array_column($events, 'event'));
        self::assertSame('old-slug', $events[1]['data']['before']['slug']);
        self::assertSame('new-slug', $events[1]['data']['after']['slug']);
        self::assertStringNotContainsString('Secret draft', json_encode($events));
    }

    public function testGalleryAlbumsItemsMetadataReorderAndUnassignment(): void
    {
        $albumValues = ['title' => 'Album', 'slug' => 'album', 'description' => '', 'status' => 'draft',
            'cover_media_id' => null, 'position' => 0];
        $album = $this->cms->saveGalleryAlbum(null, $albumValues);
        $this->cms->saveGalleryAlbum($album, [...$albumValues, 'status' => 'published']);
        $this->pdo->exec(
            "INSERT INTO test_media (stored_name, original_name, mime_type, file_size, file_hash, "
            . "visibility, caption, description) VALUES "
            . "('image', 'image.jpg', 'image/jpeg', 1, 'hash', 'public', '', '')"
        );
        $media = (int) $this->pdo->lastInsertId();
        $itemValues = ['album_id' => $album, 'media_id' => $media, 'media_type' => 'image',
            'title' => 'Image', 'caption' => '', 'alt_text' => '', 'position' => 0, 'status' => 'active'];
        $item = $this->cms->saveGallery(null, $itemValues);
        $this->cms->saveGalleryImageMetadata($item, $media, 'renamed.jpg', 'New alt');
        $this->cms->reorderGalleryAlbum($album, [$item => 3]);
        $this->cms->deleteGalleryAlbum($album);
        $this->cms->deleteGallery($item);
        $events = $this->events();
        self::assertSame(
            ['gallery.album.created', 'gallery.album.updated', 'gallery.album.published',
            'gallery.item.created', 'gallery.item.updated', 'gallery.album.reordered', 'gallery.album.deleted',
            'gallery.album.unpublished', 'gallery.item.updated', 'gallery.item.deleted'],
            array_column($events, 'event')
        );
        self::assertSame($album, $events[8]['data']['before']['album_id']);
        self::assertSame(0, $events[8]['data']['after']['album_id']);
    }

    public function testFailedSaveAndOuterRollbackDoNotLeaveNotifications(): void
    {
        $this->pdo->beginTransaction();
        $this->blocks->create('rolled-back', 'Content');
        $this->pdo->rollBack();
        self::assertSame([], $this->events());
        self::assertNull($this->blocks->findByName('rolled-back'));
        // Simulate a queue write failure after the content and delivery inserts.
        $this->pdo->exec('DROP TABLE test_jobs');
        try {
            $this->blocks->create('failed-queue', 'Content');
            self::fail('A queue write failure must fail the content transaction.');
        } catch (\PDOException) {
            self::assertNull($this->blocks->findByName('failed-queue'));
            self::assertSame([], $this->events());
        }
    }

    public function testGallerySelectionRollsBackNotificationsWithSecondItemFailure(): void
    {
        $this->pdo->exec(
            "INSERT INTO test_media (stored_name, original_name, mime_type, file_size, file_hash, "
            . "caption, description) VALUES ('image', 'image.jpg', 'image/jpeg', 1, 'hash', '', '')"
        );
        $media = (int) $this->pdo->lastInsertId();
        $values = ['album_id' => 0, 'media_id' => $media, 'media_type' => 'image', 'title' => '',
            'caption' => '', 'alt_text' => '', 'position' => 0, 'status' => 'active'];
        try {
            $this->cms->saveGallerySelection(null, [$values, [...$values, 'media_id' => 999999]]);
            self::fail('Missing media must fail the second item.');
        } catch (\PDOException) {
            self::assertSame([], $this->events());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM plugin_gallery_items')->fetchColumn());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM test_jobs')->fetchColumn());
        }
    }

    public function testWorkerSignsExactBodyAndPinsValidatedAddress(): void
    {
        $this->hooks->test($this->hookId);
        $worker = $this->worker(function ($url, $headers, $body, $timeout, $maximum, $addresses): array {
            self::assertSame(['93.184.216.34'], $addresses);
            self::assertSame(5, $timeout);
            self::assertTrue((new WebhookSigner())->verify(
                $this->secret,
                $headers['X-Rea-Timestamp'],
                $headers['X-Rea-Delivery'],
                $body,
                $headers['X-Rea-Signature'],
                time()
            ));
            self::assertSame('webhook.test', json_decode($body, true)['event']);
            return ['status' => 204, 'body' => ''];
        });
        self::assertTrue($worker->runOne());
        self::assertFalse($worker->runOne());
        $history = $this->hooks->history()[0];
        self::assertSame('delivered', $history['delivery_status']);
        self::assertSame(1, $history['attempts']);
        self::assertSame(204, $history['response_status']);
        self::assertNotNull($history['delivered_at']);
        $stored = $this->pdo->query('SELECT secret_ciphertext FROM test_webhooks')->fetchColumn();
        self::assertStringNotContainsString($this->secret, $stored);
    }

    public function testFailuresRetryWithStableIdsAndManualRetryAfterExhaustion(): void
    {
        $this->hooks->test($this->hookId);
        $ids = [];
        $worker = $this->worker(static function ($url, $headers) use (&$ids): array {
            $ids[] = $headers['X-Rea-Delivery'];
            return ['status' => 503, 'body' => 'Do not retain private receiver diagnostics'];
        });
        for ($i = 0; $i < 3; $i++) {
            $this->pdo->exec('UPDATE test_jobs SET available_at=UTC_TIMESTAMP(6)');
            self::assertTrue($worker->runOne());
            if ($i < 2) {
                self::assertSame('retrying', $this->hooks->history()[0]['delivery_status']);
                self::assertFalse($worker->runOne());
            }
        }
        self::assertCount(1, array_unique($ids));
        self::assertSame('failed', $this->hooks->history()[0]['delivery_status']);
        self::assertSame(3, $this->hooks->history()[0]['attempts']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM test_failed_jobs')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM test_jobs')->fetchColumn());
        $this->hooks->retry($ids[0]);
        self::assertTrue($this->worker(static fn (): array => ['status' => 200, 'body' => ''])->runOne());
        self::assertSame('delivered', $this->hooks->history()[0]['delivery_status']);
        self::assertSame(4, $this->hooks->history()[0]['attempts']);
        self::assertNull($this->hooks->delivery($ids[0])['response_excerpt']);
    }

    public function testDisabledHooksCancelQueuedWorkAndEventSubscriptionsFilter(): void
    {
        $this->hooks->configure($this->hookId, ['text_block.block.created'], true);
        $block = $this->blocks->create('block', 'Content');
        $this->blocks->update($block->id, 'block', 'Updated');
        self::assertCount(1, $this->events());
        $this->hooks->configure($this->hookId, ['text_block.block.created'], false);
        $this->blocks->create('other', 'Content');
        self::assertCount(1, $this->events());
        $this->worker(static function (): array {
            self::fail('Disabled hooks must not send requests.');
        })->runOne();
        self::assertSame('cancelled', $this->hooks->history()[0]['delivery_status']);
    }

    public function testTransportErrorsNeverPersistSecretsAndDuplicateJobsDoNotRedeliver(): void
    {
        $this->hooks->test($this->hookId);
        $worker = $this->worker(function (): array {
            throw new RuntimeException('Sensitive ' . $this->secret);
        });
        $this->pdo->exec('UPDATE test_jobs SET max_attempts=1');
        $worker->runOne();
        self::assertSame(
            'Webhook delivery failed.',
            $this->pdo->query('SELECT failure_reason FROM test_failed_jobs')->fetchColumn()
        );
        $id = $this->hooks->history()[0]['delivery_id'];
        $this->hooks->retry($id);
        $this->worker(static fn (): array => ['status' => 200, 'body' => ''])->runOne();
        $this->queue->push('webhooks', 'webhook.deliver', ['delivery_id' => $id]);
        $this->worker(static function (): array {
            self::fail('A delivered ID must not send again.');
        })->runOne();
        self::assertSame(2, $this->hooks->history()[0]['attempts']);
    }

    public function testUnsupportedEventsAndRetryingPendingDeliveryAreRejected(): void
    {
        try {
            $this->hooks->configure($this->hookId, ['arbitrary.event'], true);
            self::fail('Unsupported events must be rejected.');
        } catch (WebhookException) {
            self::assertCount(1, $this->hooks->all());
        }
        $this->hooks->test($this->hookId);
        $this->expectException(WebhookException::class);
        $this->hooks->retry($this->hooks->history()[0]['delivery_id']);
    }

    /** @return list<array<string, mixed>> */
    private function events(): array
    {
        return array_map(
            static fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR),
            $this->pdo->query('SELECT payload_json FROM test_webhook_deliveries ORDER BY created_at, delivery_id')
            ->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    private function worker(callable $send): WebhookWorker
    {
        return new WebhookWorker(
            $this->queue,
            $this->hooks,
            new WebhookDelivery($this->destinations, new WebhookSigner(), $send)
        );
    }
}
