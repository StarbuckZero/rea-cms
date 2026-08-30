CREATE TABLE IF NOT EXISTS `{{prefix}}plugins` (
    `plugin_id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `name` VARCHAR(191) NOT NULL,
    `version` VARCHAR(32) NOT NULL,
    `state` VARCHAR(32) NOT NULL DEFAULT 'disabled',
    `manifest_hash` CHAR(64) NOT NULL,
    `package_hash` CHAR(64) NOT NULL,
    `manifest_json` JSON NOT NULL,
    `installed_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    KEY `plugins_state_index` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}plugin_migrations` (
    `plugin_id` VARCHAR(64) NOT NULL,
    `migration` VARCHAR(191) NOT NULL,
    `checksum` CHAR(64) NOT NULL,
    `applied_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`plugin_id`, `migration`),
    CONSTRAINT `plugin_migrations_plugin_fk` FOREIGN KEY (`plugin_id`)
        REFERENCES `{{prefix}}plugins` (`plugin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}plugin_backups` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `plugin_id` VARCHAR(64) NOT NULL,
    `version` VARCHAR(32) NOT NULL,
    `backup_path` VARCHAR(500) NOT NULL,
    `package_hash` CHAR(64) NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY `plugin_backups_plugin_index` (`plugin_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `{{prefix}}permissions` (`permission_key`, `description`) VALUES
    ('core.plugins.view', 'View installed plugins.'),
    ('core.plugins.manage', 'Install, update, enable, disable, and uninstall plugins.'),
    ('core.plugins.purge', 'Permanently export and purge plugin data.');

INSERT IGNORE INTO `{{prefix}}role_permissions` (`role_id`, `permission_id`)
SELECT roles.id, permissions.id
FROM `{{prefix}}roles` AS roles
CROSS JOIN `{{prefix}}permissions` AS permissions
WHERE roles.role_key = 'super-administrator'
  AND permissions.permission_key IN ('core.plugins.view', 'core.plugins.manage', 'core.plugins.purge');
