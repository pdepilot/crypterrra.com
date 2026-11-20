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

$schema = (string)$DB_NAME;

function settings_api_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!is_string($raw) || $raw === '') {
    return [];
  }
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  try {
    $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
    $bundle = user_fetch_settings_bundle($db, $schema, $userId);
    $db->close();
    if (!is_array($bundle)) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'Account not found.']);
      exit;
    }
    echo json_encode(
      [
        'ok' => true,
        'user' => [
          'full_name' => $bundle['full_name'],
          'email' => $bundle['email'],
          'phone' => $bundle['phone'],
          'wallet_id' => $bundle['wallet_id'],
        ],
        'settings' => $bundle['settings'],
        'csrf' => user_csrf_token(),
      ],
      JSON_UNESCAPED_SLASHES
    );
  } catch (Throwable $e) {
    error_log('[settings_api GET] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load settings.']);
  }
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

$data = settings_api_json_body();
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

$userReqIp = $_SERVER['REMOTE_ADDR'] ?? '';
$userReqIp = is_string($userReqIp) ? $userReqIp : '';

try {
  if ($action === 'save_profile') {
    $full = isset($data['full_name']) && is_string($data['full_name']) ? $data['full_name'] : '';
    $email = isset($data['email']) && is_string($data['email']) ? $data['email'] : '';
    $phone = isset($data['phone']) && is_string($data['phone']) ? $data['phone'] : '';
    $r = user_update_profile_fields($db, $schema, $userId, $full, $email, $phone);
    if (!($r['ok'] ?? false)) {
      $db->close();
      echo json_encode(['ok' => false, 'error' => (string)($r['error'] ?? 'Could not save profile.')]);
      exit;
    }
    $_SESSION['user_full_name'] = trim($full);
    $_SESSION['user_email'] = trim($email);
    user_log_security_activity(
      $db,
      $userId,
      'profile_updated',
      'Profile updated',
      'Your display name, email, or phone was changed.',
      $userReqIp
    );
    $bundle = user_fetch_settings_bundle($db, $schema, $userId);
    $db->close();
    echo json_encode(
      [
        'ok' => true,
        'user' => $bundle ? [
          'full_name' => $bundle['full_name'],
          'email' => $bundle['email'],
          'phone' => $bundle['phone'],
          'wallet_id' => $bundle['wallet_id'],
        ] : null,
        'settings' => $bundle['settings'] ?? user_settings_default_payload(),
        'csrf' => user_csrf_token(),
      ],
      JSON_UNESCAPED_SLASHES
    );
    exit;
  }

  if ($action === 'save_notifications') {
    $patch = [
      'emailNotifications' => !empty($data['emailNotifications']),
      'pushNotifications' => !empty($data['pushNotifications']),
      'transactionAlerts' => !empty($data['transactionAlerts']),
    ];
    $settings = user_merge_and_save_settings_json($db, $schema, $userId, $patch);
    user_log_security_activity(
      $db,
      $userId,
      'settings_notifications',
      'Notification preferences saved',
      '',
      $userReqIp
    );
    $db->close();
    echo json_encode(['ok' => true, 'settings' => $settings, 'csrf' => user_csrf_token()], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'save_preferences') {
    $patch = [];
    if (isset($data['language']) && is_string($data['language'])) {
      $patch['language'] = $data['language'];
    }
    if (isset($data['timezone']) && is_string($data['timezone'])) {
      $patch['timezone'] = $data['timezone'];
    }
    if (isset($data['currency']) && is_string($data['currency'])) {
      $patch['currency'] = $data['currency'];
    }
    $settings = user_merge_and_save_settings_json($db, $schema, $userId, $patch);
    user_log_security_activity(
      $db,
      $userId,
      'settings_preferences',
      'Preferences saved',
      '',
      $userReqIp
    );
    $db->close();
    echo json_encode(['ok' => true, 'settings' => $settings, 'csrf' => user_csrf_token()], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'save_display') {
    $patch = [
      'compactView' => !empty($data['compactView']),
      'animatedCharts' => !empty($data['animatedCharts']),
    ];
    if (isset($data['updateFrequency']) && is_string($data['updateFrequency'])) {
      $patch['updateFrequency'] = $data['updateFrequency'];
    }
    $settings = user_merge_and_save_settings_json($db, $schema, $userId, $patch);
    user_log_security_activity(
      $db,
      $userId,
      'settings_display',
      'Display settings saved',
      '',
      $userReqIp
    );
    $db->close();
    echo json_encode(['ok' => true, 'settings' => $settings, 'csrf' => user_csrf_token()], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'deactivate_account') {
    $pw = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
    if ($pw === '' || !user_verify_password($db, $userId, $pw)) {
      $db->close();
      echo json_encode(['ok' => false, 'error' => 'Incorrect password.']);
      exit;
    }
    user_deactivate_own_account($db, $schema, $userId);
    user_log_security_activity(
      $db,
      $userId,
      'account_deactivated',
      'Account deactivated',
      'Your account was deactivated from Settings.',
      $userReqIp
    );
    $db->close();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_destroy();
    }
    echo json_encode(
      [
        'ok' => true,
        'logout' => true,
        'redirect' => userprofile_login_url('view=login'),
        'message' => 'Your account has been deactivated.',
      ],
      JSON_UNESCAPED_SLASHES
    );
    exit;
  }

  if ($action === 'delete_account') {
    $pw = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
    $confirm = isset($data['confirm_text']) && is_string($data['confirm_text']) ? trim($data['confirm_text']) : '';
    if ($confirm !== 'DELETE') {
      $db->close();
      echo json_encode(['ok' => false, 'error' => 'Type DELETE to confirm account removal.']);
      exit;
    }
    if ($pw === '' || !user_verify_password($db, $userId, $pw)) {
      $db->close();
      echo json_encode(['ok' => false, 'error' => 'Incorrect password.']);
      exit;
    }
    user_delete_own_account($db, $schema, $userId);
    user_log_security_activity(
      $db,
      $userId,
      'account_deleted',
      'Account deleted',
      'The account was permanently closed from Settings.',
      $userReqIp
    );
    $db->close();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_destroy();
    }
    echo json_encode(
      [
        'ok' => true,
        'logout' => true,
        'redirect' => userprofile_login_url('view=login'),
        'message' => 'Your account has been deleted.',
      ],
      JSON_UNESCAPED_SLASHES
    );
    exit;
  }

  $db->close();
  echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
} catch (Throwable $e) {
  error_log('[settings_api] ' . $e->getMessage());
  if (isset($db) && $db instanceof mysqli) {
    $db->close();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Request failed.']);
}
