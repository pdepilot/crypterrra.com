<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
require_once __DIR__ . '/includes/admin_transactions_data.php';

$withdrawStats = [
  'total_completed_usd' => 0.0,
  'pending_count' => 0,
  'completed_count' => 0,
  'rejected_count' => 0,
  'flagged_count' => 0,
];
$withdrawAlerts = [
  'largest_pending' => null,
  'flagged_pending_count' => 0,
  'rapid_user' => null,
  'new_address' => null,
];
try {
  $db = admin_tx_db();
  ensure_transactions_table($db);
  global $DB_NAME;
  admin_tx_ensure_counterparty_column($db, (string)$DB_NAME);
  $withdrawStats = admin_withdrawal_stats($db);
  $withdrawAlerts = admin_withdrawal_alerts($db);
  $db->close();
} catch (Throwable) {
  // keep defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Crypterra · Admin · Withdrawal Management</title>
  <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
  <!-- Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- AOS -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    /* === EXACT STYLES FROM ADMIN DASHBOARD === */
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
      font-family: 'Inter', sans-serif;
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

    .content {
      padding: 1.5rem;
    }

    /* Page Header */
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

    /* Analytics Cards */
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

    /* Control Bar */
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

    .btn-danger {
      background: var(--danger);
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

    .btn-danger:hover {
      background: #dc2626;
      transform: scale(1.02);
      box-shadow: 0 0 30px rgba(239, 68, 68, 0.5);
    }

    .btn-warning {
      background: var(--orange);
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

    /* Security Panel */
    .security-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1rem;
      margin: 1.5rem 0;
    }

    .security-card {
      background: var(--card-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 30px;
      padding: 1.2rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      transition: 0.3s;
    }

    .security-card.critical {
      border-color: var(--danger);
      background: rgba(239, 68, 68, 0.05);
    }

    .security-card.warning {
      border-color: var(--orange);
      background: rgba(249, 115, 22, 0.05);
    }

    .security-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-glow);
    }

    .security-icon {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.4rem;
      flex-shrink: 0;
    }

    .security-icon.critical {
      background: rgba(239, 68, 68, 0.2);
      color: var(--danger);
    }

    .security-icon.warning {
      background: rgba(249, 115, 22, 0.2);
      color: var(--orange);
    }

    .security-icon.success {
      background: rgba(16, 185, 129, 0.2);
      color: var(--success);
    }

    .security-content {
      flex: 1;
    }

    .security-content h4 {
      font-size: 1rem;
      margin-bottom: 0.2rem;
    }

    .security-content p {
      font-size: 0.85rem;
      color: var(--text-secondary);
    }

    .security-content small {
      font-size: 0.7rem;
      color: var(--text-muted);
    }

    .whitelist-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 0.2rem 0.6rem;
      border-radius: 40px;
      font-size: 0.7rem;
      font-weight: 600;
    }

    .whitelist-badge.verified {
      background: rgba(16, 185, 129, 0.2);
      color: var(--success);
    }

    .whitelist-badge.unverified {
      background: rgba(239, 68, 68, 0.2);
      color: var(--danger);
    }

    /* Processing Overlay */
    .processing-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.8);
      backdrop-filter: blur(8px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 2000;
    }

    .processing-card {
      background: var(--surface);
      border: 1px solid var(--glass-edge);
      border-radius: 50px;
      padding: 2.5rem;
      text-align: center;
      max-width: 500px;
      width: 90%;
      box-shadow: var(--shadow-glow);
    }

    .processing-spinner {
      width: 60px;
      height: 60px;
      border: 3px solid var(--primary-soft);
      border-top-color: var(--primary);
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 1.5rem;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .processing-step {
      margin: 1rem 0;
      padding: 0.8rem;
      border-radius: 40px;
      background: var(--glass-bg);
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .step-icon {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: var(--primary-soft);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
    }

    .step-icon.completed {
      background: var(--success);
      color: white;
    }

    .step-icon.failed {
      background: var(--danger);
      color: white;
    }

    /* Fee indicators */
    .fee-badge {
      padding: 0.2rem 0.6rem;
      border-radius: 40px;
      font-size: 0.7rem;
      font-weight: 600;
    }

    .fee-high {
      background: rgba(239, 68, 68, 0.2);
      color: var(--danger);
    }

    .fee-medium {
      background: rgba(245, 158, 11, 0.2);
      color: var(--warning);
    }

    .fee-low {
      background: rgba(16, 185, 129, 0.2);
      color: var(--success);
    }

    /* Bulk Actions Bar */
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

    /* Table */
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

    /* Status Badges */
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

    .status-approved {
      background: rgba(16, 185, 129, 0.2);
      color: var(--success);
    }

    .status-rejected {
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

    /* Actions Dropdown */
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
      min-width: 200px;
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

    .dropdown-item.danger:hover {
      background: rgba(239, 68, 68, 0.2);
      color: var(--danger);
    }

    /* Pagination */
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

    /* Feed Panel */
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

    .feed-icon {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--primary-soft);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
    }

    .feed-icon.warning {
      background: rgba(245, 158, 11, 0.2);
      color: var(--warning);
    }

    .feed-icon.danger {
      background: rgba(239, 68, 68, 0.2);
      color: var(--danger);
    }

    .feed-icon.success {
      background: rgba(16, 185, 129, 0.2);
      color: var(--success);
    }

    /* Activity Logs */
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

    /* Modal */
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
      max-width: 450px;
      text-align: center;
    }

    .rejection-reason {
      background: var(--glass-bg);
      border-radius: 30px;
      padding: 1rem;
      margin: 1rem 0;
    }

    .rejection-reason textarea {
      width: 100%;
      background: transparent;
      border: 1px solid var(--glass-edge);
      border-radius: 30px;
      padding: 0.8rem;
      color: var(--text);
      margin-top: 0.5rem;
      resize: vertical;
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

    .tx-hash {
      font-family: monospace;
      font-size: 0.8rem;
      word-break: break-all;
      background: var(--surface-light);
      padding: 0.5rem;
      border-radius: 20px;
    }

    /* Responsive */
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
      .security-grid {
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
  </style>
</head>
<body class="dark">
  <div class="admin">
    <!-- SIDEBAR (active on Withdrawals) -->
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
        <a href="./admin-users.php" class="sidebar-link"><i class="fa-solid fa-users"></i> <span>Users</span></a>
        <a href="./admin-send.php" class="sidebar-link"><i class="fa-solid fa-paper-plane"></i> <span>Send Mgmt</span></a>
        <a href="./admin-receive.php" class="sidebar-link"><i class="fa-solid fa-arrow-down"></i> <span>Receive Mgmt</span></a>
        <a href="./admin-transactions.php" class="sidebar-link"><i class="fa-solid fa-arrow-right-arrow-left"></i> <span>Transactions</span></a>
        <a href="./admin-contact.php" class="sidebar-link"><i class="fa-solid fa-envelope"></i> <span>Contact Mgmt</span></a>
        <a href="./admin-help-center.php" class="sidebar-link"><i class="fa-solid fa-life-ring"></i> <span>Help Center</span></a>
                 <a href="./admin-deposit.php" class="sidebar-link"><i class="fa-solid fa-arrow-down"></i> <span>Deposits</span></a>
        <a href="./admin-withdrawal.php" class="sidebar-link active"><i class="fa-solid fa-arrow-up"></i> <span>Withdrawals</span></a>
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

      <!-- WITHDRAWAL MANAGEMENT CONTENT -->
      <div class="content">
        <!-- Page Header -->
        <div class="page-header" data-aos="fade-up">
          <h1>Withdrawal Management</h1>
          <p>Control and process all user withdrawal requests securely.</p>
          <div class="breadcrumb">
            <a href="#">Admin</a> <span>/</span> <span style="color: var(--primary);">Withdrawals</span>
          </div>
        </div>

        <!-- Security & Fraud Detection Panel -->
        <div class="security-grid" data-aos="fade-up">
          <div class="security-card critical">
            <div class="security-icon critical"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="security-content">
              <h4>Large Withdrawal Detected</h4>
<?php if (!empty($withdrawAlerts['largest_pending'])): ?>
<?php $lp = $withdrawAlerts['largest_pending']; ?>
              <p><?php echo '$' . number_format((float)($lp['amount_usd'] ?? 0), 0) . ' ' . admin_tx_h((string)($lp['asset'] ?? '')); ?> - Requires manual approval</p>
              <small>User: <?php echo admin_tx_h((string)($lp['user_label'] ?? '—')); ?> | <?php echo (int)($lp['minutes_ago'] ?? 0); ?> min ago</small>
<?php else: ?>
              <p>No large pending withdrawals detected</p>
              <small>—</small>
<?php endif; ?>
            </div>
          </div>
          <div class="security-card warning">
            <div class="security-icon warning"><i class="fa-solid fa-location-dot"></i></div>
            <div class="security-content">
              <h4>Unusual Location</h4>
<?php if (!empty($withdrawAlerts['unusual_ip'])): ?>
<?php $ui = $withdrawAlerts['unusual_ip']; ?>
              <p>New IP detected (<?php echo admin_tx_h((string)($ui['ip'] ?? '')); ?>)</p>
              <small>User: <?php echo admin_tx_h((string)($ui['user_label'] ?? '—')); ?> | Prev: <?php echo admin_tx_h((string)($ui['prev_ip'] ?? '')); ?> | <?php echo (int)($ui['minutes_ago'] ?? 0); ?> min ago</small>
<?php else: ?>
<?php if (!empty($withdrawAlerts['unusual_country'])): ?>
<?php $uc = $withdrawAlerts['unusual_country']; ?>
              <p>Country changed (<?php echo admin_tx_h((string)($uc['country'] ?? '')); ?>)</p>
              <small>User: <?php echo admin_tx_h((string)($uc['user_label'] ?? '—')); ?> | Prev: <?php echo admin_tx_h((string)($uc['prev_country'] ?? '')); ?> | <?php echo (int)($uc['minutes_ago'] ?? 0); ?> min ago</small>
<?php else: ?>
              <p>No geo anomalies detected</p>
              <small>Flagged pending withdrawals: <?php echo (int)($withdrawAlerts['flagged_pending_count'] ?? 0); ?></small>
<?php endif; ?>
<?php endif; ?>
            </div>
          </div>
          <div class="security-card warning">
            <div class="security-icon warning"><i class="fa-solid fa-bolt"></i></div>
            <div class="security-content">
              <h4>Rapid Multiple Withdrawals</h4>
<?php if (!empty($withdrawAlerts['rapid_user'])): ?>
<?php $ru = $withdrawAlerts['rapid_user']; ?>
              <p><?php echo (int)($ru['count'] ?? 0); ?> withdrawals in <?php echo (int)($ru['minutes'] ?? 10); ?> minutes</p>
              <small>User: <?php echo admin_tx_h((string)($ru['user_label'] ?? '—')); ?> | Total: <?php echo '$' . number_format((float)($ru['total_usd'] ?? 0), 0); ?></small>
<?php else: ?>
              <p>No rapid withdrawal bursts detected</p>
              <small>—</small>
<?php endif; ?>
            </div>
          </div>
          <div class="security-card">
            <div class="security-icon success"><i class="fa-solid fa-shield"></i></div>
            <div class="security-content">
              <h4>New Wallet Address</h4>
<?php if (!empty($withdrawAlerts['new_address'])): ?>
<?php $na = $withdrawAlerts['new_address']; ?>
              <p><?php echo ($na['is_first_for_user'] ?? false) ? 'First withdrawal to this address' : 'Address seen before for this user'; ?></p>
              <div>
                <span class="whitelist-badge unverified"><i class="fa-solid fa-circle-exclamation"></i> <?php echo admin_tx_h((string)($na['to_address_short'] ?? '—')); ?></span>
              </div>
              <small>User: <?php echo admin_tx_h((string)($na['user_label'] ?? '—')); ?> | Requires verification</small>
<?php else: ?>
              <p>No pending withdrawal address to review</p>
              <div><span class="whitelist-badge verified"><i class="fa-solid fa-check-circle"></i> —</span></div>
              <small>—</small>
<?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Analytics Cards -->
        <div class="analytics-grid" data-aos="fade-up">
          <div class="analytics-card">
            <div class="analytics-header">
              <span class="analytics-icon"><i class="fa-solid fa-arrow-up"></i></span>
              <span class="trend-up"><i class="fa-solid fa-arrow-up"></i> 8.3%</span>
            </div>
            <div class="analytics-value"><?php echo '$' . number_format((float)($withdrawStats['total_completed_usd'] ?? 0), 0); ?></div>
            <div class="analytics-label">Total Withdrawals (completed)</div>
            <div class="mini-chart"><canvas id="chartTotal" width="100" height="30"></canvas></div>
          </div>
          <div class="analytics-card">
            <div class="analytics-header">
              <span class="analytics-icon"><i class="fa-solid fa-clock"></i></span>
              <span class="trend-up"><i class="fa-solid fa-arrow-up"></i> 12</span>
            </div>
            <div class="analytics-value"><?php echo (int)($withdrawStats['pending_count'] ?? 0); ?></div>
            <div class="analytics-label">Pending</div>
            <div class="mini-chart"><canvas id="chartPending" width="100" height="30"></canvas></div>
          </div>
          <div class="analytics-card">
            <div class="analytics-header">
              <span class="analytics-icon"><i class="fa-solid fa-check-circle"></i></span>
              <span class="trend-up"><i class="fa-solid fa-arrow-up"></i> 5.2%</span>
            </div>
            <div class="analytics-value"><?php echo (int)($withdrawStats['completed_count'] ?? 0); ?></div>
            <div class="analytics-label">Approved</div>
            <div class="mini-chart"><canvas id="chartApproved" width="100" height="30"></canvas></div>
          </div>
          <div class="analytics-card">
            <div class="analytics-header">
              <span class="analytics-icon"><i class="fa-solid fa-times-circle"></i></span>
              <span class="trend-down"><i class="fa-solid fa-arrow-down"></i> 3%</span>
            </div>
            <div class="analytics-value"><?php echo (int)($withdrawStats['rejected_count'] ?? 0); ?></div>
            <div class="analytics-label">Rejected</div>
            <div class="mini-chart"><canvas id="chartRejected" width="100" height="30"></canvas></div>
          </div>
          <div class="analytics-card">
            <div class="analytics-header">
              <span class="analytics-icon"><i class="fa-solid fa-flag"></i></span>
              <span class="trend-up"><i class="fa-solid fa-arrow-up"></i> 2</span>
            </div>
            <div class="analytics-value"><?php echo (int)($withdrawStats['flagged_count'] ?? 0); ?></div>
            <div class="analytics-label">Flagged</div>
            <div class="mini-chart"><canvas id="chartFlagged" width="100" height="30"></canvas></div>
          </div>
        </div>

        <!-- Top Controls -->
        <div class="control-bar" data-aos="fade-up">
          <div class="control-search">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by user, ID, wallet...">
          </div>
          <div class="control-filters">
            <select class="filter-select" id="statusFilter">
              <option value="">All Withdrawals</option>
              <option value="pending">Pending</option>
              <option value="completed">Approved</option>
              <option value="failed">Rejected</option>
              <option value="flagged">Flagged</option>
            </select>
            <div class="date-range">
              <i class="fa-regular fa-calendar"></i>
              <span>Mar 1 - Mar 18, 2025</span>
            </div>
            <select class="filter-select" id="sortFilter">
              <option value="newest">Newest</option>
              <option value="oldest">Oldest</option>
              <option value="highest_amount">Highest Amount</option>
            </select>
            <button class="btn-outline"><i class="fa-regular fa-file-pdf"></i> PDF</button>
            <button class="btn-outline"><i class="fa-regular fa-file-excel"></i> CSV</button>
          </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="bulk-bar" id="bulkBar" style="display: none;">
          <span><span id="selectedCount">0</span> withdrawals selected</span>
          <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn-primary" style="background: var(--success);" onclick="bulkApprove()">Approve Selected</button>
            <button class="btn-danger" onclick="bulkReject()">Reject Selected</button>
            <button class="btn-warning" onclick="bulkFlag()">Flag Selected</button>
          </div>
        </div>

        <!-- Withdrawals Table -->
        <div class="table-wrapper" data-aos="fade-up">
          <table>
            <thead>
              <tr>
                <th class="checkbox-col"><input type="checkbox" id="selectAll"></th>
                <th>Withdrawal ID</th>
                <th>User</th>
                <th>Amount</th>
                <th>Currency</th>
                <th>Network</th>
                <th>Wallet Address</th>
                <th>Fee</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="withdrawalTableBody">
              <!-- JS will populate -->
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="pagination" id="withdrawalPagination"></div>

        <!-- Live Feed & Activity Logs -->
        <div class="two-column">
          <!-- Live Withdrawal Feed -->
          <div class="feed-panel" data-aos="fade-up">
            <h3><i class="fa-solid fa-rss" style="color: var(--primary);"></i> Live Withdrawal Feed</h3>
            <div id="liveFeed">
              <div class="feed-item" style="color: var(--text-muted);">Loading…</div>
            </div>
          </div>

          <!-- Admin Activity Logs -->
          <div class="feed-panel" data-aos="fade-up">
            <h3><i class="fa-solid fa-history" style="color: var(--primary);"></i> Admin Actions</h3>
            <div id="activityLogs">
              <div class="log-item" style="color: var(--text-muted);">Loading…</div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Processing Overlay -->
  <div class="processing-overlay" id="processingOverlay">
    <div class="processing-card">
      <div class="processing-spinner"></div>
      <h3 style="margin-bottom: 1.5rem;">Processing Withdrawal</h3>
      
      <div class="processing-step" id="step1">
        <div class="step-icon" id="step1Icon"><i class="fa-solid fa-circle-notch"></i></div>
        <span>Checking wallet balance...</span>
      </div>
      
      <div class="processing-step" id="step2">
        <div class="step-icon" id="step2Icon"><i class="fa-solid fa-circle-notch"></i></div>
        <span>Running security checks...</span>
      </div>
      
      <div class="processing-step" id="step3">
        <div class="step-icon" id="step3Icon"><i class="fa-solid fa-circle-notch"></i></div>
        <span>Processing withdrawal...</span>
      </div>
      
      <div class="processing-step" id="step4">
        <div class="step-icon" id="step4Icon"><i class="fa-solid fa-circle-notch"></i></div>
        <span>Withdrawal completed!</span>
      </div>
    </div>
  </div>

  <!-- Withdrawal Detail Modal -->
  <div class="modal-overlay" id="withdrawalModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Withdrawal Details</h2>
        <span class="modal-close" id="closeModal">&times;</span>
      </div>
      <div id="modalBody"></div>
    </div>
  </div>

  <!-- Rejection Reason Modal -->
  <div class="modal-overlay" id="rejectModal">
    <div class="modal-content confirmation-modal">
      <div class="modal-header">
        <h3>Reject Withdrawal</h3>
        <span class="modal-close" id="closeReject">&times;</span>
      </div>
      <div class="rejection-reason">
        <label style="color: var(--text-muted);">Reason for rejection:</label>
        <textarea id="rejectReason" rows="3" placeholder="Enter rejection reason..."></textarea>
      </div>
      <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
        <button class="btn-danger" id="confirmReject">Reject</button>
        <button class="btn-outline" id="cancelReject">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div class="modal-overlay" id="confirmModal">
    <div class="modal-content confirmation-modal">
      <div class="modal-header">
        <h3>Confirm Action</h3>
        <span class="modal-close" id="closeConfirm">&times;</span>
      </div>
      <p id="confirmMessage">Are you sure you want to approve this withdrawal?</p>
      <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
        <button class="btn-primary" id="confirmYes">Yes, Confirm</button>
        <button class="btn-outline" id="confirmNo">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Manual Security Controls Modal -->
  <div class="modal-overlay" id="securityModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Security Controls</h3>
        <span class="modal-close" id="closeSecurity">&times;</span>
      </div>
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <button class="btn-danger" style="width: 100%;" onclick="lockUserAccount()">
          <i class="fa-solid fa-lock"></i> Lock User Account
        </button>
        <button class="btn-warning" style="width: 100%;" onclick="freezeWithdrawal()">
          <i class="fa-solid fa-snowflake"></i> Freeze Withdrawal
        </button>
        <button class="btn-primary" style="width: 100%;" onclick="requestVerification()">
          <i class="fa-solid fa-id-card"></i> Request Additional Verification
        </button>
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
    AOS.init({ duration: 700, once: true });

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

    // Mini charts
    function initCharts() {
      new Chart(document.getElementById('chartTotal'), {
        type: 'line',
        data: {
          datasets: [{
            data: [3, 4, 5, 4, 6, 7, 8],
            borderColor: '#22c55e',
            borderWidth: 2,
            pointRadius: 0,
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { x: { display: false }, y: { display: false } },
          plugins: { legend: { display: false } }
        }
      });
      
      new Chart(document.getElementById('chartPending'), {
        type: 'line',
        data: {
          datasets: [{
            data: [12, 14, 13, 15, 16, 14, 17],
            borderColor: '#f59e0b',
            borderWidth: 2,
            pointRadius: 0,
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { x: { display: false }, y: { display: false } },
          plugins: { legend: { display: false } }
        }
      });
      
      new Chart(document.getElementById('chartApproved'), {
        type: 'line',
        data: {
          datasets: [{
            data: [8, 9, 10, 9, 11, 12, 13],
            borderColor: '#10b981',
            borderWidth: 2,
            pointRadius: 0,
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { x: { display: false }, y: { display: false } },
          plugins: { legend: { display: false } }
        }
      });
      
      new Chart(document.getElementById('chartRejected'), {
        type: 'line',
        data: {
          datasets: [{
            data: [2, 3, 2, 4, 3, 2, 3],
            borderColor: '#ef4444',
            borderWidth: 2,
            pointRadius: 0,
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { x: { display: false }, y: { display: false } },
          plugins: { legend: { display: false } }
        }
      });
      
      new Chart(document.getElementById('chartFlagged'), {
        type: 'line',
        data: {
          datasets: [{
            data: [1, 2, 1, 3, 2, 1, 2],
            borderColor: '#f97316',
            borderWidth: 2,
            pointRadius: 0,
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: { x: { display: false }, y: { display: false } },
          plugins: { legend: { display: false } }
        }
      });
    }

    function escapeHtml(s) {
      return String(s ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }

    function fmtWallet(w) {
      const s = String(w || "");
      if (s.length <= 18) return s || "—";
      return s.slice(0, 10) + "..." + s.slice(-6);
    }

    function uiStatus(tx) {
      const d = String(tx.display_status || "").toLowerCase();
      if (d === "completed") return { label: "Approved", cls: "status-approved", pending: false };
      if (d === "pending") return { label: "Pending", cls: "status-pending", pending: true };
      if (d === "failed" || d === "cancelled") return { label: "Rejected", cls: "status-rejected", pending: false };
      if (d === "flagged") return { label: "Flagged", cls: "status-flagged", pending: false };
      return { label: tx.display_status || "—", cls: "status-pending", pending: false };
    }

    function feeUsdFromMeta(tx) {
      const m = tx && tx.meta ? tx.meta : null;
      const v = m && (m.fee_usd ?? m.network_fee_usd ?? m.feeUsd);
      const n = Number(v);
      return Number.isFinite(n) ? n : 0;
    }

    async function postTxAction(action, id) {
      const r = await fetch(API_BASE, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ action, id, csrf_token: CSRF_TOKEN }),
      });
      return r.json();
    }

    let state = { rows: [], page: 1, perPage: 25, total: 0, q: "", status: "", sort: "newest" };
    const tbody = document.getElementById("withdrawalTableBody");
    const paginationEl = document.getElementById("withdrawalPagination");

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
            fetchWithdrawals();
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

    // Get fee class
    function getFeeClass(fee, network) {
      if (network.includes('ERC20') || network === 'Ethereum') return 'fee-high';
      if (network.includes('TRC20')) return 'fee-low';
      if (network === 'Bitcoin') return 'fee-medium';
      if (fee > 100) return 'fee-high';
      if (fee > 10) return 'fee-medium';
      return 'fee-low';
    }

    // Get fee label
    function getFeeLabel(network) {
      if (network.includes('ERC20') || network === 'Ethereum') return 'High';
      if (network.includes('TRC20')) return 'Low';
      if (network === 'Bitcoin') return 'Medium';
      if (network === 'Solana') return 'Low';
      return 'Standard';
    }

    function renderTable() {
      tbody.innerHTML = "";
      if (!state.rows.length) {
        tbody.innerHTML = '<tr><td colspan="11" style="padding:1rem;color:var(--text-muted);">No withdrawal records.</td></tr>';
        return;
      }
      state.rows.forEach((tx) => {
        const ui = uiStatus(tx);
        const feeUsd = feeUsdFromMeta(tx);
        const feeClass = getFeeClass(feeUsd, String(tx.network || ""));
        const feeLabel = getFeeLabel(String(tx.network || ""));
        const amt = Number(tx.amount || 0);
        const amtStr = "$" + amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const recv = String(tx.receiver_label || tx.wallet || "—");
        const row = document.createElement("tr");
        row.innerHTML = `
          <td class="checkbox-col"><input type="checkbox" class="withdrawal-checkbox" data-id="${tx.id}" data-pending="${ui.pending ? "1" : "0"}"></td>
          <td><strong>#${tx.id}</strong></td>
          <td>${escapeHtml(tx.user_label || "—")}</td>
          <td>${amtStr}</td>
          <td>${escapeHtml(tx.asset || "—")}</td>
          <td>${escapeHtml(tx.network || "—")}</td>
          <td><span class="wallet-address" title="${escapeHtml(recv)}">${escapeHtml(fmtWallet(recv))}</span></td>
          <td><span class="fee-badge ${feeClass}">$${feeUsd.toLocaleString(undefined, { maximumFractionDigits: 2 })} (${feeLabel})</span></td>
          <td><span class="status-badge ${ui.cls}">${escapeHtml(ui.label)}</span></td>
          <td>${escapeHtml(String(tx.created_at || "").slice(0, 19))}</td>
          <td>
            <div class="actions-dropdown">
              <button class="actions-btn" onclick="toggleDropdown(this)"><i class="fa-solid fa-ellipsis-vertical"></i> Actions</button>
              <div class="dropdown-menu">
                <a class="dropdown-item" onclick="viewWithdrawal(${tx.id})"><i class="fa-regular fa-eye"></i> View Details</a>
                ${ui.pending ? `<a class="dropdown-item" onclick="approveWithdrawal(${tx.id})"><i class="fa-regular fa-circle-check"></i> Approve</a>
                <a class="dropdown-item" onclick="showRejectModal(${tx.id})"><i class="fa-regular fa-circle-xmark"></i> Reject</a>` : ``}
                <a class="dropdown-item" onclick="flagWithdrawal(${tx.id})"><i class="fa-solid fa-flag"></i> Flag as Suspicious</a>
              </div>
            </div>
          </td>
        `;
        tbody.appendChild(row);
      });
    }

    async function fetchWithdrawals() {
      const url = new URL(API_BASE, window.location.href);
      url.searchParams.set("action", "list");
      url.searchParams.set("type", "withdrawal");
      url.searchParams.set("page", String(state.page));
      url.searchParams.set("per_page", String(state.perPage));
      if (state.q) url.searchParams.set("q", state.q);
      if (state.status) url.searchParams.set("status", state.status);
      if (state.sort) url.searchParams.set("sort", state.sort);
      tbody.innerHTML = '<tr><td colspan="11" style="padding:1rem;color:var(--text-muted);">Loading…</td></tr>';
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
      } catch (e) {
        tbody.innerHTML = '<tr><td colspan="11" style="padding:1rem;color:var(--danger);">Failed to load withdrawals.</td></tr>';
        if (paginationEl) paginationEl.innerHTML = "";
      }
    }

    async function refreshLivePanels() {
      const feed = document.getElementById("liveFeed");
      const logs = document.getElementById("activityLogs");
      if (!feed || !logs) return;
      try {
        const url = new URL(API_BASE, window.location.href);
        url.searchParams.set("action", "list");
        url.searchParams.set("type", "withdrawal");
        url.searchParams.set("per_page", "8");
        url.searchParams.set("page", "1");
        const r = await fetch(url.toString(), { credentials: "same-origin" });
        const data = await r.json();
        if (!data || !data.ok) return;
        const rows = Array.isArray(data.rows) ? data.rows : [];

        // Live Feed
        feed.innerHTML = "";
        const slice = rows.slice(0, 5);
        if (!slice.length) {
          feed.innerHTML = '<div class="feed-item" style="color:var(--text-muted);">No recent withdrawals.</div>';
        } else {
          slice.forEach((tx) => {
            const ui = uiStatus(tx);
            const amt = Number(tx.amount || 0);
            const amtStr = "$" + amt.toLocaleString(undefined, { maximumFractionDigits: 0 });
            const icon =
              ui.label === "Approved" ? "fa-check" :
              ui.label === "Rejected" ? "fa-times" :
              ui.label === "Flagged" ? "fa-flag" : "fa-clock";
            const iconClass =
              ui.label === "Approved" ? "success" :
              ui.label === "Rejected" ? "danger" :
              ui.label === "Flagged" ? "danger" : "warning";
            const item = document.createElement("div");
            item.className = "feed-item";
            item.innerHTML = `
              <span class="feed-icon ${iconClass}"><i class="fa-solid ${icon}"></i></span>
              <span><strong>${escapeHtml(tx.user_label || "User")}</strong> ${ui.label.toLowerCase()} withdrawal ${amtStr} ${escapeHtml(tx.asset || "")}</span>
              <span style="color: var(--text-muted); margin-left: auto;">${escapeHtml(String(tx.created_at || "").slice(11, 16))}</span>
            `;
            feed.appendChild(item);
          });
        }

        // Admin Actions (derived from latest withdrawal statuses)
        logs.innerHTML = "";
        const slice2 = rows.slice(0, 5);
        if (!slice2.length) {
          logs.innerHTML = '<div class="log-item" style="color:var(--text-muted);">No actions yet.</div>';
        } else {
          slice2.forEach((tx) => {
            const ui = uiStatus(tx);
            const sym = ui.label === "Approved" ? "✓" : ui.label === "Rejected" ? "✗" : ui.label === "Flagged" ? "⚑" : "⏳";
            const color = ui.label === "Approved" ? "var(--success)" : ui.label === "Rejected" ? "var(--danger)" : ui.label === "Flagged" ? "var(--orange)" : "var(--warning)";
            const item = document.createElement("div");
            item.className = "log-item";
            item.innerHTML = `<span style="color: ${color};">${sym}</span> ${escapeHtml(ui.label)} #${escapeHtml(String(tx.id))} (${escapeHtml(tx.user_label || "—")})`;
            logs.appendChild(item);
          });
        }
      } catch (e) {
        // ignore
      }
    }

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

    // Select all
    const selectAll = document.getElementById('selectAll');
    selectAll.addEventListener('change', (e) => {
      document.querySelectorAll('.withdrawal-checkbox').forEach(cb => cb.checked = e.target.checked);
      updateBulkBar();
    });

    function updateBulkBar() {
      const checked = document.querySelectorAll('.withdrawal-checkbox:checked').length;
      const bulkBar = document.getElementById('bulkBar');
      if (checked > 0) {
        bulkBar.style.display = 'flex';
        document.getElementById('selectedCount').innerText = checked;
      } else {
        bulkBar.style.display = 'none';
      }
    }

    document.addEventListener('change', (e) => {
      if (e.target.classList.contains('withdrawal-checkbox')) {
        updateBulkBar();
      }
    });

    window.approveWithdrawal = async function(id) {
      const overlay = document.getElementById("processingOverlay");
      if (overlay) overlay.style.display = "flex";
      try {
        const data = await postTxAction("approve", id);
        if (data && data.ok) {
          location.reload();
        } else {
          alert(data && data.error ? "Approve failed: " + data.error : "Approve failed.");
        }
      } catch (e) {
        alert("Request failed.");
      } finally {
        if (overlay) overlay.style.display = "none";
      }
    };

    // Show reject modal
    let currentRejectId = null;
    
    window.showRejectModal = function(id) {
      currentRejectId = id;
      document.getElementById('rejectModal').style.display = 'flex';
    };

    // Confirm reject (via API)
    document.getElementById('confirmReject').addEventListener('click', async () => {
      const id = currentRejectId;
      document.getElementById('rejectModal').style.display = 'none';
      document.getElementById('rejectReason').value = '';
      currentRejectId = null;
      if (!id) return;
      try {
        const data = await postTxAction("reject", id);
        if (data && data.ok) {
          location.reload();
        } else {
          alert(data && data.error ? "Reject failed: " + data.error : "Reject failed.");
        }
      } catch (e) {
        alert("Request failed.");
      }
    });

    // Flag withdrawal (via API)
    window.flagWithdrawal = async function(id) {
      try {
        const data = await postTxAction("flag", id);
        if (data && data.ok) {
          location.reload();
        } else {
          alert(data && data.error ? "Flag failed: " + data.error : "Flag failed.");
        }
      } catch (e) {
        alert("Request failed.");
      }
    };

    // View withdrawal modal (via API)
    window.viewWithdrawal = async function(id) {
      const modalBody = document.getElementById("modalBody");
      modalBody.innerHTML = '<p style="color:var(--text-muted);">Loading…</p>';
      document.getElementById("withdrawalModal").style.display = "flex";
      let tx = null;
      try {
        const r = await fetch(API_BASE + "?action=detail&id=" + encodeURIComponent(id), { credentials: "same-origin" });
        const data = await r.json();
        if (data && data.ok) tx = data.transaction;
      } catch (e) {}
      if (!tx) {
        modalBody.innerHTML = '<p style="color:var(--danger);">Could not load withdrawal.</p>';
        return;
      }
      const ui = uiStatus(tx);
      const feeUsd = feeUsdFromMeta(tx);
      const recv = String(tx.receiver_label || tx.wallet || "—");
      const amt = Number(tx.amount || 0);
      modalBody.innerHTML = `
        <div style="margin-bottom: 1.5rem;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">Withdrawal ID</div>
              <div style="font-weight: 600;">#${escapeHtml(tx.id)}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">User</div>
              <div style="font-weight: 600;">${escapeHtml(tx.user_label || "—")}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">Amount (USD)</div>
              <div style="font-weight: 600;">$${amt.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${escapeHtml(tx.asset || "")}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">Network Fee (USD)</div>
              <div>$${feeUsd.toLocaleString(undefined, { maximumFractionDigits: 2 })}</div>
            </div>
            <div style="grid-column: span 2;">
              <div style="color: var(--text-muted); font-size: 0.85rem;">To address</div>
              <div style="font-family: monospace; word-break: break-all;">${escapeHtml(recv)}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">Timestamp</div>
              <div>${escapeHtml(String(tx.created_at || "").slice(0, 19))}</div>
            </div>
            <div>
              <div style="color: var(--text-muted); font-size: 0.85rem;">Status</div>
              <div><span class="status-badge ${ui.cls}">${escapeHtml(ui.label)}</span></div>
            </div>
          </div>
        </div>
      `;
    };

    // Show security controls modal
    window.showSecurityModal = function(id) {
      currentRejectId = id;
      document.getElementById('securityModal').style.display = 'flex';
    };

    // Security control actions
    window.lockUserAccount = function() {
      alert('🔒 User account locked successfully');
      addActivityLog('security', currentRejectId, 'Account locked');
      document.getElementById('securityModal').style.display = 'none';
    };

    window.freezeWithdrawal = function() {
      alert('❄️ Withdrawal frozen');
      addActivityLog('security', currentRejectId, 'Withdrawal frozen');
      document.getElementById('securityModal').style.display = 'none';
    };

    window.requestVerification = function() {
      alert('📋 Verification request sent to user');
      addActivityLog('security', currentRejectId, 'Verification requested');
      document.getElementById('securityModal').style.display = 'none';
    };

    // Add to activity log
    function addActivityLog(action, id, details) {
      const logDiv = document.getElementById('activityLogs');
      const newLog = document.createElement('div');
      newLog.className = 'log-item';
      
      const actionIcons = {
        'approved': '✓',
        'rejected': '✗',
        'flagged': '⚑',
        'security': '🔒'
      };
      
      const actionColors = {
        'approved': 'var(--success)',
        'rejected': 'var(--danger)',
        'flagged': 'var(--orange)',
        'security': 'var(--primary)'
      };
      
      const detailText = details ? ` (${details})` : '';
      newLog.innerHTML = `<span style="color: ${actionColors[action]};">${actionIcons[action]}</span> ${action.charAt(0).toUpperCase() + action.slice(1)} ${id}${detailText} by Admin`;
      logDiv.prepend(newLog);
      
      if (logDiv.children.length > 5) {
        logDiv.removeChild(logDiv.lastChild);
      }
    }

    // Add to live feed
    function addToLiveFeed(action, user, amount, currency) {
      const feed = document.getElementById('liveFeed');
      const feedItem = document.createElement('div');
      feedItem.className = 'feed-item';
      
      let icon = 'fa-clock';
      let iconClass = 'warning';
      
      if (action === 'approved') {
        icon = 'fa-check';
        iconClass = 'success';
      } else if (action === 'rejected') {
        icon = 'fa-times';
        iconClass = 'danger';
      } else if (action === 'flagged') {
        icon = 'fa-flag';
        iconClass = 'danger';
      }
      
      feedItem.innerHTML = `
        <span class="feed-icon ${iconClass}"><i class="fa-solid ${icon}"></i></span>
        <span><strong>${action}</strong> withdrawal for <strong>${user}</strong> $${amount.toLocaleString()} ${currency}</span>
        <span style="color: var(--text-muted); margin-left: auto;">just now</span>
      `;
      
      feed.prepend(feedItem);
      if (feed.children.length > 5) {
        feed.removeChild(feed.lastChild);
      }
    }

    // Bulk actions (via API)
    window.bulkApprove = async function() {
      const selected = document.querySelectorAll('.withdrawal-checkbox:checked[data-pending="1"]');
      if (!selected.length) return alert("Select pending withdrawals to approve.");
      for (const cb of selected) {
        const id = parseInt(cb.dataset.id, 10);
        if (!id) continue;
        const data = await postTxAction("approve", id);
        if (!data || !data.ok) return alert(data && data.error ? "Stopped: " + data.error : "Failed.");
      }
      location.reload();
    };

    window.bulkReject = async function() {
      const selected = document.querySelectorAll('.withdrawal-checkbox:checked[data-pending="1"]');
      if (!selected.length) return alert("Select pending withdrawals to reject.");
      for (const cb of selected) {
        const id = parseInt(cb.dataset.id, 10);
        if (!id) continue;
        const data = await postTxAction("reject", id);
        if (!data || !data.ok) return alert(data && data.error ? "Stopped: " + data.error : "Failed.");
      }
      location.reload();
    };

    window.bulkFlag = async function() {
      const selected = document.querySelectorAll('.withdrawal-checkbox:checked');
      if (!selected.length) return alert("Select withdrawals to flag.");
      for (const cb of selected) {
        const id = parseInt(cb.dataset.id, 10);
        if (!id) continue;
        const data = await postTxAction("flag", id);
        if (!data || !data.ok) return alert(data && data.error ? "Stopped: " + data.error : "Failed.");
      }
      location.reload();
    };

    let searchTimer = null;
    document.getElementById("searchInput").addEventListener("input", (e) => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        state.q = e.target.value.trim();
        state.page = 1;
        fetchWithdrawals();
      }, 300);
    });
    document.getElementById("statusFilter").addEventListener("change", (e) => {
      state.status = e.target.value;
      state.page = 1;
      fetchWithdrawals();
    });
    document.getElementById("sortFilter").addEventListener("change", (e) => {
      state.sort = e.target.value;
      state.page = 1;
      fetchWithdrawals();
    });

    // Close modals
    document.getElementById('closeModal').addEventListener('click', () => {
      document.getElementById('withdrawalModal').style.display = 'none';
    });

    document.getElementById('closeReject').addEventListener('click', () => {
      document.getElementById('rejectModal').style.display = 'none';
    });

    document.getElementById('cancelReject').addEventListener('click', () => {
      document.getElementById('rejectModal').style.display = 'none';
    });

    document.getElementById('closeConfirm').addEventListener('click', () => {
      document.getElementById('confirmModal').style.display = 'none';
    });

    document.getElementById('closeSecurity').addEventListener('click', () => {
      document.getElementById('securityModal').style.display = 'none';
    });

    window.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
      }
    });

    // AI Assistant
    document.getElementById('aiBtn').addEventListener('click', () => {
      alert('🤖 AI Security Assistant:\n\n• 3 high-risk withdrawals detected\n• 1 critical withdrawal ($150,000) requires manual review\n• 2 withdrawals from new wallet addresses\n• 1 unusual location detected\n• 5 pending withdrawals');
    });

    // Initialize
    fetchWithdrawals();
    initCharts();
    refreshLivePanels();
    setInterval(refreshLivePanels, 15000);

    // Resize handler
    window.addEventListener('resize', () => {
      if (window.innerWidth > 1100) {
        sidebar.classList.remove('mobile-open');
      }
    });
  </script>
  <?php require_once __DIR__ . '/includes/admin_notify_client.php'; ?>
</body>
</html>