<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_csrf.php';
require_once dirname(__DIR__) . '/includes/admin_notifications_data.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';

function nc_int($v, int $default = 0): int {
  if (is_numeric($v)) {
    return (int)$v;
  }
  return $default;
}

if ($method === 'GET') {
  try {
    $db = admin_notifications_db();
  } catch (Throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db']);
    exit;
  }

  if ($action === 'stats') {
    $stats = admin_notifications_chart_stats($db);
    $db->close();
    echo json_encode(['ok' => true] + $stats, JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'users') {
    $q = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
    $lim = nc_int($_GET['limit'] ?? 25, 25);
    $users = admin_notifications_search_users($db, $q, $lim);
    $db->close();
    echo json_encode(['ok' => true, 'users' => $users], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'feed') {
    $lim = nc_int($_GET['limit'] ?? 12, 12);
    $items = admin_notifications_recent_feed($db, $lim);
    $db->close();
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES);
    exit;
  }

  $limit = nc_int($_GET['limit'] ?? 100, 100);
  $offset = nc_int($_GET['offset'] ?? 0, 0);
  $rows = admin_notifications_list($db, $limit, $offset);
  $total = admin_notifications_total($db);
  $db->close();
  echo json_encode(['ok' => true, 'items' => $rows, 'total' => $total], JSON_UNESCAPED_SLASHES);
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
$csrf = isset($input['csrf_token']) && is_string($input['csrf_token']) ? $input['csrf_token'] : null;
if (!admin_verify_csrf($csrf)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'csrf']);
  exit;
}

$adminId = (int)($GLOBALS['ADMIN_SESSION_ID'] ?? 0);

try {
  $db = admin_notifications_db();
} catch (Throwable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db']);
  exit;
}

admin_notifications_ensure_schema($db);

if ($action === 'send') {
  $channels = isset($input['channels']) && is_array($input['channels']) ? $input['channels'] : [];
  $payload = [
    'title' => isset($input['title']) && is_string($input['title']) ? $input['title'] : '',
    'message_html' => isset($input['message_html']) && is_string($input['message_html']) ? $input['message_html'] : '',
    'recipient' => isset($input['recipient']) && is_string($input['recipient']) ? $input['recipient'] : 'all',
    'specific_user' => isset($input['specific_user']) && is_string($input['specific_user']) ? $input['specific_user'] : '',
    'specific_user_id' => isset($input['specific_user_id']) ? (int)$input['specific_user_id'] : 0,
    'type' => isset($input['type']) && is_string($input['type']) ? $input['type'] : 'info',
    'channels' => [
      'in_app' => !empty($channels['in_app']),
      'email' => !empty($channels['email']),
      'push' => !empty($channels['push']),
    ],
  ];
  $res = admin_notifications_send($db, $adminId, $payload);
  $db->close();
  if (!($res['ok'] ?? false)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string)($res['error'] ?? 'send_failed')], JSON_UNESCAPED_SLASHES);
    exit;
  }
  echo json_encode(['ok' => true, 'id' => (int)($res['id'] ?? 0), 'recipient_count' => (int)($res['recipient_count'] ?? 0)], JSON_UNESCAPED_SLASHES);
  exit;
}

if ($action === 'delete') {
  $id = nc_int($input['id'] ?? 0, 0);
  if ($id <= 0) {
    $db->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'id']);
    exit;
  }
  $ok = admin_notifications_delete($db, $id);
  $db->close();
  if (!$ok) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
  }
  echo json_encode(['ok' => true]);
  exit;
}

$db->close();
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'action']);
