<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_sidebar_state.php';

function wh(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @param array<string,mixed> $row
 */
function wallet_format_txn_title(array $row): string {
  $t = strtolower((string)($row['type'] ?? ''));
  $asset = strtoupper(trim((string)($row['asset'] ?? '')));
  $side = strtolower((string)($row['side'] ?? ''));
  if ($t === 'deposit') {
    return 'Deposit' . ($asset !== '' ? ' · ' . $asset : '');
  }
  if ($t === 'withdrawal') {
    return 'Withdrawal' . ($asset !== '' ? ' · ' . $asset : '');
  }
  if ($t === 'send') {
    return 'Send' . ($asset !== '' ? ' · ' . $asset : '');
  }
  if ($t === 'trade') {
    $verb = $side === 'sell' ? 'Sell' : 'Buy';

    return $verb . ($asset !== '' ? ' · ' . $asset : '');
  }
  if ($t === 'wallet_to_trade') {
    return 'Transfer to trade';
  }
  if ($t === 'receive') {
    return 'Receive' . ($asset !== '' ? ' · ' . $asset : '');
  }

  return ucwords(str_replace('_', ' ', $t)) . ($asset !== '' ? ' · ' . $asset : '');
}

/**
 * @param array<string,mixed> $row
 */
function wallet_txn_icon_class(array $row): string {
  $t = strtolower((string)($row['type'] ?? ''));
  if ($t === 'deposit' || $t === 'receive') {
    return 'fa-solid fa-circle-arrow-down';
  }
  if ($t === 'withdrawal' || $t === 'send') {
    return 'fa-solid fa-circle-arrow-up';
  }
  if ($t === 'trade') {
    return 'fa-solid fa-arrow-right-arrow-left';
  }
  if ($t === 'wallet_to_trade') {
    return 'fa-solid fa-right-left';
  }

  return 'fa-solid fa-receipt';
}

function wallet_txn_icon_color(string $type): string {
  $t = strtolower($type);
  if ($t === 'deposit' || $t === 'receive') {
    return 'var(--positive)';
  }
  if ($t === 'withdrawal' || $t === 'send') {
    return 'var(--negative)';
  }
  if ($t === 'trade') {
    return 'var(--warning-color)';
  }
  if ($t === 'wallet_to_trade') {
    return 'var(--primary-color)';
  }

  return 'var(--text-secondary)';
}

function wallet_txn_status_badge_class(string $status): string {
  $s = strtolower(trim($status));
  if ($s === 'completed' || $s === 'confirmed') {
    return 'status-completed';
  }
  if ($s === 'failed' || $s === 'rejected' || $s === 'cancelled') {
    return 'status-failed';
  }

  return 'status-pending';
}

/**
 * @param array<string,mixed> $row
 */
function wallet_format_txn_amount_display(array $row): string {
  $amt = (string)($row['amount'] ?? '0');
  $asset = strtoupper(trim((string)($row['asset'] ?? '')));
  $n = (float)$amt;
  if (!is_finite($n)) {
    return $amt;
  }
  $type = strtolower((string)($row['type'] ?? ''));
  if (
    $type === 'deposit'
    || $type === 'withdrawal'
    || $type === 'wallet_to_trade'
    || $type === 'send'
    || $type === 'trade'
  ) {
    return '$' . number_format($n, 2, '.', ',');
  }

  return rtrim(rtrim(number_format($n, 8, '.', ''), '0'), '.') . ($asset !== '' ? ' ' . $asset : '');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userFullName = (string)($_SESSION['user_full_name'] ?? '');
$userEmail = (string)($_SESSION['user_email'] ?? '');
$availableBalanceUsd = '0.00';
$tradableBalanceUsd = '0.00';
$walletDisplayBalanceUsd = '0.00';
$mainWalletUsdStr = '0.00';
$cryptoPortfolioUsdStr = '0.00';
$tradeBalanceUsd = '0.00';
$portfolioValueUsd = '0.00';
$volume24hUsd = '0.00';
$walletId = '';
$walletAgg = [
  'cash_usd' => '0.00',
  'crypto_portfolio_usd' => '0.00',
  'total_balance_usd' => '0.00',
];
$recentWalletTxns = [];

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
    $portfolioValueUsd = (string)($row['portfolio_value_usd'] ?? '0.00');
    $volume24hUsd = (string)($row['volume_24h_usd'] ?? '0.00');
  }
  $walletAgg = user_get_wallet_display_aggregates($db, $schema, $userId);
  $mainWalletUsdStr = $walletAgg['cash_usd'];
  $cryptoPortfolioUsdStr = $walletAgg['crypto_portfolio_usd'];
  $walletDisplayBalanceUsd = $walletAgg['total_balance_usd'];
  $tradableBalanceUsd = $mainWalletUsdStr;
  $availableBalanceUsd = $walletAgg['total_balance_usd'];
  $tradeBalanceUsd = user_get_trade_balance_usd($db, $schema, $userId);
  try {
    $p = user_fetch_portfolio($db, $userId);
    if (is_array($p) && isset($p['spot_balance_usd'])) {
      $tradeBalanceUsd = (string)$p['spot_balance_usd'];
    }
  } catch (Throwable $e) {
    error_log('[wallet-portfolio] ' . $e->getMessage());
  }
  ensure_transactions_table($db);
  try {
    $recentWalletTxns = user_fetch_recent_transactions($db, $userId, 10);
  } catch (Throwable $e) {
    error_log('[wallet-recent-tx] ' . $e->getMessage());
    $recentWalletTxns = [];
  }
  $db->close();
} catch (Throwable $e) {
  error_log('[wallet] ' . $e->getMessage());
}

if (!is_array($recentWalletTxns)) {
  $recentWalletTxns = [];
}

$kycSidebarBadge = kyc_sidebar_badge_for_session();
$csrfToken = user_csrf_token();

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);

$walletBootJson = json_encode(
  [
    'wallet_balance_usd' => $walletAgg['total_balance_usd'],
    'total_balance_usd' => $walletAgg['total_balance_usd'],
    'main_wallet_usd' => $mainWalletUsdStr,
    'available_balance_usd' => $walletAgg['total_balance_usd'],
    'tradable_balance_usd' => $mainWalletUsdStr,
    'trade_balance_usd' => $tradeBalanceUsd,
    'crypto_portfolio_usd' => $cryptoPortfolioUsdStr,
    'portfolio_value_usd' => $cryptoPortfolioUsdStr,
    'portfolio_value_db_usd' => $portfolioValueUsd,
    'volume_24h_usd' => $volume24hUsd,
    'wallet_id' => $walletId,
    'csrf' => $csrfToken,
  ],
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
if ($walletBootJson === false) {
  $walletBootJson = '{}';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes"
    />
    <title>Crypterra | Crypto Wallet Dashboard</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 (free) -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    />
    <!-- AOS Animation Library -->
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

      /* Import Google Fonts Inter */
      @import url("https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap");

      /* ========== ROOT COLORS - GREEN THEME (as requested) ========== */
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
        --price-change-up: #22c55e;
        --price-change-down: #f6465d;
        --shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        --shadow-glow: 0 0 30px var(--primary-glow);
        --light-border: #2d2d2d;
        --dark-border: #2d2d2d;
        --light-card: #1e1e1e;
        --dark-card: #1e1e1e;
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
        --price-change-up: #16a34a;
        --price-change-down: #dc2626;
        --shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        --shadow-glow: 0 0 20px rgba(34, 197, 94, 0.4);
        --light-border: #eaeaea;
        --dark-border: #eaeaea;
        --light-card: #ffffff;
        --dark-card: #ffffff;
      }

      body {
        background-color: var(--bg);
        color: var(--text);
        transition:
          background-color 0.3s ease,
          color 0.2s ease;
        overflow-x: hidden;
      }

      /* Apply global color scheme */
      .dashboard {
        display: flex;
        min-height: 100vh;
        min-width: 0;
        max-width: 100%;
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

      .sidebar.collapsed .user-avatar-large {
        margin: 0 auto;
      }

      .sidebar.collapsed .sidebar-user {
        justify-content: center;
        text-align: center;
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

      .collapse-btn:hover {
        background: var(--primary-soft);
        color: var(--primary-color);
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
        box-shadow: 0 2px 8px var(--primary-glow);
      }

      .user-info h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text);
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
        position: relative;
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
        font-weight: 500;
      }

      /* MAIN CONTENT */
      .main-content {
        flex: 1;
        min-width: 0;
        max-width: 100%;
        overflow-x: hidden;
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
        border: 1px solid var(--primary-light);
      }

      .user-name {
        color: var(--text);
        font-size: 1rem;
      }

      .header-right {
        display: flex;
        gap: 16px;
        align-items: center;
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
        position: relative;
        transition: 0.2s;
        color: var(--text-secondary);
        border: 1px solid var(--light-border);
      }

      .icon-button:hover {
        background: var(--primary-soft);
        color: var(--primary-color);
        border-color: var(--primary-light);
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
        border: 2px solid var(--bg);
        display: none;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
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
        border: 1px solid var(--light-border);
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

      .dashboard-content {
        padding: 24px 28px;
        /* Clear fixed top bar (greeting + optional wallet line) */
        padding-top: 92px;
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
      }

      .welcome-section {
        background: linear-gradient(
          120deg,
          var(--surface),
          var(--bg-secondary)
        );
        border-radius: 24px;
        padding: 18px 24px;
        margin-bottom: 24px;
        border: 1px solid var(--glass-edge);
        margin-top: 0;
      }

      .moving-text {
        font-weight: 600;
        color: var(--text);
      }

      .wallet-id {
        background: var(--card-bg);
        padding: 12px 18px;
        border-radius: 60px;
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
        margin-bottom: 28px;
        font-size: 0.9rem;
        backdrop-filter: blur(4px);
        border: 1px solid var(--glass-edge);
        max-width: 100%;
      }

      .wallet-id span#wallet-id {
        word-break: break-all;
        overflow-wrap: anywhere;
        min-width: 0;
      }

      .copy-btn {
        background: none;
        border: none;
        color: var(--primary-color);
        cursor: pointer;
        font-size: 1rem;
        transition: 0.2s;
      }

      .balance-section {
        background: linear-gradient(
          145deg,
          var(--surface),
          var(--bg-secondary)
        );
        border-radius: 32px;
        padding: 28px;
        margin-bottom: 32px;
        border: 1px solid var(--glass-edge);
        backdrop-filter: blur(4px);
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
      }

      .balance-header {
        display: flex;
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
        min-width: 0;
        width: 100%;
      }

      .balance-title {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--text-secondary);
      }

      .balance-header > div:last-child {
        min-width: 0;
        width: 100%;
      }

      .balance-amount {
        font-size: clamp(1.5rem, 5vw, 3rem);
        font-weight: 700;
        line-height: 1.15;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
        background: linear-gradient(
          to right,
          var(--text),
          var(--primary-light)
        );
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      .balance-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        margin-top: 28px;
      }

      .action-button {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: var(--text-secondary);
        transition: 0.2s;
      }

      .action-button:hover .action-icon {
        background: var(--primary-color);
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 8px 18px var(--primary-glow);
      }

      .action-icon {
        background: var(--surface);
        width: 48px;
        height: 48px;
        border-radius: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: all 0.2s;
        border: 1px solid var(--glass-edge);
      }

      .stat-grid {
        display: grid;
        grid-template-columns: repeat(
          auto-fit,
          minmax(min(100%, 200px), 1fr)
        );
        gap: 24px;
        margin-bottom: 40px;
        min-width: 0;
        width: 100%;
      }

      .stat-grid > .glass-card {
        min-width: 0;
      }

      .glass-card {
        background: var(--card-bg);
        backdrop-filter: blur(4px);
        border: 1px solid var(--glass-edge);
        border-radius: 24px;
        padding: 20px;
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
        transition:
          transform 0.2s,
          box-shadow 0.2s;
      }

      .glass-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-glow);
        border-color: var(--primary-light);
      }

      .value-large {
        font-size: clamp(1.1rem, 2.5vw, 2rem);
        font-weight: 700;
        margin: 12px 0;
        color: var(--text);
        line-height: 1.2;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: 100%;
      }

      .price-change.positive {
        color: var(--price-change-up);
        font-weight: 600;
      }

      .price-change.negative {
        color: var(--price-change-down);
        font-weight: 600;
      }

      .price-change.neutral {
        color: var(--text-muted);
        font-weight: 600;
      }

      .assets-tabs {
        display: flex;
        gap: 24px;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--glass-edge);
        padding-bottom: 12px;
      }

      .tab {
        cursor: pointer;
        opacity: 0.7;
        font-weight: 500;
        color: var(--text-secondary);
        transition: 0.2s;
      }

      .tab.active {
        opacity: 1;
        color: var(--primary-color);
        border-bottom: 2px solid var(--primary-color);
      }

      .assets-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 28px;
      }

      .asset-row {
        display: flex;
        flex-direction: column;
        padding: 14px 0;
        border-bottom: 1px solid var(--glass-edge);
      }

      .asset-row-top,
      .asset-row-bottom {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 14px;
      }

      .asset-row-top {
        align-items: center;
      }

      .asset-row-right {
        text-align: right;
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex-shrink: 0;
      }

      .asset-row-balance {
        font-size: 0.8rem;
        color: var(--text-muted);
        font-family: monospace;
      }

      .asset-row-price {
        font-weight: 700;
        color: var(--text);
      }

      .two-col-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
        gap: 24px;
        margin-top: 20px;
        min-width: 0;
        width: 100%;
      }

      .transaction-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-top: 16px;
      }

      .transaction-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.9rem;
        padding: 10px 0;
      }

      .status-badge {
        font-size: 0.7rem;
        padding: 4px 10px;
        border-radius: 40px;
      }

      .status-completed {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary-light);
      }

      .status-pending {
        background: rgba(255, 167, 38, 0.2);
        color: var(--warning-color);
      }

      .status-failed {
        background: rgba(239, 83, 80, 0.2);
        color: var(--negative);
      }

      .security-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-top: 20px;
        background: var(--primary-soft);
        padding: 14px;
        border-radius: 20px;
        border-left: 3px solid var(--primary-color);
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

      #scrollTop:hover {
        transform: scale(1.05);
        background: var(--primary-dark);
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
        .two-col-grid {
          grid-template-columns: 1fr;
        }
        .top-bar {
          padding: 10px 12px;
          align-items: center;
        }
        .header-left {
          gap: 10px;
        }
        .dashboard-content {
          padding: 12px;
          padding-top: 88px;
        }
        .balance-section {
          padding: 18px 16px;
        }
        .stat-grid {
          grid-template-columns: 1fr;
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
          <div class="user-avatar-large"><?php echo wh($ub['avatar_letter']); ?></div>
          <div class="user-info">
            <h4><?php echo wh($ub['full_name']); ?></h4>
            <p><?php echo wh($ub['wallet_short']); ?></p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section">MAIN</div>
          <a href="./userprofile.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a
          >
          <a href="./trade.php" class="sidebar-link"
            ><i class="fa-solid fa-user"></i> <span>Trade</span></a
          >
          <a href="./wallet.php" class="sidebar-link active"
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
          <a href="./send.php" class="sidebar-link"
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
              <div class="user-avatar"><?php echo wh($ub['avatar_letter']); ?></div>
              <div class="user-profile-meta">
                <h3 class="user-name">Hello, <?php echo wh($ub['full_name']); ?></h3>
              </div>
            </a>
          </div>
          <div class="header-right">
            <div class="icon-button" id="notifications-btn">
              <i class="fa-solid fa-bell"></i
              ><span class="notification-badge"></span>
            </div>
            <div class="theme-toggle" id="theme-toggle"></div>
          </div>
        </div>
        <div class="notification-dropdown" id="notification-dropdown" style="display: none" role="dialog" aria-label="Notifications"></div>
        <div class="dashboard-content">
          <div class="welcome-section" data-aos="fade-up">
            <div class="moving-text">Welcome to Your Dashboard</div>
          </div>
          <div class="wallet-id">
            <p>Wallet ID</p>
            <span id="wallet-id"><?php echo wh($walletId !== '' ? $walletId : '—'); ?></span
            ><button class="copy-btn" id="copy-wallet-btn">
              <i class="fas fa-copy"></i>
            </button>
          </div>
          <!-- Balance Section -->
          <div class="balance-section" data-aos="fade-up">
            <div class="balance-header">
              <div class="balance-title">Total balance (USD)</div>
              <div>
                <div class="balance-amount" id="mainBalanceDisplay">$<?php echo wh(number_format((float)$walletDisplayBalanceUsd, 2, '.', ',')); ?></div>
                <!--<div class="crypto-details" style="margin-top: 6px; font-size: 0.85rem; color: var(--text-muted)">-->
                <!--  Cash (USD): $<?php echo wh(number_format((float)$mainWalletUsdStr, 2, '.', ',')); ?> · Crypto (est.): $<?php echo wh(number_format((float)$cryptoPortfolioUsdStr, 2, '.', ',')); ?>-->
                <!--</div>-->
                <!--<div class="crypto-details" id="tradableBalanceHint" style="margin-top: 6px">-->
                <!--  Withdrawable USD cash (transfer to trade / withdraw): $<?php echo wh(number_format((float)$tradableBalanceUsd, 2, '.', ',')); ?>-->
                <!--</div>-->
                <div class="crypto-details" id="tradeBalanceHint" style="margin-top: 4px">
                  Trading account: $<?php echo wh(number_format((float)$tradeBalanceUsd, 2, '.', ',')); ?>
                </div>
                <div class="crypto-details" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center">
                  <input
                    type="number"
                    id="transferToTradeAmount"
                    min="0.01"
                    step="0.01"
                    placeholder="Amount (USD)"
                    style="max-width: 140px; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--light-border); background: var(--surface); color: var(--text)"
                  />
                  <!-- <button type="button" id="transferToTradeBtn" class="copy-btn" style="cursor: pointer">
                    <i class="fas fa-right-left"></i> Transfer to Trade
                  </button>
                  <span id="transferToTradeMsg" style="font-size: 0.85rem; color: var(--text-muted)"></span> -->
                </div>
                <div class="crypto-details">
                  <span>USDT Price: $1.00</span
                  ><span class="price-change positive">↑ 0.02%</span>
                </div>
              </div>
            </div>
            <div class="balance-actions">
              <a href="./send.php" class="action-button"
                ><div class="action-icon">
                  <i class="fas fa-paper-plane"></i>
                </div>
                <div class="action-label">Send</div></a
              >
              <a href="./receive.php" class="action-button"
                ><div class="action-icon">
                  <i class="fas fa-arrow-down"></i>
                </div>
                <div class="action-label">Receive</div></a
              >
              <a href="./kycverification.php" class="action-button"
                ><div class="action-icon"><i class="fas fa-id-card"></i></div>
                <div class="action-label">KYC</div></a
              >
                            <a href="#" class="action-button"
                ><div class="action-icon"><i class="fas fa-arrow-up"></i></div>
                <div class="action-label">Withdraw</div></a
              >
              <a href="./deposit.php" class="action-button"
                ><div class="action-icon">
                  <i class="fas fa-arrow-down"></i>
                </div>
                <div class="action-label">Deposit</div></a
              >
            </div>
          </div>
          <!-- stat grid -->
          <div class="stat-grid">
            <div class="glass-card" data-aos="zoom-in">
              <h4>Crypto holdings (USD)</h4>
              <div class="value-large" id="portfolioValue">$<?php echo wh(number_format((float)$cryptoPortfolioUsdStr, 2, '.', ',')); ?></div>
              <div style="display: flex; gap: 1rem; margin-top: 1rem">
                <span class="price-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +2.31%</span
                ><span class="price-change negative"
                  ><i class="fa-solid fa-arrow-down"></i> -0.8%</span
                >
              </div>
            </div>
            <div class="glass-card" data-aos="zoom-in" data-aos-delay="50">
              <h4>24h Volume</h4>
              <div class="value-large" id="volume24hDisplay">$<?php echo wh(number_format((float)$volume24hUsd, 2, '.', ',')); ?></div>
              <p style="color: var(--text-muted); margin-top: 1rem">
                +42 trades
              </p>
            </div>
            <div class="glass-card" data-aos="zoom-in" data-aos-delay="100">
              <h4>Main wallet (USD cash)</h4>
              <div class="value-large" id="walletBalanceCard">$<?php echo wh(number_format((float)$mainWalletUsdStr, 2, '.', ',')); ?></div>
              <p style="color: var(--text-muted); margin-top: 1rem">
                Same as dashboard — withdraw &amp; transfer to trade
              </p>
            </div>
          </div>
          <!-- ASSETS SECTION -->
          <div class="assets-section" data-aos="fade-up">
            <div class="assets-tabs">
              <span class="tab active" data-tab="all">All</span
              ><span class="tab" data-tab="nft">NFT</span
              ><span class="tab" data-tab="gainers">Gainers</span
              ><span class="tab" data-tab="losers">Losers</span>
            </div>
            <div class="assets-list" id="assets-list"></div>
          </div>
          <!-- two column -->
          <div class="two-col-grid">
            <div class="glass-card" data-aos="fade-right">
              <h3>Recent Transactions</h3>
              <div class="transaction-list">
                <?php if (count($recentWalletTxns) === 0) { ?>
                <div
                  class="transaction-item"
                  style="border: none; justify-content: center; color: var(--text-muted); padding: 16px 8px"
                >
                  No transactions yet. Deposits, withdrawals, sends, and trades will appear here.
                </div>
                <?php } else { ?>
                <?php foreach ($recentWalletTxns as $tx) {
                  $st = (string)($tx['status'] ?? 'pending');
                  $stLabel = ucfirst($st);
                  $created = (string)($tx['created_at'] ?? '');
                  $ts = $created !== '' ? @strtotime($created) : false;
                  $dateLine = $ts ? date('M j, Y g:i A', $ts) : '';
                  ?>
                <div class="transaction-item">
                  <div>
                    <i
                      class="<?php echo wh(wallet_txn_icon_class($tx)); ?>"
                      style="color: <?php echo wh(wallet_txn_icon_color((string)($tx['type'] ?? ''))); ?>"
                    ></i>
                    <?php echo wh(wallet_format_txn_title($tx)); ?>
                    <?php if ($dateLine !== '') { ?>
                    <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 4px">
                      <?php echo wh($dateLine); ?>
                    </div>
                    <?php } ?>
                  </div>
                  <div><?php echo wh(wallet_format_txn_amount_display($tx)); ?></div>
                  <div class="status-badge <?php echo wh(wallet_txn_status_badge_class($st)); ?>"><?php echo wh($stLabel); ?></div>
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
      let walletCsrf =
        (window.__WALLET_BOOT && window.__WALLET_BOOT.csrf) || "";

      function formatUsd(n) {
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

      async function syncWalletFromServer() {
        try {
          const res = await fetch(WALLET_API, {
            method: "GET",
            credentials: "same-origin",
          });
          const data = await res.json();
          if (!data.ok || !data.wallet) return;
          if (data.csrf) walletCsrf = data.csrf;
          const w = data.wallet;
          const cashUsd =
            typeof w.main_wallet_usd === "string" ? w.main_wallet_usd : "0";
          const totalUsd =
            typeof w.total_balance_usd === "string"
              ? w.total_balance_usd
              : w.wallet_balance_usd ?? w.available_balance_usd ?? "0";
          const trad = w.tradable_balance_usd ?? cashUsd;
          const tradeB = w.trade_balance_usd ?? "0";
          const crypto =
            typeof w.crypto_portfolio_usd === "string"
              ? w.crypto_portfolio_usd
              : w.portfolio_value_usd ?? "0";
          const vol = w.volume_24h_usd ?? "0";
          const elMain = document.getElementById("mainBalanceDisplay");
          const elCard = document.getElementById("walletBalanceCard");
          const elPort = document.getElementById("portfolioValue");
          const elVol = document.getElementById("volume24hDisplay");
          const elHint = document.getElementById("tradableBalanceHint");
          const elTrade = document.getElementById("tradeBalanceHint");
          if (elMain) elMain.textContent = formatUsd(totalUsd);
          if (elCard) elCard.textContent = formatUsd(cashUsd);
          if (elHint) {
            elHint.textContent = "Withdrawable main wallet: " + formatUsd(trad);
          }
          if (elTrade) {
            elTrade.textContent = "Trading account: " + formatUsd(tradeB);
          }
          if (elPort) elPort.textContent = formatUsd(crypto);
          if (elVol) elVol.textContent = formatUsd(vol);
          const wid = document.getElementById("wallet-id");
          if (wid && w.wallet_id) wid.textContent = w.wallet_id;
        } catch (_) {
          /* keep server-rendered values */
        }
      }

      let transferInFlight = false;
      document.getElementById("transferToTradeBtn")?.addEventListener("click", async () => {
        const msgEl = document.getElementById("transferToTradeMsg");
        const amtEl = document.getElementById("transferToTradeAmount");
        if (transferInFlight) return;
        const raw = amtEl ? String(amtEl.value || "").trim() : "";
        const amt = parseFloat(raw);
        if (!Number.isFinite(amt) || amt < 0.01) {
          if (msgEl) {
            msgEl.textContent = "Enter at least $0.01.";
            msgEl.style.color = "var(--danger-color)";
          }
          return;
        }
        transferInFlight = true;
        if (msgEl) msgEl.textContent = "Processing…";
        try {
          const res = await fetch(WALLET_API, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({
              action: "transfer_to_trade",
              amount_usd: amt,
              csrf_token: walletCsrf,
            }),
          });
          const data = await res.json();
          if (data.csrf) walletCsrf = data.csrf;
          if (data && data.ok) {
            if (msgEl) {
              msgEl.textContent = "Transfer completed.";
              msgEl.style.color = "var(--primary-color)";
            }
            if (amtEl) amtEl.value = "";
            await syncWalletFromServer();
          } else {
            if (msgEl) {
              msgEl.textContent = data.error || "Transfer failed.";
              msgEl.style.color = "var(--danger-color)";
            }
          }
        } catch (_) {
          if (msgEl) {
            msgEl.textContent = "Network error. Try again.";
            msgEl.style.color = "var(--danger-color)";
          }
        } finally {
          transferInFlight = false;
        }
      });

      // Sidebar collapse & mobile
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

      // Copy wallet ID
      const copyBtn = document.getElementById("copy-wallet-btn");
      const walletIdSpan = document.getElementById("wallet-id");
      copyBtn.addEventListener("click", () => {
        navigator.clipboard.writeText(walletIdSpan.innerText);
        const original = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="fas fa-check"></i>';
        setTimeout(() => (copyBtn.innerHTML = original), 1200);
      });

      let cachedWalletAssets = [];
      let currentWalletTab = "all";

      function applyWalletAssetTabFilter(container) {
        if (!container) return;
        const tab = currentWalletTab || "all";
        const rows = Array.from(
          container.querySelectorAll(".asset-row[data-asset-id]"),
        );
        const byId = new Map(
          rows.map((r) => [r.getAttribute("data-asset-id"), r]),
        );
        let order = [];
        if (tab === "all") {
          order = cachedWalletAssets
            .filter((a) => !a.is_nft)
            .map((a) => String(a.id))
            .filter(Boolean);
        } else if (tab === "nft") {
          order = cachedWalletAssets
            .filter((a) => a.is_nft)
            .map((a) => String(a.id));
        } else if (tab === "gainers") {
          order = cachedWalletAssets
            .filter(
              (a) =>
                !a.is_nft && Number(a.change_24h_pct || 0) > 0,
            )
            .sort(
              (a, b) =>
                Number(b.change_24h_pct || 0) - Number(a.change_24h_pct || 0),
            )
            .map((a) => String(a.id));
        } else if (tab === "losers") {
          order = cachedWalletAssets
            .filter(
              (a) =>
                !a.is_nft && Number(a.change_24h_pct || 0) < 0,
            )
            .sort(
              (a, b) =>
                Number(a.change_24h_pct || 0) - Number(b.change_24h_pct || 0),
            )
            .map((a) => String(a.id));
        }
        rows.forEach((r) => {
          r.style.display = "none";
        });
        order.forEach((id) => {
          const el = byId.get(id);
          if (el) {
            el.style.display = "";
            container.appendChild(el);
          }
        });
      }

      async function renderAssets(tabKey) {
        if (typeof tabKey === "string" && tabKey !== "") {
          currentWalletTab = tabKey;
        }
        const container = document.getElementById("assets-list");
        if (!container) return;
        try {
          const res = await fetch("./api/assets_api.php", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
          });
          const data = await res.json();
          if (!data || !data.ok || !Array.isArray(data.assets)) return;
          const items = data.assets;
          cachedWalletAssets = items;

          const byId = new Map();
          container.querySelectorAll(".asset-row[data-asset-id]").forEach((el) => {
            byId.set(el.getAttribute("data-asset-id"), el);
          });

          items.forEach((asset) => {
            const id = String(asset.id ?? "");
            if (!id) return;
            const change = Number(asset.change_24h_pct || 0);
            const bal = Number(asset.balance || 0);
            const priceUsd = Number(asset.price_usd || 0);
            const iconUrl = String(asset.icon_url || "");

            let row = byId.get(id);
            if (!row) {
              row = document.createElement("div");
              row.className = "asset-row";
              row.setAttribute("data-asset-id", id);
              row.innerHTML = `
                <div class="asset-row-top">
                  <div class="asset-row-left">
                    <strong style="display:flex;align-items:center;gap:10px">
                      <img data-role="icon" src="${iconUrl}" alt="" style="width:18px;height:18px;object-fit:contain" onerror="this.style.display='none'">
                      <span data-role="name"></span>
                    </strong>
                    <div data-role="network" style="font-size:0.75rem; color:var(--text-muted)"></div>
                  </div>
                  <div class="asset-row-right">
                    <div data-role="symbol" style="font-weight:700"></div>
                    <div class="asset-row-balance" data-role="balance"></div>
                  </div>
                </div>
                <div class="asset-row-bottom" style="margin-top:8px">
                  <span class="price-change" data-role="change"></span>
                  <span class="asset-row-price" data-role="price"></span>
                </div>
              `;
              container.appendChild(row);
              byId.set(id, row);
            }

            row.setAttribute("data-is-nft", asset.is_nft ? "1" : "0");

            const nameEl = row.querySelector('[data-role="name"]');
            const netEl = row.querySelector('[data-role="network"]');
            const symEl = row.querySelector('[data-role="symbol"]');
            const balEl = row.querySelector('[data-role="balance"]');
            const chEl = row.querySelector('[data-role="change"]');
            const prEl = row.querySelector('[data-role="price"]');

            if (nameEl) nameEl.textContent = String(asset.name || "");
            if (netEl) netEl.textContent = String(asset.network || "");
            if (symEl) symEl.textContent = String(asset.symbol || "");
            if (balEl) {
              balEl.textContent = `${bal.toLocaleString(undefined, { maximumFractionDigits: 8 })} ${String(asset.symbol || "")}`.trim();
            }

            if (chEl) {
              chEl.classList.remove("positive", "negative", "neutral");
              if (change > 0) {
                chEl.classList.add("positive");
                chEl.textContent = `↑ ${change.toFixed(2)}%`;
              } else if (change < 0) {
                chEl.classList.add("negative");
                chEl.textContent = `↓ ${Math.abs(change).toFixed(2)}%`;
              } else {
                chEl.classList.add("neutral");
                chEl.textContent = "0.00%";
              }
            }

            if (prEl) {
              prEl.textContent =
                "≈ $" +
                priceUsd.toLocaleString(undefined, {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                });
            }
          });

          items.forEach((asset) => {
            const id = String(asset.id ?? "");
            if (!id) return;
            const row = byId.get(id);
            if (row) container.appendChild(row);
          });

          if (data.portfolio_value_usd != null) {
            const pv = Number(data.portfolio_value_usd || 0);
            const elPort = document.getElementById("portfolioValue");
            if (elPort) {
              elPort.textContent =
                "$" +
                pv.toLocaleString(undefined, {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                });
            }
          }

          applyWalletAssetTabFilter(container);
        } catch (_) {
          /* ignore */
        }
      }
      renderAssets("all");
      setInterval(() => {
        renderAssets();
      }, 5000);

      const tabs = document.querySelectorAll(".assets-tabs .tab");
      tabs.forEach((tab) => {
        tab.addEventListener("click", () => {
          tabs.forEach((t) => t.classList.remove("active"));
          tab.classList.add("active");
          const type = tab.getAttribute("data-tab") || "all";
          renderAssets(type);
        });
      });

      syncWalletFromServer();
      document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "visible") syncWalletFromServer();
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

      document.querySelectorAll(".action-button").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          const label = btn.querySelector(".action-label")?.innerText;
          if (label === "Send") alert("Send crypto feature (demo)");
          else if (label === "Receive")
            alert("Your receiving address: 0x7F3...aB8d");
          else if (label === "KYC")
            alert("KYC verification pending, upload documents.");
        });
      });
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>
