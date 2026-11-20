<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Parse USD amount strings safely. PHP (float)"242,964.45" yields 242.0 — commas must be stripped first.
 */
function userprofile_parse_usd_string(string $s): float {
  $s = trim($s);
  if ($s === '') {
    return 0.0;
  }
  $s = str_replace([',', ' ', '$', "\xC2\xA0"], '', $s);
  $v = (float)$s;
  return is_finite($v) && $v >= 0 ? $v : 0.0;
}

/**
 * Dashboard subtitle under total balance: wallet-only, trade-only, or both (with links).
 */
function userprofile_balance_hints_html(string $mainStr, string $tradeStr, string $cryptoStr = '0.00'): string {
  $m = userprofile_parse_usd_string($mainStr);
  $t = userprofile_parse_usd_string($tradeStr);
  $c = userprofile_parse_usd_string($cryptoStr);
  $hm = number_format($m, 2, '.', '');
  $ht = number_format($t, 2, '.', '');
  $hc = number_format($c, 2, '.', '');
  $linkW = '<a href="./wallet.php" style="color:var(--primary-color);text-decoration:none;font-weight:600">Wallet</a>';
  $linkT = '<a href="./trade.php" style="color:var(--primary-color);text-decoration:none;font-weight:600">Trade</a>';
  if ($m <= 0 && $t <= 0 && $c > 0) {
    return '<div>Crypto holdings: <strong>$' . h($hc) . '</strong> · ' . $linkW . '</div>';
  }
  if ($m > 0 && $t <= 0) {
    return '<div>Main wallet: <strong>$' . h($hm) . '</strong> · ' . $linkW . '</div>';
  }
  if ($m <= 0 && $t > 0) {
    return '<div>Trading account: <strong>$' . h($ht) . '</strong> · ' . $linkT . '</div>';
  }
  if ($m > 0 && $t > 0) {
    return '<div>Main wallet: <strong>$' . h($hm) . '</strong> · ' . $linkW . '</div>'
      . '<div style="margin-top:6px">Trading account: <strong>$' . h($ht) . '</strong> · ' . $linkT . '</div>';
  }
  return '<div>Deposit or transfer funds to get started.</div>';
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userFullName = (string)($_SESSION['user_full_name'] ?? '');
$userEmail = (string)($_SESSION['user_email'] ?? '');
$walletId = '';
$availableBalanceUsd = '0.00';
$tradableBalanceUsd = '0.00';
$walletDisplayBalanceUsd = '0.00';
$portfolioValueUsd = '0.00';
$totalPortfolioDisplayUsd = '0.00';
$volume24hUsd = '0.00';
$twofaEnabled = false;
$mainWalletUsdStr = '0.00';
$tradeBalanceUsdStr = '0.00';
$totalCashUsd = 0.0;

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
    $userFullName = $row['full_name'];
    $userEmail = $row['email'];
    $_SESSION['user_full_name'] = $userFullName;
    $_SESSION['user_email'] = $userEmail;

    $walletId = is_string($row['wallet_id'] ?? null) ? (string)$row['wallet_id'] : '';
    if ($walletId === '') {
      $walletId = user_ensure_wallet_id($db, $userId);
    }
    $volume24hUsd = (string)($row['volume_24h_usd'] ?? '0.00');
  }
  $secState = user_fetch_security_state($db, $schema, $userId);
  $twofaEnabled = (bool)($secState['twofa_enabled'] ?? false);
  // Same as wallet.php: cash + crypto (assets), not cash column alone — then add trading account.
  $walletAgg = user_get_wallet_display_aggregates($db, $schema, $userId);
  $mainWalletUsdStr = $walletAgg['cash_usd'];
  $tradeBalanceUsdStr = user_get_trade_balance_usd($db, $schema, $userId);
  $db->close();
  $portfolioValueUsd = $walletAgg['crypto_portfolio_usd'];
  $cryptoPortfolioUsd = userprofile_parse_usd_string($portfolioValueUsd);
  $walletSubtotalUsd = userprofile_parse_usd_string($walletAgg['total_balance_usd']);
  // Total balance (hero): (main wallet cash + crypto holdings) + trading account USD.
  $totalCashUsd = $walletSubtotalUsd + userprofile_parse_usd_string($tradeBalanceUsdStr);
  if (!is_finite($totalCashUsd) || $totalCashUsd < 0) {
    $totalCashUsd = 0.0;
  }
  $walletDisplayBalanceUsd = number_format($totalCashUsd, 2, '.', '');
  // Total Portfolio Value: same grand total (wallet + trade, incl. crypto in wallet).
  $totalPortfolioUsd = $totalCashUsd;
  if (!is_finite($totalPortfolioUsd) || $totalPortfolioUsd < 0) {
    $totalPortfolioUsd = 0.0;
  }
  $totalPortfolioDisplayUsd = number_format($totalPortfolioUsd, 2, '.', '');
  $tradableBalanceUsd = $mainWalletUsdStr;
  $availableBalanceUsd = $walletDisplayBalanceUsd;
} catch (Throwable $e) {
  error_log('[userprofile] ' . $e->getMessage());
}

require_once __DIR__ . '/includes/kyc_sidebar_state.php';
$kycSidebarBadge = kyc_sidebar_badge_for_session();
$kycProfileCard = kyc_profile_card_for_session();

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);
$avatarLetter = $ub['avatar_letter'];

$isAjaxStats = isset($_GET['ajax']) && $_GET['ajax'] === 'stats';
if ($isAjaxStats) {
  header('Content-Type: application/json; charset=utf-8');
  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    $schema = (string)$DB_NAME;
    ensure_user_dashboard_schema($db, $schema);
    $wId = $walletId;
    $vol = $volume24hUsd;
    $rowS = user_fetch_by_id($db, $userId);
    if (is_array($rowS)) {
      $wId = is_string($rowS['wallet_id'] ?? null) ? (string)$rowS['wallet_id'] : $wId;
      if ($wId === '') {
        $wId = user_ensure_wallet_id($db, $userId);
      }
      $vol = (string)($rowS['volume_24h_usd'] ?? $vol);
    }
    $walletAgg = user_get_wallet_display_aggregates($db, $schema, $userId);
    $tradeBal = user_get_trade_balance_usd($db, $schema, $userId);
    $db->close();
    $mainF = round(userprofile_parse_usd_string((string)$walletAgg['cash_usd']), 2);
    $tradeF = round(userprofile_parse_usd_string((string)$tradeBal), 2);
    $cryptoF = round(userprofile_parse_usd_string((string)$walletAgg['crypto_portfolio_usd']), 2);
    $walletSubF = round(userprofile_parse_usd_string((string)$walletAgg['total_balance_usd']), 2);
    $totalCash = $walletSubF + $tradeF;
    if (!is_finite($totalCash) || $totalCash < 0) {
      $totalCash = 0.0;
    }
    $cashF = round($totalCash, 2);
    $totalPortF = $cashF;
    echo json_encode([
      'ok' => true,
      'user' => [
        'id' => $userId,
        'full_name' => $userFullName,
        'email' => $userEmail,
        'avatar_letter' => $avatarLetter,
      ],
      'wallet' => [
        'wallet_id' => $wId,
        'main_wallet_usd' => $mainF,
        'trade_balance_usd' => $tradeF,
        'wallet_subtotal_usd' => $walletSubF,
        'cash_total_usd' => $cashF,
        'total_balance_usd' => $cashF,
        'wallet_balance_usd' => $mainF,
        'available_balance_usd' => $cashF,
        'tradable_balance_usd' => $mainF,
        'crypto_holdings_usd' => $cryptoF,
        'portfolio_value_usd' => $cryptoF,
        'total_portfolio_usd' => $totalPortF,
        'volume_24h_usd' => round(userprofile_parse_usd_string((string)$vol), 2),
      ],
      'ts' => time(),
    ], JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    error_log('[userprofile stats] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'stats'], JSON_UNESCAPED_SLASHES);
  }
  exit();
}

$isAjaxTx = isset($_GET['ajax']) && $_GET['ajax'] === 'transactions';
if ($isAjaxTx) {
  header('Content-Type: application/json; charset=utf-8');
  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    ensure_transactions_table($db);
    $kind = isset($_GET['kind']) && is_string($_GET['kind']) ? strtolower(trim($_GET['kind'])) : '';
    if ($kind === 'deposit') {
      $tx = user_fetch_recent_deposits($db, $userId, 8);
    } else {
      $tx = user_fetch_recent_transactions($db, $userId, 6);
    }
    $db->close();

    echo json_encode(['ok' => true, 'transactions' => $tx, 'ts' => time()], JSON_UNESCAPED_SLASHES);
    exit();
  } catch (Throwable $e) {
    error_log('[userprofile-tx] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'transactions' => [], 'ts' => time()], JSON_UNESCAPED_SLASHES);
    exit();
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes"
    />
    <title>Crypterra · User Profile Dashboard</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 (free) -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    />
    <!-- Google Fonts Inter -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&display=swap"
      rel="stylesheet"
    />
    <!-- AOS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <style>
      /* ========== RESET & VARIABLES ========== */
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      :root {
        --primary-color: #22c55e;
        --primary-light: #4ade80;
        --primary-dark: #16a34a;
        --primary-glow: rgba(34, 197, 94, 0.5);
        --primary-soft: rgba(34, 197, 94, 0.15);
        --danger-color: #ef5350;
        --warning-color: #ffa726;
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
        /* 24h % (Binance / Trust Wallet style: up = blue, down = red) */
        --price-change-up: #3b82f6;
        --price-change-down: #f6465d;
        --shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        --shadow-glow: 0 0 30px var(--primary-glow);
        --border: #2d2d2d;
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
        --positive: #2ecc71;
        --negative: #e74c3c;
        --price-change-up: #2563eb;
        --price-change-down: #dc2626;
        --shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        --border: #eaeaea;
      }

      body {
        font-family: "Inter", "Segoe UI", sans-serif;
        background: var(--bg-secondary);
        color: var(--text);
        line-height: 1.5;
        transition:
          background 0.3s,
          color 0.2s;
        overflow-x: hidden;
        min-height: 100vh;
      }

      /* ========== DASHBOARD LAYOUT ========== */
      .dashboard {
        display: flex;
        min-height: 100vh;
        position: relative;
      }

      /* ========== SIDEBAR - FIXED POSITION, NO SCROLL ========== */
      .sidebar {
        width: 280px;
        background: rgba(10, 20, 35, 0.85);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border-right: 1px solid var(--glass-edge);
        padding: 2rem 1.2rem;
        transition:
          width 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1),
          left 0.3s;
        display: flex;
        flex-direction: column;
        z-index: 100;
        box-shadow: 5px 0 30px -10px rgba(0, 0, 0, 0.5);
        /* CRITICAL: sidebar stays fixed and does NOT scroll */
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto; /* Allow sidebar content to scroll if needed, but sidebar itself stays fixed */
        scrollbar-width: thin;
      }

      .sidebar::-webkit-scrollbar {
        width: 4px;
      }

      .sidebar::-webkit-scrollbar-track {
        background: transparent;
      }

      .sidebar::-webkit-scrollbar-thumb {
        background: var(--primary-color);
        border-radius: 4px;
      }

      body.light .sidebar {
        background: rgba(255, 255, 255, 0.85);
      }

      .sidebar.collapsed {
        width: 95px;
      }

      .sidebar.collapsed .sidebar-link span:not(.action-badge),
      .sidebar.collapsed .sidebar-header span,
      .sidebar.collapsed .user-info {
        display: none;
      }

      .sidebar.collapsed .kyc-sidebar-badge {
        display: none;
      }

      .sidebar.collapsed .sidebar-link.sidebar-link--has-badge {
        justify-content: center;
      }

      /* MOBILE SIDEBAR - FIXED OVERLAY, MAIN CONTENT SCROLLS NORMALLY */
      @media (max-width: 1100px) {
        .sidebar {
          position: fixed;
          left: -300px;
          top: 0;
          bottom: 0;
          height: 100vh;
          overflow-y: auto;
          transition: left 0.3s ease-in-out;
          z-index: 1000;
        }
        .sidebar.mobile-open {
          left: 0;
        }
        /* Overlay when sidebar is open */
        .sidebar-overlay {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.5);
          z-index: 99;
          display: none;
        }
        .sidebar-overlay.active {
          display: block;
        }
      }

      /* ========== MAIN CONTENT - SCROLLS NORMALLY ========== */
      .main-content {
        flex: 1;
        background: var(--bg);
        transition: background 0.3s;
        width: 100%;
        min-height: 100vh;
        /* CRITICAL: main content scrolls naturally */
        overflow-y: visible;
        position: relative;
      }

      /* Ensure content inside main can scroll properly */
      .dashboard-content {
        padding: 2rem;
        padding-bottom: 4rem;
      }

      /* Top bar stays sticky for better UX */
      .top-bar {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--glass-edge);
        padding: 0.8rem 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        position: sticky;
        top: 0;
        z-index: 90;
      }

      .header-left {
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
      }

      .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 18px;
        box-shadow: 0 4px 8px var(--primary-glow);
      }

      .user-name {
        font-weight: 600;
        font-size: 16px;
        color: var(--text);
      }

      .header-right {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
      }

      .icon-button {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        font-size: 18px;
        cursor: pointer;
        transition: 0.2s;
        position: relative;
        flex-shrink: 0;
      }

      .icon-button:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
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
        background: var(--danger-color);
        color: #fff;
        border-radius: 9px;
        border: 2px solid var(--glass-bg);
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
        flex-shrink: 0;
      }

      .mobile-menu-btn {
        display: none;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 50%;
        width: 42px;
        height: 42px;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        cursor: pointer;
        color: var(--primary-color);
        transition: 0.2s;
        flex-shrink: 0;
      }

      .mobile-menu-btn:hover {
        background: var(--primary-color);
        color: white;
        box-shadow: var(--shadow-glow);
      }

      @media (max-width: 1100px) {
        .mobile-menu-btn {
          display: flex;
        }
      }

      .notification-dropdown {
        position: fixed;
        top: 72px;
        right: 20px;
        width: min(380px, calc(100vw - 32px));
        max-height: min(420px, 70vh);
        overflow-y: auto;
        background: var(--card-bg);
        backdrop-filter: blur(20px);
        color: var(--text);
        border-radius: 16px;
        box-shadow: var(--shadow-glow);
        border: 1px solid var(--glass-edge);
        z-index: 1000;
        display: none;
      }
      .notification-dropdown .nd-head {
        padding: 12px 16px;
        border-bottom: 1px solid var(--glass-edge);
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        font-size: 0.95rem;
        position: sticky;
        top: 0;
        background: var(--card-bg);
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
        border-bottom: 1px solid var(--glass-edge);
        font-size: 0.88rem;
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
        color: var(--text-secondary);
        font-size: 0.82rem;
      }
      .notification-item .nd-time {
        font-size: 0.72rem;
        color: var(--text-muted);
        margin-top: 6px;
      }

      @keyframes slideIn {
        from {
          transform: translateX(400px);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }
      @keyframes fadeOut {
        from {
          opacity: 1;
        }
        to {
          opacity: 0;
          transform: translateX(100px);
        }
      }

      /* Sidebar Navigation Styles */
      .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2rem;
        padding: 0 0.5rem;
      }

      .logo-wrapper {
        display: flex;
        align-items: center;
        gap: 0.8rem;
      }

      .logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: white;
        box-shadow: 0 0 30px var(--primary-glow);
      }

      .sidebar-header h3 {
        font-size: 1.5rem;
        background: linear-gradient(135deg, #fff, var(--primary-color));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        font-weight: 700;
      }

      .collapse-btn {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--primary-color);
        transition: all 0.3s;
      }

      .sidebar-user {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 1.2rem 1rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .user-avatar-large {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(
          145deg,
          var(--primary-color),
          var(--primary-dark)
        );
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        border: 2px solid var(--primary-color);
      }

      .sidebar-nav {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
      }

      .nav-section {
        margin: 1rem 0 0.5rem;
        padding-left: 1rem;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
      }

      .sidebar.collapsed .nav-section {
        display: none;
      }

      .sidebar-link {
        display: flex;
        align-items: center;
        gap: 1.2rem;
        padding: 0.8rem 1.2rem;
        border-radius: 40px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: all 0.3s;
        white-space: nowrap;
        position: relative;
        overflow: hidden;
      }

      .sidebar-link.sidebar-link--has-badge {
        white-space: normal;
        flex-wrap: wrap;
        align-items: flex-start;
        row-gap: 0.35rem;
        overflow: visible;
      }

      .sidebar-link.sidebar-link--has-badge > i {
        margin-top: 0.15rem;
      }

      .sidebar-link.sidebar-link--has-badge > span:first-of-type {
        flex: 1 1 auto;
        min-width: 0;
      }

      .sidebar-link .kyc-sidebar-badge {
        margin-left: 0;
        flex: 1 1 100%;
        align-self: stretch;
        text-align: center;
        max-width: 100%;
        box-sizing: border-box;
        padding: 0.25rem 0.45rem;
        font-size: 0.58rem;
        line-height: 1.25;
        word-break: break-word;
      }

      .sidebar-link i {
        width: 24px;
        font-size: 1.2rem;
        text-align: center;
        flex-shrink: 0;
      }

      .sidebar-link:hover,
      .sidebar-link.active {
        color: var(--primary-color);
        transform: translateX(5px);
        background: rgba(34, 197, 94, 0.15);
      }

      .action-badge {
        background: var(--primary-color);
        color: white;
        font-size: 0.65rem;
        padding: 0.15rem 0.5rem;
        border-radius: 40px;
        margin-left: auto;
        font-weight: 600;
      }

      /* Dashboard Content Styles */
      .welcome-section {
        text-align: center;
        margin-bottom: 25px;
        padding: 20px;
        background: var(--card-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
      }

      .moving-text {
        font-size: 24px;
        font-weight: 700;
        background: linear-gradient(
          45deg,
          var(--primary-color),
          var(--primary-light)
        );
        background-size: 200% 200%;
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        animation: moveText 15s linear infinite;
      }

      @keyframes moveText {
        0% {
          background-position: 0% 50%;
        }
        100% {
          background-position: 100% 50%;
        }
      }

      .wallet-id {
        text-align: center;
        margin-bottom: 25px;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
      }

      .wallet-id span {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        padding: 5px 15px;
        border-radius: 40px;
        font-weight: 600;
        font-family: monospace;
      }

      .copy-btn {
        background: var(--primary-color);
        color: white;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        cursor: pointer;
        transition: 0.2s;
      }

      .balance-section {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 25px;
        margin-bottom: 30px;
      }

      /* Same gradient as Total Portfolio / trade balance-value */
      .balance-section .balance-amount,
      .stat-grid .balance-value {
        font-weight: 700;
        line-height: 1.25;
        background: linear-gradient(135deg, var(--text), var(--primary-light));
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        color: transparent;
      }

      .balance-section .balance-amount {
        font-size: 36px;
      }

      .stat-grid .balance-value {
        font-size: 28px;
      }

      .balance-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-top: 20px;
      }

      .action-button {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 18px 10px;
        text-align: center;
        cursor: pointer;
        text-decoration: none;
        color: var(--text);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        transition: 0.2s;
      }

      .glass-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.8rem;
        transition: all 0.4s;
        height: 100%;
      }

      .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.8rem;
        margin: 2rem 0;
      }

      /* ===== ASSETS SECTION – FULLY RESTORED ORIGINAL STYLE ===== */
      .assets-section {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 25px;
        margin: 30px 0;
        transition: 0.2s;
        width: 100%;
      }

      .assets-tabs {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--glass-edge);
        padding-bottom: 15px;
        flex-wrap: wrap;
      }

      .tab {
        padding: 8px 20px;
        border-radius: 20px;
        font-weight: 500;
        cursor: pointer;
        transition: 0.2s;
        color: var(--text-muted);
      }

      body.light .tab {
        color: var(--text-secondary);
      }

      .tab.active,
      .tab:hover {
        background: var(--primary-color);
        color: white;
      }

      .assets-list {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
      }

      .asset-card {
        background: var(--glass-bg);
        border-radius: 30px;
        padding: 18px;
        display: flex;
        align-items: center;
        gap: 15px;
        cursor: pointer;
        transition: 0.2s;
        border: 1px solid var(--glass-edge);
        color: var(--text);
        text-decoration: none;
        position: relative;
        overflow: hidden;
      }

      .asset-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--primary-color);
        transform: scaleY(0);
        transform-origin: bottom;
        transition: 0.2s;
        z-index: 1;
      }

      .asset-card:hover::before {
        transform: scaleY(1);
      }

      .asset-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-glow);
        border-color: var(--primary-color);
      }

      .asset-icon {
        width: 48px;
        height: 48px;
        border-radius: 20px;
        background: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 20px;
        flex-shrink: 0;
        position: relative;
        z-index: 2;
      }

      body.light .asset-icon {
        background: var(--primary-light);
      }

      .asset-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 4px;
        position: relative;
        z-index: 2;
      }

      .asset-name {
        font-weight: 600;
        font-size: 16px;
        display: flex;
        justify-content: space-between;
      }

      .asset-balance {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
        color: var(--text-muted);
        font-family: monospace;
      }

      .asset-value {
        font-weight: 700;
        color: var(--primary-color);
        display: flex;
        justify-content: space-between;
        margin-top: 4px;
      }

      body.light .asset-value {
        color: var(--primary-color);
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

      /* Asset card (clean, balance-first) */
      .asset-top,
      .asset-bottom {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 10px;
      }

      .asset-top .asset-left {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 2px;
      }

      .asset-network {
        font-size: 12px;
        color: var(--text-muted);
      }

      .asset-top .asset-right {
        text-align: right;
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex-shrink: 0;
      }

      .asset-symbol {
        font-weight: 700;
        color: var(--text);
      }

      body.light .asset-symbol {
        color: var(--text);
      }

      .asset-balance-only {
        font-size: 14px;
        color: var(--text-muted);
        font-family: monospace;
      }

      .two-col-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin: 2rem 0;
      }

      .transaction-item,
      .security-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.75rem 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid var(--glass-edge);
        flex-wrap: wrap;
      }

      .security-item-main {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        min-width: 0;
        flex: 1 1 12rem;
      }

      .security-item-main > i {
        margin-top: 0.2rem;
        color: var(--primary-color);
        flex-shrink: 0;
      }

      .security-item-main h4 {
        margin: 0 0 0.35rem;
        font-size: 1rem;
      }

      .security-kyc-msg,
      .security-kyc-reason {
        margin: 0;
        font-size: 0.82rem;
        line-height: 1.45;
        color: var(--text-muted);
      }

      .security-kyc-reason {
        margin-top: 0.35rem;
        color: var(--danger-color, #ef5350);
      }

      .security-item .kyc-card-cta {
        flex-shrink: 0;
        align-self: center;
        text-decoration: none;
        white-space: nowrap;
      }

      .status-badge {
        padding: 0.4rem 1.2rem;
        border-radius: 40px;
        font-size: 0.85rem;
        font-weight: 600;
        background: rgba(46, 204, 113, 0.2);
        color: var(--positive);
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
        .dashboard-content {
          padding: 1rem;
        }
        .two-col-grid {
          grid-template-columns: 1fr;
        }
        .user-name {
          display: none;
        }
        .balance-section .balance-amount {
          font-size: 28px;
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
          <div class="collapse-btn" id="collapseSidebar">
            <i class="fa-solid fa-chevron-left"></i>
          </div>
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
          <a href="./userprofile.php" class="sidebar-link active"
            ><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a
          >
          <a href="./trade.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-line"></i> <span>Trade</span></a
          >
          <a href="./wallet.php" class="sidebar-link"
            ><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a
          >
          <div class="nav-section">TRADING</div>
          <a href="./deposit.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-down"></i> <span>Deposit</span
            ><span class="action-badge">NEW</span></a
          >
          <a href="./withdraw.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-up"></i> <span>Withdraw</span></a
          >
          <div class="nav-section">ACCOUNT</div>
          <a href="./kycverification.php" class="sidebar-link sidebar-link--has-badge"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Verification</span><?php echo kyc_sidebar_badge_span($kycSidebarBadge); ?></a
          >
          <a href="./support.php" class="sidebar-link"
            ><i class="fa-solid fa-shield"></i> <span>Support</span></a
          >
          <a href="./security.php" class="sidebar-link"
            ><i class="fa-solid fa-shield"></i> <span>Security</span></a
          >
          <a href="./logout.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Logout</span></a
          >
        </nav>
      </aside>

      <div class="sidebar-overlay" id="sidebarOverlay"></div>

      <main class="main-content">
        <div class="top-bar">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn">
              <i class="fa-solid fa-bars"></i>
            </div>
            <a href="./userprofile.php" class="user-profile">
              <div class="user-avatar"><?php echo h($ub['avatar_letter']); ?></div>
              <div class="user-profile-meta">
                <h3 class="user-name">Hello, <?php echo h($ub['full_name']); ?></h3>
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

        <div class="dashboard-content">
          <div class="welcome-section" data-aos="fade-up">
            <div class="moving-text">Welcome to Your Dashboard</div>
          </div>

          <div class="wallet-id">
            <p>Wallet ID</p>
            <span id="wallet-id"><?php echo h($walletId !== '' ? $walletId : ''); ?></span>
            <button class="copy-btn" id="copy-wallet-btn">
              <i class="fas fa-copy"></i>
            </button>
          </div>

          <div class="balance-section">
            <div class="balance-header">
              <div class="balance-title">Total balance</div>
              <div>
                <div class="balance-amount" id="available-balance">$<?php echo h(number_format($totalCashUsd, 2, '.', ',')); ?></div>
                <div
                  id="balance-overview-hints"
                  style="font-size: 0.85rem; color: var(--text-muted); margin-top: 6px; line-height: 1.45"
                >
                  <?php echo userprofile_balance_hints_html($mainWalletUsdStr, $tradeBalanceUsdStr, $portfolioValueUsd); ?>
                </div>
              </div>
            </div>
            <div class="balance-actions">
              <a href="./send.php" class="action-button">
                <div class="action-icon">
                  <i class="fas fa-paper-plane"></i>
                </div>
                <div class="action-label">Send</div>
              </a>
              <a href="./receive.php" class="action-button">
                <div class="action-icon"><i class="fas fa-arrow-down"></i></div>
                <div class="action-label">Receive</div>
              </a>
                          </div>
          </div>

          <div class="stat-grid">
            <div class="glass-card">
              <h4>Total Portfolio Value</h4>
              <div class="balance-value" id="portfolio-value">$<?php echo h(number_format((float)$totalPortfolioDisplayUsd, 2, '.', ',')); ?></div>
            </div>
            <div class="glass-card">
              <h4>24h Volume</h4>
              <div class="balance-value" id="volume-24h">$<?php echo h(number_format((float)$volume24hUsd, 2, '.', ',')); ?></div>
            </div>
          </div>

          <!-- ASSETS SECTION -->
          <div class="assets-section" data-aos="fade-up">
            <div class="assets-tabs">
              <span class="tab active" data-tab="all">All</span>
              <span class="tab" data-tab="nft">NFT</span>
            </div>
            <div class="assets-list" id="assets-list"></div>
          </div>

          <div class="two-col-grid">
            <div class="glass-card">
              <h3>Recent deposits</h3>
              <div id="recent-transactions">
                <div class="transaction-item">
                  <div>
                    <i class="fa-solid fa-clock" style="color: var(--warning-color)"></i>
                    Loading transactions...
                  </div>
                  <div></div>
                  <div class="status-badge" style="background: rgba(255, 165, 0, 0.2); color: var(--warning-color);">
                    Loading
                  </div>
                </div>
              </div>
            </div>
            <div class="glass-card">
              <h3>Security & KYC</h3>
              <div class="security-item">
                <div class="security-item-main">
                  <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                  <div>
                    <h4>KYC Verification</h4>
                    <p style="color: <?php echo h($kycProfileCard['color']); ?>; margin: 0 0 0.35rem; font-weight: 600;">
                      <?php echo h($kycProfileCard['label']); ?>
                    </p>
                    <p class="security-kyc-msg"><?php echo h($kycProfileCard['message']); ?></p>
                    <?php if ($kycProfileCard['status'] === 'rejected' && $kycProfileCard['rejection_reason'] !== '') { ?>
                    <p class="security-kyc-reason"><?php echo h($kycProfileCard['rejection_reason']); ?></p>
                    <?php } ?>
                  </div>
                </div>
                <a
                  href="./kycverification.php"
                  class="status-badge kyc-card-cta"
                  style="background: <?php echo h($kycProfileCard['cta_bg']); ?>; color: <?php echo h($kycProfileCard['badge_bg']); ?>;"
                ><?php echo h($kycProfileCard['cta']); ?></a>
              </div>
              <div class="security-item">
                <div class="security-item-main">
                  <i class="fa-solid fa-lock" aria-hidden="true"></i>
                  <div>
                    <h4>2FA</h4>
                    <p style="margin: 0; font-weight: 600; color: <?php echo $twofaEnabled ? 'var(--positive, #22c55e)' : 'var(--warning-color)'; ?>;">
                      <?php echo $twofaEnabled ? 'Enabled' : 'Not enabled'; ?>
                    </p>
                    <?php if (!$twofaEnabled) { ?>
                    <p class="security-kyc-msg" style="margin-top: 0.35rem;">Turn on two-factor authentication in Security settings.</p>
                    <?php } ?>
                  </div>
                </div>
                <a
                  href="./security.php"
                  class="status-badge kyc-card-cta"
                  style="background: <?php echo $twofaEnabled ? 'rgba(100, 116, 139, 0.2)' : 'rgba(255, 167, 38, 0.2)'; ?>; color: <?php echo $twofaEnabled ? '#64748b' : 'var(--warning-color)'; ?>;"
                ><?php echo $twofaEnabled ? 'Manage' : 'Enable'; ?></a>
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
      (function () {
        AOS.init({ duration: 700, once: true });

        // Sidebar collapse
        const sidebar = document.getElementById("sidebar");
        const collapseBtn = document.getElementById("collapseSidebar");
        collapseBtn.addEventListener("click", () => {
          sidebar.classList.toggle("collapsed");
          const icon = collapseBtn.querySelector("i");
          if (sidebar.classList.contains("collapsed")) {
            icon.classList.remove("fa-chevron-left");
            icon.classList.add("fa-chevron-right");
          } else {
            icon.classList.remove("fa-chevron-right");
            icon.classList.add("fa-chevron-left");
          }
        });

        // Mobile menu with overlay
        const mobileBtn = document.getElementById("mobileMenuBtn");
        const overlay = document.getElementById("sidebarOverlay");

        function closeSidebar() {
          sidebar.classList.remove("mobile-open");
          if (overlay) overlay.classList.remove("active");
        }

        function openSidebar() {
          sidebar.classList.add("mobile-open");
          if (overlay) overlay.classList.add("active");
        }

        mobileBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          if (sidebar.classList.contains("mobile-open")) {
            closeSidebar();
          } else {
            openSidebar();
          }
        });

        if (overlay) {
          overlay.addEventListener("click", closeSidebar);
        }

        // Close sidebar on window resize if needed
        window.addEventListener("resize", () => {
          if (window.innerWidth > 1100) {
            closeSidebar();
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

        // Real-time dashboard stats (poll server)
        const walletIdEl = document.getElementById("wallet-id");
        const availableEl = document.getElementById("available-balance");
        const balanceHintsEl = document.getElementById("balance-overview-hints");
        const portfolioEl = document.getElementById("portfolio-value");
        const volumeEl = document.getElementById("volume-24h");

        /** Parse API USD (string, number, or formatted with commas) */
        function parseUsd(v) {
          if (v == null || v === "") return 0;
          if (typeof v === "number") return Number.isFinite(v) ? v : 0;
          const s = String(v).replace(/[$,\s]/g, "").trim();
          const n = parseFloat(s);
          return Number.isFinite(n) ? n : 0;
        }

        function renderBalanceHints(mainStr, tradeStr, cryptoStr) {
          const m = parseUsd(mainStr);
          const t = parseUsd(tradeStr);
          const c = parseUsd(cryptoStr);
          const hm = Number.isFinite(m) ? m.toFixed(2) : "0.00";
          const ht = Number.isFinite(t) ? t.toFixed(2) : "0.00";
          const hc = Number.isFinite(c) ? c.toFixed(2) : "0.00";
          const linkW =
            '<a href="./wallet.php" style="color:var(--primary-color);text-decoration:none;font-weight:600">Wallet</a>';
          const linkT =
            '<a href="./trade.php" style="color:var(--primary-color);text-decoration:none;font-weight:600">Trade</a>';
          if (m <= 0 && t <= 0 && c > 0) {
            return `<div>Crypto holdings: <strong>$${hc}</strong> · ${linkW}</div>`;
          }
          if (m > 0 && t <= 0) {
            return `<div>Main wallet: <strong>$${hm}</strong> · ${linkW}</div>`;
          }
          if (m <= 0 && t > 0) {
            return `<div>Trading account: <strong>$${ht}</strong> · ${linkT}</div>`;
          }
          if (m > 0 && t > 0) {
            return (
              `<div>Main wallet: <strong>$${hm}</strong> · ${linkW}</div>` +
              `<div style="margin-top:6px">Trading account: <strong>$${ht}</strong> · ${linkT}</div>`
            );
          }
          return "<div>Deposit or transfer funds to get started.</div>";
        }

        async function refreshStats() {
          try {
            const res = await fetch("./userprofile.php?ajax=stats", {
              credentials: "same-origin",
              headers: { "Accept": "application/json" },
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!data || !data.ok) return;

            if (walletIdEl && data.wallet && data.wallet.wallet_id) {
              walletIdEl.textContent = data.wallet.wallet_id;
            }
            const w = data.wallet || {};
            const mainUsd = parseUsd(w.main_wallet_usd ?? w.wallet_balance_usd);
            const tradeUsd = parseUsd(w.trade_balance_usd);
            const cryptoUsd = parseUsd(w.crypto_holdings_usd ?? w.portfolio_value_usd);
            // Cash + crypto (same as wallet.php) + trading account — not cash-only + trade.
            const cashSum = mainUsd + cryptoUsd + tradeUsd;

            if (availableEl) {
              availableEl.textContent = `$${cashSum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            }
            if (balanceHintsEl) {
              balanceHintsEl.innerHTML = renderBalanceHints(
                String(w.main_wallet_usd ?? w.wallet_balance_usd ?? "0"),
                String(w.trade_balance_usd ?? "0"),
                String(w.crypto_holdings_usd ?? w.portfolio_value_usd ?? "0"),
              );
            }
            if (portfolioEl) {
              portfolioEl.textContent = `$${cashSum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            }
            if (volumeEl && w.volume_24h_usd != null && w.volume_24h_usd !== "") {
              volumeEl.textContent = `$${parseUsd(w.volume_24h_usd).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            }
          } catch (e) {
            // ignore network errors
          }
        }

        // initial load + poll (DB-backed totals; avoids noisy live marks)
        refreshStats();
        setInterval(refreshStats, 12000);

        // Recent transactions (from DB)
        const txWrap = document.getElementById("recent-transactions");
        function txIconAndColor(type) {
          const t = (type || "").toLowerCase();
          if (t === "deposit") return { icon: "fa-circle-arrow-up", color: "var(--positive)" };
          if (t === "withdraw" || t === "withdrawal") return { icon: "fa-circle-arrow-down", color: "var(--negative)" };
          if (t === "trade") return { icon: "fa-arrow-right-arrow-left", color: "var(--warning-color)" };
          if (t === "send") return { icon: "fa-paper-plane", color: "var(--warning-color)" };
          if (t === "receive") return { icon: "fa-arrow-down", color: "var(--positive)" };
          return { icon: "fa-receipt", color: "var(--warning-color)" };
        }

        function txStatusBadge(status) {
          const s = (status || "").toLowerCase();
          if (s === "completed" || s === "success") {
            return { text: "Completed", className: "status-badge", style: "" };
          }
          if (s === "cancelled" || s === "canceled") {
            return {
              text: "Cancelled",
              className: "status-badge",
              style: "background: rgba(127, 155, 192, 0.2); color: var(--text-muted);",
            };
          }
          if (s === "failed" || s === "rejected") {
            return {
              text: "Failed",
              className: "status-badge",
              style: "background: rgba(239, 83, 80, 0.2); color: var(--negative);",
            };
          }
          return {
            text: "Pending",
            className: "status-badge",
            style: "background: rgba(255, 165, 0, 0.2); color: var(--warning-color);",
          };
        }

        function formatAmount(amountStr, asset) {
          const n = Number(amountStr);
          if (Number.isFinite(n)) {
            const pretty = n.toLocaleString(undefined, { maximumFractionDigits: 8 });
            return `${pretty} ${asset || ""}`.trim();
          }
          return `${amountStr} ${asset || ""}`.trim();
        }

        async function refreshTransactions() {
          if (!txWrap) return;
          try {
            const res = await fetch("./userprofile.php?ajax=transactions&kind=deposit", {
              credentials: "same-origin",
              headers: { "Accept": "application/json" },
            });
            if (!res.ok) return;
            const data = await res.json();
            if (!data || !data.ok) return;

            const tx = Array.isArray(data.transactions) ? data.transactions : [];
            if (tx.length === 0) {
              txWrap.innerHTML = `
                <div class="transaction-item">
                  <div><i class="fa-solid fa-circle-info" style="color: var(--warning-color)"></i> No deposits yet</div>
                  <div></div>
                  <div class="status-badge" style="background: rgba(255, 165, 0, 0.2); color: var(--warning-color);">—</div>
                </div>
              `;
              return;
            }

            txWrap.innerHTML = "";
            tx.forEach((row) => {
              const info = txIconAndColor(row.type || "deposit");
              const badge = txStatusBadge(row.status);
              const typeLabel = "Deposit";
              const title = `${typeLabel} ${row.asset || ""}`.trim();
              const ca = row.crypto_amount != null ? String(row.crypto_amount).trim() : "";
              let amount;
              if (ca !== "") {
                amount = formatAmount(ca, row.asset);
                const usd = Number(row.amount || 0);
                if (Number.isFinite(usd) && usd > 0) {
                  amount += " (≈ $" + usd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ")";
                }
              } else {
                amount =
                  "$" +
                  Number(row.amount || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  }) +
                  " USD";
              }
              const el = document.createElement("div");
              el.className = "transaction-item";
              el.innerHTML = `
                <div>
                  <i class="fa-solid ${info.icon}" style="color: ${info.color}"></i>
                  ${title}
                </div>
                <div>${amount}</div>
                <div class="${badge.className}" style="${badge.style}">${badge.text}</div>
              `;
              txWrap.appendChild(el);
            });
          } catch (e) {
            // ignore
          }
        }

        refreshTransactions();
        setInterval(refreshTransactions, 10000);

        const assetsList = document.getElementById("assets-list");
        let cachedProfileAssets = [];
        let currentProfileAssetsTab = "all";

        function applyProfileAssetsTab() {
          if (!assetsList) return;
          const tab = currentProfileAssetsTab;
          assetsList.querySelectorAll(".asset-card[data-asset-id]").forEach((card) => {
            const id = String(card.getAttribute("data-asset-id") || "");
            const asset = cachedProfileAssets.find((a) => String(a.id) === id);
            const isNft = !!(asset && asset.is_nft);
            if (tab === "nft") {
              card.style.display = isNft ? "" : "none";
            } else {
              card.style.display = isNft ? "none" : "";
            }
          });
        }

        document.querySelectorAll(".assets-tabs .tab").forEach((tabEl) => {
          tabEl.addEventListener("click", () => {
            document.querySelectorAll(".assets-tabs .tab").forEach((t) => t.classList.remove("active"));
            tabEl.classList.add("active");
            const key = tabEl.getAttribute("data-tab") || "all";
            currentProfileAssetsTab = key === "nft" ? "nft" : "all";
            applyProfileAssetsTab();
          });
        });

        function formatUsd(n) {
          const x = Number(n);
          if (!Number.isFinite(x)) return "$0.00";
          return (
            "$" +
            x.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            })
          );
        }

        function formatCoin(n) {
          const x = Number(n);
          if (!Number.isFinite(x)) return "0";
          return x.toLocaleString(undefined, { maximumFractionDigits: 8 });
        }

        async function renderAssets() {
          if (!assetsList) return;
          try {
            const res = await fetch("./api/assets_api.php", {
              credentials: "same-origin",
              headers: { Accept: "application/json" },
            });
            const data = await res.json();
            if (!data || !data.ok || !Array.isArray(data.assets)) return;

            cachedProfileAssets = data.assets;

            const byId = new Map();
            assetsList.querySelectorAll(".asset-card[data-asset-id]").forEach((el) => {
              byId.set(el.getAttribute("data-asset-id"), el);
            });

            data.assets.forEach((asset) => {
              const id = String(asset.id ?? "");
              if (!id) return;
              const change = Number(asset.change_24h_pct || 0);
              const balNum = Number(asset.balance || 0);
              const priceUsd = Number(asset.price_usd || 0);
              const iconUrl = String(asset.icon_url || "").trim();

              let card = byId.get(id);
              if (!card) {
                card = document.createElement("a");
                card.href = "#";
                card.className = "asset-card";
                card.setAttribute("data-asset-id", id);
                card.innerHTML = `
                  <div class="asset-icon" aria-hidden="true">
                    <img src="${iconUrl}" alt="" style="width:28px;height:28px;object-fit:contain" onerror="this.style.display='none'">
                  </div>
                  <div class="asset-info">
                    <div class="asset-top">
                      <div class="asset-left">
                        <div class="asset-name"><span data-role="asset-name"></span></div>
                        <div class="asset-network" data-role="asset-network"></div>
                      </div>
                      <div class="asset-right">
                        <div class="asset-symbol" data-role="asset-symbol"></div>
                        <div class="asset-balance-only" data-role="asset-balance"></div>
                      </div>
                    </div>
                    <div class="asset-bottom" style="margin-top:6px">
                      <span class="price-change" data-role="asset-change"></span>
                      <span class="asset-value" style="margin-top:0" data-role="asset-price"></span>
                    </div>
                  </div>
                `;
                assetsList.appendChild(card);
                byId.set(id, card);
              }

              card.setAttribute("data-is-nft", asset.is_nft ? "1" : "0");

              const nameEl = card.querySelector('[data-role="asset-name"]');
              const netEl = card.querySelector('[data-role="asset-network"]');
              const symEl = card.querySelector('[data-role="asset-symbol"]');
              const balEl = card.querySelector('[data-role="asset-balance"]');
              const chEl = card.querySelector('[data-role="asset-change"]');
              const prEl = card.querySelector('[data-role="asset-price"]');

              if (nameEl) nameEl.textContent = String(asset.name || "");
              if (netEl) netEl.textContent = String(asset.network || "");
              if (symEl) symEl.textContent = String(asset.symbol || "");
              if (balEl) balEl.textContent = `${formatCoin(balNum)} ${String(asset.symbol || "")}`.trim();

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
                prEl.textContent = `≈ ${formatUsd(priceUsd)}`;
              }
            });

            data.assets.forEach((asset) => {
              const id = String(asset.id ?? "");
              if (!id) return;
              const el = byId.get(id);
              if (el) assetsList.appendChild(el);
            });
            applyProfileAssetsTab();
          } catch (e) {
            // ignore
          }
        }
        renderAssets();
        setInterval(renderAssets, 5000);

        // Copy wallet ID
        const copyBtn = document.getElementById("copy-wallet-btn");
        const walletId = document.getElementById("wallet-id");
        if (copyBtn && walletId) {
          copyBtn.addEventListener("click", () => {
            const id = walletId.textContent;
            navigator.clipboard.writeText(id).then(() => {
              const original = copyBtn.innerHTML;
              copyBtn.innerHTML = '<i class="fas fa-check"></i>';
              copyBtn.style.background = "#2ecc71";
              setTimeout(() => {
                copyBtn.innerHTML = original;
                copyBtn.style.background = "";
              }, 2000);
            });
          });
        }

        // Scroll to top
        const scrollBtn = document.getElementById("scrollTop");
        window.addEventListener("scroll", () => {
          scrollBtn.style.display = window.scrollY > 300 ? "flex" : "none";
        });
        scrollBtn.addEventListener("click", () => {
          window.scrollTo({ top: 0, behavior: "smooth" });
        });

        // Welcome notification
        setTimeout(() => {
          showNotification(
            "Welcome to Crypterra! You have successfully logged in.",
          );
        }, 1000);
      })();
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>
