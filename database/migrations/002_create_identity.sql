CREATE TABLE IF NOT EXISTS `{{prefix}}users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(254) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'active',
    `last_login_at` TIMESTAMP(6) NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    `deleted_at` TIMESTAMP(6) NULL,
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `users_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}user_profiles` (
    `user_id` BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    `display_name` VARCHAR(191) NOT NULL,
    `locale` VARCHAR(35) NOT NULL DEFAULT 'en',
    `timezone` VARCHAR(64) NOT NULL DEFAULT 'UTC',
    `theme` VARCHAR(32) NOT NULL DEFAULT 'system',
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    CONSTRAINT `user_profiles_user_fk` FOREIGN KEY (`user_id`)
        REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `role_key` VARCHAR(100) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `is_system` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY `roles_key_unique` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `permission_key` VARCHAR(191) NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY `permissions_key_unique` (`permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}role_permissions` (
    `role_id` BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `role_permissions_role_fk` FOREIGN KEY (`role_id`)
        REFERENCES `{{prefix}}roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `role_permissions_permission_fk` FOREIGN KEY (`permission_id`)
        REFERENCES `{{prefix}}permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}user_roles` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`user_id`, `role_id`),
    CONSTRAINT `user_roles_user_fk` FOREIGN KEY (`user_id`)
        REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `user_roles_role_fk` FOREIGN KEY (`role_id`)
        REFERENCES `{{prefix}}roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}sessions` (
    `token_hash` CHAR(64) NOT NULL PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent_hash` CHAR(64) NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `last_seen_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `expires_at` TIMESTAMP(6) NOT NULL,
    `reauthenticated_at` TIMESTAMP(6) NULL,
    `revoked_at` TIMESTAMP(6) NULL,
    KEY `sessions_user_index` (`user_id`),
    KEY `sessions_expiry_index` (`expires_at`),
    CONSTRAINT `sessions_user_fk` FOREIGN KEY (`user_id`)
        REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}password_resets` (
    `token_hash` CHAR(64) NOT NULL PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `expires_at` TIMESTAMP(6) NOT NULL,
    `used_at` TIMESTAMP(6) NULL,
    KEY `password_resets_user_index` (`user_id`),
    CONSTRAINT `password_resets_user_fk` FOREIGN KEY (`user_id`)
        REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}mfa_methods` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `method` VARCHAR(32) NOT NULL,
    `secret_ciphertext` TEXT NOT NULL,
    `enabled_at` TIMESTAMP(6) NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY `mfa_methods_user_index` (`user_id`),
    CONSTRAINT `mfa_methods_user_fk` FOREIGN KEY (`user_id`)
        REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}mfa_recovery_codes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL,
    `used_at` TIMESTAMP(6) NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY `mfa_recovery_codes_user_index` (`user_id`),
    CONSTRAINT `mfa_recovery_codes_user_fk` FOREIGN KEY (`user_id`)
        REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}login_attempts` (
    `attempt_key` CHAR(64) NOT NULL PRIMARY KEY,
    `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `window_started_at` TIMESTAMP(6) NOT NULL,
    `locked_until` TIMESTAMP(6) NULL,
    `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}audit_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `actor_user_id` BIGINT UNSIGNED NULL,
    `event_type` VARCHAR(191) NOT NULL,
    `subject_type` VARCHAR(100) NULL,
    `subject_id` VARCHAR(191) NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `request_id` CHAR(32) NOT NULL,
    `metadata_json` JSON NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY `audit_event_index` (`event_type`, `created_at`),
    KEY `audit_actor_index` (`actor_user_id`, `created_at`),
    CONSTRAINT `audit_actor_fk` FOREIGN KEY (`actor_user_id`)
        REFERENCES `{{prefix}}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `{{prefix}}roles` (`role_key`, `name`, `is_system`) VALUES
    ('super-administrator', 'Super Administrator', 1),
    ('administrator', 'Administrator', 1),
    ('editor', 'Editor', 1),
    ('author', 'Author', 1),
    ('contributor', 'Contributor', 1),
    ('viewer', 'Viewer', 1);

INSERT IGNORE INTO `{{prefix}}permissions` (`permission_key`, `description`) VALUES
    ('core.admin.access', 'Access the administration area.'),
    ('core.users.view', 'View users.'),
    ('core.users.manage', 'Create and update users.'),
    ('core.roles.manage', 'Manage roles and permissions.'),
    ('core.audit.view', 'View security audit events.'),
    ('core.settings.manage', 'Manage core settings.');

INSERT IGNORE INTO `{{prefix}}role_permissions` (`role_id`, `permission_id`)
SELECT roles.id, permissions.id
FROM `{{prefix}}roles` AS roles
CROSS JOIN `{{prefix}}permissions` AS permissions
WHERE roles.role_key = 'super-administrator';

INSERT IGNORE INTO `{{prefix}}role_permissions` (`role_id`, `permission_id`)
SELECT roles.id, permissions.id
FROM `{{prefix}}roles` AS roles
JOIN `{{prefix}}permissions` AS permissions
    ON permissions.permission_key IN ('core.admin.access', 'core.users.view', 'core.audit.view', 'core.settings.manage')
WHERE roles.role_key = 'administrator';
