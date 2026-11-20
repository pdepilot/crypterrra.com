<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/user_auth.php';
require_once dirname(__DIR__) . '/includes/user_db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function security_api_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!is_string($raw) || $raw === '') {
    return [];
  }
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

if ($method === 'GET') {
  try {
    $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
    $schema = (string)$DB_NAME;
    $state = user_fetch_security_state($db, $schema, $userId);
    $activity = user_list_security_activity($db, $userId, 25);
    $db->close();
    echo json_encode(
      ['ok' => true, 'state' => $state, 'activity' => $activity, 'csrf' => user_csrf_token()],
      JSON_UNESCAPED_SLASHES
    );
  } catch (Throwable $e) {
    error_log('[user security_api] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load security state.']);
  }
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

$data = security_api_json_body();
$csrf = isset($data['csrf_token']) && is_string($data['csrf_token']) ? $data['csrf_token'] : null;
if (!user_verify_csrf($csrf)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Invalid security token.']);
  exit;
}

$action = isset($data['action']) && is_string($data['action']) ? $data['action'] : '';

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
} catch (Throwable $e) {
  http_response_code(503);
  echo json_encode(['ok' => false, 'error' => 'Database unavailable.']);
  exit;
}

$schema = (string)$DB_NAME;

$userReqIp = $_SERVER['REMOTE_ADDR'] ?? '';
$userReqIp = is_string($userReqIp) ? $userReqIp : '';

try {
  if ($action === 'clear_activity') {
    user_clear_security_activity($db, $userId);
  } elseif ($action === 'enable_2fa') {
    $code = isset($data['code']) && is_string($data['code']) ? preg_replace('/\D/', '', $data['code']) : '';
    if (strlen($code) !== 6) {
      throw new InvalidArgumentException('Enter a valid 6-digit code.');
    }
    user_set_twofa_enabled($db, $schema, $userId, true);
    user_log_security_activity(
      $db,
      $userId,
      'twofa_enabled',
      'Two-factor authentication enabled',
      '2FA is now required when signing in.',
      $userReqIp
    );
  } elseif ($action === 'disable_2fa') {
    $code = isset($data['code']) && is_string($data['code']) ? preg_replace('/\D/', '', $data['code']) : '';
    if (strlen($code) !== 6) {
      throw new InvalidArgumentException('Enter a valid 6-digit code.');
    }
    user_set_twofa_enabled($db, $schema, $userId, false);
    user_log_security_activity(
      $db,
      $userId,
      'twofa_disabled',
      'Two-factor authentication disabled',
      'Sign-in no longer requires an authenticator code.',
      $userReqIp
    );
  } elseif ($action === 'set_alerts') {
    $en = isset($data['enabled']) && ($data['enabled'] === true || $data['enabled'] === 1 || $data['enabled'] === '1');
    user_set_login_alerts_enabled($db, $schema, $userId, $en);
    user_log_security_activity(
      $db,
      $userId,
      'login_alerts',
      $en ? 'Login alerts enabled' : 'Login alerts disabled',
      $en
        ? 'You will receive email notifications for new sign-ins when available.'
        : 'Email notifications for new sign-ins are turned off.',
      $userReqIp
    );
  } elseif ($action === 'change_password') {
    $cur = isset($data['current_password']) && is_string($data['current_password']) ? $data['current_password'] : '';
    $new = isset($data['new_password']) && is_string($data['new_password']) ? $data['new_password'] : '';
    $r = user_change_password($db, $schema, $userId, $cur, $new);
    if (!($r['ok'] ?? false)) {
      throw new InvalidArgumentException(is_string($r['error'] ?? null) ? (string)$r['error'] : 'Could not change password.');
    }
    session_regenerate_id(true);
    user_log_security_activity(
      $db,
      $userId,
      'password_changed',
      'Password changed',
      'Your account password was updated.',
      $userReqIp
    );
  } else {
    throw new InvalidArgumentException('Unknown action.');
  }

  $state = user_fetch_security_state($db, $schema, $userId);
  $activity = user_list_security_activity($db, $userId, 25);
  $db->close();
  echo json_encode(
    ['ok' => true, 'state' => $state, 'activity' => $activity, 'csrf' => user_csrf_token()],
    JSON_UNESCAPED_SLASHES
  );
} catch (InvalidArgumentException $e) {
  $db->close();
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
  error_log('[user security_api] ' . $e->getMessage());
  if (isset($db) && $db instanceof mysqli) {
    $db->close();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Request failed.']);
}
