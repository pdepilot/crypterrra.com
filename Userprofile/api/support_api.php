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

function support_api_json_body(): array {
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
    $list = user_support_list_for_user($db, $userId, 40);
    $db->close();
    echo json_encode(['ok' => true, 'tickets' => $list, 'csrf' => user_csrf_token()], JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    error_log('[support_api GET] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load tickets.']);
  }
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

$data = support_api_json_body();
$csrf = isset($data['csrf_token']) && is_string($data['csrf_token']) ? $data['csrf_token'] : null;
if (!user_verify_csrf($csrf)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Invalid security token.']);
  exit;
}

$fullName = isset($data['full_name']) && is_string($data['full_name']) ? $data['full_name'] : '';
$email = isset($data['email']) && is_string($data['email']) ? $data['email'] : '';
$issueType = isset($data['issue_type']) && is_string($data['issue_type']) ? $data['issue_type'] : 'other';
$priority = isset($data['priority']) && is_string($data['priority']) ? $data['priority'] : 'medium';
$subject = isset($data['subject']) && is_string($data['subject']) ? $data['subject'] : '';
$message = isset($data['message']) && is_string($data['message']) ? $data['message'] : '';

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  $r = user_support_create_ticket($db, $userId, $fullName, $email, $issueType, $priority, $subject, $message);
  $db->close();
  if (!($r['ok'] ?? false)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string)($r['error'] ?? 'create_failed')], JSON_UNESCAPED_SLASHES);
    exit;
  }
  echo json_encode(
    [
      'ok' => true,
      'ticket_ref' => (string)($r['ticket_ref'] ?? ''),
      'id' => (int)($r['id'] ?? 0),
      'csrf' => user_csrf_token(),
    ],
    JSON_UNESCAPED_SLASHES
  );
} catch (Throwable $e) {
  error_log('[support_api POST] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Could not create ticket.']);
}
