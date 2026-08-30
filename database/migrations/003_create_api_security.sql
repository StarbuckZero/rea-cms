CREATE TABLE IF NOT EXISTS `{{prefix}}api_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `token_id` CHAR(16) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_cidr` VARCHAR(64) NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `last_used_at` TIMESTAMP(6) NULL,
    `expires_at` TIMESTAMP(6) NULL,
    `revoked_at` TIMESTAMP(6) NULL,
    UNIQUE KEY `api_tokens_token_id_unique` (`token_id`),
    KEY `api_tokens_user_index` (`user_id`),
    CONSTRAINT `api_tokens_user_fk` FOREIGN KEY (`user_id`)
        REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}api_token_scopes` (
    `token_id` BIGINT UNSIGNED NOT NULL,
    `scope` VARCHAR(191) NOT NULL,
    PRIMARY KEY (`token_id`, `scope`),
    CONSTRAINT `api_token_scopes_token_fk` FOREIGN KEY (`token_id`)
        REFERENCES `{{prefix}}api_tokens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}api_allowed_origins` (
    `origin` VARCHAR(255) NOT NULL PRIMARY KEY,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}api_allowed_networks` (
    `cidr` VARCHAR(64) NOT NULL PRIMARY KEY,
    `description` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}api_policies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `layer` VARCHAR(32) NOT NULL,
    `resource` VARCHAR(191) NOT NULL DEFAULT '*',
    `operation` VARCHAR(32) NOT NULL DEFAULT '*',
    `policy` VARCHAR(32) NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY `api_policies_declaration_unique` (`layer`, `resource`, `operation`, `policy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}rate_limits` (
    `limit_key` CHAR(64) NOT NULL PRIMARY KEY,
    `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `window_started_at` TIMESTAMP(6) NOT NULL,
    `expires_at` TIMESTAMP(6) NOT NULL,
    KEY `rate_limits_expiry_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `{{prefix}}api_policies` (`layer`, `resource`, `operation`, `policy`) VALUES
    ('global', '*', '*', 'same-origin');

INSERT IGNORE INTO `{{prefix}}permissions` (`permission_key`, `description`) VALUES
    ('core.api.status.read', 'Read the core API status resource.'),
    ('core.api.tokens.manage', 'Create and revoke API tokens and scopes.'),
    ('core.api.policies.manage', 'Manage API access policies and allowlists.');

INSERT IGNORE INTO `{{prefix}}role_permissions` (`role_id`, `permission_id`)
SELECT roles.id, permissions.id
FROM `{{prefix}}roles` AS roles
CROSS JOIN `{{prefix}}permissions` AS permissions
WHERE roles.role_key = 'super-administrator'
  AND permissions.permission_key IN (
      'core.api.status.read',
      'core.api.tokens.manage',
      'core.api.policies.manage'
  );
