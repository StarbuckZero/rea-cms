CREATE TABLE IF NOT EXISTS `{{prefix}}webhooks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(191) NOT NULL,
    `url` VARCHAR(2048) NOT NULL,
    `secret_ciphertext` TEXT NOT NULL,
    `events_json` JSON NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}webhook_deliveries` (
    `delivery_id` CHAR(32) NOT NULL PRIMARY KEY,
    `webhook_id` BIGINT UNSIGNED NOT NULL,
    `event_type` VARCHAR(191) NOT NULL,
    `payload_json` JSON NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `response_status` SMALLINT UNSIGNED NULL,
    `response_excerpt` VARCHAR(1000) NULL,
    `delivered_at` TIMESTAMP(6) NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY `webhook_deliveries_hook_index` (`webhook_id`, `created_at`),
    CONSTRAINT `webhook_deliveries_hook_fk` FOREIGN KEY (`webhook_id`) REFERENCES `{{prefix}}webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}backup_records` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `scope` VARCHAR(32) NOT NULL,
    `private_path` VARCHAR(500) NOT NULL,
    `checksum` CHAR(64) NOT NULL,
    `status` VARCHAR(32) NOT NULL,
    `metadata_json` JSON NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `verified_at` TIMESTAMP(6) NULL,
    KEY `backup_records_created_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `{{prefix}}permissions` (`permission_key`, `description`) VALUES
    ('core.backups.manage', 'Create, verify, restore, and rotate backups.'),
    ('core.webhooks.manage', 'Manage signed webhook destinations and delivery history.');

INSERT IGNORE INTO `{{prefix}}role_permissions` (`role_id`, `permission_id`)
SELECT roles.id, permissions.id FROM `{{prefix}}roles` AS roles
CROSS JOIN `{{prefix}}permissions` AS permissions
WHERE roles.role_key = 'super-administrator'
AND permissions.permission_key IN ('core.backups.manage', 'core.webhooks.manage');
