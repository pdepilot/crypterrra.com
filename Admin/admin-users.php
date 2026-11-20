<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Crypterra · Admin · User Management (fully responsive)</title>
<?php
  require_once __DIR__ . '/includes/admin_auth.php';
  require_once __DIR__ . '/includes/admin_csrf.php';
  require_once __DIR__ . '/includes/admin_users_data.php';
  $stats = ['total' => 0, 'new_today' => 0, 'verified' => 0, 'suspended' => 0];
  $activityFeed = [];
  try {
    $db = admin_users_db();
    $stats = admin_users_summary($db);
    $activityFeed = admin_users_activity_feed($db, 12);
    $db->close();
  } catch (Throwable) {
    // keep defaults; UI still loads via API
  }
  $UI_MODAL_PREFIX = '../';
  require_once __DIR__ . '/../includes/ui_modal_script.php';
?>
  <!-- Font Awesome 6 (free) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Google Fonts Inter & Space Grotesk -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- AOS scroll animation -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
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
      --orange: #f97316;

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
      font-family: 'Inter', 'Space Grotesk', sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.5;
      overflow-x: hidden;
    }

    /* ========== ADMIN LAYOUT (exact same as dashboard) ========== */
    .admin {
      display: flex;
      min-height: 100vh;
    }

    /* ========== SIDEBAR ========== */
    .sidebar {
      width: 280px;
      background: rgba(10, 20, 35, 0.9);
      backdrop-filter: blur(20px) saturate(200%);
      -webkit-backdrop-filter: blur(20px) saturate(200%);
      border-right: 1px solid var(--glass-edge);
      padding: 1.8rem 1.2rem;
      transition: width 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1), left 0.3s;
      display: flex;
      flex-direction: column;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      scrollbar-width: none;
      z-index: 100;
      box-shadow: 10px 0 40px -15px rgba(0,0,0,0.8);
    }
    .sidebar::-webkit-scrollbar { display: none; }
    .sidebar.collapsed { width: 95px; }
    .sidebar.collapsed .sidebar-link span,
    .sidebar.collapsed .sidebar-header span,
    .sidebar.collapsed .admin-info { display: none; }

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
      width: 42px; height: 42px;
      background: linear-gradient(145deg, var(--primary), var(--primary-dark));
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
      width: 34px; height: 34px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; color: var(--primary);
      transition: 0.3s;
    }
    .collapse-btn:hover {
      background: var(--primary); color: white; transform: rotate(180deg);
      box-shadow: var(--shadow-glow);
    }

    .admin-info {
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      padding: 1rem;
      margin-bottom: 2rem;
      display: flex; align-items: center; gap: 1rem;
    }
    .admin-avatar {
      width: 44px; height: 44px; border-radius: 50%;
      background: linear-gradient(145deg, var(--primary), var(--primary-dark));
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; color: white; font-weight: 600;
      border: 2px solid var(--primary);
      box-shadow: 0 0 20px var(--primary-glow);
    }
    .admin-details h4 { font-size: 0.95rem; }
    .admin-details p { font-size: 0.7rem; color: var(--text-muted); }

    .sidebar-nav {
      flex: 1;
      display: flex; flex-direction: column; gap: 0.3rem;
    }
    .nav-section {
      margin: 1rem 0 0.3rem; padding-left: 1rem;
      font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;
      color: var(--text-muted);
    }
    .sidebar.collapsed .nav-section { display: none; }

    .sidebar-link {
      display: flex; align-items: center; gap: 1.2rem;
      padding: 0.8rem 1.2rem; border-radius: 40px;
      color: var(--text-secondary); text-decoration: none;
      transition: 0.3s; white-space: nowrap; position: relative; overflow: hidden;
    }
    .sidebar-link i { width: 24px; font-size: 1.2rem; text-align: center; }
    .sidebar-link::before {
      content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 0;
      background: linear-gradient(90deg, var(--primary-soft), transparent);
      transition: width 0.3s; z-index: -1;
    }
    .sidebar-link:hover::before, .sidebar-link.active::before { width: 100%; }
    .sidebar-link:hover, .sidebar-link.active {
      color: var(--primary); transform: translateX(5px);
      box-shadow: 0 0 20px var(--primary-glow);
    }
    .sidebar-link.active { background: var(--primary-soft); border: 1px solid var(--glass-edge); }

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
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 0.8rem; position: sticky; top: 0; z-index: 90;
    }
    .header-left {
      display: flex; align-items: center; gap: 12px;
      flex-wrap: wrap;
    }
    .mobile-menu-btn {
      display: none; background: var(--glass-bg); border: 1px solid var(--glass-edge);
      border-radius: 50%; width: 42px; height: 42px; align-items: center; justify-content: center;
      font-size: 1.4rem; cursor: pointer; color: var(--primary);
      flex-shrink: 0;
    }
    .search-box {
      background: var(--glass-bg); border: 1px solid var(--glass-edge);
      border-radius: 40px; padding: 8px 16px; display: flex; align-items: center; gap: 8px;
    }
    .search-box i { color: var(--text-muted); }
    .search-box input {
      background: transparent; border: none; color: var(--text); outline: none;
      font-size: 0.9rem; width: 200px;
    }
    .header-right {
      display: flex; align-items: center; gap: 15px;
      flex-wrap: wrap;
    }
    .system-status {
      display: flex; align-items: center; gap: 6px; font-size: 0.85rem;
    }
    .status-dot {
      width: 10px; height: 10px; border-radius: 50%; background: var(--success);
      box-shadow: 0 0 15px #10b981; animation: pulse 2s infinite;
    }
    @keyframes pulse { 0% { opacity: 0.8; } 50% { opacity: 1; } 100% { opacity: 0.8; } }
    .icon-btn {
      width: 40px; height: 40px; border-radius: 50%; background: var(--glass-bg);
      border: 1px solid var(--glass-edge); display: flex; align-items: center; justify-content: center;
      color: var(--primary); cursor: pointer; transition: 0.2s; position: relative;
      flex-shrink: 0;
    }
    .icon-btn:hover { background: var(--primary); color: white; transform: translateY(-2px); box-shadow: var(--shadow-glow); }
    .badge {
      position: absolute; top: 5px; right: 5px; width: 8px; height: 8px;
      background: var(--danger); border-radius: 50%; border: 2px solid var(--glass-bg);
    }
    .theme-toggle {
      width: 52px; height: 26px; background: #3a4a5a; border-radius: 34px;
      position: relative; cursor: pointer; border: 1px solid var(--glass-edge);
      flex-shrink: 0;
    }
    .theme-toggle::before {
      content: ''; position: absolute; width: 20px; height: 20px; background: white;
      border-radius: 50%; top: 2px; left: 2px; transition: 0.3s;
    }
    body.dark .theme-toggle { background: var(--primary); }
    body.dark .theme-toggle::before { transform: translateX(26px); }

    /* content area */
    .content {
      padding: 1.5rem;
    }

    /* page header */
    .page-header {
      margin-bottom: 1.8rem;
    }
    .page-header h1 {
      font-size: 2rem;
      background: linear-gradient(135deg, #fff, var(--primary));
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      display: inline-block;
    }
    .page-header p { color: var(--text-secondary); }
    .breadcrumb {
      display: flex; align-items: center; gap: 8px; color: var(--text-muted);
      font-size: 0.9rem; margin-top: 5px;
    }
    .breadcrumb a { color: var(--text-secondary); text-decoration: none; }
    .breadcrumb a:hover { color: var(--primary); }

    /* user analytics cards */
    .analytics-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr));
      gap: 1rem; margin-bottom: 1.8rem;
    }
    .analytics-card {
      background: var(--card-bg); backdrop-filter: blur(16px);
      border: 1px solid var(--glass-edge); border-radius: 30px; padding: 1.2rem;
      transition: 0.3s;
    }
    .analytics-card:hover { border-color: var(--primary); box-shadow: var(--shadow-glow); transform: translateY(-3px); }
    .analytics-value { font-size: 2rem; font-weight: 700; color: var(--primary); line-height: 1.2; }
    .analytics-label { color: var(--text-muted); font-size: 0.85rem; }

    /* top controls */
    .control-bar {
      display: flex; flex-wrap: wrap; align-items: center; gap: 1rem;
      margin-bottom: 1.5rem; background: var(--card-bg); backdrop-filter: blur(16px);
      border: 1px solid var(--glass-edge); border-radius: 60px; padding: 0.8rem 1.5rem;
    }
    .control-search {
      flex: 2; min-width: 250px;
      display: flex; align-items: center; gap: 8px;
      background: var(--glass-bg); border-radius: 40px; padding: 0.5rem 1rem;
    }
    .control-search i { color: var(--text-muted); }
    .control-search input {
      background: transparent; border: none; color: var(--text); outline: none; width: 100%;
    }
    .control-filters {
      display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
    }
    .filter-select {
      background: var(--glass-bg); border: 1px solid var(--glass-edge);
      border-radius: 40px; padding: 0.5rem 2rem 0.5rem 1rem; color: var(--text);
      font-size: 0.9rem; cursor: pointer; appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2322c55e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 8px center;
    }
    .btn-primary {
      background: var(--primary); border: none; border-radius: 40px;
      padding: 0.6rem 1.5rem; color: white; font-weight: 600; cursor: pointer;
      display: flex; align-items: center; gap: 8px; transition: 0.2s;
    }
    .btn-primary:hover { background: var(--primary-dark); transform: scale(1.02); box-shadow: var(--shadow-glow); }

    /* table container */
    .table-wrapper {
      background: var(--card-bg); backdrop-filter: blur(16px);
      border: 1px solid var(--glass-edge); border-radius: 40px; padding: 1.2rem;
      overflow-x: auto; margin: 1.5rem 0;
      max-height: 600px; overflow-y: auto;
      scrollbar-width: thin; scrollbar-color: var(--primary) var(--surface);
    }
    .table-wrapper::-webkit-scrollbar { width: 6px; height: 6px; }
    .table-wrapper::-webkit-scrollbar-track { background: var(--surface); border-radius: 10px; }
    .table-wrapper::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }

    table {
      width: 100%; border-collapse: collapse; min-width: 1300px;
    }
    th {
      position: sticky; top: 0; background: var(--surface-light); z-index: 10;
      padding: 1.2rem 0.8rem; color: var(--primary); border-bottom: 2px solid var(--glass-edge);
      font-weight: 600; backdrop-filter: blur(10px);
    }
    td { padding: 1rem 0.8rem; border-bottom: 1px solid var(--glass-edge); }
    tr { transition: 0.2s; }
    tr:hover { background: var(--primary-soft); }

    /* checkbox */
    .checkbox-col { width: 40px; text-align: center; }
    input[type="checkbox"] {
      width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;
    }

    /* avatar */
    .user-avatar-sm {
      width: 36px; height: 36px; border-radius: 50%; background: var(--primary-soft);
      display: flex; align-items: center; justify-content: center; font-weight: 600;
      color: var(--primary); border: 1px solid var(--glass-edge);
    }

    /* status badges */
    .badge {
      padding: 0.25rem 0.8rem; border-radius: 40px; font-size: 0.75rem; font-weight: 600;
      display: inline-block;
    }
    .badge-green { background: rgba(16,185,129,0.2); color: var(--success); }
    .badge-yellow { background: rgba(245,158,11,0.2); color: var(--warning); }
    .badge-red { background: rgba(239,68,68,0.2); color: var(--danger); }
    .badge-orange { background: rgba(249,115,22,0.2); color: var(--orange); }

    /* actions dropdown */
    .actions-dropdown {
      position: relative; display: inline-block;
    }
    .actions-btn {
      background: transparent; border: 1px solid var(--glass-edge); border-radius: 30px;
      padding: 0.4rem 1rem; cursor: pointer; display: flex; align-items: center; gap: 4px;
    }
    .actions-btn:hover { background: var(--primary); color: white; }
    .dropdown-menu {
      position: absolute; right: 0; top: 110%; background: var(--surface);
      border: 1px solid var(--glass-edge); border-radius: 20px; padding: 0.5rem 0;
      min-width: 180px; z-index: 50; display: none; box-shadow: var(--shadow-glow);
    }
    .dropdown-menu.show { display: block; }
    .dropdown-item {
      padding: 0.6rem 1rem; display: flex; align-items: center; gap: 8px;
      color: var(--text-secondary); text-decoration: none; transition: 0.2s;
      cursor: pointer;
    }
    .dropdown-item:hover { background: var(--primary-soft); color: var(--primary); }

    /* bulk actions bar */
    .bulk-bar {
      background: var(--card-bg); border: 1px solid var(--glass-edge);
      border-radius: 60px; padding: 0.8rem 1.5rem; display: flex;
      align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;
      margin: 1rem 0;
    }

    /* activity feed */
    .activity-feed {
      background: var(--card-bg); border: 1px solid var(--glass-edge);
      border-radius: 40px; padding: 1.2rem; margin-top: 1.5rem;
    }
    .feed-item {
      display: flex; align-items: center; gap: 1rem; padding: 0.8rem 0;
      border-bottom: 1px solid var(--glass-edge); flex-wrap: wrap;
    }
    .feed-item:last-child { border-bottom: none; }
    .feed-icon {
      width: 32px; height: 32px; border-radius: 50%; background: var(--primary-soft);
      display: flex; align-items: center; justify-content: center; color: var(--primary);
    }

    /* modals (hidden by default) */
    .modal-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.7); backdrop-filter: blur(8px);
      display: none; align-items: center; justify-content: center; z-index: 1000;
    }
    .modal-content {
      background: var(--surface); border: 1px solid var(--glass-edge);
      border-radius: 50px; padding: 2rem; max-width: 600px; width: 90%;
      max-height: 80vh; overflow-y: auto; box-shadow: var(--shadow-glow);
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .modal-close { cursor: pointer; font-size: 1.5rem; color: var(--text-muted); }
    .modal-close:hover { color: var(--primary); }

    /* pagination */
    .pagination {
      display: flex; justify-content: flex-end; gap: 8px; margin-top: 1rem; flex-wrap: wrap;
    }
    .page-btn {
      background: var(--glass-bg); border: 1px solid var(--glass-edge);
      border-radius: 30px; padding: 0.5rem 1rem; cursor: pointer;
    }
    .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

    /* responsive */
    @media (max-width: 1100px) {
      .mobile-menu-btn { display: flex; }
      .control-bar { border-radius: 30px; }
    }
    @media (max-width: 768px) {
      .content { padding: 1rem; }
      .control-bar { flex-direction: column; align-items: stretch; }
      .bulk-bar { flex-direction: column; align-items: flex-start; }
      .analytics-value { font-size: 1.5rem; }
    }
    @media (max-width: 480px) {
      .top-bar { padding: 0.6rem 1rem; }
      .search-box input { width: 140px; }
      .header-right .system-status span:last-child { display: none; }
      .header-right .icon-btn span { display: none; }
      .icon-btn[style*="width: auto"] { padding: 0 8px; }
    }

    /* animations */
    .fade-in { animation: fadeIn 0.3s; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body class="dark">
  <div class="admin">
    <!-- SIDEBAR (exact copy from admin-dashboard) -->
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="logo">
          <div class="logo-icon"><i class="fa-solid fa-globe"></i></div>
          <span>Crypterra</span>
        </div>
        <div class="collapse-btn" id="collapseSidebar"><i class="fa-solid fa-chevron-left"></i></div>
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
        <a href="./admin-dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a>
        <a href="./admin-users.php" class="sidebar-link active"><i class="fa-solid fa-users"></i> <span>Users</span></a>
        <a href="./admin-send.php" class="sidebar-link"><i class="fa-solid fa-paper-plane"></i> <span>Send Mgmt</span></a>
        <a href="./admin-receive.php" class="sidebar-link"><i class="fa-solid fa-arrow-down"></i> <span>Receive Mgmt</span></a>
        <a href="./admin-transactions.php" class="sidebar-link"><i class="fa-solid fa-arrow-right-arrow-left"></i> <span>Transactions</span></a>
        <a href="./admin-contact.php" class="sidebar-link"><i class="fa-solid fa-envelope"></i> <span>Contact Mgmt</span></a>
        <a href="./admin-help-center.php" class="sidebar-link"><i class="fa-solid fa-life-ring"></i> <span>Help Center</span></a>
                <a href="./admin-deposit.php" class="sidebar-link"><i class="fa-solid fa-arrow-down"></i> <span>Deposits</span></a>
        <a href="./admin-withdrawal.php" class="sidebar-link"><i class="fa-solid fa-arrow-up"></i> <span>Withdrawals</span></a>
        <a href="./admin-kyc.php" class="sidebar-link"><i class="fa-solid fa-id-card"></i> <span>KYC Management</span></a>
        <a href="./admin-wallet-control.php" class="sidebar-link"><i class="fa-solid fa-wallet"></i> <span>Wallet Control</span></a>
        <a href="./admin-market-control.php" class="sidebar-link"><i class="fa-solid fa-chart-line"></i> <span>Market Control</span></a>
        <a href="./admin-security-center.php" class="sidebar-link"><i class="fa-solid fa-shield"></i> <span>Security Center</span></a>
        <a href="./admin-analytics.php" class="sidebar-link"><i class="fa-solid fa-magnifying-glass-chart"></i> <span>Analytics</span></a>
        <a href="./admin-notifications.php" class="sidebar-link"><i class="fa-solid fa-bell"></i> <span>Notifications</span></a>
        <a href="./admin-settings.php" class="sidebar-link"><i class="fa-solid fa-gear"></i> <span>Settings</span></a>
        <div style="margin-top: 2rem;"></div>
        <a href="./admin-logout.php" class="sidebar-link"><i class="fa-solid fa-sign-out-alt"></i> <span>Logout</span></a>
      </nav>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
      <!-- TOP BAR -->
      <div class="top-bar">
        <div class="header-left">
          <div class="mobile-menu-btn" id="mobileMenuBtn"><i class="fa-solid fa-bars"></i></div>
          <div class="search-box">
            <i class="fa-solid fa-search"></i>
            <input type="text" placeholder="Search admin...">
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
          <div class="icon-btn" style="width: auto; padding: 0 12px; border-radius: 40px; gap: 6px;">
            <i class="fa-regular fa-user"></i> <span class="admin-short">SA</span>
          </div>
        </div>
      </div>

      <!-- CONTENT: USER MANAGEMENT -->
      <div class="content">
        <!-- Page Header & Breadcrumb -->
        <div class="page-header" data-aos="fade-up">
          <h1>User Management</h1>
          <p>Manage all registered users on Crypterra.</p>
          <div class="breadcrumb">
            <a href="#">Admin</a> <span>/</span> <span style="color: var(--primary);">Users</span>
          </div>
        </div>

        <!-- User Analytics Cards -->
        <div class="analytics-grid" data-aos="fade-up">
          <div class="analytics-card"><div class="analytics-value" id="statTotal"><?php echo (int)($stats['total'] ?? 0); ?></div><div class="analytics-label">Total Users</div></div>
          <div class="analytics-card"><div class="analytics-value" id="statNewToday"><?php echo (int)($stats['new_today'] ?? 0); ?></div><div class="analytics-label">New Today</div></div>
          <div class="analytics-card"><div class="analytics-value" id="statVerified"><?php echo (int)($stats['verified'] ?? 0); ?></div><div class="analytics-label">Verified</div></div>
          <div class="analytics-card"><div class="analytics-value" id="statSuspended"><?php echo (int)($stats['suspended'] ?? 0); ?></div><div class="analytics-label">Suspended</div></div>
        </div>

        <!-- Top Controls -->
        <div class="control-bar" data-aos="fade-up">
          <div class="control-search">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by name, email, ID...">
          </div>
          <div class="control-filters">
            <select class="filter-select" id="filterSelect">
              <option value="">All Users</option>
              <option value="verified">Verified</option>
              <option value="unverified">Unverified</option>
              <option value="suspended">Suspended</option>
              <option value="active">Active</option>
            </select>
            <select class="filter-select" id="sortSelect">
              <option value="newest">Newest</option>
              <option value="oldest">Oldest</option>
              <option value="highest_balance">Highest Balance</option>
            </select>
            <button class="btn-primary"><i class="fa-solid fa-plus"></i> Add User</button>
          </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="bulk-bar" data-aos="fade-up" id="bulkBar" style="display: none;">
          <span><span id="selectedCount">0</span> users selected</span>
          <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="action-btn" style="background:transparent; border:1px solid var(--glass-edge); border-radius:40px; padding:0.4rem 1rem; color:var(--text);">Delete</button>
            <button class="action-btn" style="background:transparent; border:1px solid var(--glass-edge); border-radius:40px; padding:0.4rem 1rem; color:var(--text);">Suspend</button>
            <button class="action-btn" style="background:transparent; border:1px solid var(--glass-edge); border-radius:40px; padding:0.4rem 1rem; color:var(--text);">Send Notification</button>
          </div>
        </div>

        <!-- Users Table -->
        <div class="table-wrapper" data-aos="fade-up">
          <table>
            <thead>
              <tr>
                <th class="checkbox-col"><input type="checkbox" id="selectAll"></th>
                <th>User ID</th>
                <th>Avatar</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Country</th>
                <th>KYC</th>
                <th>Status</th>
                <th>Balance</th>
                <th>Joined</th>
                <th>Last Active</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="userTableBody">
              <!-- dynamically populated by js -->
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="pagination"></div>

        <!-- Activity Feed -->
        <div class="activity-feed" data-aos="fade-up">
          <h3 style="margin-bottom: 1rem;"><i class="fa-solid fa-clock" style="color: var(--primary);"></i> Live user activity</h3>
          <div id="activityFeedBody">
            <?php if (!$activityFeed): ?>
              <div class="feed-item" style="color:var(--text-muted);"><span>No recent activity yet.</span></div>
            <?php else: foreach ($activityFeed as $item): ?>
              <div class="feed-item">
                <span class="feed-icon"><i class="<?php echo admin_users_h($item['icon']); ?>"></i></span>
                <span><strong><?php echo admin_users_h($item['name']); ?></strong> <?php echo admin_users_h($item['detail']); ?></span>
                <span style="color: var(--text-muted); margin-left: auto;"><?php echo admin_users_h(admin_users_time_ago($item['at'])); ?></span>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- User Detail Modal -->
  <div class="modal-overlay" id="userModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>User Profile</h2>
        <span class="modal-close" id="closeModal">&times;</span>
      </div>
      <div id="modalBody">
        <!-- filled by js -->
      </div>
    </div>
  </div>

  <!-- Floating AI (optional, kept from dashboard) -->
  <div class="ai-assistant" id="aiBtn" style="position:fixed; bottom:1.5rem; right:1.5rem; width:56px; height:56px; border-radius:50%; background:var(--primary); display:flex; align-items:center; justify-content:center; color:white; cursor:pointer; box-shadow:var(--shadow-glow);"><i class="fa-solid fa-robot"></i></div>

  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 700, once: true });

    const API_BASE = './api/users_api.php';
    const CSRF_TOKEN = <?php echo json_encode(admin_csrf_token(), JSON_UNESCAPED_SLASHES); ?>;

    // Sidebar collapse
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('collapseSidebar');
    collapseBtn.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      const icon = collapseBtn.querySelector('i');
      icon.classList.toggle('fa-chevron-left');
      icon.classList.toggle('fa-chevron-right');
    });

    // Mobile menu
    const mobileBtn = document.getElementById('mobileMenuBtn');
    mobileBtn.addEventListener('click', () => {
      sidebar.classList.toggle('mobile-open');
    });
    document.addEventListener('click', (e) => {
      if (window.innerWidth <= 1100 && !sidebar.contains(e.target) && !mobileBtn.contains(e.target)) {
        sidebar.classList.remove('mobile-open');
      }
    });

    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    themeToggle.addEventListener('click', () => {
      document.body.classList.toggle('dark');
    });

    function getBadgeClass(type, value) {
      if (type === 'kyc') {
        if (value === 'Verified') return 'badge-green';
        if (value === 'Pending') return 'badge-yellow';
        return 'badge-red';
      } else {
        if (value === 'Active') return 'badge-green';
        if (value === 'Suspended') return 'badge-red';
        return 'badge-orange';
      }
    }

    // Render table
    const tbody = document.getElementById('userTableBody');
    const paginationEl = document.getElementById('pagination');
    let state = { users: [], page: 1, perPage: 25, total: 0, q: '', filter: '', sort: 'newest' };

    function initials(fullNameOrEmail) {
      const s = String(fullNameOrEmail || '').trim();
      if (!s) return 'U';
      const parts = s.split(/\s+/).filter(Boolean);
      if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
      return s.slice(0, 2).toUpperCase();
    }

    function fmtDate(s) {
      if (!s) return '—';
      return String(s).slice(0, 10);
    }

    function totalPages() {
      const per = Number(state.perPage || 1);
      const tot = Number(state.total || 0);
      return Math.max(1, Math.ceil(tot / per));
    }

    function renderPagination() {
      if (!paginationEl) return;
      const pages = totalPages();
      const current = Math.min(Math.max(1, Number(state.page || 1)), pages);
      state.page = current;

      paginationEl.innerHTML = '';

      function addBtn(labelHtml, page, isActive = false, disabled = false, ariaLabel = '') {
        const btn = document.createElement('button');
        btn.className = 'page-btn' + (isActive ? ' active' : '');
        btn.innerHTML = labelHtml;
        if (ariaLabel) btn.setAttribute('aria-label', ariaLabel);
        if (disabled) {
          btn.disabled = true;
          btn.style.opacity = '0.5';
          btn.style.cursor = 'not-allowed';
        } else {
          btn.addEventListener('click', () => {
            window.goToPage(page);
          });
        }
        paginationEl.appendChild(btn);
      }

      function addEllipsis() {
        const span = document.createElement('span');
        span.textContent = '…';
        span.style.padding = '0.5rem 0.6rem';
        span.style.color = 'var(--text-muted)';
        paginationEl.appendChild(span);
      }

      addBtn('<i class="fa-solid fa-chevron-left"></i>', current - 1, false, current <= 1, 'Previous page');

      const windowSize = 2;
      const start = Math.max(1, current - windowSize);
      const end = Math.min(pages, current + windowSize);

      if (start > 1) {
        addBtn('1', 1, current === 1, false, 'Page 1');
        if (start > 2) addEllipsis();
      }

      for (let p = start; p <= end; p++) {
        addBtn(String(p), p, p === current, false, 'Page ' + p);
      }

      if (end < pages) {
        if (end < pages - 1) addEllipsis();
        addBtn(String(pages), pages, current === pages, false, 'Page ' + pages);
      }

      addBtn('<i class="fa-solid fa-chevron-right"></i>', current + 1, false, current >= pages, 'Next page');
    }

    window.goToPage = function(page) {
      const p = Number(page || 1);
      const pages = totalPages();
      const next = Math.min(Math.max(1, p), pages);
      if (next === state.page) return;
      state.page = next;
      fetchUsers();
    };

    function renderTable() {
      tbody.innerHTML = '';
      state.users.forEach((u) => {
        const status = (u.is_active === 1) ? 'Active' : 'Suspended';
        const kyc = u.kyc_status || 'N/A';
        const avatar = initials(u.full_name || u.email);
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td class="checkbox-col"><input type="checkbox" class="user-checkbox" data-id="${u.id}"></td>
          <td>#${u.id}</td>
          <td><div class="user-avatar-sm">${avatar}</div></td>
          <td>${u.full_name || '—'}</td>
          <td>${u.email || '—'}</td>
          <td>${u.country || '—'}</td>
          <td><span class="badge ${getBadgeClass('kyc', kyc)}">${kyc}</span></td>
          <td><span class="badge ${getBadgeClass('status', status)}">${status}</span></td>
          <td>$${Number(u.available_balance_usd || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
          <td>${fmtDate(u.created_at)}</td>
          <td>${fmtDate(u.last_login_at)}</td>
          <td>
            <div class="actions-dropdown">
              <button class="actions-btn" onclick="toggleDropdown(this)"><i class="fa-solid fa-ellipsis-vertical"></i> Actions</button>
              <div class="dropdown-menu">
                <a class="dropdown-item" onclick="viewProfile(${u.id})"><i class="fa-regular fa-eye"></i> View Profile</a>
                <a class="dropdown-item"><i class="fa-regular fa-pen-to-square"></i> Edit User</a>
                <a class="dropdown-item" onclick="setUserStatus(${u.id}, 'suspend')"><i class="fa-solid fa-ban"></i> Suspend</a>
                <a class="dropdown-item" onclick="setUserStatus(${u.id}, 'activate')"><i class="fa-solid fa-check-circle"></i> Activate</a>
                <a class="dropdown-item" style="color:var(--danger);" onclick="deleteUser(${u.id})"><i class="fa-solid fa-trash"></i> Delete</a>
                <a class="dropdown-item" onclick="resetPassword(${u.id})"><i class="fa-solid fa-key"></i> Reset Password</a>
                <a class="dropdown-item"><i class="fa-regular fa-bell"></i> Send Notification</a>
              </div>
            </div>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    function escapeHtml(s) {
      return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    async function refreshStats() {
      try {
        const r = await fetch(API_BASE + '?action=stats', { credentials: 'same-origin' });
        const data = await r.json();
        if (!data || !data.ok) return;
        const s = data.stats || {};
        const elTotal = document.getElementById('statTotal');
        const elNew = document.getElementById('statNewToday');
        const elVer = document.getElementById('statVerified');
        const elSus = document.getElementById('statSuspended');
        if (elTotal) elTotal.textContent = String(s.total ?? 0);
        if (elNew) elNew.textContent = String(s.new_today ?? 0);
        if (elVer) elVer.textContent = String(s.verified ?? 0);
        if (elSus) elSus.textContent = String(s.suspended ?? 0);
      } catch (e) {}
    }

    async function refreshActivity() {
      const el = document.getElementById('activityFeedBody');
      if (!el) return;
      try {
        const r = await fetch(API_BASE + '?action=activity&limit=12', { credentials: 'same-origin' });
        const data = await r.json();
        if (!data || !data.ok || !Array.isArray(data.items)) return;
        if (data.items.length === 0) {
          el.innerHTML = '<div class="feed-item" style="color:var(--text-muted);"><span>No recent activity yet.</span></div>';
          return;
        }
        el.innerHTML = data.items.map((it) => `
          <div class="feed-item">
            <span class="feed-icon"><i class="${escapeHtml(it.icon)}"></i></span>
            <span><strong>${escapeHtml(it.name)}</strong> ${escapeHtml(it.detail)}</span>
            <span style="color: var(--text-muted); margin-left: auto;">${escapeHtml(it.time_ago)}</span>
          </div>
        `).join('');
      } catch (e) {}
    }

    async function fetchUsers() {
      const url = new URL(API_BASE, window.location.href);
      url.searchParams.set('action', 'list');
      url.searchParams.set('page', String(state.page));
      url.searchParams.set('per_page', String(state.perPage));
      if (state.q) url.searchParams.set('q', state.q);
      if (state.filter) url.searchParams.set('filter', state.filter);
      if (state.sort) url.searchParams.set('sort', state.sort);

      tbody.innerHTML = '<tr><td colspan="12" style="padding:1rem;color:var(--text-muted);">Loading…</td></tr>';
      try {
        const r = await fetch(url.toString(), { credentials: 'same-origin' });
        const data = await r.json();
        if (!data || !data.ok) throw new Error('bad');
        state.users = Array.isArray(data.users) ? data.users : [];
        state.total = Number(data.total || 0);
        state.page = Number(data.page || state.page || 1);
        state.perPage = Number(data.per_page || state.perPage || 25);
        renderTable();
        renderPagination();
        refreshStats();
        refreshActivity();
      } catch (e) {
        tbody.innerHTML = '<tr><td colspan="12" style="padding:1rem;color:var(--danger);">Failed to load users.</td></tr>';
        if (paginationEl) paginationEl.innerHTML = '';
      }
    }

    async function postAction(payload) {
      const r = await fetch(API_BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ ...payload, csrf_token: CSRF_TOKEN })
      });
      return await r.json();
    }

    window.setUserStatus = async function(id, action) {
      if (!id) return;
      const res = await postAction({ action, id });
      if (res && res.ok) {
        fetchUsers();
      } else {
        alert('Action failed.');
      }
    };

    window.deleteUser = async function(id) {
      if (!id) return;
      if (!(await UiModal.confirm('Delete this user? This cannot be undone.', { title: 'Delete user', danger: true }))) return;
      const res = await postAction({ action: 'delete', id });
      if (res && res.ok) {
        fetchUsers();
      } else {
        alert('Delete failed.');
      }
    };

    window.resetPassword = async function(id) {
      if (!id) return;
      if (!(await UiModal.confirm('Reset this user password and generate a temporary password?', { title: 'Reset password' }))) return;
      const res = await postAction({ action: 'reset_password', id });
      if (res && res.ok && res.temp_password) {
        alert('Temporary password: ' + res.temp_password);
      } else {
        alert('Reset failed.');
      }
    };

    // Dropdown toggle
    window.toggleDropdown = function(btn) {
      const allMenus = document.querySelectorAll('.dropdown-menu');
      allMenus.forEach(m => m.classList.remove('show'));
      const menu = btn.nextElementSibling;
      menu.classList.toggle('show');
    };
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.actions-dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show'));
      }
    });

    // Select all checkbox
    const selectAll = document.getElementById('selectAll');
    selectAll.addEventListener('change', (e) => {
      document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = e.target.checked);
      updateBulkBar();
    });

    function updateBulkBar() {
      const checked = document.querySelectorAll('.user-checkbox:checked').length;
      const bulkBar = document.getElementById('bulkBar');
      if (checked > 0) {
        bulkBar.style.display = 'flex';
        document.getElementById('selectedCount').innerText = checked;
      } else {
        bulkBar.style.display = 'none';
      }
    }

    document.addEventListener('change', (e) => {
      if (e.target.classList.contains('user-checkbox')) updateBulkBar();
    });

    // View profile modal
    window.viewProfile = async function(id) {
      let user = null;
      try {
        const r = await fetch(API_BASE + '?action=detail&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
        const data = await r.json();
        if (data && data.ok) user = data.user;
      } catch (e) {}
      if (!user) {
        alert('User not found.');
        return;
      }
      const modalBody = document.getElementById('modalBody');
      const avatar = initials(user.full_name || user.email);
      const status = (user.is_active === 1) ? 'Active' : 'Suspended';
      modalBody.innerHTML = `
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap;">
          <div class="user-avatar-sm" style="width:64px;height:64px; font-size:1.5rem;">${avatar}</div>
          <div><h3>${user.full_name || '—'}</h3><p>${user.email || '—'}</p></div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
          <div><strong>User ID</strong><br>#${user.id}</div>
          <div><strong>Status</strong><br><span class="badge ${getBadgeClass('status', status)}">${status}</span></div>
          <div><strong>Joined</strong><br>${fmtDate(user.created_at)}</div>
          <div><strong>Last Active</strong><br>${fmtDate(user.last_login_at)}</div>
        </div>
        <h4 style="margin:1.5rem 0 0.5rem;">Transaction history</h4>
        <div style="overflow-x:auto;">
          <table style="min-width:100%; font-size:0.8rem;">
            <tr><th>Date</th><th>Amount</th><th>Status</th></tr>
            <tr><td>2025-03-15</td><td>+$1,200</td><td><span class="badge badge-green">completed</span></td></tr>
            <tr><td>2025-03-14</td><td>-$450</td><td><span class="badge badge-green">completed</span></td></tr>
          </table>
        </div>
      `;
      document.getElementById('userModal').style.display = 'flex';
    };

    document.getElementById('closeModal').addEventListener('click', () => {
      document.getElementById('userModal').style.display = 'none';
    });

    // AI assistant
    document.getElementById('aiBtn').addEventListener('click', () => {
      alert('🤖 AI: 12 new users today, 3 pending KYC. Suggest reviewing unverified accounts.');
    });

    // Resize handler
    window.addEventListener('resize', () => {
      if (window.innerWidth > 1100) sidebar.classList.remove('mobile-open');
    });

    // Search/filter wiring
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterSelect');
    const sortSelect = document.getElementById('sortSelect');
    let searchTimer = null;
    if (searchInput) {
      searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
          state.q = searchInput.value.trim();
          state.page = 1;
          fetchUsers();
        }, 250);
      });
    }
    if (filterSelect) {
      filterSelect.addEventListener('change', () => {
        state.filter = filterSelect.value;
        state.page = 1;
        fetchUsers();
      });
    }
    if (sortSelect) {
      sortSelect.addEventListener('change', () => {
        state.sort = sortSelect.value;
        state.page = 1;
        fetchUsers();
      });
    }

    fetchUsers();
  </script>
  <?php require_once __DIR__ . '/includes/admin_notify_client.php'; ?>
</body>
</html>