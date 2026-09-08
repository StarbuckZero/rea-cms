<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use DateTimeImmutable;
use DateTimeZone;

final class PodcastEpisode
{
    public function __construct(
        public readonly int $id,
        public readonly int $feedId,
        public readonly string $feedSlug,
        public readonly string $feedTitle,
        public readonly string $guid,
        public readonly string $slug,
        public readonly string $title,
        public readonly string $description,
        public readonly string $content,
        public readonly string $link,
        public readonly string $audioUrl,
        public readonly ?int $audioLength,
        public readonly string $audioType,
        public readonly ?int $durationSeconds,
        public readonly string $imageUrl,
        public readonly bool $explicit,
        public readonly string $episodeType,
        public readonly ?DateTimeImmutable $publishedAt,
    ) {
    }

    /** @return array<string, mixed> */
    public function api(
        ?string $timezone = null,
        string $defaultTimezone = PodcastSchedule::APPLICATION_DEFAULT_TIMEZONE,
    ): array {
        $schedule = new PodcastSchedule();
        $timezone = trim($timezone ?? '');
        $zone = new DateTimeZone(
            $schedule->validTimezone($timezone) ? $timezone : $schedule->defaultTimezone($defaultTimezone),
        );
        $published = $this->publishedAt?->setTimezone($zone);
        return [
            'id' => $this->id,
            'feed' => ['id' => $this->feedId, 'slug' => $this->feedSlug, 'title' => $this->feedTitle],
            'guid' => $this->guid,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'link' => $this->link,
            'audio' => [
                'url' => $this->audioUrl,
                'length' => $this->audioLength,
                'type' => $this->audioType,
                'durationSeconds' => $this->durationSeconds,
                'durationFormatted' => $this->formattedDuration(),
            ],
            'imageUrl' => $this->imageUrl,
            'explicit' => $this->explicit,
            'episodeType' => $this->episodeType,
            'publishedAt' => $this->publishedAt?->format(DATE_ATOM),
            'publishedDate' => $published?->format('F j, Y') ?? '',
            'publishedTime' => $published?->format('g:i A') ?? '',
        ];
    }

    private function formattedDuration(): string
    {
        if ($this->durationSeconds === null || $this->durationSeconds < 0) {
            return '';
        }
        $hours = intdiv($this->durationSeconds, 3600);
        $minutes = intdiv($this->durationSeconds % 3600, 60);
        $minuteLabel = $minutes . ($minutes === 1 ? ' minute' : ' minutes');
        return $hours === 0 ? $minuteLabel : $hours . ($hours === 1 ? ' hour ' : ' hours ') . $minuteLabel;
    }
}
