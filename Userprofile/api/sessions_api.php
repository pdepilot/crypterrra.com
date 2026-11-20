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

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
} catch (Throwable $e) {
  http_response_code(503);
  echo json_encode(['ok' => false, 'error' => 'Database unavailable.']);
  exit;
}

if ($method === 'GET') {
  $sid = session_id();
  $rows = user_list_sessions($db, $userId, is_string($sid) ? $sid : '', 15);
  $db->close();
  echo json_encode(['ok' => true, 'sessions' => $rows, 'csrf' => user_csrf_token()], JSON_UNESCAPED_SLASHES);
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
  $d = json_decode($raw, true);
  if (is_array($d)) $data = $d;
}

$csrf = isset($data['csrf_token']) && is_string($data['csrf_token']) ? $data['csrf_token'] : null;
if (!user_verify_csrf($csrf)) {
  $db->close();
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Invalid security token.']);
  exit;
}

$action = isset($data['action']) && is_string($data['action']) ? $data['action'] : '';
if ($action !== 'terminate') {
  $db->close();
  echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
  exit;
}

$sessionRowId = isset($data['session_id']) && is_numeric($data['session_id']) ? (int)$data['session_id'] : 0;
if ($sessionRowId <= 0) {
  $db->close();
  echo json_encode(['ok' => false, 'error' => 'Invalid session id.']);
  exit;
}

// Prevent terminating the current session via this endpoint (avoid self-lockout UX).
$sid = session_id();
$list = user_list_sessions($db, $userId, is_string($sid) ? $sid : '', 50);
foreach ($list as $s) {
  if (!empty($s['is_current']) && (int)($s['id'] ?? 0) === $sessionRowId) {
    $db->close();
    echo json_encode(['ok' => false, 'error' => 'Cannot terminate current session.']);
    exit;
  }
}

$ok = user_terminate_session($db, $userId, $sessionRowId);
if ($ok) {
  $rip = $_SERVER['REMOTE_ADDR'] ?? '';
  $rip = is_string($rip) ? $rip : '';
  user_log_security_activity(
    $db,
    $userId,
    'session_terminated',
    'Session ended',
    'Another browser or device session was signed out from Security settings.',
    $rip
  );
}
$rows = user_list_sessions($db, $userId, is_string($sid) ? $sid : '', 15);
$db->close();

echo json_encode(['ok' => $ok, 'sessions' => $rows, 'csrf' => user_csrf_token()], JSON_UNESCAPED_SLASHES);

