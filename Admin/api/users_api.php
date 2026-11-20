<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_csrf.php';
require_once dirname(__DIR__) . '/includes/admin_users_data.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function users_api_int($v, int $default = 0): int {
  if (is_numeric($v)) return (int)$v;
  return $default;
}

if ($method === 'GET') {
  try {
    $db = admin_users_db();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db']);
    exit;
  }

  if ($action === 'stats') {
    $stats = admin_users_summary($db);
    $db->close();
    echo json_encode(['ok' => true, 'stats' => $stats]);
    exit;
  }

  if ($action === 'detail') {
    $id = users_api_int($_GET['id'] ?? 0, 0);
    $user = admin_users_get($db, $id);
    $db->close();
    if (!$user) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'not_found']);
      exit;
    }
    echo json_encode(['ok' => true, 'user' => $user]);
    exit;
  }

  if ($action === 'activity') {
    $lim = users_api_int($_GET['limit'] ?? 12, 12);
    $lim = max(1, min(50, $lim));
    $feed = admin_users_activity_feed($db, $lim);
    $db->close();
    $items = [];
    foreach ($feed as $e) {
      $items[] = [
        'icon' => (string)($e['icon'] ?? ''),
        'name' => (string)($e['name'] ?? ''),
        'detail' => (string)($e['detail'] ?? ''),
        'time_ago' => admin_users_time_ago((string)($e['at'] ?? '')),
      ];
    }
    echo json_encode(['ok' => true, 'items' => $items]);
    exit;
  }

  // list (default)
  $page = users_api_int($_GET['page'] ?? 1, 1);
  $perPage = users_api_int($_GET['per_page'] ?? 25, 25);
  $q = isset($_GET['q']) && is_string($_GET['q']) ? (string)$_GET['q'] : '';
  $filter = isset($_GET['filter']) && is_string($_GET['filter']) ? (string)$_GET['filter'] : '';
  $sort = isset($_GET['sort']) && is_string($_GET['sort']) ? (string)$_GET['sort'] : '';

  $payload = admin_users_list($db, $page, $perPage, $q, $filter, $sort);
  $db->close();
  echo json_encode(['ok' => true] + $payload);
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

try {
  $db = admin_users_db();
} catch (Throwable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db']);
  exit;
}

$id = users_api_int($input['id'] ?? 0, 0);

if ($action === 'activate') {
  $ok = admin_users_set_active($db, $id, true);
  $db->close();
  echo json_encode(['ok' => $ok]);
  exit;
}

if ($action === 'suspend') {
  $ok = admin_users_set_active($db, $id, false);
  $db->close();
  echo json_encode(['ok' => $ok]);
  exit;
}

if ($action === 'delete') {
  $ok = admin_users_delete($db, $id);
  $db->close();
  echo json_encode(['ok' => $ok]);
  exit;
}

if ($action === 'reset_password') {
  $res = admin_users_reset_password($db, $id);
  $db->close();
  echo json_encode($res + ['ok' => (bool)($res['ok'] ?? false)]);
  exit;
}

$db->close();
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown_action']);

