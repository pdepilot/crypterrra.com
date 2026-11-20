<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__, 2) . '/includes/contact_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$adminId = (int)($GLOBALS['ADMIN_SESSION_ID'] ?? 0);
$adminEmail = (string)($GLOBALS['ADMIN_SESSION_EMAIL'] ?? '');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && ($action === '' || $action === 'list')) {
  $rows = contact_fetch_all_for_admin();
  $contacts = [];
  foreach ($rows as $r) {
    $id = (int)$r['id'];
    $contacts[] = [
      'id' => $id,
      'ticket' => contact_ticket_ref($id),
      'name' => (string)$r['name'],
      'email' => (string)$r['email'],
      'subject' => (string)$r['subject'],
      'message' => (string)$r['message'],
      'category' => (string)$r['category'],
      'priority' => (string)$r['priority'],
      'status' => (string)$r['status'],
      'received' => (string)$r['created_at'],
    ];
  }
  $logs = contact_fetch_activity_logs(150);
  $activity = [];
  foreach ($logs as $l) {
    $activity[] = [
      'time' => (string)$l['created_at'],
      'admin' => (string)$l['admin_email'],
      'action' => (string)$l['action'],
      'ticket' => (string)$l['ticket_ref'],
      'details' => (string)($l['details'] ?? ''),
    ];
  }
  echo json_encode([
    'ok' => true,
    'contacts' => $contacts,
    'stats' => contact_stats(),
    'activity' => $activity,
  ]);
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method']);
  exit;
}

$raw = file_get_contents('php://input');
$json = is_string($raw) ? json_decode($raw, true) : null;
$input = is_array($json) ? $json : $_POST;

$action = isset($input['action']) && is_string($input['action']) ? $input['action'] : '';

function contact_api_id(array $input): int {
  if (isset($input['id']) && is_numeric($input['id'])) {
    return (int)$input['id'];
  }
  return 0;
}

if ($action === 'update') {
  $id = contact_api_id($input);
  $field = isset($input['field']) && is_string($input['field']) ? $input['field'] : '';
  $value = isset($input['value']) && is_string($input['value']) ? $input['value'] : '';
  if ($id <= 0 || !in_array($field, ['status', 'priority', 'category'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
  }
  $ok = contact_update_field($id, $field, $value);
  if ($ok) {
    contact_log_admin_activity(
      $adminId,
      $adminEmail,
      'update_' . $field,
      contact_ticket_ref($id),
      $field . '=' . $value
    );
  }
  echo json_encode(['ok' => $ok]);
  exit;
}

if ($action === 'resolve') {
  $id = contact_api_id($input);
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
  }
  $ok = contact_update_field($id, 'status', 'resolved');
  if ($ok) {
    contact_log_admin_activity($adminId, $adminEmail, 'resolve', contact_ticket_ref($id), 'marked resolved');
  }
  echo json_encode(['ok' => $ok]);
  exit;
}

if ($action === 'delete') {
  $id = contact_api_id($input);
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
  }
  $ref = contact_ticket_ref($id);
  $ok = contact_delete($id);
  if ($ok) {
    contact_log_admin_activity($adminId, $adminEmail, 'delete', $ref, 'submission removed');
  }
  echo json_encode(['ok' => $ok]);
  exit;
}

if ($action === 'reply_log') {
  $ticketRef = isset($input['ticket_ref']) && is_string($input['ticket_ref']) ? trim($input['ticket_ref']) : '';
  $message = isset($input['message']) && is_string($input['message']) ? trim($input['message']) : '';
  if ($ticketRef === '' || $message === '') {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
  }
  contact_log_admin_activity(
    $adminId,
    $adminEmail,
    'reply',
    $ticketRef,
    mb_substr($message, 0, 2000)
  );
  echo json_encode(['ok' => true]);
  exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown_action']);
