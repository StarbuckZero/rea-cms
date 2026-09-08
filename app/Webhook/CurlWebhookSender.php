<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

final class CurlWebhookSender
{
    /** @param array<string, string> $headers
     * @param list<string> $addresses
     * @return array{status: int, body: string}
     */
    public function __invoke(
        string $url,
        array $headers,
        string $body,
        int $timeout,
        int $maximumBytes,
        array $addresses,
    ): array {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $address = $addresses[0] ?? '';
        if ($address === '') {
            throw new WebhookException('No validated webhook address is available.');
        }
        $curl = curl_init($url);
        if ($curl === false) {
            throw new WebhookException('Could not initialize webhook delivery.');
        }
        $response = '';
        $responseHeaders = 0;
        $requestHeaders = [];
        foreach ($headers as $name => $value) {
            $requestHeaders[] = $name . ': ' . $value;
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROXY => '',
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'ReaCMS-Webhooks/1.0',
            CURLOPT_RESOLVE => [$host . ':443:' . (str_contains($address, ':') ? '[' . $address . ']' : $address)],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $responseHeaders += strlen($line);
                return $responseHeaders > 16_384 ? 0 : strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response, $maximumBytes): int {
                if (strlen($response) + strlen($chunk) > $maximumBytes) {
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            },
        ]);
        $success = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($success === false) {
            // Do not expose URLs, secrets, response bodies or transport diagnostics in job logs.
            throw new WebhookException('Webhook connection failed, timed out, or exceeded response limits.');
        }
        return ['status' => $status, 'body' => $response];
    }
}
