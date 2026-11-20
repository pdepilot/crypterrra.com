<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_sidebar_state.php';
$kycSidebarBadge = kyc_sidebar_badge_for_session();
require_once dirname(__DIR__) . '/includes/geoip_local.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'portfolio') {
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    ensure_user_dashboard_schema($db, $DB_NAME);
    $p = user_fetch_portfolio($db, $userId);
    $db->close();
    echo json_encode(['ok' => true, 'portfolio' => $p ?? [], 'ts' => time()], JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    error_log('[withdraw-portfolio] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'portfolio' => [], 'ts' => time()], JSON_UNESCAPED_SLASHES);
  }
  exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'submit_withdrawal') {
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method'], JSON_UNESCAPED_SLASHES);
    exit;
  }
  $raw = file_get_contents('php://input');
  $input = is_string($raw) ? json_decode($raw, true) : null;
  if (!is_array($input)) {
    $input = $_POST;
  }
  if (!user_verify_csrf(isset($input['csrf_token']) ? (string)$input['csrf_token'] : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf'], JSON_UNESCAPED_SLASHES);
    exit;
  }
  if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth'], JSON_UNESCAPED_SLASHES);
    exit;
  }

  $asset = isset($input['asset']) && is_string($input['asset']) ? $input['asset'] : '';
  $cryptoAmt = isset($input['crypto_amount']) && is_numeric($input['crypto_amount']) ? (float)$input['crypto_amount'] : 0.0;
  $network = isset($input['network']) && is_string($input['network']) ? trim($input['network']) : '';
  $toAddr = isset($input['to_address']) && is_string($input['to_address']) ? $input['to_address'] : '';

  // Server-side client context (best-effort).
  $clientIp = '';
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && is_string($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $clientIp = trim((string)($parts[0] ?? ''));
  }
  if ($clientIp === '' && !empty($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
    $clientIp = $_SERVER['REMOTE_ADDR'];
  }
  $userAgent = (!empty($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : '';
  $geo = geoip_local_country($clientIp);
  $countryIso = (string)($geo['iso'] ?? '');
  $countryName = (string)($geo['name'] ?? '');

  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    // Trade withdrawals only: debit spot_balance_usd (trade balance) and hard-stop on gas validation.
    $res = user_create_pending_trade_withdrawal($db, (string)$DB_NAME, $userId, $asset, $cryptoAmt, $network, $toAddr, $clientIp, $userAgent, $countryIso, $countryName);
    $db->close();
    if (!($res['ok'] ?? false)) {
      http_response_code(400);
    }
    echo json_encode($res, JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    error_log('[withdraw-ajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server'], JSON_UNESCAPED_SLASHES);
  }
  exit;
}

$recentWithdrawals = [];
$savedWithdrawAddresses = [];
$availableBalanceUsd = '0.00';
try {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $schema = (string)$DB_NAME;
  ensure_user_dashboard_schema($db, $schema);
  // Match trade.php behavior: load base user row first, then try richer portfolio view.
  // This prevents showing 0 if the portfolio query fails (missing columns, etc).
  try {
    $row = user_fetch_by_id($db, $userId);
  } catch (Throwable $e) {
    error_log('[withdraw-user] ' . $e->getMessage());
  }

  try {
    // Withdraw page shows trade balance only (spot_balance_usd).
    $availableBalanceUsd = user_get_trade_balance_usd($db, $schema, $userId);
  } catch (Throwable $e) {
    error_log('[withdraw-wallet-balance] ' . $e->getMessage());
  }

  try {
    ensure_transactions_table($db);
    $recentWithdrawals = user_fetch_recent_withdrawals($db, $userId, 50);
    $seenAddr = [];
    foreach ($recentWithdrawals as $r) {
      $addr = trim((string)($r['to_address'] ?? ''));
      if ($addr === '' || isset($seenAddr[$addr])) {
        continue;
      }
      $seenAddr[$addr] = true;
      $a = strtoupper(trim((string)($r['asset'] ?? '')));
      $short = strlen($addr) <= 18 ? $addr : substr($addr, 0, 10) . '…' . substr($addr, -6);
      $savedWithdrawAddresses[] = [
        'address' => $addr,
        'label' => $a !== '' ? $a . ' · recent withdrawal' : 'Recent withdrawal',
        'short' => $short,
      ];
      if (count($savedWithdrawAddresses) >= 12) {
        break;
      }
    }
  } catch (Throwable $e) {
    error_log('[withdraw-tx] ' . $e->getMessage());
  }
  $db->close();
} catch (Throwable $e) {
  error_log('[withdraw] ' . $e->getMessage());
}

$userFullName = (string)($_SESSION['user_full_name'] ?? '');
if (isset($row) && is_array($row)) {
  $userFullName = (string)($row['full_name'] ?? $userFullName);
  $_SESSION['user_full_name'] = $userFullName;
}

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);

function wh(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    <title>Crypterra | Withdraw Funds</title>
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

      /* Withdraw Page Specific Styles */
      .withdraw-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 28px;
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

      .withdraw-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 32px;
      }

      .withdraw-card {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 28px;
        transition:
          transform 0.2s,
          box-shadow 0.2s;
      }

      .withdraw-card:hover {
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
        padding-right: 60px;
      }

      .input-suffix {
        position: absolute;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-weight: 500;
      }

      /* Address Book */
      .saved-addresses {
        margin-bottom: 24px;
      }

      .address-item {
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

      .address-item:hover {
        border-color: var(--primary-color);
        background: var(--primary-soft);
      }

      .address-item i {
        color: var(--primary-color);
        font-size: 1.2rem;
      }

      .address-details {
        flex: 1;
      }

      .address-label-name {
        font-weight: 600;
        font-size: 0.9rem;
      }

      .address-hash {
        font-size: 0.7rem;
        color: var(--text-muted);
        font-family: monospace;
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

      /* Withdraw Button */
      .withdraw-btn {
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

      .withdraw-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }

      .withdraw-btn:disabled {
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

      /* Alert */
      .alert {
        background: rgba(239, 68, 68, 0.1);
        border-left: 3px solid var(--negative);
        padding: 12px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-size: 0.85rem;
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
        .withdraw-grid {
          grid-template-columns: 1fr;
        }
        .withdraw-container {
          padding: 16px;
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
            ><i class="fa-solid fa-chart-line"></i> <span>Trade</span></a
          >
          <a href="./wallet.php" class="sidebar-link"
            ><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a
          >
          <div class="nav-section">TRADING</div>
          <a href="./send.php" class="sidebar-link"
            ><i class="fa-solid fa-paper-plane"></i> <span>Send</span></a
          >
          <!-- <a href="./withdraw.php" class="sidebar-link active"
            ><i class="fa-solid fa-arrow-up"></i> <span>Withdraw</span></a
          >
          <a href="./send.php" class="sidebar-link"
            ><i class="fa-solid fa-paper-plane"></i> <span>Send</span></a
          > -->
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
        <div class="top-bar">
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

        <div class="withdraw-container">
          <div class="page-header" data-aos="fade-up">
            <h1>
              <i class="fa-solid fa-arrow-up-from-bracket"></i> Withdraw Funds
            </h1>
            <p style="color: var(--text-muted); margin-top: 8px">
              Securely withdraw your cryptocurrency to external wallets
            </p>
            <p style="color: var(--text-muted); margin-top: 10px; font-size: 0.9rem; line-height: 1.5">
              Limits shown are your <strong>trading account (spot) USD</strong> — 
              <a href="./trade.php" style="color: var(--primary-color); font-weight: 600">Trade</a>. Main wallet USD is
              not withdrawn here.
            </p>
          </div>

          <div class="withdraw-grid">
            <!-- Left Column: Withdrawal Form -->
            <div class="withdraw-card" data-aos="fade-right">
              <div class="card-title">
                <i class="fa-solid fa-money-bill-transfer"></i>
                <span>Withdrawal Details</span>
              </div>

              <div class="balance-display">
                <div class="balance-label">Trading account (USD)</div>
                <div class="balance-amount" id="availableBalance">
                  $<?php echo wh(number_format((float)$availableBalanceUsd, 2, '.', ',')); ?>
                </div>
                <div
                  style="font-size: 0.85rem; margin-top: 8px"
                  id="cryptoBalance"
                >
                  ≈ <?php echo wh(number_format(((float)$availableBalanceUsd / 84500.0), 4, '.', '')); ?> BTC
                </div>
              </div>

              <div class="crypto-selector">
                <label>Select Cryptocurrency</label>
                <div class="crypto-buttons" id="crypto-buttons">
                  <button
                    class="crypto-btn active"
                    data-crypto="BTC"
                  >
                    <i class="fab fa-bitcoin"></i> Bitcoin (BTC)
                  </button>
                  <button
                    class="crypto-btn"
                    data-crypto="ETH"
                  >
                    <i class="fab fa-ethereum"></i> Ethereum (ETH)
                  </button>
                  <button
                    class="crypto-btn"
                    data-crypto="USDT"
                  >
                    <i class="fas fa-dollar-sign"></i> USDT (ERC20)
                  </button>
                  <button
                    class="crypto-btn"
                    data-crypto="SOL"
                  >
                    <i class="fas fa-bolt"></i> Solana (SOL)
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

              <div class="saved-addresses">
                <label>Saved Addresses</label>
                <div id="savedAddressesList">
                  <?php if (empty($savedWithdrawAddresses)) { ?>
                  <div
                    class="saved-addresses-empty"
                    style="
                      color: var(--text-muted);
                      font-size: 0.85rem;
                      padding: 10px 0;
                    "
                  >
                    No saved addresses yet. Destinations from your withdrawal history will appear here.
                  </div>
                  <?php } else { ?>
                  <?php foreach ($savedWithdrawAddresses as $sa) { ?>
                  <div
                    class="address-item"
                    data-address="<?php echo wh((string)$sa['address']); ?>"
                    data-label="<?php echo wh((string)$sa['label']); ?>"
                  >
                    <i class="fa-solid fa-bookmark"></i>
                    <div class="address-details">
                      <div class="address-label-name"><?php echo wh((string)$sa['label']); ?></div>
                      <div class="address-hash"><?php echo wh((string)$sa['short']); ?></div>
                    </div>
                  </div>
                  <?php } ?>
                  <?php } ?>
                </div>
              </div>

              <div class="input-group">
                <label>Withdrawal Amount</label>
                <div class="input-with-icon">
                  <input
                    type="number"
                    id="withdrawAmount"
                    class="input-field"
                    placeholder="0.00"
                    step="any"
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
                  <span>Withdrawal Amount</span>
                  <span id="displayAmount">0.0000 BTC</span>
                </div>
                <div class="fee-row">
                  <span>Network Fee</span>
                  <span id="networkFee">~0.0002 BTC</span>
                </div>
                <div class="fee-row total-row">
                  <span>Total to Deduct</span>
                  <span id="totalDeduct">0.0002 BTC</span>
                </div>
                <div class="fee-row">
                  <span>≈ USD Value</span>
                  <span id="usdValue">$0.00 USD</span>
                </div>
              </div>

              <div class="alert" id="insufficientAlert" style="display: none">
                <i class="fa-solid fa-triangle-exclamation"></i> Insufficient
                balance! Please enter a lower amount.
              </div>

              <button class="withdraw-btn" id="withdrawBtn">
                <i class="fa-solid fa-arrow-up"></i> Withdraw Funds
              </button>
            </div>

            <!-- Right Column: Instructions & History -->
            <div class="withdraw-card" data-aos="fade-left">
              <div class="card-title">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Withdrawal History</span>
              </div>

              <div id="withdrawalHistory" class="history-table">
<?php if (empty($recentWithdrawals)): ?>
                <div class="history-row">
                  <div style="grid-column: 1 / -1; color: var(--text-muted); padding: 8px 0;">
                    No withdrawals yet. Submit a withdrawal request to see it here.
                  </div>
                </div>
<?php else: ?>
<?php foreach (array_slice($recentWithdrawals, 0, 15) as $wrow):
  $asset = strtoupper(trim((string)($wrow['asset'] ?? '')));
  $usd = (float)($wrow['amount'] ?? 0);
  $crypto = trim((string)($wrow['crypto_amount'] ?? ''));
  $created = (string)($wrow['created_at'] ?? '');
  $dtLabel = $created !== '' ? date('M j, Y', strtotime($created)) : '—';
  $status = strtolower(trim((string)($wrow['status'] ?? 'pending')));
  $badgeClass = $status === 'completed' ? 'status-success' : ($status === 'pending' ? 'status-pending' : 'status-failed');
  $badgeText = $status === 'completed' ? 'Completed' : ($status === 'pending' ? 'Processing' : ucfirst($status));
  $icon = 'fas fa-coins';
  if ($asset === 'BTC') $icon = 'fab fa-bitcoin';
  elseif ($asset === 'ETH') $icon = 'fab fa-ethereum';
  elseif (in_array($asset, ['USDT','USDC','USD'], true)) $icon = 'fas fa-dollar-sign';
  elseif ($asset === 'SOL') $icon = 'fas fa-bolt';
?>
                <div class="history-row">
                  <div>
                    <i class="<?php echo wh($icon); ?>"></i>
                    <?php
                      if ($crypto !== '') {
                        $prettyC = rtrim(rtrim(number_format((float)$crypto, 8, '.', ''), '0'), '.');
                        echo wh($prettyC . ' ' . $asset) . ' <span style="color:var(--text-muted);font-size:0.85em;">(≈ $' . wh(number_format($usd, 2, '.', ',')) . ')</span>';
                      } else {
                        echo wh('$' . number_format($usd, 2, '.', ',') . ' USD');
                      }
                    ?>
                  </div>
                  <div><?php echo wh($dtLabel); ?></div>
                  <div><span class="status-badge <?php echo wh($badgeClass); ?>"><?php echo wh($badgeText); ?></span></div>
                </div>
<?php endforeach; ?>
<?php endif; ?>
              </div>

              <div style="margin-top: 28px">
                <h4 style="margin-bottom: 16px">
                  <i class="fa-solid fa-circle-info"></i> Important Notes
                </h4>
                <ul style="list-style: none; padding-left: 0">
                  <li style="padding: 8px 0">
                    <i
                      class="fa-solid fa-shield"
                      style="color: var(--primary-color); width: 24px"
                    ></i>
                    Minimum withdrawal: 0.064 BTC / 2.29 ETH / 5000 USDT
                  </li>
                  <li style="padding: 8px 0">
                    <i
                      class="fa-solid fa-clock"
                      style="color: var(--primary-color); width: 24px"
                    ></i>
                    Processing time: 10-30 minutes
                  </li>
                  <li style="padding: 8px 0">
                    <i
                      class="fa-solid fa-qrcode"
                      style="color: var(--primary-color); width: 24px"
                    ></i>
                    Double-check recipient address before confirming
                  </li>
                  <li style="padding: 8px 0">
                    <i
                      class="fa-solid fa-lock"
                      style="color: var(--primary-color); width: 24px"
                    ></i>
                    Withdrawals are reviewed before completion
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Error Modal (for gas/balance/validation alerts) -->
    <div
      id="withdrawErrorModal"
      class="modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="withdrawErrorTitle"
    >
      <div class="modal-content">
        <i
          class="fa-solid fa-triangle-exclamation"
          style="
            font-size: 2.5rem;
            color: var(--warning-color);
            margin-bottom: 16px;
          "
        ></i>
        <h3 id="withdrawErrorTitle">Transaction blocked</h3>
        <p id="withdrawErrorMessage" style="color: var(--text-secondary); margin-top: 10px"></p>
        <div class="modal-buttons" style="margin-top: 22px">
          <button class="modal-btn confirm" id="closeWithdrawError">OK</button>
        </div>
      </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="includes/user_theme.js"></script>
    <script>
      const WITHDRAW_CSRF = <?php echo json_encode(user_csrf_token(), JSON_UNESCAPED_SLASHES); ?>;
      AOS.init({ duration: 500, once: true });

      // Cryptocurrency data
      const TRADE_QUOTE_API = "./api/trade_withdraw_quote_api.php";
      const WALLET_API = "./api/wallet_api.php";

      let currentAssetId = "btc";
      let currentBalance = 0;
      let availableUsd = <?php echo json_encode((float)$availableBalanceUsd, JSON_UNESCAPED_SLASHES); ?>;

      // DOM Elements
      const cryptoBtns = document.querySelectorAll(".crypto-btn");
      const withdrawAmount = document.getElementById("withdrawAmount");
      const cryptoSuffix = document.getElementById("cryptoSuffix");
      const displayAmount = document.getElementById("displayAmount");
      const networkFeeSpan = document.getElementById("networkFee");
      const totalDeductSpan = document.getElementById("totalDeduct");
      const usdValueSpan = document.getElementById("usdValue");
      const cryptoBalanceSpan = document.getElementById("cryptoBalance");
      const availableBalanceSpan = document.getElementById("availableBalance");
      const insufficientAlert = document.getElementById("insufficientAlert");
      const withdrawBtn = document.getElementById("withdrawBtn");
      const maxAmountBtn = document.getElementById("maxAmountBtn");
      const recipientAddress = document.getElementById("recipientAddress");

      const withdrawErrorModal = document.getElementById("withdrawErrorModal");
      const withdrawErrorMessage = document.getElementById("withdrawErrorMessage");
      const closeWithdrawError = document.getElementById("closeWithdrawError");

      function showWithdrawErrorModal(message) {
        if (withdrawErrorMessage) withdrawErrorMessage.textContent = String(message || "");
        if (withdrawErrorModal) withdrawErrorModal.style.display = "flex";
      }

      function hideWithdrawErrorModal() {
        if (withdrawErrorModal) withdrawErrorModal.style.display = "none";
      }

      if (closeWithdrawError) {
        closeWithdrawError.addEventListener("click", hideWithdrawErrorModal);
      }
      if (withdrawErrorModal) {
        withdrawErrorModal.addEventListener("click", (e) => {
          if (e.target === withdrawErrorModal) hideWithdrawErrorModal();
        });
      }

      async function executeWithdrawal(amount, assetId, recipient) {
        withdrawBtn.disabled = true;
        const prev = withdrawBtn.innerHTML;
        withdrawBtn.innerHTML = "Submitting…";
        try {
          const res = await fetch("./withdraw.php?ajax=submit_withdrawal", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({
              csrf_token: WITHDRAW_CSRF,
              asset: assetId,
              crypto_amount: amount,
              amount_usd: 0,
              network: deriveNetworkFromAsset(assetId),
              to_address: recipient,
              fee_usd: 0,
            }),
          });
          const data = await res.json().catch(() => null);
          if (!res.ok || !data || !data.ok) {
            const err = data && data.error ? String(data.error) : "Request failed";
            const fallback = "You don't have Enough Network Fee To Proceed With This Transaction";
            const normalized = String(err || "").trim().toLowerCase();
            const showMsg =
              !err ||
              normalized === "request failed" ||
              normalized === "request failed." ||
              normalized === "server"
                ? fallback
                : err;
            showWithdrawErrorModal(showMsg);
            return;
          }
          location.reload();
        } catch (e) {
          showWithdrawErrorModal("Network error. Please try again.");
        } finally {
          withdrawBtn.disabled = false;
          withdrawBtn.innerHTML = prev;
        }
      }

      function deriveNetworkFromAsset(assetId) {
        if (assetId === "btc") return "BTC";
        if (assetId === "eth") return "ERC20";
        if (assetId === "usdt_erc20") return "ERC20";
        if (assetId === "usdt_trc20") return "TRC20";
        if (assetId === "bnb") return "BEP20";
        if (assetId === "sol") return "SOL";
        return "";
      }

      async function refreshTradeBalance() {
        try {
          const res = await fetch(WALLET_API, { method: "GET", credentials: "same-origin" });
          const data = await res.json();
          if (!data || !data.ok || !data.wallet) return;
          const t = Number(data.wallet.trade_balance_usd ?? 0);
          if (Number.isFinite(t)) {
            availableUsd = t;
            const el = document.getElementById("availableBalance");
            if (el) {
              el.textContent = "$" + t.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
          }
        } catch (e) {}
      }

      // Update UI based on selected crypto
      function updateCryptoUI() {
        const sym =
          currentAssetId === "btc" ? "BTC" :
          currentAssetId === "eth" ? "ETH" :
          currentAssetId === "usdt_erc20" ? "USDT" :
          currentAssetId === "sol" ? "SOL" : "—";
        cryptoSuffix.innerText = sym;
        networkFeeSpan.innerText = "—";
        cryptoBalanceSpan.innerText = `Available trade balance: $${availableUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        availableBalanceSpan.innerText = `$${availableUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        // Update withdraw amount placeholder
        withdrawAmount.placeholder = `0.00 ${cryptoSuffix.innerText}`;

        // Recalculate fees if amount exists
        if (withdrawAmount.value) {
          calculateFees();
        } else {
          updateFeeDisplay(0);
        }
      }

      // refreshPortfolio removed: wallet assets are the source of truth now.

      function calculateFees() {
        let amount = parseFloat(withdrawAmount.value);

        if (isNaN(amount)) amount = 0;
        updateFeeDisplay(amount);
        return { amount };
      }

      function updateFeeDisplay(amount) {
        const sym = cryptoSuffix.innerText;
        displayAmount.innerText = `${amount.toLocaleString(undefined, { maximumFractionDigits: 8 })} ${sym}`;
        usdValueSpan.innerText = `$0.00 USD`;
        if (amount > 0) {
          // Allow clicking; server will hard-stop if insufficient gas/trade balance.
          withdrawBtn.disabled = false;
          quoteTradeWithdrawal(amount);
        } else {
          networkFeeSpan.innerText = "—";
          totalDeductSpan.innerText = `0.00 USD`;
          insufficientAlert.style.display = "none";
          withdrawBtn.disabled = true;
        }
      }

      let quoteTimer = null;
      async function quoteTradeWithdrawal(amount) {
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(async () => {
          try {
            const sym = cryptoSuffix.innerText;
            const net =
              currentAssetId === "btc" ? "BTC" :
              currentAssetId === "eth" ? "ERC20" :
              currentAssetId === "usdt_erc20" ? "ERC20" :
              currentAssetId === "sol" ? "SOL" : "";
            const url = new URL(TRADE_QUOTE_API, window.location.href);
            url.searchParams.set("asset_id", currentAssetId);
            url.searchParams.set("network", net);
            url.searchParams.set("amount", String(amount));
            const res = await fetch(url.toString(), { credentials: "same-origin", headers: { Accept: "application/json" } });
            const data = await res.json();
            if (!data || !data.ok) {
              // If quote API fails, don't soft-lock the button.
              insufficientAlert.style.display = "none";
              withdrawBtn.disabled = false;
              return;
            }

            const usdValue = Number(data.amount_usd || 0);
            const gasSym = String(data.gas_symbol || "").toUpperCase();
            const gasFee = Number(data.gas_fee_amount || 0);
            const gasFeeUsd = Number(data.gas_fee_usd || 0);
            const platFeeUsd = Number(data.platform_fee_usd || 0);
            const totalUsd = Number(data.total_usd_deduct || 0);

            usdValueSpan.innerText = `$${usdValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USD`;
            networkFeeSpan.innerText = gasFee > 0 ? `~${gasFee} ${gasSym} (≈ $${gasFeeUsd.toFixed(2)})` : "—";
            totalDeductSpan.innerText = `$${totalUsd.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USD`;

            if (data.can_proceed) {
              insufficientAlert.style.display = "none";
              withdrawBtn.disabled = false;
            } else {
              insufficientAlert.style.display = "block";
              withdrawBtn.disabled = true;
              if (!data.ok_gas) {
                insufficientAlert.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> You do not have enough ${gasSym} to proceed with this transaction.`;
              } else {
                insufficientAlert.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Insufficient trade balance! Please enter a lower amount.`;
              }
            }
          } catch (e) {
            // Network/parse errors should not permanently disable the button.
            insufficientAlert.style.display = "none";
            withdrawBtn.disabled = false;
          }
        }, 200);
      }

      // Handle amount input
      withdrawAmount.addEventListener("input", () => {
        calculateFees();
      });

      // Max button
      maxAmountBtn.addEventListener("click", () => {
        const maxWithdraw = Math.max(0, currentBalance);
        withdrawAmount.value = maxWithdraw.toString();
        calculateFees();
      });

      // Crypto selection
      cryptoBtns.forEach((btn) => {
        btn.addEventListener("click", () => {
          cryptoBtns.forEach((b) => b.classList.remove("active"));
          btn.classList.add("active");
          const c = btn.getAttribute("data-crypto");
          currentAssetId =
            c === "BTC" ? "btc" :
            c === "ETH" ? "eth" :
            c === "USDT" ? "usdt_erc20" :
            c === "SOL" ? "sol" : "btc";
          updateCryptoUI();
          withdrawAmount.value = "";
          calculateFees();
        });
      });

      // Saved addresses
      const addressItems = document.querySelectorAll(".address-item");
      addressItems.forEach((item) => {
        item.addEventListener("click", () => {
          const address = item.getAttribute("data-address");
          recipientAddress.value = address;
        });
      });

      // Withdraw button - submit immediately (2FA removed)
      withdrawBtn.addEventListener("click", () => {
        const amount = parseFloat(withdrawAmount.value);
        const recipient = recipientAddress.value.trim();

        if (!recipient) {
          showWithdrawErrorModal("Please enter a recipient address");
          return;
        }

        if (isNaN(amount) || amount <= 0) {
          showWithdrawErrorModal("Please enter a valid amount");
          return;
        }
        executeWithdrawal(amount, currentAssetId, recipient);
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

      // Initialize
      updateCryptoUI();
      calculateFees();
      refreshTradeBalance();
      setInterval(refreshTradeBalance, 10000);
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>
