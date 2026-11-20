<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/user_auth.php';
require_once dirname(__DIR__) . '/includes/user_notifications_data.php';

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
} catch (Throwable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db']);
  exit;
}

if ($method === 'GET') {
  $action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : 'list';
  if ($action === 'count') {
    $n = user_inapp_notifications_unread_count($db, $userId);
    $db->close();
    echo json_encode(['ok' => true, 'unread' => $n], JSON_UNESCAPED_SLASHES);
    exit;
  }
  $limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 20;
  $items = user_inapp_notifications_list($db, $userId, $limit);
  $db->close();
  echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES);
  exit;
}

if ($method !== 'POST') {
  $db->close();
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method']);
  exit;
}

$raw = file_get_contents('php://input');
$json = is_string($raw) ? json_decode($raw, true) : null;
$input = is_array($json) ? $json : $_POST;
$action = isset($input['action']) && is_string($input['action']) ? $input['action'] : '';

if ($action === 'mark_read') {
  $id = isset($input['id']) && is_numeric($input['id']) ? (int)$input['id'] : 0;
  user_inapp_notifications_mark_read($db, $userId, $id);
  $unread = user_inapp_notifications_unread_count($db, $userId);
  $db->close();
  echo json_encode(['ok' => true, 'unread' => $unread], JSON_UNESCAPED_SLASHES);
  exit;
}

if ($action === 'mark_all_read') {
  $n = user_inapp_notifications_mark_all_read($db, $userId);
  $db->close();
  echo json_encode(['ok' => true, 'updated' => $n, 'unread' => 0], JSON_UNESCAPED_SLASHES);
  exit;
}

$db->close();
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'action']);
