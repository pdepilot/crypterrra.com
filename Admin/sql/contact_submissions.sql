-- Contact form storage (used by contact.php + Admin/admin-contact.php)
-- Database: crypterra_admin (tables are auto-created by PHP if missing)

CREATE TABLE IF NOT EXISTS `contact_submissions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NOT NULL DEFAULT '',
  `message` TEXT NOT NULL,
  `category` VARCHAR(32) NOT NULL DEFAULT 'general',
  `priority` VARCHAR(16) NOT NULL DEFAULT 'medium',
  `status` VARCHAR(32) NOT NULL DEFAULT 'open',
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  KEY `idx_email` (`email`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `contact_activity_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL,
  `admin_email` VARCHAR(255) NOT NULL DEFAULT '',
  `action` VARCHAR(80) NOT NULL,
  `ticket_ref` VARCHAR(32) NOT NULL,
  `details` TEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
