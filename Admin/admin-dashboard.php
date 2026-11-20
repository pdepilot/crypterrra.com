<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
require_once __DIR__ . '/includes/admin_dashboard_helpers.php';
require_once __DIR__ . '/includes/admin_dashboard_data.php';

$metrics = [
  'total_users' => 0,
  'active_users' => 0,
  'total_deposits_usd' => 0.0,
  'total_withdrawals_usd' => 0.0,
  'platform_balance_usd' => 0.0,
  'daily_volume_usd' => 0.0,
];

try {
  $db = admin_dash_db();
  // metrics (existing helpers)
  $metrics = admin_dashboard_metrics($db);
  // dashboard tables
  $dashUsers = admin_dash_fetch_users($db, 8);
  $dashTx = admin_dash_fetch_recent_transactions($db, 6);
  $dashAlerts = admin_dash_fetch_fraud_alerts($db, 2);
  $dashKyc = admin_dash_fetch_kyc_pending($db, 6);
  $dashWallets = admin_dash_fetch_wallet_controls($db, 6);
  $dashPairs = admin_dash_fetch_market_pairs($db, 6);
  // charts
  $chartUserGrowth = admin_dash_chart_user_growth($db);
  $chartRevenue = admin_dash_chart_revenue_weekly($db);
  $chartVolume = admin_dash_chart_volume_today($db);
  $chartDvSw = admin_dash_chart_deposit_vs_withdrawal($db);
  $db->close();
} catch (Throwable) {
  // Render page with safe defaults if DB is unavailable.
}

// Logs come from contact module DB (best-effort).
$dashLogs = admin_dash_fetch_admin_activity_logs(20);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · Admin Control Center (fully responsive)</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 (free) -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    />
    <!-- Google Fonts Inter & Space Grotesk (futuristic) -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <!-- AOS scroll animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <!-- Chart.js 4 for interactive charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      :root {
        --primary: #22c55e;
        --primary-light: #4ade80;
        --primary-dark: #16a34a;
        --primary-glow: rgba(34, 197, 94, 0.5);
        --primary-soft: rgba(34, 197, 94, 0.15);
        --danger: #ef4444;
        --warning: #f59e0b;
        --success: #10b981;
        --info: #3b82f6;

        --bg: #0a0f1a;
        --bg-secondary: #0e1422;
        --surface: #141b2b;
        --surface-light: #1e2740;
        --text: #f1f5f9;
        --text-secondary: #b0c0d0;
        --text-muted: #7f8ea3;
        --glass-bg: rgba(20, 30, 45, 0.5);
        --glass-edge: rgba(34, 197, 94, 0.25);
        --card-bg: rgba(20, 30, 50, 0.6);
        --shadow: 0 20px 40px -12px rgba(0, 0, 0, 0.8);
        --shadow-glow: 0 0 30px var(--primary-glow);
        --border: #1f2a3f;
      }

      body {
        font-family: "Inter", "Space Grotesk", sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.5;
        overflow-x: hidden;
      }

      /* ========== ADMIN LAYOUT ========== */
      .admin {
        display: flex;
        min-height: 100vh;
      }

      /* ========== SIDEBAR (collapsible, glass, fully responsive) ========== */
      .sidebar {
        width: 280px;
        background: rgba(10, 20, 35, 0.9);
        backdrop-filter: blur(20px) saturate(200%);
        -webkit-backdrop-filter: blur(20px) saturate(200%);
        border-right: 1px solid var(--glass-edge);
        padding: 1.8rem 1.2rem;
        transition:
          width 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1),
          left 0.3s;
        display: flex;
        flex-direction: column;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        scrollbar-width: none;
        z-index: 100;
        box-shadow: 10px 0 40px -15px rgba(0, 0, 0, 0.8);
      }
      .sidebar::-webkit-scrollbar {
        display: none;
      }
      .sidebar.collapsed {
        width: 95px;
      }
      .sidebar.collapsed .sidebar-link span,
      .sidebar.collapsed .sidebar-header span,
      .sidebar.collapsed .admin-info {
        display: none;
      }

      /* mobile sidebar */
      @media (max-width: 1100px) {
        .sidebar {
          position: fixed;
          left: -300px;
        }
        .sidebar.mobile-open {
          left: 0;
        }
      }

      .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 2.2rem;
        padding: 0 0.5rem;
      }
      .logo {
        display: flex;
        align-items: center;
        gap: 0.8rem;
      }
      .logo-icon {
        width: 42px;
        height: 42px;
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        box-shadow: 0 0 30px var(--primary-glow);
      }
      .logo span {
        font-size: 1.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }
      .collapse-btn {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 50%;
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--primary);
        transition: 0.3s;
      }
      .collapse-btn:hover {
        background: var(--primary);
        color: white;
        transform: rotate(180deg);
        box-shadow: var(--shadow-glow);
      }

      .admin-info {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
      }
      .admin-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: white;
        font-weight: 600;
        border: 2px solid var(--primary);
        box-shadow: 0 0 20px var(--primary-glow);
      }
      .admin-details h4 {
        font-size: 0.95rem;
      }
      .admin-details p {
        font-size: 0.7rem;
        color: var(--text-muted);
      }

      .sidebar-nav {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
      }
      .nav-section {
        margin: 1rem 0 0.3rem;
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
        transition: 0.3s;
        white-space: nowrap;
        position: relative;
        overflow: hidden;
      }
      .sidebar-link i {
        width: 24px;
        font-size: 1.2rem;
        text-align: center;
      }
      .sidebar-link::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 0;
        background: linear-gradient(90deg, var(--primary-soft), transparent);
        transition: width 0.3s;
        z-index: -1;
      }
      .sidebar-link:hover::before,
      .sidebar-link.active::before {
        width: 100%;
      }
      .sidebar-link:hover,
      .sidebar-link.active {
        color: var(--primary);
        transform: translateX(5px);
        box-shadow: 0 0 20px var(--primary-glow);
      }
      .sidebar-link.active {
        background: var(--primary-soft);
        border: 1px solid var(--glass-edge);
      }

      /* ========== MAIN CONTENT ========== */
      .main {
        flex: 1;
        background: var(--bg-secondary);
        width: 100%;
        overflow-x: hidden;
      }

      /* top header */
      .top-bar {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--glass-edge);
        padding: 0.8rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.8rem;
        position: sticky;
        top: 0;
        z-index: 90;
      }
      .header-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
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
        color: var(--primary);
        flex-shrink: 0;
      }
      .search-box {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 8px 16px;
        display: flex;
        align-items: center;
        gap: 8px;
      }
      .search-box i {
        color: var(--text-muted);
      }
      .search-box input {
        background: transparent;
        border: none;
        color: var(--text);
        outline: none;
        font-size: 0.9rem;
        width: 200px;
      }
      .header-right {
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
      }
      .system-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
      }
      .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--success);
        box-shadow: 0 0 15px #10b981;
        animation: pulse 2s infinite;
      }
      @keyframes pulse {
        0% {
          opacity: 0.8;
        }
        50% {
          opacity: 1;
        }
        100% {
          opacity: 0.8;
        }
      }
      .icon-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        cursor: pointer;
        transition: 0.2s;
        position: relative;
        flex-shrink: 0;
      }
      .icon-btn:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }
      .badge {
        position: absolute;
        top: 5px;
        right: 5px;
        width: 8px;
        height: 8px;
        background: var(--danger);
        border-radius: 50%;
        border: 2px solid var(--glass-bg);
      }
      .theme-toggle {
        width: 52px;
        height: 26px;
        background: #3a4a5a;
        border-radius: 34px;
        position: relative;
        cursor: pointer;
        border: 1px solid var(--glass-edge);
        flex-shrink: 0;
      }
      .theme-toggle::before {
        content: "";
        position: absolute;
        width: 20px;
        height: 20px;
        background: white;
        border-radius: 50%;
        top: 2px;
        left: 2px;
        transition: 0.3s;
      }
      body.dark .theme-toggle {
        background: var(--primary);
      }
      body.dark .theme-toggle::before {
        transform: translateX(26px);
      }

      /* content area */
      .content {
        padding: 1.5rem;
      }

      /* metric cards - responsive grid */
      .metric-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.2rem;
        margin-bottom: 1.8rem;
      }
      .metric-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 1.5rem;
        transition: 0.3s;
        box-shadow: var(--shadow);
      }
      .metric-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-glow);
        transform: translateY(-5px);
      }
      .metric-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 8px;
      }
      .metric-icon {
        width: 48px;
        height: 48px;
        border-radius: 20px;
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--primary);
      }
      .metric-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1.2;
      }
      .metric-change {
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 4px;
      }
      .positive {
        color: var(--success);
      }
      .negative {
        color: var(--danger);
      }
      .spark {
        height: 30px;
        width: 100%;
        max-width: 80px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 20px;
        margin-top: 8px;
      }

      /* chart grid - responsive */
      .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.2rem;
        margin-bottom: 1.8rem;
      }
      .chart-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }
      .chart-card h3 {
        margin-bottom: 1rem;
        font-size: 1.1rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 8px;
      }
      canvas {
        max-height: 200px;
        width: 100% !important;
      }

      /* tables & sections */
      .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 1.5rem 0 1rem;
        flex-wrap: wrap;
        gap: 12px;
      }
      .glass-table-container {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.2rem;
        overflow-x: auto;
        margin-bottom: 1.5rem;
        width: 100%;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
      }
      th {
        text-align: left;
        padding: 1rem 0.5rem;
        color: var(--primary);
        border-bottom: 2px solid var(--glass-edge);
      }
      td {
        padding: 1rem 0.5rem;
        border-bottom: 1px solid var(--glass-edge);
      }
      tr:hover {
        background: var(--primary-soft);
      }
      .badge-status {
        padding: 0.2rem 0.8rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
      }
      .badge-green {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }
      .badge-yellow {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }
      .badge-red {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .action-btn {
        background: transparent;
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 0.3rem 0.8rem;
        color: var(--text);
        cursor: pointer;
        transition: 0.2s;
        margin: 0 2px;
        font-size: 0.8rem;
      }
      .action-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
      }

      /* fraud alerts - responsive grid */
      .alert-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1rem;
        margin: 1rem 0;
      }
      .alert-card {
        background: rgba(239, 68, 68, 0.1);
        border-left: 4px solid var(--danger);
        border-radius: 30px;
        padding: 1.2rem;
        display: flex;
        gap: 1rem;
        align-items: center;
      }
      .alert-icon {
        font-size: 2rem;
        color: var(--danger);
        flex-shrink: 0;
      }
      .alert-content {
        flex: 1;
        min-width: 0;
      }
      .alert-title {
        font-weight: 600;
      }
      .alert-time {
        font-size: 0.75rem;
        color: var(--text-muted);
      }

      /* system health */
      .health-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin: 1rem 0 1.5rem;
      }
      .health-item {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 1.2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
      }
      .health-led {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        flex-shrink: 0;
      }
      .led-green {
        background: var(--success);
        box-shadow: 0 0 20px #10b981;
      }
      .led-yellow {
        background: var(--warning);
        box-shadow: 0 0 20px #f59e0b;
      }
      .led-red {
        background: var(--danger);
        box-shadow: 0 0 20px #ef4444;
      }

      /* two column layouts - responsive */
      .two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem;
        margin-bottom: 1.5rem;
      }
      @media (max-width: 800px) {
        .two-col {
          grid-template-columns: 1fr;
        }
      }

      /* floating AI assistant */
      .ai-assistant {
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        z-index: 200;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        color: white;
        cursor: pointer;
        box-shadow: var(--shadow-glow);
        transition: 0.3s;
        animation: float 4s infinite;
      }
      .ai-assistant:hover {
        transform: scale(1.1);
        background: var(--primary-dark);
      }
      @keyframes float {
        0% {
          transform: translateY(0);
        }
        50% {
          transform: translateY(-8px);
        }
        100% {
          transform: translateY(0);
        }
      }

      /* ========== RESPONSIVE BREAKPOINTS ========== */
      @media (max-width: 1100px) {
        .mobile-menu-btn {
          display: flex;
        }
        .search-box input {
          width: 150px;
        }
      }
      @media (max-width: 768px) {
        .content {
          padding: 1rem;
        }
        .top-bar {
          padding: 0.8rem 1rem;
        }
        .search-box {
          order: 3;
          width: 100%;
        }
        .search-box input {
          width: 100%;
        }
        .header-left {
          width: 100%;
          justify-content: space-between;
        }
        .header-right {
          width: 100%;
          justify-content: flex-end;
        }
        .metric-grid {
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        .chart-grid {
          grid-template-columns: 1fr;
        }
      }
      @media (max-width: 480px) {
        .metric-grid {
          grid-template-columns: 1fr;
        }
        .health-grid {
          grid-template-columns: 1fr;
        }
        .system-status span:last-child {
          display: none;
        } /* hide "System Online" text on very small */
        .admin-avatar {
          width: 36px;
          height: 36px;
          font-size: 1rem;
        }
        .icon-btn {
          width: 36px;
          height: 36px;
        }
        .ai-assistant {
          width: 48px;
          height: 48px;
          font-size: 1.5rem;
          bottom: 1rem;
          right: 1rem;
        }
      }

      /* loading skeletons animation */
      .spark {
        background: linear-gradient(
          90deg,
          var(--primary-soft) 0%,
          var(--primary-light) 50%,
          var(--primary-soft) 100%
        );
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
      }
      @keyframes shimmer {
        0% {
          background-position: -100% 0;
        }
        100% {
          background-position: 100% 0;
        }
      }
    </style>
  </head>
  <body class="dark">
    <div class="admin">
      <!-- SIDEBAR (admin) -->
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <div class="logo">
            <div class="logo-icon"><i class="fa-solid fa-globe"></i></div>
            <span>Crypterra</span>
          </div>
          <div class="collapse-btn" id="collapseSidebar">
            <i class="fa-solid fa-chevron-left"></i>
          </div>
        </div>
        <div class="admin-info">
          <div class="admin-avatar">SA</div>
          <div class="admin-details">
            <h4>Super Admin</h4>
            <p>sysadmin@crypterra.io</p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section">MAIN</div>
          <a href="./admin-dashboard.php" class="sidebar-link active"
            ><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a
          >
          <a href="./admin-users.php" class="sidebar-link"
            ><i class="fa-solid fa-users"></i> <span>Users</span></a
          >
          <a href="./admin-send.php" class="sidebar-link"
            ><i class="fa-solid fa-paper-plane"></i> <span>Send Mgmt</span></a
          >
          <a href="./admin-receive.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-down"></i> <span>Receive Mgmt</span></a
          >
          <a href="./admin-transactions.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-right-arrow-left"></i>
            <span>Transactions</span></a
          >
          <a href="./admin-contact.php" class="sidebar-link"
            ><i class="fa-solid fa-envelope"></i>
            <span>Contact Mgmt</span></a
          >
          <a href="./admin-help-center.php" class="sidebar-link"
            ><i class="fa-solid fa-life-ring"></i>
            <span>Help Center</span></a
          >

         
          <a href="./admin-deposit.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-down"></i> <span>Deposits</span></a
          >
          <a href="./admin-withdrawal.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-up"></i> <span>Withdrawals</span></a
          >
          <a href="./admin-kyc.php" class="sidebar-link"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Management</span></a
          >
          <a href="./admin-wallet-control.php" class="sidebar-link"
            ><i class="fa-solid fa-wallet"></i> <span>Wallet Control</span></a
          >
          <a href="./admin-market-control.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-line"></i>
            <span>Market Control</span></a
          >
          <a href="./admin-security-center.php" class="sidebar-link"
            ><i class="fa-solid fa-shield"></i> <span>Security Center</span></a
          >
          <a href="./admin-analytics.php" class="sidebar-link"
            ><i class="fa-solid fa-magnifying-glass-chart"></i>
            <span>Analytics</span></a
          >
          <a href="./admin-notifications.php" class="sidebar-link"
            ><i class="fa-solid fa-bell"></i> <span>Notifications</span></a
          >
          <a href="./admin-settings.php" class="sidebar-link"
            ><i class="fa-solid fa-gear"></i> <span>Settings</span></a
          >
          <div style="margin-top: 2rem"></div>
          <a href="./admin-logout.php" class="sidebar-link"
            ><i class="fa-solid fa-sign-out-alt"></i> <span>Logout</span></a
          >
        </nav>
      </aside>

      <!-- MAIN CONTENT -->
      <main class="main">
        <!-- TOP BAR -->
        <div class="top-bar">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn">
              <i class="fa-solid fa-bars"></i>
            </div>
            <div class="search-box">
              <i class="fa-solid fa-search"></i>
              <input type="text" placeholder="Search admin..." />
            </div>
          </div>
          <div class="header-right">
            <div class="system-status">
              <span class="status-dot"></span>
              <span>System Online</span>
            </div>
            <div class="icon-btn">
              <i class="fa-regular fa-bell"></i>
              <span class="badge"></span>
            </div>
            <div class="icon-btn"><i class="fa-regular fa-envelope"></i></div>
            <div class="theme-toggle" id="themeToggle"></div>
            <div
              class="icon-btn"
              style="
                width: auto;
                padding: 0 12px;
                border-radius: 40px;
                gap: 6px;
              "
            >
              <i class="fa-regular fa-user"></i>
              <span class="admin-short">SA</span>
            </div>
          </div>
        </div>

        <!-- DASHBOARD CONTENT -->
        <div class="content">
          <!-- METRIC CARDS -->
          <div class="metric-grid" data-aos="fade-up">
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"
                  ><i class="fa-solid fa-users"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +5.2%</span
                >
              </div>
              <div class="metric-value"><?php echo admin_h(admin_format_compact_number((int)$metrics['total_users'])); ?></div>
              <div>Total Users</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"
                  ><i class="fa-solid fa-user-check"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +2.1%</span
                >
              </div>
              <div class="metric-value"><?php echo admin_h(admin_format_compact_number((int)$metrics['active_users'])); ?></div>
              <div>Active Users</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"
                  ><i class="fa-solid fa-circle-dollar"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +12%</span
                >
              </div>
              <div class="metric-value"><?php echo admin_h(admin_format_usd_compact((float)$metrics['total_deposits_usd'])); ?></div>
              <div>Total Deposits</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"
                  ><i class="fa-solid fa-money-bill-trend-up"></i></span
                ><span class="metric-change negative"
                  ><i class="fa-solid fa-arrow-down"></i> -3.1%</span
                >
              </div>
              <div class="metric-value"><?php echo admin_h(admin_format_usd_compact((float)$metrics['total_withdrawals_usd'])); ?></div>
              <div>Withdrawals</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"
                  ><i class="fa-solid fa-scale-balanced"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +7.4%</span
                >
              </div>
              <div class="metric-value"><?php echo admin_h(admin_format_usd_compact((float)$metrics['platform_balance_usd'])); ?></div>
              <div>Platform Balance</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"
                  ><i class="fa-solid fa-chart-simple"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +18%</span
                >
              </div>
              <div class="metric-value"><?php echo admin_h(admin_format_usd_compact((float)$metrics['daily_volume_usd'])); ?></div>
              <div>Daily Volume</div>
              <div class="spark"></div>
            </div>
          </div>

          <!-- CHARTS -->
          <div class="chart-grid" data-aos="fade-up">
            <div class="chart-card">
              <h3>
                <i
                  class="fa-regular fa-chart-line"
                  style="color: var(--primary)"
                ></i>
                User Growth
              </h3>
              <canvas id="userChart"></canvas>
            </div>
            <div class="chart-card">
              <h3>
                <i
                  class="fa-regular fa-chart-bar"
                  style="color: var(--primary)"
                ></i>
                Revenue (weekly)
              </h3>
              <canvas id="revenueChart"></canvas>
            </div>
            <div class="chart-card">
              <h3>
                <i
                  class="fa-regular fa-chart-line"
                  style="color: var(--primary)"
                ></i>
                Trading Volume
              </h3>
              <canvas id="volumeChart"></canvas>
            </div>
            <div class="chart-card">
              <h3>
                <i
                  class="fa-regular fa-chart-line"
                  style="color: var(--primary)"
                ></i>
                Deposit vs Withdrawal
              </h3>
              <canvas id="dvswChart"></canvas>
            </div>
          </div>

          <!-- USER MANAGEMENT TABLE -->
          <div class="section-header">
            <h2>
              <i class="fa-solid fa-users" style="color: var(--primary)"></i>
              User Management
            </h2>
            <div>
              <input
                type="text"
                placeholder="Filter..."
                style="
                  background: var(--glass-bg);
                  border: 1px solid var(--glass-edge);
                  border-radius: 40px;
                  padding: 8px 16px;
                  color: var(--text);
                  width: 200px;
                  max-width: 100%;
                "
              />
            </div>
          </div>
          <div class="glass-table-container">
            <table>
              <thead>
                <tr>
                  <th>User ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Country</th>
                  <th>Status</th>
                  <th>Balance</th>
                  <th>KYC</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
<?php if (!empty($dashUsers)): ?>
<?php foreach ($dashUsers as $u): ?>
                <tr>
                  <td>#C<?php echo admin_h((string)$u['id']); ?></td>
                  <td><?php echo admin_h((string)$u['name']); ?></td>
                  <td><?php echo admin_h((string)$u['email']); ?></td>
                  <td><?php echo admin_h((string)$u['country']); ?></td>
                  <td>
                    <span class="badge-status <?php echo admin_h(admin_dash_badge_class_status((string)$u['status'])); ?>">
                      <?php echo admin_h((string)$u['status']); ?>
                    </span>
                  </td>
                  <td><?php echo admin_h('$' . number_format((float)$u['balance_usd'], 2)); ?></td>
                  <td>
                    <span class="badge-status <?php echo admin_h(admin_dash_badge_class_status((string)$u['kyc'])); ?>">
                      <?php echo admin_h((string)$u['kyc']); ?>
                    </span>
                  </td>
                  <td>
                    <a class="action-btn" href="./admin-users.php">View</a>
                    <a class="action-btn" href="./admin-users.php">Manage</a>
                  </td>
                </tr>
<?php endforeach; ?>
<?php else: ?>
                <tr>
                  <td colspan="8" style="color: var(--text-muted)">No users found.</td>
                </tr>
<?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- TRANSACTION FEED + FRAUD ALERTS (responsive two-col) -->
          <div class="two-col">
            <div class="glass-table-container">
              <h3 style="margin-bottom: 1rem">
                <i class="fa-solid fa-clock" style="color: var(--primary)"></i>
                Real-time transactions
              </h3>
              <table style="min-width: 500px">
<?php if (!empty($dashTx)): ?>
<?php foreach ($dashTx as $t): ?>
                <tr>
                  <td>
                    <i
                      class="fa-solid <?php echo admin_h(strtolower($t['type']) === 'deposit' ? 'fa-arrow-down' : (strtolower($t['type']) === 'withdrawal' ? 'fa-arrow-up' : 'fa-arrow-right-arrow-left')); ?>"
                      style="color: <?php echo admin_h(strtolower($t['type']) === 'deposit' ? 'var(--success)' : (strtolower($t['type']) === 'withdrawal' ? 'var(--warning)' : 'var(--primary)')); ?>"
                    ></i>
                    <?php echo admin_h((string)$t['type']); ?>
                  </td>
                  <td><?php echo admin_h((string)$t['amount_label']); ?></td>
                  <td><?php echo admin_h((string)$t['user_label']); ?></td>
                  <td>
                    <span class="badge-status <?php echo admin_h(admin_dash_badge_class_status((string)$t['status'])); ?>">
                      <?php echo admin_h((string)$t['status']); ?>
                    </span>
                  </td>
                  <td style="text-align:right;">
<?php if (($t['type_raw'] ?? '') === 'withdrawal' && ($t['status_raw'] ?? '') === 'pending'): ?>
                    <button class="action-btn" onclick="dashTxAction('approve', <?php echo (int)$t['id']; ?>)">Approve</button>
                    <button class="action-btn" style="border-color: var(--danger)" onclick="dashTxAction('reject', <?php echo (int)$t['id']; ?>)">Decline</button>
<?php else: ?>
                    <a class="action-btn" href="./admin-transactions.php">View</a>
<?php endif; ?>
                  </td>
                </tr>
<?php endforeach; ?>
<?php else: ?>
                <tr>
                  <td colspan="5" style="color: var(--text-muted)">No transactions yet.</td>
                </tr>
<?php endif; ?>
              </table>
            </div>
            <div>
              <h3 style="margin-bottom: 1rem">
                <i
                  class="fa-solid fa-triangle-exclamation"
                  style="color: var(--danger)"
                ></i>
                Fraud alerts
              </h3>
              <div class="alert-grid" style="grid-template-columns: 1fr">
<?php if (!empty($dashAlerts)): ?>
<?php foreach ($dashAlerts as $a): ?>
                <div class="alert-card">
                  <i class="<?php echo admin_h((string)$a['icon']); ?> alert-icon"></i>
                  <div class="alert-content">
                    <div class="alert-title"><?php echo admin_h((string)$a['title']); ?></div>
                    <div><?php echo admin_h((string)$a['detail']); ?></div>
                    <div class="alert-time"><?php echo admin_h((string)$a['time']); ?></div>
                  </div>
                </div>
<?php endforeach; ?>
<?php else: ?>
                <div class="alert-card" style="border-left-color: var(--glass-edge); background: rgba(34,197,94,0.06);">
                  <i class="fa-solid fa-shield alert-icon" style="color: var(--primary)"></i>
                  <div class="alert-content">
                    <div class="alert-title">No alerts</div>
                    <div>Nothing flagged right now.</div>
                    <div class="alert-time">—</div>
                  </div>
                </div>
<?php endif; ?>
              </div>
            </div>
          </div>

          <!-- KYC PANEL + WALLET CONTROL (responsive two-col) -->
          <div class="two-col">
            <div class="glass-table-container">
              <h3>
                <i
                  class="fa-solid fa-id-card"
                  style="color: var(--primary)"
                ></i>
                KYC pending reviews
              </h3>
              <table style="min-width: 400px">
<?php if (!empty($dashKyc)): ?>
<?php foreach ($dashKyc as $k): ?>
                <tr>
                  <td>ID: #C<?php echo admin_h((string)$k['id']); ?></td>
                  <td><?php echo admin_h((string)$k['name']); ?></td>
                  <td>
                    <a class="action-btn" href="./admin-kyc.php">Review</a>
                    <a class="action-btn" href="./admin-kyc.php">Approve</a>
                    <a class="action-btn" href="./admin-kyc.php" style="border-color: var(--danger)">Reject</a>
                  </td>
                </tr>
<?php endforeach; ?>
<?php else: ?>
                <tr>
                  <td colspan="3" style="color: var(--text-muted)">No KYC queue found (add `users.kyc_status` = 'pending' to enable).</td>
                </tr>
<?php endif; ?>
              </table>
            </div>
            <div class="glass-table-container">
              <h3>
                <i class="fa-solid fa-wallet" style="color: var(--primary)"></i>
                Wallet controls
              </h3>
              <table style="min-width: 400px">
<?php if (!empty($dashWallets)): ?>
<?php foreach ($dashWallets as $w): ?>
                <tr>
                  <td><?php echo admin_h((string)$w['wallet']); ?></td>
                  <td><?php echo admin_h((string)$w['name']); ?></td>
                  <td>
                    <a class="action-btn" href="./admin-wallet-control.php">Freeze</a>
                    <a class="action-btn" href="./admin-wallet-control.php">Monitor</a>
                  </td>
                </tr>
<?php endforeach; ?>
<?php else: ?>
                <tr>
                  <td colspan="3" style="color: var(--text-muted)">No connected wallets found.</td>
                </tr>
<?php endif; ?>
              </table>
            </div>
          </div>

          <!-- SYSTEM HEALTH -->
          <h3>
            <i
              class="fa-solid fa-heart-pulse"
              style="color: var(--primary)"
            ></i>
            System health
          </h3>
          <div class="health-grid">
            <div class="health-item">
              <span class="health-led led-green"></span>
              <div><strong>Core API</strong><br />operational</div>
            </div>
            <div class="health-item">
              <span class="health-led led-green"></span>
              <div><strong>Database</strong><br />healthy</div>
            </div>
            <div class="health-item">
              <span class="health-led led-yellow"></span>
              <div><strong>Price feed</strong><br />delayed 2s</div>
            </div>
            <div class="health-item">
              <span class="health-led led-green"></span>
              <div><strong>WebSocket</strong><br />connected</div>
            </div>
          </div>

          <!-- ACTIVITY LOGS -->
          <div class="glass-table-container" style="margin-top: 0.5rem">
            <h3>
              <i
                class="fa-solid fa-clock-rotate-left"
                style="color: var(--primary)"
              ></i>
              Admin activity logs
            </h3>
            <table style="min-width: 600px">
<?php if (!empty($dashLogs)): ?>
<?php foreach ($dashLogs as $l): ?>
              <tr>
                <td><?php echo admin_h((string)$l['time']); ?></td>
                <td><?php echo admin_h((string)$l['admin']); ?></td>
                <td><?php echo admin_h((string)$l['action']); ?></td>
              </tr>
<?php endforeach; ?>
<?php else: ?>
              <tr>
                <td colspan="3" style="color: var(--text-muted)">No admin activity logs yet.</td>
              </tr>
<?php endif; ?>
            </table>
          </div>

          <!-- MARKET CONTROL -->
          <div class="glass-table-container" style="margin-top: 1rem">
            <h3>
              <i
                class="fa-solid fa-chart-line"
                style="color: var(--primary)"
              ></i>
              Market control (pairs)
            </h3>
            <table style="min-width: 500px">
              <tr>
                <th>Pair</th>
                <th>Status</th>
                <th>Fee</th>
                <th>Action</th>
              </tr>
<?php if (!empty($dashPairs)): ?>
<?php foreach ($dashPairs as $p): ?>
              <tr>
                <td><?php echo admin_h((string)$p['pair']); ?></td>
                <td>
                  <span class="badge-status <?php echo admin_h(admin_dash_badge_class_status((string)$p['status'])); ?>">
                    <?php echo admin_h((string)$p['status']); ?>
                  </span>
                </td>
                <td><?php echo admin_h((string)$p['fee']); ?></td>
                <td><a class="action-btn" href="./admin-market-control.php"><?php echo admin_h((string)$p['action']); ?></a></td>
              </tr>
<?php endforeach; ?>
<?php else: ?>
              <tr>
                <td colspan="4" style="color: var(--text-muted)">No traded pairs yet.</td>
              </tr>
<?php endif; ?>
            </table>
          </div>
        </div>
      </main>
    </div>

    <!-- FLOATING AI ASSISTANT -->
    <div class="ai-assistant" id="aiBtn" title="AI admin assistant">
      <i class="fa-solid fa-robot"></i>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      const DASH_API = "./api/transactions_api.php";
      const DASH_CSRF = <?php echo json_encode(admin_csrf_token(), JSON_UNESCAPED_SLASHES); ?>;
      AOS.init({ duration: 700, once: true });
      async function dashTxAction(action, id) {
        try {
          const r = await fetch(DASH_API, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({ action, id, csrf_token: DASH_CSRF }),
          });
          const data = await r.json();
          if (data && data.ok) {
            location.reload();
          } else {
            alert(data && data.error ? "Action failed: " + data.error : "Action failed.");
          }
        } catch (e) {
          alert("Request failed.");
        }
      }

      // sidebar collapse
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      collapseBtn.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");
        const icon = collapseBtn.querySelector("i");
        icon.classList.toggle("fa-chevron-left");
        icon.classList.toggle("fa-chevron-right");
      });

      // mobile menu
      const mobileBtn = document.getElementById("mobileMenuBtn");
      mobileBtn.addEventListener("click", () => {
        sidebar.classList.toggle("mobile-open");
      });
      document.addEventListener("click", (e) => {
        if (
          window.innerWidth <= 1100 &&
          !sidebar.contains(e.target) &&
          !mobileBtn.contains(e.target)
        ) {
          sidebar.classList.remove("mobile-open");
        }
      });

      // theme toggle (simple)
      const themeToggle = document.getElementById("themeToggle");
      themeToggle.addEventListener("click", () => {
        document.body.classList.toggle("dark");
      });

      // charts
      const userGrowthLabels = <?php echo json_encode($chartUserGrowth['labels'] ?? [], JSON_UNESCAPED_SLASHES); ?>;
      const userGrowthData = <?php echo json_encode($chartUserGrowth['data'] ?? [], JSON_UNESCAPED_SLASHES); ?>;
      const revenueLabels = <?php echo json_encode($chartRevenue['labels'] ?? [], JSON_UNESCAPED_SLASHES); ?>;
      const revenueData = <?php echo json_encode($chartRevenue['data'] ?? [], JSON_UNESCAPED_SLASHES); ?>;
      const volumeLabels = <?php echo json_encode($chartVolume['labels'] ?? [], JSON_UNESCAPED_SLASHES); ?>;
      const volumeData = <?php echo json_encode($chartVolume['data'] ?? [], JSON_UNESCAPED_SLASHES); ?>;
      const dvswLabels = <?php echo json_encode($chartDvSw['labels'] ?? [], JSON_UNESCAPED_SLASHES); ?>;
      const dvswDeposit = <?php echo json_encode($chartDvSw['deposits'] ?? [], JSON_UNESCAPED_SLASHES); ?>;
      const dvswWithdrawal = <?php echo json_encode($chartDvSw['withdrawals'] ?? [], JSON_UNESCAPED_SLASHES); ?>;

      const userCtx = document.getElementById("userChart").getContext("2d");
      new Chart(userCtx, {
        type: "line",
        data: {
          labels: userGrowthLabels,
          datasets: [
            {
              label: "Users",
              data: userGrowthData,
              borderColor: "#22c55e",
              tension: 0.3,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
        },
      });
      const revCtx = document.getElementById("revenueChart").getContext("2d");
      new Chart(revCtx, {
        type: "bar",
        data: {
          labels: revenueLabels,
          datasets: [
            {
              label: "Revenue",
              data: revenueData,
              backgroundColor: "#22c55e",
            },
          ],
        },
        options: { responsive: true, plugins: { legend: { display: false } } },
      });
      const volCtx = document.getElementById("volumeChart").getContext("2d");
      new Chart(volCtx, {
        type: "line",
        data: {
          labels: volumeLabels,
          datasets: [
            {
              label: "Volume (M)",
              data: volumeData,
              borderColor: "#22c55e",
              fill: true,
              backgroundColor: "rgba(34,197,94,0.1)",
            },
          ],
        },
        options: { responsive: true, plugins: { legend: { display: false } } },
      });
      const dvCtx = document.getElementById("dvswChart").getContext("2d");
      new Chart(dvCtx, {
        type: "line",
        data: {
          labels: dvswLabels,
          datasets: [
            {
              label: "Deposit",
              data: dvswDeposit,
              borderColor: "#10b981",
            },
            {
              label: "Withdrawal",
              data: dvswWithdrawal,
              borderColor: "#ef4444",
            },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { labels: { color: "#b0c0d0" } } },
        },
      });

      // ai assistant
      document.getElementById("aiBtn").addEventListener("click", () => {
        alert(
          "🤖 AI: 12 new users today, 3 pending KYC. Suggest reviewing fraud alerts.",
        );
      });

      // ensure mobile sidebar closes on resize > 1100
      window.addEventListener("resize", () => {
        if (window.innerWidth > 1100) {
          sidebar.classList.remove("mobile-open");
        }
      });
    </script>
  <?php require_once __DIR__ . '/includes/admin_notify_client.php'; ?>
  </body>
</html>
