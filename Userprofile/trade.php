<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_sidebar_state.php';
$kycSidebarBadge = kyc_sidebar_badge_for_session();

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userFullName = (string)($_SESSION['user_full_name'] ?? '');

$spotBalanceUsd = '0.00';
$futuresBalanceUsd = '0.00';
$dailyPlUsd = '0.00';
$totalPlUsd = '0.00';
$openPositions = 0;

try {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $schema = (string)$DB_NAME;
  ensure_user_dashboard_schema($db, $schema);
  $row = user_fetch_by_id($db, $userId);
  if (is_array($row)) {
    $userFullName = (string)$row['full_name'];
    $_SESSION['user_full_name'] = $userFullName;
  }

  $spotBalanceUsd = user_get_trade_balance_usd($db, $schema, $userId);
  try {
    $p = user_fetch_portfolio($db, $userId);
    if (is_array($p)) {
      $spotBalanceUsd = (string)$p['spot_balance_usd'];
      $futuresBalanceUsd = (string)$p['futures_balance_usd'];
      $dailyPlUsd = (string)$p['daily_pl_usd'];
      $totalPlUsd = (string)$p['total_pl_usd'];
      $openPositions = (int)$p['open_positions'];
    }
  } catch (Throwable $e) {
    error_log('[trade-portfolio] ' . $e->getMessage());
  }
  $db->close();
} catch (Throwable $e) {
  error_log('[trade] ' . $e->getMessage());
}

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'execute_trade') {
  header('Content-Type: application/json; charset=utf-8');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method.']);
    exit();
  }

  $csrf = $_POST['csrf_token'] ?? null;
  if (!is_string($csrf) || !user_verify_csrf($csrf)) {
    echo json_encode(['ok' => false, 'error' => 'Security verification failed.']);
    exit();
  }

  $side = isset($_POST['side']) && is_string($_POST['side']) ? $_POST['side'] : '';
  $asset = isset($_POST['asset']) && is_string($_POST['asset']) ? $_POST['asset'] : '';
  $network = isset($_POST['network']) && is_string($_POST['network']) ? $_POST['network'] : '';
  $amountUsd = isset($_POST['amount_usd']) ? (float)$_POST['amount_usd'] : 0.0;
  $catalogAssetId = isset($_POST['catalog_asset_id']) && is_string($_POST['catalog_asset_id']) ? $_POST['catalog_asset_id'] : null;

  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    // Place an open (pending) order first; it can be cancelled before it fills.
    $result = user_place_trade_order($db, $DB_NAME, $userId, $side, $asset, $network, $amountUsd, $catalogAssetId);
    if (!($result['ok'] ?? false)) {
      $db->close();
      echo json_encode($result, JSON_UNESCAPED_SLASHES);
      exit();
    }
    // Buys stay pending until filled later; sells execute immediately against trade positions/balance only.
    if (strtolower(trim($side)) === 'sell' && isset($result['order_id'])) {
      $fillResult = user_fill_trade_order($db, $DB_NAME, $userId, (int)$result['order_id']);
      if (!($fillResult['ok'] ?? false)) {
        user_cancel_trade_order($db, $userId, (int)$result['order_id']);
        $db->close();
        echo json_encode(
          [
            'ok' => false,
            'error' => (string)($fillResult['error'] ?? 'Sell could not be completed.'),
            'code' => $fillResult['code'] ?? null,
          ],
          JSON_UNESCAPED_SLASHES
        );
        exit();
      }
      $db->close();
      echo json_encode(array_merge($result, $fillResult), JSON_UNESCAPED_SLASHES);
      exit();
    }
    $db->close();
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
    exit();
  } catch (Throwable $e) {
    error_log('[trade-exec] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Trade failed. Please try again.']);
    exit();
  }
}

/** Live network fee (USD) for selected wallet asset + network — same rules as Send/Withdraw. */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'trade_network_fee') {
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  if ($userId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'auth'], JSON_UNESCAPED_SLASHES);
    exit();
  }
  $catalogId = isset($_GET['catalog_asset_id']) && is_string($_GET['catalog_asset_id']) ? strtolower(trim($_GET['catalog_asset_id'])) : '';
  $network = isset($_GET['network']) && is_string($_GET['network']) ? $_GET['network'] : '';
  if ($catalogId === '' || trim($network) === '') {
    echo json_encode(['ok' => false, 'error' => 'input'], JSON_UNESCAPED_SLASHES);
    exit();
  }
  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    ensure_network_fee_rules_schema($db);
    $feeUsd = user_trade_network_fee_usd($db, $userId, $catalogId, $network);
    $q = user_quote_network_fees($db, $userId, $catalogId, $network, 1.0);
    $db->close();
    $gasSym = '';
    $gasAmt = 0.0;
    if ($q['ok'] ?? false) {
      $gasSym = (string)($q['gas_symbol'] ?? '');
      $gasAmt = (float)($q['gas_fee_amount'] ?? 0.0);
    }
    echo json_encode(
      [
        'ok' => true,
        'network_fee_usd' => $feeUsd,
        'gas_symbol' => $gasSym,
        'gas_amount' => $gasAmt,
        'ts' => time(),
      ],
      JSON_UNESCAPED_SLASHES
    );
    exit();
  } catch (Throwable $e) {
    error_log('[trade_network_fee] ' . $e->getMessage());
    if (isset($db) && $db instanceof mysqli) {
      @$db->close();
    }
    echo json_encode(['ok' => false, 'error' => 'server'], JSON_UNESCAPED_SLASHES);
    exit();
  }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'cancel_order') {
  header('Content-Type: application/json; charset=utf-8');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method.']);
    exit();
  }
  $csrf = $_POST['csrf_token'] ?? null;
  if (!is_string($csrf) || !user_verify_csrf($csrf)) {
    echo json_encode(['ok' => false, 'error' => 'Security verification failed.']);
    exit();
  }
  $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $result = user_cancel_trade_order($db, $userId, $orderId);
    $db->close();
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
    exit();
  } catch (Throwable $e) {
    error_log('[trade-cancel] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Cancel failed.']);
    exit();
  }
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'fill_order') {
  header('Content-Type: application/json; charset=utf-8');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method.']);
    exit();
  }
  $csrf = $_POST['csrf_token'] ?? null;
  if (!is_string($csrf) || !user_verify_csrf($csrf)) {
    echo json_encode(['ok' => false, 'error' => 'Security verification failed.']);
    exit();
  }
  $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $result = user_fill_trade_order($db, $DB_NAME, $userId, $orderId);
    $db->close();
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
    exit();
  } catch (Throwable $e) {
    error_log('[trade-fill] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Execution failed.']);
    exit();
  }
}

$isAjaxPortfolio = isset($_GET['ajax']) && $_GET['ajax'] === 'portfolio';
if ($isAjaxPortfolio) {
  header('Content-Type: application/json; charset=utf-8');
  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $schema = (string)$DB_NAME;
    ensure_user_dashboard_schema($db, $schema);
    ensure_positions_table($db);
    $p = null;
    try {
      $p = user_fetch_portfolio($db, $userId);
    } catch (Throwable $e) {
      error_log('[trade-portfolio-ajax] ' . $e->getMessage());
    }
    if (!is_array($p)) {
      $p = [];
    }
    if (!isset($p['spot_balance_usd'])) {
      $p['spot_balance_usd'] = user_get_trade_balance_usd($db, $schema, $userId);
    }
    if (!isset($p['futures_balance_usd'])) {
      $p['futures_balance_usd'] = '0.00';
    }
    if (!isset($p['daily_pl_usd'])) {
      $p['daily_pl_usd'] = '0.00';
    }
    if (!isset($p['total_pl_usd'])) {
      $p['total_pl_usd'] = '0.00';
    }
    if (!isset($p['open_positions'])) {
      $p['open_positions'] = 0;
    }
    $positions = [];
    try {
      $positions = user_fetch_positions($db, $userId);
    } catch (Throwable $e) {
      error_log('[trade-positions] ' . $e->getMessage());
    }
    $db->close();
    echo json_encode(
      ['ok' => true, 'portfolio' => $p, 'positions' => $positions, 'ts' => time()],
      JSON_UNESCAPED_SLASHES
    );
    exit();
  } catch (Throwable $e) {
    error_log('[trade-portfolio] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'portfolio' => [], 'ts' => time()], JSON_UNESCAPED_SLASHES);
    exit();
  }
}

$isAjaxOrders = isset($_GET['ajax']) && $_GET['ajax'] === 'orders';
if ($isAjaxOrders) {
  header('Content-Type: application/json; charset=utf-8');
  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    ensure_transactions_table($db);
    $orders = user_fetch_trade_orders($db, $userId, 80);
    $db->close();
    echo json_encode(['ok' => true, 'orders' => $orders, 'ts' => time()], JSON_UNESCAPED_SLASHES);
    exit();
  } catch (Throwable $e) {
    error_log('[trade-orders] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'orders' => [], 'ts' => time()], JSON_UNESCAPED_SLASHES);
    exit();
  }
}

// Public market snapshot: same DB tables as Admin/admin-market-control.php (market_pairs, market_settings).
if (isset($_GET['ajax']) && $_GET['ajax'] === 'market_config') {
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  try {
    require_once dirname(__DIR__) . '/includes/market_control_data.php';
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    market_control_ensure_schema($db);
    $snap = market_control_public_snapshot($db);
    $db->close();
    echo json_encode(
      array_merge(['ok' => true, 'ts' => time()], $snap),
      JSON_UNESCAPED_SLASHES
    );
    exit();
  } catch (Throwable $e) {
    error_log('[trade-market-config] ' . $e->getMessage());
    echo json_encode(
      [
        'ok' => false,
        'pairs' => [],
        'trading_enabled' => true,
        'global_fee_pct' => 0.1,
        'ts' => time(),
      ],
      JSON_UNESCAPED_SLASHES
    );
    exit();
  }
}

/** Same-origin proxy for Binance spot klines (public API; avoids browser CORS quirks). */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'binance_klines') {
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  $symbol = isset($_GET['symbol']) ? (string)preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['symbol'] ?? '')) : '';
  $symbol = strtoupper($symbol);
  $interval = isset($_GET['interval']) ? trim((string)($_GET['interval'] ?? '')) : '';
  $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 250;
  $limit = max(1, min(1000, $limit));
  $allowedIntervals = ['1m', '3m', '5m', '15m', '30m', '1h', '2h', '4h', '6h', '8h', '12h', '1d', '3d', '1w', '1M'];
  if ($symbol === '' || strlen($symbol) < 4 || !in_array($interval, $allowedIntervals, true)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_parameters'], JSON_UNESCAPED_SLASHES);
    exit();
  }
  $url = 'https://api.binance.com/api/v3/klines?symbol=' . rawurlencode($symbol)
    . '&interval=' . rawurlencode($interval)
    . '&limit=' . $limit;
  $raw = null;
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    if ($ch !== false) {
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 18,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
      ]);
      $raw = curl_exec($ch);
      $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($raw === false || $http !== 200) {
        $raw = null;
      }
    }
  }
  if ($raw === null || $raw === '') {
    $ctx = stream_context_create([
      'http' => [
        'timeout' => 15,
        'ignore_errors' => true,
      ],
      'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
      ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
  }
  if ($raw === false || $raw === '') {
    echo json_encode(['ok' => false, 'error' => 'upstream_unavailable'], JSON_UNESCAPED_SLASHES);
    exit();
  }
  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_upstream'], JSON_UNESCAPED_SLASHES);
    exit();
  }
  if (isset($decoded['code'])) {
    echo json_encode(['ok' => false, 'error' => 'binance', 'detail' => $decoded], JSON_UNESCAPED_SLASHES);
    exit();
  }
  echo json_encode(['ok' => true, 'klines' => $decoded], JSON_UNESCAPED_SLASHES);
  exit();
}

$csrfToken = user_csrf_token();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover"
    />
    <title>Crypterra | Institutional Trading Terminal</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    />
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.1.0/dist/lightweight-charts.standalone.production.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family:
          "Inter",
          system-ui,
          -apple-system,
          sans-serif;
      }
      @import url("https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap");

      :root {
        --primary-color: #22c55e;
        --primary-light: #4ade80;
        --primary-dark: #16a34a;
        --primary-glow: rgba(34, 197, 94, 0.5);
        --primary-soft: rgba(34, 197, 94, 0.15);
        --bg: #0a1525;
        --bg-secondary: #07101f;
        --surface: #0f1e30;
        --surface-light: #1a2a40;
        --text: #f0f7ff;
        --text-secondary: #b0c8e0;
        --text-muted: #7f9bc0;
        --glass-bg: rgba(20, 40, 60, 0.4);
        --glass-edge: rgba(34, 197, 94, 0.25);
        --card-bg: rgba(15, 30, 45, 0.6);
        --positive: #26a69a;
        --negative: #ef5350;
        --price-change-up: #22c55e;
        --price-change-down: #f6465d;
        --shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        --shadow-glow: 0 0 30px var(--primary-glow);
        --border: #2d2d2d;
        --warning: #ffa726;
      }
      body.light {
        --bg: #f5f7fb;
        --bg-secondary: #eef2f6;
        --surface: #ffffff;
        --surface-light: #f0f4fa;
        --text: #111827;
        --text-secondary: #374151;
        --text-muted: #6b7280;
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-edge: rgba(34, 197, 94, 0.3);
        --card-bg: #ffffff;
        --border: #eaeaea;
        --price-change-up: #16a34a;
        --price-change-down: #dc2626;
      }
      body {
        background: var(--bg);
        color: var(--text);
        transition: all 0.3s;
      }
      .dashboard {
        display: flex;
        min-height: 100vh;
      }

      /* Sidebar */
      .sidebar {
        width: 280px;
        background: var(--bg-secondary);
        border-right: 1px solid var(--border);
        transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 20;
      }
      .sidebar.collapsed {
        width: 80px;
      }
      .sidebar.collapsed .logo-wrapper h3,
      .sidebar.collapsed .user-info,
      .sidebar.collapsed .sidebar-link span:not(.action-badge),
      .sidebar.collapsed .nav-section {
        display: none;
      }
      .sidebar.collapsed .sidebar-link {
        justify-content: center;
        padding: 12px;
      }
      .sidebar.collapsed .sidebar-link i {
        margin-right: 0;
      }
      .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px 20px;
        border-bottom: 1px solid var(--border);
      }
      .logo-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
      }
      .logo-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        box-shadow: 0 4px 10px var(--primary-glow);
      }
      .collapse-btn {
        cursor: pointer;
        background: var(--surface);
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s;
        color: var(--text-secondary);
      }
      .sidebar-user {
        padding: 24px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        border-bottom: 1px solid var(--border);
      }
      .user-avatar-large {
        width: 52px;
        height: 52px;
        background: linear-gradient(
          145deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border-radius: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 24px;
      }
      .user-info h4 {
        font-size: 1rem;
        font-weight: 600;
      }
      .user-info p {
        font-size: 0.75rem;
        color: var(--text-muted);
      }
      .sidebar-nav {
        padding: 20px 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
      }
      .nav-section {
        font-size: 0.7rem;
        letter-spacing: 1px;
        font-weight: 600;
        color: var(--text-muted);
        margin: 16px 0 8px 12px;
      }
      .sidebar-link {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 12px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: 0.2s;
      }
      .sidebar-link i {
        width: 22px;
        font-size: 1.1rem;
      }
      .sidebar-link:hover {
        background: var(--primary-soft);
        color: var(--primary-color);
      }
      .sidebar-link.active {
        background: var(--primary-soft);
        color: var(--primary-color);
        border-left: 3px solid var(--primary-color);
      }
      .action-badge {
        background: var(--primary-color);
        font-size: 0.6rem;
        padding: 2px 8px;
        border-radius: 20px;
        margin-left: auto;
        color: white;
      }

      /* Top Bar */
      .main-content {
        flex: 1;
        min-width: 0;
      }
      .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 28px;
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--glass-edge);
        position: sticky;
        top: 0;
        z-index: 15;
      }
      .header-left {
        display: flex;
        align-items: center;
        gap: 20px;
      }
      .mobile-menu-btn {
        display: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text);
      }
      .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: inherit;
      }
      .user-avatar {
        width: 40px;
        height: 40px;
        background: var(--primary-soft);
        border-radius: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: var(--primary-color);
      }
      .header-right {
        display: flex;
        gap: 16px;
      }
      .icon-button {
        background: var(--surface);
        width: 40px;
        height: 40px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        border: 1px solid var(--border);
        transition: 0.2s;
        color: var(--text-secondary);
      }
      .icon-button:hover {
        background: var(--primary-soft);
        color: var(--primary-color);
      }
      .theme-toggle {
        width: 40px;
        height: 40px;
        background: var(--surface);
        border-radius: 30px;
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2322c55e'%3E%3Cpath d='M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: center;
        background-size: 20px;
      }
      .dashboard-content {
        padding: 24px 28px;
      }
      @media (max-width: 768px) {
        .dashboard-content {
          padding: 16px;
        }
        .sidebar {
          position: fixed;
          transform: translateX(-100%);
          z-index: 1000;
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
        .mobile-menu-btn {
          display: block;
        }

        #scrollTop {
          right: 1.5rem;
          bottom: 5.4rem;
        }
      }

      /* Live Price Ticker */
      .price-ticker {
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 12px 20px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        justify-content: space-between;
        align-items: center;
        backdrop-filter: blur(8px);
      }
      .ticker-item {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 600;
      }
      .ticker-symbol {
        color: var(--text-muted);
        font-size: 14px;
      }
      .ticker-price {
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
      }
      .ticker-change {
        font-size: 14px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 20px;
      }
      .ticker-change.positive {
        color: var(--price-change-up);
        background: rgba(34, 197, 94, 0.14);
      }
      .ticker-change.negative {
        color: var(--price-change-down);
        background: rgba(246, 70, 93, 0.12);
      }
      .ticker-change.neutral {
        color: var(--text-muted);
        background: rgba(127, 155, 192, 0.1);
      }

      /* Balance Hero */
      .balance-hero {
        background: linear-gradient(
          145deg,
          var(--surface),
          var(--bg-secondary)
        );
        border-radius: 32px;
        padding: 24px;
        margin-bottom: 28px;
        border: 1px solid var(--glass-edge);
        backdrop-filter: blur(8px);
      }
      .balance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 20px;
      }
      .balance-card {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 24px;
        padding: 18px;
        transition: all 0.2s;
        border: 1px solid transparent;
      }
      .balance-card:hover {
        border-color: var(--primary-light);
        transform: translateY(-2px);
        background: var(--primary-soft);
      }
      .balance-value {
        font-size: 28px;
        font-weight: 700;
        background: linear-gradient(135deg, var(--text), var(--primary-light));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      /* Chart Card */
      .chart-card {
        background: var(--card-bg);
        backdrop-filter: blur(4px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 20px;
        margin-bottom: 24px;
        transition: all 0.3s;
      }
      .chart-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--glass-edge);
      }
      .tool-btn {
        background: var(--surface-light);
        border: none;
        padding: 8px 14px;
        border-radius: 12px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: 0.2s;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
      }
      .tool-btn:hover {
        background: var(--primary-color);
        color: white;
      }
      .pair-selector,
      .timeframe-selector {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
      }
      .pair-btn,
      .tf-btn {
        background: var(--surface-light);
        border: none;
        padding: 8px 18px;
        border-radius: 40px;
        color: var(--text-secondary);
        font-weight: 500;
        cursor: pointer;
        transition: 0.2s;
      }
      .pair-btn.active,
      .tf-btn.active,
      .pair-btn:hover,
      .tf-btn:hover {
        background: var(--primary-color);
        color: white;
        box-shadow: 0 0 12px var(--primary-glow);
      }
      #chart-container {
        width: 100%;
        height: 480px;
        position: relative;
        background: var(--surface-light);
        border-radius: 16px;
        overflow: hidden;
      }
      .chart-fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 10000;
        background: var(--bg);
        padding: 20px;
        border-radius: 0;
        margin: 0;
      }
      .chart-fullscreen #chart-container {
        height: calc(100vh - 140px);
      }
      .live-price-label {
        font-size: 24px;
        font-weight: 700;
        color: var(--primary-color);
        margin-top: 12px;
      }

      /* Trading Grid */
      .trading-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 24px;
        margin-bottom: 28px;
      }
      @media (max-width: 1024px) {
        .trading-grid {
          grid-template-columns: 1fr;
        }

        #scrollTop {
          right: 1.5rem;
          bottom: 5.4rem;
        }
      }

      /* Trade Panel */
      .trade-panel {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 24px;
      }
      .trade-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
      }
      .trade-tab {
        cursor: pointer;
        padding: 8px 24px;
        border-radius: 40px;
        font-weight: 600;
        background: var(--surface-light);
        color: var(--text-secondary);
        transition: 0.2s;
      }
      .trade-tab.active {
        background: var(--primary-color);
        color: white;
      }
      .input-field,
      .input-select {
        width: 100%;
        background: var(--surface-light);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 12px 16px;
        color: var(--text);
        margin-bottom: 16px;
        outline: none;
      }
      .perc-buttons {
        display: flex;
        gap: 12px;
        margin: 12px 0;
      }
      .perc-btn {
        flex: 1;
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 8px;
        cursor: pointer;
      }
      .fee-display {
        background: var(--primary-soft);
        padding: 12px;
        border-radius: 16px;
        margin: 12px 0;
        font-size: 13px;
      }
      /* Same pattern as Userprofile/send.php — network / fee requirement */
      .receive-network-warning {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 16px;
        padding: 14px 16px;
        border-radius: 14px;
        border: 1px solid #f87171;
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        color: #991b1b;
        font-size: 0.9rem;
        line-height: 1.45;
        box-shadow: 0 2px 12px rgba(220, 38, 38, 0.18);
      }
      .receive-network-warning i {
        flex-shrink: 0;
        margin-top: 2px;
        color: #dc2626;
        font-size: 1.1rem;
      }
      .receive-network-warning span {
        font-weight: 500;
      }
      .action-btn {
        width: 100%;
        padding: 14px;
        border-radius: 40px;
        font-weight: 700;
        color: white;
        border: none;
        cursor: pointer;
        margin-top: 12px;
      }
      .action-buy {
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
      }
      .action-sell {
        background: linear-gradient(135deg, #ef4444, #dc2626);
      }
      .quick-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
      }
      .glass-btn {
        flex: 1;
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        padding: 12px;
        border-radius: 28px;
        text-align: center;
        cursor: pointer;
        transition: 0.2s;
        text-decoration: none;
        color: var(--text-secondary);
        display: inline-block;
      }
      .glass-btn:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
      }

      /* Gainers/Losers */
      .movers-section {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 20px;
        margin-bottom: 24px;
      }
      .movers-container {
        display: flex;
        gap: 24px;
        margin-top: 16px;
      }
      .movers-column {
        flex: 1;
        background: var(--surface-light);
        border-radius: 24px;
        padding: 16px;
      }
      .mover-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--glass-edge);
      }

      /* Market Feed */
      .market-feed-section {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 20px;
        margin-bottom: 24px;
      }
      .feed-list {
        max-height: 200px;
        overflow-y: auto;
      }
      .feed-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid var(--glass-edge);
        font-size: 13px;
        animation: slideIn 0.3s ease;
      }
      @keyframes slideIn {
        from {
          opacity: 0;
          transform: translateX(-20px);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }

      /* Orders Section */
      .orders-section {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 20px;
        overflow-x: auto;
        margin-bottom: 24px;
      }
      .order-filters {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
      }
      .filter-btn {
        background: var(--surface-light);
        border: none;
        padding: 8px 20px;
        border-radius: 40px;
        cursor: pointer;
        color: var(--text-secondary);
        transition: 0.2s;
      }
      .filter-btn.active {
        background: var(--primary-color);
        color: white;
      }
      table {
        width: 100%;
        border-collapse: collapse;
      }
      th,
      td {
        text-align: left;
        padding: 12px 8px;
        border-bottom: 1px solid var(--glass-edge);
      }
      .cancel-order {
        background: none;
        border: none;
        color: var(--negative);
        cursor: pointer;
        font-size: 14px;
      }

      /* Bot Marketplace */
      .bots-section {
        margin: 32px 0;
      }
      .bots-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-top: 20px;
      }
      .bot-card {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 20px;
        transition: all 0.3s;
        cursor: pointer;
      }
      .bot-card:hover {
        transform: translateY(-4px);
        border-color: var(--primary-color);
        box-shadow: var(--shadow-glow);
      }
      .bot-price {
        font-size: 28px;
        font-weight: 700;
        color: var(--primary-color);
        margin: 12px 0;
      }
      .buy-bot-btn {
        background: var(--primary-color);
        border: none;
        padding: 12px;
        border-radius: 40px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
        transition: 0.2s;
      }
      .buy-bot-btn:hover {
        background: var(--primary-dark);
        transform: scale(1.02);
      }

      /* Payment Modal */
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(12px);
        z-index: 10001;
        display: none;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.3s ease;
      }
      @keyframes fadeIn {
        from {
          opacity: 0;
        }
        to {
          opacity: 1;
        }
      }
      .payment-modal {
        background: var(--surface);
        border-radius: 32px;
        padding: 32px;
        max-width: 500px;
        width: 90%;
        border: 1px solid var(--primary-color);
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-glow);
      }
      .qr-code {
        width: 160px;
        height: 160px;
        background: white;
        margin: 16px auto;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
      }
      .countdown {
        font-size: 28px;
        font-weight: 700;
        color: var(--primary-color);
        text-align: center;
        margin: 16px 0;
        font-family: monospace;
      }

      /* Floating Popups */
      .floating-popup {
        position: fixed;
        top: 100px;
        right: 20px;
        background: var(--surface);
        border-left: 4px solid var(--primary-color);
        padding: 12px 20px;
        border-radius: 12px;
        box-shadow: var(--shadow);
        z-index: 1000;
        animation: slideInRight 0.3s ease;
        max-width: 350px;
        backdrop-filter: blur(10px);
      }
      @keyframes slideInRight {
        from {
          transform: translateX(400px);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }

      #scrollTop {
        position: fixed;
        right: 1.5rem;
        bottom: 5.4rem;
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--primary-color);
        color: var(--primary-color);
        width: 50px;
        height: 50px;
        border-radius: 50%;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 99;
      }

      @media (max-width: 768px) {
        .movers-container {
          flex-direction: column;
        }
        .price-ticker {
          flex-direction: column;
          align-items: flex-start;
        }

        #scrollTop {
          right: 1.5rem;
          bottom: 5.4rem;
        }
      }
    </style>
    <link rel="stylesheet" href="includes/user_sidebar_branding.css" />
  </head>
  <body class="dark">
    <div class="dashboard">
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <div class="logo-wrapper">
            <div class="logo-icon"><i class="fa-solid fa-globe"></i></div>
            <h3><span>Crypterra</span></h3>
          </div>
          <div class="collapse-btn" id="collapseSidebar"><i class="fa-solid fa-chevron-left"></i></div>
        </div>
        <div class="sidebar-user">
          <div class="user-avatar-large"><?php echo h($ub['avatar_letter']); ?></div>
          <div class="user-info">
            <h4><?php echo h($ub['full_name']); ?></h4>
            <p><?php echo h($ub['wallet_short']); ?></p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section">MAIN</div>
          <a href="./userprofile.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a>
          <a href="./trade.php" class="sidebar-link active"><i class="fa-solid fa-chart-line"></i> <span>Trade</span></a>
          <a href="./wallet.php" class="sidebar-link"><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a>
          <div class="nav-section">TRADING</div>
          <a href="./send.php" class="sidebar-link"><i class="fa-solid fa-paper-plane"></i> <span>Send</span></a>
          <a href="./receive.php" class="sidebar-link"><i class="fa-solid fa-inbox"></i> <span>Receive</span></a>
          <div class="nav-section">ACCOUNT</div>
          <a href="./kycverification.php" class="sidebar-link"><i class="fa-solid fa-id-card"></i> <span>KYC Verification</span> <?php echo kyc_sidebar_badge_span($kycSidebarBadge); ?></a>
          <a href="./security.php" class="sidebar-link"><i class="fa-solid fa-shield"></i> <span>Security</span></a>
          <a href="./settings.php" class="sidebar-link"><i class="fa-solid fa-gear"></i> <span>Settings</span></a>
          <div class="nav-section">SUPPORT</div>
          <a href="./support.php" class="sidebar-link"><i class="fa-solid fa-headset"></i> <span>Support</span></a>
          <a href="./logout.php" class="sidebar-link"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span></a>
        </nav>
      </aside>
      <main class="main-content">
        <div class="top-bar">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn"><i class="fa-solid fa-bars"></i></div>
          </div>
          <div class="header-right">
            <div class="icon-button" id="notifications-btn"><i class="fa-solid fa-bell"></i></div>
          </div>
        </div>
        <div id="tradingPausedBanner" style="display:none"></div>
        <div id="priceTicker"></div>
        <div id="livePriceLabel"></div>
        <div id="pairSelector"></div>
        <div class="stat-grid">
          <div>Spot <span id="spotBalance"><?php echo h($spotBalanceUsd); ?></span></div>
          <div>Futures <span id="futuresBalance"><?php echo h($futuresBalanceUsd); ?></span></div>
          <div>Daily P/L <span id="dailyPL"><?php echo h($dailyPlUsd); ?></span></div>
          <div>Total P/L <span id="totalPL"><?php echo h($totalPlUsd); ?></span></div>
          <div>Open <span id="openPositions"><?php echo (int)$openPositions; ?></span></div>
        </div>
        <div id="chartCard">
          <button type="button" id="zoomInBtn">+</button>
          <button type="button" id="zoomOutBtn">−</button>
          <button type="button" id="resetChartBtn">Reset</button>
          <button type="button" id="volumeToggle">Volume</button>
          <button type="button" id="fullscreenBtn">Full</button>
          <div id="chart-container" style="height:420px"></div>
        </div>
        <div id="marketFeedList"></div>
        <div>
          <h4>Top Gainers</h4><div id="gainersList"></div>
          <h4>Top Losers</h4><div id="losersList"></div>
        </div>
        <div class="trade-panel">
          <button type="button" class="trade-tab active" data-mode="buy">Buy</button>
          <button type="button" class="trade-tab" data-mode="sell">Sell</button>
          <select id="assetSelect"></select>
          <select id="networkSelect"></select>
          <input type="number" id="amountUSD" min="0" step="0.01" placeholder="Amount USD" />
          <button type="button" class="perc-btn" data-perc="25">25%</button>
          <button type="button" class="perc-btn" data-perc="50">50%</button>
          <button type="button" class="perc-btn" data-perc="100">100%</button>
          <div id="feeDisplay"></div>
          <div id="tradeNetworkBanner" style="display:none"><span id="tradeNetworkBannerText"></span></div>
          <button type="button" id="executeBtn" class="action-btn action-buy"><i class="fas fa-bolt"></i> Execute Buy</button>
        </div>
        <div>
          <button type="button" class="filter-btn active" data-filter="all">All</button>
          <button type="button" class="filter-btn" data-filter="open">Open</button>
          <button type="button" class="filter-btn" data-filter="filled">Filled</button>
          <table><tbody id="ordersTableBody"></tbody></table>
        </div>
        <div id="botsGrid"></div>
      </main>
    </div>
    <div id="paymentModal" style="display:none">
      <div id="modalBotInfo"></div>
      <div id="countdown"></div>
      <input type="text" id="txnHash" placeholder="Transaction hash" />
      <button type="button" id="confirmPayment">Confirm</button>
      <button type="button" id="cancelModal">Cancel</button>
    </div>
    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="includes/user_theme.js"></script>
    <script>

      // Sidebar controls
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      const mobileBtn = document.getElementById("mobileMenuBtn");

      collapseBtn.addEventListener("click", () =>
        sidebar.classList.toggle("collapsed"),
      );
      mobileBtn.addEventListener("click", () =>
        sidebar.classList.toggle("mobile-open"),
      );

      // Close sidebar when clicking outside on mobile
      document.addEventListener("click", (e) => {
        if (
          window.innerWidth <= 768 &&
          !sidebar.contains(e.target) &&
          !mobileBtn.contains(e.target) &&
          sidebar.classList.contains("mobile-open")
        ) {
          sidebar.classList.remove("mobile-open");
        }
      });

      // Scroll to top button
      const scrollBtn = document.getElementById("scrollTop");
      window.addEventListener(
        "scroll",
        () =>
          (scrollBtn.style.display = window.scrollY > 400 ? "flex" : "none"),
      );
      scrollBtn.onclick = () => window.scrollTo({ top: 0, behavior: "smooth" });

      // Notification button (integrated from connect-wallet.php)
      const notifBtn = document.getElementById("notifications-btn");
      notifBtn.addEventListener("click", () => {
        let toast = document.createElement("div");
        toast.className = "floating-popup";
        toast.innerHTML = "🔔 Market Alert: High volatility detected";
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
      });

      // ========== LIVE CHART — Binance REST + WebSocket (Lightweight Charts) ==========
      let chart,
        candlestickSeries,
        volumeSeries,
        currentSymbol = "BTCUSDT",
        currentInterval = "1h",
        ws = null,
        volumeEnabled = true;
      let marketSnapshotPairs = [];
      let tradingGlobalEnabled = true;
      let marketFeeByAsset = {};
      const chartContainer = document.getElementById("chart-container");
      let chartLoadSeq = 0;
      let wsReconnectTimer = null;
      let restPollTimer = null;
      let chartStatusNote = "";
      let walletAssetsForTrade = [];
      let cachedNetworkFeeUsd = 0.5;
      let cachedGasHint = "";

      /** Catalog asset_id → market base (USDT variants map to USDT). */
      function catalogIdToTradeBase(catalogId) {
        const id = String(catalogId || "").toLowerCase();
        const map = {
          btc: "BTC",
          eth: "ETH",
          sol: "SOL",
          bnb: "BNB",
          matic: "MATIC",
          dot: "DOT",
          trx: "TRX",
          xrp: "XRP",
          usdt_erc20: "USDT",
          usdt_trc20: "USDT",
          usdt_bep20: "USDT",
        };
        return map[id] || "";
      }

      const NETWORK_OPTIONS_BY_CATALOG = {
        btc: [{ value: "BTC", label: "Bitcoin (BTC network · gas in BTC)" }],
        eth: [{ value: "ERC20", label: "Ethereum (ERC20 · gas in ETH)" }],
        bnb: [{ value: "BEP20", label: "BEP20 / BSC (gas in BNB)" }],
        sol: [{ value: "SOL", label: "Solana (gas in SOL)" }],
        matic: [{ value: "ERC20", label: "Polygon (ERC20 · gas in MATIC/ETH per rules)" }],
        dot: [{ value: "DOT", label: "Polkadot (DOT network)" }],
        trx: [{ value: "TRC20", label: "TRON (TRC20 · gas in TRX)" }],
        xrp: [{ value: "XRP", label: "XRP Ledger (gas in XRP)" }],
        usdt_erc20: [{ value: "ERC20", label: "ERC20 (gas in ETH)" }],
        usdt_trc20: [{ value: "TRC20", label: "TRC20 (gas in TRX)" }],
        usdt_bep20: [{ value: "BEP20", label: "BEP20 / BSC (gas in BNB)" }],
      };

      function defaultCatalogIdForTradeBase(base) {
        const b = String(base || "").toUpperCase();
        const map = {
          BTC: "btc",
          ETH: "eth",
          SOL: "sol",
          BNB: "bnb",
          XRP: "xrp",
          MATIC: "matic",
          DOT: "dot",
          TRX: "trx",
          USDT: "usdt_bep20",
        };
        return map[b] || b.toLowerCase();
      }

      /** Binance chart symbol; USDT stablecoin uses BTC/USDT as reference (no USDTUSDT pair). */
      function chartSymbolForTradeBase(tb) {
        const b = String(tb || "").toUpperCase();
        if (b === "USDT") return "BTCUSDT";
        return b + "USDT";
      }

      function tradeBaseFromAssetSelect() {
        const sel = document.getElementById("assetSelect");
        const opt = sel && sel.selectedOptions && sel.selectedOptions[0];
        const tb = opt && opt.dataset && opt.dataset.tradeBase;
        return tb ? String(tb) : "";
      }

      function selectAssetOptionByTradeBase(base) {
        const sel = document.getElementById("assetSelect");
        if (!sel) return;
        const u = String(base || "").toUpperCase();
        for (let i = 0; i < sel.options.length; i++) {
          const o = sel.options[i];
          if ((o.dataset.tradeBase || "").toUpperCase() === u) {
            sel.selectedIndex = i;
            return;
          }
        }
      }

      function rebuildNetworkSelectForCatalog(catalogId) {
        const netSel = document.getElementById("networkSelect");
        if (!netSel) return;
        const id = String(catalogId || "").toLowerCase();
        const opts = NETWORK_OPTIONS_BY_CATALOG[id];
        if (opts && opts.length) {
          netSel.innerHTML = opts.map((o) => `<option value="${o.value}">${o.label}</option>`).join("");
        } else {
          netSel.innerHTML = `<option value="BEP20">BEP20 (BSC)</option><option value="ERC20">ERC20 (Ethereum)</option>`;
        }
      }

      async function refreshTradeNetworkFeeUsd() {
        const sel = document.getElementById("assetSelect");
        const net = document.getElementById("networkSelect");
        if (!sel?.value || !net) {
          cachedGasHint = "";
          cachedNetworkFeeUsd = 0.5;
          updateFee();
          return;
        }
        try {
          const u = new URL("./trade.php", window.location.href);
          u.searchParams.set("ajax", "trade_network_fee");
          u.searchParams.set("catalog_asset_id", sel.value);
          u.searchParams.set("network", net.value);
          const res = await fetch(u.toString(), {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
          });
          const data = await res.json().catch(() => null);
          if (data && data.ok && typeof data.network_fee_usd === "number") {
            cachedNetworkFeeUsd = data.network_fee_usd;
            const g = data.gas_symbol || "";
            const ga = Number(data.gas_amount);
            if (g && Number.isFinite(ga) && ga > 0) {
              cachedGasHint = ` · est. from ${ga} ${g} at spot`;
            } else {
              cachedGasHint = "";
            }
          } else {
            cachedNetworkFeeUsd = net.value.includes("ERC20") ? 3.5 : 0.5;
            cachedGasHint = "";
          }
        } catch (_) {
          cachedNetworkFeeUsd = net.value.includes("ERC20") ? 3.5 : 0.5;
          cachedGasHint = "";
        }
        updateFee();
      }

      async function loadWalletAssetsForTrade() {
        try {
          const res = await fetch("./api/assets_api.php", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
          });
          const data = await res.json().catch(() => null);
          walletAssetsForTrade =
            data && data.ok && Array.isArray(data.assets) ? data.assets : [];
        } catch (_) {
          walletAssetsForTrade = [];
        }
      }

      function populateTradeAssetSelect() {
        const sel = document.getElementById("assetSelect");
        if (!sel || !marketSnapshotPairs.length) return;
        const prev = sel.value;
        const bases = new Set(marketSnapshotPairs.map((p) => p.base));
        const rows = [];

        for (const a of walletAssetsForTrade) {
          if (a.is_nft) continue;
          const id = String(a.id || "");
          const tb = catalogIdToTradeBase(id);
          if (!tb || !bases.has(tb)) continue;
          const net = String(a.network || "").trim();
          const netLabel = net ? ` · ${net}` : "";
          const bal = Number(a.balance) || 0;
          const val = Number(a.value_usd) || 0;
          const balStr = bal.toLocaleString(undefined, { maximumFractionDigits: 8 });
          const sym = String(a.symbol || tb).toUpperCase();
          rows.push({
            catalogId: id,
            tradeBase: tb,
            sortKey: val > 0 ? -val : 0,
            label: `${sym}${netLabel} · Bal ${balStr} · ≈ $${val.toFixed(2)}`,
          });
        }

        rows.sort((a, b) => a.sortKey - b.sortKey || a.catalogId.localeCompare(b.catalogId));

        if (rows.length === 0) {
          for (const p of marketSnapshotPairs) {
            const cid = defaultCatalogIdForTradeBase(p.base);
            rows.push({
              catalogId: cid,
              tradeBase: p.base,
              sortKey: 0,
              label: `${p.base} · $${Number(p.price_usd).toLocaleString(undefined, { maximumFractionDigits: 4 })}`,
            });
          }
        }

        const esc = (t) =>
          String(t)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/"/g, "&quot;");
        sel.innerHTML = rows
          .map(
            (r) =>
              `<option value="${esc(r.catalogId)}" data-trade-base="${esc(r.tradeBase)}" data-catalog-id="${esc(r.catalogId)}">${esc(r.label)}</option>`,
          )
          .join("");

        if (prev && [...sel.options].some((o) => o.value === prev)) {
          sel.value = prev;
        } else if (rows[0]) {
          sel.value = rows[0].catalogId;
        }
        rebuildNetworkSelectForCatalog(sel.value);
      }

      function formatPairLabel(symbol) {
        const s = String(symbol || "").toUpperCase();
        const q = s.match(/(USDT|DAI|BUSD|TUSD|FDUSD|USDC|EUR)$/);
        if (q && q.index > 0) {
          return `${s.slice(0, q.index)}/${q[1]}`;
        }
        return s;
      }

      function toBinanceInterval(tf) {
        const t = String(tf ?? "").trim();
        const legacy = {
          "1": "1m",
          "5": "5m",
          "15": "15m",
          "60": "1h",
          "240": "4h",
          "1D": "1d",
          "1440": "1d",
        };
        if (legacy[t]) return legacy[t];
        if (t === "1M") return "1M";
        const lower = t.toLowerCase();
        const map = {
          "1m": "1m",
          "3m": "3m",
          "5m": "5m",
          "15m": "15m",
          "30m": "30m",
          "1h": "1h",
          "2h": "2h",
          "4h": "4h",
          "6h": "6h",
          "8h": "8h",
          "12h": "12h",
          "1d": "1d",
          "3d": "3d",
          "1w": "1w",
        };
        return map[lower] || "1h";
      }

      function clearWsReconnectTimer() {
        if (wsReconnectTimer) {
          clearTimeout(wsReconnectTimer);
          wsReconnectTimer = null;
        }
      }

      function stopRestPoll() {
        if (restPollTimer) {
          clearInterval(restPollTimer);
          restPollTimer = null;
        }
      }

      function closeChartWebSocket() {
        clearWsReconnectTimer();
        if (ws) {
          try {
            ws.onopen = null;
            ws.onmessage = null;
            ws.onerror = null;
            ws.onclose = null;
          } catch (_) {}
          try {
            ws.close();
          } catch (_) {}
          ws = null;
        }
      }

      function getChartPixelHeight() {
        const h = chartContainer ? chartContainer.clientHeight : 480;
        return Math.max(120, h || 480);
      }

      function resizeTradeChart() {
        if (!chart || !chartContainer) return;
        chart.applyOptions({
          width: chartContainer.clientWidth,
          height: getChartPixelHeight(),
        });
      }

      function setLivePriceLabel(priceText, pairSymbol) {
        const el = document.getElementById("livePriceLabel");
        if (!el) return;
        const pair = formatPairLabel(pairSymbol || currentSymbol);
        const note = chartStatusNote ? " · " + chartStatusNote : "";
        el.textContent = `${pair}: ${priceText}${note}`;
      }

      async function refreshMarketSnapshot() {
        try {
          const [res] = await Promise.all([
            fetch("./trade.php?ajax=market_config", {
              credentials: "same-origin",
              headers: { Accept: "application/json" },
            }),
            loadWalletAssetsForTrade(),
          ]);
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) return;
          tradingGlobalEnabled = data.trading_enabled !== false;
          marketSnapshotPairs = Array.isArray(data.pairs) ? data.pairs : [];
          marketFeeByAsset = {};
          marketSnapshotPairs.forEach((p) => {
            marketFeeByAsset[p.base] = p.fee_pct;
          });
          const pairSel = document.getElementById("pairSelector");
          if (pairSel && marketSnapshotPairs.length) {
            pairSel.innerHTML = marketSnapshotPairs
              .map(
                (p, i) =>
                  `<button type="button" class="pair-btn${i === 0 ? " active" : ""}" data-pair="${p.symbol}" data-base="${p.base}">${p.label}</button>`,
              )
              .join("");
            if (
              !marketSnapshotPairs.some((x) => x.symbol === currentSymbol)
            ) {
              currentSymbol = marketSnapshotPairs[0].symbol;
            }
          }
          populateTradeAssetSelect();
          const tb = tradeBaseFromAssetSelect();
          if (tb) {
            currentSymbol = chartSymbolForTradeBase(tb);
            const matchPair = marketSnapshotPairs.find((x) => x.base === tb);
            if (matchPair) {
              document.querySelectorAll(".pair-btn").forEach((b) => {
                b.classList.toggle("active", b.dataset.pair === matchPair.symbol);
              });
            }
          }
          const ban = document.getElementById("tradingPausedBanner");
          if (ban) ban.style.display = tradingGlobalEnabled ? "none" : "block";
          const ex = document.getElementById("executeBtn");
          if (ex) ex.disabled = !tradingGlobalEnabled;
          renderTickerFromSnapshot();
          renderMoversFromSnapshot();
          await refreshTradeNetworkFeeUsd();
        } catch (e) {
          console.warn("market_config", e);
        }
      }

      function renderTickerFromSnapshot() {
        const tickerDiv = document.getElementById("priceTicker");
        if (!tickerDiv) return;
        if (!marketSnapshotPairs.length) {
          tickerDiv.innerHTML =
            '<div class="ticker-item">No pairs configured (admin).</div>';
          return;
        }
        tickerDiv.innerHTML = marketSnapshotPairs
          .map((p) => {
            const ch = Number(p.change_24h_pct);
            const cls =
              ch > 0 ? "positive" : ch < 0 ? "negative" : "neutral";
            const price = Number(p.price_usd);
            const chTxt =
              ch > 0 ? `+${ch.toFixed(2)}%` : ch < 0 ? `${ch.toFixed(2)}%` : "0.00%";
            return `<div class="ticker-item"><span class="ticker-symbol">${p.label}</span><span class="ticker-price">$${price.toLocaleString(undefined, { minimumFractionDigits: 2 })}</span><span class="ticker-change ${cls}">${chTxt}</span></div>`;
          })
          .join("");
      }

      function renderMoversFromSnapshot() {
        const gEl = document.getElementById("gainersList");
        const lEl = document.getElementById("losersList");
        if (!gEl || !lEl || marketSnapshotPairs.length < 2) return;
        const sorted = [...marketSnapshotPairs].sort(
          (a, b) =>
            Number(b.change_24h_pct) - Number(a.change_24h_pct),
        );
        const gainers = sorted.slice(0, 5);
        const losers = sorted.slice(-5).reverse();
        gEl.innerHTML = gainers
          .map(
            (p) =>
              `<div class="mover-item"><span><strong>${p.base}</strong></span><span style="color:var(--price-change-up)">+${Number(p.change_24h_pct).toFixed(2)}%</span><span>$${Number(p.price_usd).toFixed(2)}</span></div>`,
          )
          .join("");
        lEl.innerHTML = losers
          .map(
            (p) =>
              `<div class="mover-item"><span><strong>${p.base}</strong></span><span style="color:var(--price-change-down)">${Number(p.change_24h_pct).toFixed(2)}%</span><span>$${Number(p.price_usd).toFixed(2)}</span></div>`,
          )
          .join("");
      }

      function chartLayoutOptions() {
        const light = document.body.classList.contains("light");
        return {
          background: { type: "solid", color: "transparent" },
          textColor: light ? "#111827" : "#f0f7ff",
        };
      }

      function initChart(binanceInterval) {
        if (chart) chart.remove();
        volumeSeries = null;
        chartContainer.innerHTML =
          '<div id="chart-inner" style="width:100%;height:100%;min-height:100%;position:relative"></div>';
        const inner = document.getElementById("chart-inner");
        const showSeconds =
          binanceInterval === "1m" || binanceInterval === "3m";
        const cw = Math.max(
          chartContainer.clientWidth || chartContainer.offsetWidth || 0,
          320,
        );
        const ch = Math.max(getChartPixelHeight(), 160);
        const gridColor = document.body.classList.contains("light")
          ? "rgba(17,24,39,0.12)"
          : "rgba(34,197,94,0.1)";
        chart = LightweightCharts.createChart(inner, {
          width: cw,
          height: ch,
          layout: chartLayoutOptions(),
          grid: {
            vertLines: { color: gridColor },
            horzLines: { color: gridColor },
          },
          timeScale: {
            timeVisible: true,
            secondsVisible: showSeconds,
          },
          crosshair: { mode: LightweightCharts.CrosshairMode.Normal },
        });
        candlestickSeries = chart.addCandlestickSeries({
          upColor: "#26a69a",
          downColor: "#ef5350",
          borderVisible: false,
          wickUpColor: "#26a69a",
          wickDownColor: "#ef5350",
        });
        candlestickSeries.priceScale().applyOptions({
          scaleMargins: { top: 0.08, bottom: volumeEnabled ? 0.22 : 0.05 },
        });
        if (volumeEnabled) {
          volumeSeries = chart.addHistogramSeries({
            color: "#22c55e",
            priceFormat: { type: "volume" },
            priceScaleId: "",
          });
          volumeSeries.priceScale().applyOptions({
            scaleMargins: { top: 0.85, bottom: 0 },
          });
        }
      }

      function mapBinanceKlineRows(rows) {
        return rows.map((d) => ({
          time: Math.floor(d[0] / 1000),
          open: parseFloat(d[1]),
          high: parseFloat(d[2]),
          low: parseFloat(d[3]),
          close: parseFloat(d[4]),
          volume: parseFloat(d[5]),
        }));
      }

      async function fetchHistoricalKlines(
        symbol,
        binanceInterval,
        limitRows = 500,
      ) {
        const params = new URLSearchParams({
          symbol,
          interval: binanceInterval,
          limit: String(limitRows),
        });
        const rel = `trade.php?ajax=binance_klines&${params.toString()}`;
        let payload = null;
        try {
          const res = await fetch(
            new URL(rel, window.location.href).toString(),
            {
              credentials: "same-origin",
              headers: { Accept: "application/json" },
            },
          );
          payload = await res.json().catch(() => null);
        } catch (e) {
          console.warn("binance_klines fetch", e);
        }
        if (payload && payload.ok && Array.isArray(payload.klines)) {
          return mapBinanceKlineRows(payload.klines);
        }
        try {
          const ext = `https://api.binance.com/api/v3/klines?${params.toString()}`;
          const res2 = await fetch(ext, { method: "GET" });
          const raw = await res2.json().catch(() => null);
          if (Array.isArray(raw)) {
            return mapBinanceKlineRows(raw);
          }
          console.warn("binance direct", raw);
        } catch (e) {
          console.warn("binance direct fetch", e);
        }
        if (payload) console.warn("binance_klines", payload);
        return [];
      }

      async function pollLatestCandle(symbol, binanceInterval, seq) {
        if (seq !== chartLoadSeq || !candlestickSeries) return;
        const rows = await fetchHistoricalKlines(symbol, binanceInterval, 2);
        if (seq !== chartLoadSeq || !rows.length) return;
        const last = rows[rows.length - 1];
        try {
          candlestickSeries.update(last);
          if (volumeSeries) {
            volumeSeries.update({
              time: last.time,
              value: last.volume,
              color: last.close > last.open ? "#26a69a" : "#ef5350",
            });
          }
          chartStatusNote = "Live (REST fallback)";
          setLivePriceLabel(
            "$" +
              last.close.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 6,
              }),
            symbol,
          );
          window.currentPrice = last.close;
        } catch (e) {
          console.warn("pollLatestCandle", e);
        }
      }

      function startRestPoll(symbol, binanceInterval, seq) {
        stopRestPoll();
        pollLatestCandle(symbol, binanceInterval, seq);
        restPollTimer = setInterval(() => {
          pollLatestCandle(symbol, binanceInterval, seq);
        }, 2600);
      }

      function connectWebSocket(symbol, binanceInterval, seq) {
        closeChartWebSocket();
        const stream = `${symbol.toLowerCase()}@kline_${binanceInterval}`;
        const sock = new WebSocket(
          `wss://stream.binance.com:9443/ws/${stream}`,
        );
        ws = sock;

        sock.onopen = () => {
          if (seq !== chartLoadSeq) return;
          chartStatusNote = "";
          stopRestPoll();
          if (
            typeof window.currentPrice === "number" &&
            !Number.isNaN(window.currentPrice)
          ) {
            setLivePriceLabel(
              "$" +
                window.currentPrice.toLocaleString(undefined, {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 6,
                }),
              symbol,
            );
          }
        };

        sock.onmessage = (event) => {
          if (seq !== chartLoadSeq || !candlestickSeries) return;
          let data;
          try {
            data = JSON.parse(event.data);
          } catch (_) {
            return;
          }
          const k = data.k;
          if (!k) return;
          const candle = {
            time: Math.floor(k.t / 1000),
            open: parseFloat(k.o),
            high: parseFloat(k.h),
            low: parseFloat(k.l),
            close: parseFloat(k.c),
            volume: parseFloat(k.v),
          };
          candlestickSeries.update(candle);
          if (volumeSeries) {
            volumeSeries.update({
              time: candle.time,
              value: candle.volume,
              color: candle.close > candle.open ? "#26a69a" : "#ef5350",
            });
          }
          chartStatusNote = "";
          setLivePriceLabel(
            "$" +
              candle.close.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 6,
              }),
            symbol,
          );
          window.currentPrice = candle.close;
        };

        sock.onerror = () => {
          if (seq !== chartLoadSeq) return;
          chartStatusNote = "Reconnecting…";
          if (typeof window.currentPrice === "number") {
            setLivePriceLabel(
              "$" +
                window.currentPrice.toLocaleString(undefined, {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 6,
                }),
              symbol,
            );
          }
          startRestPoll(symbol, binanceInterval, seq);
        };

        sock.onclose = () => {
          if (seq !== chartLoadSeq) return;
          chartStatusNote = "Reconnecting…";
          startRestPoll(symbol, binanceInterval, seq);
          clearWsReconnectTimer();
          wsReconnectTimer = setTimeout(() => {
            if (seq !== chartLoadSeq) return;
            connectWebSocket(symbol, binanceInterval, seq);
          }, 2800);
        };
      }

      async function loadChart(symbol, intervalKey) {
        chartLoadSeq += 1;
        const seq = chartLoadSeq;
        const binanceInterval = toBinanceInterval(intervalKey);
        currentInterval = binanceInterval;

        closeChartWebSocket();
        stopRestPoll();
        chartStatusNote = "";
        setLivePriceLabel("Loading…", symbol);

        try {
          initChart(binanceInterval);
        } catch (e) {
          console.error("initChart", e);
          chartStatusNote = "Chart init failed";
          setLivePriceLabel("—", symbol);
          return;
        }

        await new Promise((r) =>
          requestAnimationFrame(() => requestAnimationFrame(r)),
        );
        resizeTradeChart();

        const candles = await fetchHistoricalKlines(symbol, binanceInterval);
        if (seq !== chartLoadSeq) return;

        if (!candles.length) {
          chartStatusNote = "No data (check pair on Binance)";
          setLivePriceLabel("—", symbol);
          startRestPoll(symbol, binanceInterval, seq);
          connectWebSocket(symbol, binanceInterval, seq);
          return;
        }

        try {
          candlestickSeries.setData(candles);
          if (volumeSeries) {
            volumeSeries.setData(
              candles.map((d) => ({
                time: d.time,
                value: d.volume,
                color: d.close > d.open ? "#26a69a" : "#ef5350",
              })),
            );
          }
          chart.timeScale().fitContent();
          const last = candles[candles.length - 1];
          chartStatusNote = "";
          setLivePriceLabel(
            "$" +
              last.close.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 6,
              }),
            symbol,
          );
          window.currentPrice = last.close;
        } catch (e) {
          console.error("setData", e);
          chartStatusNote = "Chart error";
          setLivePriceLabel("—", symbol);
        }

        if (seq !== chartLoadSeq) return;
        connectWebSocket(symbol, binanceInterval, seq);
      }

      (async function initTradeMarket() {
        await refreshMarketSnapshot();
        await new Promise((r) =>
          requestAnimationFrame(() => requestAnimationFrame(r)),
        );
        if (typeof window.LightweightCharts === "undefined") {
          chartStatusNote = "Chart library blocked or offline";
          setLivePriceLabel("—", currentSymbol);
          console.error("LightweightCharts not loaded (check CDN / ad blocker).");
          return;
        }
        await loadChart(currentSymbol, currentInterval);
      })();
      setInterval(refreshMarketSnapshot, 15000);
      window.addEventListener("resize", () => {
        resizeTradeChart();
      });

      // Chart Controls
      document.getElementById("zoomInBtn").onclick = () => {
        if (chart) chart.timeScale().zoomIn();
      };
      document.getElementById("zoomOutBtn").onclick = () => {
        if (chart) chart.timeScale().zoomOut();
      };
      document.getElementById("resetChartBtn").onclick = () => {
        if (chart) chart.timeScale().fitContent();
      };
      document.getElementById("volumeToggle").onclick = () => {
        volumeEnabled = !volumeEnabled;
        loadChart(currentSymbol, currentInterval);
      };

      // Pair Switching (delegated — buttons load after market_config)
      document.getElementById("pairSelector").addEventListener("click", (e) => {
        const btn = e.target.closest(".pair-btn");
        if (!btn || !btn.dataset.pair) return;
        document
          .querySelectorAll(".pair-btn")
          .forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        const base = btn.dataset.base || String(btn.dataset.pair || "").replace(/USDT$/i, "");
        selectAssetOptionByTradeBase(base);
        const assetSel = document.getElementById("assetSelect");
        if (assetSel) rebuildNetworkSelectForCatalog(assetSel.value);
        currentSymbol = chartSymbolForTradeBase(base);
        loadChart(currentSymbol, currentInterval);
        refreshTradeNetworkFeeUsd();
      });

      document.getElementById("assetSelect").addEventListener("change", () => {
        const sel = document.getElementById("assetSelect");
        rebuildNetworkSelectForCatalog(sel.value);
        const tb = tradeBaseFromAssetSelect();
        const p = marketSnapshotPairs.find((x) => x.base === tb);
        if (p) {
          currentSymbol = chartSymbolForTradeBase(tb);
          document.querySelectorAll(".pair-btn").forEach((b) => {
            b.classList.toggle("active", b.dataset.pair === p.symbol);
          });
          loadChart(currentSymbol, currentInterval);
        }
        refreshTradeNetworkFeeUsd();
      });

      // Timeframe Switching
      document.querySelectorAll(".tf-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
          document
            .querySelectorAll(".tf-btn")
            .forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");
          currentInterval = toBinanceInterval(btn.dataset.tf);
          loadChart(currentSymbol, currentInterval);
        });
      });

      // Fullscreen
      const chartCard = document.getElementById("chartCard");
      document.getElementById("fullscreenBtn").onclick = () => {
        chartCard.classList.toggle("chart-fullscreen");
        setTimeout(() => resizeTradeChart(), 50);
        setTimeout(() => resizeTradeChart(), 300);
      };
      document.addEventListener("keydown", (e) => {
        if (
          e.key === "Escape" &&
          chartCard.classList.contains("chart-fullscreen")
        ) {
          chartCard.classList.remove("chart-fullscreen");
          setTimeout(() => resizeTradeChart(), 50);
        }
      });
      document.addEventListener("fullscreenchange", () => {
        setTimeout(() => resizeTradeChart(), 50);
      });

      // Ticker + movers: driven by admin market_config (refreshMarketSnapshot)

      // Market Feed
      const feedList = document.getElementById("marketFeedList");
      const activities = [
        {
          user: "0x3A2...Fc9",
          action: "Bought",
          amount: "15,230",
          asset: "USDT",
        },
        { user: "Whale_47", action: "Sold", amount: "8.5", asset: "BTC" },
        {
          user: "CryptoKing",
          action: "Deposited",
          amount: "50,000",
          asset: "USDT",
        },
        { user: "TraderJoe", action: "Withdrew", amount: "12.4", asset: "ETH" },
        { user: "0x7F3...aB8d", action: "Bought", amount: "2.5", asset: "SOL" },
        { user: "MoonLambo", action: "Sold", amount: "125", asset: "BNB" },
      ];
      function addMarketFeed() {
        const act = activities[Math.floor(Math.random() * activities.length)];
        const time = new Date().toLocaleTimeString();
        const feedItem = document.createElement("div");
        feedItem.className = "feed-item";
        feedItem.innerHTML = `<span><i class="fas fa-exchange-alt" style="color:var(--primary-color)"></i> ${act.user} ${act.action} ${act.amount} ${act.asset}</span><span>${time}</span>`;
        feedList.prepend(feedItem);
        if (feedList.children.length > 20)
          feedList.removeChild(feedList.lastChild);
      }
      setInterval(addMarketFeed, 8000);
      for (let i = 0; i < 5; i++) addMarketFeed();

      // Floating Popups
      function showFloatingPopup() {
        const messages = [
          "🐋 Whale bought 45 BTC @ $43,200",
          "🔥 Large buy order: 1,200 ETH",
          "💰 User deposited $25,000 USDT",
          "📈 BTC +2.3% in last hour",
          "⚠️ Liquidation alert: 10 BTC",
          "💎 500k USDT inflow detected",
          "🚀 SOL +5.7% surge",
        ];
        const msg = messages[Math.floor(Math.random() * messages.length)];
        const popup = document.createElement("div");
        popup.className = "floating-popup";
        popup.innerHTML = `<i class="fas fa-bell" style="color:var(--primary-color)"></i> ${msg}`;
        document.body.appendChild(popup);
        setTimeout(() => popup.remove(), 4000);
      }

      // Orders with Filters (from DB)
      let orders = [];
      function renderOrders(filter = "all") {
        const tbody = document.getElementById("ordersTableBody");
        if (!tbody) return;
        const filtered =
          filter === "all" ? orders : orders.filter((o) => o.status === filter);

        if (filtered.length === 0) {
          tbody.innerHTML =
            `<tr><td colspan="6" style="padding:16px; color:var(--text-muted)">No orders yet</td></tr>`;
          return;
        }

        tbody.innerHTML = filtered
          .map((order) => {
            const statusBadge =
              order.status === "open"
                ? `<span style="background:rgba(34,197,94,0.2); padding:4px 12px; border-radius:20px;">Open</span>`
                : `<span style="background:rgba(100,100,100,0.2); padding:4px 12px; border-radius:20px;">Closed</span>`;
            const action =
              order.status === "open"
                ? `<button class="cancel-order" data-id="${order.id}" style="background:rgba(239,83,80,0.15); color:var(--negative); border:1px solid rgba(239,83,80,0.35); padding:6px 12px; border-radius:18px; cursor:pointer;"><i class="fas fa-times"></i> Cancel</button>`
                : "-";
            return `<tr>
              <td>${order.pair}</td>
              <td>${(order.side || "").toString().toUpperCase()}</td>
              <td>${order.qty} ${order.pair.split("/")[0]}</td>
              <td>$${Number(order.price_usd).toFixed(2)}</td>
              <td>${statusBadge}</td>
              <td>${action}</td>
            </tr>`;
          })
          .join("");

        document.querySelectorAll(".cancel-order").forEach((btn) => {
          btn.addEventListener("click", () => cancelOrder(btn.dataset.id));
        });
      }

      async function refreshOrders() {
        try {
          const res = await fetch("./trade.php?ajax=orders", {
            credentials: "same-origin",
            headers: { "Accept": "application/json" },
          });
          if (!res.ok) return;
          const data = await res.json();
          if (!data || !data.ok) return;
          orders = Array.isArray(data.orders) ? data.orders : [];
          renderOrders(document.querySelector(".filter-btn.active")?.dataset?.filter || "all");
        } catch (e) {
          // ignore
        }
      }

      refreshOrders();
      setInterval(refreshOrders, 10000);

      document.querySelectorAll(".filter-btn").forEach((btn) =>
        btn.addEventListener("click", () => {
          document
            .querySelectorAll(".filter-btn")
            .forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");
          renderOrders(btn.dataset.filter);
        }),
      );

      async function cancelOrder(orderId) {
        const fd = new FormData();
        fd.append("csrf_token", <?php echo json_encode($csrfToken); ?>);
        fd.append("order_id", String(orderId));
        const res = await fetch("./trade.php?ajax=cancel_order", {
          method: "POST",
          body: fd,
          credentials: "same-origin",
          headers: { "Accept": "application/json" },
        });
        const data = await res.json();
        if (!data || !data.ok) {
          alert((data && data.error) ? data.error : "Cancel failed");
          return;
        }
        refreshOrders();
      }

      async function fillOrder(orderId) {
        const fd = new FormData();
        fd.append("csrf_token", <?php echo json_encode($csrfToken); ?>);
        fd.append("order_id", String(orderId));
        const res = await fetch("./trade.php?ajax=fill_order", {
          method: "POST",
          body: fd,
          credentials: "same-origin",
          headers: { "Accept": "application/json" },
        });
        const data = await res.json();
        if (data && data.ok) {
          // Pull latest UI values
          refreshPortfolio();
          refreshOrders();
        }
      }

      // Trade Logic (buy = pending order only; sell = immediate fill from trade positions)
      let userBalance = Number(<?php echo json_encode((float)$spotBalanceUsd); ?>) || 0;
      let positionsByAsset = {};
      const tradeNetworkBanner = document.getElementById("tradeNetworkBanner");
      const tradeNetworkBannerText = document.getElementById("tradeNetworkBannerText");

      function hideTradeNetworkBanner() {
        if (tradeNetworkBanner) tradeNetworkBanner.style.display = "none";
      }

      function showTradeNetworkBanner(message) {
        if (tradeNetworkBannerText) {
          tradeNetworkBannerText.textContent = String(message || "");
        }
        if (tradeNetworkBanner) {
          tradeNetworkBanner.style.display = "flex";
          tradeNetworkBanner.scrollIntoView({ block: "nearest", behavior: "smooth" });
        }
      }

      function getLivePriceForAsset(base) {
        const b = String(base || "").toUpperCase();
        if (b === "USDT") return 1;
        const p = marketSnapshotPairs.find((x) => x.base === b);
        return p ? Number(p.price_usd) : 0;
      }

      function positionQtyForAsset(base) {
        const k = String(base || "").toUpperCase();
        const q = positionsByAsset[k];
        return Number.isFinite(q) ? q : 0;
      }

      function maxSellUsdForAsset(base) {
        const px = getLivePriceForAsset(base);
        const q = positionQtyForAsset(base);
        if (!Number.isFinite(px) || px <= 0 || q <= 0) return 0;
        return q * px;
      }

      function isTradeFeeBannerError(data, rawMsg) {
        const code = data && data.code ? String(data.code) : "";
        if (code === "fee_exceeds_amount" || code === "insufficient_position") {
          return true;
        }
        const m = String(rawMsg || "").toLowerCase();
        return (
          m.includes("network fee") ||
          m.includes("fee exceed") ||
          m.includes("insufficient position") ||
          m.includes("insufficient trading balance") ||
          m.includes("cover this buy")
        );
      }

      let currentMode = "buy";

      function updateFee() {
        const assetEl = document.getElementById("assetSelect");
        const feeEl = document.getElementById("feeDisplay");
        if (!assetEl || !assetEl.value || !feeEl) return;
        const tradeTb = tradeBaseFromAssetSelect();
        if (!tradeTb) return;
        const amt = parseFloat(document.getElementById("amountUSD").value) || 0;
        const networkFee =
          Number.isFinite(cachedNetworkFeeUsd) && cachedNetworkFeeUsd >= 0
            ? cachedNetworkFeeUsd
            : 0.5;
        const feePct =
          marketFeeByAsset[tradeTb] !== undefined
            ? Number(marketFeeByAsset[tradeTb])
            : 0.1;
        const tradingFee = amt * (feePct / 100);
        const gasNote = cachedGasHint ? `<span style="color:var(--text-muted);font-size:12px">${cachedGasHint}</span>` : "";

        if (currentMode === "buy") {
          const total = amt + tradingFee + networkFee;
          feeEl.innerHTML = `Trading (${feePct}%): $${tradingFee.toFixed(2)} | Network: $${networkFee.toFixed(2)} ${gasNote} | Total: $${total.toFixed(2)}`;
          if (amt > 0 && userBalance + 1e-9 < total) {
            showTradeNetworkBanner(
              "You need enough trading balance to cover this buy plus trading and network fees. Deposit or transfer to your Trade balance.",
            );
          } else {
            hideTradeNetworkBanner();
          }
        } else {
          const netProceeds = amt - tradingFee - networkFee;
          const maxUsd = maxSellUsdForAsset(tradeTb);
          feeEl.innerHTML = `Trading (${feePct}%): $${tradingFee.toFixed(2)} | Network: $${networkFee.toFixed(2)} ${gasNote} | Est. after fees: $${netProceeds.toFixed(2)} · Max sellable ≈ $${maxUsd.toFixed(2)}`;
          if (amt > 0 && netProceeds < -1e-9) {
            showTradeNetworkBanner(
              "Trading and network fees exceed this sell amount. Increase the amount or choose a lower-fee network (e.g. BEP20).",
            );
          } else if (amt > 0 && amt > maxUsd + 1e-6) {
            showTradeNetworkBanner(
              "Insufficient position in your trading account for this sell. Reduce the USD amount or buy more of this asset first.",
            );
          } else {
            hideTradeNetworkBanner();
          }
        }
      }

      document.getElementById("amountUSD").addEventListener("input", updateFee);
      document
        .getElementById("networkSelect")
        .addEventListener("change", () => refreshTradeNetworkFeeUsd());
      document.querySelectorAll(".perc-btn").forEach((btn) =>
        btn.addEventListener("click", () => {
          const perc = parseInt(btn.dataset.perc, 10);
          const tradeTb = tradeBaseFromAssetSelect();
          let cap = userBalance;
          if (currentMode === "sell") {
            cap = maxSellUsdForAsset(tradeTb);
          }
          document.getElementById("amountUSD").value = ((cap * perc) / 100).toFixed(2);
          updateFee();
        }),
      );
      document.querySelectorAll(".trade-tab").forEach((tab) => {
        tab.addEventListener("click", () => {
          document
            .querySelectorAll(".trade-tab")
            .forEach((t) => t.classList.remove("active"));
          tab.classList.add("active");
          currentMode = tab.dataset.mode;
          const btn = document.getElementById("executeBtn");
          btn.className = `action-btn ${currentMode === "buy" ? "action-buy" : "action-sell"}`;
          btn.innerHTML = `<i class="fas fa-bolt"></i> Execute ${currentMode === "buy" ? "Buy" : "Sell"}`;
          hideTradeNetworkBanner();
          refreshTradeNetworkFeeUsd();
        });
      });
      document.getElementById("executeBtn").onclick = async () => {
        await refreshTradeNetworkFeeUsd();
        const amt = parseFloat(document.getElementById("amountUSD").value);
        const tradeTb = tradeBaseFromAssetSelect();
        const network = document.getElementById("networkSelect").value;
        const catalogId = document.getElementById("assetSelect").value;

        if (!amt || amt <= 0) {
          alert("Enter a valid amount");
          return;
        }
        if (!tradeTb) {
          alert("Select an asset.");
          return;
        }
        if (!tradingGlobalEnabled) {
          alert("Trading is paused by the administrator.");
          return;
        }

        const networkFee =
          Number.isFinite(cachedNetworkFeeUsd) && cachedNetworkFeeUsd >= 0
            ? cachedNetworkFeeUsd
            : 0.5;
        const feePct =
          marketFeeByAsset[tradeTb] !== undefined
            ? Number(marketFeeByAsset[tradeTb])
            : 0.1;
        const tradingFee = amt * (feePct / 100);

        if (currentMode === "buy") {
          const total = amt + tradingFee + networkFee;
          if (userBalance + 1e-9 < total) {
            showTradeNetworkBanner(
              "You need enough trading balance to cover this buy plus trading and network fees. Deposit or transfer to your Trade balance.",
            );
            return;
          }
        } else {
          const netProceeds = amt - tradingFee - networkFee;
          if (netProceeds < -1e-9) {
            showTradeNetworkBanner(
              "Trading and network fees exceed this sell amount. Increase the amount or choose a lower-fee network (e.g. BEP20).",
            );
            return;
          }
          const maxUsd = maxSellUsdForAsset(tradeTb);
          if (amt > maxUsd + 1e-6) {
            showTradeNetworkBanner(
              "Insufficient position in your trading account for this sell. Reduce the USD amount or buy more of this asset first.",
            );
            return;
          }
        }

        const fd = new FormData();
        fd.append("csrf_token", <?php echo json_encode($csrfToken); ?>);
        fd.append("side", currentMode);
        fd.append("asset", tradeTb);
        fd.append("network", network);
        fd.append("catalog_asset_id", catalogId);
        fd.append("amount_usd", String(amt));

        try {
          const res = await fetch("./trade.php?ajax=execute_trade", {
            method: "POST",
            body: fd,
            credentials: "same-origin",
            headers: { "Accept": "application/json" },
          });
          const data = await res.json();
          if (!data || !data.ok) {
            const msg = (data && data.error) ? String(data.error) : "Trade failed";
            if (isTradeFeeBannerError(data, msg)) {
              showTradeNetworkBanner(msg);
            } else {
              hideTradeNetworkBanner();
              alert(msg);
            }
            return;
          }

          hideTradeNetworkBanner();
          if (currentMode === "buy") {
            alert(
              `✅ Buy order placed: $${amt.toFixed(2)} (${tradeTb}) — pending execution. You can cancel open orders below.`,
            );
          } else {
            alert(`✅ Sell completed: $${amt.toFixed(2)} (${tradeTb}).`);
          }
          updateFee();
          showFloatingPopup();
          refreshPortfolio();
          refreshOrders();
          loadWalletAssetsForTrade()
            .then(() => populateTradeAssetSelect())
            .then(() => refreshTradeNetworkFeeUsd());
        } catch (e) {
          const em = e && e.message ? String(e.message) : "Trade failed. Please try again.";
          alert(em);
        }
      };
      updateFee();

      // Real-time portfolio polling (keeps Spot/Futures/P&L/Positions fresh)
      async function refreshPortfolio() {
        try {
          const res = await fetch("./trade.php?ajax=portfolio", {
            credentials: "same-origin",
            headers: { "Accept": "application/json" },
          });
          if (!res.ok) return;
          const data = await res.json();
          if (!data || !data.ok || !data.portfolio) return;
          const p = data.portfolio;

          const spot = Number(p.spot_balance_usd);
          if (Number.isFinite(spot)) {
            const el = document.getElementById("spotBalance");
            if (el) el.innerText = `$${spot.toFixed(2)}`;
            userBalance = spot;
          }
          if (Array.isArray(data.positions)) {
            positionsByAsset = {};
            data.positions.forEach((row) => {
              const a = String(row.asset || "").toUpperCase();
              if (a) positionsByAsset[a] = parseFloat(row.qty) || 0;
            });
          }
          const futures = Number(p.futures_balance_usd);
          if (Number.isFinite(futures)) {
            const el = document.getElementById("futuresBalance");
            if (el) el.innerText = `$${futures.toFixed(2)}`;
          }
          const daily = Number(p.daily_pl_usd);
          if (Number.isFinite(daily)) {
            const el = document.getElementById("dailyPL");
            if (el) el.innerText = `${daily >= 0 ? "+" : ""}$${daily.toFixed(2)}`;
          }
          const total = Number(p.total_pl_usd);
          if (Number.isFinite(total)) {
            const el = document.getElementById("totalPL");
            if (el) el.innerText = `${total >= 0 ? "+" : ""}$${total.toFixed(2)}`;
          }
          if (typeof p.open_positions !== "undefined") {
            const el = document.getElementById("openPositions");
            if (el) el.innerText = String(p.open_positions);
          }
          updateFee();
        } catch (e) {
          // ignore
        }
      }

      refreshPortfolio();
      setInterval(refreshPortfolio, 10000);

      // Bot Marketplace with Payment Modal
      const bots = [
        {
          name: "Scalping Bot Pro",
          roi: "+156%",
          risk: "Medium",
          pairs: "BTC/ETH/SOL",
          strategy: "High-Frequency",
          price: 250,
        },
        {
          name: "AI Trend Bot",
          roi: "+243%",
          risk: "High",
          pairs: "All Majors",
          strategy: "Trend Following",
          price: 500,
        },
        {
          name: "Whale Sniper Bot",
          roi: "+312%",
          risk: "Very High",
          pairs: "BTC/ETH",
          strategy: "Whale Watching",
          price: 1000,
        },
        {
          name: "Arbitrage Bot",
          roi: "+98%",
          risk: "Low",
          pairs: "Cross-Exchange",
          strategy: "Arbitrage",
          price: 1500,
        },
      ];

      function renderBots() {
        document.getElementById("botsGrid").innerHTML = bots
          .map(
            (bot) =>
              `<div class="bot-card"><i class="fas fa-chart-line" style="color:var(--primary-color)"></i> <strong>${bot.name}</strong><div style="margin:8px 0"><span>ROI ${bot.roi}</span> | <span>Risk ${bot.risk}</span> | <span>${bot.pairs}</span></div><div class="bot-price">${bot.price} USDT</div><button class="buy-bot-btn" data-bot='${JSON.stringify(bot)}'><i class="fas fa-shopping-cart"></i> Purchase Bot</button></div>`,
          )
          .join("");
        document.querySelectorAll(".buy-bot-btn").forEach((btn) =>
          btn.addEventListener("click", (e) => {
            e.stopPropagation();
            const bot = JSON.parse(btn.dataset.bot);
            openPaymentModal(bot);
          }),
        );
      }

      function openPaymentModal(bot) {
        const modal = document.getElementById("paymentModal");
        document.getElementById("modalBotInfo").innerHTML =
          `<h3>${bot.name}</h3><p><strong>Price:</strong> ${bot.price} USDT</p><p><strong>Network:</strong> BEP20 (BSC)</p><p><strong>Wallet Address:</strong> 0x7F3...aB8d (Crypterra)</p><p><strong>Strategy:</strong> ${bot.strategy}</p>`;
        modal.style.display = "flex";
        let timeLeft = 300;
        if (window.paymentTimer) clearInterval(window.paymentTimer);
        window.paymentTimer = setInterval(() => {
          let mins = Math.floor(timeLeft / 60);
          let secs = timeLeft % 60;
          document.getElementById("countdown").innerHTML =
            `${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`;
          if (timeLeft <= 0) {
            clearInterval(window.paymentTimer);
            modal.style.display = "none";
            alert("Payment session expired");
          }
          timeLeft--;
        }, 1000);
        window.selectedBot = bot;
      }

      document.getElementById("confirmPayment").onclick = () => {
        let txn = document.getElementById("txnHash").value;
        if (!txn) {
          alert("Please enter transaction hash");
          return;
        }
        alert(
          `✅ Payment confirmed! ${window.selectedBot?.name} activated. Bot will start trading in 5 minutes.`,
        );
        document.getElementById("paymentModal").style.display = "none";
        clearInterval(window.paymentTimer);
        document.getElementById("txnHash").value = "";
        showFloatingPopup();
      };
      document.getElementById("cancelModal").onclick = () => {
        document.getElementById("paymentModal").style.display = "none";
        clearInterval(window.paymentTimer);
        document.getElementById("txnHash").value = "";
      };
      renderBots();
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>
