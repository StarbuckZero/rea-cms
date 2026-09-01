<?php

declare(strict_types=1);

namespace ReaCms\Podcast;

final class CurlFeedFetcher implements FeedFetcher
{
    public function __construct(private readonly PodcastUrlGuard $guard = new PodcastUrlGuard())
    {
    }

    public function fetch(PodcastFeed $feed, PodcastSettings $settings): FeedFetchResult
    {
        $url = $feed->rssUrl;
        for ($redirects = 0; $redirects <= 5; $redirects++) {
            $target = $this->guard->validate($url);
            $response = $this->request($target, $feed, $settings);
            if ($response['location'] === null) {
                if ($response['status'] === 304) {
                    return new FeedFetchResult(304, etag: $response['etag'], lastModified: $response['modified']);
                }
                if ($response['status'] !== 200) {
                    throw new PodcastFetchException(
                        'The podcast feed returned HTTP ' . $response['status'] . '.',
                        $response['status'],
                    );
                }
                return new FeedFetchResult(
                    200,
                    $response['body'],
                    $response['etag'],
                    $response['modified'],
                );
            }
            $url = $this->redirectUrl($url, $response['location']);
        }
        throw new PodcastException('The podcast feed redirected too many times.');
    }

    /**
     * @param array{url: string, host: string, port: int, address: string} $target
     * @return array{status: int, body: string, etag: ?string, modified: ?string, location: ?string}
     */
    private function request(array $target, PodcastFeed $feed, PodcastSettings $settings): array
    {
        $curl = curl_init($target['url']);
        if ($curl === false) {
            throw new PodcastException('The podcast feed request could not be initialized.');
        }
        $body = '';
        $headers = [];
        $tooLarge = false;
        $requestHeaders = ['Accept: application/rss+xml, application/xml;q=0.9, text/xml;q=0.8'];
        if ($feed->etag !== null && $feed->etag !== '') {
            $requestHeaders[] = 'If-None-Match: ' . str_replace(["\r", "\n"], '', $feed->etag);
        }
        if ($feed->lastModified !== null && $feed->lastModified !== '') {
            $requestHeaders[] = 'If-Modified-Since: ' . str_replace(["\r", "\n"], '', $feed->lastModified);
        }
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(10, $settings->requestTimeoutSeconds),
            CURLOPT_TIMEOUT => $settings->requestTimeoutSeconds,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'ReaCMS-Podcast/1.0',
            CURLOPT_RESOLVE => [sprintf(
                '%s:%d:%s',
                $target['host'],
                $target['port'],
                $target['address'],
            )],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                $length = strlen($line);
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function (
                $handle,
                string $chunk,
            ) use (
                &$body,
                &$tooLarge,
                $settings,
            ): int {
                if (strlen($body) + strlen($chunk) > $settings->maximumDownloadBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $executed = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($tooLarge) {
            throw new PodcastException('The podcast feed exceeds the configured maximum download size.');
        }
        if ($executed === false) {
            throw new PodcastException('The podcast feed could not be downloaded: ' . $error);
        }
        $location = $headers['location'] ?? null;
        if ($status < 300 || $status >= 400) {
            $location = null;
        }
        return [
            'status' => $status,
            'body' => $body,
            'etag' => $headers['etag'] ?? null,
            'modified' => $headers['last-modified'] ?? null,
            'location' => $location,
        ];
    }

    private function redirectUrl(string $current, string $location): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }
        $parts = parse_url($current);
        if (!is_array($parts) || !is_string($parts['scheme'] ?? null) || !is_string($parts['host'] ?? null)) {
            throw new PodcastException('The podcast feed returned an invalid redirect.');
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (is_int($parts['port'] ?? null)) {
            $origin .= ':' . $parts['port'];
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '/';
        return $origin . rtrim(dirname($path), '/') . '/' . $location;
    }
}
