<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMNode;
use JsonException;
use ReaCms\Content\Slugger;
use ReaCms\Plugin\SafeHtml;

final class PodcastFeedParser
{
    private const ITUNES = 'http://www.itunes.com/dtds/podcast-1.0.dtd';
    private const CONTENT = 'http://purl.org/rss/1.0/modules/content/';

    public function parse(string $xml): ParsedPodcast
    {
        if (trim($xml) === '' || stripos($xml, '<!DOCTYPE') !== false) {
            throw new PodcastException('The RSS response is empty or contains a prohibited document type.');
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOBLANKS);
            $errors = libxml_get_errors();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            $message = isset($errors[0]) ? trim($errors[0]->message) : 'Malformed XML.';
            throw new PodcastException('The RSS feed is invalid: ' . $message);
        }
        $channel = $document->getElementsByTagName('channel')->item(0);
        if (!$channel instanceof DOMElement || $document->documentElement?->localName !== 'rss') {
            throw new PodcastException('The document is not an RSS podcast feed.');
        }
        $title = $this->limit($this->childText($channel, 'title'), 255);
        if ($title === '') {
            throw new PodcastException('The podcast RSS channel has no title.');
        }
        $episodes = [];
        $slugs = [];
        foreach ($channel->childNodes as $node) {
            if (!$node instanceof DOMElement || $node->localName !== 'item') {
                continue;
            }
            $episode = $this->episode($node, $slugs);
            if ($episode !== null) {
                $episodes[] = $episode;
            }
        }
        $payload = [
            'title' => $title,
            'description' => $this->limit(
                SafeHtml::sanitize($this->childText($channel, 'description'))->value,
                65_000,
            ),
            'link' => $this->limit($this->childText($channel, 'link'), 1000),
            'language' => $this->limit($this->childText($channel, 'language'), 35),
            'author' => $this->limit($this->namespaceText($channel, self::ITUNES, 'author'), 255),
            'image' => $this->limit($this->channelImage($channel), 1000),
            'explicit' => $this->explicit($this->namespaceText($channel, self::ITUNES, 'explicit')),
            'episodes' => array_map(static fn (ParsedPodcastEpisode $episode): array => [
                $episode->guid,
                $episode->slug,
                $episode->title,
                $episode->description,
                $episode->content,
                $episode->link,
                $episode->audioUrl,
                $episode->audioLength,
                $episode->audioType,
                $episode->durationSeconds,
                $episode->imageUrl,
                $episode->explicit,
                $episode->episodeType,
                $episode->publishedAt?->format(DATE_ATOM),
            ], $episodes),
        ];
        try {
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            throw new PodcastException('The normalized podcast data could not be hashed.', previous: $exception);
        }
        return new ParsedPodcast(
            $payload['title'],
            $payload['description'],
            $payload['link'],
            $payload['language'],
            $payload['author'],
            $payload['image'],
            $payload['explicit'],
            $episodes,
            $hash,
        );
    }

    /** @param array<string, true> $slugs */
    private function episode(DOMElement $item, array &$slugs): ?ParsedPodcastEpisode
    {
        $title = $this->limit($this->childText($item, 'title'), 255);
        $guid = $this->limit($this->childText($item, 'guid'), 1000);
        $link = $this->limit($this->childText($item, 'link'), 1000);
        if ($title === '' || ($guid === '' && $link === '')) {
            return null;
        }
        $guid = $guid !== '' ? $guid : $link;
        $slug = (new Slugger())->slug($title);
        if (isset($slugs[$slug])) {
            $slug .= '-' . substr(hash('sha256', $guid), 0, 8);
        }
        $slugs[$slug] = true;
        $enclosure = $this->directChild($item, 'enclosure');
        $published = $this->date($this->childText($item, 'pubDate'));
        $description = $this->limit(SafeHtml::sanitize($this->childText($item, 'description'))->value, 65_000);
        $content = $this->limit(
            SafeHtml::sanitize($this->namespaceText($item, self::CONTENT, 'encoded'))->value,
            65_000,
        );
        if ($content === '') {
            $content = $description;
        }
        return new ParsedPodcastEpisode(
            $guid,
            $slug,
            $title,
            $description,
            $content,
            $link,
            $this->limit($enclosure?->getAttribute('url') ?? '', 1000),
            $this->positiveInteger($enclosure?->getAttribute('length')),
            $this->limit($enclosure?->getAttribute('type') ?? '', 191),
            $this->duration($this->namespaceText($item, self::ITUNES, 'duration')),
            $this->limit($this->itunesImage($item), 1000),
            $this->explicit($this->namespaceText($item, self::ITUNES, 'explicit')),
            $this->limit($this->namespaceText($item, self::ITUNES, 'episodeType') ?: 'full', 32),
            $published,
        );
    }

    private function childText(DOMElement $parent, string $name): string
    {
        $child = $this->directChild($parent, $name);
        return $child === null ? '' : trim($child->textContent);
    }

    private function directChild(DOMElement $parent, string $name): ?DOMElement
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $name && $node->namespaceURI === null) {
                return $node;
            }
        }
        return null;
    }

    private function namespaceText(DOMElement $parent, string $namespace, string $name): string
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === $name && $node->namespaceURI === $namespace) {
                return trim($node->textContent);
            }
        }
        return '';
    }

    private function channelImage(DOMElement $channel): string
    {
        $itunes = $this->itunesImage($channel);
        if ($itunes !== '') {
            return $itunes;
        }
        $image = $this->directChild($channel, 'image');
        return $image === null ? '' : $this->childText($image, 'url');
    }

    private function itunesImage(DOMElement $parent): string
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === 'image' && $node->namespaceURI === self::ITUNES) {
                return trim($node->getAttribute('href'));
            }
        }
        return '';
    }

    private function explicit(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', 'yes', 'explicit'], true);
    }

    private function date(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function positiveInteger(?string $value): ?int
    {
        return is_string($value) && ctype_digit($value) ? (int) $value : null;
    }

    private function duration(string $value): ?int
    {
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $parts = array_map('intval', explode(':', $value));
        if (count($parts) === 2) {
            return ($parts[0] * 60) + $parts[1];
        }
        if (count($parts) === 3) {
            return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
        }
        return null;
    }

    private function limit(string $value, int $bytes): string
    {
        return mb_strcut($value, 0, $bytes, 'UTF-8');
    }
}
