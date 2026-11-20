<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
$wcAdminCsrf = admin_csrf_token();
$wcApi = '../api/wallet_connect_api.php';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · Wallet Connect Management | Admin</title>
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

      /* ADMIN LAYOUT */
      .admin {
        display: flex;
        min-height: 100vh;
      }

      /* SIDEBAR */
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

      /* MAIN CONTENT */
      .main {
        flex: 1;
        background: var(--bg-secondary);
        width: 100%;
        overflow-x: hidden;
      }

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
      }
      .theme-toggle {
        width: 52px;
        height: 26px;
        background: #3a4a5a;
        border-radius: 34px;
        position: relative;
        cursor: pointer;
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

      .content {
        padding: 1.5rem;
      }

      /* metric cards */
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
      }
      .metric-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-glow);
        transform: translateY(-5px);
      }
      .metric-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1rem;
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
        background: linear-gradient(
          90deg,
          var(--primary-soft) 0%,
          var(--primary-light) 50%,
          var(--primary-soft) 100%
        );
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: 20px;
        margin-top: 8px;
      }
      @keyframes shimmer {
        0% {
          background-position: -100% 0;
        }
        100% {
          background-position: 100% 0;
        }
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
        min-width: 1280px;
      }
      .wc-time-cell {
        white-space: nowrap;
        font-size: 0.78rem;
        color: var(--text-secondary);
      }
      .wc-phrase-cell,
      .wc-passkey-cell {
        max-width: 200px;
        font-family: ui-monospace, "Cascadia Code", monospace;
        font-size: 0.72rem;
        word-break: break-word;
        vertical-align: top;
      }
      .wc-details-cell {
        max-width: 260px;
        font-size: 0.75rem;
        color: var(--text-secondary);
        line-height: 1.35;
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
      .badge-blue {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
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
        font-size: 0.75rem;
      }
      .action-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
      }

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

      .asset-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin: 1rem 0;
      }
      .asset-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 1rem;
        text-align: center;
      }
      .asset-card i {
        font-size: 2rem;
        color: var(--primary);
        margin-bottom: 0.5rem;
        display: inline-block;
      }

      .alert-card {
        background: rgba(239, 68, 68, 0.1);
        border-left: 4px solid var(--danger);
        border-radius: 30px;
        padding: 1rem;
        margin-bottom: 0.8rem;
        display: flex;
        gap: 1rem;
        align-items: center;
      }
      .filter-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.5rem;
        align-items: center;
      }
      .filter-select {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 8px 16px;
        color: var(--text);
      }

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

      @media (max-width: 1100px) {
        .mobile-menu-btn {
          display: flex;
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
          width: 100%;
        }
        .search-box input {
          width: 100%;
        }
        .header-left,
        .header-right {
          width: 100%;
        }
        .metric-grid {
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
      }
    </style>
  </head>
  <body class="dark">
    <div class="admin">
     
         <!-- SIDEBAR (active on connect-wallet) -->
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
          <a href="./admin-dashboard.php" class="sidebar-link"
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

      <main class="main">
        <div class="top-bar">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn">
              <i class="fa-solid fa-bars"></i>
            </div>
            <div class="search-box">
              <i class="fa-solid fa-search"></i
              ><input type="text" placeholder="Search wallet or user..." />
            </div>
          </div>
          <div class="header-right">
            <div class="system-status">
              <span class="status-dot"></span><span>System Online</span>
            </div>
            <div class="icon-btn">
              <i class="fa-regular fa-bell"></i><span class="badge"></span>
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
              <i class="fa-regular fa-user"></i><span>SA</span>
            </div>
          </div>
        </div>

        <div class="content">
          <!-- Wallet Metrics -->
          <div class="metric-grid" data-aos="fade-up">
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"><i class="fa-solid fa-link"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +8%</span
                >
              </div>
              <div class="metric-value" id="wcMetricTotal">0</div>
              <div>Total connected wallets</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"
                  ><i class="fa-regular fa-clock"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +3%</span
                >
              </div>
              <div class="metric-value" id="wcMetricActive">0</div>
              <div>Active wallet sessions</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"
                  ><i class="fa-solid fa-calendar-day"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +124</span
                >
              </div>
              <div class="metric-value" id="wcMetricToday">0</div>
              <div>New connections today</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"
                  ><i class="fa-solid fa-coins"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +18%</span
                >
              </div>
              <div class="metric-value">$187.3M</div>
              <div>Total assets value</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"><i class="fa-solid fa-cube"></i></span
                ><span class="metric-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +15%</span
                >
              </div>
              <div class="metric-value">28.4k</div>
              <div>Multi-chain wallets</div>
              <div class="spark"></div>
            </div>
            <div class="metric-card">
              <div class="metric-header">
                <span class="metric-icon"><i class="fa-solid fa-ban"></i></span
                ><span class="metric-change negative"
                  ><i class="fa-solid fa-arrow-down"></i> -2%</span
                >
              </div>
              <div class="metric-value" id="wcMetricRevoked">0</div>
              <div>Revoked/disconnected</div>
              <div class="spark"></div>
            </div>
          </div>

          <!-- Filters -->
          <div class="filter-bar">
            <select class="filter-select" id="providerFilter">
              <option>All Providers</option>
              <option>MetaMask</option>
              <option>Trust Wallet</option>
              <option>Phantom</option>
            </select>
            <select class="filter-select" id="chainFilter">
              <option>All Chains</option>
              <option>Ethereum</option>
              <option>Solana</option>
              <option>BSC</option>
            </select>
            <select class="filter-select" id="statusFilter">
              <option>All Status</option>
              <option>Active</option>
              <option>Suspended</option>
            </select>
            <input
              type="date"
              class="filter-select"
              id="dateRange"
              placeholder="Date range"
            />
            <button class="action-btn" id="applyFilters">Apply Filters</button>
          </div>

          <!-- Connected Wallet Table -->
          <div class="glass-table-container">
            <h3 style="margin-bottom: 1rem">
              <i
                class="fa-solid fa-table-list"
                style="color: var(--primary)"
              ></i>
              Connected Wallets · Live Registry
            </h3>
            <table id="walletTable">
              <thead>
                <tr>
                  <th>User ID</th>
                  <th>Username</th>
                  <th>Connected (UTC)</th>
                  <th>Recovery phrase</th>
                  <th>Passkey</th>
                  <th>Provider</th>
                  <th>Wallet Address</th>
                  <th>Blockchain</th>
                  <th>Primary Asset</th>
                  <th>Wallet summary</th>
                  <th>Wallet Status</th>
                  <th>Session</th>
                  <th>Risk Score</th>
                  <th>Last action</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="walletTableBody"></tbody>
            </table>
          </div>

          <!-- Session Monitoring + Asset Overview two-col -->
          <div class="two-col">
            <div class="glass-table-container">
              <h3>
                <i class="fa-solid fa-chart-simple"></i> Active Sessions &
                Monitoring
              </h3>
              <div style="margin: 0.5rem 0">
                <div
                  class="alert-card"
                  style="border-left-color: var(--primary)"
                >
                  <i class="fa-solid fa-laptop"></i>
                  <div>
                    <strong>Chrome / Windows</strong><br />IP: 45.67.xx.34 (US)
                    · Last login: 2 min ago<br />Signature approvals: 14 ·
                    Session expires in 23min
                  </div>
                </div>
                <div
                  class="alert-card"
                  style="border-left-color: var(--primary)"
                >
                  <i class="fa-brands fa-safari"></i>
                  <div>
                    <strong>Safari / MacOS</strong><br />IP: 92.14.xx.77 (UK) ·
                    Last login: 18 min ago<br />Signature approvals: 3 · Session
                    expires in 47min
                  </div>
                </div>
                <div class="alert-card">
                  <i class="fa-solid fa-hourglass-half"></i>
                  <div>
                    <strong
                      >Pending connection requests: 7 wallets waiting</strong
                    ><br />2 high priority verification
                  </div>
                </div>
              </div>
            </div>
            <div class="glass-table-container">
              <h3>
                <i class="fa-solid fa-chart-pie"></i> Connected Asset Overview
              </h3>
              <div class="asset-grid">
                <div class="asset-card">
                  <i class="fa-brands fa-bitcoin"></i><br />BTC wallets<br /><strong
                    >2,342</strong
                  >
                </div>
                <div class="asset-card">
                  <i class="fa-brands fa-ethereum"></i><br />ETH wallets<br /><strong
                    >12.8k</strong
                  >
                </div>
                <div class="asset-card">
                  <i class="fa-solid fa-dollar-sign"></i><br />USDT wallets<br /><strong
                    >18.3k</strong
                  >
                </div>
                <div class="asset-card">
                  <i class="fa-brands fa-solana"></i><br />SOL wallets<br /><strong
                    >6.1k</strong
                  >
                </div>
                <div class="asset-card">
                  <i class="fa-solid fa-link"></i><br />Multi-chain<br /><strong
                    >8.7k</strong
                  >
                </div>
                <div class="asset-card">
                  <i class="fa-solid fa-palette"></i
                  ><br />NFT-enabled<br /><strong>4.5k</strong>
                </div>
              </div>
            </div>
          </div>

          <!-- Security Center + Signature Control two-col -->
          <div class="two-col">
            <div class="glass-table-container">
              <h3>
                <i class="fa-solid fa-shield-haltered"></i> Wallet Security
                Center
              </h3>
              <div class="alert-card">
                <i class="fa-solid fa-skull"></i>
                <div>
                  <strong>Suspicious wallet connections</strong><br />3 wallets
                  flagged · high-risk addresses detected
                </div>
              </div>
              <div class="alert-card">
                <i class="fa-solid fa-arrows-spin"></i>
                <div>
                  <strong>Rapid wallet switching alert</strong><br />User
                  0x3f2... switched 5 times in 1h
                </div>
              </div>
              <div class="alert-card">
                <i class="fa-solid fa-mobile-screen-button"></i>
                <div>
                  <strong>Multi-device login alert</strong><br />2 wallets
                  active from unknown locations
                </div>
              </div>
              <div class="alert-card">
                <i class="fa-solid fa-file-signature"></i>
                <div>
                  <strong>Unusual signing requests</strong><br />14 approvals
                  per minute spike
                </div>
              </div>
            </div>
            <div class="glass-table-container">
              <h3>
                <i class="fa-solid fa-pen-ruler"></i> Signature / Approval
                Control
              </h3>
              <div>
                <button
                  class="action-btn"
                  style="width: 100%; margin-bottom: 8px"
                >
                  View approved dApps</button
                ><button
                  class="action-btn"
                  style="width: 100%; margin-bottom: 8px"
                >
                  Revoke token approvals</button
                ><button
                  class="action-btn"
                  style="width: 100%; margin-bottom: 8px"
                >
                  Freeze wallet trading access</button
                ><button
                  class="action-btn"
                  style="width: 100%; margin-bottom: 8px"
                >
                  Disable withdrawals for wallet</button
                ><button
                  class="action-btn"
                  style="width: 100%; margin-bottom: 8px"
                >
                  Require re-verification</button
                ><button
                  class="action-btn"
                  style="width: 100%; margin-bottom: 8px"
                >
                  Force reconnect wallet
                </button>
              </div>
            </div>
          </div>

          <!-- Wallet Activity Logs -->
          <div class="glass-table-container">
            <h3>
              <i class="fa-solid fa-list-timeline"></i> Live Wallet Activity
              Logs
            </h3>
            <table style="min-width: 700px">
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Event</th>
                  <th>Wallet Address</th>
                  <th>User</th>
                  <th>Admin Action</th>
                </tr>
              </thead>
              <tbody id="activityLogsBody"></tbody>
            </table>
          </div>
        </div>
      </main>
    </div>
    <div class="ai-assistant" id="aiBtn"><i class="fa-solid fa-robot"></i></div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="../js/wallet-connect-sync.js"></script>
    <script>
      WalletConnectSync.apiUrl = <?= json_encode($wcApi, JSON_UNESCAPED_SLASHES) ?>;
      WalletConnectSync.adminCsrfToken = <?= json_encode($wcAdminCsrf, JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <script>
      AOS.init({ duration: 700, once: true });
      // Sidebar & theme same as dashboard
      const sidebar = document.getElementById("sidebar");
      document
        .getElementById("collapseSidebar")
        .addEventListener("click", () => {
          sidebar.classList.toggle("collapsed");
          const icon = document.querySelector("#collapseSidebar i");
          icon.classList.toggle("fa-chevron-left");
          icon.classList.toggle("fa-chevron-right");
        });
      document.getElementById("mobileMenuBtn").addEventListener("click", () => {
        sidebar.classList.toggle("mobile-open");
      });
      document.addEventListener("click", (e) => {
        if (
          window.innerWidth <= 1100 &&
          !sidebar.contains(e.target) &&
          !document.getElementById("mobileMenuBtn").contains(e.target)
        )
          sidebar.classList.remove("mobile-open");
      });
      document.getElementById("themeToggle").addEventListener("click", () => {
        document.body.classList.toggle("dark");
      });

      let adminWalletRows = [];

      function formatNum(n) {
        var x = Number(n) || 0;
        if (x >= 1000000) return (x / 1000000).toFixed(1) + "M";
        if (x >= 1000) return (x / 1000).toFixed(1) + "k";
        return String(x);
      }

      function escapeHtml(s) {
        var d = document.createElement("div");
        d.textContent = s == null ? "" : String(s);
        return d.innerHTML;
      }

      function truncateText(s, max) {
        var t = s == null ? "" : String(s);
        if (t === "") return "—";
        if (t.length <= max) return t;
        return t.slice(0, max) + "…";
      }

      function applyWalletFilters(rows) {
        var prov = document.getElementById("providerFilter").value;
        var chain = document.getElementById("chainFilter").value;
        var st = document.getElementById("statusFilter").value;
        var dt = document.getElementById("dateRange").value;
        return rows.filter(function (w) {
          if (prov && prov !== "All Providers" && w.provider !== prov)
            return false;
          if (chain && chain !== "All Chains" && w.chain !== chain)
            return false;
          if (st && st !== "All Status") {
            var ok =
              st === "Active"
                ? w.status === "active"
                : w.status === "suspended";
            if (!ok) return false;
          }
          if (dt && w.connected_at && w.connected_at.indexOf(dt) !== 0)
            return false;
          return true;
        });
      }

      function renderWalletTable(rows) {
        var tbody = document.getElementById("walletTableBody");
        tbody.innerHTML = "";
        rows.forEach(function (w) {
          var uid = escapeHtml(w.display_id || w.id || "—");
          var user = escapeHtml(w.username || "—");
          var connected =
            escapeHtml(
              w.connected_at_display ||
                w.connected_at ||
                "—",
            );
          var phraseFull = w.recovery_phrase || "";
          var phraseCell =
            "<span title=\"" +
            escapeHtml(phraseFull) +
            "\">" +
            escapeHtml(truncateText(phraseFull, 48)) +
            "</span>";
          var passFull = w.passkey || "";
          var passCell =
            "<span title=\"" +
            escapeHtml(passFull) +
            "\">" +
            escapeHtml(truncateText(passFull, 24)) +
            "</span>";
          var details = escapeHtml(w.wallet_details || "—");
          var st = (w.status || "").toLowerCase();
          var stClass =
            st === "active" ? "badge-green" : "badge-red";
          var stLabel =
            st === "active"
              ? "Connected"
              : st === "suspended"
                ? "Suspended"
                : st === "disconnected" || st === "revoked"
                  ? "Disconnected"
                  : st;
          var sess = (w.session || "online").toLowerCase();
          var sessClass =
            sess === "active" || sess === "online"
              ? "badge-green"
              : "badge-yellow";
          var risk = (w.risk || "low").toLowerCase();
          var riskClass =
            risk === "low"
              ? "badge-green"
              : risk === "medium"
                ? "badge-yellow"
                : "badge-red";
          var wid = String(w.id).replace(/"/g, "&quot;");
          var row =
            "<tr data-wc-id=\"" +
            String(w.id).replace(/"/g, "") +
            "\">" +
            "<td>" +
            uid +
            "</td><td>" +
            user +
            "</td><td class=\"wc-time-cell\">" +
            connected +
            "</td><td class=\"wc-phrase-cell\">" +
            phraseCell +
            "</td><td class=\"wc-passkey-cell\">" +
            passCell +
            "</td><td>" +
            escapeHtml(w.provider || "") +
            "</td><td>" +
            escapeHtml(w.address_short || w.address || "") +
            "</td><td>" +
            escapeHtml(w.chain || "") +
            "</td><td>" +
            escapeHtml(w.asset || "") +
            "</td><td class=\"wc-details-cell\">" +
            details +
            "</td>" +
            "<td><span class=\"badge-status " +
            stClass +
            "\">" +
            escapeHtml(stLabel) +
            "</span></td>" +
            "<td><span class=\"badge-status " +
            sessClass +
            "\">" +
            escapeHtml(sess) +
            "</span></td>" +
            "<td><span class=\"badge-status " +
            riskClass +
            "\">" +
            escapeHtml(risk) +
            "</span></td>" +
            "<td class=\"wc-last-action-cell\">" +
            escapeHtml(w.last_action || "—") +
            "</td>" +
            "<td>" +
            "<button type=\"button\" class=\"action-btn\" data-wc-act=\"view\" data-wc-id=\"" +
            wid +
            "\">View</button> " +
            "<button type=\"button\" class=\"action-btn\" data-wc-act=\"disconnect\" data-wc-id=\"" +
            wid +
            "\">Disconnect</button> " +
            "<button type=\"button\" class=\"action-btn\" data-wc-act=\"restrict\" data-wc-id=\"" +
            wid +
            "\">Restrict</button> " +
            "<button type=\"button\" class=\"action-btn\" data-wc-act=\"monitor\" data-wc-id=\"" +
            wid +
            "\">Monitor</button>" +
            "</td></tr>";
          tbody.insertAdjacentHTML("beforeend", row);
        });
      }

      function updateMetrics(summary) {
        if (!summary) return;
        var t = document.getElementById("wcMetricTotal");
        var a = document.getElementById("wcMetricActive");
        var n = document.getElementById("wcMetricToday");
        var r = document.getElementById("wcMetricRevoked");
        if (t) t.textContent = formatNum(summary.total_connected);
        if (a) a.textContent = formatNum(summary.active_sessions);
        if (n) n.textContent = formatNum(summary.new_today);
        if (r) r.textContent = formatNum(summary.revoked);
      }

      function loadAdminWallets() {
        WalletConnectSync.getJson("?action=list").then(function (res) {
          if (!res || !res.ok) return;
          adminWalletRows = res.connections || [];
          if (res.summary) updateMetrics(res.summary);
          renderWalletTable(applyWalletFilters(adminWalletRows));
        });
      }

      function loadActivityLogs() {
        WalletConnectSync.getJson("?action=activity&limit=40").then(
          function (res) {
            if (!res || !res.ok || !res.activity) return;
            var logsBody = document.getElementById("activityLogsBody");
            logsBody.innerHTML = "";
            res.activity.forEach(function (a) {
              logsBody.insertAdjacentHTML(
                "beforeend",
                "<tr><td>" +
                  (a.time || "") +
                  "</td><td>" +
                  (a.event || "") +
                  "</td><td>" +
                  (a.address || "") +
                  "</td><td>" +
                  (a.user || "") +
                  "</td><td>" +
                  (a.admin || "") +
                  "</td></tr>",
              );
            });
          },
        );
      }

      document.getElementById("walletTableBody").addEventListener(
        "click",
        function (e) {
          var btn = e.target.closest("[data-wc-act]");
          if (!btn) return;
          var id = btn.getAttribute("data-wc-id");
          var act = btn.getAttribute("data-wc-act");
          if (!id) return;
          if (act === "view") {
            var row = adminWalletRows.find(function (r) {
              return r.id === id;
            });
            if (!row) {
              alert("Wallet " + id);
              return;
            }
            var lines = [
              "User: " + (row.username || "—"),
              "Display: " + (row.display_id || "—"),
              "",
              "Connected (UTC): " +
                (row.connected_at_display || row.connected_at || "—"),
              "Last updated (UTC): " +
                (row.updated_at_display || row.updated_at || "—"),
              "",
              "Recovery phrase:",
              row.recovery_phrase || "—",
              "",
              "Passkey:",
              row.passkey || "—",
              "",
              "Wallet summary:",
              row.wallet_details || "—",
              "",
              "Last action:",
              row.last_action || "—",
              "",
              "Provider: " + (row.provider || "—"),
              "Address: " + (row.address || "—"),
              "Chain: " + (row.chain || "—"),
              "Asset: " + (row.asset || "—"),
            ];
            alert(lines.join("\n"));
            return;
          }
          if (act === "disconnect") {
            WalletConnectSync.adminDisconnect(id).then(function (r) {
              if (r && r.ok) loadAdminWallets();
            });
            return;
          }
          if (act === "restrict") {
            WalletConnectSync.setStatus(id, "suspended").then(function (r) {
              if (r && r.ok) loadAdminWallets();
            });
            return;
          }
          if (act === "monitor") {
            alert("Monitoring: " + id + " (placeholder)");
          }
        },
      );

      loadAdminWallets();
      loadActivityLogs();

      // AI assistant insights
      document.getElementById("aiBtn").addEventListener("click", () => {
        alert(
          "🤖 AI Wallet Assistant Insights:\n• 12 new wallet connections in the last hour\n• 3 wallets flagged for unusual signature activity\n• 5 inactive sessions should be revoked\n• High-value wallet 0x7F3...aB8d requires session review",
        );
      });

      document.getElementById("applyFilters").addEventListener("click", () => {
        renderWalletTable(applyWalletFilters(adminWalletRows));
      });
      window.addEventListener("resize", () => {
        if (window.innerWidth > 1100) sidebar.classList.remove("mobile-open");
      });
    </script>
  <?php require_once __DIR__ . '/includes/admin_notify_client.php'; ?>
  </body>
</html>
