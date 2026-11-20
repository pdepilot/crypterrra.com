<?php
declare(strict_types=1);

/**
 * Crypterra Admin Login (PHP + MySQLi)
 *
 * Notes:
 * - Keeps the existing frontend layout/styles intact.
 * - Uses prepared statements only.
 * - Provides CSRF protection, session hardening, timeout markers, and basic lockout.
 */

// -----------------------------
// Configuration
// -----------------------------

// Defaults (XAMPP MySQL: root often has an empty password).
// Real credentials belong in db-config.php, which is not committed.
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'crypterra';

// Local overrides. Root db-config.php is the shared site config; Admin/db-config.php wins if present.
$rootConfig = dirname(__DIR__) . '/db-config.php';
if (is_file($rootConfig)) {
  require $rootConfig;
}
if (is_file(__DIR__ . '/db-config.php')) {
  require __DIR__ . '/db-config.php';
}

// Table/column defaults (adjust if your schema differs).
$ADMIN_TABLE = 'admins';
$ADMIN_COL_ID = 'id';
$ADMIN_COL_USERNAME = 'username';
$ADMIN_COL_EMAIL = 'email';
$ADMIN_COL_PASSWORD = 'password'; // expected hashed password from password_hash()
$ADMIN_COL_ROLE = 'role';
$ADMIN_COL_LAST_LOGIN = 'last_login_at';
$ADMIN_COL_LAST_LOGIN_IP = 'last_login_ip';
$ADMIN_COL_IS_ACTIVE = 'is_active';

// Optional: activity log table (best-effort insert if exists/creatable).
$ADMIN_LOGS_TABLE = 'admin_logs';

// Security settings.
$SESSION_TIMEOUT_SECONDS = 30 * 60; // 30 minutes inactivity timeout marker.
$MAX_FAILED_ATTEMPTS = 8;
$LOCKOUT_SECONDS = 15 * 60; // 15 minutes lockout after too many failures.

// -----------------------------
// Helpers
// -----------------------------

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function client_ip(): string {
  // On XAMPP/dev environments, REMOTE_ADDR is usually sufficient.
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  return is_string($ip) ? $ip : '';
}

function db_connect(string $host, string $user, string $pass, string $db): mysqli {
  mysqli_report(MYSQLI_REPORT_OFF);
  $mysqli = @new mysqli($host, $user, $pass);
  if ($mysqli->connect_errno) {
    throw new RuntimeException('Database connection failed: ' . $mysqli->connect_error);
  }
  $mysqli->set_charset('utf8mb4');
  if ($db !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
    $mysqli->close();
    throw new RuntimeException('Invalid database name.');
  }
  if ($db !== '') {
    @$mysqli->query(
      'CREATE DATABASE IF NOT EXISTS `' . $db . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    if (!$mysqli->select_db($db)) {
      $err = $mysqli->error;
      $mysqli->close();
      throw new RuntimeException('Cannot open database: ' . $err);
    }
  }
  return $mysqli;
}

/**
 * Works without mysqlnd (get_result() is not available on some XAMPP builds).
 */
function schema_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
  $s = $db->real_escape_string($schema);
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($column);
  $r = @$db->query(
    "SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$s}' AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'"
  );
  if (!$r) {
    return false;
  }
  $row = $r->fetch_assoc();
  $r->free();
  return isset($row['n']) && (int)$row['n'] > 0;
}

/**
 * Fetch admin row without mysqli_stmt::get_result() (requires mysqlnd).
 */
function fetch_admin_by_credentials(
  mysqli_stmt $stmt,
  string $idCol,
  string $userCol,
  string $emailCol,
  string $passCol,
  string $roleCol
): ?array {
  if (!$stmt->execute()) {
    return null;
  }
  if (!$stmt->store_result()) {
    return null;
  }
  $id = 0;
  $username = $email = $passwordHash = $role = '';
  $stmt->bind_result($id, $username, $email, $passwordHash, $role);
  if (!$stmt->fetch()) {
    $stmt->free_result();
    return null;
  }
  $stmt->free_result();
  return [
    $idCol => $id,
    $userCol => $username,
    $emailCol => $email,
    $passCol => $passwordHash,
    $roleCol => $role,
  ];
}

function ensure_login_attempts_table(mysqli $db): void {
  // Best-effort: create a small table to track failed attempts + lockouts.
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS admin_login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  identifier_hash CHAR(64) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  failed_count INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_attempt_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_ident_ip (identifier_hash, ip),
  KEY idx_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;
  @$db->query($sql);
}

/** bcrypt for documented default password ChangeMe123! (must match sql/crypterra_admin_schema.sql seed). */
const DEFAULT_ADMIN_PASSWORD_HASH = '$2y$10$Mqx9j.a/hY4eWX6ACh5L7eIQUxxQkq1ieILSXFdYuwWvAet/E821C';

/** Old seed hash did not verify against ChangeMe123! — replace only if still present. */
const LEGACY_BROKEN_ADMIN_PASSWORD_HASH = '$2y$10$wJuyWHitumDEVHKmVnxTou2aeuBgADnsKSk/v9QPqI7qjYqpDlZXG';

/**
 * Create `admins` if missing; seed first admin if table is empty (same defaults as schema SQL).
 */
function ensure_admins_table(mysqli $db, string $adminTable): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$adminTable}` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);

  $r = @$db->query("SELECT COUNT(*) AS c FROM `{$adminTable}`");
  if ($r) {
    $row = $r->fetch_assoc();
    $r->free();
    if (isset($row['c']) && (int)$row['c'] === 0) {
      $username = 'superadmin';
      $email = 'admin@crypterra.com';
      $role = 'super_admin';
      $hash = DEFAULT_ADMIN_PASSWORD_HASH;
      $ins = $db->prepare(
        "INSERT INTO `{$adminTable}` (`username`, `email`, `password`, `role`, `is_active`, `created_at`) VALUES (?, ?, ?, ?, 1, NOW())"
      );
      if ($ins) {
        $ins->bind_param('ssss', $username, $email, $hash, $role);
        @$ins->execute();
        $ins->close();
      }
    }
  }
}

/** Fix rows still using the broken seed hash from older schema exports. */
function migrate_legacy_default_admin_password(mysqli $db, string $adminTable): void {
  $newHash = DEFAULT_ADMIN_PASSWORD_HASH;
  $oldHash = LEGACY_BROKEN_ADMIN_PASSWORD_HASH;
  $user = 'superadmin';
  $email = 'admin@crypterra.com';
  $stmt = $db->prepare(
    "UPDATE `{$adminTable}` SET `password` = ? WHERE `username` = ? AND `email` = ? AND `password` = ?"
  );
  if ($stmt) {
    $stmt->bind_param('ssss', $newHash, $user, $email, $oldHash);
    @$stmt->execute();
    $stmt->close();
  }
}

function ensure_admin_audit_schema(mysqli $db, string $dbName, string $adminTable, string $lastLoginCol, string $lastIpCol, string $logsTable, string $isActiveCol): void {
  // Best-effort schema hardening to satisfy "last login" + "admin logs" requirements.
  // If permissions are restricted, failures are ignored and login continues.

  foreach ([[$lastLoginCol, "DATETIME NULL"], [$lastIpCol, "VARCHAR(45) NULL"], [$isActiveCol, "TINYINT(1) UNSIGNED NOT NULL DEFAULT 1"]] as $col) {
    [$colName, $colDef] = $col;
    if (!schema_column_exists($db, $dbName, $adminTable, $colName)) {
      @$db->query("ALTER TABLE `{$adminTable}` ADD COLUMN `{$colName}` {$colDef}");
    }
  }

  // Create logs table if missing.
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$logsTable} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id INT UNSIGNED NOT NULL,
  action VARCHAR(50) NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_admin_id (admin_id),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL;
  @$db->query($sql);
}

function read_lock_status(mysqli $db, string $identifierHash, string $ip): array {
  // Returns [failed_count => int, locked_until_ts => ?int]
  $failed = 0;
  $lockedUntilTs = null;

  $stmt = $db->prepare('SELECT failed_count, locked_until FROM admin_login_attempts WHERE identifier_hash = ? AND ip = ? LIMIT 1');
  if (!$stmt) {
    return ['failed_count' => 0, 'locked_until_ts' => null];
  }
  $stmt->bind_param('ss', $identifierHash, $ip);
  if ($stmt->execute()) {
    $stmt->store_result();
    $stmt->bind_result($failedCount, $lockedUntil);
    if ($stmt->fetch()) {
      $failed = (int)$failedCount;
      if (is_string($lockedUntil) && $lockedUntil !== '') {
        $ts = strtotime($lockedUntil);
        if ($ts !== false) {
          $lockedUntilTs = $ts;
        }
      }
    }
    $stmt->free_result();
  }
  $stmt->close();

  return ['failed_count' => $failed, 'locked_until_ts' => $lockedUntilTs];
}

function bump_failed_attempt(mysqli $db, string $identifierHash, string $ip, int $maxFailed, int $lockoutSeconds): void {
  $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

  // Upsert attempt row.
  $stmt = $db->prepare(
    'INSERT INTO admin_login_attempts (identifier_hash, ip, failed_count, locked_until, last_attempt_at)
     VALUES (?, ?, 1, NULL, ?)
     ON DUPLICATE KEY UPDATE failed_count = failed_count + 1, last_attempt_at = VALUES(last_attempt_at)'
  );
  if ($stmt) {
    $stmt->bind_param('sss', $identifierHash, $ip, $now);
    @$stmt->execute();
    $stmt->close();
  }

  // If threshold reached, set lock.
  $stmt2 = $db->prepare('SELECT failed_count FROM admin_login_attempts WHERE identifier_hash = ? AND ip = ? LIMIT 1');
  if ($stmt2) {
    $stmt2->bind_param('ss', $identifierHash, $ip);
    if ($stmt2->execute()) {
      $stmt2->store_result();
      $stmt2->bind_result($failedCount);
      if ($stmt2->fetch()) {
        $failedCount = (int)$failedCount;
        if ($failedCount >= $maxFailed) {
          $lockedUntil = (new DateTimeImmutable('now'))->modify('+' . $lockoutSeconds . ' seconds')->format('Y-m-d H:i:s');
          $stmt3 = $db->prepare('UPDATE admin_login_attempts SET locked_until = ? WHERE identifier_hash = ? AND ip = ?');
          if ($stmt3) {
            $stmt3->bind_param('sss', $lockedUntil, $identifierHash, $ip);
            @$stmt3->execute();
            $stmt3->close();
          }
        }
      }
      $stmt2->free_result();
    }
    $stmt2->close();
  }
}

function clear_failed_attempts(mysqli $db, string $identifierHash, string $ip): void {
  $stmt = $db->prepare('DELETE FROM admin_login_attempts WHERE identifier_hash = ? AND ip = ?');
  if ($stmt) {
    $stmt->bind_param('ss', $identifierHash, $ip);
    @$stmt->execute();
    $stmt->close();
  }
}

function session_harden_and_start(): void {
  // Cookie hardening.
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  // Keep admin session isolated from user session.
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_name('crypterra_admin');
  }
  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
      'lifetime' => 0,
      'path' => '/',
      'domain' => '',
      'secure' => $isHttps,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  } else {
    // Fallback for older PHP; SameSite not supported here.
    session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
  }

  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
}

function csrf_token(): string {
  if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool {
  if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    return false;
  }
  if (!is_string($token) || $token === '') {
    return false;
  }
  return hash_equals($_SESSION['csrf_token'], $token);
}

// -----------------------------
// Request handling
// -----------------------------

session_harden_and_start();

$error = '';
$identifierValue = '';

// Session timeout marker for downstream admin pages to enforce.
$_SESSION['admin_session_timeout_seconds'] = $SESSION_TIMEOUT_SECONDS;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $identifierValue = isset($_POST['identifier']) && is_string($_POST['identifier']) ? trim($_POST['identifier']) : '';
  $password = isset($_POST['password']) && is_string($_POST['password']) ? (string)$_POST['password'] : '';
  $postedCsrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;

  if (!verify_csrf($postedCsrf)) {
    $error = 'Security verification failed. Please refresh and try again.';
  } elseif ($identifierValue === '' || $password === '') {
    $error = 'Please enter your login and password.';
  } else {
    // Sanitize identifier: keep it printable; still bind via prepared stmt.
    $identifierValue = preg_replace('/[^\P{C}\n\r\t]/u', '', $identifierValue) ?? $identifierValue;
    $ip = client_ip();
    $identifierLower = function_exists('mb_strtolower')
      ? mb_strtolower($identifierValue, 'UTF-8')
      : strtolower($identifierValue);
    $identifierHash = hash('sha256', $identifierLower . '|' . $ip);

    try {
      $db = db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
      ensure_admins_table($db, $ADMIN_TABLE);
      migrate_legacy_default_admin_password($db, $ADMIN_TABLE);
      ensure_login_attempts_table($db);
      ensure_admin_audit_schema($db, $DB_NAME, $ADMIN_TABLE, $ADMIN_COL_LAST_LOGIN, $ADMIN_COL_LAST_LOGIN_IP, $ADMIN_LOGS_TABLE, $ADMIN_COL_IS_ACTIVE);

      $lock = read_lock_status($db, $identifierHash, $ip);
      if ($lock['locked_until_ts'] !== null && $lock['locked_until_ts'] > time()) {
        // Generic message (do not reveal anything about account existence).
        $error = 'Invalid credentials. Please try again later.';
      } else {
        // Look up by email OR username.
        // Backtick identifiers: MySQL 8+ reserves ROLE, PASSWORD, etc.
        $sql = "SELECT `{$ADMIN_COL_ID}`, `{$ADMIN_COL_USERNAME}`, `{$ADMIN_COL_EMAIL}`, `{$ADMIN_COL_PASSWORD}`, `{$ADMIN_COL_ROLE}`
                FROM `{$ADMIN_TABLE}`
                WHERE (`{$ADMIN_COL_EMAIL}` = ? OR `{$ADMIN_COL_USERNAME}` = ?) AND `{$ADMIN_COL_IS_ACTIVE}` = 1
                LIMIT 1";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
          throw new RuntimeException('prepare failed: ' . $db->error);
        }

        $bindEmail = $identifierValue;
        $bindUser = $identifierValue;
        $stmt->bind_param('ss', $bindEmail, $bindUser);
        $admin = fetch_admin_by_credentials(
          $stmt,
          $ADMIN_COL_ID,
          $ADMIN_COL_USERNAME,
          $ADMIN_COL_EMAIL,
          $ADMIN_COL_PASSWORD,
          $ADMIN_COL_ROLE
        );
        $stmt->close();

        $passwordHash = is_array($admin) && isset($admin[$ADMIN_COL_PASSWORD]) && is_string($admin[$ADMIN_COL_PASSWORD])
          ? $admin[$ADMIN_COL_PASSWORD]
          : '';

        $valid = ($passwordHash !== '') && password_verify($password, $passwordHash);

        if (!$valid) {
          bump_failed_attempt($db, $identifierHash, $ip, $MAX_FAILED_ATTEMPTS, $LOCKOUT_SECONDS);
          $error = 'Invalid credentials. Please try again.';
        } else {
          // Successful login: harden session and store admin identity.
          session_regenerate_id(true);

          $adminId = (int)$admin[$ADMIN_COL_ID];
          $adminUsername = (string)$admin[$ADMIN_COL_USERNAME];
          $adminEmail = (string)$admin[$ADMIN_COL_EMAIL];
          $adminRole = (string)$admin[$ADMIN_COL_ROLE];

          $_SESSION['admin_id'] = $adminId;
          $_SESSION['admin_username'] = $adminUsername;
          $_SESSION['admin_email'] = $adminEmail;
          $_SESSION['admin_role'] = $adminRole;
          $_SESSION['admin_login_time'] = time();
          $_SESSION['admin_last_activity'] = time();

          clear_failed_attempts($db, $identifierHash, $ip);

          // Update last login timestamp (best-effort).
          $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
          $updateSql = "UPDATE `{$ADMIN_TABLE}` SET `{$ADMIN_COL_LAST_LOGIN}` = ?, `{$ADMIN_COL_LAST_LOGIN_IP}` = ? WHERE `{$ADMIN_COL_ID}` = ?";
          if ($up = $db->prepare($updateSql)) {
            $up->bind_param('ssi', $now, $ip, $adminId);
            @$up->execute();
            $up->close();
          }

          // Insert login activity (best-effort). Will work if table exists and columns match.
          // Expected columns: id, admin_id, action, ip, user_agent, created_at
          $ua = isset($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
          $logSql = "INSERT INTO `{$ADMIN_LOGS_TABLE}` (`admin_id`, `action`, `ip`, `user_agent`, `created_at`) VALUES (?, 'login', ?, ?, ?)";
          if ($lg = $db->prepare($logSql)) {
            $lg->bind_param('isss', $adminId, $ip, $ua, $now);
            @$lg->execute();
            $lg->close();
          }

          $db->close();

          header("Location: admin-dashboard.php");
          exit();
        }
      }

      $db->close();
    } catch (Throwable $e) {
      error_log('[admin-login] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
      $error = 'Login temporarily unavailable. Please try again.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Crypterra · Admin Access</title>
  <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
  <!-- Font Awesome 6 (free) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Google Fonts Inter + Space Grotesk for futuristic feel -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* ========== RESET & VARIABLES ========== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --primary: #22c55e;
      --primary-light: #4ade80;
      --primary-dark: #16a34a;
      --primary-glow: rgba(34, 197, 94, 0.5);
      --primary-soft: rgba(34, 197, 94, 0.15);
      --danger: #ef4444;
      --warning: #f59e0b;
      --bg: #0a0f1a;
      --bg-secondary: #0e1422;
      --surface: rgba(20, 30, 45, 0.8);
      --surface-light: rgba(30, 40, 60, 0.9);
      --text: #ffffff;
      --text-secondary: rgba(255, 255, 255, 0.7);
      --text-muted: rgba(255, 255, 255, 0.5);
      --glass-border: rgba(34, 197, 94, 0.3);
      --glass-bg: rgba(10, 20, 30, 0.6);
      --shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
      --shadow-glow: 0 0 30px var(--primary-glow);
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      overflow-x: hidden;
      min-height: 100vh;
      position: relative;
    }

    /* ========== 3D PARTICLE BACKGROUND ========== */
    #particles {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 0;
      pointer-events: none;
    }

    .gradient-orb {
      position: fixed;
      width: 60vmax;
      height: 60vmax;
      border-radius: 50%;
      background: radial-gradient(circle at center, rgba(34, 197, 94, 0.15) 0%, transparent 70%);
      animation: float 20s ease-in-out infinite;
      z-index: 1;
      pointer-events: none;
    }

    .orb-1 {
      top: -20%;
      right: -10%;
      width: 80vmin;
      height: 80vmin;
      background: radial-gradient(circle at center, rgba(34, 197, 94, 0.2) 0%, transparent 70%);
      animation: float 25s ease-in-out infinite;
    }

    .orb-2 {
      bottom: -20%;
      left: -10%;
      width: 70vmin;
      height: 70vmin;
      background: radial-gradient(circle at center, rgba(34, 197, 94, 0.15) 0%, transparent 70%);
      animation: float 30s ease-in-out infinite reverse;
    }

    .orb-3 {
      top: 30%;
      left: 40%;
      width: 40vmin;
      height: 40vmin;
      background: radial-gradient(circle at center, rgba(34, 197, 94, 0.1) 0%, transparent 70%);
      animation: float 20s ease-in-out infinite 2s;
    }

    @keyframes float {
      0%, 100% { transform: translate(0, 0) scale(1); }
      25% { transform: translate(5%, 5%) scale(1.05); }
      50% { transform: translate(-5%, 2%) scale(0.95); }
      75% { transform: translate(2%, -5%) scale(1.02); }
    }

    /* Glowing grid overlay */
    .grid-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: 
        linear-gradient(rgba(34, 197, 94, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(34, 197, 94, 0.05) 1px, transparent 1px);
      background-size: 50px 50px;
      z-index: 1;
      pointer-events: none;
      animation: gridPulse 4s ease-in-out infinite;
    }

    @keyframes gridPulse {
      0%, 100% { opacity: 0.5; }
      50% { opacity: 0.8; }
    }

    /* ========== PRELOADER ========== */
    .preloader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--bg);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: opacity 1s ease, visibility 1s ease;
    }

    .preloader.hidden {
      opacity: 0;
      visibility: hidden;
    }

    .preloader-content {
      text-align: center;
    }

    .preloader-logo {
      font-size: 3rem;
      font-weight: 700;
      background: linear-gradient(135deg, #fff, var(--primary));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-bottom: 2rem;
      font-family: 'Space Grotesk', sans-serif;
      animation: glowPulse 2s ease-in-out infinite;
    }

    @keyframes glowPulse {
      0%, 100% { text-shadow: 0 0 20px var(--primary-glow); }
      50% { text-shadow: 0 0 40px var(--primary-glow); }
    }

    .preloader-bar {
      width: 200px;
      height: 3px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 3px;
      overflow: hidden;
      margin: 0 auto;
    }

    .preloader-progress {
      width: 0%;
      height: 100%;
      background: linear-gradient(90deg, var(--primary), var(--primary-light));
      animation: loadProgress 2s ease-in-out forwards;
      box-shadow: 0 0 20px var(--primary-glow);
    }

    @keyframes loadProgress {
      0% { width: 0%; }
      100% { width: 100%; }
    }

    /* ========== MAIN LAYOUT ========== */
    .admin-container {
      position: relative;
      z-index: 10;
      min-height: 100vh;
      display: flex;
      backdrop-filter: blur(10px);
    }

    /* Left Section - Branding */
    .brand-section {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      position: relative;
      overflow: hidden;
    }

    .brand-content {
      text-align: center;
      max-width: 600px;
      animation: fadeInUp 1s ease-out;
    }

    .brand-icon {
      font-size: 4rem;
      color: var(--primary);
      margin-bottom: 2rem;
      filter: drop-shadow(0 0 30px var(--primary-glow));
      animation: rotateSlow 20s linear infinite;
    }

    @keyframes rotateSlow {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .brand-title {
      font-size: 3.5rem;
      font-weight: 700;
      margin-bottom: 1rem;
      font-family: 'Space Grotesk', sans-serif;
      background: linear-gradient(135deg, #fff, var(--primary));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .brand-subtitle {
      font-size: 1.2rem;
      color: var(--text-secondary);
      margin-bottom: 2rem;
      min-height: 60px;
    }

    .typing-text {
      font-size: 1.2rem;
      color: var(--primary);
      border-right: 2px solid var(--primary);
      display: inline-block;
      white-space: nowrap;
      overflow: hidden;
      animation: blink 0.7s step-end infinite;
    }

    @keyframes blink {
      from, to { border-color: transparent; }
      50% { border-color: var(--primary); }
    }

    .security-badge-container {
      display: flex;
      justify-content: center;
      gap: 2rem;
      margin-top: 3rem;
      flex-wrap: wrap;
    }

    .security-badge {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
      padding: 1rem;
      background: var(--glass-bg);
      backdrop-filter: blur(10px);
      border: 1px solid var(--glass-border);
      border-radius: 20px;
      min-width: 120px;
      transition: 0.3s;
    }

    .security-badge:hover {
      transform: translateY(-5px);
      border-color: var(--primary);
      box-shadow: var(--shadow-glow);
    }

    .security-badge i {
      font-size: 1.5rem;
      color: var(--primary);
    }

    .security-badge span {
      font-size: 0.9rem;
      color: var(--text-secondary);
    }

    /* Right Section - Login Form */
    .login-section {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      background: linear-gradient(90deg, transparent, rgba(34, 197, 94, 0.05) 50%, transparent);
    }

    .login-card {
      width: 100%;
      max-width: 450px;
      padding: 2.5rem;
      background: var(--glass-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      border-radius: 40px;
      box-shadow: var(--shadow), var(--shadow-glow);
      animation: fadeInUp 1s ease-out 0.3s both;
      position: relative;
      overflow: hidden;
    }

    .login-card::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle at center, rgba(34, 197, 94, 0.1) 0%, transparent 70%);
      animation: rotate 20s linear infinite;
      z-index: -1;
    }

    @keyframes rotate {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    .login-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .login-header h2 {
      font-size: 2.2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      font-family: 'Space Grotesk', sans-serif;
      background: linear-gradient(135deg, #fff, var(--primary));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .login-header p {
      color: var(--text-muted);
      font-size: 0.95rem;
    }

    .security-indicators {
      display: flex;
      justify-content: center;
      gap: 1.5rem;
      margin-bottom: 2rem;
      flex-wrap: wrap;
    }

    .indicator {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.85rem;
      color: var(--text-secondary);
    }

    .indicator i {
      color: var(--primary);
      font-size: 0.9rem;
    }

    .indicator.warning i {
      color: var(--warning);
    }

    /* Form Styles */
    .form-group {
      margin-bottom: 1.5rem;
      position: relative;
    }

    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--text-secondary);
      font-size: 0.9rem;
      font-weight: 500;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-icon {
      position: absolute;
      left: 1rem;
      color: var(--text-muted);
      font-size: 1rem;
      transition: 0.2s;
    }

    .form-input {
      width: 100%;
      padding: 1rem 1rem 1rem 2.8rem;
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid var(--glass-border);
      border-radius: 30px;
      color: var(--text);
      font-size: 1rem;
      transition: 0.3s;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px var(--primary-soft);
    }

    .password-toggle {
      position: absolute;
      right: 1rem;
      background: transparent;
      border: none;
      color: var(--text-muted);
      cursor: pointer;
      font-size: 1rem;
      transition: 0.2s;
    }

    .password-toggle:hover {
      color: var(--primary);
    }

    /* MFA Section */
    .mfa-section {
      margin-bottom: 1.5rem;
      display: none;
      animation: slideDown 0.3s ease;
    }

    .mfa-section.show {
      display: block;
    }

    .mfa-inputs {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
    }

    .mfa-box {
      width: 45px;
      height: 45px;
      background: rgba(0, 0, 0, 0.3);
      border: 1px solid var(--glass-border);
      border-radius: 15px;
      text-align: center;
      font-size: 1.2rem;
      font-weight: 600;
      color: var(--text);
      transition: 0.2s;
    }

    .mfa-box:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 2px var(--primary-soft);
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Remember device & Forgot password */
    .form-options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .remember-checkbox {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-secondary);
      cursor: pointer;
    }

    .remember-checkbox input {
      accent-color: var(--primary);
      width: 16px;
      height: 16px;
    }

    .forgot-link {
      color: var(--primary);
      text-decoration: none;
      font-size: 0.9rem;
      transition: 0.2s;
    }

    .forgot-link:hover {
      text-decoration: underline;
      filter: brightness(1.2);
    }

    /* Login Button */
    .login-btn {
      width: 100%;
      padding: 1rem;
      background: linear-gradient(145deg, var(--primary), var(--primary-dark));
      border: none;
      border-radius: 40px;
      color: white;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
      position: relative;
      overflow: hidden;
      margin-bottom: 1.5rem;
    }

    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-glow);
    }

    .login-btn.loading {
      pointer-events: none;
      opacity: 0.8;
    }

    .login-btn.loading .btn-text {
      display: none;
    }

    .login-btn.loading .btn-loader {
      display: inline-block;
    }

    .btn-loader {
      display: none;
      width: 20px;
      height: 20px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Error Message */
    .error-message {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid var(--danger);
      border-radius: 30px;
      padding: 0.8rem;
      margin-bottom: 1.5rem;
      color: var(--danger);
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      display: none;
    }

    .error-message.show {
      display: flex;
      animation: shake 0.5s ease;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    /* Security Scan Animation */
    .security-scan {
      margin-top: 1.5rem;
      display: none;
    }

    .security-scan.show {
      display: block;
    }

    .scan-line {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
      color: var(--text-muted);
      font-size: 0.9rem;
      animation: fadeIn 0.3s ease;
    }

    .scan-line i {
      color: var(--primary);
      font-size: 0.9rem;
    }

    .scan-line.success i {
      color: var(--primary);
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Device Recognition */
    .device-info {
      margin-top: 1.5rem;
      padding: 0.8rem;
      background: rgba(0, 0, 0, 0.2);
      border-radius: 30px;
      font-size: 0.85rem;
      color: var(--text-muted);
      text-align: center;
      border: 1px solid var(--glass-border);
    }

    .device-info i {
      color: var(--primary);
      margin-right: 0.3rem;
    }

    /* Footer */
    .login-footer {
      margin-top: 1.5rem;
      text-align: center;
      color: var(--text-muted);
      font-size: 0.8rem;
    }

    .login-footer i {
      color: var(--primary);
      margin: 0 0.2rem;
    }

    /* Responsive */
    @media (max-width: 968px) {
      .admin-container {
        flex-direction: column;
      }
      
      .brand-section {
        padding: 2rem 1rem;
      }
      
      .brand-title {
        font-size: 2.5rem;
      }
      
      .login-section {
        padding: 1rem;
      }
      
      .login-card {
        max-width: 100%;
      }
    }

    @media (max-width: 480px) {
      .brand-title {
        font-size: 2rem;
      }
      
      .security-badge-container {
        gap: 0.5rem;
      }
      
      .security-badge {
        min-width: 100px;
        padding: 0.8rem;
      }
      
      .mfa-inputs {
        gap: 0.3rem;
      }
      
      .mfa-box {
        width: 40px;
        height: 40px;
      }
    }

    /* Flicker transition effect */
    .flicker-transition {
      animation: flicker 0.5s ease;
    }

    @keyframes flicker {
      0% { opacity: 1; }
      10% { opacity: 0.8; }
      20% { opacity: 1; }
      30% { opacity: 0.7; }
      40% { opacity: 1; }
      50% { opacity: 0.6; }
      60% { opacity: 1; }
      70% { opacity: 0.8; }
      80% { opacity: 1; }
      90% { opacity: 0.9; }
      100% { opacity: 1; }
    }
  </style>
</head>
<body>
  <!-- Preloader -->
  <div class="preloader" id="preloader">
    <div class="preloader-content">
      <div class="preloader-logo">CRYPTERRA</div>
      <div class="preloader-bar">
        <div class="preloader-progress"></div>
      </div>
    </div>
  </div>

  <!-- Particle Background (Canvas) -->
  <canvas id="particles"></canvas>
  
  <!-- Gradient Orbs -->
  <div class="gradient-orb orb-1"></div>
  <div class="gradient-orb orb-2"></div>
  <div class="gradient-orb orb-3"></div>
  
  <!-- Grid Overlay -->
  <div class="grid-overlay"></div>

  <!-- Main Admin Container -->
  <div class="admin-container">
    <!-- Left Section - Branding -->
    <div class="brand-section">
      <div class="brand-content">
        <div class="brand-icon">
          <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 class="brand-title">Admin Control Panel</h1>
        <div class="brand-subtitle">
          <span class="typing-text" id="typingText"></span>
        </div>
        
        <div class="security-badge-container">
          <div class="security-badge">
            <i class="fa-solid fa-lock"></i>
            <span>256-bit AES</span>
          </div>
          <div class="security-badge">
            <i class="fa-solid fa-fingerprint"></i>
            <span>Biometric Ready</span>
          </div>
          <div class="security-badge">
            <i class="fa-solid fa-shield"></i>
            <span>HSM Protected</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Section - Login Form -->
    <div class="login-section">
      <div class="login-card">
        <div class="login-header">
          <h2>Admin Login</h2>
          <p>Authorized personnel only</p>
        </div>

        <div class="security-indicators">
          <div class="indicator">
            <i class="fa-solid fa-lock"></i>
            <span>Secure Connection</span>
          </div>
          <div class="indicator">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Encrypted Login</span>
          </div>
          <div class="indicator warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Access Restricted</span>
          </div>
        </div>

        <!-- Error Message -->
        <div class="error-message<?php echo $error !== '' ? ' show' : ''; ?>" id="errorMessage">
          <i class="fa-solid fa-circle-exclamation"></i>
          <span><?php echo $error !== '' ? h($error) : 'Invalid credentials. Please try again.'; ?></span>
        </div>

        <!-- Login Form -->
        <form id="loginForm" method="post" action="">
          <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">

          <!-- Email / Username -->
          <div class="form-group">
            <label class="form-label">Email / Username</label>
            <div class="input-wrapper">
              <i class="fa-regular fa-envelope input-icon"></i>
              <input type="text" class="form-input" placeholder="admin@crypterra.com" id="email" name="identifier" autocomplete="username" value="<?php echo h($identifierValue); ?>">
            </div>
          </div>

          <!-- Password -->
          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrapper">
              <i class="fa-solid fa-lock input-icon"></i>
              <input type="password" class="form-input" placeholder="••••••••" id="password" name="password" autocomplete="current-password" value="">
              <button type="button" class="password-toggle" id="togglePassword">
                <i class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <!-- MFA Section (Initially Hidden) -->
          <div class="mfa-section" id="mfaSection">
            <label class="form-label">Enter 2FA Code</label>
            <div class="mfa-inputs">
              <input type="text" class="mfa-box" maxlength="1" pattern="[0-9]">
              <input type="text" class="mfa-box" maxlength="1" pattern="[0-9]">
              <input type="text" class="mfa-box" maxlength="1" pattern="[0-9]">
              <input type="text" class="mfa-box" maxlength="1" pattern="[0-9]">
              <input type="text" class="mfa-box" maxlength="1" pattern="[0-9]">
              <input type="text" class="mfa-box" maxlength="1" pattern="[0-9]">
            </div>
          </div>

          <!-- Form Options -->
          <div class="form-options">
            <label class="remember-checkbox">
              <input type="checkbox" checked> Remember this device
            </label>
            <a href="#" class="forgot-link">Forgot admin password?</a>
          </div>

          <!-- Device Recognition -->
          <div class="device-info">
            <i class="fa-solid fa-laptop"></i> 
            Recognized device: MacBook Pro · Last login: 2 hours ago
          </div>

          <!-- Login Button -->
          <button type="submit" class="login-btn" id="loginBtn">
            <span class="btn-text">Access Admin Panel</span>
            <span class="btn-loader"></span>
          </button>

          <!-- Security Scan Animation -->
          <div class="security-scan" id="securityScan">
            <div class="scan-line">
              <i class="fa-regular fa-circle-check"></i>
              <span>Verifying credentials...</span>
            </div>
            <div class="scan-line">
              <i class="fa-regular fa-circle"></i>
              <span>Checking security protocols...</span>
            </div>
            <div class="scan-line">
              <i class="fa-regular fa-circle"></i>
              <span>Establishing secure channel...</span>
            </div>
          </div>
        </form>

        <div class="login-footer">
          <i class="fa-solid fa-shield"></i> Unauthorized access prohibited · All actions are logged
        </div>
      </div>
    </div>
  </div>

  <script>
    (function() {
      // Preloader
      window.addEventListener('load', function() {
        setTimeout(function() {
          document.getElementById('preloader').classList.add('hidden');
        }, 2000);
      });

      // Typing animation
      const phrases = [
        'Secure access to Crypterra management system',
        'Authorized personnel only',
        'High-security admin portal',
        '256-bit encrypted access'
      ];
      
      let phraseIndex = 0;
      let charIndex = 0;
      let isDeleting = false;
      const typingElement = document.getElementById('typingText');
      
      function typeEffect() {
        const currentPhrase = phrases[phraseIndex];
        
        if (isDeleting) {
          typingElement.textContent = currentPhrase.substring(0, charIndex - 1);
          charIndex--;
        } else {
          typingElement.textContent = currentPhrase.substring(0, charIndex + 1);
          charIndex++;
        }
        
        if (!isDeleting && charIndex === currentPhrase.length) {
          isDeleting = true;
          setTimeout(typeEffect, 2000);
        } else if (isDeleting && charIndex === 0) {
          isDeleting = false;
          phraseIndex = (phraseIndex + 1) % phrases.length;
          setTimeout(typeEffect, 500);
        } else {
          setTimeout(typeEffect, isDeleting ? 50 : 100);
        }
      }
      
      setTimeout(typeEffect, 2000);

      // Particle system
      const canvas = document.getElementById('particles');
      const ctx = canvas.getContext('2d');
      
      function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
      }
      
      resizeCanvas();
      
      const particles = [];
      const particleCount = 50;
      
      for (let i = 0; i < particleCount; i++) {
        particles.push({
          x: Math.random() * canvas.width,
          y: Math.random() * canvas.height,
          size: Math.random() * 2 + 1,
          speedX: (Math.random() - 0.5) * 0.5,
          speedY: (Math.random() - 0.5) * 0.5,
          color: `rgba(34, 197, 94, ${Math.random() * 0.3})`
        });
      }
      
      function animateParticles() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        particles.forEach(p => {
          p.x += p.speedX;
          p.y += p.speedY;
          
          if (p.x < 0 || p.x > canvas.width) p.speedX *= -1;
          if (p.y < 0 || p.y > canvas.height) p.speedY *= -1;
          
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
          ctx.fillStyle = p.color;
          ctx.fill();
        });
        
        requestAnimationFrame(animateParticles);
      }
      
      animateParticles();
      
      window.addEventListener('resize', resizeCanvas);

      // Password toggle
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');
      
      togglePassword.addEventListener('click', () => {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        togglePassword.innerHTML = type === 'password' ? '<i class="fa-regular fa-eye"></i>' : '<i class="fa-regular fa-eye-slash"></i>';
      });

      // MFA inputs handling
      const mfaBoxes = document.querySelectorAll('.mfa-box');
      mfaBoxes.forEach((box, index) => {
        box.addEventListener('input', () => {
          if (box.value.length === 1 && index < mfaBoxes.length - 1) {
            mfaBoxes[index + 1].focus();
          }
        });
        
        box.addEventListener('keydown', (e) => {
          if (e.key === 'Backspace' && index > 0 && !box.value) {
            mfaBoxes[index - 1].focus();
          }
        });
      });

      // Submit UX: show loader + scan while the form posts.
      const loginBtn = document.getElementById('loginBtn');
      const loginForm = document.getElementById('loginForm');
      const securityScan = document.getElementById('securityScan');

      loginForm.addEventListener('submit', () => {
        loginBtn.classList.add('loading');
        securityScan.classList.add('show');
      });

      // Flicker transition on theme toggle (simulated)
      const themeToggle = document.querySelector('.theme-toggle');
      if (themeToggle) {
        themeToggle.addEventListener('click', () => {
          document.body.classList.add('flicker-transition');
          setTimeout(() => {
            document.body.classList.remove('flicker-transition');
          }, 500);
        });
      }
    })();
  </script>
</body>
</html>