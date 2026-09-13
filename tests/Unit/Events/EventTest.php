<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Events;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReaCms\Events\EventCalendar;
use ReaCms\Events\EventPresenter;
use ReaCms\Events\EventQuery;
use ReaCms\Events\EventValidator;
use ReaCms\Plugin\ManifestValidator;
use ReaCms\Plugin\PackageInspector;
use ReaCms\Plugin\SafeTemplate;

final class EventTest extends TestCase
{
    /** @return array<string,mixed> */
    public static function input(): array
    {
        return ['title' => 'Comedy Night', 'start_date' => '2026-10-10', 'end_date' => '2026-10-10',
            'start_time' => '20:00', 'end_time' => '22:00', 'timezone' => 'America/New_York',
            'description' => '<p>Café, comedy; fun</p><p>Second line</p>', 'published' => true];
    }

    /** @param array<string,mixed> $changes
     * @return array<string,mixed>
     */
    private function event(array $changes = []): array
    {
        $row = (new EventValidator())->validate([...self::input(), ...$changes]);
        return (new EventPresenter())->present([...$row, 'id' => 42, 'type_id' => 3,
            'type_name' => 'Comedy Show', 'type_slug' => 'comedy-show', 'public_image_id' => null,
            'default_image_id' => 7, 'created_at' => '2026-09-01T12:00:00Z', 'updated_at' => '2026-09-02T13:00:00Z']);
    }

    public function testStructuredDatesTypesAndImageFallback(): void
    {
        $event = $this->event();
        self::assertSame('2026-10-10T20:00:00-04:00', $event['startDateTime']);
        self::assertSame('2026-10-10T22:00:00-04:00', $event['endDateTime']);
        self::assertSame(['id' => 3, 'name' => 'Comedy Show', 'slug' => 'comedy-show'], $event['type']);
        self::assertIsArray($event['location']);
        self::assertSame('/media/7', $event['image']);
        self::assertFalse($event['allDay']);
    }

    public function testCalendarEscapingFoldingUnicodeAndStableIdentity(): void
    {
        $event = $this->event(['title' => str_repeat('🎉 Café, ', 15)]);
        $calendar = new EventCalendar();
        $ics = $calendar->render($event, 'https://example.test');
        self::assertStringContainsString('DTSTART:20261011T000000Z', $ics);
        self::assertStringContainsString('DTEND:20261011T020000Z', $ics);
        self::assertStringContainsString('Café\\, comedy\\; fun\\n\\nSecond line', $ics);
        self::assertStringNotContainsString('<p>', $ics);
        foreach (explode("\r\n", $ics) as $line) {
            self::assertLessThanOrEqual(75, strlen($line));
            self::assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
        $changed = $calendar->render(
            [...$event, 'title' => 'Edited', 'updatedAt' => '2026-09-03T12:00:00Z'],
            'https://example.test'
        );
        preg_match('/UID:[^\r]+/', $ics, $first);
        preg_match('/UID:[^\r]+/', $changed, $second);
        self::assertSame($first, $second);
        self::assertStringContainsString('SUMMARY:Edited', $changed);
    }

    public function testAllDayEndIsExclusiveAcrossDaylightSavingBoundary(): void
    {
        $event = $this->event(['start_date' => '2026-03-07', 'end_date' => '2026-03-08', 'all_day' => true]);
        self::assertNull($event['startTime']);
        self::assertSame('2026-03-09T00:00:00-04:00', $event['endDateTime']);
        $ics = (new EventCalendar())->render($event, 'https://example.test');
        self::assertStringContainsString('DTSTART;VALUE=DATE:20260307', $ics);
        self::assertStringContainsString('DTEND;VALUE=DATE:20260309', $ics);
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function invalidEvents(): iterable
    {
        yield 'invalid date' => [['start_date' => '2026-02-30']];
        yield 'reverse interval' => [['end_time' => '19:00']];
        yield 'missing timed end' => [['end_time' => '']];
        yield 'bad zone' => [['timezone' => 'Moon/Crater']];
        yield 'DST gap' => [['start_date' => '2026-03-08', 'end_date' => '2026-03-08', 'start_time' => '02:30']];
        yield 'DST repeated hour' => [
            ['start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'start_time' => '01:30'],
        ];
        yield 'unsafe URL' => [['url' => 'javascript:alert(1)']];
        yield 'array title' => [['title' => []]];
    }

    /** @param array<string,mixed> $changes */
    #[DataProvider('invalidEvents')]
    public function testInvalidEventsAreRejected(array $changes): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new EventValidator())->validate([...self::input(), ...$changes]);
    }

    public function testCombinedQueryAndPagination(): void
    {
        $query = EventQuery::parse(['type' => 'comedy-show', 'start' => '2026-10-01', 'end' => '2026-10-31',
            'sort' => 'name-desc', 'page' => '2', 'perPage' => '500']);
        self::assertSame(100, $query->perPage);
        self::assertSame('comedy-show', $query->filters['type']);
        self::assertSame('name-desc', $query->sort);
        self::assertSame([], EventQuery::parse(['type' => '', 'start' => '', 'end' => ''])->filters);
        $this->expectException(InvalidArgumentException::class);
        EventQuery::parse(['past' => 'true', 'upcoming' => 'true']);
    }

    public function testPackageAndEveryTemplateAreAcceptedByExistingPlatform(): void
    {
        $root = dirname(__DIR__, 3) . '/plugins/events';
        $package = (new PackageInspector(new ManifestValidator()))->inspectDirectory($root);
        self::assertSame('events', $package->manifest->id);
        foreach (glob($root . '/templates/api/*') as $file) {
            $template = file_get_contents($file);
            $context = ['event' => [...$this->event(), 'type' => 'Comedy', 'location' => 'Orlando']];
            $output = str_ends_with($file, '.txt')
                ? (new SafeTemplate())->renderText($template, $context)
                : (new SafeTemplate())->render($template, $context);
            self::assertStringContainsString('Comedy Night', $output);
            if (str_ends_with($file, '.txt')) {
                self::assertStringNotContainsString('<p>', $output);
            }
        }
    }
}
