<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_csrf.php';
require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function admin_user_action_audit(mysqli $db, int $adminId, string $action, string $identifier, ?string $reason = null): void {
  // Best-effort audit row (used by Security Center "Recent Security Activity").
  // Only runs if table exists; failures are ignored.
  $r = @$db->query("SHOW TABLES LIKE 'admin_logs'");
  $has = $r && $r->num_rows > 0;
  if ($r) $r->free();
  if (!$has) return;

  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $ip = is_string($ip) ? $ip : '';
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $ua = is_string($ua) ? $ua : '';
  $ua = substr($ua, 0, 255);

  $detail = $action . ' ' . $identifier;
  if (is_string($reason) && trim($reason) !== '') {
    $detail .= ' · ' . substr(trim($reason), 0, 120);
  }
  $detail = substr($detail, 0, 50); // admin_logs.action is VARCHAR(50)

  if ($stmt = $db->prepare('INSERT INTO admin_logs (admin_id, action, ip, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())')) {
    $stmt->bind_param('isss', $adminId, $detail, $ip, $ua);
    @$stmt->execute();
    $stmt->close();
  }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
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

$token = isset($data['csrf_token']) && is_string($data['csrf_token']) ? $data['csrf_token'] : null;
if (!admin_verify_csrf($token)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Invalid security token.']);
  exit;
}

$action = isset($data['action']) && is_string($data['action']) ? $data['action'] : '';
$identifier = isset($data['identifier']) && is_string($data['identifier']) ? trim($data['identifier']) : '';
$reason = isset($data['reason']) && is_string($data['reason']) ? trim($data['reason']) : '';

if ($identifier === '') {
  echo json_encode(['ok' => false, 'error' => 'Missing user identifier.']);
  exit;
}

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
$schema = (string)$DB_NAME;

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  ensure_user_dashboard_schema($db, $schema);
  ensure_user_admin_security_columns($db, $schema);

  $u = admin_user_find_by_identifier($db, $identifier);
  if (!$u) {
    $db->close();
    echo json_encode(['ok' => false, 'error' => 'User not found.']);
    exit;
  }
  $userId = (int)$u['id'];

  match ($action) {
    'lock' => admin_user_set_lock($db, $schema, $userId, true, $reason),
    'unlock' => admin_user_set_lock($db, $schema, $userId, false, ''),
    'freeze' => admin_user_set_active($db, $schema, $userId, false),
    'unfreeze' => admin_user_set_active($db, $schema, $userId, true),
    'force_password_reset' => admin_user_set_force_password_reset($db, $schema, $userId, true),
    'clear_force_password_reset' => admin_user_set_force_password_reset($db, $schema, $userId, false),
    'force_logout' => admin_user_force_logout($db, $schema, $userId),
    default => throw new InvalidArgumentException('Unknown action.'),
  };

  $adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
  if ($adminId > 0) {
    admin_user_action_audit($db, $adminId, $action, $identifier, $reason);
  }

  $db->close();
  echo json_encode(['ok' => true, 'csrf' => admin_csrf_token()], JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
  if (isset($db) && $db instanceof mysqli) $db->close();
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
  error_log('[user_security_actions] ' . $e->getMessage());
  if (isset($db) && $db instanceof mysqli) $db->close();
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Request failed.']);
}

