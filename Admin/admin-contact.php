<?php
require_once __DIR__ . '/includes/admin_auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
  <title>Crypterra · Contact Management | Admin Center</title>
  <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
  <!-- Font Awesome 6 (free) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <!-- Google Fonts Inter & Space Grotesk -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
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
      transition: width 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1), left 0.3s;
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
    .sidebar::-webkit-scrollbar { display: none; }
    .sidebar.collapsed { width: 95px; }
    .sidebar.collapsed .sidebar-link span,
    .sidebar.collapsed .sidebar-header span,
    .sidebar.collapsed .admin-info { display: none; }

    @media (max-width: 1100px) {
      .sidebar { position: fixed; left: -300px; }
      .sidebar.mobile-open { left: 0; }
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
      width: 34px;
      height: 34px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      color: var(--primary);
      transition: 0.3s;
    }
    .collapse-btn:hover { background: var(--primary); color: white; transform: rotate(180deg); }

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
      background: linear-gradient(145deg, var(--primary), var(--primary-dark));
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      border: 2px solid var(--primary);
      box-shadow: 0 0 20px var(--primary-glow);
    }
    .admin-details h4 { font-size: 0.95rem; }
    .admin-details p { font-size: 0.7rem; color: var(--text-muted); }

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
    .sidebar.collapsed .nav-section { display: none; }
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
    .sidebar-link i { width: 24px; font-size: 1.2rem; text-align: center; }
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
    .sidebar-link:hover::before, .sidebar-link.active::before { width: 100%; }
    .sidebar-link:hover, .sidebar-link.active {
      color: var(--primary);
      transform: translateX(5px);
      box-shadow: 0 0 20px var(--primary-glow);
    }
    .sidebar-link.active { background: var(--primary-soft); border: 1px solid var(--glass-edge); }

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
    .header-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
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
    .header-right { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
    .system-status { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; }
    .status-dot {
      width: 10px; height: 10px; border-radius: 50%;
      background: var(--success);
      box-shadow: 0 0 15px #10b981;
      animation: pulse 2s infinite;
    }
    @keyframes pulse { 0% { opacity: 0.8; } 50% { opacity: 1; } 100% { opacity: 0.8; } }
    .icon-btn {
      width: 40px; height: 40px; border-radius: 50%;
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
    .icon-btn:hover { background: var(--primary); color: white; transform: translateY(-2px); box-shadow: var(--shadow-glow); }
    .badge {
      position: absolute;
      top: 5px; right: 5px;
      width: 8px; height: 8px;
      background: var(--danger);
      border-radius: 50%;
    }
    .theme-toggle {
      width: 52px; height: 26px;
      background: #3a4a5a;
      border-radius: 34px;
      position: relative;
      cursor: pointer;
    }
    .theme-toggle::before {
      content: "";
      position: absolute;
      width: 20px; height: 20px;
      background: white;
      border-radius: 50%;
      top: 2px; left: 2px;
      transition: 0.3s;
    }
    body.dark .theme-toggle { background: var(--primary); }
    body.dark .theme-toggle::before { transform: translateX(26px); }

    .content { padding: 1.5rem; }

    /* metric cards */
    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    .metric-card:hover { border-color: var(--primary); box-shadow: var(--shadow-glow); transform: translateY(-5px); }
    .metric-header { display: flex; justify-content: space-between; margin-bottom: 1rem; }
    .metric-icon {
      width: 48px; height: 48px;
      border-radius: 20px;
      background: var(--primary-soft);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: var(--primary);
    }
    .metric-value { font-size: 2rem; font-weight: 700; }
    .metric-change { font-size: 0.8rem; display: flex; align-items: center; gap: 4px; }
    .positive { color: var(--success); }
    .negative { color: var(--danger); }
    .spark { height: 30px; width: 100%; background: linear-gradient(90deg, var(--primary-soft) 0%, var(--primary-light) 50%, var(--primary-soft) 100%); background-size: 200% 100%; animation: shimmer 1.5s infinite; border-radius: 20px; margin-top: 8px; }
    @keyframes shimmer { 0% { background-position: -100% 0; } 100% { background-position: 100% 0; } }

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
    table { width: 100%; border-collapse: collapse; min-width: 900px; }
    th { text-align: left; padding: 1rem 0.5rem; color: var(--primary); border-bottom: 2px solid var(--glass-edge); }
    td { padding: 1rem 0.5rem; border-bottom: 1px solid var(--glass-edge); vertical-align: middle; }
    tr:hover { background: var(--primary-soft); }
    .badge-status { padding: 0.2rem 0.8rem; border-radius: 40px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
    .badge-green { background: rgba(16, 185, 129, 0.2); color: var(--success); }
    .badge-yellow { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
    .badge-red { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
    .badge-blue { background: rgba(59, 130, 246, 0.2); color: var(--info); }
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
    .action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

    .two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.2rem;
      margin-bottom: 1.5rem;
    }
    @media (max-width: 800px) { .two-col { grid-template-columns: 1fr; } }

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

    .reply-modal {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.8);
      backdrop-filter: blur(8px);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .reply-card {
      background: var(--surface);
      border-radius: 40px;
      padding: 2rem;
      width: 90%;
      max-width: 550px;
      border: 1px solid var(--primary);
    }
    .reply-card textarea {
      width: 100%;
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 24px;
      padding: 1rem;
      color: var(--text);
      margin: 1rem 0;
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
    .ai-assistant:hover { transform: scale(1.1); background: var(--primary-dark); }
    @keyframes float { 0% { transform: translateY(0); } 50% { transform: translateY(-8px); } 100% { transform: translateY(0); } }

    @media (max-width: 1100px) { .mobile-menu-btn { display: flex; } }
    @media (max-width: 768px) { .content { padding: 1rem; } .top-bar { padding: 0.8rem 1rem; } .search-box { width: 100%; } .search-box input { width: 100%; } .metric-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); } }
  </style>
</head>
<body class="dark">
<div class="admin">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-header"><div class="logo"><div class="logo-icon"><i class="fa-solid fa-globe"></i></div><span>Crypterra</span></div><div class="collapse-btn" id="collapseSidebar"><i class="fa-solid fa-chevron-left"></i></div></div>
    <div class="admin-info"><div class="admin-avatar">SA</div><div class="admin-details"><h4>Super Admin</h4><p>sysadmin@crypterra.io</p></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section">MAIN</div>
      <a href="./admin-dashboard.php" class="sidebar-link"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></a>
      <a href="./admin-users.php" class="sidebar-link"><i class="fa-solid fa-users"></i><span>Users</span></a>
      <a href="./admin-send.php" class="sidebar-link"><i class="fa-solid fa-paper-plane"></i><span>Send Mgmt</span></a>
      <a href="./admin-receive.php" class="sidebar-link"><i class="fa-solid fa-arrow-down"></i><span>Receive Mgmt</span></a>
      <a href="./admin-transactions.php" class="sidebar-link"><i class="fa-solid fa-arrow-right-arrow-left"></i><span>Transactions</span></a>
      <a href="./admin-contact.php" class="sidebar-link active"><i class="fa-solid fa-envelope"></i><span>Contact Mgmt</span></a>
      <a href="./admin-help-center.php" class="sidebar-link"><i class="fa-solid fa-life-ring"></i><span>Help Center</span></a>
            <a href="./admin-deposit.php" class="sidebar-link"><i class="fa-solid fa-arrow-down"></i><span>Deposits</span></a>
      <a href="./admin-withdrawal.php" class="sidebar-link"><i class="fa-solid fa-arrow-up"></i><span>Withdrawals</span></a>
      <a href="./admin-kyc.php" class="sidebar-link"><i class="fa-solid fa-id-card"></i><span>KYC Management</span></a>
      <a href="./admin-wallet-control.php" class="sidebar-link"><i class="fa-solid fa-wallet"></i><span>Wallet Control</span></a>
      <a href="./admin-market-control.php" class="sidebar-link"><i class="fa-solid fa-chart-line"></i><span>Market Control</span></a>
      <a href="./admin-security-center.php" class="sidebar-link"><i class="fa-solid fa-shield"></i><span>Security Center</span></a>
      <a href="./admin-analytics.php" class="sidebar-link"><i class="fa-solid fa-magnifying-glass-chart"></i><span>Analytics</span></a>
      <a href="./admin-notifications.php" class="sidebar-link"><i class="fa-solid fa-bell"></i><span>Notifications</span></a>
      <a href="./admin-settings.php" class="sidebar-link"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      <div style="margin-top: 2rem"></div>
      <a href="./admin-logout.php" class="sidebar-link"><i class="fa-solid fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
  </aside>

  <main class="main">
    <div class="top-bar">
      <div class="header-left"><div class="mobile-menu-btn" id="mobileMenuBtn"><i class="fa-solid fa-bars"></i></div><div class="search-box"><i class="fa-solid fa-search"></i><input type="text" id="searchInput" placeholder="Search by name, email or ticket..."></div></div>
      <div class="header-right"><div class="system-status"><span class="status-dot"></span><span>System Online</span></div><div class="icon-btn"><i class="fa-regular fa-bell"></i><span class="badge"></span></div><div class="icon-btn"><i class="fa-regular fa-envelope"></i></div><div class="theme-toggle" id="themeToggle"></div><div class="icon-btn" style="width: auto; padding: 0 12px; border-radius: 40px; gap: 6px;"><i class="fa-regular fa-user"></i><span>SA</span></div></div>
    </div>

    <div class="content">
      <!-- Contact Metrics -->
      <div class="metric-grid" data-aos="fade-up">
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-regular fa-message"></i></span></div><div class="metric-value" id="metricTotal">0</div><div>Total inquiries</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-regular fa-clock"></i></span></div><div class="metric-value" id="metricOpen">0</div><div>Open tickets</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-spinner"></i></span></div><div class="metric-value" id="metricProgress">0</div><div>In progress</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-check-circle"></i></span></div><div class="metric-value" id="metricResolved">0</div><div>Resolved</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-flag"></i></span></div><div class="metric-value" id="metricUrgent">0</div><div>High priority (open)</div><div class="spark"></div></div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <select class="filter-select" id="statusFilter"><option value="all">All Status</option><option value="open">Open</option><option value="in-progress">In Progress</option><option value="resolved">Resolved</option></select>
        <select class="filter-select" id="priorityFilter"><option value="all">All Priority</option><option value="high">High</option><option value="medium">Medium</option><option value="low">Low</option></select>
        <select class="filter-select" id="categoryFilter"><option value="all">All Categories</option><option value="support">Support</option><option value="security">Security</option><option value="billing">Billing</option><option value="general">General</option></select>
        <button class="action-btn" id="applyFilters">Apply Filters</button>
        <button class="action-btn" id="exportCSVBtn"><i class="fa-regular fa-file-excel"></i> Export CSV</button>
      </div>

      <!-- Main Contact Table (Frontend contact submissions) -->
      <div class="glass-table-container">
        <h3 style="margin-bottom:1rem"><i class="fa-regular fa-address-card" style="color:var(--primary)"></i> Frontend Contact Submissions · Manage Inquiries</h3>
        <table id="contactTable">
          <thead>
            <tr><th>Ticket ID</th><th>Name</th><th>Email</th><th>Category</th><th>Subject</th><th>Priority</th><th>Status</th><th>Received</th><th>Actions</th></tr>
          </thead>
          <tbody id="contactTableBody"></tbody>
        </table>
      </div>

      <!-- Quick Reply + AI Suggested Responses -->
      <div class="two-col">
        <div class="glass-table-container">
          <h3><i class="fa-regular fa-comment-dots"></i> Quick Reply Templates</h3>
          <div style="display: flex; flex-direction: column; gap: 12px;">
            <button class="action-btn" style="text-align:left" onclick="setQuickReply('Thank you for contacting Crypterra support. Your request has been received and our team will respond within 24 hours.')">📩 General acknowledgment</button>
            <button class="action-btn" style="text-align:left" onclick="setQuickReply('We have escalated your security concern to our risk team. Please provide any additional details.')">🔐 Security escalation</button>
            <button class="action-btn" style="text-align:left" onclick="setQuickReply('Your issue has been resolved. Let us know if you need further assistance.')">✅ Resolution confirmation</button>
            <button class="action-btn" style="text-align:left" onclick="setQuickReply('We are investigating your KYC-related issue. Expect an update within 6 hours.')">📄 KYC/billing inquiry</button>
          </div>
          <div style="margin-top: 1rem;">
            <label>Custom reply:</label>
            <textarea id="quickReplyText" rows="3" style="width:100%; background:var(--glass-bg); border:1px solid var(--glass-edge); border-radius:20px; padding:0.8rem; color:white;"></textarea>
            <button class="action-btn" id="sendReplyBtn" style="margin-top:8px;"><i class="fa-regular fa-paper-plane"></i> Send Reply to Selected</button>
          </div>
        </div>
        <div class="glass-table-container">
          <h3><i class="fa-regular fa-lightbulb"></i> AI Suggested Responses</h3>
          <div id="aiSuggestions" style="display:flex; flex-direction:column; gap:12px;">
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-solid fa-robot"></i><div><strong>💡 AI suggestion for high-priority:</strong><br>"We have flagged your request as urgent and a senior support agent will assist you within 2 hours."</div><button class="action-btn" onclick="setQuickReply('We have flagged your request as urgent and a senior support agent will assist you within 2 hours.')">Use</button></div>
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-solid fa-robot"></i><div><strong>🤖 Billing related:</strong><br>"To resolve your deposit issue, please confirm your transaction hash and we will manually verify."</div><button class="action-btn" onclick="setQuickReply('To resolve your deposit issue, please confirm your transaction hash and we will manually verify.')">Use</button></div>
          </div>
        </div>
      </div>

      <!-- Activity Logs for Contact Management -->
      <div class="glass-table-container">
        <h3><i class="fa-regular fa-clock"></i> Admin Activity Logs (Contact Management)</h3>
        <table style="min-width:700px"><thead><tr><th>Timestamp</th><th>Admin</th><th>Action</th><th>Ticket ID</th><th>Details</th></tr></thead><tbody id="activityLogsBody"></tbody></table>
      </div>
    </div>
  </main>
</div>
<div class="ai-assistant" id="aiBtn"><i class="fa-solid fa-robot"></i></div>

<!-- Reply Modal (View & Reply) -->
<div id="replyModal" class="reply-modal"><div class="reply-card"><h3><i class="fa-regular fa-reply"></i> Reply to Inquiry</h3><p id="modalTicketInfo"></p><p id="modalTicketSubject" style="opacity:.85;font-size:.9rem;margin:0.5rem 0"></p><div id="modalTicketMessage" style="max-height:220px;overflow:auto;background:var(--glass-bg);padding:1rem;border-radius:16px;border:1px solid var(--glass-edge);font-size:.85rem;white-space:pre-wrap;margin-bottom:0.75rem"></div><textarea id="modalReplyMessage" rows="5" placeholder="Write your response (logged for your team; email delivery can be added later)..."></textarea><div style="display:flex; gap:1rem; justify-content:flex-end;"><button class="action-btn" id="modalSendBtn">Log reply</button><button class="action-btn" id="modalCloseBtn">Cancel</button></div></div></div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({ duration: 700, once: true });
  const CONTACT_API = "api/contact_api.php";
  const sidebar = document.getElementById("sidebar");
  document.getElementById("collapseSidebar").addEventListener("click", () => { sidebar.classList.toggle("collapsed"); const icon = document.querySelector("#collapseSidebar i"); icon.classList.toggle("fa-chevron-left"); icon.classList.toggle("fa-chevron-right"); });
  document.getElementById("mobileMenuBtn").addEventListener("click", () => { sidebar.classList.toggle("mobile-open"); });
  document.addEventListener("click", (e) => { if(window.innerWidth <= 1100 && !sidebar.contains(e.target) && !document.getElementById("mobileMenuBtn").contains(e.target)) sidebar.classList.remove("mobile-open"); });
  document.getElementById("themeToggle").addEventListener("click", () => { document.body.classList.toggle("dark"); });

  let contacts = [];
  let activityLogs = [];

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/"/g, "&quot;");
  }

  function apiPost(body) {
    return fetch(CONTACT_API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "same-origin",
      body: JSON.stringify(body),
    }).then((r) => r.json());
  }

  function loadData() {
    return fetch(CONTACT_API + "?action=list", { credentials: "same-origin" })
      .then((r) => {
        if (r.status === 401) {
          window.location.href = "admin-login.php";
          return Promise.reject();
        }
        return r.json();
      })
      .then((data) => {
        if (!data || !data.ok) return;
        contacts = data.contacts || [];
        activityLogs = data.activity || [];
        const st = data.stats || {};
        const el = (id, v) => {
          const n = document.getElementById(id);
          if (n) n.textContent = String(v ?? 0);
        };
        el("metricTotal", st.total);
        el("metricOpen", st.open);
        el("metricProgress", st.in_progress);
        el("metricResolved", st.resolved);
        el("metricUrgent", st.urgent);
        renderContactTable();
        renderActivityLogs();
      })
      .catch(() => {});
  }

  function renderContactTable() {
    const tbody = document.getElementById("contactTableBody");
    tbody.innerHTML = "";
    const filterStatus = document.getElementById("statusFilter").value;
    const filterPriority = document.getElementById("priorityFilter").value;
    const filterCategory = document.getElementById("categoryFilter").value;
    const searchTerm = document.getElementById("searchInput").value.toLowerCase();
    const filtered = contacts.filter(
      (c) =>
        (filterStatus === "all" || c.status === filterStatus) &&
        (filterPriority === "all" || c.priority === filterPriority) &&
        (filterCategory === "all" || c.category === filterCategory) &&
        (c.name.toLowerCase().includes(searchTerm) ||
          c.email.toLowerCase().includes(searchTerm) ||
          (c.subject || "").toLowerCase().includes(searchTerm) ||
          (c.message || "").toLowerCase().includes(searchTerm))
    );
    filtered.forEach((c) => {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td>" +
        esc(c.ticket) +
        "</td><td>" +
        esc(c.name) +
        "</td><td>" +
        esc(c.email) +
        "</td><td>" +
        '<select class="filter-select cat-sel" data-id="' +
        c.id +
        '" style="min-width:110px">' +
        ["general", "support", "security", "billing"]
          .map(
            (v) =>
              '<option value="' +
              v +
              '"' +
              (c.category === v ? " selected" : "") +
              ">" +
              v +
              "</option>"
          )
          .join("") +
        "</select></td><td>" +
        esc(c.subject) +
        "</td>" +
        '<td><select class="filter-select pri-sel" data-id="' +
        c.id +
        '" style="min-width:100px">' +
        ["low", "medium", "high"]
          .map(
            (v) =>
              '<option value="' +
              v +
              '"' +
              (c.priority === v ? " selected" : "") +
              ">" +
              v +
              "</option>"
          )
          .join("") +
        "</select></td>" +
        '<td><select class="filter-select stat-sel" data-id="' +
        c.id +
        '" style="min-width:120px">' +
        ["open", "in-progress", "resolved"]
          .map(
            (v) =>
              '<option value="' +
              v +
              '"' +
              (c.status === v ? " selected" : "") +
              ">" +
              v +
              "</option>"
          )
          .join("") +
        "</select></td>" +
        "<td>" +
        esc(c.received) +
        '</td><td><button type="button" class="action-btn btn-reply" data-id="' +
        c.id +
        '"><i class="fa-regular fa-reply"></i> View / Reply</button> ' +
        '<button type="button" class="action-btn btn-resolve" data-id="' +
        c.id +
        '">Mark Resolved</button> ' +
        '<button type="button" class="action-btn btn-delete" data-id="' +
        c.id +
        '">Delete</button></td>';
      tbody.appendChild(tr);
    });
    tbody.querySelectorAll(".stat-sel").forEach((sel) => {
      sel.addEventListener("change", () => {
        const id = parseInt(sel.getAttribute("data-id"), 10);
        const val = sel.value;
        apiPost({ action: "update", id, field: "status", value: val }).then((data) => {
          if (data && data.ok) loadData();
          else alert("Could not update status.");
        });
      });
    });
    tbody.querySelectorAll(".pri-sel").forEach((sel) => {
      sel.addEventListener("change", () => {
        const id = parseInt(sel.getAttribute("data-id"), 10);
        apiPost({ action: "update", id, field: "priority", value: sel.value }).then((data) => {
          if (data && data.ok) loadData();
          else alert("Could not update priority.");
        });
      });
    });
    tbody.querySelectorAll(".cat-sel").forEach((sel) => {
      sel.addEventListener("change", () => {
        const id = parseInt(sel.getAttribute("data-id"), 10);
        apiPost({ action: "update", id, field: "category", value: sel.value }).then((data) => {
          if (data && data.ok) loadData();
          else alert("Could not update category.");
        });
      });
    });
    tbody.querySelectorAll(".btn-reply").forEach((btn) => {
      btn.addEventListener("click", () => {
        const id = parseInt(btn.getAttribute("data-id"), 10);
        const row = contacts.find((x) => x.id === id);
        if (row) viewAndReply(row);
      });
    });
    tbody.querySelectorAll(".btn-resolve").forEach((btn) => {
      btn.addEventListener("click", () => {
        resolveTicket(parseInt(btn.getAttribute("data-id"), 10));
      });
    });
    tbody.querySelectorAll(".btn-delete").forEach((btn) => {
      btn.addEventListener("click", () => {
        deleteTicket(parseInt(btn.getAttribute("data-id"), 10));
      });
    });
  }

  function viewAndReply(c) {
    document.getElementById("modalTicketInfo").textContent =
      c.ticket + " · " + c.name + " <" + c.email + ">";
    document.getElementById("modalTicketSubject").textContent = c.subject
      ? "Subject: " + c.subject
      : "(No subject)";
    document.getElementById("modalTicketMessage").textContent = c.message || "";
    document.getElementById("replyModal").style.display = "flex";
    window.currentReplyTicket = c;
  }

  document.getElementById("modalSendBtn").addEventListener("click", () => {
    const msg = document.getElementById("modalReplyMessage").value.trim();
    if (!msg || !window.currentReplyTicket) {
      alert("Write a message first");
      return;
    }
    apiPost({
      action: "reply_log",
      ticket_ref: window.currentReplyTicket.ticket,
      message: msg,
    }).then((data) => {
      if (data && data.ok) {
        document.getElementById("replyModal").style.display = "none";
        document.getElementById("modalReplyMessage").value = "";
        loadData();
      } else {
        alert("Could not log reply.");
      }
    });
  });
  document.getElementById("modalCloseBtn").addEventListener("click", () => {
    document.getElementById("replyModal").style.display = "none";
  });

  function resolveTicket(id) {
    if (!id) return;
    apiPost({ action: "resolve", id }).then((data) => {
      if (data && data.ok) loadData();
      else alert("Could not update.");
    });
  }

  async function deleteTicket(id) {
    if (!id) return;
    if (!(await UiModal.confirm("Delete this submission permanently?", { title: "Delete", danger: true }))) return;
    apiPost({ action: "delete", id }).then((data) => {
      if (data && data.ok) loadData();
      else alert("Could not delete.");
    });
  }

  function renderActivityLogs() {
    const tbody = document.getElementById("activityLogsBody");
    tbody.innerHTML = "";
    activityLogs.forEach((log) => {
      const tr = document.createElement("tr");
      tr.innerHTML =
        "<td>" +
        esc(log.time) +
        "</td><td>" +
        esc(log.admin) +
        "</td><td>" +
        esc(log.action) +
        "</td><td>" +
        esc(log.ticket) +
        "</td><td>" +
        esc(log.details) +
        "</td>";
      tbody.appendChild(tr);
    });
  }

  window.setQuickReply = (text) => {
    document.getElementById("quickReplyText").value = text;
  };
  document.getElementById("sendReplyBtn").addEventListener("click", () => {
    const replyText = document.getElementById("quickReplyText").value.trim();
    if (!replyText) {
      alert("Enter a message first");
      return;
    }
    const openRows = contacts.filter((c) => c.status === "open" || c.status === "in-progress");
    if (!openRows.length) {
      alert("No open tickets.");
      return;
    }
    Promise.all(
      openRows.slice(0, 20).map((c) =>
        apiPost({
          action: "reply_log",
          ticket_ref: c.ticket,
          message: replyText,
        })
      )
    ).then(() => loadData());
  });

  document.getElementById("applyFilters").addEventListener("click", () => {
    renderContactTable();
  });
  document.getElementById("searchInput").addEventListener("input", () => {
    renderContactTable();
  });
  document.getElementById("categoryFilter").addEventListener("change", () => {
    renderContactTable();
  });
  document.getElementById("exportCSVBtn").addEventListener("click", () => {
    let csv =
      "Ticket ID,Name,Email,Category,Subject,Message,Priority,Status,Received\n";
    contacts.forEach((c) => {
      const msg = (c.message || "").replace(/"/g, '""');
      csv +=
        '"' +
        c.ticket +
        '","' +
        c.name.replace(/"/g, '""') +
        '","' +
        c.email.replace(/"/g, '""') +
        '","' +
        c.category +
        '","' +
        (c.subject || "").replace(/"/g, '""') +
        '","' +
        msg +
        '","' +
        c.priority +
        '","' +
        c.status +
        '","' +
        c.received +
        '"\n';
    });
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = "crypterra_contacts.csv";
    link.click();
  });

  document.getElementById("aiBtn").addEventListener("click", () => {
    const hi = contacts.filter((c) => c.priority === "high" && c.status !== "resolved").length;
    alert(
      "Contact snapshot:\n• High-priority (not resolved): " +
        hi +
        "\n• Data loads from the same database as the public contact form."
    );
  });

  loadData();
</script>
  <?php require_once __DIR__ . '/includes/admin_notify_client.php'; ?>
</body>
</html>