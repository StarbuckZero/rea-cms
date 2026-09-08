<?php

declare(strict_types=1);

namespace ReaCms\Webhook;

use PDO;
use ReaCms\Core\Configuration\Environment;
use ReaCms\Jobs\PdoJobQueue;
use ReaCms\Security\SecretCipher;

final class WebhookFactory
{
    public static function repository(PDO $pdo, Environment $environment): PdoWebhookRepository
    {
        $prefix = $environment->get('DB_TABLE_PREFIX', 'rea_') ?? 'rea_';
        return new PdoWebhookRepository(
            $pdo,
            new PdoJobQueue($pdo, $prefix),
            new SecretCipher($environment->require('APP_KEY')),
            $prefix
        );
    }

    public static function recorder(PDO $pdo, Environment $environment): ContentWebhookRecorder
    {
        return new ContentWebhookRecorder($pdo, self::repository($pdo, $environment));
    }

    public static function destinations(): DestinationValidator
    {
        return new DestinationValidator(static function (string $host): array {
            $host = trim($host, '[]');
            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                return [$host];
            }
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            $addresses = [];
            foreach ($records === false ? [] : $records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($address)) {
                    $addresses[] = $address;
                }
            }
            return $addresses;
        });
    }
}
