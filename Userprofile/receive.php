<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_sidebar_state.php';
$kycSidebarBadge = kyc_sidebar_badge_for_session();
$csrfToken = user_csrf_token();

$userId = (int)($_SESSION['user_id'] ?? 0);
$walletId = '';
$mainWalletUsd = '0.00';
$cryptoPortfolioUsd = '0.00';
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
    $walletId = is_string($row['wallet_id'] ?? null) ? (string)$row['wallet_id'] : '';
    if ($walletId === '') {
      $walletId = user_ensure_wallet_id($db, $userId);
    }
  }
  $mainWalletUsd = user_get_wallet_balance_usd($db, $schema, $userId);
  $cryptoPortfolioUsd = number_format(getPortfolioValue($userId), 2, '.', '');
  $db->close();
} catch (Throwable $e) {
  error_log('[receive] ' . $e->getMessage());
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'incoming') {
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth', 'transactions' => []], JSON_UNESCAPED_SLASHES);
    exit;
  }
  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    ensure_transactions_table($db);
    $incoming = user_fetch_incoming_transactions($db, $userId, 35);
    $db->close();
    echo json_encode(['ok' => true, 'transactions' => $incoming, 'ts' => time()], JSON_UNESCAPED_SLASHES);
  } catch (Throwable $e) {
    error_log('[receive-incoming] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'server', 'transactions' => []], JSON_UNESCAPED_SLASHES);
  }
  exit;
}

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);

$receiveBootJson = json_encode(
  [
    'main_wallet_usd' => $mainWalletUsd,
    'crypto_portfolio_usd' => $cryptoPortfolioUsd,
    'wallet_id' => $walletId,
    'csrf' => $csrfToken,
  ],
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
if ($receiveBootJson === false) {
  $receiveBootJson = '{}';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes"
    />
    <title>Crypterra | Receive Crypto</title>
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
        overflow-x: hidden;
        width: 100%;
      }

      .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--glass-edge);
        box-sizing: border-box;
        flex-wrap: wrap;
        gap: 12px;
      }

      .header-left {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
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

      .user-profile-meta .user-name {
        font-size: 0.95rem;
        font-weight: 600;
        white-space: nowrap;
      }

      .header-right {
        display: flex;
        gap: 12px;
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
        transition: 0.2s;
        color: var(--text-secondary);
        border: 1px solid var(--light-border);
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

      /* Receive Page Specific Styles - FULLY RESPONSIVE */
      .receive-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px 16px 40px;
        margin-top: 60px;
      }

      .page-header {
        margin-bottom: 28px;
      }

      .page-header h1 {
        font-size: 1.8rem;
        background: linear-gradient(135deg, var(--text), var(--primary-light));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        word-break: break-word;
      }

      .receive-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
      }

      .receive-card {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 20px;
        transition:
          transform 0.2s,
          box-shadow 0.2s;
      }

      .receive-card:hover {
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
        gap: 10px;
        color: var(--primary-light);
        flex-wrap: wrap;
      }

      .card-title i {
        font-size: 1.5rem;
      }

      /* Crypto Selector - Mobile optimized */
      .crypto-selector {
        margin-bottom: 24px;
      }

      .crypto-selector label {
        display: block;
        margin-bottom: 12px;
        font-weight: 500;
        color: var(--text-secondary);
        font-size: 0.9rem;
      }

      .crypto-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
      }

      .crypto-btn {
        background: var(--surface);
        border: 1px solid var(--light-border);
        padding: 10px 16px;
        border-radius: 60px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        color: var(--text-secondary);
        font-size: 0.85rem;
        white-space: nowrap;
      }

      .crypto-btn.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        box-shadow: 0 0 15px var(--primary-glow);
      }

      /* Network warning */
      .receive-network-warning {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 24px;
        padding: 14px 16px;
        border-radius: 16px;
        border: 1px solid #f87171;
        background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        color: #991b1b;
        font-size: 0.85rem;
        line-height: 1.45;
        box-shadow: 0 2px 12px rgba(220, 38, 38, 0.18);
      }
      .receive-network-warning i {
        flex-shrink: 0;
        margin-top: 2px;
        color: #dc2626;
        font-size: 1rem;
      }

      /* QR Code Section - Fluid sizing */
      .qr-section {
        text-align: center;
        margin-bottom: 24px;
      }

      .qr-container {
        display: flex;
        justify-content: center;
        margin: 16px 0;
        padding: 16px;
        background: white;
        border-radius: 24px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      }

      #qrcode {
        display: flex;
        justify-content: center;
        align-items: center;
      }

      #qrcode canvas,
      #qrcode img {
        display: block;
        width: 160px;
        height: 160px;
        max-width: 100%;
        height: auto;
      }

      /* Address Section */
      .address-section {
        background: var(--primary-soft);
        border-radius: 20px;
        padding: 18px;
        margin: 20px 0;
      }

      .wallet-address {
        background: var(--bg);
        padding: 12px;
        border-radius: 14px;
        font-family: monospace;
        font-size: 0.8rem;
        word-break: break-all;
        margin-bottom: 14px;
        border: 1px solid var(--glass-edge);
        text-align: center;
      }

      .copy-address-btn {
        background: var(--primary-color);
        border: none;
        padding: 12px 20px;
        border-radius: 40px;
        color: white;
        cursor: pointer;
        font-weight: 600;
        transition: 0.2s;
        width: 100%;
        font-size: 0.95rem;
      }

      /* Amount Request */
      .amount-request {
        margin-top: 24px;
      }

      .amount-request label {
        display: block;
        margin-bottom: 10px;
        font-weight: 500;
        color: var(--text-secondary);
      }

      .amount-input-group {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
      }

      .amount-input-group input {
        flex: 2;
        min-width: 120px;
        background: var(--input-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 12px 16px;
        color: var(--text);
        font-size: 0.95rem;
      }

      .amount-input-group input:focus {
        outline: none;
        border-color: var(--primary-color);
      }

      .set-amount-btn {
        background: var(--primary-color);
        border: none;
        padding: 12px 20px;
        border-radius: 28px;
        color: white;
        cursor: pointer;
        font-weight: 500;
        font-size: 0.9rem;
        white-space: nowrap;
      }

      /* Incoming Transactions */
      .incoming-list {
        margin-top: 16px;
        max-height: 400px;
        overflow-y: auto;
      }

      .incoming-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid var(--glass-edge);
        gap: 12px;
        flex-wrap: wrap;
      }

      .incoming-details {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 2;
        min-width: 160px;
      }

      .incoming-icon {
        width: 40px;
        height: 40px;
        background: var(--primary-soft);
        border-radius: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
        flex-shrink: 0;
      }

      .incoming-info h4 {
        font-size: 0.9rem;
        margin-bottom: 4px;
        word-break: break-word;
      }

      .incoming-info p {
        font-size: 0.7rem;
        color: var(--text-muted);
      }

      .incoming-amount {
        text-align: right;
        flex-shrink: 0;
      }

      .status-badge {
        padding: 4px 10px;
        border-radius: 40px;
        font-size: 0.7rem;
        font-weight: 500;
        display: inline-block;
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
        background: rgba(239, 68, 68, 0.18);
        color: #f87171;
      }

      .incoming-empty {
        padding: 24px 12px;
        text-align: center;
        color: var(--text-muted);
        font-size: 0.85rem;
        line-height: 1.45;
      }

      #scrollTop {
        position: fixed;
        right: 1rem;
        bottom: 5rem;
        background: var(--glass-bg);
        backdrop-filter: blur(10px);
        border: 1px solid var(--primary-color);
        color: var(--primary-color);
        width: 48px;
        height: 48px;
        border-radius: 50%;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 99;
      }

      /* ========== RESPONSIVE BREAKPOINTS ========== */
      @media (max-width: 900px) {
        .receive-grid {
          gap: 20px;
        }
        .receive-card {
          padding: 18px;
        }
      }

      @media (max-width: 768px) {
        .sidebar {
          position: fixed;
          transform: translateX(-100%);
          transition: transform 0.3s ease;
          z-index: 1000;
          width: 260px;
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
        .mobile-menu-btn {
          display: block;
        }
        .receive-grid {
          grid-template-columns: 1fr;
          gap: 24px;
        }
        .receive-container {
          padding: 16px 14px 32px;
        }
        .page-header h1 {
          font-size: 1.5rem;
        }
        .card-title {
          font-size: 1.2rem;
        }
        .crypto-btn {
          padding: 8px 14px;
          font-size: 0.8rem;
          white-space: nowrap;
        }
        .amount-input-group {
          flex-direction: column;
          align-items: stretch;
        }
        .amount-input-group input {
          width: 100%;
        }
        .set-amount-btn {
          width: 100%;
          text-align: center;
        }
        #qrcode canvas,
        #qrcode img {
          width: 140px;
          height: 140px;
        }
        .incoming-item {
          flex-direction: column;
          align-items: flex-start;
        }
        .incoming-amount {
          text-align: left;
          width: 100%;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }
        .header-left .user-profile .user-profile-meta {
          display: none;
        }
        .top-bar {
          padding: 12px 16px;
        }
      }

      @media (max-width: 480px) {
        .receive-container {
          padding: 12px 12px 28px;
        }
        .receive-card {
          padding: 16px;
          border-radius: 24px;
        }
        .crypto-buttons {
          gap: 8px;
        }
        .crypto-btn {
          padding: 6px 12px;
          font-size: 0.75rem;
        }
        .wallet-address {
          font-size: 0.7rem;
        }
        .copy-address-btn {
          padding: 10px;
        }
        .card-title i {
          font-size: 1.2rem;
        }
        #scrollTop {
          width: 42px;
          height: 42px;
          right: 0.8rem;
          bottom: 4.5rem;
        }
      }

      @media (max-width: 360px) {
        .crypto-btn span:not(i) {
          display: none;
        }
        .crypto-btn i {
          margin-right: 0;
          font-size: 1.2rem;
        }
        .crypto-btn {
          padding: 8px 10px;
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
          <div class="user-avatar-large"><?php echo htmlspecialchars($ub['avatar_letter'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="user-info">
            <h4><?php echo htmlspecialchars($ub['full_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
            <p><?php echo htmlspecialchars($ub['wallet_short'], ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section">MAIN</div>
          <a href="./userprofile.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a>
          <a href="./trade.php" class="sidebar-link"><i class="fa-solid fa-chart-line"></i> <span>Trade</span></a>
          <a href="./wallet.php" class="sidebar-link"><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a>
          <div class="nav-section">TRADING</div>
          <a href="./send.php" class="sidebar-link"><i class="fa-solid fa-paper-plane"></i> <span>Send</span></a>
          <a href="./receive.php" class="sidebar-link active"><i class="fa-solid fa-inbox"></i> <span>Receive</span></a>
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
            <a href="./userprofile.php" class="user-profile">
              <div class="user-avatar"><?php echo htmlspecialchars($ub['avatar_letter'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="user-profile-meta"><h3 class="user-name">Hello, <?php echo htmlspecialchars($ub['full_name'], ENT_QUOTES, 'UTF-8'); ?></h3></div>
            </a>
          </div>
          <div class="header-right">
            <div class="icon-button" id="notifications-btn"><i class="fa-solid fa-bell"></i></div>
            <div class="theme-toggle" id="theme-toggle"></div>
          </div>
        </div>
        <div class="receive-container">
          <div class="page-header"><h1><i class="fa-solid fa-inbox"></i> Receive</h1></div>
          <div class="balance-display">
            <div>Main wallet: $<span id="recv-main-usd"><?php echo htmlspecialchars($mainWalletUsd, ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div>Crypto: $<span id="recv-crypto-usd"><?php echo htmlspecialchars($cryptoPortfolioUsd, ENT_QUOTES, 'UTF-8'); ?></span></div>
          </div>
          <div class="crypto-buttons">
            <button type="button" class="crypto-btn active" data-crypto="BTC">BTC</button>
            <button type="button" class="crypto-btn" data-crypto="ETH">ETH</button>
            <button type="button" class="crypto-btn" data-crypto="USDT">USDT</button>
            <button type="button" class="crypto-btn" data-crypto="USDT_TRC20">USDT TRC20</button>
            <button type="button" class="crypto-btn" data-crypto="TRON">TRX</button>
            <button type="button" class="crypto-btn" data-crypto="SOL">SOL</button>
            <button type="button" class="crypto-btn" data-crypto="MATIC">MATIC</button>
          </div>
          <p id="networkName">Bitcoin (BTC)</p>
          <p id="receiveNetworkWarningText"></p>
          <div class="wallet-address" id="walletAddress"></div>
          <button type="button" class="copy-address-btn" id="copyAddressBtn"><i class="fas fa-copy"></i> Copy Address</button>
          <div id="qrcode"></div>
          <div class="amount-input">
            <input type="number" id="requestAmount" min="0" step="any" placeholder="Request amount" />
            <span id="amountSymbol">BTC</span>
            <button type="button" id="setAmountBtn">Set amount</button>
            <div id="amountMessage"></div>
          </div>
          <div id="incomingList"></div>
        </div>
      </main>
    </div>
    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="includes/user_theme.js"></script>
    <script src="js/qrcode.min.js"></script>
    <script>
      const CSRF_TOKEN = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;
      window.__RECEIVE_BOOT = <?php echo $receiveBootJson; ?>;

      const WALLET_API_SYNC = "./api/wallet_api.php";
      async function syncReceiveWalletSummary() {
        try {
          const res = await fetch(WALLET_API_SYNC, { credentials: "same-origin", headers: { Accept: "application/json" } });
          const data = await res.json();
          if (!data || !data.ok || !data.wallet) return;
          const w = data.wallet;
          const mainEl = document.getElementById("recv-main-usd");
          const cryptoEl = document.getElementById("recv-crypto-usd");
          const main = w.main_wallet_usd ?? w.wallet_balance_usd;
          const crypto = w.crypto_portfolio_usd ?? w.portfolio_value_usd;
          if (mainEl && typeof main === "string") mainEl.textContent = Number(main).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          if (cryptoEl && typeof crypto === "string") cryptoEl.textContent = Number(crypto).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } catch (_) {}
      }
      syncReceiveWalletSummary();
      setInterval(syncReceiveWalletSummary, 30000);

      const walletAddresses = { BTC: "bc1q2pg4lwsqtzx59pryzfvrgvp0kemwmwf8c3vkcy", ETH: "0x13ceE51d1859A001DA6bfFb13CCc94A20B3e3d25", USDT: "0x13ceE51d1859A001DA6bfFb13CCc94A20B3e3d25", USDT_TRC20: "TAeA9wpt2SF7jD4qQmin2Zd2wkjjH3WrsQ", TRON: "TAeA9wpt2SF7jD4qQmin2Zd2wkjjH3WrsQ", SOL: "apSWSvtL4guAoTJMun4tsDRZWvKvWBhJNHCactpKX4d", MATIC: "0x13ceE51d1859A001DA6bfFb13CCc94A20B3e3d25" };
      const networkNames = { BTC: "Bitcoin (BTC)", ETH: "Ethereum (ETH)", USDT: "Ethereum (ERC20)", USDT_TRC20: "Tron (TRC20)", TRON: "Tron (TRX)", SOL: "Solana (SOL)", MATIC: "solana (MATIC)" };
      const receiveOnlyAssetWarnings = { BTC: "Only send Bitcoin (BTC) to this address. Other assets will be lost forever.", ETH: "Only send Ethereum (ETH) to this address. Other assets will be lost forever.", USDT_TRC20: "Only send USDT (TRC20) on the Tron network to this address. Other assets will be lost forever.", USDT: "Only send USDT (ERC20) on Ethereum to this address. Other assets will be lost forever.", TRON: "Only send TRON (TRX) to this address. Other assets will be lost forever.", MATIC: "Only send MATIC on the Polygon network to this address. Other assets will be lost forever.", SOL: "Only send Solana (SOL) to this address. Other assets will be lost forever." };

      let currentCrypto = "BTC";
      let requestedAmount = null;

      function updateCryptoUI() {
        const address = walletAddresses[currentCrypto];
        document.getElementById("walletAddress").innerText = address;
        document.getElementById("networkName").innerHTML = networkNames[currentCrypto];
        document.getElementById("amountSymbol").innerText = currentCrypto;
        const qrContainer = document.getElementById("qrcode");
        if (qrContainer) {
          qrContainer.innerHTML = "";
          const qrText = address != null && address !== "" ? String(address) : "";
          if (typeof QRCode !== "undefined" && qrText) {
            try { new QRCode(qrContainer, { text: qrText, width: 180, height: 180, colorDark: "#000000", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.M }); } 
            catch (e) { qrContainer.textContent = qrText || "—"; }
          } else { qrContainer.textContent = qrText || "—"; }
        }
        const warnEl = document.getElementById("receiveNetworkWarningText");
        if (warnEl) warnEl.textContent = receiveOnlyAssetWarnings[currentCrypto] || `Only send ${networkNames[currentCrypto] || currentCrypto} to this address. Other assets will be lost forever.`;
      }

      document.querySelectorAll(".crypto-btn").forEach(btn => {
        btn.addEventListener("click", () => {
          document.querySelectorAll(".crypto-btn").forEach(b => b.classList.remove("active"));
          btn.classList.add("active");
          currentCrypto = btn.getAttribute("data-crypto");
          updateCryptoUI();
        });
      });

      const copyAddressBtn = document.getElementById("copyAddressBtn");
      const walletAddressSpan = document.getElementById("walletAddress");
      if (copyAddressBtn && walletAddressSpan) {
        copyAddressBtn.addEventListener("click", () => {
          navigator.clipboard.writeText(walletAddressSpan.innerText);
          const originalText = copyAddressBtn.innerHTML;
          copyAddressBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
          setTimeout(() => (copyAddressBtn.innerHTML = originalText), 2000);
          showNotification("Address copied to clipboard!");
        });
      }

      const setAmountBtn = document.getElementById("setAmountBtn");
      const requestAmount = document.getElementById("requestAmount");
      const amountMessage = document.getElementById("amountMessage");
      if (setAmountBtn && requestAmount && amountMessage) {
        setAmountBtn.addEventListener("click", () => {
          const amount = parseFloat(requestAmount.value);
          if (amount && amount > 0) {
            requestedAmount = amount;
            amountMessage.innerHTML = `<i class="fa-solid fa-check-circle" style="color: var(--primary-color);"></i> Requesting ${amount} ${currentCrypto}. Share this amount with sender.`;
            updateCryptoUI();
          } else {
            requestedAmount = null;
            amountMessage.innerHTML = "";
          }
        });
      }

      function incomingIconHtml(icon) {
        const map = { btc: '<i class="fab fa-bitcoin"></i>', eth: '<i class="fab fa-ethereum"></i>', usdt: '<i class="fas fa-dollar-sign"></i>', sol: '<i class="fas fa-bolt"></i>', tron: '<i class="fas fa-dollar-sign"></i>' };
        return map[icon] || '<i class="fas fa-coins"></i>';
      }

      function escapeIncomingText(s) { return String(s ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }

      function formatIncomingTime(iso) { if (!iso) return ""; const d = new Date(String(iso).replace(" ", "T")); return isNaN(d.getTime()) ? String(iso) : d.toLocaleString(undefined, { dateStyle: "medium", timeStyle: "short" }); }

      async function loadIncomingTransactions() {
        const el = document.getElementById("incomingList");
        if (!el) return;
        try {
          const res = await fetch("./receive.php?ajax=incoming", { credentials: "same-origin", headers: { Accept: "application/json" } });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok || !Array.isArray(data.transactions)) { el.innerHTML = '<div class="incoming-empty">Could not load transactions. Try again later.</div>'; return; }
          if (data.transactions.length === 0) { el.innerHTML = '<div class="incoming-empty">No incoming transactions yet. Deposits and wallet funding from your team will appear here.</div>'; return; }
          el.innerHTML = data.transactions.map(row => {
            const sc = ["status-completed", "status-pending", "status-failed"].includes(row.status_class) ? row.status_class : "status-pending";
            return `<div class="incoming-item" data-tx-id="${Number(row.id)}"><div class="incoming-details"><div class="incoming-icon">${incomingIconHtml(row.icon)}</div><div class="incoming-info"><h4>${escapeIncomingText(row.title)}</h4><p>${escapeIncomingText(row.subtitle)}</p><p style="font-size:0.68rem;color:var(--text-muted);margin-top:2px">${escapeIncomingText(formatIncomingTime(row.created_at))}</p></div></div><div class="incoming-amount"><div class="amount">${escapeIncomingText(row.amount_display)}</div><span class="status-badge ${sc}">${escapeIncomingText(row.status)}</span></div></div>`;
          }).join("");
        } catch (_) { el.innerHTML = '<div class="incoming-empty">Could not load transactions.</div>'; }
      }

      function showNotification(message) {
        const notif = document.createElement("div");
        notif.className = "notification-popup";
        notif.style.cssText = "position:fixed;top:80px;right:24px;background:var(--surface);padding:12px 20px;border-radius:40px;border-left:4px solid var(--primary-color);z-index:200;font-size:0.85rem;box-shadow:0 4px 12px rgba(0,0,0,0.2);max-width:calc(100% - 48px);";
        notif.innerHTML = message;
        document.body.appendChild(notif);
        setTimeout(() => notif.remove(), 3000);
      }

      const notifBtn = document.getElementById("notifications-btn");
      if (notifBtn) notifBtn.addEventListener("click", () => showNotification("📢 New feature: Request specific amounts when receiving crypto!"));

      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      const mobileBtn = document.getElementById("mobileMenuBtn");
      if (sidebar && collapseBtn) collapseBtn.addEventListener("click", () => sidebar.classList.toggle("collapsed"));
      if (sidebar && mobileBtn) mobileBtn.addEventListener("click", () => sidebar.classList.toggle("mobile-open"));
      document.addEventListener("click", (e) => { if (sidebar && mobileBtn && window.innerWidth <= 768 && !sidebar.contains(e.target) && !mobileBtn.contains(e.target) && sidebar.classList.contains("mobile-open")) sidebar.classList.remove("mobile-open"); });

      const scrollBtn = document.getElementById("scrollTop");
      if (scrollBtn) {
        window.addEventListener("scroll", () => { window.scrollY > 400 ? scrollBtn.style.display = "flex" : scrollBtn.style.display = "none"; });
        scrollBtn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
      }

      updateCryptoUI();
      loadIncomingTransactions();
      setInterval(loadIncomingTransactions, 25000);
      document.addEventListener("visibilitychange", () => { if (document.visibilityState === "visible") loadIncomingTransactions(); });
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>