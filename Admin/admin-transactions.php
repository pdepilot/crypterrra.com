<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · Admin · Transaction Management</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
<?php
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
require_once __DIR__ . '/includes/admin_transactions_data.php';
require_once __DIR__ . '/includes/admin_dashboard_data.php';
$txStats = [
  'total_count' => 0,
  'deposits_usd' => 0.0,
  'withdrawals_usd' => 0.0,
  'pending_count' => 0,
  'failed_count' => 0,
  'sparklines' => [
    'total' => array_fill(0, 7, 0),
    'deposits' => array_fill(0, 7, 0),
    'withdrawals' => array_fill(0, 7, 0),
    'pending' => array_fill(0, 7, 0),
    'failed' => array_fill(0, 7, 0),
  ],
];
$adminActivityLogs = [];
try {
  $db = admin_tx_db();
  ensure_transactions_table($db);
  $txStats = admin_tx_summary($db);
  $db->close();
} catch (Throwable) {
  // defaults
}
try {
  $adminActivityLogs = admin_dash_fetch_admin_activity_logs(6);
} catch (Throwable) {
  $adminActivityLogs = [];
}
?>
    <!-- Font Awesome 6 -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    />
    <!-- Google Fonts -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <!-- AOS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
      /* === EXACT STYLES FROM ADMIN-USERS === */
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
        font-family: "Inter", sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.5;
        overflow-x: hidden;
      }

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

      .content {
        padding: 1.5rem;
      }

      /* Page Header - exactly like admin-users */
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
      .page-header p {
        color: var(--text-secondary);
      }
      .breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-top: 5px;
      }
      .breadcrumb a {
        color: var(--text-secondary);
        text-decoration: none;
      }
      .breadcrumb a:hover {
        color: var(--primary);
      }

      /* Analytics Cards - perfectly styled */
      .analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 1.8rem;
      }

      .analytics-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 1.2rem;
        transition: 0.3s;
        position: relative;
        overflow: hidden;
      }

      .analytics-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-glow);
        transform: translateY(-3px);
      }

      .analytics-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
      }

      .analytics-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
      }

      .analytics-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
      }

      .analytics-label {
        color: var(--text-muted);
        font-size: 0.85rem;
      }

      .trend-up {
        color: var(--success);
        font-size: 0.8rem;
        background: rgba(16, 185, 129, 0.1);
        padding: 0.2rem 0.5rem;
        border-radius: 40px;
      }

      .trend-down {
        color: var(--danger);
        font-size: 0.8rem;
        background: rgba(239, 68, 68, 0.1);
        padding: 0.2rem 0.5rem;
        border-radius: 40px;
      }

      .mini-chart {
        height: 40px;
        width: 100%;
        margin-top: 0.5rem;
      }

      /* Control Bar - exactly like admin-users */
      .control-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 0.8rem 1.5rem;
      }

      .control-search {
        flex: 2;
        min-width: 250px;
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--glass-bg);
        border-radius: 40px;
        padding: 0.5rem 1rem;
      }

      .control-search i {
        color: var(--text-muted);
      }

      .control-search input {
        background: transparent;
        border: none;
        color: var(--text);
        outline: none;
        width: 100%;
      }

      .control-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
      }

      .filter-select {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.5rem 2rem 0.5rem 1rem;
        color: var(--text);
        font-size: 0.9rem;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2322c55e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
      }

      .date-range {
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.5rem 1rem;
      }

      .date-range i {
        color: var(--primary);
      }

      .btn-primary {
        background: var(--primary);
        border: none;
        border-radius: 40px;
        padding: 0.6rem 1.5rem;
        color: white;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
      }

      .btn-primary:hover {
        background: var(--primary-dark);
        transform: scale(1.02);
        box-shadow: var(--shadow-glow);
      }

      .btn-outline {
        background: transparent;
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.5rem 1rem;
        color: var(--text);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: 0.2s;
      }

      .btn-outline:hover {
        background: var(--primary-soft);
        border-color: var(--primary);
        color: var(--primary);
      }

      /* Fraud Alert Cards - properly styled */
      .fraud-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1rem;
        margin: 1.5rem 0;
      }

      .fraud-card {
        background: rgba(239, 68, 68, 0.05);
        border: 1px solid rgba(239, 68, 68, 0.3);
        border-radius: 30px;
        padding: 1.2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        backdrop-filter: blur(10px);
        transition: 0.3s;
      }

      .fraud-card:hover {
        border-color: var(--danger);
        box-shadow: 0 0 30px rgba(239, 68, 68, 0.3);
        transform: translateY(-2px);
      }

      .fraud-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(239, 68, 68, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--danger);
        font-size: 1.4rem;
        flex-shrink: 0;
      }

      .fraud-content h4 {
        font-size: 1rem;
        margin-bottom: 0.2rem;
      }

      .fraud-content p {
        font-size: 0.85rem;
        color: var(--text-secondary);
      }

      .fraud-content small {
        font-size: 0.7rem;
        color: var(--text-muted);
      }

      /* Bulk Actions Bar - exactly like admin-users */
      .bulk-bar {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 0.8rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        margin: 1rem 0;
      }

      /* Table - exactly like admin-users */
      .table-wrapper {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.2rem;
        overflow-x: auto;
        margin: 1.5rem 0;
        max-height: 600px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: var(--primary) var(--surface);
      }

      .table-wrapper::-webkit-scrollbar {
        width: 6px;
        height: 6px;
      }

      .table-wrapper::-webkit-scrollbar-track {
        background: var(--surface);
        border-radius: 10px;
      }

      .table-wrapper::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 10px;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1600px;
      }

      th {
        position: sticky;
        top: 0;
        background: var(--surface-light);
        z-index: 10;
        padding: 1.2rem 0.8rem;
        color: var(--primary);
        border-bottom: 2px solid var(--glass-edge);
        font-weight: 600;
        backdrop-filter: blur(10px);
      }

      td {
        padding: 1rem 0.8rem;
        border-bottom: 1px solid var(--glass-edge);
      }

      tr {
        transition: 0.2s;
      }

      tr:hover {
        background: var(--primary-soft);
      }

      .checkbox-col {
        width: 40px;
        text-align: center;
      }

      input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
        cursor: pointer;
      }

      /* Transaction Type Badges */
      .type-badge {
        display: flex;
        align-items: center;
        gap: 6px;
      }

      .type-icon {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .type-deposit {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .type-withdrawal {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .type-trade {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }

      /* Status Badges - exactly like admin-users */
      .status-badge {
        padding: 0.25rem 0.8rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
      }

      .status-pending {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .status-completed {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .status-failed {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .status-flagged {
        background: rgba(249, 115, 22, 0.2);
        color: var(--orange);
      }

      /* Wallet Address */
      .wallet-address {
        font-family: monospace;
        font-size: 0.8rem;
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        background: var(--glass-bg);
        padding: 0.2rem 0.5rem;
        border-radius: 40px;
        display: inline-block;
      }

      /* Actions Dropdown - exactly like admin-users */
      .actions-dropdown {
        position: relative;
        display: inline-block;
      }

      .actions-btn {
        background: transparent;
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 0.4rem 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 4px;
      }

      .actions-btn:hover {
        background: var(--primary);
        color: white;
      }

      .dropdown-menu {
        position: absolute;
        right: 0;
        top: 110%;
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 0.5rem 0;
        min-width: 180px;
        z-index: 50;
        display: none;
        box-shadow: var(--shadow-glow);
      }

      .dropdown-menu.show {
        display: block;
      }

      .dropdown-item {
        padding: 0.6rem 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: 0.2s;
        cursor: pointer;
      }

      .dropdown-item:hover {
        background: var(--primary-soft);
        color: var(--primary);
      }

      /* Pagination - exactly like admin-users */
      .pagination {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 1rem;
        flex-wrap: wrap;
      }

      .page-btn {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 0.5rem 1rem;
        cursor: pointer;
      }

      .page-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
      }

      /* Feed Panels - properly styled */
      .feed-panel {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .feed-panel h3 {
        margin-bottom: 1.2rem;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .feed-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.8rem 0;
        border-bottom: 1px solid var(--glass-edge);
        animation: slideIn 0.3s;
      }

      .feed-item:last-child {
        border-bottom: none;
      }

      @keyframes slideIn {
        from {
          opacity: 0;
          transform: translateX(-10px);
        }
        to {
          opacity: 1;
          transform: translateX(0);
        }
      }

      .log-item {
        font-size: 0.9rem;
        padding: 0.6rem 0;
        border-bottom: 1px dashed var(--glass-edge);
        display: flex;
        gap: 1rem;
      }

      .log-item:last-child {
        border-bottom: none;
      }

      /* Two column layout */
      .two-column {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-top: 1.5rem;
      }

      /* Modal - exactly like admin-users */
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(8px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
      }

      .modal-content {
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 50px;
        padding: 2rem;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: var(--shadow-glow);
      }

      .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
      }

      .modal-close {
        cursor: pointer;
        font-size: 1.5rem;
        color: var(--text-muted);
      }

      .modal-close:hover {
        color: var(--primary);
      }

      .confirmation-modal {
        max-width: 400px;
        text-align: center;
      }

      /* Blockchain info */
      .blockchain-info {
        background: var(--glass-bg);
        border-radius: 30px;
        padding: 1.2rem;
        margin-top: 1.5rem;
      }

      .blockchain-info h4 {
        margin-bottom: 1rem;
        color: var(--primary);
      }

      .confirmations-bar {
        background: var(--surface-light);
        height: 8px;
        border-radius: 40px;
        margin: 0.5rem 0;
        overflow: hidden;
      }

      .confirmations-progress {
        height: 100%;
        background: var(--primary);
        border-radius: 40px;
      }

      /* Responsive - exactly like admin-users */
      @media (max-width: 1100px) {
        .mobile-menu-btn {
          display: flex;
        }
        .control-bar {
          border-radius: 30px;
        }
      }

      @media (max-width: 768px) {
        .content {
          padding: 1rem;
        }
        .control-bar {
          flex-direction: column;
          align-items: stretch;
        }
        .bulk-bar {
          flex-direction: column;
          align-items: flex-start;
        }
        .analytics-value {
          font-size: 1.5rem;
        }
        .two-column {
          grid-template-columns: 1fr;
        }
        .fraud-grid {
          grid-template-columns: 1fr;
        }
      }

      @media (max-width: 480px) {
        .top-bar {
          padding: 0.6rem 1rem;
        }
        .search-box input {
          width: 140px;
        }
        .header-right .system-status span:last-child {
          display: none;
        }
      }

      /* AI Assistant */
      .ai-assistant {
        position: fixed;
        bottom: 1.5rem;
        right: 1.5rem;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        box-shadow: var(--shadow-glow);
        z-index: 99;
        transition: 0.3s;
      }

      .ai-assistant:hover {
        transform: scale(1.1);
      }

      .fade-in {
        animation: fadeIn 0.3s;
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
          transform: translateY(10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
    </style>
  </head>
  <body class="dark">
    <div class="admin">
      <!-- SIDEBAR (exact copy, active on Transactions) -->
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
          <a href="./admin-transactions.php" class="sidebar-link active"
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
        <!-- TOP BAR (identical) -->
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

        <!-- TRANSACTION MANAGEMENT CONTENT -->
        <div class="content">
          <!-- Page Header -->
          <div class="page-header" data-aos="fade-up">
            <h1>Transaction Management</h1>
            <p>Monitor and control all platform transactions in real time.</p>
            <div class="breadcrumb">
              <a href="#">Admin</a> <span>/</span>
              <span style="color: var(--primary)">Transactions</span>
            </div>
          </div>

          <!-- Analytics Cards -->
          <div class="analytics-grid" data-aos="fade-up">
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-arrow-right-arrow-left"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 12.3%</span
                >
              </div>
              <div class="analytics-value" id="statTotalTx"><?php echo number_format((int)($txStats['total_count'] ?? 0)); ?></div>
              <div class="analytics-label">Total Transactions</div>
              <div class="mini-chart">
                <canvas id="chartTotal" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-arrow-down"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 5.4%</span
                >
              </div>
              <div class="analytics-value" id="statDeposits"><?php echo '$' . number_format((float)($txStats['deposits_usd'] ?? 0), 0); ?></div>
              <div class="analytics-label">Total Deposits</div>
              <div class="mini-chart">
                <canvas id="chartDeposits" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-arrow-up"></i
                ></span>
                <span class="trend-down"
                  ><i class="fa-solid fa-arrow-down"></i> 2.1%</span
                >
              </div>
              <div class="analytics-value" id="statWithdrawals"><?php echo '$' . number_format((float)($txStats['withdrawals_usd'] ?? 0), 0); ?></div>
              <div class="analytics-label">Total Withdrawals</div>
              <div class="mini-chart">
                <canvas id="chartWithdrawals" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-clock"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 8</span
                >
              </div>
              <div class="analytics-value" id="statPending"><?php echo number_format((int)($txStats['pending_count'] ?? 0)); ?></div>
              <div class="analytics-label">Pending</div>
              <div class="mini-chart">
                <canvas id="chartPending" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-exclamation-circle"></i
                ></span>
                <span class="trend-down"
                  ><i class="fa-solid fa-arrow-down"></i> 3</span
                >
              </div>
              <div class="analytics-value" id="statFailed"><?php echo number_format((int)($txStats['failed_count'] ?? 0)); ?></div>
              <div class="analytics-label">Failed</div>
              <div class="mini-chart">
                <canvas id="chartFailed" width="100" height="30"></canvas>
              </div>
            </div>
          </div>

          <!-- Fraud / risk (placeholder — use alerts from table + notification bell) -->
          <div class="fraud-grid" data-aos="fade-up">
            <div class="fraud-card">
              <div class="fraud-icon">
                <i class="fa-solid fa-triangle-exclamation"></i>
              </div>
              <div class="fraud-content">
                <h4>Review pending items</h4>
                <p>Filter by <strong>Pending</strong> or use the notification bell for open deposits and withdrawals.</p>
                <small>Flagged transactions are marked in the Status column.</small>
              </div>
            </div>
          </div>

          <!-- Top Controls -->
          <div class="control-bar" data-aos="fade-up">
            <div class="control-search">
              <i class="fa-solid fa-search"></i>
              <input type="text" id="txSearchInput" placeholder="Search by ID, user, wallet..." />
            </div>
            <div class="control-filters">
              <select class="filter-select" id="txTypeFilter">
                <option value="">All Transactions</option>
                <option value="deposit">Deposits</option>
                <option value="withdrawal">Withdrawals</option>
                <option value="trade">Trades</option>
              </select>
              <select class="filter-select" id="txStatusFilter">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="completed">Completed</option>
                <option value="failed">Failed</option>
                <option value="flagged">Flagged</option>
              </select>
              <select class="filter-select" id="txSortSelect">
                <option value="newest">Newest</option>
                <option value="oldest">Oldest</option>
                <option value="highest_amount">Highest Amount</option>
              </select>
              <button class="btn-outline">
                <i class="fa-regular fa-file-pdf"></i> PDF
              </button>
              <button class="btn-outline">
                <i class="fa-regular fa-file-excel"></i> CSV
              </button>
            </div>
          </div>

          <!-- Bulk Actions Bar -->
          <div class="bulk-bar" id="bulkBar" style="display: none">
            <span><span id="selectedCount">0</span> transactions selected</span>
            <div style="display: flex; gap: 10px; flex-wrap: wrap">
              <button class="btn-primary" style="background: var(--success)">
                Approve
              </button>
              <button class="btn-primary" style="background: var(--danger)">
                Reject
              </button>
              <button class="btn-primary" style="background: var(--orange)">
                Flag
              </button>
            </div>
          </div>

          <!-- Main Transaction Table -->
          <div class="table-wrapper" data-aos="fade-up">
            <table>
              <thead>
                <tr>
                  <th class="checkbox-col">
                    <input type="checkbox" id="selectAll" />
                  </th>
                  <th>Transaction ID</th>
                  <th>From (sender)</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Currency</th>
                  <th>Network</th>
                  <th>To (receiver)</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="transactionTableBody">
                <!-- JS will populate -->
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="pagination" id="txPagination"></div>

          <!-- Live Transaction Feed & Activity Logs -->
          <div class="two-column">
            <!-- Live Feed -->
            <div class="feed-panel" data-aos="fade-up">
              <h3>
                <i class="fa-solid fa-rss" style="color: var(--primary)"></i>
                Live Transaction Feed
              </h3>
              <div id="liveFeed">
                <div class="feed-item" style="color:var(--text-muted);">Loading…</div>
              </div>
            </div>

            <!-- Admin Activity Logs -->
            <div class="feed-panel" data-aos="fade-up">
              <h3>
                <i
                  class="fa-solid fa-history"
                  style="color: var(--primary)"
                ></i>
                Admin Actions
              </h3>
              <div id="activityLogs">
                <?php if (!$adminActivityLogs): ?>
                <div class="log-item" style="color:var(--text-muted);">No recent admin activity (contact module).</div>
                <?php else: foreach ($adminActivityLogs as $log): ?>
                <div class="log-item" title="<?php echo admin_tx_h((string)($log['action'] ?? '')); ?>">
                  <span style="color: var(--primary);">•</span>
                  <?php echo admin_tx_h((string)($log['action'] ?? '')); ?>
                  <?php if (!empty($log['time'])): ?><span style="color:var(--text-muted);"> · <?php echo admin_tx_h((string)$log['time']); ?></span><?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Transaction Detail Modal -->
    <div class="modal-overlay" id="transactionModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Transaction Details</h2>
          <span class="modal-close" id="closeModal">&times;</span>
        </div>
        <div id="modalBody"></div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmModal">
      <div class="modal-content confirmation-modal">
        <div class="modal-header">
          <h3>Confirm Action</h3>
          <span class="modal-close" id="closeConfirm">&times;</span>
        </div>
        <p id="confirmMessage">
          Are you sure you want to approve this transaction?
        </p>
        <div
          style="
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
          "
        >
          <button class="btn-primary" id="confirmYes">Yes, Confirm</button>
          <button class="btn-outline" id="confirmNo">Cancel</button>
        </div>
      </div>
    </div>

    <!-- AI Assistant -->
    <div class="ai-assistant" id="aiBtn">
      <i class="fa-solid fa-robot"></i>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      const API_BASE = "./api/transactions_api.php";
      const CSRF_TOKEN = <?php echo json_encode(admin_csrf_token(), JSON_UNESCAPED_SLASHES); ?>;
      const TX_SPARK = <?php echo json_encode($txStats['sparklines'] ?? [], JSON_UNESCAPED_UNICODE); ?>;

      AOS.init({ duration: 700, once: true });

      // Sidebar collapse
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      collapseBtn.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");
        const icon = collapseBtn.querySelector("i");
        icon.classList.toggle("fa-chevron-left");
        icon.classList.toggle("fa-chevron-right");
      });

      // Mobile menu
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

      // Theme toggle
      const themeToggle = document.getElementById("themeToggle");
      themeToggle.addEventListener("click", () => {
        document.body.classList.toggle("dark");
      });

      function escapeHtml(s) {
        return String(s ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;");
      }

      function typeUi(t) {
        const x = String(t || "").toLowerCase();
        if (x === "deposit") return { className: "type-deposit", icon: "fa-arrow-down", label: "Deposit" };
        if (x === "withdrawal") return { className: "type-withdrawal", icon: "fa-arrow-up", label: "Withdrawal" };
        return { className: "type-trade", icon: "fa-repeat", label: "Trade" };
      }

      function statusBadgeClass(display) {
        const d = String(display || "").toLowerCase();
        if (d === "completed") return "status-completed";
        if (d === "pending") return "status-pending";
        if (d === "failed" || d === "cancelled") return "status-failed";
        if (d === "flagged") return "status-flagged";
        return "status-pending";
      }

      function fmtWallet(w) {
        const s = String(w || "");
        if (s.length <= 16) return s || "—";
        return s.slice(0, 8) + "..." + s.slice(-6);
      }

      let state = { rows: [], page: 1, perPage: 25, total: 0, q: "", type: "", status: "", sort: "newest" };
      const tbody = document.getElementById("transactionTableBody");
      const paginationEl = document.getElementById("txPagination");

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
        paginationEl.innerHTML = "";
        function addBtn(html, page, active, disabled, aria) {
          const btn = document.createElement("button");
          btn.className = "page-btn" + (active ? " active" : "");
          btn.innerHTML = html;
          if (aria) btn.setAttribute("aria-label", aria);
          if (disabled) {
            btn.disabled = true;
            btn.style.opacity = "0.5";
          } else {
            btn.addEventListener("click", () => {
              state.page = page;
              fetchTransactions();
            });
          }
          paginationEl.appendChild(btn);
        }
        function ell() {
          const span = document.createElement("span");
          span.textContent = "…";
          span.style.padding = "0.5rem 0.6rem";
          span.style.color = "var(--text-muted)";
          paginationEl.appendChild(span);
        }
        addBtn('<i class="fa-solid fa-chevron-left"></i>', current - 1, false, current <= 1, "Previous page");
        const win = 2;
        const start = Math.max(1, current - win);
        const end = Math.min(pages, current + win);
        if (start > 1) {
          addBtn("1", 1, current === 1, false, "Page 1");
          if (start > 2) ell();
        }
        for (let p = start; p <= end; p++) {
          addBtn(String(p), p, p === current, false, "Page " + p);
        }
        if (end < pages) {
          if (end < pages - 1) ell();
          addBtn(String(pages), pages, current === pages, false, "Page " + pages);
        }
        addBtn('<i class="fa-solid fa-chevron-right"></i>', current + 1, false, current >= pages, "Next page");
      }

      function txSender(tx) {
        return String(tx.sender_label || tx.user_label || "—");
      }
      function txReceiver(tx) {
        return String(tx.receiver_label || tx.wallet || "—");
      }

      function renderTable() {
        tbody.innerHTML = "";
        state.rows.forEach((tx) => {
          const tu = typeUi(tx.type);
          const stClass = statusBadgeClass(tx.display_status);
          const amt = Number(tx.amount || 0);
          const amtStr =
            tu.label === "Trade"
              ? "$" + amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
              : "$" + amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
          const recv = txReceiver(tx);
          const row = document.createElement("tr");
          row.innerHTML = `
          <td class="checkbox-col"><input type="checkbox" class="tx-checkbox" data-id="${tx.id}"></td>
          <td><strong>#${tx.id}</strong></td>
          <td>${escapeHtml(txSender(tx))}</td>
          <td>
            <span class="type-badge">
              <span class="type-icon ${tu.className}"><i class="fa-solid ${tu.icon}"></i></span>
              ${tu.label}
            </span>
          </td>
          <td>${amtStr}</td>
          <td>${escapeHtml(tx.asset || "—")}</td>
          <td>${escapeHtml(tx.network || "—")}</td>
          <td><span class="wallet-address" title="${escapeHtml(recv)}">${escapeHtml(fmtWallet(recv))}</span></td>
          <td><span class="status-badge ${stClass}">${escapeHtml(tx.display_status)}</span></td>
          <td>${escapeHtml(String(tx.created_at || "").slice(0, 19))}</td>
          <td>
            <div class="actions-dropdown">
              <button class="actions-btn" onclick="toggleDropdown(this)"><i class="fa-solid fa-ellipsis-vertical"></i> Actions</button>
              <div class="dropdown-menu">
                <a class="dropdown-item" onclick="viewTransaction(${tx.id})"><i class="fa-regular fa-eye"></i> View Details</a>
                <a class="dropdown-item" onclick="showConfirm('approve', ${tx.id})"><i class="fa-regular fa-circle-check"></i> Approve</a>
                <a class="dropdown-item" onclick="showConfirm('reject', ${tx.id})"><i class="fa-regular fa-circle-xmark"></i> Reject</a>
                <a class="dropdown-item" onclick="showConfirm('flag', ${tx.id})"><i class="fa-solid fa-flag"></i> Flag as Suspicious</a>
                <a class="dropdown-item" style="color: var(--orange);" onclick="showConfirm('reverse', ${tx.id})"><i class="fa-solid fa-rotate-left"></i> Reverse</a>
              </div>
            </div>
          </td>
        `;
          tbody.appendChild(row);
        });
      }

      // Mini charts (last 7 days from DB)
      function initCharts() {
        const s = TX_SPARK || {};
        const ds = (k) => (Array.isArray(s[k]) ? s[k] : []).map((n) => Number(n) || 0);
        const mk = (id, data, color) => {
          const el = document.getElementById(id);
          if (!el || typeof Chart === "undefined") return;
          new Chart(el, {
            type: "line",
            data: {
              labels: [],
              datasets: [
                {
                  data: data.length ? data : [0, 0, 0, 0, 0, 0, 0],
                  borderColor: color,
                  borderWidth: 2,
                  pointRadius: 0,
                  tension: 0.4,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: { x: { display: false }, y: { display: false } },
              plugins: { legend: { display: false } },
            },
          });
        };
        mk("chartTotal", ds("total"), "#22c55e");
        mk("chartDeposits", ds("deposits"), "#22c55e");
        mk("chartWithdrawals", ds("withdrawals"), "#ef4444");
        mk("chartPending", ds("pending"), "#f59e0b");
        mk("chartFailed", ds("failed"), "#ef4444");
      }

      async function refreshStats() {
        try {
          const r = await fetch(API_BASE + "?action=stats", { credentials: "same-origin" });
          const data = await r.json();
          if (!data || !data.ok || !data.stats) return;
          const st = data.stats;
          const ge = (id) => document.getElementById(id);
          const n0 = (v) => Number(v || 0);
          if (ge("statTotalTx")) ge("statTotalTx").textContent = String(st.total_count ?? 0);
          if (ge("statDeposits")) ge("statDeposits").textContent = "$" + n0(st.deposits_usd).toLocaleString(undefined, { maximumFractionDigits: 0 });
          if (ge("statWithdrawals")) ge("statWithdrawals").textContent = "$" + n0(st.withdrawals_usd).toLocaleString(undefined, { maximumFractionDigits: 0 });
          if (ge("statPending")) ge("statPending").textContent = String(st.pending_count ?? 0);
          if (ge("statFailed")) ge("statFailed").textContent = String(st.failed_count ?? 0);
        } catch (e) {}
      }

      async function refreshLiveFeed() {
        const feed = document.getElementById("liveFeed");
        if (!feed) return;
        try {
          const r = await fetch(API_BASE + "?action=feed&limit=8", { credentials: "same-origin" });
          const data = await r.json();
          if (!data || !data.ok || !Array.isArray(data.feed)) return;
          if (!data.feed.length) {
            feed.innerHTML = '<div class="feed-item" style="color:var(--text-muted);">No recent transactions.</div>';
            return;
          }
          feed.innerHTML = "";
          data.feed.forEach((item) => {
            const tu = typeUi(item.type);
            const div = document.createElement("div");
            div.className = "feed-item";
            div.innerHTML = `
              <span class="type-icon ${tu.className}"><i class="fa-solid ${tu.icon}"></i></span>
              <span><strong>${escapeHtml(item.label)}</strong></span>
              <span style="color: var(--text-muted); margin-left: auto;">${escapeHtml(String(item.created_at || "").slice(0, 16))}</span>
            `;
            feed.appendChild(div);
          });
        } catch (e) {}
      }

      async function fetchTransactions() {
        const url = new URL(API_BASE, window.location.href);
        url.searchParams.set("action", "list");
        url.searchParams.set("page", String(state.page));
        url.searchParams.set("per_page", String(state.perPage));
        if (state.q) url.searchParams.set("q", state.q);
        if (state.type) url.searchParams.set("type", state.type);
        if (state.status) url.searchParams.set("status", state.status);
        if (state.sort) url.searchParams.set("sort", state.sort);
        tbody.innerHTML =
          '<tr><td colspan="11" style="padding:1rem;color:var(--text-muted);">Loading…</td></tr>';
        try {
          const r = await fetch(url.toString(), { credentials: "same-origin" });
          const data = await r.json();
          if (!data || !data.ok) throw new Error("bad");
          state.rows = Array.isArray(data.rows) ? data.rows : [];
          state.total = Number(data.total || 0);
          state.page = Number(data.page || state.page || 1);
          state.perPage = Number(data.per_page || state.perPage || 25);
          renderTable();
          renderPagination();
          refreshStats();
          refreshLiveFeed();
        } catch (e) {
          tbody.innerHTML =
            '<tr><td colspan="11" style="padding:1rem;color:var(--danger);">Failed to load transactions.</td></tr>';
          if (paginationEl) paginationEl.innerHTML = "";
        }
      }

      // Dropdown toggle
      window.toggleDropdown = function (btn) {
        const allMenus = document.querySelectorAll(".dropdown-menu");
        allMenus.forEach((m) => m.classList.remove("show"));
        const menu = btn.nextElementSibling;
        menu.classList.toggle("show");
      };

      document.addEventListener("click", (e) => {
        if (!e.target.closest(".actions-dropdown")) {
          document
            .querySelectorAll(".dropdown-menu")
            .forEach((m) => m.classList.remove("show"));
        }
      });

      // Select all
      const selectAll = document.getElementById("selectAll");
      selectAll.addEventListener("change", (e) => {
        document
          .querySelectorAll(".tx-checkbox")
          .forEach((cb) => (cb.checked = e.target.checked));
        updateBulkBar();
      });

      function updateBulkBar() {
        const checked = document.querySelectorAll(
          ".tx-checkbox:checked",
        ).length;
        const bulkBar = document.getElementById("bulkBar");
        if (checked > 0) {
          bulkBar.style.display = "flex";
          document.getElementById("selectedCount").innerText = checked;
        } else {
          bulkBar.style.display = "none";
        }
      }

      document.addEventListener("change", (e) => {
        if (e.target.classList.contains("tx-checkbox")) {
          updateBulkBar();
        }
      });

      // View transaction modal
      window.viewTransaction = async function (id) {
        const modalBody = document.getElementById("modalBody");
        modalBody.innerHTML = '<p style="color:var(--text-muted);">Loading…</p>';
        document.getElementById("transactionModal").style.display = "flex";
        let tx = null;
        try {
          const r = await fetch(API_BASE + "?action=detail&id=" + encodeURIComponent(id), { credentials: "same-origin" });
          const data = await r.json();
          if (data && data.ok) tx = data.transaction;
        } catch (e) {}
        if (!tx) {
          modalBody.innerHTML = '<p style="color:var(--danger);">Could not load transaction.</p>';
          return;
        }
        const stClass = statusBadgeClass(tx.display_status);
        const confirmations = String(tx.status || "").toLowerCase() === "completed" ? 12 : 3;
        const confirmProgress = (confirmations / 12) * 100;
        const amt = Number(tx.amount || 0);
        modalBody.innerHTML = `
        <div style="margin-bottom: 1.5rem;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">Transaction ID</div>
              <div style="font-weight: 600;">#${escapeHtml(tx.id)}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">Amount</div>
              <div style="font-weight: 600;">$${amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${escapeHtml(tx.asset || "")}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">From (sender)</div>
              <div style="font-weight: 600;">${escapeHtml(txSender(tx))}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">To (receiver)</div>
              <div style="font-weight: 600;">${escapeHtml(txReceiver(tx))}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">Network</div>
              <div>${escapeHtml(tx.network || "—")}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">Status</div>
              <div><span class="status-badge ${stClass}">${escapeHtml(tx.display_status)}</span></div>
            </div>
            <div style="grid-column: span 2;">
              <div style="color: var(--text-muted); font-size: 0.85rem;">Wallet / reference</div>
              <div style="font-family: monospace; word-break: break-all;">${escapeHtml(tx.wallet || "—")}</div>
            </div>
            <div style="grid-column: span 2;">
              <div style="color: var(--text-muted); font-size: 0.85rem;">Timestamp</div>
              <div>${escapeHtml(String(tx.created_at || "").slice(0, 19))}</div>
            </div>
          </div>
          <div class="blockchain-info">
            <h4><i class="fa-solid fa-link"></i> Record</h4>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
              <span>Internal reference</span>
              <span><strong>${confirmations}/12</strong> (illustrative)</span>
            </div>
            <div class="confirmations-bar">
              <div class="confirmations-progress" style="width: ${confirmProgress}%;"></div>
            </div>
          </div>
        </div>
      `;
      };

      // Confirmation modal
      let currentAction = null;
      let currentTxId = null;

      window.showConfirm = function (action, txId) {
        currentAction = action;
        currentTxId = txId;
        const messages = {
          approve: "approve this transaction",
          reject: "reject this transaction",
          flag: "flag this transaction as suspicious",
          reverse: "reverse this transaction",
        };
        document.getElementById("confirmMessage").innerText =
          `Are you sure you want to ${messages[action]}?`;
        document.getElementById("confirmModal").style.display = "flex";
      };

      document.getElementById("confirmYes").addEventListener("click", async () => {
        if (currentAction && currentTxId) {
          try {
            const r = await fetch(API_BASE, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "same-origin",
              body: JSON.stringify({
                action: currentAction,
                id: currentTxId,
                csrf_token: CSRF_TOKEN,
              }),
            });
            const data = await r.json();
            if (data && data.ok) {
              fetchTransactions();
            } else {
              alert("Action could not be completed.");
            }
          } catch (e) {
            alert("Request failed.");
          }
        }
        document.getElementById("confirmModal").style.display = "none";
        currentAction = null;
        currentTxId = null;
      });

      document.getElementById("confirmNo").addEventListener("click", () => {
        document.getElementById("confirmModal").style.display = "none";
      });

      document.getElementById("closeConfirm").addEventListener("click", () => {
        document.getElementById("confirmModal").style.display = "none";
      });

      // Close modals
      document.getElementById("closeModal").addEventListener("click", () => {
        document.getElementById("transactionModal").style.display = "none";
      });

      window.addEventListener("click", (e) => {
        if (e.target.classList.contains("modal-overlay")) {
          e.target.style.display = "none";
        }
      });

      // AI assistant
      document.getElementById("aiBtn").addEventListener("click", () => {
        alert(
          "🤖 AI Assistant:\n\n• 12 pending transactions\n• 2 flagged for review\n• Large withdrawal: $450,000 USDT needs approval\n• 3 failed transactions in last hour",
        );
      });

      const searchInput = document.getElementById("txSearchInput");
      const typeFilter = document.getElementById("txTypeFilter");
      const statusFilter = document.getElementById("txStatusFilter");
      const sortSelect = document.getElementById("txSortSelect");
      let searchTimer = null;
      if (searchInput) {
        searchInput.addEventListener("input", () => {
          clearTimeout(searchTimer);
          searchTimer = setTimeout(() => {
            state.q = searchInput.value.trim();
            state.page = 1;
            fetchTransactions();
          }, 300);
        });
      }
      if (typeFilter) {
        typeFilter.addEventListener("change", () => {
          state.type = typeFilter.value;
          state.page = 1;
          fetchTransactions();
        });
      }
      if (statusFilter) {
        statusFilter.addEventListener("change", () => {
          state.status = statusFilter.value;
          state.page = 1;
          fetchTransactions();
        });
      }
      if (sortSelect) {
        sortSelect.addEventListener("change", () => {
          state.sort = sortSelect.value;
          state.page = 1;
          fetchTransactions();
        });
      }

      initCharts();
      fetchTransactions();

      // Resize handler
      window.addEventListener("resize", () => {
        if (window.innerWidth > 1100) {
          sidebar.classList.remove("mobile-open");
        }
      });
    </script>
  <?php require_once __DIR__ . '/includes/admin_notify_client.php'; ?>
  </body>
</html>
