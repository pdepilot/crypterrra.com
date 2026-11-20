<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_csrf.php';
require_once dirname(__DIR__, 2) . '/includes/market_control_data.php';
require_once dirname(__DIR__) . '/includes/admin_wallet_data.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** @var string $DB_NAME */
global $DB_NAME;
$schema = (string)$DB_NAME;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function market_api_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!is_string($raw) || $raw === '') {
    return [];
  }
  $d = json_decode($raw, true);
  return is_array($d) ? $d : [];
}

if ($method === 'GET') {
  $action = isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : 'bootstrap';
  try {
    $db = admin_wallet_db();
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db']);
    exit;
  }

  market_control_ensure_schema($db);

  if ($action === 'pairs') {
    $rows = market_control_list_pairs($db);
    $db->close();
    echo json_encode(['ok' => true, 'pairs' => $rows, 'csrf' => admin_csrf_token()], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'settings') {
    $s = market_control_get_settings($db);
    $db->close();
    echo json_encode(['ok' => true, 'settings' => $s, 'csrf' => admin_csrf_token()], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'open_orders') {
    $lim = isset($_GET['limit']) ? (int)$_GET['limit'] : 40;
    $items = market_control_fetch_open_orders($db, $lim);
    $db->close();
    echo json_encode(['ok' => true, 'open_orders' => $items, 'csrf' => admin_csrf_token()], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'bootstrap') {
    $pairs = market_control_list_pairs($db);
    $stats = market_control_stats($db);
    $settings = market_control_get_settings($db);
    $alerts = market_control_compute_alert_cards($pairs, $settings);
    $feed = market_control_fetch_feed($db, 30);
    $openOrders = market_control_fetch_open_orders($db, 40);
    if ($feed === []) {
      market_control_log_event(
        $db,
        'info',
        'Market activity feed connected',
        'Admin actions and ticks will appear here in real time.'
      );
      $feed = market_control_fetch_feed($db, 30);
    }
    $db->close();
    echo json_encode(
      [
        'ok' => true,
        'pairs' => $pairs,
        'stats' => $stats,
        'settings' => $settings,
        'alerts' => $alerts,
        'feed' => $feed,
        'open_orders' => $openOrders,
        'server_time' => gmdate('c'),
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

$input = market_api_json_body();
$csrf = isset($input['csrf_token']) && is_string($input['csrf_token']) ? $input['csrf_token'] : null;
if (!admin_verify_csrf($csrf)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'csrf']);
  exit;
}

$action = isset($input['action']) && is_string($input['action']) ? $input['action'] : '';
$aid = (int)($GLOBALS['ADMIN_SESSION_ID'] ?? 0);

try {
  $db = admin_wallet_db();
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db']);
  exit;
}

market_control_ensure_schema($db);

try {
  if ($action === 'fund_trade' || $action === 'debit_trade') {
    $userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
    $amount = isset($input['amount']) ? (float)$input['amount'] : 0.0;
    $reason = isset($input['reason']) && is_string($input['reason']) ? $input['reason'] : '';
    $dir = $action === 'fund_trade' ? 'credit' : 'debit';
    $r = admin_trade_balance_adjustment($db, $schema, $userId, $dir, $amount, $reason, $aid);
    if (($r['ok'] ?? false)) {
      $em = '';
      $fn = '';
      $st = $db->prepare('SELECT email, full_name FROM users WHERE id = ? LIMIT 1');
      if ($st) {
        $st->bind_param('i', $userId);
        $st->execute();
        $st->bind_result($em, $fn);
        $st->fetch();
        $st->close();
      }
      $who = trim($fn . ' ' . $em);
      if ($who === '') {
        $who = 'User #' . $userId;
      }
      $verb = $dir === 'credit' ? 'credited' : 'debited';
      market_control_log_event(
        $db,
        'settings',
        'Trading balance ' . $verb,
        '$' . number_format($amount, 2) . ' · ' . $who . ($reason !== '' ? ' — ' . $reason : '')
      );
    }
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    $r['ok'] = (bool)($r['ok'] ?? false);
    $r['csrf'] = admin_csrf_token();
    echo json_encode($r, JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'pair_create') {
    $base = isset($input['base']) && is_string($input['base']) ? $input['base'] : '';
    $quote = isset($input['quote']) && is_string($input['quote']) ? $input['quote'] : 'USDT';
    $price = isset($input['price_usd']) ? (float)$input['price_usd'] : 0.0;
    $fee = isset($input['fee_pct']) ? (is_null($input['fee_pct']) ? null : (float)$input['fee_pct']) : null;
    $r = market_control_create_pair($db, $base, $quote, $price, $fee);
    if (($r['ok'] ?? false)) {
      market_control_log_event(
        $db,
        'add',
        'Pair created',
        strtoupper($base) . '/' . strtoupper($quote) . ' @ $' . number_format($price, 2)
      );
    }
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    echo json_encode(array_merge($r, ['csrf' => admin_csrf_token()]), JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'pair_update_price') {
    $symbol = isset($input['symbol']) && is_string($input['symbol']) ? $input['symbol'] : '';
    $price = isset($input['price_usd']) ? (float)$input['price_usd'] : 0.0;
    $r = market_control_update_pair_price($db, $symbol, $price);
    if (($r['ok'] ?? false)) {
      market_control_log_event(
        $db,
        'price',
        'Manual price set',
        market_control_normalize_symbol($symbol) . ' → $' . number_format($price, 2)
      );
    }
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    echo json_encode(array_merge($r, ['csrf' => admin_csrf_token()]), JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'pair_apply_percent') {
    $symbol = isset($input['symbol']) && is_string($input['symbol']) ? $input['symbol'] : '';
    $pct = isset($input['percent']) ? (float)$input['percent'] : 0.0;
    $r = market_control_apply_price_percent($db, $symbol, $pct);
    if (($r['ok'] ?? false)) {
      market_control_log_event(
        $db,
        'price',
        'Price % adjustment',
        market_control_normalize_symbol($symbol) . ' ' . sprintf('%+.2f%%', $pct)
      );
    }
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    echo json_encode(array_merge($r, ['csrf' => admin_csrf_token()]), JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'pair_set_active') {
    $symbol = isset($input['symbol']) && is_string($input['symbol']) ? $input['symbol'] : '';
    $active = !empty($input['active']);
    $r = market_control_set_pair_active($db, $symbol, $active);
    if (($r['ok'] ?? false)) {
      market_control_log_event(
        $db,
        'pause',
        $active ? 'Pair enabled' : 'Pair paused',
        market_control_normalize_symbol($symbol)
      );
    }
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    echo json_encode(array_merge($r, ['csrf' => admin_csrf_token()]), JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'pair_delete') {
    $symbol = isset($input['symbol']) && is_string($input['symbol']) ? $input['symbol'] : '';
    $r = market_control_delete_pair($db, $symbol);
    if (($r['ok'] ?? false)) {
      market_control_log_event($db, 'pause', 'Pair removed', market_control_normalize_symbol($symbol));
    }
    $db->close();
    if (!($r['ok'] ?? false)) {
      http_response_code(400);
    }
    echo json_encode(array_merge($r, ['csrf' => admin_csrf_token()]), JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'settings_save') {
    $patch = [];
    if (array_key_exists('trading_enabled', $input)) {
      $patch['trading_enabled'] = (bool)$input['trading_enabled'];
    }
    if (array_key_exists('global_fee_pct', $input)) {
      $patch['global_fee_pct'] = (float)$input['global_fee_pct'];
    }
    if (array_key_exists('auto_fluctuation_enabled', $input)) {
      $patch['auto_fluctuation_enabled'] = (bool)$input['auto_fluctuation_enabled'];
    }
    if (array_key_exists('volatility', $input) && is_string($input['volatility'])) {
      $patch['volatility'] = $input['volatility'];
    }
    if (array_key_exists('fluctuation_interval_sec', $input)) {
      $patch['fluctuation_interval_sec'] = (int)$input['fluctuation_interval_sec'];
    }
    market_control_save_settings($db, $patch);
    $parts = [];
    if (array_key_exists('trading_enabled', $patch)) {
      $parts[] = ($patch['trading_enabled'] ? 'Trading ON' : 'Trading OFF');
    }
    if (array_key_exists('global_fee_pct', $patch)) {
      $parts[] = 'Global fee ' . number_format((float)$patch['global_fee_pct'], 4) . '%';
    }
    if (array_key_exists('auto_fluctuation_enabled', $patch)) {
      $parts[] = ($patch['auto_fluctuation_enabled'] ? 'Auto fluctuation on' : 'Auto fluctuation off');
    }
    if (array_key_exists('volatility', $patch)) {
      $parts[] = 'Vol ' . (string)$patch['volatility'];
    }
    if (array_key_exists('fluctuation_interval_sec', $patch)) {
      $parts[] = 'Tick every ' . (int)$patch['fluctuation_interval_sec'] . 's';
    }
    if ($parts !== []) {
      market_control_log_event($db, 'settings', 'Settings updated', implode(' · ', $parts));
    }
    $db->close();
    echo json_encode(['ok' => true, 'csrf' => admin_csrf_token()], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'tick_random') {
    market_control_manual_random_tick($db);
    $db->close();
    echo json_encode(['ok' => true, 'csrf' => admin_csrf_token()], JSON_UNESCAPED_SLASHES);
    exit;
  }

  $db->close();
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'action']);
} catch (Throwable $e) {
  if (isset($db) && $db instanceof mysqli) {
    @$db->close();
  }
  error_log('[market_control_api] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'exception']);
}
