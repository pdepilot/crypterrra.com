<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_sidebar_state.php';

function sh(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userFullName = (string)($_SESSION['user_full_name'] ?? '');
$userEmail = (string)($_SESSION['user_email'] ?? '');
$sendAgg = [
  'cash_usd' => '0.00',
  'crypto_portfolio_usd' => '0.00',
  'total_balance_usd' => '0.00',
];
$totalBalanceUsd = '0.00';
$cashBalanceUsd = '0.00';
$walletId = '';
$recentSends = [];
$sendBootAssets = [];
$sendGasRules = [];

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
    $userEmail = (string)$row['email'];
    $_SESSION['user_full_name'] = $userFullName;
    $_SESSION['user_email'] = $userEmail;
    $walletId = is_string($row['wallet_id'] ?? null) ? (string)$row['wallet_id'] : '';
    if ($walletId === '') {
      $walletId = user_ensure_wallet_id($db, $userId);
    }
  }
  $sendAgg = user_get_wallet_display_aggregates($db, $schema, $userId);
  ensure_transactions_table($db);
  ensure_asset_portfolio_schema($db);
  $recentSends = user_fetch_recent_sends($db, $userId, 12);

  $sqlBoot = <<<'SQL'
SELECT c.asset_id, c.symbol, c.icon_url, COALESCE(b.balance, 0) AS balance
FROM asset_catalog c
LEFT JOIN user_asset_balances b ON b.asset_id = c.asset_id AND b.user_id = ?
WHERE c.is_active = 1
SQL;
  $stBoot = $db->prepare($sqlBoot);
  if ($stBoot) {
    $stBoot->bind_param('i', $userId);
    $stBoot->execute();
    $resBoot = $stBoot->get_result();
    if ($resBoot instanceof mysqli_result) {
      while ($r = $resBoot->fetch_assoc()) {
        $sendBootAssets[] = [
          'id' => (string)($r['asset_id'] ?? ''),
          'symbol' => (string)($r['symbol'] ?? ''),
          'balance' => (string)($r['balance'] ?? '0'),
          'icon_url' => (string)($r['icon_url'] ?? ''),
        ];
      }
      $resBoot->free();
    }
    $stBoot->close();
  }

  $sendGasRules = [];
  ensure_network_fee_rules_schema($db);
  $sendAssetNetworks = [
    'btc' => 'BTC',
    'eth' => 'ERC20',
    'sol' => 'SOL',
    'usdt_erc20' => 'ERC20',
    'usdt_trc20' => 'TRC20',
  ];
  foreach ($sendAssetNetworks as $bootAssetId => $bootNetwork) {
    $feeQuote = user_quote_network_fees($db, $userId, $bootAssetId, $bootNetwork, 1.0);
    if ($feeQuote['ok'] ?? false) {
      $sendGasRules[$bootAssetId] = [
        'gas_asset_id' => (string)($feeQuote['gas_asset_id'] ?? ''),
        'gas_fee_amount' => (float)($feeQuote['gas_fee_amount'] ?? 0.0),
        'min_gas_reserve' => (float)($feeQuote['min_gas_reserve'] ?? 0.0),
        'asset_fee_pct' => (float)($feeQuote['asset_fee_pct'] ?? 0.0),
      ];
    }
  }

  $db->close();
} catch (Throwable $e) {
  error_log('[send] ' . $e->getMessage());
}

$totalBalanceUsd = $sendAgg['total_balance_usd'];
$cashBalanceUsd = $sendAgg['cash_usd'];

$kycSidebarBadge = kyc_sidebar_badge_for_session();
$csrfToken = user_csrf_token();

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);

$sendBootJson = json_encode(
  [
    'wallet_balance_usd' => $sendAgg['total_balance_usd'],
    'total_balance_usd' => $sendAgg['total_balance_usd'],
    'main_wallet_usd' => $sendAgg['cash_usd'],
    'crypto_portfolio_usd' => $sendAgg['crypto_portfolio_usd'],
    'available_balance_usd' => $sendAgg['total_balance_usd'],
    'assets' => $sendBootAssets,
    'gas_rules' => $sendGasRules,
    'csrf' => $csrfToken,
  ],
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
if ($sendBootJson === false) {
  $sendBootJson = '{}';
}

function send_status_badge_class(string $status): string {
  $s = strtolower($status);
  if ($s === 'completed' || $s === 'confirmed') {
    return 'status-success';
  }
  if ($s === 'failed' || $s === 'rejected' || $s === 'cancelled') {
    return 'status-failed';
  }
  return 'status-pending';
}

function send_short_addr(string $addr): string {
  $a = trim($addr);
  if (strlen($a) <= 14) {
    return $a;
  }
  return substr($a, 0, 10) . '...';
}

function send_addr_initials(string $addr): string {
  $s = preg_replace('/[^a-zA-Z0-9]/', '', $addr);
  if ($s === '') {
    return '?';
  }

  return strtoupper(substr($s, 0, 2));
}

/** Map transactions.asset (uppercased asset_id) to send page crypto button id. */
function send_tx_asset_to_btn_id(string $assetFromDb): ?string {
  $low = strtolower(trim($assetFromDb));
  return match ($low) {
    'btc' => 'btc',
    'eth' => 'eth',
    'sol' => 'sol',
    'usdt_erc20' => 'usdt_erc20',
    'usdt_trc20' => 'usdt_trc20',
    default => null,
  };
}

function send_tx_asset_badge(string $assetFromDb): string {
  $low = strtolower(trim($assetFromDb));
  return match ($low) {
    'btc' => 'BTC',
    'eth' => 'ETH',
    'sol' => 'SOL',
    'usdt_erc20', 'usdt_trc20' => 'USDT',
    default => strlen($low) <= 10 ? strtoupper($low) : strtoupper(substr($low, 0, 8)) . '…',
  };
}

/**
 * Unique recipient addresses from recent sends (newest first), for the "Recent Wallets" list.
 *
 * @param list<array<string,mixed>> $sends
 * @return list<array{address:string, label:string, short:string, initials:string, badge:string, asset_btn:?string}>
 */
function send_unique_recent_recipients(array $sends, int $max = 8): array {
  $max = max(1, min(20, $max));
  $seen = [];
  $out = [];
  foreach ($sends as $tx) {
    $addr = trim((string)($tx['to_address'] ?? ''));
    if ($addr === '') {
      continue;
    }
    $key = strtolower($addr);
    if (isset($seen[$key])) {
      continue;
    }
    $seen[$key] = true;
    $assetRaw = (string)($tx['asset'] ?? '');
    $short = send_short_addr($addr);
    $out[] = [
      'address' => $addr,
      'label' => $short,
      'short' => $short,
      'initials' => send_addr_initials($addr),
      'badge' => send_tx_asset_badge($assetRaw),
      'asset_btn' => send_tx_asset_to_btn_id($assetRaw),
    ];
    if (count($out) >= $max) {
      break;
    }
  }

  return $out;
}

$recentRecipients = send_unique_recent_recipients($recentSends, 8);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes"
    />
    <title>Crypterra | Send Crypto</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    />
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family:
          "Inter",
          system-ui,
          -apple-system,
          "Segoe UI",
          Roboto,
          sans-serif;
      }

      @import url("https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap");

      /* ========== ROOT COLORS - GREEN THEME ========== */
      :root {
        --primary-color: #22c55e;
        --primary-light: #4ade80;
        --primary-dark: #16a34a;
        --primary-glow: rgba(34, 197, 94, 0.5);
        --primary-soft: rgba(34, 197, 94, 0.15);
        --secondary-color: #2ecc71;
        --danger-color: #ef5350;
        --warning-color: #ffa726;

        /* dark theme (default) */
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
        --shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        --shadow-glow: 0 0 30px var(--primary-glow);
        --light-border: #2d2d2d;
        --input-bg: rgba(10, 20, 35, 0.8);
      }

      /* light theme class */
      body.light {
        --primary-color: #22c55e;
        --primary-light: #4ade80;
        --primary-glow: rgba(34, 197, 94, 0.4);
        --bg: #f5f7fb;
        --bg-secondary: #eef2f6;
        --surface: #ffffff;
        --surface-light: #f0f4fa;
        --text: #333333;
        --text-secondary: #555555;
        --text-muted: #7f8c8d;
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-edge: rgba(34, 197, 94, 0.3);
        --card-bg: #ffffff;
        --positive: #2ecc71;
        --negative: #e74c3c;
        --shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        --shadow-glow: 0 0 20px rgba(34, 197, 94, 0.4);
        --light-border: #eaeaea;
        --input-bg: #f8fafc;
      }

      body {
        background-color: var(--bg);
        color: var(--text);
        transition:
          background-color 0.3s ease,
          color 0.2s ease;
      }

      /* Dashboard Layout */
      .dashboard {
        display: flex;
        min-height: 100vh;
      }

      /* SIDEBAR */
      .sidebar {
        width: 280px;
        background: var(--bg-secondary);
        border-right: 1px solid var(--light-border);
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
        border-bottom: 1px solid var(--light-border);
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
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: 0.2s;
        color: var(--text-secondary);
      }

      .sidebar-user {
        padding: 24px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        border-bottom: 1px solid var(--light-border);
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

      /* MAIN CONTENT */
      .main-content {
        flex: 1;
        overflow-x: auto;
      }

      .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 28px;
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--glass-edge);
        box-sizing: border-box;
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
        align-items: center;
      }

      .icon-button {
        position: relative;
        background: var(--surface);
        width: 40px;
        height: 40px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.2s;
        color: var(--text-secondary);
        border: 1px solid var(--light-border);
      }

      .notification-badge {
        position: absolute;
        top: 4px;
        right: 4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 18px;
        text-align: center;
        background: var(--danger-color, #ef4444);
        color: #fff;
        border-radius: 9px;
        border: 2px solid var(--surface);
        display: none;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
      }

      .notification-dropdown {
        position: fixed;
        top: 72px;
        right: 20px;
        width: min(380px, calc(100vw - 32px));
        max-height: min(420px, 70vh);
        overflow-y: auto;
        background: var(--surface);
        border-radius: 16px;
        font-size: 0.85rem;
        z-index: 200;
        border: 1px solid var(--light-border);
        box-shadow: var(--shadow);
        color: var(--text);
        display: none;
      }
      .notification-dropdown .nd-head {
        padding: 12px 16px;
        border-bottom: 1px solid var(--light-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        position: sticky;
        top: 0;
        background: var(--surface);
      }
      .notification-dropdown .nd-mark {
        background: none;
        border: none;
        color: var(--primary-color);
        cursor: pointer;
        font-size: 0.8rem;
      }
      .notification-item {
        padding: 12px 16px;
        border-bottom: 1px solid var(--light-border);
        line-height: 1.45;
      }
      .notification-item.unread {
        border-left: 3px solid var(--primary-color);
      }
      .notification-item .nd-title {
        font-weight: 600;
        margin-bottom: 4px;
      }
      .notification-item .nd-body {
        color: var(--text-muted);
        font-size: 0.8rem;
      }
      .notification-item .nd-time {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 6px;
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

      /* Send Page Specific Styles */
      .send-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 28px;
        margin-top: 60px;
      }

      .page-header {
        margin-bottom: 32px;
      }

      .page-header h1 {
        font-size: 2rem;
        background: linear-gradient(135deg, var(--text), var(--primary-light));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      .send-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 32px;
      }

      .send-card {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 28px;
        transition:
          transform 0.2s,
          box-shadow 0.2s;
      }

      .send-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-glow);
        border-color: var(--primary-light);
      }

      .card-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--primary-light);
      }

      .card-title i {
        font-size: 1.8rem;
      }

      /* Balance Display */
      .balance-display {
        background: var(--primary-soft);
        border-radius: 24px;
        padding: 20px;
        margin-bottom: 28px;
        text-align: center;
      }

      .balance-label {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 8px;
      }

      .balance-amount {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--primary-light);
      }

      /* Crypto Selector */
      .crypto-selector {
        margin-bottom: 24px;
      }

      .crypto-selector label {
        display: block;
        margin-bottom: 10px;
        font-weight: 500;
        color: var(--text-secondary);
      }

      .crypto-buttons {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
      }

      .crypto-btn {
        background: var(--surface);
        border: 1px solid var(--light-border);
        padding: 12px 24px;
        border-radius: 60px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        color: var(--text-secondary);
      }

      .crypto-btn.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        box-shadow: 0 0 15px var(--primary-glow);
      }

      /* Input Fields */
      .input-group {
        margin-bottom: 24px;
      }

      .input-group label {
        display: block;
        margin-bottom: 10px;
        font-weight: 500;
        color: var(--text-secondary);
      }

      .input-field {
        background: var(--input-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 14px 20px;
        width: 100%;
        color: var(--text);
        font-size: 1rem;
        transition: all 0.2s;
      }

      .input-field:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-soft);
      }

      .input-with-icon {
        position: relative;
      }

      .input-with-icon input {
        padding-right: 70px;
      }

      .input-suffix {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-weight: 500;
      }

      /* Contact List */
      .contact-list {
        margin-bottom: 24px;
      }

      .contact-item {
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 16px;
        padding: 12px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .contact-item:hover {
        border-color: var(--primary-color);
        background: var(--primary-soft);
        transform: translateX(4px);
      }

      .contact-avatar {
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

      .contact-details {
        flex: 1;
      }

      .contact-name {
        font-weight: 600;
        font-size: 0.95rem;
      }

      .contact-address {
        font-size: 0.7rem;
        color: var(--text-muted);
        font-family: monospace;
      }

      .contact-badge {
        font-size: 0.7rem;
        color: var(--primary-light);
      }

      /* Fee & Summary */
      .fee-summary {
        background: var(--surface);
        border-radius: 20px;
        padding: 20px;
        margin: 24px 0;
      }

      .fee-row {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid var(--glass-edge);
      }

      .fee-row:last-child {
        border-bottom: none;
      }

      .total-row {
        font-weight: 700;
        color: var(--primary-light);
        font-size: 1.1rem;
        margin-top: 8px;
        padding-top: 12px;
      }

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

      /* Send Button */
      .send-btn {
        width: 100%;
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border: none;
        padding: 16px;
        border-radius: 60px;
        color: white;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        margin-top: 20px;
      }

      .send-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }

      .send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }

      /* 2FA Modal */
      .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 1000;
        justify-content: center;
        align-items: center;
      }

      .modal-content {
        background: var(--card-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 32px;
        max-width: 400px;
        width: 90%;
        text-align: center;
      }

      .modal-content h3 {
        margin-bottom: 20px;
      }

      .modal-input {
        background: var(--input-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 16px;
        padding: 14px;
        width: 100%;
        color: var(--text);
        font-size: 1.2rem;
        text-align: center;
        letter-spacing: 4px;
        margin: 20px 0;
      }

      .modal-buttons {
        display: flex;
        gap: 12px;
      }

      .modal-btn {
        flex: 1;
        padding: 12px;
        border-radius: 40px;
        border: none;
        cursor: pointer;
        font-weight: 600;
      }

      .modal-btn.confirm {
        background: var(--primary-color);
        color: white;
      }

      .modal-btn.cancel {
        background: var(--surface);
        color: var(--text-secondary);
      }

      /* History */
      .history-table {
        margin-top: 20px;
        max-height: 300px;
        overflow-y: auto;
      }

      .history-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid var(--glass-edge);
      }

      .status-badge {
        padding: 4px 12px;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 500;
      }

      .status-success {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary-light);
      }

      .status-pending {
        background: rgba(255, 167, 38, 0.2);
        color: var(--warning-color);
      }

      .status-failed {
        background: rgba(239, 68, 68, 0.2);
        color: var(--negative);
      }

      .alert {
        background: rgba(239, 68, 68, 0.1);
        border-left: 3px solid var(--negative);
        padding: 12px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 0.85rem;
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
        .sidebar {
          position: fixed;
          transform: translateX(-100%);
          transition: transform 0.3s;
          z-index: 1000;
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
        .mobile-menu-btn {
          display: block;
        }
        .send-grid {
          grid-template-columns: 1fr;
        }
        .send-container {
          padding: 16px;
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
      <!-- SIDEBAR -->
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <div class="logo-wrapper">
            <div class="logo-icon"><i class="fa-solid fa-globe"></i></div>
            <h3><span>Crypterra</span></h3>
          </div>
          <div class="collapse-btn" id="collapseSidebar">
            <i class="fa-solid fa-chevron-left"></i>
          </div>
        </div>
        <div class="sidebar-user">
          <div class="user-avatar-large"><?php echo sh($ub['avatar_letter']); ?></div>
          <div class="user-info">
            <h4><?php echo sh($ub['full_name']); ?></h4>
            <p><?php echo sh($ub['wallet_short']); ?></p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section">MAIN</div>
          <a href="./userprofile.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a
          >
          <a href="./trade.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-line"></i> <span>Trade</span></a
          >
          <a href="./wallet.php" class="sidebar-link"
            ><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a
          >
          <div class="nav-section">TRADING</div>
          <!-- <a href="./deposit.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-down"></i> <span>Deposit</span>
            <span class="action-badge">NEW</span></a
          >
          <a href="./withdraw.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-up"></i> <span>Withdraw</span></a
          > -->
          <a href="./send.php" class="sidebar-link active"
            ><i class="fa-solid fa-paper-plane"></i> <span>Send</span></a
          >
          <a href="./receive.php" class="sidebar-link"
            ><i class="fa-solid fa-inbox"></i> <span>Receive</span></a
          >
          <div class="nav-section">ACCOUNT</div>
          <a href="./kycverification.php" class="sidebar-link"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Verification</span>
            <?php echo kyc_sidebar_badge_span($kycSidebarBadge); ?></a
          >
                    <a href="./security.php" class="sidebar-link"
            ><i class="fa-solid fa-shield"></i> <span>Security</span></a
          >
          <a href="./settings.php" class="sidebar-link"
            ><i class="fa-solid fa-gear"></i> <span>Settings</span></a
          >
          <div class="nav-section">SUPPORT</div>
          <a href="./support.php" class="sidebar-link"
            ><i class="fa-solid fa-headset"></i> <span>Support</span></a
          >
          <a href="./logout.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Logout</span></a
          >
        </nav>
      </aside>

      <!-- MAIN CONTENT -->
      <main class="main-content">
        <div class="top-bar top-bar--main">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn">
              <i class="fa-solid fa-bars"></i>
            </div>
            <a href="./userprofile.php" class="user-profile">
              <div class="user-avatar"><?php echo sh($ub['avatar_letter']); ?></div>
              <div class="user-profile-meta">
                <h3 class="user-name">Hello, <?php echo sh($ub['full_name']); ?></h3>
              </div>
            </a>
          </div>
          <div class="header-right">
            <div class="icon-button" id="notifications-btn">
              <i class="fa-solid fa-bell"></i>
              <span class="notification-badge"></span>
            </div>
            <div class="theme-toggle" id="theme-toggle"></div>
          </div>
        </div>

        <div class="notification-dropdown" id="notification-dropdown" style="display: none" role="dialog" aria-label="Notifications"></div>

        <div class="send-container">
          <div class="page-header" data-aos="fade-up">
            <h1><i class="fa-solid fa-paper-plane"></i> Send Cryptocurrency</h1>
            <!--<p style="color: var(--text-muted); margin-top: 8px">-->
            <!--  Crypto is debited from your asset balance when you submit; each send stays-->
            <!--  <strong>pending</strong> until staff review it in Admin → Send. If declined, your crypto is refunded.-->
            <!--</p>-->
          </div>

          <div class="send-grid">
            <!-- Left Column: Send Form -->
            <div class="send-card" data-aos="fade-right">
              <div class="card-title">
                <i class="fa-solid fa-send"></i>
                <span>Send Details</span>
              </div>

              <div class="balance-display">
                <div class="balance-label">Total balance (USD)</div>
                <div class="balance-amount" id="totalBalanceDisplay">
                  $<?php echo sh(number_format((float)$totalBalanceUsd, 2, '.', ',')); ?>
                </div>
                <div
                  style="font-size: 0.85rem; margin-top: 8px; color: var(--text-muted)"
                  id="cashBalanceHint"
                >
                  USD cash (withdraw / trade): $<?php echo sh(number_format((float)$cashBalanceUsd, 2, '.', ',')); ?>
                </div>
                <div
                  style="font-size: 0.85rem; margin-top: 8px"
                  id="cryptoBalance"
                >
                  —
                </div>
              </div>

              <div class="crypto-selector">
                <label>Select Cryptocurrency</label>
                <div class="crypto-buttons" id="crypto-buttons">
                  <button
                    class="crypto-btn active"
                    data-asset="btc"
                    type="button"
                  >
                    <img src="https://assets.coincap.io/assets/icons/btc@2x.png" alt="" style="width:18px;height:18px;object-fit:contain" onerror="this.style.display='none'">
                    Bitcoin (BTC)
                  </button>
                  <button class="crypto-btn" data-asset="eth" type="button">
                    <img src="https://assets.coincap.io/assets/icons/eth@2x.png" alt="" style="width:18px;height:18px;object-fit:contain" onerror="this.style.display='none'">
                    Ethereum (ETH)
                  </button>
                  <button class="crypto-btn" data-asset="usdt_erc20" type="button">
                    <img src="https://assets.coincap.io/assets/icons/usdt@2x.png" alt="" style="width:18px;height:18px;object-fit:contain" onerror="this.style.display='none'">
                    USDT (ERC20)
                  </button>
                  <button class="crypto-btn" data-asset="usdt_trc20" type="button">
                    <img src="https://assets.coincap.io/assets/icons/usdt@2x.png" alt="" style="width:18px;height:18px;object-fit:contain" onerror="this.style.display='none'">
                    USDT (TRC20)
                  </button>
                  <button class="crypto-btn" data-asset="sol" type="button">
                    <img src="https://assets.coincap.io/assets/icons/sol@2x.png" alt="" style="width:18px;height:18px;object-fit:contain" onerror="this.style.display='none'">
                    Solana (SOL)
                  </button>
                </div>
              </div>

              <div class="input-group">
                <label>Recipient Address</label>
                <div class="input-with-icon">
                  <input
                    type="text"
                    id="recipientAddress"
                    class="input-field"
                    placeholder="Enter wallet address (0x... or 1...)"
                    value=""
                  />
                </div>
              </div>

              <div class="contact-list">
                <label>Recent Wallets</label>
                <div id="contactsList">
                  <?php if ($recentRecipients === []) { ?>
                  <div
                    style="color: var(--text-muted); font-size: 0.9rem; padding: 10px 4px; line-height: 1.45"
                  >
                    No saved recipients yet. After you send crypto, addresses you use will appear here for quick
                    reuse.
                  </div>
                  <?php } else { ?>
                  <?php foreach ($recentRecipients as $rc) {
                    $addrEsc = sh($rc['address']);
                    $labelEsc = sh($rc['label']);
                    $iniEsc = sh($rc['initials']);
                    $shortEsc = sh($rc['short']);
                    $badgeEsc = sh($rc['badge']);
                    $btn = $rc['asset_btn'];
                    ?>
                  <div
                    class="contact-item"
                    data-address="<?php echo $addrEsc; ?>"
                    data-name="<?php echo $labelEsc; ?>"
                    <?php echo $btn !== null ? ' data-asset="' . sh($btn) . '"' : ''; ?>
                  >
                    <div class="contact-avatar"><?php echo $iniEsc; ?></div>
                    <div class="contact-details">
                      <div class="contact-name">Saved address</div>
                      <div class="contact-address"><?php echo $shortEsc; ?></div>
                    </div>
                    <div class="contact-badge"><?php echo $badgeEsc; ?></div>
                  </div>
                  <?php } ?>
                  <?php } ?>
                </div>
              </div>

              <div class="input-group">
                <label>Amount to Send</label>
                <div class="input-with-icon">
                  <input
                    type="text"
                    inputmode="decimal"
                    id="sendAmount"
                    class="input-field"
                    placeholder="0.064"
                    autocomplete="off"
                    value=""
                  />
                  <span class="input-suffix" id="cryptoSuffix">BTC</span>
                </div>
                <div style="margin-top: 8px; text-align: right">
                  <button
                    id="maxAmountBtn"
                    style="
                      background: none;
                      border: none;
                      color: var(--primary-color);
                      cursor: pointer;
                      font-size: 0.85rem;
                    "
                  >
                    <i class="fas fa-arrow-up"></i> Max
                  </button>
                </div>
              </div>

              <div class="fee-summary">
                <div class="fee-row">
                  <span>Send Amount</span>
                  <span id="displayAmount">0.0000 BTC</span>
                </div>
                <div class="fee-row">
                  <span>≈ USD Value</span>
                  <span id="usdValue">$0.00 USD</span>
                </div>
              </div>

              <div
                class="receive-network-warning"
                id="sendGasFeeBanner"
                style="display: none"
                role="alert"
                aria-live="polite"
              >
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span id="sendGasFeeBannerText"></span>
              </div>

              <div class="alert" id="insufficientAlert" style="display: none">
                <i class="fa-solid fa-triangle-exclamation"></i> Insufficient
                balance! Please enter a lower amount.
              </div>

              <button class="send-btn" id="sendBtn">
                <i class="fa-solid fa-paper-plane"></i> Send Now
              </button>
            </div>

            <!-- Right Column: Instructions & History -->
            <div class="send-card" data-aos="fade-left">
              <div class="card-title">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Recent Transfers</span>
              </div>

              <div id="transferHistory" class="history-table">
                <?php if ($recentSends === []) { ?>
                <div
                  class="history-row"
                  style="border: none; color: var(--text-muted); justify-content: center"
                >
                  <div>No sends yet. Your transfers will appear here.</div>
                </div>
                <?php } else { ?>
                <?php foreach ($recentSends as $tx) {
                  $asset = strtoupper((string)($tx['asset'] ?? ''));
                  $icon = 'fas fa-coins';
                  if ($asset === 'BTC') {
                    $icon = 'fab fa-bitcoin';
                  } elseif ($asset === 'ETH') {
                    $icon = 'fab fa-ethereum';
                  } elseif ($asset === 'USDT') {
                    $icon = 'fas fa-dollar-sign';
                  } elseif ($asset === 'SOL') {
                    $icon = 'fas fa-bolt';
                  }
                  $cryptoAmt = (string)($tx['crypto_amount'] ?? '');
                  $to = (string)($tx['to_address'] ?? '');
                  $st = (string)($tx['status'] ?? 'pending');
                  $badgeClass = send_status_badge_class($st);
                  $stLabel = ucfirst($st);
                  ?>
                <div class="history-row">
                  <div><i class="<?php echo sh($icon); ?>"></i> <?php echo sh($cryptoAmt !== '' ? $cryptoAmt . ' ' . $asset : $asset); ?></div>
                  <div>To: <?php echo sh(send_short_addr($to)); ?></div>
                  <div>
                    <span class="status-badge <?php echo sh($badgeClass); ?>"><?php echo sh($stLabel); ?></span>
                  </div>
                </div>
                <?php } ?>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="includes/user_theme.js"></script>
    <script>

      const WALLET_API = "./api/wallet_api.php";
      const FEE_QUOTE_API = "./api/fee_quote_api.php";
      const NETWORK_FEE_MSG =
        "You don't have enough network fee to proceed with this transaction.";
      let walletCsrf =
        (window.__SEND_BOOT && window.__SEND_BOOT.csrf) || "";
      const ASSETS_API = "./api/assets_api.php";

      const sendRules = {
        btc: { symbol: "BTC", min: 0.001, decimals: 6 },
        eth: { symbol: "ETH", min: 0.01, decimals: 6 },
        sol: { symbol: "SOL", min: 0.1, decimals: 6 },
        usdt_erc20: { symbol: "USDT", min: 10, decimals: 2 },
        usdt_trc20: { symbol: "USDT", min: 10, decimals: 2 },
      };

      let assetsById = {};
      let assetsLoaded = false;
      let lastSendQuote = null;
      let sendQuoteTimer = null;
      const sendGasRules = Object.assign(
        {
          btc: { gas_asset_id: "btc", gas_fee_amount: 0.0002, min_gas_reserve: 0.0001, asset_fee_pct: 0 },
          eth: { gas_asset_id: "eth", gas_fee_amount: 0.002, min_gas_reserve: 0.001, asset_fee_pct: 0 },
          sol: { gas_asset_id: "sol", gas_fee_amount: 0.0005, min_gas_reserve: 0.0001, asset_fee_pct: 0 },
          usdt_erc20: { gas_asset_id: "eth", gas_fee_amount: 0.002, min_gas_reserve: 0.001, asset_fee_pct: 0 },
          usdt_trc20: { gas_asset_id: "trx", gas_fee_amount: 2, min_gas_reserve: 1, asset_fee_pct: 0 },
        },
        (window.__SEND_BOOT && window.__SEND_BOOT.gas_rules) || {},
      );

      /** Parse API / form numeric strings (handles commas and locale decimals). */
      function parseAssetBalance(raw) {
        if (raw == null || raw === "") return 0;
        if (typeof raw === "number") return Number.isFinite(raw) ? raw : 0;
        const s = String(raw).replace(/[$,\s]/g, "").trim();
        const n = parseFloat(s);
        return Number.isFinite(n) ? n : 0;
      }

      /** Parse amount typed in the send field (Android may use comma decimals). */
      function parseCryptoAmount(raw) {
        if (raw == null) return NaN;
        let s = String(raw).trim().replace(/\s/g, "");
        if (s === "") return NaN;
        if (s.includes(",") && !s.includes(".")) {
          s = s.replace(",", ".");
        } else {
          s = s.replace(/,/g, "");
        }
        const n = parseFloat(s);
        return Number.isFinite(n) ? n : NaN;
      }

      function applyAssetsList(list) {
        if (!Array.isArray(list)) return;
        assetsById = {};
        list.forEach((a) => {
          if (a && a.id != null) {
            assetsById[String(a.id)] = a;
          }
        });
        assetsLoaded = Object.keys(assetsById).length > 0;
      }

      applyAssetsList(window.__SEND_BOOT && window.__SEND_BOOT.assets);

      async function refreshAssetsFromServer() {
        try {
          const [assetsRes, walletRes] = await Promise.all([
            fetch(ASSETS_API, { credentials: "same-origin", headers: { Accept: "application/json" } }),
            fetch(WALLET_API, { credentials: "same-origin", headers: { Accept: "application/json" } }),
          ]);
          const data = await assetsRes.json();
          const wj = await walletRes.json().catch(() => null);
          if (!data || !data.ok || !Array.isArray(data.assets)) return;
          applyAssetsList(data.assets);
          assetsLoaded = true;
          const totalEl = document.getElementById("totalBalanceDisplay");
          const cashHint = document.getElementById("cashBalanceHint");
          if (wj && wj.ok && wj.wallet) {
            const tw = wj.wallet;
            const total =
              typeof tw.total_balance_usd === "string"
                ? tw.total_balance_usd
                : tw.wallet_balance_usd ?? "0";
            const cash =
              typeof tw.main_wallet_usd === "string" ? tw.main_wallet_usd : "0";
            if (totalEl && typeof total === "string") {
              totalEl.textContent = formatUsdVal(total);
            }
            if (cashHint && typeof cash === "string") {
              cashHint.textContent =
                "USD cash (withdraw / trade): " + formatUsdVal(cash);
            }
          }
          updateCryptoUI();
        } catch (_) {
          /* ignore */
        }
      }

      function formatUsdVal(n) {
        const x = Number(n);
        if (Number.isNaN(x)) return "$0.00";
        return (
          "$" +
          x.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })
        );
      }

      function networkForAsset(assetId) {
        if (assetId === "btc") return "BTC";
        if (assetId === "eth") return "ERC20";
        if (assetId === "sol") return "SOL";
        if (assetId === "usdt_trc20") return "TRC20";
        if (assetId === "usdt_erc20") return "ERC20";
        return "";
      }

      let currentAssetId = "btc";
      let currentBalance = 0;

      // DOM Elements
      const cryptoBtns = document.querySelectorAll(".crypto-btn");
      const sendAmount = document.getElementById("sendAmount");
      const cryptoSuffix = document.getElementById("cryptoSuffix");
      const displayAmount = document.getElementById("displayAmount");
      const usdValueSpan = document.getElementById("usdValue");
      const cryptoBalanceSpan = document.getElementById("cryptoBalance");
      const insufficientAlert = document.getElementById("insufficientAlert");
      const sendGasFeeBanner = document.getElementById("sendGasFeeBanner");
      const sendGasFeeBannerText = document.getElementById("sendGasFeeBannerText");
      const sendBtn = document.getElementById("sendBtn");
      const maxAmountBtn = document.getElementById("maxAmountBtn");
      const recipientAddress = document.getElementById("recipientAddress");

      const sendErrorModal = document.getElementById("sendErrorModal");
      const sendErrorMessage = document.getElementById("sendErrorMessage");
      const closeSendError = document.getElementById("closeSendError");

      function hideSendErrorModal() {
        if (sendErrorModal) sendErrorModal.style.display = "none";
      }

      function showSendErrorModal(message) {
        hideGasFeeBanner();
        if (sendErrorMessage) sendErrorMessage.textContent = String(message || "");
        if (sendErrorModal) sendErrorModal.style.display = "flex";
      }

      function hideGasFeeBanner() {
        if (sendGasFeeBanner) sendGasFeeBanner.style.display = "none";
      }

      function showGasFeeBanner(message) {
        hideSendErrorModal();
        if (sendGasFeeBannerText) {
          sendGasFeeBannerText.textContent = String(message || NETWORK_FEE_MSG);
        }
        if (sendGasFeeBanner) {
          sendGasFeeBanner.style.display = "flex";
          sendGasFeeBanner.scrollIntoView({ block: "nearest", behavior: "smooth" });
        }
      }

      /** Synchronous gas check using server boot rules + loaded asset balances. */
      function checkNetworkFeeLocal(amount) {
        const rule = sendGasRules[currentAssetId];
        if (!rule) return { ok: true };
        const gasId = String(rule.gas_asset_id || "").trim();
        const gasFee = Number(rule.gas_fee_amount || 0);
        const reserve = Number(rule.min_gas_reserve || 0);
        const assetFeePct = Number(rule.asset_fee_pct || 0);
        const assetFee = assetFeePct > 0 ? amount * assetFeePct : 0;
        const totalAssetDebit = amount + assetFee;
        const requiredGas = gasFee + reserve;
        if (gasId === "" || requiredGas <= 0) return { ok: true };

        const assetBal = parseAssetBalance(assetsById[currentAssetId]?.balance);
        const gasBal = parseAssetBalance(assetsById[gasId]?.balance);

        if (gasId === currentAssetId) {
          return { ok: assetBal + 1e-12 >= totalAssetDebit + requiredGas };
        }
        if (assetBal + 1e-12 < totalAssetDebit) {
          return { ok: false, reason: "asset" };
        }
        return { ok: gasBal + 1e-12 >= requiredGas };
      }

      async function fetchSendQuote(amount) {
        const url = new URL(FEE_QUOTE_API, window.location.href);
        url.searchParams.set("asset_id", currentAssetId);
        url.searchParams.set("network", networkForAsset(currentAssetId));
        url.searchParams.set("amount", String(amount));
        const res = await fetch(url.toString(), {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        return res.json().catch(() => null);
      }

      function applyGasValidation(amount) {
        const local = checkNetworkFeeLocal(amount);
        if (!local.ok) {
          if (local.reason === "asset") {
            hideGasFeeBanner();
            return false;
          }
          insufficientAlert.style.display = "none";
          showGasFeeBanner(NETWORK_FEE_MSG);
          sendBtn.disabled = true;
          return false;
        }
        hideGasFeeBanner();
        return true;
      }

      if (closeSendError) {
        closeSendError.addEventListener("click", hideSendErrorModal);
      }
      if (sendErrorModal) {
        sendErrorModal.addEventListener("click", (e) => {
          if (e.target === sendErrorModal) hideSendErrorModal();
        });
      }

      async function executeSend(amount, assetId, recipient) {
        sendBtn.disabled = true;
        try {
          const res = await fetch(WALLET_API, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
              action: "send",
              csrf_token: walletCsrf,
              asset: assetId,
              crypto_amount: amount,
              to_address: recipient,
              network: networkForAsset(assetId),
            }),
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok || !data.ok) {
            const msg = String(data.error || "Request failed. Please try again.");
            showSendErrorModal(msg);
            return;
          }
          if (data.csrf) walletCsrf = data.csrf;
          hideGasFeeBanner();
          await refreshAssetsFromServer();

          const historyContainer = document.getElementById("transferHistory");
          const first = historyContainer && historyContainer.firstElementChild;
          if (first && first.textContent && first.textContent.includes("No sends yet")) {
            first.remove();
          }

          const d = sendRules[assetId] || { decimals: 6 };
          const iconUrl = String(assetsById[assetId]?.icon_url || "");
          const cryptoIcon = iconUrl
            ? `<img src="${iconUrl}" alt="" style="width:16px;height:16px;object-fit:contain" onerror="this.style.display='none'">`
            : '<i class="fas fa-coins"></i>';
          const shortAddr =
            recipient.length > 14 ? recipient.substring(0, 10) + "..." : recipient;

          const newHistory = document.createElement("div");
          newHistory.className = "history-row";
          newHistory.innerHTML = `
        <div>${cryptoIcon} ${amount.toFixed(d.decimals)} ${String(assetsById[assetId]?.symbol || "").toUpperCase()}</div>
        <div>To: ${shortAddr}</div>
        <div><span class="status-badge status-pending">Pending</span></div>
      `;
          historyContainer.insertBefore(newHistory, historyContainer.firstChild);

          const notif = document.createElement("div");
          notif.className = "notification-popup show";
          notif.style.position = "fixed";
          notif.style.top = "80px";
          notif.style.right = "24px";
          notif.style.background = "var(--surface)";
          notif.style.padding = "12px 20px";
          notif.style.borderRadius = "40px";
          notif.style.borderLeft = `4px solid var(--primary-color)`;
          notif.style.zIndex = "200";
          notif.innerHTML = `✅ Send submitted (pending review). ${amount} ${String(assetsById[assetId]?.symbol || "").toUpperCase()} — balance updated; await admin approval.`;
          document.body.appendChild(notif);
          setTimeout(() => notif.remove(), 4000);

          sendAmount.value = "";
          recipientAddress.value = "";
          calculateFees();
        } catch (e) {
          const em = e && e.message ? String(e.message) : "Network error";
          showSendErrorModal(em);
        } finally {
          calculateFees();
        }
      }

      // Update UI based on selected crypto (USD pool shared with Wallet page)
      function updateCryptoUI() {
        hideGasFeeBanner();
        lastSendQuote = null;
        const rule = sendRules[currentAssetId] || { symbol: "", min: 0, decimals: 6 };
        const a = assetsById[currentAssetId];
        const bal = a ? parseAssetBalance(a.balance) : 0;
        currentBalance = Number.isFinite(bal) ? bal : 0;

        cryptoSuffix.innerText = rule.symbol || (a ? a.symbol : "");
        cryptoBalanceSpan.innerText = assetsLoaded
          ? `Available: ${currentBalance.toLocaleString(undefined, { maximumFractionDigits: 8 })} ${cryptoSuffix.innerText}`
          : "Loading balance…";

        sendAmount.placeholder = `Min: ${rule.min} ${cryptoSuffix.innerText}`;

        if (sendAmount.value) {
          calculateFees();
        } else {
          updateFeeDisplay(0);
        }
      }

      function calculateFees() {
        const data = sendRules[currentAssetId] || { min: 0, decimals: 6 };
        const amount = parseCryptoAmount(sendAmount.value);

        if (isNaN(amount) || amount <= 0) {
          hideGasFeeBanner();
          insufficientAlert.style.display = "none";
          sendBtn.disabled = true;
          updateFeeDisplay(0);
          lastSendQuote = null;
          return { amount: 0, isInsufficient: false };
        }

        updateFeeDisplay(amount);

        if (amount < data.min) {
          hideGasFeeBanner();
          insufficientAlert.style.display = "none";
          sendBtn.disabled = true;
          lastSendQuote = null;
          return { amount, isInsufficient: false };
        }

        const isInsufficient = assetsLoaded && amount > currentBalance;
        if (isInsufficient) {
          hideGasFeeBanner();
          insufficientAlert.style.display = "block";
          insufficientAlert.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Insufficient balance! Please enter a lower amount.`;
          sendBtn.disabled = true;
          lastSendQuote = null;
          return { amount, isInsufficient: true };
        }

        insufficientAlert.style.display = "none";
        if (!applyGasValidation(amount)) {
          lastSendQuote = null;
          return { amount, isInsufficient: false };
        }
        sendBtn.disabled = !assetsLoaded;
        quoteSendFees(amount);
        return { amount, isInsufficient: false };
      }

      async function quoteSendFees(amount) {
        clearTimeout(sendQuoteTimer);
        sendQuoteTimer = setTimeout(async () => {
          try {
            const quote = await fetchSendQuote(amount);
            if (!quote || !quote.ok) {
              applyGasValidation(amount);
              lastSendQuote = null;
              return;
            }
            lastSendQuote = quote;

            if (quote.can_proceed) {
              hideGasFeeBanner();
              insufficientAlert.style.display = "none";
              sendBtn.disabled = false;
            } else if (!quote.ok_gas) {
              insufficientAlert.style.display = "none";
              showGasFeeBanner(NETWORK_FEE_MSG);
              sendBtn.disabled = true;
            } else {
              hideGasFeeBanner();
              insufficientAlert.style.display = "block";
              insufficientAlert.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Insufficient balance! Please enter a lower amount.`;
              sendBtn.disabled = true;
            }
          } catch (_) {
            applyGasValidation(amount);
            lastSendQuote = null;
          }
        }, 200);
      }

      function updateFeeDisplay(amount) {
        const data = sendRules[currentAssetId] || { min: 0, decimals: 6 };
        const px = Number(assetsById[currentAssetId]?.price_usd || 0);
        const usdValue = amount * (Number.isFinite(px) ? px : 0);

        displayAmount.innerText = `${amount.toFixed(data.decimals)} ${cryptoSuffix.innerText}`;
        usdValueSpan.innerText = `$${usdValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USD`;

        if (amount > 0 && amount < data.min) {
          sendBtn.disabled = true;
        }
      }

      // Handle amount input
      sendAmount.addEventListener("input", () => {
        calculateFees();
      });

      // Max button
      maxAmountBtn.addEventListener("click", () => {
        const data = sendRules[currentAssetId] || { decimals: 6 };
        const bal = parseAssetBalance(assetsById[currentAssetId]?.balance);
        if (!Number.isFinite(bal) || bal <= 0) return;
        sendAmount.value = bal.toFixed(Number(data.decimals || 6));
        calculateFees();
      });

      // Crypto selection
      cryptoBtns.forEach((btn) => {
        btn.addEventListener("click", () => {
          cryptoBtns.forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");
          currentAssetId = btn.getAttribute("data-asset");
          updateCryptoUI();
          sendAmount.value = "";
          calculateFees();
        });
      });

      // Contact selection (recent recipients from server)
      document.querySelectorAll("#contactsList .contact-item").forEach((item) => {
        item.addEventListener("click", () => {
          const address = item.getAttribute("data-address");
          const name = item.getAttribute("data-name") || "Address";
          const assetBtn = item.getAttribute("data-asset");
          if (address) {
            recipientAddress.value = address;
          }
          if (assetBtn && sendRules[assetBtn]) {
            currentAssetId = assetBtn;
            cryptoBtns.forEach((b) => {
              b.classList.toggle("active", b.getAttribute("data-asset") === assetBtn);
            });
            sendAmount.value = "";
            updateCryptoUI();
            calculateFees();
          }

          const notif = document.createElement("div");
          notif.className = "notification-popup show";
          notif.style.position = "fixed";
          notif.style.top = "80px";
          notif.style.right = "24px";
          notif.style.background = "var(--surface)";
          notif.style.padding = "12px 20px";
          notif.style.borderRadius = "40px";
          notif.style.borderLeft = `4px solid var(--primary-color)`;
          notif.style.zIndex = "200";
          notif.innerHTML = `📇 ${name} selected`;
          document.body.appendChild(notif);
          setTimeout(() => notif.remove(), 2000);
        });
      });

      // Send button - submit directly (2FA removed)
      sendBtn.addEventListener("click", async () => {
        const amount = parseCryptoAmount(sendAmount.value);
        const data = sendRules[currentAssetId] || { min: 0 };
        const recipient = recipientAddress.value.trim();

        if (!recipient) {
          showSendErrorModal("Please enter a recipient address");
          return;
        }

        if (isNaN(amount) || amount <= 0) {
          showSendErrorModal("Please enter a valid amount");
          return;
        }

        if (amount < data.min) {
          showSendErrorModal(`Minimum send amount is ${data.min} ${cryptoSuffix.innerText}`);
          return;
        }

        if (assetsLoaded && amount > currentBalance) {
          showSendErrorModal("Insufficient balance. Please enter a lower amount.");
          return;
        }

        if (!applyGasValidation(amount)) {
          return;
        }

        try {
          const quote = await fetchSendQuote(amount);
          lastSendQuote = quote;
          if (quote && quote.ok && !quote.ok_gas) {
            showGasFeeBanner(NETWORK_FEE_MSG);
            sendBtn.disabled = true;
            return;
          }
        } catch (_) {
          if (!applyGasValidation(amount)) {
            return;
          }
        }

        executeSend(amount, currentAssetId, recipient);
      });

      // Sidebar functionality
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      const mobileBtn = document.getElementById("mobileMenuBtn");

      collapseBtn.addEventListener("click", () =>
        sidebar.classList.toggle("collapsed"),
      );
      mobileBtn.addEventListener("click", () =>
        sidebar.classList.toggle("mobile-open"),
      );

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

      // Scroll to top
      const scrollBtn = document.getElementById("scrollTop");
      window.addEventListener("scroll", () => {
        if (window.scrollY > 400) scrollBtn.style.display = "flex";
        else scrollBtn.style.display = "none";
      });
      scrollBtn.addEventListener("click", () =>
        window.scrollTo({ top: 0, behavior: "smooth" }),
      );

      const notificationDropdown = document.getElementById("notification-dropdown");
      const notifBtn = document.getElementById("notifications-btn");
      const notifBadge = document.querySelector("#notifications-btn .notification-badge");

      function ndEscapeHtml(s) {
        return String(s ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;");
      }

      async function refreshNotificationBadge() {
        try {
          const res = await fetch("./api/user_notifications_api.php?action=count", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
          });
          const data = await res.json().catch(() => null);
          const n = data && data.ok ? parseInt(data.unread, 10) || 0 : 0;
          if (notifBadge) {
            if (n > 0) {
              notifBadge.textContent = n > 99 ? "99+" : String(n);
              notifBadge.style.display = "inline-flex";
            } else {
              notifBadge.textContent = "";
              notifBadge.style.display = "none";
            }
          }
        } catch (e) {}
      }

      async function renderNotificationDropdown() {
        if (!notificationDropdown) return;
        try {
          const res = await fetch("./api/user_notifications_api.php?limit=25", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
          });
          const data = await res.json().catch(() => null);
          const items = data && data.ok && Array.isArray(data.items) ? data.items : [];
          let html = '<div class="nd-head"><span>Notifications</span><button type="button" class="nd-mark" id="ndMarkAll">Mark all read</button></div>';
          if (!items.length) {
            html += '<div class="notification-item">No notifications yet.</div>';
          } else {
            items.forEach((it) => {
              const unread = !it.is_read;
              html += `<div class="notification-item${unread ? " unread" : ""}" data-nid="${it.id}">
                  <div class="nd-title">${ndEscapeHtml(it.title)}</div>
                  <div class="nd-body">${it.body_html ? it.body_html : ""}</div>
                  <div class="nd-time">${ndEscapeHtml(it.created_at || "")}</div>
                </div>`;
            });
          }
          notificationDropdown.innerHTML = html;
          const markAll = document.getElementById("ndMarkAll");
          if (markAll) {
            markAll.addEventListener("click", async (ev) => {
              ev.stopPropagation();
              await fetch("./api/user_notifications_api.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/json", Accept: "application/json" },
                body: JSON.stringify({ action: "mark_all_read" }),
              });
              await refreshNotificationBadge();
              await renderNotificationDropdown();
            });
          }
          notificationDropdown.querySelectorAll(".notification-item[data-nid]").forEach((el) => {
            el.addEventListener("click", async () => {
              const id = parseInt(el.getAttribute("data-nid"), 10);
              if (!id) return;
              await fetch("./api/user_notifications_api.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/json", Accept: "application/json" },
                body: JSON.stringify({ action: "mark_read", id }),
              });
              el.classList.remove("unread");
              await refreshNotificationBadge();
            });
          });
        } catch (e) {
          notificationDropdown.innerHTML =
            '<div class="notification-item">Could not load notifications.</div>';
        }
      }

      if (notifBtn && notificationDropdown) {
        notifBtn.addEventListener("click", async (e) => {
          e.stopPropagation();
          const open = notificationDropdown.style.display === "block";
          if (open) {
            notificationDropdown.style.display = "none";
          } else {
            await renderNotificationDropdown();
            notificationDropdown.style.display = "block";
          }
        });
        document.addEventListener("click", (e) => {
          if (notificationDropdown.style.display !== "block") return;
          if (!notificationDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
            notificationDropdown.style.display = "none";
          }
        });
      }

      refreshNotificationBadge();
      setInterval(refreshNotificationBadge, 30000);

      document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") refreshAssetsFromServer();
      });

      // Initialize: live wallet assets
      updateCryptoUI();
      refreshAssetsFromServer();
      setInterval(refreshAssetsFromServer, 15000);
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>
