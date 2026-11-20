<?php
declare(strict_types=1);

require_once __DIR__ . '/../Userprofile/includes/user_db.php';

global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
} catch (Throwable $e) {
  echo "DB connect failed: " . $e->getMessage() . PHP_EOL;
  exit(1);
}

// Create core tables used by the app.
ensure_transactions_table($db);
ensure_kyc_requests_table($db);

// Create users table if missing (minimal columns; matches login.php expectations + KYC status).
$sql = <<<SQL
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `kyc_status` VARCHAR(20) NOT NULL DEFAULT 'not_submitted',
  `kyc_updated_at` DATETIME NULL,
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_active` (`is_active`),
  KEY `idx_users_kyc` (`kyc_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
@$db->query($sql);

echo "database: " . (string)$DB_NAME . PHP_EOL;
foreach (['users', 'transactions', 'kyc_requests'] as $t) {
  $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
  $exists = ($r && $r->num_rows > 0);
  if ($r) $r->free();
  echo $t . ': ' . ($exists ? 'exists' : 'missing') . PHP_EOL;
}

$db->close();

