<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_sidebar_state.php';
$kycSidebarBadge = kyc_sidebar_badge_for_session();

$userId = (int)($_SESSION['user_id'] ?? 0);

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'submit_deposit') {
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
  $amountUsd = isset($input['amount_usd']) && is_numeric($input['amount_usd']) ? (float)$input['amount_usd'] : 0.0;
  $network = isset($input['network']) && is_string($input['network']) ? trim($input['network']) : '';
  $depositAddr = isset($input['deposit_address']) && is_string($input['deposit_address']) ? $input['deposit_address'] : '';

  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    ensure_user_dashboard_schema($db, $DB_NAME);
    $res = user_create_pending_deposit($db, $userId, $asset, $cryptoAmt, $amountUsd, $network, $depositAddr);
    $db->close();
    if (!($res['ok'] ?? false)) {
      http_response_code(400);
    }
    echo json_encode($res, JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    error_log('[deposit-ajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server'], JSON_UNESCAPED_SLASHES);
  }
  exit;
}

$recentDeposits = [];
try {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  ensure_transactions_table($db);
  $recentDeposits = user_fetch_recent_deposits($db, $userId, 15);
  $db->close();
} catch (Throwable $e) {
  error_log('[deposit] ' . $e->getMessage());
}

function dep_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @return array{text:string, class:string, style:string}
 */
function dep_status_badge(string $status): array {
  $s = strtolower(trim($status));
  if ($s === 'completed') {
    return ['text' => 'Completed', 'class' => 'status-badge status-success', 'style' => ''];
  }
  if ($s === 'pending') {
    return ['text' => 'Confirming', 'class' => 'status-badge status-pending', 'style' => ''];
  }
  if ($s === 'failed') {
    return ['text' => 'Failed', 'class' => 'status-badge', 'style' => 'background: rgba(239, 83, 80, 0.15); color: var(--danger-color);'];
  }
  if ($s === 'cancelled') {
    return ['text' => 'Cancelled', 'class' => 'status-badge', 'style' => 'background: rgba(127, 155, 192, 0.2); color: var(--text-muted);'];
  }
  return ['text' => ucfirst($s), 'class' => 'status-badge status-pending', 'style' => ''];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover"
    />
    <meta name="format-detection" content="telephone=no" />
    <title>Crypterra | Deposit Funds</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    />
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      @import url("https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap");

      /* ========== ROOT COLORS - GREEN THEME (matching dashboard) ========== */
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
        --dark-border: #2d2d2d;
        --light-card: #1e1e1e;
        --dark-card: #1e1e1e;
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
        --text: #111827;
        --text-secondary: #374151;
        --text-muted: #6b7280;
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-edge: rgba(34, 197, 94, 0.3);
        --card-bg: #ffffff;
        --positive: #2ecc71;
        --negative: #e74c3c;
        --shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        --shadow-glow: 0 0 20px rgba(34, 197, 94, 0.4);
        --light-border: #eaeaea;
        --dark-border: #eaeaea;
        --light-card: #ffffff;
        --dark-card: #ffffff;
        --input-bg: #f8fafc;
      }

      body {
        font-family: "Inter", "Segoe UI", sans-serif;
        background-color: var(--bg-secondary);
        color: var(--text);
        transition:
          background-color 0.3s ease,
          color 0.2s ease;
        overflow-x: hidden;
        min-height: 100vh;
      }

      /* Dashboard Layout */
      .dashboard {
        display: flex;
        min-height: 100vh;
        position: relative;
      }

      /* SIDEBAR - FIXED POSITION */
      .sidebar {
        width: 280px;
        background: rgba(10, 20, 35, 0.85);
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border-right: 1px solid var(--glass-edge);
        padding: 2rem 1.2rem;
        transition: width 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1);
        display: flex;
        flex-direction: column;
        z-index: 100;
        box-shadow: 5px 0 30px -10px rgba(0, 0, 0, 0.5);
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        scrollbar-width: thin;
      }

      body.light .sidebar {
        background: rgba(255, 255, 255, 0.85);
      }

      .sidebar.collapsed {
        width: 95px;
      }

      .sidebar.collapsed .logo-wrapper h3,
      .sidebar.collapsed .user-info,
      .sidebar.collapsed .sidebar-link span:not(.action-badge),
      .sidebar.collapsed .nav-section {
        display: none;
      }

      .sidebar.collapsed .kyc-sidebar-badge {
        display: none;
      }

      /* MOBILE SIDEBAR */
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

      /* MAIN CONTENT */
      .main-content {
        flex: 1;
        background: var(--bg);
        transition: background 0.3s;
        width: 100%;
        min-height: 100vh;
        overflow-y: visible;
        position: relative;
      }

      /* TOP BAR - EXACT MATCH FROM USERPROFILE */
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
        .user-name {
          display: none;
        }
        .top-bar {
          padding: 0.8rem 1rem;
        }
      }

      @media (max-width: 480px) {
        .user-profile-meta {
          display: none;
        }
        .header-right {
          gap: 8px;
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
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
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
        background: linear-gradient(145deg, var(--primary-color), var(--primary-dark));
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

      /* Deposit Page Specific Styles */
      .dashboard-content {
        padding: 2rem;
        padding-bottom: 4rem;
      }

      @media (max-width: 768px) {
        .dashboard-content {
          padding: 1rem;
        }
      }

      .deposit-container {
        max-width: 1200px;
        margin: 0 auto;
      }

      .page-header {
        margin-bottom: 24px;
      }

      .page-header h1 {
        font-size: clamp(1.5rem, 5vw, 2rem);
        background: linear-gradient(135deg, var(--text), var(--primary-light));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
      }

      .page-header p {
        color: var(--text-muted);
        margin-top: 8px;
        font-size: clamp(0.8rem, 3vw, 0.9rem);
      }

      .deposit-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
      }

      .deposit-card {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 24px;
        transition: transform 0.2s, box-shadow 0.2s;
      }

      .deposit-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-glow);
        border-color: var(--primary-light);
      }

      .card-title {
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--primary-light);
        flex-wrap: wrap;
      }

      .card-title i {
        font-size: 1.5rem;
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
        gap: 10px;
        flex-wrap: wrap;
      }

      .crypto-btn {
        background: var(--surface);
        border: 1px solid var(--light-border);
        padding: 10px 18px;
        border-radius: 60px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        color: var(--text-secondary);
        font-size: 0.85rem;
      }

      .crypto-btn i {
        font-size: 1rem;
      }

      .crypto-btn.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        box-shadow: 0 0 15px var(--primary-glow);
      }

      /* Amount Input */
      .amount-input {
        margin-bottom: 24px;
      }

      .amount-input label {
        display: block;
        margin-bottom: 10px;
        font-weight: 500;
        color: var(--text-secondary);
      }

      .input-group {
        display: flex;
        align-items: center;
        background: var(--input-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 4px;
        transition: all 0.2s;
        flex-wrap: wrap;
      }

      .input-group:focus-within {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-soft);
      }

      .input-group input {
        flex: 1;
        background: transparent;
        border: none;
        padding: 14px 16px;
        font-size: 1rem;
        color: var(--text);
        outline: none;
        min-width: 120px;
      }

      .input-group span {
        padding: 0 16px;
        color: var(--text-muted);
        font-weight: 500;
      }

      .usd-equivalent {
        margin-top: 8px;
        font-size: 0.8rem;
        color: var(--text-muted);
        text-align: right;
      }

      /* Address Section */
      .address-section {
        background: var(--primary-soft);
        border-radius: 20px;
        padding: 20px;
        margin: 20px 0;
        text-align: center;
      }

      .address-label {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-bottom: 12px;
      }

      .wallet-address {
        background: var(--bg);
        padding: 12px;
        border-radius: 16px;
        font-family: monospace;
        font-size: 0.8rem;
        word-break: break-all;
        margin-bottom: 16px;
        border: 1px solid var(--glass-edge);
      }

      .copy-address-btn {
        background: var(--primary-color);
        border: none;
        padding: 8px 20px;
        border-radius: 40px;
        color: white;
        cursor: pointer;
        font-weight: 600;
        transition: 0.2s;
        font-size: 0.85rem;
      }

      .copy-address-btn:hover {
        background: var(--primary-dark);
        transform: scale(1.02);
      }

      .qr-container {
        display: flex;
        justify-content: center;
        margin: 16px 0;
        padding: 16px;
        background: white;
        border-radius: 20px;
      }

      #qrcode {
        display: flex;
        justify-content: center;
      }

      #qrcode img,
      #qrcode canvas {
        max-width: 100%;
        height: auto;
      }

      /* Fee Info */
      .fee-info {
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid var(--glass-edge);
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        font-size: 0.85rem;
      }

      .deposit-btn {
        width: 100%;
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        border: none;
        padding: 14px;
        border-radius: 60px;
        color: white;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        margin-top: 20px;
      }

      .deposit-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }

      /* Instructions */
      .instructions {
        background: var(--surface);
        border-radius: 20px;
        padding: 20px;
        margin-top: 20px;
      }

      .instructions h4 {
        margin-bottom: 12px;
        color: var(--primary-light);
        font-size: 1rem;
      }

      .instructions ul {
        list-style: none;
        padding-left: 0;
      }

      .instructions li {
        padding: 8px 0;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.85rem;
        flex-wrap: wrap;
      }

      .instructions li i {
        color: var(--primary-color);
        width: 24px;
        flex-shrink: 0;
      }

      /* History Table */
      .history-table {
        width: 100%;
        margin-top: 16px;
      }

      .history-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--glass-edge);
        gap: 10px;
        flex-wrap: wrap;
        font-size: 0.8rem;
      }

      .history-row > div {
        flex: 1;
        min-width: 0;
      }

      .history-row > div:first-child {
        flex: 2;
      }

      .status-badge {
        padding: 4px 10px;
        border-radius: 40px;
        font-size: 0.7rem;
        font-weight: 500;
        white-space: nowrap;
        display: inline-block;
      }

      .status-success {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary-light);
      }

      .status-pending {
        background: rgba(255, 167, 38, 0.2);
        color: var(--warning-color);
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
        .deposit-grid {
          grid-template-columns: 1fr;
          gap: 20px;
        }
        .deposit-card {
          padding: 16px;
        }
        .crypto-buttons {
          gap: 8px;
        }
        .crypto-btn {
          padding: 8px 14px;
          font-size: 0.75rem;
        }
      }

      @media (max-width: 480px) {
        .crypto-buttons {
          flex-direction: column;
          width: 100%;
        }
        .crypto-btn {
          justify-content: center;
        }
        .address-section {
          padding: 16px;
        }
        .wallet-address {
          font-size: 0.7rem;
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
          <div class="user-avatar-large"><?php echo dep_h($ub['avatar_letter']); ?></div>
          <div class="user-info">
            <h4><?php echo dep_h($ub['full_name']); ?></h4>
            <p><?php echo dep_h($ub['wallet_short']); ?></p>
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
          <a href="./deposit.php" class="sidebar-link active"
            ><i class="fa-solid fa-arrow-down"></i> <span>Deposit</span>
            <span class="action-badge">NEW</span></a
          >
          <a href="./withdraw.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-up"></i> <span>Withdraw</span></a
          >
          <div class="nav-section">ACCOUNT</div>
          <a href="./kycverification.php" class="sidebar-link sidebar-link--has-badge"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Verification</span><?php echo kyc_sidebar_badge_span($kycSidebarBadge); ?></a
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
        <!-- TOP BAR - EXACT MATCH FROM USERPROFILE -->
        <div class="top-bar">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn">
              <i class="fa-solid fa-bars"></i>
            </div>
            <a href="./userprofile.php" class="user-profile">
              <div class="user-avatar"><?php echo dep_h($ub['avatar_letter']); ?></div>
              <div class="user-profile-meta">
                <h3 class="user-name">Hello, <?php echo dep_h($ub['full_name']); ?></h3>
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

        <div class="dashboard-content">
          <div class="deposit-container">
            <div class="page-header" data-aos="fade-up">
              <h1>
                <i class="fa-solid fa-arrow-down-to-line"></i> Deposit Funds
              </h1>
              <p>
                Choose your preferred cryptocurrency and deposit securely
              </p>
            </div>

            <div class="deposit-grid">
              <!-- Left Column: Deposit Form -->
              <div class="deposit-card" data-aos="fade-right">
                <div class="card-title">
                  <i class="fa-solid fa-coins"></i>
                  <span>Deposit Details</span>
                </div>

                <div class="crypto-selector">
                  <label>Select Cryptocurrency</label>
                  <div class="crypto-buttons" id="crypto-buttons">
                    <button class="crypto-btn active" data-crypto="BTC" data-asset="BTC" data-network="Bitcoin">
                      <i class="fab fa-bitcoin"></i> Bitcoin (BTC)
                    </button>
                    <button class="crypto-btn" data-crypto="ETH" data-asset="ETH" data-network="Ethereum">
                      <i class="fab fa-ethereum"></i> Ethereum (ETH)
                    </button>
                    <button class="crypto-btn" data-crypto="USDT" data-asset="USDT" data-network="ERC20">
                      <i class="fas fa-dollar-sign"></i> USDT (ERC20)
                    </button>
                    <button class="crypto-btn" data-crypto="USDT_TRC20" data-asset="USDT" data-network="TRC20">
                      <i class="fas fa-dollar-sign"></i> USDT (TRC20)
                    </button>
                    <button class="crypto-btn" data-crypto="SOL" data-asset="SOL" data-network="Solana">
                      <i class="fas fa-bolt"></i> Solana (SOL)
                    </button>
                  </div>
                </div>

                <div class="amount-input">
                  <label>Amount</label>
                  <div class="input-group">
                    <input
                      type="number"
                      id="amountInput"
                      placeholder="0.00"
                      step="any"
                      value="0.01"
                    />
                    <span id="cryptoCurrencySymbol">BTC</span>
                  </div>
                  <div class="usd-equivalent" id="usdEquivalent">≈ $0.00 USD</div>
                </div>

                <div class="address-section">
                  <div class="address-label">
                    <i class="fa-solid fa-qrcode"></i> Deposit Address
                  </div>
                  <div class="wallet-address" id="walletAddress">
                    bc1q3jpcmus3ms2hrc2wqq36l6damvnvm6uuygevp0
                  </div>
                  <button class="copy-address-btn" id="copyAddressBtn">
                    <i class="fas fa-copy"></i> Copy Address
                  </button>
                  <div class="qr-container" id="qr-container">
                    <div id="qrcode"></div>
                  </div>
                </div>

                <!--<div class="fee-info">-->
                <!--  <span><i class="fa-solid fa-clock"></i> Network Fee</span>-->
                <!--  <span id="networkFee">~0.003 BTC</span>-->
                <!--</div>-->

                <button class="deposit-btn" id="simulateDepositBtn">
                  <i class="fa-solid fa-arrow-down"></i> Start Deposit
                </button>
              </div>

              <!-- Right Column: Instructions & History -->
              <div class="deposit-card" data-aos="fade-left">
                <div class="card-title">
                  <i class="fa-solid fa-circle-info"></i>
                  <span>Instructions & History</span>
                </div>

                <div class="instructions">
                  <h4><i class="fa-solid fa-lightbulb"></i> How to deposit</h4>
                  <ul>
                    <li><i class="fa-solid fa-1"></i> Select your cryptocurrency</li>
                    <li><i class="fa-solid fa-2"></i> Enter the amount you want to deposit</li>
                    <li><i class="fa-solid fa-3"></i> Copy the deposit address or scan QR code</li>
                    <li><i class="fa-solid fa-4"></i> Send funds from your external wallet</li>
                    <li><i class="fa-solid fa-5"></i> Funds appear after 2-3 network confirmations</li>
                  </ul>
                </div>

                <div style="margin-top: 24px">
                  <h4 style="margin-bottom: 16px; font-size: 1rem;">
                    <i class="fa-solid fa-clock-rotate-left"></i> Recent Deposits
                  </h4>
                  <div id="depositHistory" class="history-table">
                    <?php if (empty($recentDeposits)): ?>
                    <div class="history-row">
                      <div style="grid-column: 1 / -1; color: var(--text-muted); padding: 8px 0;">
                        No deposits yet. Send crypto to your address below; pending and completed deposits appear here.
                      </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($recentDeposits as $drow):
                      $asset = strtoupper(trim((string)($drow['asset'] ?? '')));
                      if ($asset === '') {
                        $asset = 'USD';
                      }
                      $amtNum = (float)($drow['amount'] ?? 0);
                      $amtStr = number_format($amtNum, 2, '.', ',');
                      $bd = dep_status_badge((string)($drow['status'] ?? ''));
                      $created = (string)($drow['created_at'] ?? '');
                      $dtLabel = $created !== '' ? date('M j, Y', strtotime($created)) : '—';
                      $icon = 'fas fa-coins';
                      if ($asset === 'BTC') {
                        $icon = 'fab fa-bitcoin';
                      } elseif ($asset === 'ETH') {
                        $icon = 'fab fa-ethereum';
                      } elseif (in_array($asset, ['USDT', 'USDC', 'USD'], true)) {
                        $icon = 'fas fa-dollar-sign';
                      } elseif ($asset === 'SOL') {
                        $icon = 'fas fa-sun';
                      }
                      $cryptoMeta = isset($drow['crypto_amount']) ? trim((string)$drow['crypto_amount']) : '';
                      ?>
                    <div class="history-row">
                      <div><i class="<?php echo dep_h($icon); ?>"></i> <?php
                      if ($cryptoMeta !== '') {
                        $prettyC = rtrim(rtrim(number_format((float)$cryptoMeta, 8, '.', ''), '0'), '.');
                        echo dep_h($prettyC . ' ' . $asset) . ' <span style="color:var(--text-muted);font-size:0.75em;">(≈ $' . dep_h(number_format($amtNum, 2, '.', ',')) . ')</span>';
                      } else {
                        echo dep_h('$' . $amtStr . ' USD');
                      }
                      ?>
                      <div><?php echo dep_h($dtLabel); ?></div>
                      <div><span class="<?php echo dep_h($bd['class']); ?>"<?php echo $bd['style'] !== '' ? ' style="' . dep_h($bd['style']) . '"' : ''; ?>><?php echo dep_h($bd['text']); ?></span></div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
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

      // Wallet addresses for different cryptocurrencies
      const walletAddresses = {
        BTC: "bc1q3jpcmus3ms2hrc2wqq36l6damvnvm6uuygevp0",
        ETH: "0x6e608254bE0aCb503D8855C9A223AFcd61631BD0",
        USDT: "0x6e608254bE0aCb503D8855C9A223AFcd61631BD0",
        USDT_TRC20: "TKJf3jr7dJeehsZLiHAhbF5Z3mexpRXeLE",
        SOL: "Bh7xJP3zHb8BfebqseuxpxLGoKTWuNMMQGMqgkbC1PY3",
      };

      // Exchange rates (simplified)
      const exchangeRates = {
        BTC: 84500,
        ETH: 3200,
        USDT: 1,
        SOL: 145,
      };

      // Network fees
      const networkFees = {
        BTC: "~0.003 BTC",
        ETH: "~0.1 ETH",
        USDT: "~5 USDT",
        USDT_TRC20: "~1 USDT ($1.00)",
        SOL: "~0.0005 SOL ($0.07)",
      };

      // Display symbols for the amount input
      const displaySymbols = {
        BTC: "BTC",
        ETH: "ETH",
        USDT: "USDT",
        USDT_TRC20: "USDT",
        SOL: "SOL",
      };

      // Asset mapping for API submission
      const assetMapping = {
        BTC: "BTC",
        ETH: "ETH",
        USDT: "USDT",
        USDT_TRC20: "USDT",
        SOL: "SOL",
      };

      // Network mapping for API submission
      const networkMapping = {
        BTC: "Bitcoin",
        ETH: "Ethereum",
        USDT: "ERC20",
        USDT_TRC20: "TRC20",
        SOL: "Solana",
      };

      let currentCrypto = "BTC";
      let currentAsset = "BTC";
      let currentNetwork = "Bitcoin";

      // Update UI based on selected crypto
      function updateCryptoUI() {
        // Update the symbol display next to amount input
        document.getElementById("cryptoCurrencySymbol").innerText = displaySymbols[currentCrypto] || currentCrypto;
        
        // Update wallet address
        const address = walletAddresses[currentCrypto];
        document.getElementById("walletAddress").innerText = address || "Address not available";
        
        // Update network fee
        document.getElementById("networkFee").innerHTML = networkFees[currentCrypto] || "Fee not available";
        
        // Update QR Code
        const qrContainer = document.getElementById("qrcode");
        if (qrContainer && address) {
          qrContainer.innerHTML = "";
          new QRCode(qrContainer, {
            text: address,
            width: 140,
            height: 140,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M,
          });
        }

        // Update USD equivalent
        updateUSDEq();
      }

      function updateUSDEq() {
        const amount = parseFloat(document.getElementById("amountInput").value) || 0;
        const rate = exchangeRates[currentCrypto] || exchangeRates.USDT;
        const usdValue = amount * rate;
        document.getElementById("usdEquivalent").innerHTML = `≈ $${usdValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USD`;
      }

      // Handle crypto selection
      const cryptoBtns = document.querySelectorAll(".crypto-btn");
      cryptoBtns.forEach((btn) => {
        btn.addEventListener("click", () => {
          // Remove active class from all buttons
          cryptoBtns.forEach((b) => b.classList.remove("active"));
          // Add active class to clicked button
          btn.classList.add("active");
          
          // Get crypto data from button attributes
          currentCrypto = btn.getAttribute("data-crypto");
          currentAsset = btn.getAttribute("data-asset") || currentCrypto;
          currentNetwork = btn.getAttribute("data-network") || currentCrypto;
          
          // Update the UI
          updateCryptoUI();
        });
      });

      // Handle amount input
      const amountInput = document.getElementById("amountInput");
      amountInput.addEventListener("input", updateUSDEq);

      // Copy address functionality
      const copyAddressBtn = document.getElementById("copyAddressBtn");
      const walletAddressSpan = document.getElementById("walletAddress");
      copyAddressBtn.addEventListener("click", () => {
        navigator.clipboard.writeText(walletAddressSpan.innerText);
        const originalText = copyAddressBtn.innerHTML;
        copyAddressBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => (copyAddressBtn.innerHTML = originalText), 2000);
      });

      const simulateBtn = document.getElementById("simulateDepositBtn");
      simulateBtn.addEventListener("click", async () => {
        const amount = parseFloat(amountInput.value);
        if (!amount || amount <= 0) {
          alert("Please enter a valid amount");
          return;
        }
        const rate = exchangeRates[currentCrypto] || exchangeRates.USDT;
        const usdValue = amount * rate;
        const addr = walletAddressSpan.innerText.trim();
        const assetToSend = assetMapping[currentCrypto] || currentCrypto;
        const networkToSend = networkMapping[currentCrypto] || currentNetwork;
        
        const prevHtml = simulateBtn.innerHTML;
        simulateBtn.disabled = true;
        simulateBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
        try {
          const res = await fetch("./deposit.php?ajax=submit_deposit", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({
              csrf_token: DEPOSIT_CSRF,
              asset: assetToSend,
              crypto_amount: amount,
              amount_usd: usdValue,
              network: networkToSend,
              deposit_address: addr,
            }),
          });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok) {
            const err = data && data.error ? String(data.error) : "unknown";
            alert("Could not record deposit: " + err);
            return;
          }
          location.reload();
        } catch (e) {
          alert("Network error. Please try again.");
        } finally {
          simulateBtn.disabled = false;
          simulateBtn.innerHTML = prevHtml;
        }
      });

      // Sidebar functionality - identical to userprofile
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      const mobileBtn = document.getElementById("mobileMenuBtn");
      const overlay = document.getElementById("sidebarOverlay");

      if (collapseBtn) {
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
      }

      function closeSidebar() {
        sidebar.classList.remove("mobile-open");
        if (overlay) overlay.classList.remove("active");
      }

      function openSidebar() {
        sidebar.classList.add("mobile-open");
        if (overlay) overlay.classList.add("active");
      }

      if (mobileBtn) {
        mobileBtn.addEventListener("click", (e) => {
          e.stopPropagation();
          if (sidebar.classList.contains("mobile-open")) {
            closeSidebar();
          } else {
            openSidebar();
          }
        });
      }

      if (overlay) {
        overlay.addEventListener("click", closeSidebar);
      }

      window.addEventListener("resize", () => {
        if (window.innerWidth > 1100) {
          closeSidebar();
        }
      });

      // Notification bell (simple alert for now)
      const notifBtn = document.getElementById("notifications-btn");
      if (notifBtn) {
        notifBtn.addEventListener("click", () => {
          alert("📢 New: BTC deposits now have lower fees!");
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

      // Initialize QR code and UI
      setTimeout(() => {
        updateCryptoUI();
      }, 100);
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>