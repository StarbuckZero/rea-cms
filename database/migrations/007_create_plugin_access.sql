CREATE TABLE IF NOT EXISTS `{{prefix}}user_plugin_access` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `plugin_id` VARCHAR(64) NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (`user_id`, `plugin_id`),
    CONSTRAINT `user_plugin_access_user_fk` FOREIGN KEY (`user_id`)
        REFERENCES `{{prefix}}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `{{prefix}}permissions` (`permission_key`, `description`) VALUES
    ('core.media.access', 'Access and manage shared media.');

INSERT IGNORE INTO `{{prefix}}role_permissions` (`role_id`, `permission_id`)
SELECT roles.id, permissions.id
FROM `{{prefix}}roles` AS roles
JOIN `{{prefix}}permissions` AS permissions
    ON permissions.permission_key = 'core.media.access'
WHERE roles.role_key = 'super-administrator';
