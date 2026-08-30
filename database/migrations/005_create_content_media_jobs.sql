CREATE TABLE IF NOT EXISTS `{{prefix}}content_revisions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `plugin_id` VARCHAR(64) NOT NULL,
    `resource` VARCHAR(64) NOT NULL,
    `content_id` BIGINT UNSIGNED NOT NULL,
    `revision_number` INT UNSIGNED NOT NULL,
    `actor_user_id` BIGINT UNSIGNED NULL,
    `kind` VARCHAR(32) NOT NULL DEFAULT 'revision',
    `snapshot_json` JSON NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY `content_revisions_number_unique` (`plugin_id`, `resource`, `content_id`, `revision_number`),
    KEY `content_revisions_content_index` (`plugin_id`, `resource`, `content_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}content_slug_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `plugin_id` VARCHAR(64) NOT NULL,
    `resource` VARCHAR(64) NOT NULL,
    `content_id` BIGINT UNSIGNED NOT NULL,
    `slug` VARCHAR(191) NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY `content_slug_unique` (`plugin_id`, `resource`, `slug`),
    KEY `content_slug_target_index` (`plugin_id`, `resource`, `content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}content_previews` (
    `token_hash` CHAR(64) NOT NULL PRIMARY KEY,
    `plugin_id` VARCHAR(64) NOT NULL,
    `resource` VARCHAR(64) NOT NULL,
    `content_id` BIGINT UNSIGNED NOT NULL,
    `expires_at` TIMESTAMP(6) NOT NULL,
    `used_at` TIMESTAMP(6) NULL,
    KEY `content_previews_expiry_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}taxonomies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `plugin_id` VARCHAR(64) NOT NULL,
    `taxonomy_key` VARCHAR(64) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `hierarchical` TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY `taxonomies_key_unique` (`plugin_id`, `taxonomy_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}terms` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `taxonomy_id` BIGINT UNSIGNED NOT NULL,
    `parent_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(191) NOT NULL,
    `slug` VARCHAR(191) NOT NULL,
    `description` TEXT NOT NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY `terms_slug_unique` (`taxonomy_id`, `slug`),
    CONSTRAINT `terms_taxonomy_fk` FOREIGN KEY (`taxonomy_id`) REFERENCES `{{prefix}}taxonomies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `terms_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `{{prefix}}terms` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}term_relationships` (
    `term_id` BIGINT UNSIGNED NOT NULL,
    `plugin_id` VARCHAR(64) NOT NULL,
    `resource` VARCHAR(64) NOT NULL,
    `content_id` BIGINT UNSIGNED NOT NULL,
    `position` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`term_id`, `plugin_id`, `resource`, `content_id`),
    CONSTRAINT `term_relationships_term_fk` FOREIGN KEY (`term_id`) REFERENCES `{{prefix}}terms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}content_relationships` (
    `source_plugin_id` VARCHAR(64) NOT NULL,
    `source_resource` VARCHAR(64) NOT NULL,
    `source_content_id` BIGINT UNSIGNED NOT NULL,
    `relationship` VARCHAR(64) NOT NULL,
    `target_plugin_id` VARCHAR(64) NOT NULL,
    `target_resource` VARCHAR(64) NOT NULL,
    `target_content_id` BIGINT UNSIGNED NOT NULL,
    `position` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`source_plugin_id`, `source_resource`, `source_content_id`, `relationship`, `target_plugin_id`, `target_resource`, `target_content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}redirects` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `source_path` VARCHAR(500) NOT NULL,
    `target_path` VARCHAR(500) NOT NULL,
    `status_code` SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    `plugin_id` VARCHAR(64) NULL,
    `content_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY `redirects_source_unique` (`source_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}not_found_log` (
    `path_hash` CHAR(64) NOT NULL PRIMARY KEY,
    `path` VARCHAR(500) NOT NULL,
    `hit_count` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `first_seen_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `last_seen_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}media` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `stored_name` CHAR(64) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(127) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    `file_hash` CHAR(64) NOT NULL,
    `visibility` VARCHAR(16) NOT NULL DEFAULT 'private',
    `folder` VARCHAR(191) NULL,
    `alt_text` VARCHAR(500) NOT NULL DEFAULT '',
    `caption` TEXT NOT NULL,
    `credit` VARCHAR(500) NOT NULL DEFAULT '',
    `description` TEXT NOT NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    `updated_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY `media_stored_name_unique` (`stored_name`),
    KEY `media_hash_index` (`file_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}media_variants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `media_id` BIGINT UNSIGNED NOT NULL,
    `variant_key` VARCHAR(64) NOT NULL,
    `stored_name` CHAR(64) NOT NULL,
    `mime_type` VARCHAR(127) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL,
    `width` INT UNSIGNED NULL,
    `height` INT UNSIGNED NULL,
    UNIQUE KEY `media_variants_key_unique` (`media_id`, `variant_key`),
    CONSTRAINT `media_variants_media_fk` FOREIGN KEY (`media_id`) REFERENCES `{{prefix}}media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}media_usage` (
    `media_id` BIGINT UNSIGNED NOT NULL,
    `plugin_id` VARCHAR(64) NOT NULL,
    `resource` VARCHAR(64) NOT NULL,
    `content_id` BIGINT UNSIGNED NOT NULL,
    `field` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`media_id`, `plugin_id`, `resource`, `content_id`, `field`),
    CONSTRAINT `media_usage_media_fk` FOREIGN KEY (`media_id`) REFERENCES `{{prefix}}media` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `queue` VARCHAR(64) NOT NULL,
    `job_type` VARCHAR(191) NOT NULL,
    `payload_json` JSON NOT NULL,
    `idempotency_key` VARCHAR(191) NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
    `available_at` TIMESTAMP(6) NOT NULL,
    `reserved_at` TIMESTAMP(6) NULL,
    `reservation_token` CHAR(32) NULL,
    `created_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    UNIQUE KEY `jobs_idempotency_unique` (`queue`, `idempotency_key`),
    KEY `jobs_available_index` (`queue`, `available_at`, `reserved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{{prefix}}failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `original_job_id` BIGINT UNSIGNED NOT NULL,
    `queue` VARCHAR(64) NOT NULL,
    `job_type` VARCHAR(191) NOT NULL,
    `payload_json` JSON NOT NULL,
    `attempts` INT UNSIGNED NOT NULL,
    `failure_reason` VARCHAR(1000) NOT NULL,
    `failed_at` TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY `failed_jobs_queue_index` (`queue`, `failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
