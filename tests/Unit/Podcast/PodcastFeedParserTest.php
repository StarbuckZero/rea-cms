<?php

declare(strict_types=1);

namespace ReaCms\Tests\Unit\Podcast;

use PHPUnit\Framework\TestCase;
use ReaCms\Podcast\PodcastException;
use ReaCms\Podcast\PodcastFeedParser;

final class PodcastFeedParserTest extends TestCase
{
    public function testItNormalizesPodcastAndEpisodeNamespaces(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
 xmlns:content="http://purl.org/rss/1.0/modules/content/">
 <channel>
  <title>Example Show</title><link>https://example.com</link><language>en</language>
  <description>About the show</description><itunes:author>Host</itunes:author>
  <itunes:image href="https://example.com/show.jpg"/><itunes:explicit>true</itunes:explicit>
  <item>
   <title>Episode One</title><guid>episode-1</guid><pubDate>Tue, 18 Aug 2026 04:22:00 +0000</pubDate>
   <link>https://example.com/one</link><description><![CDATA[<p>Summary</p><script>bad()</script>]]></description>
   <content:encoded><![CDATA[<p>Full notes</p>]]></content:encoded>
   <enclosure url="https://example.com/one.mp3" length="1234" type="audio/mpeg"/>
   <itunes:duration>01:02:03</itunes:duration><itunes:episodeType>full</itunes:episodeType>
  </item>
 </channel>
</rss>
XML;
        $podcast = (new PodcastFeedParser())->parse($xml);

        self::assertSame('Example Show', $podcast->title);
        self::assertSame('Host', $podcast->author);
        self::assertTrue($podcast->explicit);
        self::assertCount(1, $podcast->episodes);
        self::assertSame('episode-one', $podcast->episodes[0]->slug);
        self::assertSame(3723, $podcast->episodes[0]->durationSeconds);
        self::assertSame('https://example.com/one.mp3', $podcast->episodes[0]->audioUrl);
        self::assertStringNotContainsString('script', $podcast->episodes[0]->description);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $podcast->contentHash);
    }

    public function testItRejectsDoctypesAndNonRssDocuments(): void
    {
        $parser = new PodcastFeedParser();
        foreach (['<!DOCTYPE rss><rss><channel><title>x</title></channel></rss>', '<feed/>'] as $xml) {
            try {
                $parser->parse($xml);
                self::fail('Invalid XML was accepted.');
            } catch (PodcastException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
