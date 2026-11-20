<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_csrf.php';
require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';
require_once dirname(__DIR__) . '/includes/admin_security_settings.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
} catch (Throwable $e) {
  http_response_code(503);
  echo json_encode(['ok' => false, 'error' => 'Database unavailable.']);
  exit;
}

if ($method === 'GET') {
  $settings = admin_get_security_settings($db);
  $db->close();
  echo json_encode(['ok' => true, 'settings' => $settings], JSON_UNESCAPED_SLASHES);
  exit;
}

if ($method !== 'POST') {
  $db->close();
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

$raw = file_get_contents('php://input');
$data = [];
if (is_string($raw) && $raw !== '') {
  $decoded = json_decode($raw, true);
  if (is_array($decoded)) {
    $data = $decoded;
  }
}

$token = null;
if (isset($data['csrf_token']) && is_string($data['csrf_token'])) {
  $token = $data['csrf_token'];
} elseif (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
  $token = $_POST['csrf_token'];
}

if (!admin_verify_csrf($token)) {
  $db->close();
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Invalid security token.']);
  exit;
}

$current = admin_get_security_settings($db);

$admin2fa = $data['admin_2fa_enabled'] ?? null;
if (is_bool($admin2fa)) {
  $current['admin_2fa_enabled'] = $admin2fa;
} elseif (isset($data['admin_2fa_enabled'])) {
  $v = $data['admin_2fa_enabled'];
  $current['admin_2fa_enabled'] = $v === true || $v === 1 || $v === '1' || $v === 'true';
}

$user2fa = $data['default_user_2fa_enabled'] ?? null;
if (is_bool($user2fa)) {
  $current['default_user_2fa_enabled'] = $user2fa;
} elseif (isset($data['default_user_2fa_enabled'])) {
  $v = $data['default_user_2fa_enabled'];
  $current['default_user_2fa_enabled'] = $v === true || $v === 1 || $v === '1' || $v === 'true';
}

foreach (
  [
    'access_session_timeout_min' => 'access_session_timeout_min',
    'max_login_attempts' => 'max_login_attempts',
    'withdrawal_limit_usd' => 'withdrawal_limit_usd',
    'auto_lock_min' => 'auto_lock_min',
    'user_session_timeout_min' => 'user_session_timeout_min',
  ] as $key => $dest
) {
  if (isset($data[$key]) && is_numeric($data[$key])) {
    $current[$dest] = $key === 'withdrawal_limit_usd' ? (float)$data[$key] : (int)$data[$key];
  }
}

try {
  admin_save_security_settings($db, $current);
} catch (Throwable $e) {
  error_log('[security_api] ' . $e->getMessage());
  $db->close();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Could not save settings.']);
  exit;
}

$saved = admin_get_security_settings($db);
$db->close();
echo json_encode(
  ['ok' => true, 'settings' => $saved, 'csrf' => admin_csrf_token()],
  JSON_UNESCAPED_SLASHES
);
