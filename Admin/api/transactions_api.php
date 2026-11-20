<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_csrf.php';
require_once dirname(__DIR__) . '/includes/admin_transactions_data.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function tx_api_int($v, int $default = 0): int {
  if (is_numeric($v)) {
    return (int)$v;
  }
  return $default;
}

if ($method === 'GET') {
  try {
    $db = admin_tx_db();
  } catch (Throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db']);
    exit;
  }

  ensure_transactions_table($db);
  global $DB_NAME;
  admin_tx_ensure_counterparty_column($db, (string)$DB_NAME);

  if ($action === 'stats') {
    $summary = admin_tx_summary($db);
    $db->close();
    echo json_encode(['ok' => true, 'stats' => $summary]);
    exit;
  }

  if ($action === 'feed') {
    $lim = tx_api_int($_GET['limit'] ?? 8, 8);
    $feed = admin_tx_recent_feed($db, $lim);
    $db->close();
    echo json_encode(['ok' => true, 'feed' => $feed]);
    exit;
  }

  if ($action === 'detail') {
    $id = tx_api_int($_GET['id'] ?? 0, 0);
    $row = admin_tx_get($db, $id);
    $db->close();
    if (!$row) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'not_found']);
      exit;
    }
    echo json_encode(['ok' => true, 'transaction' => $row]);
    exit;
  }

  $page = tx_api_int($_GET['page'] ?? 1, 1);
  $perPage = tx_api_int($_GET['per_page'] ?? 25, 25);
  $q = isset($_GET['q']) && is_string($_GET['q']) ? (string)$_GET['q'] : '';
  $type = isset($_GET['type']) && is_string($_GET['type']) ? (string)$_GET['type'] : '';
  $status = isset($_GET['status']) && is_string($_GET['status']) ? (string)$_GET['status'] : '';
  $sort = isset($_GET['sort']) && is_string($_GET['sort']) ? (string)$_GET['sort'] : '';

  $payload = admin_tx_list($db, $page, $perPage, $q, $type, $status, $sort);
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
  $db = admin_tx_db();
} catch (Throwable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db']);
  exit;
}

ensure_transactions_table($db);
global $DB_NAME;
admin_tx_ensure_counterparty_column($db, (string)$DB_NAME);

$id = tx_api_int($input['id'] ?? 0, 0);
$note = null;
if (isset($input['note']) && is_string($input['note'])) {
  $note = $input['note'];
}

if ($action === 'approve' || $action === 'reject' || $action === 'flag' || $action === 'reverse') {
  $res = admin_tx_apply_action($db, $id, $action, $note);
  $db->close();
  echo json_encode($res + ['ok' => (bool)($res['ok'] ?? false)]);
  exit;
}

$db->close();
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown_action']);
