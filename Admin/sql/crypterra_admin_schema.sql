-- Crypterra Admin database schema for `crypterra_admin`
-- Run this in phpMyAdmin (Import) or: mysql -u root -p crypterra_admin < crypterra_admin_schema.sql
--
-- Default admin (CHANGE PASSWORD after first login):
--   username: superadmin
--   email:    admin@crypterra.com
--   password: ChangeMe123!

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Admin users (used by Admin/admin-login.php)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'password_hash() bcrypt',
  `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`),
  KEY `idx_admins_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Login activity log (inserted on successful login)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_logs_admin_id` (`admin_id`),
  KEY `idx_admin_logs_created_at` (`created_at`),
  CONSTRAINT `fk_admin_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Failed login / lockout tracking (also auto-created by PHP if missing)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier_hash` CHAR(64) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL,
  `last_attempt_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ident_ip` (`identifier_hash`, `ip`),
  KEY `idx_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Seed: first admin only if table is empty (password: ChangeMe123!)
INSERT INTO `admins` (`username`, `email`, `password`, `role`, `is_active`, `created_at`)
SELECT
  'superadmin',
  'admin@crypterra.com',
  '$2y$10$Mqx9j.a/hY4eWX6ACh5L7eIQUxxQkq1ieILSXFdYuwWvAet/E821C',
  'super_admin',
  1,
  NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `admins` LIMIT 1);
