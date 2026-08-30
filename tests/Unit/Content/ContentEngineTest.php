<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Content;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReaCms\Content\ContentException;
use ReaCms\Content\ContentImporter;
use ReaCms\Content\ContentValidator;
use ReaCms\Content\Lifecycle;
use ReaCms\Content\ResourceDefinition;

final class ContentEngineTest extends TestCase
{
    public function testResourceCannotEscapePluginNamespace(): void
    {
        $this->expectException(ContentException::class);
        new ResourceDefinition('blog', 'posts', 'rea_users', ['title' => 'string'], ['title'], []);
    }

    public function testUnknownAndMistypedFieldsAreRejected(): void
    {
        $definition = $this->definition();
        $validator = new ContentValidator();

        $this->expectException(ContentException::class);
        $validator->validate($definition, ['title' => 'Hello', 'secret' => true]);
    }

    public function testDraftAndFutureContentAreNeverPublic(): void
    {
        $lifecycle = new Lifecycle();
        $now = new DateTimeImmutable('2026-08-29T12:00:00+00:00');

        self::assertFalse($lifecycle->publiclyVisible('draft', null, $now));
        self::assertFalse($lifecycle->publiclyVisible('published', $now->modify('+1 hour'), $now));
        self::assertTrue($lifecycle->publiclyVisible('published', $now->modify('-1 second'), $now));
    }

    public function testSchedulingConvertsSiteTimezoneToUtc(): void
    {
        $scheduled = (new Lifecycle())->scheduleUtc('2026-08-29 09:30:00', 'America/New_York');

        self::assertSame('2026-08-29 13:30:00', $scheduled->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $scheduled->getTimezone()->getName());
    }

    public function testImportValidationOnlyPerformsNoWrites(): void
    {
        $writes = 0;
        $result = (new ContentImporter())->import(
            $this->definition(),
            '[{"title":"Validated"}]',
            true,
            static function (array $record) use (&$writes): void {
                $writes++;
            },
        );

        self::assertSame(['valid' => 1, 'persisted' => 0], $result);
        self::assertSame(0, $writes);
    }

    private function definition(): ResourceDefinition
    {
        return new ResourceDefinition(
            'blog',
            'posts',
            'plugin_blog_posts',
            ['title' => 'string'],
            ['title'],
            ['blog.posts.update'],
        );
    }
}
