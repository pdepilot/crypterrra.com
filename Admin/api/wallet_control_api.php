<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_csrf.php';
require_once dirname(__DIR__) . '/includes/admin_wallet_data.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** @var string $DB_NAME */
global $DB_NAME;
$schema = (string)$DB_NAME;

$aid = (int)($GLOBALS['ADMIN_SESSION_ID'] ?? 0);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function wallet_api_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!is_string($raw) || $raw === '') {
    return [];
  }
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

if ($method === 'GET') {
  $action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : 'list';
  try {
    $db = admin_wallet_db();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db']);
    exit;
  }

  if ($action === 'stats') {
    $stats = admin_wallet_aggregate_stats($db, $schema);
    $db->close();
    echo json_encode(['ok' => true, 'stats' => $stats], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'list') {
    $q = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : null;
    $lim = isset($_GET['limit']) ? (int)$_GET['limit'] : 400;
    $lim = max(1, min(500, $lim));
    $wallets = admin_wallet_list_rows($db, $schema, $lim, $q);
    $db->close();
    echo json_encode(['ok' => true, 'wallets' => $wallets], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'top') {
    $lim = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    $top = admin_wallet_top_traders($db, $schema, 5);
    $db->close();
    echo json_encode(['ok' => true, 'top' => $top], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'feed') {
    $lim = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
    $feed = admin_wallet_recent_feed($db, $lim);
    $db->close();
    echo json_encode(['ok' => true, 'feed' => $feed], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'history') {
    $uid = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $lim = isset($_GET['limit']) ? (int)$_GET['limit'] : 40;
    $hist = admin_wallet_user_history($db, $uid, $lim);
    $db->close();
    echo json_encode(['ok' => true, 'items' => $hist], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'search') {
    $q = isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '';
    $rows = admin_wallet_search_users($db, $schema, $q, 20);
    $db->close();
    echo json_encode(['ok' => true, 'users' => $rows], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'alerts') {
    $alerts = admin_wallet_security_alerts($db, $schema);
    $db->close();
    echo json_encode(['ok' => true, 'alerts' => $alerts], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'bootstrap') {
    $stats = admin_wallet_aggregate_stats($db, $schema);
    $wallets = admin_wallet_list_rows($db, $schema, 400, null);
    $top = admin_wallet_top_traders($db, $schema, 5);
    $feed = admin_wallet_recent_feed($db, 12);
    $alerts = admin_wallet_security_alerts($db, $schema);
    $db->close();
    echo json_encode(
      [
        'ok' => true,
        'stats' => $stats,
        'wallets' => $wallets,
        'top' => $top,
        'feed' => $feed,
        'alerts' => $alerts,
        'csrf' => admin_csrf_token(),
      ],
      JSON_UNESCAPED_SLASHES
    );
    exit;
  }

  $db->close();
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'action']);
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method']);
  exit;
}

$input = wallet_api_json_body();
$csrf = isset($input['csrf_token']) && is_string($input['csrf_token']) ? $input['csrf_token'] : null;
if (!admin_verify_csrf($csrf)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'csrf']);
  exit;
}

$action = isset($input['action']) && is_string($input['action']) ? $input['action'] : '';

try {
  $db = admin_wallet_db();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db']);
  exit;
}

try {
  if ($action === 'credit' || $action === 'debit') {
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $amount = isset($input['amount']) ? (float)$input['amount'] : 0.0;
    $reason = isset($input['reason']) && is_string($input['reason']) ? $input['reason'] : '';
    $dir = $action === 'credit' ? 'credit' : 'debit';
    $r = admin_wallet_apply_balance_adjustment($db, $schema, $userId, $dir, $amount, $reason, $aid);
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    $r['ok'] = (bool)($r['ok'] ?? false);
    $r['csrf'] = admin_csrf_token();
    echo json_encode($r, JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'fund') {
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $amount = isset($input['amount']) ? (float)$input['amount'] : 0.0;
    $asset = isset($input['asset']) && is_string($input['asset']) ? $input['asset'] : 'USDT';
    $network = isset($input['network']) && is_string($input['network']) ? $input['network'] : '';
    $reference = isset($input['reference']) && is_string($input['reference']) ? $input['reference'] : '';
    $description = isset($input['description']) && is_string($input['description']) ? $input['description'] : '';
    $r = admin_wallet_manual_fund($db, $schema, $userId, $amount, $asset, $network, $reference, $description, $aid);
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    $r['ok'] = (bool)($r['ok'] ?? false);
    $r['csrf'] = admin_csrf_token();
    echo json_encode($r, JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'lock' || $action === 'unlock') {
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $reason = isset($input['reason']) && is_string($input['reason']) ? $input['reason'] : '';
    $frozen = $action === 'lock';
    $r = admin_wallet_set_frozen($db, $schema, $userId, $frozen, $reason, $aid);
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    $r['ok'] = (bool)($r['ok'] ?? false);
    $r['csrf'] = admin_csrf_token();
    echo json_encode($r, JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'adjust') {
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $amount = isset($input['amount']) ? (float)$input['amount'] : 0.0;
    $reason = isset($input['reason']) && is_string($input['reason']) ? $input['reason'] : '';
    $mode = isset($input['mode']) && is_string($input['mode']) ? strtolower($input['mode']) : 'credit';
    $dir = $mode === 'debit' ? 'debit' : 'credit';
    $r = admin_wallet_apply_balance_adjustment($db, $schema, $userId, $dir, $amount, $reason, $aid);
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    $r['ok'] = (bool)($r['ok'] ?? false);
    $r['csrf'] = admin_csrf_token();
    echo json_encode($r, JSON_UNESCAPED_SLASHES);
    exit;
  }

  $db->close();
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'action']);
} catch (Throwable $e) {
  if (isset($db) && $db instanceof mysqli) {
    @$db->close();
  }
  error_log('[wallet_control_api] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'exception']);
}
