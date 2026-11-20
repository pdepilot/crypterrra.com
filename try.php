<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes" />
  <title>Crypterra · User Profile Dashboard</title>
  <?php include __DIR__ . '/includes/ui_modal_script.php'; ?>
  <!-- Font Awesome 6 (free) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <!-- Google Fonts Inter -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&display=swap" rel="stylesheet" />
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
    }

    body.light {
      --primary-color: #22c55e;
      --bg: #f5f7fb;
      --bg-secondary: #eef2f6;
      --text: #333333;
      --text-secondary: #555555;
      --text-muted: #7f8c8d;
      --glass-bg: rgba(255, 255, 255, 0.7);
      --glass-edge: rgba(34, 197, 94, 0.3);
      --card-bg: #ffffff;
      --positive: #2ecc71;
      --negative: #e74c3c;
      --shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
    }

    body {
      font-family: "Inter", "Segoe UI", sans-serif;
      background: var(--bg-secondary);
      color: var(--text);
      line-height: 1.5;
      transition: background 0.3s, color 0.2s;
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
      transition: width 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1), left 0.3s;
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

    .sidebar.collapsed .sidebar-link span,
    .sidebar.collapsed .sidebar-header span,
    .sidebar.collapsed .user-info {
      display: none;
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
      top: 8px;
      right: 8px;
      width: 8px;
      height: 8px;
      background: var(--danger-color);
      border-radius: 50%;
      border: 2px solid var(--glass-bg);
    }

    .theme-toggle {
      width: 52px;
      height: 26px;
      background: #ccc;
      border-radius: 34px;
      position: relative;
      cursor: pointer;
      transition: background 0.3s;
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
      transition: transform 0.3s ease;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    }

    body.dark .theme-toggle {
      background: var(--primary-color);
    }
    body.dark .theme-toggle::before {
      transform: translateX(26px);
    }
    body.light .theme-toggle {
      background: var(--primary-light);
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

    .notification-popup {
      position: fixed;
      top: 80px;
      right: 20px;
      background: var(--card-bg);
      backdrop-filter: blur(20px);
      color: var(--text);
      padding: 15px 25px;
      border-radius: 12px;
      box-shadow: var(--shadow-glow);
      z-index: 1000;
      display: none;
      animation: slideIn 0.3s ease-out, fadeOut 0.5s 4.5s ease-in forwards;
      border-left: 4px solid var(--primary-color);
      max-width: 300px;
    }

    @keyframes slideIn {
      from { transform: translateX(400px); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    @keyframes fadeOut {
      from { opacity: 1; }
      to { opacity: 0; transform: translateX(100px); }
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
      background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
      background-size: 200% 200%;
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      animation: moveText 15s linear infinite;
    }

    @keyframes moveText {
      0% { background-position: 0% 50%; }
      100% { background-position: 100% 50%; }
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

    .balance-amount {
      font-size: 36px;
      font-weight: 700;
      color: var(--primary-color);
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

    .value-large {
      font-size: 3rem;
      font-weight: 700;
      background: linear-gradient(to right, #fff, var(--primary-color));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
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

    .two-col-grid {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 2rem;
      margin: 2rem 0;
    }

    .transaction-item, .security-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 0;
      border-bottom: 1px solid var(--glass-edge);
      flex-wrap: wrap;
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
      right: 2rem;
      bottom: 2rem;
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
      .dashboard-content { padding: 1rem; }
      .two-col-grid { grid-template-columns: 1fr; }
      .user-name { display: none; }
      .balance-amount { font-size: 28px; }
    }
  </style>
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
      <div class="user-avatar-large">D</div>
      <div class="user-info">
        <h4>Dave Brake</h4>
        <p>0x7F3...aB8d</p>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section">MAIN</div>
      <a href="#" class="sidebar-link active"><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a>
      <a href="#" class="sidebar-link"><i class="fa-solid fa-user"></i> <span>Profile</span></a>
      <a href="#" class="sidebar-link"><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a>
      <div class="nav-section">TRADING</div>
      <a href="#" class="sidebar-link"><i class="fa-solid fa-arrow-down"></i> <span>Deposit</span><span class="action-badge">NEW</span></a>
      <a href="#" class="sidebar-link"><i class="fa-solid fa-arrow-up"></i> <span>Withdraw</span></a>
      <div class="nav-section">ACCOUNT</div>
      <a href="#" class="sidebar-link"><i class="fa-solid fa-id-card"></i> <span>KYC Verification</span><span class="action-badge" style="background:#ffa726">PENDING</span></a>
      <a href="#" class="sidebar-link"><i class="fa-solid fa-shield"></i> <span>Security</span></a>
      <a href="#" class="sidebar-link"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span></a>
    </nav>
  </aside>

  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <main class="main-content">
    <div class="top-bar">
      <div class="header-left">
        <div class="mobile-menu-btn" id="mobileMenuBtn">
          <i class="fa-solid fa-bars"></i>
        </div>
        <a href="#" class="user-profile">
          <div class="user-avatar">D</div>
          <h3 class="user-name">Hello, Dave Brake</h3>
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

    <div class="notification-popup" id="notification-popup">
      Someone from USA deposited $15,000
    </div>

    <div class="dashboard-content">
      <div class="welcome-section" data-aos="fade-up">
        <div class="moving-text">Welcome to Your Dashboard</div>
      </div>

      <div class="wallet-id">
        <p>Wallet ID</p>
        <span id="wallet-id">23536687</span>
        <button class="copy-btn" id="copy-wallet-btn">
          <i class="fas fa-copy"></i>
        </button>
      </div>

      <div class="balance-section">
        <div class="balance-header">
          <div class="balance-title">Your available balance</div>
          <div>
            <div class="balance-amount">$0.04</div>
          </div>
        </div>
        <div class="balance-actions">
          <a href="#" class="action-button">
            <div class="action-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="action-label">Send</div>
          </a>
          <a href="#" class="action-button">
            <div class="action-icon"><i class="fas fa-arrow-down"></i></div>
            <div class="action-label">Receive</div>
          </a>
          <a href="#" class="action-button">
            <div class="action-icon"><i class="fas fa-arrow-up"></i></div>
            <div class="action-label">Withdraw</div>
          </a>
        </div>
      </div>

      <div class="stat-grid">
        <div class="glass-card">
          <h4>Total Portfolio Value</h4>
          <div class="value-large">$18,432.91</div>
        </div>
        <div class="glass-card">
          <h4>24h Volume</h4>
          <div class="value-large">$1,210.45</div>
        </div>
      </div>

      <!-- ASSETS SECTION -->
          <div class="assets-section" data-aos="fade-up">
            <div class="assets-tabs">
              <span class="tab active">All</span>
              <span class="tab">NFT</span>
              <span class="tab">Gainers</span>
              <span class="tab">Losers</span>
            </div>
            <div class="assets-list" id="assets-list"></div>
          </div>
     
      <div class="two-col-grid">
        <div class="glass-card">
          <h3>Recent Transactions</h3>
          <div class="transaction-item">
            <div><i class="fa-solid fa-circle-arrow-up" style="color:var(--positive)"></i> Deposit BTC</div>
            <div>0.15 BTC</div>
            <div class="status-badge">Completed</div>
          </div>
          <div class="transaction-item">
            <div><i class="fa-solid fa-arrow-right-arrow-left" style="color:var(--warning-color)"></i> Trade ETH → USDT</div>
            <div>2.5 ETH</div>
            <div class="status-badge">Completed</div>
          </div>
          <div class="transaction-item">
            <div><i class="fa-solid fa-circle-arrow-down" style="color:var(--negative)"></i> Withdraw USDT</div>
            <div>5,000 USDT</div>
            <div class="status-badge" style="background: rgba(255, 165, 0, 0.2); color: var(--warning-color);">Pending</div>
          </div>
        </div>
        <div class="glass-card">
          <h3>Security & KYC</h3>
          <div class="security-item">
            <i class="fa-solid fa-id-card"></i>
            <h4>KYC Verification</h4>
            <p style="color:var(--warning-color)">Pending verification</p>
          </div>
          <div class="security-item">
            <i class="fa-solid fa-lock"></i>
            <h4>2FA</h4>
            <p>Enabled</p>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  (function() {
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
    
    // Theme toggle
    const body = document.body;
    const themeToggle = document.getElementById("theme-toggle");
    const savedTheme = localStorage.getItem("crypterra-theme") || "dark";
    body.classList.add(savedTheme);
    
    themeToggle.addEventListener("click", () => {
      if (body.classList.contains("dark")) {
        body.classList.remove("dark");
        body.classList.add("light");
        localStorage.setItem("crypterra-theme", "light");
      } else {
        body.classList.remove("light");
        body.classList.add("dark");
        localStorage.setItem("crypterra-theme", "dark");
      }
    });
    
    // Notifications
    const notificationPopup = document.getElementById("notification-popup");
    const notifBtn = document.getElementById("notifications-btn");
    
    function showNotification(message) {
      notificationPopup.textContent = message;
      notificationPopup.style.display = "block";
      setTimeout(() => {
        notificationPopup.style.display = "none";
      }, 5000);
    }
    
    if (notifBtn) {
      notifBtn.addEventListener("click", () => {
        showNotification("You have 3 unread notifications");
      });
    }
    
    // ASSETS DATA – simplified display (balance, 24h %, unit price)
        const assets = [
          {
            id: "usdt",
            name: "USDT",
            symbol: "USDT",
            network: "TRC20",
            balance: "1,000,000.00",
            price: "$1.00",
            change: 0.02,
            page: "#",
            icon: "T",
          },
          {
            id: "trx",
            name: "TRX",
            symbol: "Tron",
            network: "",
            balance: "0.00",
            price: "$0.12",
            change: 2.35,
            page: "#",
            icon: "T",
          },
          {
            id: "usdt-erc",
            name: "USDT",
            symbol: "USDT",
            network: "ERC20",
            balance: "0.00",
            price: "$1.00",
            change: -0.01,
            page: "#",
            icon: "U",
          },
          {
            id: "eth",
            name: "ETH",
            symbol: "Ethereum",
            network: "",
            balance: "0.00",
            price: "$3,450.25",
            change: 1.85,
            page: "#",
            icon: "E",
          },
          {
            id: "bnb",
            name: "BNB",
            symbol: "BNB",
            network: "",
            balance: "0.00",
            price: "$578.42",
            change: -0.75,
            page: "#",
            icon: "B",
          },
          {
            id: "btc",
            name: "BTC",
            symbol: "Bitcoin",
            network: "",
            balance: "0.00",
            price: "$61,245.80",
            change: 3.25,
            page: "#",
            icon: "B",
          },
          {
            id: "matic",
            name: "MATIC",
            symbol: "MATIC",
            network: "",
            balance: "0.00",
            price: "$0.89",
            change: 4.12,
            page: "#",
            icon: "M",
          },
          {
            id: "sol",
            name: "SOL",
            symbol: "Solana",
            network: "",
            balance: "0.00",
            price: "$142.65",
            change: 5.78,
            page: "#",
            icon: "S",
          },
          {
            id: "dot",
            name: "DOT",
            symbol: "DOT",
            network: "",
            balance: "0.00",
            price: "$7.85",
            change: -1.25,
            page: "#",
            icon: "D",
          },
        ];

        const assetsList = document.getElementById("assets-list");
        function renderAssets() {
          if (!assetsList) return;
          assetsList.innerHTML = "";
          assets.forEach((asset) => {
            const assetCard = document.createElement("a");
            assetCard.href = asset.page;
            assetCard.className = "asset-card";
            assetCard.innerHTML = `
            <div class="asset-icon">${asset.icon}</div>
            <div class="asset-info">
              <div style="display:flex;justify-content:space-between;align-items:baseline;gap:10px">
                <div style="min-width:0">
                  <div class="asset-name" style="justify-content:flex-start">${asset.name}</div>
                  <div style="font-size:12px;color:var(--text-muted)">${asset.network || ""}</div>
                </div>
                <div style="text-align:right">
                  <div style="font-weight:700">${asset.symbol}</div>
                  <div style="font-size:14px;color:var(--text-muted);font-family:monospace">${asset.balance} ${asset.symbol}</div>
                </div>
              </div>
              <div style="display:flex;justify-content:space-between;margin-top:6px">
                <span class="price-change ${asset.change >= 0 ? "positive" : "negative"}">
                  ${asset.change >= 0 ? "↑" : "↓"} ${Math.abs(asset.change).toFixed(2)}%
                </span>
                <span class="asset-value" style="margin-top:0;display:inline-flex">≈ ${asset.price}</span>
              </div>
            </div>
          `;
            assetsList.appendChild(assetCard);
          });
        }
    renderAssets();
    
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
      showNotification("Welcome to Crypterra! You have successfully logged in.");
    }, 1000);
  })();
</script>
</body>
</html>