<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
require_once __DIR__ . '/includes/admin_wallet_data.php';

/** @var string $DB_NAME */
global $DB_NAME;
$schema = (string)$DB_NAME;

$csrf = admin_csrf_token();
$stats = [
  'total_balance_usd' => 0.0,
  'wallet_count' => 0,
  'active_wallets' => 0,
  'frozen_wallets' => 0,
  'spark_total' => [],
  'spark_wallets' => [],
  'spark_active' => [],
  'spark_frozen' => [],
];
$walletsBoot = [];
$topRows = [];
$feedRows = [];
$securityAlerts = [];
try {
  $db = admin_wallet_db();
  $stats = admin_wallet_aggregate_stats($db, $schema);
  $walletsBoot = admin_wallet_list_rows($db, $schema, 400, null);
  $topRows = admin_wallet_top_traders($db, $schema, 5);
  $feedRows = admin_wallet_recent_feed($db, 12);
  $securityAlerts = admin_wallet_security_alerts($db, $schema);
  $db->close();
} catch (Throwable $e) {
  error_log('[admin-wallet-control] ' . $e->getMessage());
}

$sn = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$apiUrl = rtrim(dirname($sn), '/') . '/api/wallet_control_api.php';

$boot = [
  'csrf' => $csrf,
  'apiUrl' => $apiUrl,
  'stats' => $stats,
  'wallets' => $walletsBoot,
  'top' => $topRows,
  'feed' => $feedRows,
  'alerts' => $securityAlerts,
];
$bootJson = json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_SLASHES);
if ($bootJson === false) {
  $bootJson = '{}';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · Admin · Wallet Control</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
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
    <!-- Select2 for user dropdown -->
    <link
      href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
      rel="stylesheet"
    />
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

      /* Analytics Cards */
      .analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

      /* Manual Fund Card */
      .fund-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
        transition: 0.3s;
      }

      .fund-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-glow);
      }

      .fund-card h3 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.2rem;
        color: var(--primary);
      }

      .fund-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1rem;
      }

      .fund-field {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }

      .fund-field label {
        font-size: 0.85rem;
        color: var(--text-muted);
      }

      .fund-field input,
      .fund-field select,
      .fund-field textarea {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.8rem 1rem;
        color: var(--text);
        font-size: 0.95rem;
        outline: none;
        transition: 0.2s;
      }

      .fund-field input:focus,
      .fund-field select:focus,
      .fund-field textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px var(--primary-soft);
      }

      .fund-field input[readonly] {
        background: var(--surface-light);
        color: var(--text-secondary);
      }

      .fund-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
      }

      /* Balance Adjustment Panel */
      .balance-panel {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.8rem;
      }

      .adjust-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .adjust-card.credit {
        border-left: 4px solid var(--success);
      }

      .adjust-card.debit {
        border-left: 4px solid var(--danger);
      }

      .adjust-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.2rem;
      }

      .adjust-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .adjust-icon.credit {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .adjust-icon.debit {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .adjust-field {
        margin-bottom: 1rem;
      }

      .adjust-field label {
        display: block;
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 0.3rem;
      }

      .adjust-field input,
      .adjust-field select {
        width: 100%;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.8rem 1rem;
        color: var(--text);
      }

      /* Wallet Status Control */
      .status-control {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
      }

      .status-badge-large {
        padding: 0.5rem 1.5rem;
        border-radius: 40px;
        font-size: 1rem;
        font-weight: 600;
      }

      .status-badge-large.active {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .status-badge-large.frozen {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .status-actions {
        display: flex;
        gap: 1rem;
      }

      /* Security Alerts */
      .security-alerts {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1rem;
        margin: 1.5rem 0;
      }

      .alert-card {
        background: rgba(239, 68, 68, 0.05);
        border: 1px solid var(--danger);
        border-radius: 30px;
        padding: 1.2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
      }

      .alert-card.warning {
        border-color: var(--orange);
        background: rgba(249, 115, 22, 0.05);
      }

      .alert-card.alert-ok {
        border-color: var(--primary);
        background: rgba(34, 197, 94, 0.06);
      }

      .alert-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
      }

      .alert-icon.critical {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .alert-icon.warning {
        background: rgba(249, 115, 22, 0.2);
        color: var(--orange);
      }

      .alert-icon.ok {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary);
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

      .btn-success {
        background: var(--success);
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

      .btn-success:hover {
        background: #0f9e6a;
        transform: scale(1.02);
        box-shadow: 0 0 30px rgba(16, 185, 129, 0.5);
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
        min-width: 1200px;
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

      /* Status Badges */
      .status-badge {
        padding: 0.25rem 0.8rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
      }

      .status-active {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .status-frozen {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
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
        max-width: 400px;
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
        to {
          transform: rotate(360deg);
        }
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
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .feed-icon.credit {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .feed-icon.debit {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .feed-icon.freeze {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
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

      /* Transaction history in modal */
      .history-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.8rem;
        border-bottom: 1px solid var(--glass-edge);
      }

      .history-item:last-child {
        border-bottom: none;
      }

      .history-amount.credit {
        color: var(--success);
      }

      .history-amount.debit {
        color: var(--danger);
      }

      /* Responsive */
      @media (max-width: 1100px) {
        .mobile-menu-btn {
          display: flex;
        }
        .control-bar {
          border-radius: 30px;
        }
        .balance-panel {
          grid-template-columns: 1fr;
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
        .analytics-value {
          font-size: 1.5rem;
        }
        .two-column {
          grid-template-columns: 1fr;
        }
        .fund-grid {
          grid-template-columns: 1fr;
        }
        .status-control {
          flex-direction: column;
          align-items: flex-start;
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

      /* Select2 Custom Theme */
      .select2-container--default .select2-selection--single {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        height: 45px;
        padding: 0.3rem 1rem;
      }

      .select2-container--default
        .select2-selection--single
        .select2-selection__rendered {
        color: var(--text);
        line-height: 35px;
      }

      .select2-container--default
        .select2-selection--single
        .select2-selection__arrow {
        top: 10px;
        right: 10px;
      }

      .select2-dropdown {
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        overflow: hidden;
      }

      .select2-results__option {
        color: var(--text);
      }

      .select2-results__option--highlighted {
        background: var(--primary-soft) !important;
        color: var(--primary) !important;
      }

      /* Auto-suggestions */
      .suggestions-box {
        position: absolute;
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        margin-top: 5px;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
      }

      .suggestion-item {
        padding: 0.8rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid var(--glass-edge);
      }

      .suggestion-item:hover {
        background: var(--primary-soft);
      }

      .suggestion-item:last-child {
        border-bottom: none;
      }

      /* Validation feedback */
      .validation-feedback {
        font-size: 0.8rem;
        margin-top: 0.3rem;
        display: none;
      }

      .validation-feedback.valid {
        color: var(--success);
        display: block;
      }

      .validation-feedback.invalid {
        color: var(--danger);
        display: block;
      }
    </style>
  </head>
  <body class="dark">
    <div class="admin">
      <!-- SIDEBAR (active on Wallet Control) -->
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
          <a href="./admin-wallet-control.php" class="sidebar-link active"
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

        <!-- WALLET CONTROL CONTENT -->
        <div class="content">
          <!-- Page Header -->
          <div class="page-header" data-aos="fade-up">
            <h1>Wallet Control</h1>
            <p>Manage and control user wallets across the platform.</p>
            <p style="font-size:0.85rem;color:var(--text-muted);margin-top:0.35rem">
              Data source: <code><?php echo htmlspecialchars((string)$DB_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code>
              — user accounts must live in this database (same as <code>db-config.php</code> / login).
            </p>
            <div class="breadcrumb">
              <a href="#">Admin</a> <span>/</span>
              <span style="color: var(--primary)">Wallet Control</span>
            </div>
          </div>

          <!-- Security Alerts (live from transactions) -->
          <div class="security-alerts" id="walletSecurityAlerts" data-aos="fade-up">
            <?php
            $alertsList = $securityAlerts !== [] ? $securityAlerts : [
              ['severity' => 'ok', 'title' => 'Live activity', 'detail' => 'Alerts could not be loaded. Refresh the page or check the error log.', 'sub' => ''],
              ['severity' => 'ok', 'title' => 'Frequent Adjustments', 'detail' => '—', 'sub' => ''],
              ['severity' => 'ok', 'title' => 'Suspicious Wallet Activity', 'detail' => '—', 'sub' => ''],
            ];
            foreach ($alertsList as $a) {
              $sev = isset($a['severity']) ? (string)$a['severity'] : 'ok';
              $cardClass = 'alert-card';
              if ($sev === 'warning') {
                $cardClass .= ' warning';
              } elseif ($sev === 'ok') {
                $cardClass .= ' alert-ok';
              }
              $iconClass = $sev === 'critical' ? 'critical' : ($sev === 'warning' ? 'warning' : 'ok');
              $iconFa = $sev === 'critical' ? 'fa-triangle-exclamation' : ($sev === 'warning' ? 'fa-clock' : 'fa-circle-check');
              ?>
            <div class="<?php echo htmlspecialchars($cardClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
              <div class="alert-icon <?php echo htmlspecialchars($iconClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <i class="fa-solid <?php echo htmlspecialchars($iconFa, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"></i>
              </div>
              <div>
                <h4><?php echo htmlspecialchars((string)($a['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h4>
                <p><?php echo htmlspecialchars((string)($a['detail'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
                <?php if (!empty($a['sub'])) { ?>
                <small><?php echo htmlspecialchars((string)$a['sub'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></small>
                <?php } ?>
              </div>
            </div>
            <?php } ?>
          </div>

          <!-- Wallet Overview Cards -->
          <div class="analytics-grid" data-aos="fade-up">
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-coins"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 5.3%</span
                >
              </div>
              <div class="analytics-value">$<?php echo number_format((float)($stats['total_balance_usd'] ?? 0), 2); ?></div>
              <div class="analytics-label">Total Platform Balance</div>
              <div class="mini-chart">
                <canvas id="chartTotal" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-users"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 124</span
                >
              </div>
              <div class="analytics-value"><?php echo number_format((int)($stats['wallet_count'] ?? 0)); ?></div>
              <div class="analytics-label">Total User Wallets</div>
              <div class="mini-chart">
                <canvas id="chartWallets" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-circle-check"></i
                ></span>
                <span class="trend-up"
                  ><i class="fa-solid fa-arrow-up"></i> 2.1%</span
                >
              </div>
              <div class="analytics-value"><?php echo number_format((int)($stats['active_wallets'] ?? 0)); ?></div>
              <div class="analytics-label">Active Wallets</div>
              <div class="mini-chart">
                <canvas id="chartActive" width="100" height="30"></canvas>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon"
                  ><i class="fa-solid fa-snowflake"></i
                ></span>
                <span class="trend-down"
                  ><i class="fa-solid fa-arrow-down"></i> 3</span
                >
              </div>
              <div class="analytics-value"><?php echo number_format((int)($stats['frozen_wallets'] ?? 0)); ?></div>
              <div class="analytics-label">Frozen Wallets</div>
              <div class="mini-chart">
                <canvas id="chartFrozen" width="100" height="30"></canvas>
              </div>
            </div>
          </div>

          <!-- Manual Fund User Panel -->
          <div class="fund-card" data-aos="fade-up">
            <h3>
              <i class="fa-solid fa-hand-holding-dollar"></i> Fund User Wallet
            </h3>
            <p
              style="
                font-size: 0.85rem;
                color: var(--text-muted);
                margin: 0 0 1rem;
                line-height: 1.45;
              "
            >
              Credits the user’s <strong>main wallet</strong> only (withdrawals,
              sends, transfers to trading). Funds do not appear in
              <strong>Trade</strong> until the user transfers them to their trading
              account from the Wallet page.
            </p>
            <div class="fund-grid">
              <div class="fund-field">
                <label
                  >User Email or Wallet ID
                  <span style="color: var(--danger)">*</span></label
                >
                <div style="position: relative">
                  <input
                    type="text"
                    id="userSearch"
                    placeholder="Start typing to search..."
                    autocomplete="off"
                  />
                  <div class="suggestions-box" id="suggestions"></div>
                </div>
                <div class="validation-feedback" id="userValidation"></div>
              </div>
              <div class="fund-field">
                <label
                  >Amount <span style="color: var(--danger)">*</span></label
                >
                <input
                  type="number"
                  id="fundAmount"
                  placeholder="0.00"
                  min="0.01"
                  step="0.01"
                />
                <div class="validation-feedback" id="amountValidation"></div>
              </div>
              <div class="fund-field">
                <label>Asset</label>
                <select id="fundAsset">
                  <option value="BTC">Bitcoin (BTC)</option>
                  <option value="ETH">Ethereum (ETH)</option>
                   <option value="TRX">Tron (TRX)</option>
                  <option value="USDT">Tether (USDT)</option>
                  <option value="USDC">USD Coin (USDC)</option>
                  <option value="SOL">Solana (SOL)</option>
                </select>
              </div>
              <div class="fund-field">
                <label>Network</label>
                <select id="fundNetwork">
                  <option value="Bitcoin">Bitcoin</option>
                  <option value="ERC20">ERC20</option>
                  <option value="TRC20">TRC20</option>
                   <option value="TRX">TRX</option>
                    <option value="ETH">ETH</option>
                  <option value="BEP20">BEP20</option>
                  <option value="Solana">Solana</option>
                </select>
              </div>
              <div class="fund-field">
                <label>Reference ID</label>
                <input type="text" id="fundReference" readonly />
              </div>
              <div class="fund-field">
                <label>Description (Optional)</label>
                <textarea
                  id="fundDescription"
                  placeholder="Add notes..."
                  rows="2"
                ></textarea>
              </div>
            </div>
            <div class="fund-actions">
              <button class="btn-primary" onclick="processManualFund()">
                <i class="fa-solid fa-circle-dollar"></i> Fund Wallet
              </button>
              <button class="btn-outline" onclick="resetFundForm()">
                <i class="fa-solid fa-rotate"></i> Reset
              </button>
            </div>
          </div>

          <!-- Balance Adjustment Panel -->
          <div class="balance-panel" data-aos="fade-up">
            <p
              style="
                font-size: 0.85rem;
                color: var(--text-muted);
                margin: 0 0 1rem;
                line-height: 1.45;
                grid-column: 1 / -1;
              "
            >
              <strong>Credit</strong> and <strong>Debit</strong> adjust the
              user’s <strong>main wallet</strong> (same pool as deposits and
              withdrawals). Trading uses a separate balance; users move funds
              from Wallet → Trade when ready.
            </p>
            <!-- Credit Panel -->
            <div class="adjust-card credit">
              <div class="adjust-header">
                <div class="adjust-icon credit">
                  <i class="fa-solid fa-plus"></i>
                </div>
                <h3>Credit (Add Balance)</h3>
              </div>
              <div class="adjust-field">
                <label>User</label>
                <select id="creditUser" style="width: 100%">
                  <option value="">Select user...</option>
                  <?php foreach ($walletsBoot as $w) { ?>
                  <option value="<?php echo (int)$w['userId']; ?>"><?php echo htmlspecialchars($w['user'] . ' — ' . $w['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="adjust-field">
                <label>Amount</label>
                <input type="number" id="creditAmount" placeholder="0.00" />
              </div>
              <div class="adjust-field">
                <label>Reason</label>
                <input
                  type="text"
                  id="creditReason"
                  placeholder="e.g., Manual adjustment"
                />
              </div>
              <button
                class="btn-success"
                style="width: 100%"
                onclick="processCredit()"
              >
                <i class="fa-solid fa-circle-plus"></i> Credit Wallet
              </button>
            </div>

            <!-- Debit Panel -->
            <div class="adjust-card debit">
              <div class="adjust-header">
                <div class="adjust-icon debit">
                  <i class="fa-solid fa-minus"></i>
                </div>
                <h3>Debit (Subtract Balance)</h3>
              </div>
              <div class="adjust-field">
                <label>User</label>
                <select id="debitUser" style="width: 100%">
                  <option value="">Select user...</option>
                  <?php foreach ($walletsBoot as $w) { ?>
                  <option value="<?php echo (int)$w['userId']; ?>"><?php echo htmlspecialchars($w['user'] . ' — ' . $w['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="adjust-field">
                <label>Amount</label>
                <input type="number" id="debitAmount" placeholder="0.00" />
              </div>
              <div class="adjust-field">
                <label>Reason</label>
                <input
                  type="text"
                  id="debitReason"
                  placeholder="e.g., Fee deduction"
                />
              </div>
              <button
                class="btn-danger"
                style="width: 100%"
                onclick="processDebit()"
              >
                <i class="fa-solid fa-circle-minus"></i> Debit Wallet
              </button>
            </div>
          </div>

          <!-- Wallet Status Control -->
          <div
            class="status-control"
            data-aos="fade-up"
            id="walletStatusControl"
          >
            <div style="display: flex; align-items: center; gap: 1rem">
              <i
                class="fa-solid fa-wallet"
                style="font-size: 2rem; color: var(--primary)"
              ></i>
              <div>
                <h4>
                  Selected Wallet: <span id="selectedWalletName">None</span>
                </h4>
                <div>
                  <span
                    class="status-badge-large active"
                    id="statusBadge"
                    style="display: none"
                    >Active</span
                  >
                </div>
              </div>
            </div>
            <div class="status-actions">
              <button
                class="btn-success"
                onclick="freezeWallet(false)"
                id="freezeBtn"
                style="display: none"
              >
                <i class="fa-solid fa-snowflake"></i> Freeze Wallet
              </button>
              <button
                class="btn-danger"
                onclick="freezeWallet(true)"
                id="unfreezeBtn"
                style="display: none"
              >
                <i class="fa-solid fa-fire"></i> Unfreeze Wallet
              </button>
              <span style="color: var(--text-muted)" id="noWalletMsg"
                >Select a wallet from table below</span
              >
            </div>
          </div>

          <!-- Search & Filters -->
          <div class="control-bar" data-aos="fade-up">
            <div class="control-search">
              <i class="fa-solid fa-search"></i>
              <input
                type="text"
                id="searchInput"
                placeholder="Search by email or wallet ID..."
              />
            </div>
            <div class="control-filters">
              <select class="filter-select" id="statusFilter">
                <option>All Wallets</option>
                <option>Active</option>
                <option>Frozen</option>
                <option>High Balance</option>
              </select>
              <button class="btn-outline">
                <i class="fa-regular fa-file-pdf"></i> PDF
              </button>
              <button class="btn-outline">
                <i class="fa-regular fa-file-excel"></i> CSV
              </button>
            </div>
          </div>

          <!-- Wallets Table -->
          <div class="table-wrapper" data-aos="fade-up">
            <table>
              <thead>
                <tr>
                  <th>Wallet ID</th>
                  <th>User</th>
                  <th>Email</th>
                  <th>Asset</th>
                  <th>Balance</th>
                  <th>Status</th>
                  <th>Last Activity</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="walletTableBody">
                <!-- JS will populate -->
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="pagination">
            <button class="page-btn">
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button class="page-btn active">1</button>
            <button class="page-btn">2</button>
            <button class="page-btn">3</button>
            <button class="page-btn">4</button>
            <button class="page-btn">5</button>
            <button class="page-btn">
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>

          <!-- Real-Time Activity Feed & Insights -->
          <div class="two-column">
            <!-- Activity Feed -->
            <div class="feed-panel" data-aos="fade-up">
              <h3>
                <i class="fa-solid fa-rss" style="color: var(--primary)"></i>
                Real-Time Activity
              </h3>
              <div id="liveFeed">
                <?php foreach ($feedRows as $fe) {
                  $ft = strtolower((string)($fe['type'] ?? ''));
                  $ic = $ft === 'withdrawal' || ($fe['label'] ?? '') === 'Admin debit' ? 'debit' : 'credit';
                  if (strpos($ft, 'trade') !== false) {
                    $ic = 'credit';
                  }
                  ?>
                <div class="feed-item">
                  <span class="feed-icon <?php echo $ic === 'debit' ? 'debit' : 'credit'; ?>"
                    ><i class="fa-solid fa-<?php echo $ic === 'debit' ? 'circle-minus' : 'circle-plus'; ?>"></i
                  ></span>
                  <span><?php echo htmlspecialchars((string)($fe['label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> · <?php echo htmlspecialchars((string)($fe['user'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> · <?php echo htmlspecialchars((string)($fe['amount'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                  <span style="color: var(--text-muted); margin-left: auto"><?php echo htmlspecialchars((string)($fe['created_at'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                </div>
                <?php } ?>
                <?php if ($feedRows === []) { ?>
                <p style="color: var(--text-muted); font-size: 0.9rem">No recent transactions yet.</p>
                <?php } ?>
              </div>
            </div>

            <!-- Top Users by Balance -->
            <div class="feed-panel" data-aos="fade-up">
              <h3>
                <i class="fa-solid fa-trophy" style="color: var(--primary)"></i>
                Top Users by Balance
              </h3>
              <div>
                <?php $rank = 0;
                foreach ($topRows as $tr) {
                  $rank++; ?>
                <div class="log-item">
                  <strong><?php echo (int)$rank; ?>. <?php echo htmlspecialchars((string)($tr['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                  <span style="margin-left: auto; color: var(--primary)">$<?php echo number_format((float)($tr['balance_usd'] ?? 0), 2); ?></span>
                </div>
                <?php } ?>
                <?php if ($topRows === []) { ?>
                <p style="color: var(--text-muted); font-size: 0.9rem">No users yet.</p>
                <?php } ?>
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
        <h3 style="margin-bottom: 1.5rem">Processing Transaction</h3>

        <div class="processing-step" id="step1">
          <div class="step-icon" id="step1Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Validating user...</span>
        </div>

        <div class="processing-step" id="step2">
          <div class="step-icon" id="step2Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Processing transaction...</span>
        </div>

        <div class="processing-step" id="step3">
          <div class="step-icon" id="step3Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Updating wallet balance...</span>
        </div>

        <div class="processing-step" id="step4">
          <div class="step-icon" id="step4Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Success!</span>
        </div>
      </div>
    </div>

    <!-- Wallet History Modal -->
    <div class="modal-overlay" id="historyModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Wallet History</h2>
          <span class="modal-close" id="closeHistory">&times;</span>
        </div>
        <div id="historyContent">
          <!-- Dynamic content will be inserted -->
        </div>
      </div>
    </div>

    <!-- Adjust Balance Modal -->
    <div class="modal-overlay" id="adjustModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Adjust Balance</h2>
          <span class="modal-close" id="closeAdjust">&times;</span>
        </div>
        <div id="adjustContent">
          <!-- Dynamic content will be inserted -->
        </div>
      </div>
    </div>

    <!-- AI Assistant -->
    <div class="ai-assistant" id="aiBtn">
      <i class="fa-solid fa-robot"></i>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
      AOS.init({ duration: 700, once: true });

      window.__WALLET_BOOT = <?php echo $bootJson; ?>;
      let wallets = Array.isArray(window.__WALLET_BOOT.wallets)
        ? window.__WALLET_BOOT.wallets
        : [];
      let walletCsrf = window.__WALLET_BOOT.csrf || "";
      // Resolve from the current page URL so /investmentwebsite/Admin/... and /Admin/... both work.
      const WALLET_API = new URL(
        "api/wallet_control_api.php",
        window.location.href,
      ).href;
      let fundSelectedUserId = 0;
      let selectedWalletUserId = 0;
      let selectedWalletRef = "";
      let searchTimer = null;
      let searchTableTimer = null;

      function esc(s) {
        return String(s ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;");
      }

      function escAttr(s) {
        return String(s ?? "")
          .replace(/&/g, "&amp;")
          .replace(/"/g, "&quot;")
          .replace(/</g, "&lt;");
      }

      function effectiveFundUserId() {
        const u = fundSelectedUserId || selectedWalletUserId;
        return typeof u === "number" && !Number.isNaN(u) ? u : parseInt(String(u), 10) || 0;
      }

      async function walletApiPost(body) {
        const res = await fetch(WALLET_API, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ ...body, csrf_token: walletCsrf }),
        });
        const data = await res.json().catch(() => ({}));
        if (data && data.csrf) walletCsrf = data.csrf;
        return { res, data };
      }

      function renderSecurityAlerts(alerts) {
        const el = document.getElementById("walletSecurityAlerts");
        if (!el || !Array.isArray(alerts) || alerts.length === 0) return;
        el.innerHTML = alerts
          .map((a) => {
            const sev = a.severity || "ok";
            let cardClass = "alert-card";
            if (sev === "warning") cardClass += " warning";
            else if (sev === "ok") cardClass += " alert-ok";
            const iconClass =
              sev === "critical" ? "critical" : sev === "warning" ? "warning" : "ok";
            const iconFa =
              sev === "critical"
                ? "fa-triangle-exclamation"
                : sev === "warning"
                  ? "fa-clock"
                  : "fa-circle-check";
            const sub = a.sub
              ? `<small>${esc(a.sub)}</small>`
              : "";
            return `<div class="${cardClass}"><div class="alert-icon ${iconClass}"><i class="fa-solid ${iconFa}"></i></div><div><h4>${esc(a.title)}</h4><p>${esc(a.detail)}</p>${sub}</div></div>`;
          })
          .join("");
      }

      async function refreshSecurityAlerts() {
        try {
          const r = await fetch(WALLET_API + "?action=alerts", {
            credentials: "same-origin",
          });
          const d = await r.json().catch(() => ({}));
          if (d && d.ok && Array.isArray(d.alerts)) {
            renderSecurityAlerts(d.alerts);
          }
        } catch (e) {
          /* ignore */
        }
      }

      async function refreshWalletBootstrap() {
        const r = await fetch(WALLET_API + "?action=bootstrap", {
          credentials: "same-origin",
        });
        const d = await r.json().catch(() => ({}));
        if (d && d.ok && Array.isArray(d.wallets)) {
          wallets = d.wallets;
          if (d.csrf) walletCsrf = d.csrf;
          renderTable();
          populateUserSelects();
          if (Array.isArray(d.alerts)) {
            renderSecurityAlerts(d.alerts);
          }
        }
      }

      function populateUserSelects() {
        const opts =
          '<option value="">Select user...</option>' +
          wallets
            .map(
              (w) =>
                `<option value="${Number(w.userId)}">${esc(w.user)} — ${esc(w.email)}</option>`,
            )
            .join("");
        const cr = document.getElementById("creditUser");
        const db = document.getElementById("debitUser");
        const s2 = { theme: "default", width: "100%", placeholder: "Select user..." };
        if (cr) {
          if ($(cr).data("select2")) $(cr).select2("destroy");
          cr.innerHTML = opts;
          $(cr).select2(s2);
        }
        if (db) {
          if ($(db).data("select2")) $(db).select2("destroy");
          db.innerHTML = opts;
          $(db).select2(s2);
        }
      }

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

      // Generate reference ID
      document.getElementById("fundReference").value =
        "FUND-" + Math.random().toString(36).substring(7).toUpperCase();

      // Auto-suggestions for user search
      const userSearch = document.getElementById("userSearch");
      const suggestions = document.getElementById("suggestions");

      userSearch.addEventListener("input", (e) => {
        const value = String(e.target.value || "").trim();
        if (searchTimer) clearTimeout(searchTimer);
        if (value.length < 1) {
          suggestions.style.display = "none";
          suggestions.innerHTML = "";
          fundSelectedUserId = 0;
          return;
        }
        searchTimer = setTimeout(async () => {
          const r = await fetch(
            WALLET_API + "?action=search&q=" + encodeURIComponent(value),
            { credentials: "same-origin" },
          );
          const ct = (r.headers.get("content-type") || "").toLowerCase();
          if (!r.ok || !ct.includes("application/json")) {
            suggestions.innerHTML =
              '<div class="suggestion-item" style="color:var(--danger)">Search unavailable (check login or network).</div>';
            suggestions.style.display = "block";
            return;
          }
          const d = await r.json().catch(() => ({}));
          if (!d.ok || !Array.isArray(d.users)) {
            suggestions.innerHTML = "";
            suggestions.style.display = "none";
            return;
          }
          suggestions.innerHTML = d.users
            .map(
              (u) =>
                `<div class="suggestion-item" role="button" data-uid="${Number(u.id)}" data-label="${escAttr(u.label)}" data-email="${escAttr(u.email)}"><strong>${esc(u.label)}</strong> — ${esc(u.email)}</div>`,
            )
            .join("");
          suggestions.style.display = d.users.length ? "block" : "none";
        }, 280);
      });

      suggestions.addEventListener("click", (e) => {
        const item = e.target.closest(".suggestion-item");
        if (!item) return;
        e.preventDefault();
        const uid = parseInt(item.getAttribute("data-uid") || "0", 10);
        const name = item.getAttribute("data-label") || "";
        const email = item.getAttribute("data-email") || "";
        if (uid > 0) {
          selectFundUser(uid, name, email);
        }
      });

      document.addEventListener("click", (e) => {
        if (!userSearch.contains(e.target) && !suggestions.contains(e.target)) {
          suggestions.style.display = "none";
        }
      });

      window.selectFundUser = function (userId, name, email) {
        fundSelectedUserId = Number(userId) || 0;
        userSearch.value = name + " (" + email + ")";
        suggestions.style.display = "none";
        document.getElementById("userValidation").className =
          "validation-feedback valid";
        document.getElementById("userValidation").textContent = "✓ User selected";
      };

      // Validation
      function validateFundForm() {
        let isValid = true;

        if (!effectiveFundUserId()) {
          document.getElementById("userValidation").className =
            "validation-feedback invalid";
          document.getElementById("userValidation").textContent =
            "Please search and select a user";
          isValid = false;
        }

        const amount = document.getElementById("fundAmount").value;
        if (!amount || amount <= 0) {
          document.getElementById("amountValidation").className =
            "validation-feedback invalid";
          document.getElementById("amountValidation").textContent =
            "Please enter a valid amount";
          isValid = false;
        } else {
          document.getElementById("amountValidation").className =
            "validation-feedback valid";
          document.getElementById("amountValidation").textContent =
            "✓ Valid amount";
        }

        return isValid;
      }

      // Mini charts (sparklines from server stats)
      function initCharts() {
        const st = window.__WALLET_BOOT && window.__WALLET_BOOT.stats;
        const z = [0, 0, 0, 0, 0, 0, 0];
        const g = (k) =>
          st && Array.isArray(st[k]) && st[k].length ? st[k] : z;
        const mk = (id, data, color) =>
          new Chart(document.getElementById(id), {
            type: "line",
            data: {
              datasets: [
                {
                  data,
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
        mk("chartTotal", g("spark_total"), "#22c55e");
        mk("chartWallets", g("spark_wallets"), "#22c55e");
        mk("chartActive", g("spark_active"), "#10b981");
        mk("chartFrozen", g("spark_frozen"), "#ef4444");
      }

      // Render table
      function renderTable() {
        const tbody = document.getElementById("walletTableBody");
        tbody.innerHTML = "";
        wallets.forEach((wallet) => {
          const statusClass =
            wallet.status === "Active" ? "status-active" : "status-frozen";
          const row = document.createElement("tr");
          row.setAttribute("data-wallet-id", wallet.id);
          row.setAttribute("data-user", wallet.user);
          row.setAttribute("data-status", wallet.status);
          row.onclick = () => selectWallet(wallet.id);
          const idLit = JSON.stringify(wallet.id);
          row.innerHTML = `
          <td><strong>${esc(wallet.id)}</strong></td>
          <td>${esc(wallet.user)}</td>
          <td>${esc(wallet.email)}</td>
          <td>${esc(wallet.asset)}</td>
          <td>$${Number(wallet.balanceUSD).toLocaleString()} (${esc(String(wallet.balance))} ${esc(wallet.asset)})</td>
          <td><span class="status-badge ${statusClass}">${esc(wallet.status)}</span></td>
          <td>${esc(wallet.lastActivity)}</td>
          <td>
            <div class="actions-dropdown">
              <button class="actions-btn" onclick="toggleDropdown(event, this)"><i class="fa-solid fa-ellipsis-vertical"></i> Actions</button>
              <div class="dropdown-menu">
                <a class="dropdown-item" onclick="viewWallet(${idLit})"><i class="fa-regular fa-eye"></i> View Wallet</a>
                <a class="dropdown-item" onclick="showAdjustModal(${idLit})"><i class="fa-solid fa-scale-balanced"></i> Adjust Balance</a>
                <a class="dropdown-item" onclick="toggleWalletStatus(${idLit})"><i class="fa-solid fa-${wallet.status === "Active" ? "snowflake" : "fire"}"></i> ${wallet.status === "Active" ? "Freeze" : "Unfreeze"}</a>
                <a class="dropdown-item" onclick="showHistory(${idLit})"><i class="fa-solid fa-clock-rotate-left"></i> View History</a>
              </div>
            </div>
          </td>
        `;
          tbody.appendChild(row);
        });
      }

      // Prevent row click when clicking dropdown
      window.toggleDropdown = function (event, btn) {
        event.stopPropagation();
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

      // Select wallet from table
      window.selectWallet = function (walletId) {
        const wallet = wallets.find(
          (w) => String(w.id) === String(walletId),
        );
        if (wallet) {
          selectedWalletUserId = Number(wallet.userId);
          selectedWalletRef = wallet.id;
          fundSelectedUserId = Number(wallet.userId) || 0;
          const userSearchEl = document.getElementById("userSearch");
          if (userSearchEl) {
            userSearchEl.value = wallet.user + " (" + wallet.email + ")";
          }
          const uv = document.getElementById("userValidation");
          if (uv) {
            uv.className = "validation-feedback valid";
            uv.textContent = "✓ User selected";
          }
          document.getElementById("selectedWalletName").textContent =
            wallet.user + " (" + wallet.id + ")";
          document.getElementById("statusBadge").style.display = "inline-block";
          document.getElementById("statusBadge").className =
            "status-badge-large " +
            (wallet.status === "Active" ? "active" : "frozen");
          document.getElementById("statusBadge").textContent = wallet.status;

          if (wallet.status === "Active") {
            document.getElementById("freezeBtn").style.display = "inline-flex";
            document.getElementById("unfreezeBtn").style.display = "none";
          } else {
            document.getElementById("freezeBtn").style.display = "none";
            document.getElementById("unfreezeBtn").style.display =
              "inline-flex";
          }
          document.getElementById("noWalletMsg").style.display = "none";
        }
      };

      // Freeze/Unfreeze wallet (maps to account lock)
      window.freezeWallet = async function (unfreeze) {
        if (!selectedWalletUserId) {
          alert("Select a wallet from the table first.");
          return;
        }
        const action = unfreeze ? "unlock" : "lock";
        const { res, data } = await walletApiPost({
          action,
          user_id: selectedWalletUserId,
          reason: unfreeze ? "" : "Frozen from Admin Wallet Control",
        });
        if (!res.ok || !data.ok) {
          alert(
            data.error === "csrf"
              ? "Session expired — refresh the page."
              : data.error || "Could not update lock status.",
          );
          return;
        }
        await refreshWalletBootstrap();
        if (selectedWalletRef) selectWallet(selectedWalletRef);
        const w = wallets.find((x) => x.userId === selectedWalletUserId);
        if (w) addToFeed(unfreeze ? "unfreeze" : "freeze", w.user);
      };

      window.toggleWalletStatus = async function (walletId) {
        const wallet = wallets.find((w) => w.id === walletId);
        if (!wallet) return;
        const willFreeze = wallet.status === "Active";
        const action = willFreeze ? "lock" : "unlock";
        const { res, data } = await walletApiPost({
          action,
          user_id: wallet.userId,
          reason: willFreeze ? "Frozen from Admin Wallet Control" : "",
        });
        if (!res.ok || !data.ok) {
          alert(data.error || "Could not update lock status.");
          return;
        }
        await refreshWalletBootstrap();
        if (
          document
            .getElementById("selectedWalletName")
            .textContent.includes(wallet.user)
        ) {
          selectWallet(walletId);
        }
        addToFeed(willFreeze ? "freeze" : "unfreeze", wallet.user);
      };

      // Process manual fund
      window.processManualFund = async function () {
        if (!validateFundForm()) return;

        const overlay = document.getElementById("processingOverlay");
        overlay.style.display = "flex";

        for (let i = 1; i <= 4; i++) {
          document.getElementById(`step${i}Icon`).className = "step-icon";
          document.getElementById(`step${i}Icon`).innerHTML =
            '<i class="fa-solid fa-circle-notch"></i>';
        }

        const markStep = (n) => {
          document.getElementById(`step${n}Icon`).className =
            "step-icon completed";
          document.getElementById(`step${n}Icon`).innerHTML =
            '<i class="fa-solid fa-check"></i>';
        };

        try {
          markStep(1);
          const amount = parseFloat(
            document.getElementById("fundAmount").value || "0",
          );
          const asset = document.getElementById("fundAsset").value;
          const network = document.getElementById("fundNetwork").value;
          const reference = document.getElementById("fundReference").value;
          const description = document.getElementById("fundDescription").value;

          const fundUid = effectiveFundUserId();
          const { res, data } = await walletApiPost({
            action: "fund",
            user_id: fundUid,
            amount,
            asset,
            network,
            reference,
            description,
          });

          markStep(2);
          markStep(3);

          if (!res.ok || !data.ok) {
            markStep(4);
            overlay.style.display = "none";
            alert(data.error || "Could not fund wallet.");
            return;
          }

          markStep(4);
          overlay.style.display = "none";
          const u = wallets.find(
            (w) => Number(w.userId) === Number(fundUid),
          );
          addToFeed("credit", u ? u.user : "User", String(amount), asset);
          alert("Successfully funded wallet.");
          resetFundForm();
          await refreshWalletBootstrap();
        } catch (err) {
          overlay.style.display = "none";
          alert(err && err.message ? err.message : "Network error");
        }
      };

      // Process credit
      window.processCredit = async function () {
        const userId = parseInt(
          document.getElementById("creditUser").value || "0",
          10,
        );
        const amount = parseFloat(
          document.getElementById("creditAmount").value || "0",
        );
        const reason = document.getElementById("creditReason").value;

        if (!userId || !amount) {
          alert("Please select a user and enter amount");
          return;
        }

        const { res, data } = await walletApiPost({
          action: "credit",
          user_id: userId,
          amount,
          reason,
        });
        if (!res.ok || !data.ok) {
          alert(data.error || "Could not credit wallet.");
          return;
        }
        const u = wallets.find((w) => Number(w.userId) === userId);
        addToFeed("credit", u ? u.user : "User " + userId, String(amount), "USDT");
        alert("Credit applied.");
        document.getElementById("creditAmount").value = "";
        document.getElementById("creditReason").value = "";
        await refreshWalletBootstrap();
      };

      // Process debit
      window.processDebit = async function () {
        const userId = parseInt(
          document.getElementById("debitUser").value || "0",
          10,
        );
        const amount = parseFloat(
          document.getElementById("debitAmount").value || "0",
        );
        const reason = document.getElementById("debitReason").value;

        if (!userId || !amount) {
          alert("Please select a user and enter amount");
          return;
        }

        const { res, data } = await walletApiPost({
          action: "debit",
          user_id: userId,
          amount,
          reason,
        });
        if (!res.ok || !data.ok) {
          alert(
            data.error === "insufficient"
              ? "Insufficient balance."
              : data.error || "Could not debit wallet.",
          );
          return;
        }
        const u = wallets.find((w) => Number(w.userId) === userId);
        addToFeed("debit", u ? u.user : "User " + userId, String(amount), "USDT");
        alert("Debit applied.");
        document.getElementById("debitAmount").value = "";
        document.getElementById("debitReason").value = "";
        await refreshWalletBootstrap();
      };

      // Add to live feed
      function addToFeed(type, user, amount = "", asset = "") {
        const feed = document.getElementById("liveFeed");
        const feedItem = document.createElement("div");
        feedItem.className = "feed-item";

        let icon = "";
        let iconClass = "";
        let text = "";

        switch (type) {
          case "credit":
            icon = "fa-circle-plus";
            iconClass = "credit";
            text = `<strong>Admin</strong> funded <strong>${user}</strong> $${amount} ${asset}`;
            break;
          case "debit":
            icon = "fa-circle-minus";
            iconClass = "debit";
            text = `Balance adjusted for <strong>${user}</strong> (-$${amount})`;
            break;
          case "freeze":
            icon = "fa-snowflake";
            iconClass = "freeze";
            text = `Wallet frozen for <strong>${user}</strong>`;
            break;
          case "unfreeze":
            icon = "fa-fire";
            iconClass = "freeze";
            text = `Wallet unfrozen for <strong>${user}</strong>`;
            break;
        }

        feedItem.innerHTML = `
        <span class="feed-icon ${iconClass}"><i class="fa-solid ${icon}"></i></span>
        <span>${text}</span>
        <span style="color: var(--text-muted); margin-left: auto;">just now</span>
      `;

        feed.prepend(feedItem);
        if (feed.children.length > 5) {
          feed.removeChild(feed.lastChild);
        }
      }

      // View wallet
      window.viewWallet = function (id) {
        const wallet = wallets.find((w) => w.id === id);
        if (!wallet) return;
        alert(
          `Wallet Details:\n\nUser: ${wallet.user}\nEmail: ${wallet.email}\nAsset: ${wallet.asset}\nBalance: ${wallet.balance} ${wallet.asset} ($${Number(wallet.balanceUSD).toFixed(2)})\nStatus: ${wallet.status}\nLast Activity: ${wallet.lastActivity}`,
        );
      };

      // Show history modal
      window.showHistory = async function (id) {
        const wallet = wallets.find((w) => w.id === id);
        const content = document.getElementById("historyContent");
        if (!wallet) return;
        content.innerHTML =
          '<p style="color:var(--text-muted)">Loading…</p>';
        document.getElementById("historyModal").style.display = "flex";
        try {
          const r = await fetch(
            WALLET_API +
              "?action=history&user_id=" +
              encodeURIComponent(String(wallet.userId)) +
              "&limit=40",
            { credentials: "same-origin" },
          );
          const d = await r.json().catch(() => ({}));
          if (!d.ok || !Array.isArray(d.items)) {
            content.innerHTML =
              '<p style="color:var(--danger)">Could not load history.</p>';
            return;
          }
          const rows = d.items
            .map((it) => {
              const amt = Number(it.amount);
              const sign =
                it.type === "withdrawal" ||
                (it.meta &&
                  it.meta.source === "admin_wallet_control" &&
                  it.meta.direction === "debit")
                  ? "-"
                  : "+";
              return `<div class="history-item" style="display:flex;justify-content:space-between;gap:1rem;padding:0.5rem 0;border-bottom:1px solid var(--border)">
            <span><strong>${esc(it.type)}</strong> ${esc(it.asset)}</span>
            <span class="history-amount ${sign === "-" ? "debit" : "credit"}">${sign}${esc(String(amt))}</span>
            <span style="color:var(--text-muted);font-size:0.85rem">${esc(it.created_at)}</span>
          </div>`;
            })
            .join("");
          content.innerHTML =
            `<h4 style="margin-bottom: 1rem;">Transaction History for ${esc(wallet.user)}</h4>` +
            (rows || "<p>No transactions yet.</p>");
        } catch (e) {
          content.innerHTML =
            '<p style="color:var(--danger)">Network error.</p>';
        }
      };

      // Show adjust balance modal
      window.showAdjustModal = function (id) {
        const wallet = wallets.find((w) => w.id === id);
        const content = document.getElementById("adjustContent");
        if (!wallet) return;
        const idLit = JSON.stringify(wallet.id);
        content.innerHTML = `
        <div style="margin-bottom: 1rem;">
          <p><strong>User:</strong> ${esc(wallet.user)}</p>
          <p><strong>Current Balance (USD pool):</strong> ${esc(String(wallet.balance))} ${esc(wallet.asset)} ($${Number(wallet.balanceUSD).toFixed(2)})</p>
        </div>
        <div class="adjust-field">
          <label>Adjustment Type</label>
          <select id="adjustType">
            <option value="credit">Credit (Add)</option>
            <option value="debit">Debit (Subtract)</option>
          </select>
        </div>
        <div class="adjust-field">
          <label>Amount (${esc(wallet.asset)})</label>
          <input type="number" id="adjustAmount" placeholder="0.00" step="0.01" min="0.01">
        </div>
        <div class="adjust-field">
          <label>Reason</label>
          <input type="text" id="adjustReason" placeholder="e.g., Manual adjustment">
        </div>
        <button class="btn-primary" style="width: 100%; margin-top: 1rem;" onclick="confirmAdjust(${idLit})">
          Apply Adjustment
        </button>
      `;

        document.getElementById("adjustModal").style.display = "flex";
      };

      window.confirmAdjust = async function (id) {
        const mode = document.getElementById("adjustType").value;
        const amount = parseFloat(
          document.getElementById("adjustAmount").value || "0",
        );
        const reason = document.getElementById("adjustReason").value;
        const wallet = wallets.find((w) => w.id === id);

        if (!amount || !wallet) {
          alert("Please enter an amount");
          return;
        }

        const { res, data } = await walletApiPost({
          action: "adjust",
          user_id: wallet.userId,
          amount,
          reason,
          mode,
        });
        if (!res.ok || !data.ok) {
          alert(
            data.error === "insufficient"
              ? "Insufficient balance."
              : data.error || "Could not adjust balance.",
          );
          return;
        }
        addToFeed(mode, wallet.user, String(amount), wallet.asset);
        document.getElementById("adjustModal").style.display = "none";
        alert("Adjustment applied.");
        await refreshWalletBootstrap();
      };

      // Reset fund form
      window.resetFundForm = function () {
        fundSelectedUserId = 0;
        document.getElementById("fundAmount").value = "";
        document.getElementById("fundAsset").value = "BTC";
        document.getElementById("fundNetwork").value = "Bitcoin";
        document.getElementById("fundReference").value =
          "FUND-" + Math.random().toString(36).substring(7).toUpperCase();
        document.getElementById("fundDescription").value = "";
        document.getElementById("amountValidation").className =
          "validation-feedback";
        document.getElementById("amountValidation").textContent = "";
        const userSearchEl = document.getElementById("userSearch");
        const uv = document.getElementById("userValidation");
        if (selectedWalletUserId && userSearchEl) {
          const w = wallets.find(
            (x) => Number(x.userId) === Number(selectedWalletUserId),
          );
          if (w) {
            userSearchEl.value = w.user + " (" + w.email + ")";
            if (uv) {
              uv.className = "validation-feedback valid";
              uv.textContent = "✓ User selected";
            }
          } else if (userSearchEl) {
            userSearchEl.value = "";
            if (uv) {
              uv.className = "validation-feedback";
              uv.textContent = "";
            }
          }
        } else if (userSearchEl) {
          userSearchEl.value = "";
          if (uv) {
            uv.className = "validation-feedback";
            uv.textContent = "";
          }
        }
      };

      // Search wallets table (server-side so users outside the first page are found)
      document.getElementById("searchInput").addEventListener("input", (e) => {
        const q = String(e.target.value || "").trim();
        if (searchTableTimer) clearTimeout(searchTableTimer);
        searchTableTimer = setTimeout(async () => {
          const url =
            WALLET_API +
            "?action=list&limit=400" +
            (q !== "" ? "&q=" + encodeURIComponent(q) : "");
          const r = await fetch(url, { credentials: "same-origin" });
          const ct = (r.headers.get("content-type") || "").toLowerCase();
          if (!r.ok || !ct.includes("application/json")) {
            return;
          }
          const d = await r.json().catch(() => ({}));
          if (d && d.ok && Array.isArray(d.wallets)) {
            wallets = d.wallets;
            renderTable();
            if (q !== "" && d.wallets.length === 0) {
              const tbody = document.getElementById("walletTableBody");
              tbody.innerHTML =
                '<tr><td colspan="8" style="padding:1rem;color:var(--text-muted)">No users match your search.</td></tr>';
            }
          }
        }, 320);
      });

      // Filter functionality
      document
        .getElementById("statusFilter")
        .addEventListener("change", (e) => {
          const filter = e.target.value.toLowerCase();
          const rows = document.querySelectorAll("#walletTableBody tr");

          rows.forEach((row) => {
            const statusCell = row.querySelector(".status-badge");
            if (statusCell) {
              const status = statusCell.textContent.toLowerCase();
              if (
                filter === "all wallets" ||
                filter.includes(status) ||
                (filter === "high balance" &&
                  parseFloat(row.cells[4].textContent.replace(/[$,]/g, "")) >
                    200000)
              ) {
                row.style.display = "";
              } else {
                row.style.display = "none";
              }
            }
          });
        });

      // Close modals
      document.getElementById("closeHistory").addEventListener("click", () => {
        document.getElementById("historyModal").style.display = "none";
      });

      document.getElementById("closeAdjust").addEventListener("click", () => {
        document.getElementById("adjustModal").style.display = "none";
      });

      window.addEventListener("click", (e) => {
        if (e.target.classList.contains("modal-overlay")) {
          e.target.style.display = "none";
        }
      });

      // AI Assistant
      document.getElementById("aiBtn").addEventListener("click", () => {
        const st = window.__WALLET_BOOT && window.__WALLET_BOOT.stats;
        const tb = st ? Number(st.total_balance_usd || 0).toFixed(2) : "—";
        const fw = st ? String(st.frozen_wallets ?? "—") : "—";
        alert(
          `Wallet snapshot:\n\n• Total platform balance (USD): $${tb}\n• Frozen accounts (locked): ${fw}\n\nUse Transactions for full audit trail.`,
        );
      });

      // Initialize
      renderTable();
      populateUserSelects();
      initCharts();
      setInterval(refreshSecurityAlerts, 45000);

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
