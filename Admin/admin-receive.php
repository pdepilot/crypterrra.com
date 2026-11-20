<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_transactions_data.php';

/**
 * @return string
 */
function admin_receive_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function admin_receive_short_wallet(string $w): string {
  $w = trim($w);
  if ($w === '') return '—';
  if (strlen($w) <= 18) return $w;
  return substr($w, 0, 10) . '…' . substr($w, -6);
}

$receiveRecords = [];
try {
  $db = admin_tx_db();
  ensure_transactions_table($db);
  $list = admin_tx_list($db, 1, 250, '', 'receive', 'all', 'newest');
  $db->close();

  foreach (($list['rows'] ?? []) as $row) {
    $meta = is_array($row['meta'] ?? null) ? $row['meta'] : [];
    $from = isset($meta['from_address']) && is_string($meta['from_address']) ? $meta['from_address'] : '';
    $to = isset($meta['to_address']) && is_string($meta['to_address']) ? $meta['to_address'] : '';
    $cryptoAmount = $meta['crypto_amount'] ?? null;
    if (!is_numeric($cryptoAmount)) {
      $cryptoAmount = $row['amount'] ?? 0;
    }
    $status = strtolower((string)($row['status'] ?? 'pending'));
    $receiveRecords[] = [
      'id' => 'RCV' . (string)($row['id'] ?? ''),
      'tx_id' => (int)($row['id'] ?? 0),
      'userId' => '#U' . (string)($row['user_id'] ?? 0),
      'userName' => (string)($row['user_label'] ?? 'Unknown'),
      'source' => admin_receive_short_wallet((string)$from),
      'destination' => admin_receive_short_wallet($to !== '' ? $to : (string)($row['wallet'] ?? '')),
      'asset' => (string)($row['asset'] ?? ''),
      'amount' => (string)$cryptoAmount,
      'network' => (string)($row['network'] ?? '—'),
      // Confirmations are not tracked in this app yet; keep placeholders to match UI.
      'confirmations' => $status === 'completed' ? 1 : 0,
      'required' => 1,
      'status' => ($status === 'completed') ? 'confirmed' : 'pending',
      'timestamp' => (string)($row['created_at'] ?? ''),
      'from_address_full' => $from,
      'to_address_full' => $to,
      'meta' => $meta,
    ];
  }
} catch (Throwable $e) {
  // Keep empty list; page still loads.
  $receiveRecords = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0" />
  <title>Crypterra · Receive Management | Admin Center</title>
  <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <!-- Google Fonts Inter & Space Grotesk -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <!-- AOS -->
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
    table { width: 100%; border-collapse: collapse; min-width: 1000px; }
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

    .modal {
      display: none;
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.85);
      backdrop-filter: blur(12px);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    .modal-card {
      background: var(--surface);
      border-radius: 40px;
      padding: 2rem;
      width: 90%;
      max-width: 500px;
      border: 1px solid var(--primary);
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
      <a href="./admin-receive.php" class="sidebar-link active"><i class="fa-solid fa-arrow-down"></i><span>Receive Mgmt</span></a>
      <a href="./admin-transactions.php" class="sidebar-link"><i class="fa-solid fa-arrow-right-arrow-left"></i><span>Transactions</span></a>
      <a href="./admin-kyc.php" class="sidebar-link"><i class="fa-solid fa-id-card"></i><span>KYC Management</span></a>
      <a href="./admin-security-center.php" class="sidebar-link"><i class="fa-solid fa-shield"></i><span>Security Center</span></a>
      <a href="./admin-help-center.php" class="sidebar-link"><i class="fa-solid fa-life-ring"></i><span>Help Center</span></a>
      <a href="./admin-settings.php" class="sidebar-link"><i class="fa-solid fa-gear"></i><span>Settings</span></a>
      <div style="margin-top: 2rem"></div>
      <a href="./admin-logout.php" class="sidebar-link"><i class="fa-solid fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
  </aside>

  <main class="main">
    <div class="top-bar">
      <div class="header-left"><div class="mobile-menu-btn" id="mobileMenuBtn"><i class="fa-solid fa-bars"></i></div><div class="search-box"><i class="fa-solid fa-search"></i><input type="text" id="searchInput" placeholder="Search by user, txid or address..."></div></div>
      <div class="header-right"><div class="system-status"><span class="status-dot"></span><span>System Online</span></div><div class="icon-btn"><i class="fa-regular fa-bell"></i><span class="badge"></span></div><div class="icon-btn"><i class="fa-regular fa-envelope"></i></div><div class="theme-toggle" id="themeToggle"></div><div class="icon-btn" style="width: auto; padding: 0 12px; border-radius: 40px; gap: 6px;"><i class="fa-regular fa-user"></i><span>SA</span></div></div>
    </div>

    <div class="content">
      <!-- Receive Metrics -->
      <div class="metric-grid" data-aos="fade-up">
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-arrow-down"></i></span><span class="metric-change positive"><i class="fa-solid fa-arrow-up"></i> +24%</span></div><div class="metric-value">2,847</div><div>Total Receive TX</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-regular fa-hourglass-half"></i></span><span class="metric-change positive"><i class="fa-solid fa-arrow-up"></i> +12</span></div><div class="metric-value">63</div><div>Pending Confirmation</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-check-circle"></i></span><span class="metric-change positive"><i class="fa-solid fa-arrow-up"></i> +18%</span></div><div class="metric-value">2,712</div><div>Confirmed</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-dollar-sign"></i></span><span class="metric-change positive"><i class="fa-solid fa-arrow-up"></i> +32%</span></div><div class="metric-value">$14.2M</div><div>Total Received Value</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-chart-line"></i></span><span class="metric-change positive"><i class="fa-solid fa-arrow-up"></i> +8%</span></div><div class="metric-value">$1.8M</div><div>Avg Daily Receive</div><div class="spark"></div></div>
        <div class="metric-card"><div class="metric-header"><span class="metric-icon"><i class="fa-solid fa-clock"></i></span><span class="metric-change negative"><i class="fa-solid fa-arrow-down"></i> -12min</span></div><div class="metric-value">18min</div><div>Avg Confirmation Time</div><div class="spark"></div></div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <select class="filter-select" id="statusFilter"><option value="all">All Status</option><option value="pending">Pending</option><option value="confirmed">Confirmed</option></select>
        <select class="filter-select" id="assetFilter"><option value="all">All Assets</option><option value="BTC">BTC</option><option value="ETH">ETH</option><option value="USDT">USDT</option><option value="SOL">SOL</option></select>
        <select class="filter-select" id="networkFilter"><option value="all">All Networks</option><option value="ERC20">ERC20</option><option value="TRC20">TRC20</option><option value="BSC">BSC</option><option value="Solana">Solana</option></select>
        <button class="action-btn" id="applyFilters">Apply Filters</button>
        <button class="action-btn" id="exportCSVBtn"><i class="fa-regular fa-file-excel"></i> Export CSV</button>
      </div>

      <!-- Main Receive Records Table (synced from Userprofile/receive.php) -->
      <div class="glass-table-container">
        <h3 style="margin-bottom:1rem"><i class="fa-solid fa-arrow-down" style="color:var(--primary)"></i> User Receive Records · Incoming Transactions</h3>
        <table id="receiveTable">
          <thead>
            <tr><th>Receive ID</th><th>User</th><th>Source Address (Sender)</th><th>Destination Wallet</th><th>Asset</th><th>Amount</th><th>Network</th><th>Confirmations</th><th>Status</th><th>Timestamp</th></tr>
          </thead>
          <tbody id="receiveTableBody"></tbody>
        </table>
      </div>

      <!-- Analytics & Recent Activity -->
      <div class="two-col">
        <div class="glass-table-container">
          <h3><i class="fa-solid fa-chart-pie"></i> Receive Volume by Asset</h3>
          <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px;">
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-brands fa-bitcoin"></i><div><strong>BTC</strong><br>342 txs · $5.2M</div></div>
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-brands fa-ethereum"></i><div><strong>ETH</strong><br>891 txs · $4.8M</div></div>
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-solid fa-dollar-sign"></i><div><strong>USDT</strong><br>1,204 txs · $3.6M</div></div>
            <div class="alert-card" style="border-left-color:var(--primary)"><i class="fa-brands fa-solana"></i><div><strong>SOL</strong><br>410 txs · $0.6M</div></div>
          </div>
        </div>
        <div class="glass-table-container">
          <h3><i class="fa-solid fa-bell"></i> Pending Alerts</h3>
          <div class="alert-card"><i class="fa-solid fa-hourglass-start"></i><div><strong>63 pending confirmations</strong><br>12 transactions waiting > 30 minutes</div><button class="action-btn" onclick="alert('Review slow confirmations')">Review</button></div>
          <div class="alert-card"><i class="fa-solid fa-flag-checkered"></i><div><strong>High-value pending</strong><br>$340k USDT receive requires 2 more confirmations</div></div>
          <div class="alert-card"><i class="fa-solid fa-link"></i><div><strong>Unusual source</strong><br>3 receives from mixer address flagged</div></div>
        </div>
      </div>

      <!-- Admin Activity Logs for Receive Management -->
      <div class="glass-table-container">
        <h3><i class="fa-regular fa-clock"></i> Admin Activity Logs (Receive Overview)</h3>
        <table style="min-width:700px"><thead><tr><th>Timestamp</th><th>Admin</th><th>Action</th><th>Receive ID</th><th>Note</th></tr></thead><tbody id="activityLogsBody"></tbody></table>
      </div>
    </div>
  </main>
</div>

<div class="ai-assistant" id="aiBtn"><i class="fa-solid fa-robot"></i></div>

<!-- Details Modal -->
<div id="detailsModal" class="modal"><div class="modal-card"><h3><i class="fa-solid fa-receipt"></i> Receive Details</h3><div id="detailsContent"></div><button class="action-btn" id="closeDetailsModal" style="margin-top:1rem;">Close</button></div></div>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({ duration: 700, once: true });
  
  // Sidebar & theme
  const sidebar = document.getElementById("sidebar");
  document.getElementById("collapseSidebar").addEventListener("click", () => { sidebar.classList.toggle("collapsed"); const icon = document.querySelector("#collapseSidebar i"); icon.classList.toggle("fa-chevron-left"); icon.classList.toggle("fa-chevron-right"); });
  document.getElementById("mobileMenuBtn").addEventListener("click", () => { sidebar.classList.toggle("mobile-open"); });
  document.addEventListener("click", (e) => { if(window.innerWidth <= 1100 && !sidebar.contains(e.target) && !document.getElementById("mobileMenuBtn").contains(e.target)) sidebar.classList.remove("mobile-open"); });
  document.getElementById("themeToggle").addEventListener("click", () => { document.body.classList.toggle("dark"); });

  // DB-backed receive data (synced from Userprofile/receive.php -> wallet_api.php action=receive)
  let receiveRecords = <?php echo json_encode($receiveRecords, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?>;

  let activityLogs = [
    { time:"2025-04-09 14:30", admin:"superadmin@crypterra.io", action:"Viewed", request:"RCV1001", note:"Checked large ETH receive" }
  ];

  function renderReceiveTable() {
    const tbody = document.getElementById("receiveTableBody");
    tbody.innerHTML = "";
    const statusFilter = document.getElementById("statusFilter").value;
    const assetFilter = document.getElementById("assetFilter").value;
    const networkFilter = document.getElementById("networkFilter").value;
    const searchTerm = document.getElementById("searchInput").value.toLowerCase();
    
    const filtered = receiveRecords.filter(r => 
      (statusFilter === "all" || r.status === statusFilter) &&
      (assetFilter === "all" || r.asset === assetFilter) &&
      (networkFilter === "all" || r.network === networkFilter) &&
      (r.userName.toLowerCase().includes(searchTerm) || r.id.toLowerCase().includes(searchTerm) || r.source.toLowerCase().includes(searchTerm) || r.destination.toLowerCase().includes(searchTerm))
    );
    
    filtered.forEach(r => {
      const statusClass = r.status === "pending" ? "badge-yellow" : "badge-green";
      const confirmStatus = `${r.confirmations}/${r.required}`;
      const confirmClass = r.confirmations >= r.required ? "badge-green" : "badge-yellow";
      
      const row = `<tr data-id="${r.id}">
        <td>${r.id}</td>
        <td>${r.userName} (${r.userId})</td>
        <td><small>${r.source}</small></td>
        <td><small>${r.destination}</small></td>
        <td>${r.asset}</td>
        <td>${r.amount}</td>
        <td>${r.network}</td>
        <td><span class="badge-status ${confirmClass}">${confirmStatus}</span></td>
        <td><span class="badge-status ${statusClass}">${r.status === "confirmed" ? "Confirmed" : "Pending"}</span></td>
        <td>${r.timestamp}</td>
        </tr>`;
      tbody.insertAdjacentHTML('beforeend', row);
    });

    // Row click -> details (read-only, no approve/decline actions)
    tbody.querySelectorAll("tr[data-id]").forEach((tr) => {
      tr.addEventListener("click", () => {
        const id = tr.getAttribute("data-id");
        if (id) viewDetails(id);
      });
    });
  }

  window.viewDetails = (id) => {
    const r = receiveRecords.find(r => r.id === id);
    if(r) {
      const detailsHtml = `
        <p><strong>Receive ID:</strong> ${r.id}</p>
        <p><strong>Transaction ID:</strong> ${r.tx_id ?? ''}</p>
        <p><strong>User:</strong> ${r.userName} (${r.userId})</p>
        <p><strong>Source Address:</strong> ${r.from_address_full || r.source}</p>
        <p><strong>Destination Wallet:</strong> ${r.to_address_full || r.destination}</p>
        <p><strong>Asset:</strong> ${r.asset}</p>
        <p><strong>Amount:</strong> ${r.amount}</p>
        <p><strong>Network:</strong> ${r.network}</p>
        <p><strong>Confirmations:</strong> ${r.confirmations}/${r.required}</p>
        <p><strong>Status:</strong> ${r.status}</p>
        <p><strong>Timestamp:</strong> ${r.timestamp}</p>
      `;
      document.getElementById("detailsContent").innerHTML = detailsHtml;
      document.getElementById("detailsModal").style.display = "flex";
      // Log activity
      activityLogs.unshift({ time: new Date().toLocaleString(), admin: "superadmin@crypterra.io", action: "Viewed details", request: id, note: "Inspected receive record" });
      renderActivityLogs();
    }
  };

  function renderActivityLogs() {
    const tbody = document.getElementById("activityLogsBody");
    tbody.innerHTML = "";
    activityLogs.slice(0,10).forEach(log => {
      tbody.insertAdjacentHTML('beforeend', `<tr><td>${log.time}</td><td>${log.admin}</td><td>${log.action}</td><td>${log.request}</td><td>${log.note}</td>`);
    });
  }

  document.getElementById("applyFilters").addEventListener("click", () => { renderReceiveTable(); });
  document.getElementById("searchInput").addEventListener("input", () => { renderReceiveTable(); });
  
  document.getElementById("exportCSVBtn").addEventListener("click", () => {
    let csv = "Receive ID,User,Source,Destination,Asset,Amount,Network,Confirmations,Status,Timestamp\n";
    receiveRecords.forEach(r => { csv += `${r.id},${r.userName},${r.source},${r.destination},${r.asset},${r.amount},${r.network},${r.confirmations}/${r.required},${r.status},${r.timestamp}\n`; });
    const blob = new Blob([csv], {type: "text/csv"});
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob); link.download = "crypterra_receive_records.csv"; link.click();
  });

  document.getElementById("closeDetailsModal").addEventListener("click", () => {
    document.getElementById("detailsModal").style.display = "none";
  });

  // AI Assistant Insights
  document.getElementById("aiBtn").addEventListener("click", () => {
    const pendingCount = receiveRecords.filter(r => r.status === "pending").length;
    const highValuePending = receiveRecords.filter(r => r.status === "pending" && parseFloat(r.amount) > 10000).length;
    alert(`🤖 AI Receive Assistant Insights:\n• ${pendingCount} pending receive confirmations\n• ${highValuePending} high-value pending transactions (>10k)\n• Total received today: $1.24M\n• Suggested: review pending USDT transaction RCV1006 (large amount)`);
  });

  renderReceiveTable();
  renderActivityLogs();
</script>
</body>
</html>