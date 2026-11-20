<?php
declare(strict_types=1);

/**
 * Absolute path to security.php from site root (never relative — relative breaks when SCRIPT_NAME is under Userprofile/api/).
 */
function userprofile_security_page_url(string $query = ''): string {
  $sn = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  if ($sn !== '' && preg_match('#^(.*?/Userprofile)/#', $sn, $m)) {
    $base = rtrim($m[1], '/');
    return $base . '/security.php' . ($query !== '' ? '?' . $query : '');
  }
  return '/Userprofile/security.php' . ($query !== '' ? '?' . $query : '');
}

/**
 * Absolute path to login.php at site root (never relative — relative breaks when SCRIPT_NAME is under Userprofile/api/).
 */
function userprofile_login_url(string $query = ''): string {
  $sn = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  if ($sn !== '' && preg_match('#^(.*?)/Userprofile/#', $sn, $m)) {
    $prefix = rtrim(str_replace('\\', '/', (string)($m[1] ?? '')), '/');
    if ($prefix !== '' && $prefix[0] !== '/') {
      $prefix = '/' . $prefix;
    }
    return $prefix . '/login.php' . ($query !== '' ? '?' . $query : '');
  }
  return '/login.php' . ($query !== '' ? '?' . $query : '');
}

/**
 * True when the current script is allowed while force_password_reset is on (must match SCRIPT_NAME quirks: no leading slash, backslashes).
 * Includes Security page + APIs it uses (password change, sessions) + logout.
 */
function user_auth_is_allowed_script_while_force_pw_reset(string $scriptName): bool {
  $n = str_replace('\\', '/', $scriptName);
  return (bool) preg_match(
    '#Userprofile/(support\\.php|security\\.php|logout\\.php|logout_do\\.php|api/(security_api|sessions_api|support_api)\\.php)$#i',
    $n
  );
}

function user_session_start(): void {
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  // Keep user session isolated from admin session.
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_name('crypterra_user');
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
    session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
  }

  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
}

user_session_start();

if (empty($_SESSION['user_id'])) {
  $next = $_SERVER['REQUEST_URI'] ?? '';
  if (!is_string($next)) {
    $next = '';
  }
  $next = substr($next, 0, 500);

  $q = 'view=login';
  if ($next !== '') {
    $q .= '&next=' . rawurlencode($next);
  }
  header('Location: ' . userprofile_login_url($q));
  exit();
}

// Enforce admin security controls (lock/freeze/force reset/force logout).
require_once __DIR__ . '/user_db.php';

try {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

  $uid = (int)$_SESSION['user_id'];
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);

  // Record and enforce per-device sessions for Security -> Active Sessions.
  $sid = session_id();
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $ip = is_string($ip) ? $ip : '';
  $ua = is_string($ua) ? $ua : '';
  if ($sid !== '') {
    user_record_session($db, $uid, $sid, $ip, $ua);
    if (!user_session_exists($db, $uid, $sid)) {
      $db->close();
      $_SESSION = [];
      if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
      }
      header('Location: ' . userprofile_login_url('view=login'));
      exit();
    }
  }

  $flags = user_fetch_account_flags($db, (string)$DB_NAME, $uid);
  $db->close();

  $sessVer = isset($_SESSION['user_session_version']) ? (int)$_SESSION['user_session_version'] : null;
  if ($sessVer === null) {
    $_SESSION['user_session_version'] = (int)$flags['session_version'];
    $sessVer = (int)$flags['session_version'];
  }

  if ($sessVer !== (int)$flags['session_version']) {
    // Forced logout.
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_destroy();
    }
    header('Location: ' . userprofile_login_url('view=login'));
    exit();
  }

  if (empty($flags['is_active']) || !empty($flags['is_locked'])) {
    $blocked = !empty($flags['is_locked']) ? 'locked' : 'suspended';
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_destroy();
    }
    header('Location: ' . userprofile_login_url('view=login&blocked=' . rawurlencode($blocked)));
    exit();
  }

  if (!empty($flags['force_password_reset'])) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!is_string($script)) {
      $script = '';
    }
    // Allow only Security (change password) and Logout. Do not use str_ends_with('/Userprofile/security.php'):
    // SCRIPT_NAME may omit a leading slash or use backslashes on Windows, which wrongly redirects every page.
    if (!user_auth_is_allowed_script_while_force_pw_reset($script)) {
      header('Location: ' . userprofile_security_page_url('force_pw=1'));
      exit();
    }
  }
} catch (Throwable $e) {
  // Best-effort only: don't break auth flow if DB unavailable.
  error_log('[user_auth security controls] ' . $e->getMessage());
}

