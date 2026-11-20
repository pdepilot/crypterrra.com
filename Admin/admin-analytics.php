<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_dashboard_helpers.php';
require_once __DIR__ . '/includes/admin_analytics_data.php';

$analytics = [
  'kpis' => [
    'total_users' => 0,
    'active_users' => 0,
    'total_deposits_usd' => 0.0,
    'total_withdrawals_usd' => 0.0,
    'trading_volume_usd' => 0.0,
    'revenue_usd' => 0.0,
    'platform_balance_usd' => 0.0,
    'avg_user_balance_usd' => 0.0,
  ],
  'series' => [
    'today' => ['revenue' => ['labels' => [], 'data' => []], 'users' => ['labels' => [], 'data' => []], 'volume' => ['labels' => [], 'data' => []], 'dvsw' => ['labels' => [], 'deposits' => [], 'withdrawals' => []], 'pie' => ['deposits' => 0.0, 'withdrawals' => 0.0, 'trades' => 0.0]],
    'd7' => ['revenue' => ['labels' => [], 'data' => []], 'users' => ['labels' => [], 'data' => []], 'volume' => ['labels' => [], 'data' => []], 'dvsw' => ['labels' => [], 'deposits' => [], 'withdrawals' => []], 'pie' => ['deposits' => 0.0, 'withdrawals' => 0.0, 'trades' => 0.0]],
    'd30' => ['revenue' => ['labels' => [], 'data' => []], 'users' => ['labels' => [], 'data' => []], 'volume' => ['labels' => [], 'data' => []], 'dvsw' => ['labels' => [], 'deposits' => [], 'withdrawals' => []], 'pie' => ['deposits' => 0.0, 'withdrawals' => 0.0, 'trades' => 0.0]],
  ],
];

try {
  $db = admin_dash_db();
  $analytics = admin_analytics_build_payload($db);
  $db->close();
} catch (Throwable) {
  // Keep safe defaults.
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
    <title>Crypterra · Admin · Analytics Dashboard</title>
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
    <!-- Simple Maps (for world map simulation) -->
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

      /* Time Filters */
      .time-filter-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.8rem;
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 0.8rem 1.5rem;
      }

      .time-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
      }

      .time-btn {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.5rem 1.2rem;
        color: var(--text-secondary);
        cursor: pointer;
        transition: 0.2s;
      }

      .time-btn:hover,
      .time-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
      }

      .date-range {
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.5rem 1rem;
        margin-left: auto;
      }

      .date-range i {
        color: var(--primary);
      }

      /* KPI Cards */
      .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 1.8rem;
      }

      .kpi-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 1.2rem;
        transition: 0.3s;
        position: relative;
        overflow: hidden;
      }

      .kpi-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-glow);
        transform: translateY(-3px);
      }

      .kpi-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.8rem;
      }

      .kpi-icon {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
      }

      .kpi-icon.users {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }
      .kpi-icon.active {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }
      .kpi-icon.deposit {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary);
      }
      .kpi-icon.withdrawal {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }
      .kpi-icon.volume {
        background: rgba(139, 92, 246, 0.2);
        color: #a78bfa;
      }
      .kpi-icon.revenue {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .kpi-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1.2;
        margin-bottom: 0.3rem;
      }

      .kpi-label {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
      }

      .kpi-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }

      .kpi-change {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 0.85rem;
        padding: 0.2rem 0.6rem;
        border-radius: 40px;
      }

      .kpi-change.positive {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
      }

      .kpi-change.negative {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
      }

      .kpi-sparkline {
        width: 80px;
        height: 30px;
      }

      /* Main Charts Grid */
      .charts-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1.5rem;
        margin-bottom: 1.8rem;
      }

      @media (max-width: 1200px) {
        .charts-grid {
          grid-template-columns: 1fr;
        }
      }

      .chart-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .chart-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 0.5rem;
      }

      .chart-header h3 {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--primary);
      }

      .chart-legend {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
      }

      .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
      }

      .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
      }

      .legend-color.revenue {
        background: var(--primary);
      }
      .legend-color.users {
        background: var(--info);
      }
      .legend-color.deposit {
        background: var(--success);
      }
      .legend-color.withdrawal {
        background: var(--danger);
      }
      .legend-color.volume {
        background: #a78bfa;
      }

      .chart-wrapper {
        height: 250px;
        position: relative;
      }

      /* World Map Simulation */
      .map-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
      }

      .map-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
      }

      .map-header h3 {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--primary);
      }

      .world-map {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 4px;
        padding: 1rem;
        background: var(--glass-bg);
        border-radius: 30px;
        min-height: 200px;
        align-items: center;
      }

      .map-grid-item {
        aspect-ratio: 1;
        background: var(--surface-light);
        border-radius: 4px;
        transition: 0.3s;
      }

      .map-grid-item[data-heat="1"] {
        background: rgba(34, 197, 94, 0.2);
      }
      .map-grid-item[data-heat="2"] {
        background: rgba(34, 197, 94, 0.4);
      }
      .map-grid-item[data-heat="3"] {
        background: rgba(34, 197, 94, 0.6);
      }
      .map-grid-item[data-heat="4"] {
        background: rgba(34, 197, 94, 0.8);
      }
      .map-grid-item[data-heat="5"] {
        background: var(--primary);
      }

      .map-legend {
        display: flex;
        justify-content: center;
        gap: 2rem;
        margin-top: 1rem;
        flex-wrap: wrap;
      }

      .map-legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .map-legend-color {
        width: 20px;
        height: 20px;
        border-radius: 4px;
      }

      /* Top Trading Pairs Table */
      .pairs-table {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
      }

      .pairs-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
      }

      .pairs-header h3 {
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--primary);
      }

      .table-mini {
        width: 100%;
        border-collapse: collapse;
      }

      .table-mini th {
        text-align: left;
        padding: 1rem 0.5rem;
        color: var(--text-muted);
        font-weight: 500;
        font-size: 0.85rem;
      }

      .table-mini td {
        padding: 0.8rem 0.5rem;
        border-bottom: 1px solid var(--glass-edge);
      }

      .table-mini tr:last-child td {
        border-bottom: none;
      }

      .change-positive {
        color: var(--success);
      }

      .change-negative {
        color: var(--danger);
      }

      /* User Engagement Panel */
      .engagement-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin-bottom: 1.8rem;
      }

      @media (max-width: 1200px) {
        .engagement-grid {
          grid-template-columns: repeat(2, 1fr);
        }
      }

      @media (max-width: 768px) {
        .engagement-grid {
          grid-template-columns: 1fr;
        }
      }

      .engagement-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .engagement-card h4 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.2rem;
        color: var(--primary);
      }

      .user-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
      }

      .user-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--glass-edge);
      }

      .user-item:last-child {
        border-bottom: none;
      }

      .user-info {
        display: flex;
        align-items: center;
        gap: 0.8rem;
      }

      .user-avatar-small {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-weight: 600;
        font-size: 0.8rem;
      }

      .user-value {
        font-weight: 600;
        color: var(--primary);
      }

      /* Transaction Analytics Pie */
      .pie-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.8rem;
      }

      @media (max-width: 768px) {
        .pie-container {
          grid-template-columns: 1fr;
        }
      }

      .pie-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .pie-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1rem;
        color: var(--primary);
      }

      .pie-wrapper {
        height: 200px;
        position: relative;
      }

      .pie-legend {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
        margin-top: 1rem;
      }

      .pie-legend-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
      }

      .pie-legend-color {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 8px;
      }

      .pie-legend-color.deposit {
        background: var(--success);
      }
      .pie-legend-color.withdrawal {
        background: var(--danger);
      }
      .pie-legend-color.trade {
        background: var(--info);
      }

      /* Financial Insights */
      .insights-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin-bottom: 1.8rem;
      }

      @media (max-width: 1200px) {
        .insights-grid {
          grid-template-columns: repeat(2, 1fr);
        }
      }

      @media (max-width: 768px) {
        .insights-grid {
          grid-template-columns: 1fr;
        }
      }

      .insight-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .insight-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
        margin: 0.5rem 0;
      }

      .insight-label {
        color: var(--text-muted);
        font-size: 0.9rem;
      }

      .asset-list {
        margin-top: 1rem;
      }

      .asset-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--glass-edge);
      }

      .asset-item:last-child {
        border-bottom: none;
      }

      .asset-name {
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .asset-percentage {
        color: var(--primary);
        font-weight: 600;
      }

      /* Alerts Panel */
      .alerts-panel {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
      }

      .alerts-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.2rem;
        color: var(--primary);
      }

      .alert-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        margin-bottom: 0.5rem;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
      }

      .alert-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .alert-icon.insight {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary);
      }
      .alert-icon.warning {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }
      .alert-icon.critical {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .alert-content {
        flex: 1;
      }

      .alert-content h4 {
        font-size: 0.95rem;
        margin-bottom: 0.2rem;
      }

      .alert-content p {
        font-size: 0.8rem;
        color: var(--text-muted);
      }

      /* Real-Time Feed */
      .feed-panel {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .feed-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.2rem;
        color: var(--primary);
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

      .feed-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
      }

      .feed-icon.user {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }
      .feed-icon.trade {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary);
      }
      .feed-icon.deposit {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
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

      /* Export Buttons */
      .export-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
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

      /* Loading Skeleton */
      .skeleton {
        background: linear-gradient(
          90deg,
          var(--surface) 25%,
          var(--surface-light) 50%,
          var(--surface) 75%
        );
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
        border-radius: 40px;
      }

      @keyframes loading {
        0% {
          background-position: 200% 0;
        }
        100% {
          background-position: -200% 0;
        }
      }

      /* Responsive */
      @media (max-width: 1100px) {
        .mobile-menu-btn {
          display: flex;
        }
        .time-filter-bar {
          border-radius: 30px;
        }
      }

      @media (max-width: 768px) {
        .content {
          padding: 1rem;
        }
        .time-filter-bar {
          flex-direction: column;
          align-items: stretch;
        }
        .date-range {
          margin-left: 0;
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
        .kpi-value {
          font-size: 1.5rem;
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
      <!-- SIDEBAR (active on Analytics) -->
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
          <a href="./admin-analytics.php" class="sidebar-link active"
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
              <input type="text" placeholder="Search analytics..." />
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

        <!-- ANALYTICS CONTENT -->
        <div class="content">
          <!-- Page Header -->
          <div class="page-header" data-aos="fade-up">
            <h1>Analytics Dashboard</h1>
            <p>
              Real-time insights and performance metrics across the platform.
            </p>
            <div class="breadcrumb">
              <a href="#">Admin</a> <span>/</span>
              <span style="color: var(--primary)">Analytics</span>
            </div>
          </div>

          <!-- Time Filters -->
          <div class="time-filter-bar" data-aos="fade-up">
            <div class="time-buttons">
              <button
                class="time-btn active"
                onclick="changeTimeFilter('today', this)"
              >
                Today
              </button>
              <button class="time-btn" onclick="changeTimeFilter('7d', this)">
                7 Days
              </button>
              <button class="time-btn" onclick="changeTimeFilter('30d', this)">
                30 Days
              </button>
            </div>
            <div class="date-range">
              <i class="fa-regular fa-calendar"></i>
              <span id="dateRangeLabel">—</span>
            </div>
            <div class="export-buttons">
              <button class="btn-outline" id="exportPdfBtn">
                <i class="fa-regular fa-file-pdf"></i> PDF
              </button>
              <button class="btn-outline" id="exportCsvBtn">
                <i class="fa-regular fa-file-excel"></i> CSV
              </button>
            </div>
          </div>

          <!-- KPI Cards -->
          <div class="kpi-grid" data-aos="fade-up">
            <div class="kpi-card" id="kpi-users">
              <div class="kpi-header">
                <span class="kpi-icon users"
                  ><i class="fa-solid fa-users"></i
                ></span>
              </div>
              <div class="kpi-value" id="totalUsers">0</div>
              <div class="kpi-label">Total Users</div>
              <div class="kpi-footer">
                <span class="kpi-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +12.5%</span
                >
                <div class="kpi-sparkline">
                  <canvas id="sparklineUsers" width="80" height="30"></canvas>
                </div>
              </div>
            </div>
            <div class="kpi-card">
              <div class="kpi-header">
                <span class="kpi-icon active"
                  ><i class="fa-solid fa-user-check"></i
                ></span>
              </div>
              <div class="kpi-value" id="activeUsers">0</div>
              <div class="kpi-label">Active Users (24h)</div>
              <div class="kpi-footer">
                <span class="kpi-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +5.2%</span
                >
                <div class="kpi-sparkline">
                  <canvas id="sparklineActive" width="80" height="30"></canvas>
                </div>
              </div>
            </div>
            <div class="kpi-card">
              <div class="kpi-header">
                <span class="kpi-icon deposit"
                  ><i class="fa-solid fa-arrow-down"></i
                ></span>
              </div>
              <div class="kpi-value" id="totalDeposits">$0</div>
              <div class="kpi-label">Total Deposits</div>
              <div class="kpi-footer">
                <span class="kpi-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +8.3%</span
                >
                <div class="kpi-sparkline">
                  <canvas
                    id="sparklineDeposits"
                    width="80"
                    height="30"
                  ></canvas>
                </div>
              </div>
            </div>
            <div class="kpi-card">
              <div class="kpi-header">
                <span class="kpi-icon withdrawal"
                  ><i class="fa-solid fa-arrow-up"></i
                ></span>
              </div>
              <div class="kpi-value" id="totalWithdrawals">$0</div>
              <div class="kpi-label">Total Withdrawals</div>
              <div class="kpi-footer">
                <span class="kpi-change negative"
                  ><i class="fa-solid fa-arrow-down"></i> -2.1%</span
                >
                <div class="kpi-sparkline">
                  <canvas
                    id="sparklineWithdrawals"
                    width="80"
                    height="30"
                  ></canvas>
                </div>
              </div>
            </div>
            <div class="kpi-card">
              <div class="kpi-header">
                <span class="kpi-icon volume"
                  ><i class="fa-solid fa-chart-line"></i
                ></span>
              </div>
              <div class="kpi-value" id="tradingVolume">$0</div>
              <div class="kpi-label">Trading Volume</div>
              <div class="kpi-footer">
                <span class="kpi-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +15.3%</span
                >
                <div class="kpi-sparkline">
                  <canvas id="sparklineVolume" width="80" height="30"></canvas>
                </div>
              </div>
            </div>
            <div class="kpi-card">
              <div class="kpi-header">
                <span class="kpi-icon revenue"
                  ><i class="fa-solid fa-coins"></i
                ></span>
              </div>
              <div class="kpi-value" id="revenue">$0</div>
              <div class="kpi-label">Revenue (Fees)</div>
              <div class="kpi-footer">
                <span class="kpi-change positive"
                  ><i class="fa-solid fa-arrow-up"></i> +7.8%</span
                >
                <div class="kpi-sparkline">
                  <canvas id="sparklineRevenue" width="80" height="30"></canvas>
                </div>
              </div>
            </div>
          </div>

          <!-- Main Charts Grid -->
          <div class="charts-grid" data-aos="fade-up">
            <!-- Revenue Chart -->
            <div class="chart-card">
              <div class="chart-header">
                <h3>
                  <i class="fa-solid fa-chart-line"></i> Revenue Over Time
                </h3>
                <div class="chart-legend">
                  <div class="legend-item">
                    <span class="legend-color revenue"></span> Revenue
                  </div>
                </div>
              </div>
              <div class="chart-wrapper">
                <canvas id="revenueChart"></canvas>
              </div>
            </div>

            <!-- User Growth Chart -->
            <div class="chart-card">
              <div class="chart-header">
                <h3><i class="fa-solid fa-users"></i> User Growth</h3>
                <div class="chart-legend">
                  <div class="legend-item">
                    <span class="legend-color users"></span> New Users
                  </div>
                </div>
              </div>
              <div class="chart-wrapper">
                <canvas id="userGrowthChart"></canvas>
              </div>
            </div>

            <!-- Trading Volume Chart -->
            <div class="chart-card">
              <div class="chart-header">
                <h3><i class="fa-solid fa-chart-bar"></i> Trading Volume</h3>
                <div class="chart-legend">
                  <div class="legend-item">
                    <span class="legend-color volume"></span> Volume (USD)
                  </div>
                </div>
              </div>
              <div class="chart-wrapper">
                <canvas id="volumeChart"></canvas>
              </div>
            </div>

            <!-- Deposit vs Withdrawal Chart -->
            <div class="chart-card">
              <div class="chart-header">
                <h3>
                  <i class="fa-solid fa-scale-balanced"></i> Deposit vs
                  Withdrawal
                </h3>
                <div class="chart-legend">
                  <div class="legend-item">
                    <span class="legend-color deposit"></span> Deposits
                  </div>
                  <div class="legend-item">
                    <span class="legend-color withdrawal"></span> Withdrawals
                  </div>
                </div>
              </div>
              <div class="chart-wrapper">
                <canvas id="depositWithdrawalChart"></canvas>
              </div>
            </div>
          </div>

          <!-- World Map Heatmap Simulation -->
          <div class="map-card" data-aos="fade-up">
            <div class="map-header">
              <h3><i class="fa-solid fa-globe"></i> User Distribution</h3>
              <span style="color: var(--text-muted)"
                >Active users by region</span
              >
            </div>
            <div class="world-map" id="worldMap">
              <!-- Grid will be populated by JS -->
            </div>
            <div class="map-legend">
              <div class="map-legend-item">
                <span
                  class="map-legend-color"
                  style="background: rgba(34, 197, 94, 0.2)"
                ></span>
                Low
              </div>
              <div class="map-legend-item">
                <span
                  class="map-legend-color"
                  style="background: rgba(34, 197, 94, 0.4)"
                ></span>
                Medium
              </div>
              <div class="map-legend-item">
                <span
                  class="map-legend-color"
                  style="background: rgba(34, 197, 94, 0.8)"
                ></span>
                High
              </div>
              <div class="map-legend-item">
                <span
                  class="map-legend-color"
                  style="background: var(--primary)"
                ></span>
                Very High
              </div>
            </div>
          </div>

          <!-- Top Trading Pairs -->
          <div class="pairs-table" data-aos="fade-up">
            <div class="pairs-header">
              <h3>
                <i class="fa-solid fa-arrow-right-arrow-left"></i> Top Trading
                Pairs
              </h3>
              <button class="btn-outline">View All</button>
            </div>
            <table class="table-mini">
              <thead>
                <tr>
                  <th>Pair</th>
                  <th>Volume (24h)</th>
                  <th>Trades</th>
                  <th>Change %</th>
                </tr>
              </thead>
              <tbody id="topPairsBody"></tbody>
            </table>
          </div>

          <!-- User Engagement Panel -->
          <div class="engagement-grid" data-aos="fade-up">
            <!-- Most Active Users -->
            <div class="engagement-card">
              <h4><i class="fa-solid fa-fire"></i> Most Active Users</h4>
              <div class="user-list" id="mostActiveUsersList"></div>
            </div>

            <!-- Highest Balances -->
            <div class="engagement-card">
              <h4><i class="fa-solid fa-trophy"></i> Highest Balances</h4>
              <div class="user-list" id="highestBalancesList"></div>
            </div>

            <!-- Frequent Traders -->
            <div class="engagement-card">
              <h4><i class="fa-solid fa-clock"></i> Frequent Traders</h4>
              <div class="user-list" id="frequentTradersList"></div>
            </div>
          </div>

          <!-- Transaction Analytics & Financial Insights -->
          <div class="pie-container" data-aos="fade-up">
            <!-- Transaction Types Pie Chart -->
            <div class="pie-card">
              <div class="pie-header">
                <i class="fa-solid fa-chart-pie"></i>
                <h4>Transaction Types</h4>
              </div>
              <div class="pie-wrapper">
                <canvas id="transactionPieChart"></canvas>
              </div>
              <div class="pie-legend">
                <div class="pie-legend-item">
                  <span
                    ><span class="pie-legend-color deposit"></span>
                    Deposits</span
                  >
                  <span id="pieDepositsPct">—</span>
                </div>
                <div class="pie-legend-item">
                  <span
                    ><span class="pie-legend-color withdrawal"></span>
                    Withdrawals</span
                  >
                  <span id="pieWithdrawalsPct">—</span>
                </div>
                <div class="pie-legend-item">
                  <span
                    ><span class="pie-legend-color trade"></span> Trades</span
                  >
                  <span id="pieTradesPct">—</span>
                </div>
              </div>
            </div>

            <!-- Financial Insights -->
            <div class="pie-card">
              <div class="pie-header">
                <i class="fa-solid fa-coins"></i>
                <h4>Financial Insights</h4>
              </div>
              <div style="padding: 0.5rem">
                <div class="insight-value" id="fiPlatformBalance">$0</div>
                <div class="insight-label">Total Platform Balance</div>

                <div class="insight-value" style="font-size: 1.5rem" id="fiAvgBalance">$0</div>
                <div class="insight-label">Average User Balance</div>

                <h5 style="margin: 1rem 0 0.5rem">Top Assets (by trade volume)</h5>
                <div class="asset-list" id="topAssetsList"></div>
              </div>
            </div>
          </div>

          <!-- Alerts & Insights Panel -->
          <div class="alerts-panel" data-aos="fade-up">
            <div class="alerts-header">
              <i class="fa-solid fa-lightbulb"></i>
              <h4>Smart Insights</h4>
            </div>
            <div id="smartInsightsList"></div>
          </div>

          <!-- Real-Time Data Feed -->
          <div class="feed-panel" data-aos="fade-up">
            <div class="feed-header">
              <i class="fa-solid fa-rss"></i>
              <h4>Real-Time Activity</h4>
            </div>
            <div id="liveFeed"></div>
          </div>
        </div>
      </main>
    </div>

    <!-- AI Assistant -->
    <div class="ai-assistant" id="aiBtn">
      <i class="fa-solid fa-robot"></i>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
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

      const ANALYTICS = <?php echo json_encode($analytics, JSON_UNESCAPED_SLASHES); ?>;
      const ANALYTICS_API = "./api/analytics_api.php";
      let currentPeriod = "today";

      function periodKey(period) {
        if (period === "today") return "today";
        if (period === "7d") return "d7";
        if (period === "30d") return "d30";
        return "today";
      }

      function formatCompactNumber(n) {
        const num = Number(n) || 0;
        const abs = Math.abs(num);
        if (abs >= 1e9) return (num / 1e9).toFixed(1).replace(/\.0$/, "") + "B";
        if (abs >= 1e6) return (num / 1e6).toFixed(1).replace(/\.0$/, "") + "M";
        if (abs >= 1e3) return (num / 1e3).toFixed(1).replace(/\.0$/, "") + "k";
        return Math.round(num).toLocaleString();
      }

      function formatCompactUSD(n) {
        const num = Number(n) || 0;
        return "$" + formatCompactNumber(num);
      }

      function setDateRangeLabel(period) {
        const el = document.getElementById("dateRangeLabel");
        const today = new Date();
        function fmt(d) {
          return d.toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" });
        }
        if (period === "today") {
          el.textContent = fmt(today);
          return;
        }
        const days = period === "7d" ? 7 : 30;
        const start = new Date(today);
        start.setDate(today.getDate() - (days - 1));
        el.textContent = `${fmt(start)} - ${fmt(today)}`;
      }

      function setKpis(kpis) {
        document.getElementById("totalUsers").textContent = (Number(kpis.total_users) || 0).toLocaleString();
        document.getElementById("activeUsers").textContent = (Number(kpis.active_users) || 0).toLocaleString();
        document.getElementById("totalDeposits").textContent = formatCompactUSD(kpis.total_deposits_usd);
        document.getElementById("totalWithdrawals").textContent = formatCompactUSD(kpis.total_withdrawals_usd);
        document.getElementById("tradingVolume").textContent = formatCompactUSD(kpis.trading_volume_usd);
        document.getElementById("revenue").textContent = formatCompactUSD(kpis.revenue_usd);
      }

      function escHtml(s) {
        return String(s ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;");
      }

      function formatPct(n) {
        const v = Number(n) || 0;
        const sign = v >= 0 ? "+" : "-";
        return `${sign}${Math.abs(v).toFixed(1)}%`;
      }

      function timeAgo(ts) {
        const t = Date.parse(ts);
        if (!isFinite(t)) return "—";
        const diff = Math.max(0, Date.now() - t);
        const s = Math.floor(diff / 1000);
        if (s < 10) return "just now";
        if (s < 60) return `${s}s ago`;
        const m = Math.floor(s / 60);
        if (m < 60) return `${m} min ago`;
        const h = Math.floor(m / 60);
        if (h < 24) return `${h} hr ago`;
        const d = Math.floor(h / 24);
        return `${d} day${d === 1 ? "" : "s"} ago`;
      }

      async function fetchSnapshot(period) {
        const url = `${ANALYTICS_API}?action=snapshot&period=${encodeURIComponent(period)}&limit=5`;
        const r = await fetch(url, { method: "GET", credentials: "same-origin" });
        const data = await r.json();
        if (!data || !data.ok) throw new Error("snapshot_failed");
        return data;
      }

      async function fetchFeed(limit = 5) {
        const url = `${ANALYTICS_API}?action=feed&limit=${encodeURIComponent(limit)}`;
        const r = await fetch(url, { method: "GET", credentials: "same-origin" });
        const data = await r.json();
        if (!data || !data.ok) throw new Error("feed_failed");
        return data.feed || [];
      }

      function renderUserList(containerId, items, mode) {
        const el = document.getElementById(containerId);
        if (!el) return;
        if (!items || items.length === 0) {
          el.innerHTML = `<div class="user-item"><div class="user-info"><span class="user-avatar-small">—</span><span style="color:var(--text-muted)">No data</span></div><span class="user-value" style="color:var(--text-muted)">—</span></div>`;
          return;
        }
        el.innerHTML = items
          .map((it) => {
            const initials = escHtml(it.initials || "NA");
            const label = escHtml(it.label || "User");
            let value = "—";
            if (mode === "most_active") value = `${Number(it.trades) || 0} trades`;
            if (mode === "highest_balances") value = formatCompactUSD(it.value);
            if (mode === "frequent_traders") value = `${(Number(it.per_day) || 0).toFixed(1)} trades/day`;
            return `<div class="user-item">
              <div class="user-info">
                <span class="user-avatar-small">${initials}</span>
                <span>${label}</span>
              </div>
              <span class="user-value">${escHtml(value)}</span>
            </div>`;
          })
          .join("");
      }

      function renderPairs(rows) {
        const tbody = document.getElementById("topPairsBody");
        if (!tbody) return;
        if (!rows || rows.length === 0) {
          tbody.innerHTML = `<tr><td colspan="4" style="color:var(--text-muted)">No trading pairs yet.</td></tr>`;
          return;
        }
        tbody.innerHTML = rows
          .map((r) => {
            const pair = escHtml(r.pair || "—");
            const vol = formatCompactUSD(r.volume);
            const trades = (Number(r.trades) || 0).toLocaleString();
            const pct = Number(r.change_pct) || 0;
            const cls = pct >= 0 ? "change-positive" : "change-negative";
            return `<tr>
              <td>${pair}</td>
              <td>${escHtml(vol)}</td>
              <td>${escHtml(trades)}</td>
              <td class="${cls}">${escHtml(formatPct(pct))}</td>
            </tr>`;
          })
          .join("");
      }

      function renderFinancial(fin) {
        if (!fin) return;
        const pb = document.getElementById("fiPlatformBalance");
        const ab = document.getElementById("fiAvgBalance");
        if (pb) pb.textContent = formatCompactUSD(fin.platform_balance_usd);
        if (ab) ab.textContent = formatCompactUSD(fin.avg_user_balance_usd);

        const assetsEl = document.getElementById("topAssetsList");
        if (!assetsEl) return;
        const items = fin.top_assets || [];
        if (items.length === 0) {
          assetsEl.innerHTML = `<div class="asset-item"><span class="asset-name"><span style="color:var(--text-muted)">●</span> —</span><span class="asset-percentage">0%</span></div>`;
          return;
        }
        const colors = ["var(--primary)", "var(--success)", "var(--info)", "var(--warning)"];
        assetsEl.innerHTML = items
          .map((a, idx) => {
            const c = colors[idx % colors.length];
            const asset = escHtml(a.asset || "—");
            const pct = Math.round(Number(a.pct) || 0) + "%";
            return `<div class="asset-item">
              <span class="asset-name"><span style="color:${c}">●</span> ${asset}</span>
              <span class="asset-percentage">${escHtml(pct)}</span>
            </div>`;
          })
          .join("");
      }

      function renderInsights(list) {
        const el = document.getElementById("smartInsightsList");
        if (!el) return;
        if (!list || list.length === 0) {
          el.innerHTML = `<div class="alert-item"><div class="alert-icon insight"><i class="fa-solid fa-lightbulb"></i></div><div class="alert-content"><h4>No insights yet</h4><p style="color:var(--text-muted)">No data available.</p></div></div>`;
          return;
        }
        el.innerHTML = list
          .map((it) => {
            const level = escHtml(it.level || "insight");
            const icon = escHtml(it.icon || "fa-solid fa-lightbulb");
            const title = escHtml(it.title || "Insight");
            const detail = escHtml(it.detail || "");
            return `<div class="alert-item">
              <div class="alert-icon ${level}"><i class="${icon}"></i></div>
              <div class="alert-content">
                <h4>${title}</h4>
                <p>${detail}</p>
              </div>
            </div>`;
          })
          .join("");
      }

      function renderFeed(items) {
        const feed = document.getElementById("liveFeed");
        if (!feed) return;
        if (!items || items.length === 0) {
          feed.innerHTML = `<div class="feed-item"><span class="feed-icon user"><i class="fa-solid fa-user"></i></span><span style="color:var(--text-muted)">No recent activity</span><span style="color:var(--text-muted); margin-left:auto">—</span></div>`;
          return;
        }
        feed.innerHTML = items
          .slice(0, 5)
          .map((e) => {
            const iconClass = escHtml(e.iconClass || "trade");
            const text = escHtml(e.text || "");
            const ago = timeAgo(e.ts);
            const iconName =
              iconClass === "user"
                ? "user-plus"
                : iconClass === "trade"
                  ? "arrow-right-arrow-left"
                  : iconClass === "deposit"
                    ? "arrow-down"
                    : iconClass === "withdrawal"
                      ? "arrow-up"
                      : "circle-info";
            return `<div class="feed-item">
              <span class="feed-icon ${iconClass}"><i class="fa-solid fa-${iconName}"></i></span>
              <span>${text}</span>
              <span style="color: var(--text-muted); margin-left: auto">${escHtml(ago)}</span>
            </div>`;
          })
          .join("");
      }

      async function refreshDynamicSections(period) {
        try {
          const snap = await fetchSnapshot(period);
          const eng = snap.engagement || {};
          renderUserList("mostActiveUsersList", eng.most_active, "most_active");
          renderUserList("highestBalancesList", eng.highest_balances, "highest_balances");
          renderUserList("frequentTradersList", eng.frequent_traders, "frequent_traders");
          renderPairs(snap.pairs || []);
          renderFinancial(snap.financial || {});
          renderInsights(snap.insights || []);
          renderFeed(snap.feed || []);
        } catch (e) {
          // Keep UI stable if API fails.
        }
      }

      let charts = {
        sparklines: {},
        revenue: null,
        users: null,
        volume: null,
        dvsw: null,
        pie: null,
      };

      function initCharts() {
        // Sparklines (keep simple mini-trends derived from "today" series)
        const base = ANALYTICS.series.today;
        const sparkUsers = (base.users?.data || []).slice(-7);
        const sparkRevenue = (base.revenue?.data || []).slice(-7);
        const sparkDep = (base.dvsw?.deposits || []).slice(-7);
        const sparkWd = (base.dvsw?.withdrawals || []).slice(-7);
        const sparkVol = (base.volume?.data || []).slice(-7);

        charts.sparklines.users = new Chart(document.getElementById("sparklineUsers"), {
          type: "line",
          data: {
            datasets: [
              {
                data: sparkUsers.length ? sparkUsers : [0, 0, 0, 0, 0, 0, 0],
                borderColor: "#3b82f6",
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
        charts.sparklines.active = new Chart(document.getElementById("sparklineActive"), {
          type: "line",
          data: {
            datasets: [
              {
                data: sparkUsers.length ? sparkUsers.map((v) => Math.max(0, Math.round(v * 0.35))) : [0, 0, 0, 0, 0, 0, 0],
                borderColor: "#10b981",
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
        charts.sparklines.deposits = new Chart(document.getElementById("sparklineDeposits"), {
          type: "line",
          data: {
            datasets: [
              {
                data: sparkDep.length ? sparkDep : [0, 0, 0, 0, 0, 0, 0],
                borderColor: "#22c55e",
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
        charts.sparklines.withdrawals = new Chart(document.getElementById("sparklineWithdrawals"), {
          type: "line",
          data: {
            datasets: [
              {
                data: sparkWd.length ? sparkWd : [0, 0, 0, 0, 0, 0, 0],
                borderColor: "#ef4444",
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
        charts.sparklines.volume = new Chart(document.getElementById("sparklineVolume"), {
          type: "line",
          data: {
            datasets: [
              {
                data: sparkVol.length ? sparkVol : [0, 0, 0, 0, 0, 0, 0],
                borderColor: "#a78bfa",
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
        charts.sparklines.revenue = new Chart(document.getElementById("sparklineRevenue"), {
          type: "line",
          data: {
            datasets: [
              {
                data: sparkRevenue.length ? sparkRevenue : [0, 0, 0, 0, 0, 0, 0],
                borderColor: "#f59e0b",
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

        // Main charts (default: today)
        const s = ANALYTICS.series.today;

        charts.revenue = new Chart(document.getElementById("revenueChart"), {
          type: "line",
          data: {
            labels: s.revenue?.labels || [],
            datasets: [
              {
                label: "Revenue",
                data: s.revenue?.data || [],
                borderColor: "#22c55e",
                backgroundColor: "rgba(34,197,94,0.1)",
                tension: 0.4,
                fill: true,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: {
                grid: { color: "rgba(255,255,255,0.1)" },
                ticks: {
                  color: "#7f8ea3",
                  callback: (value) => formatCompactUSD(value),
                },
              },
              x: { grid: { display: false }, ticks: { color: "#7f8ea3" } },
            },
          },
        });

        charts.users = new Chart(document.getElementById("userGrowthChart"), {
          type: "line",
          data: {
            labels: s.users?.labels || [],
            datasets: [
              {
                label: "New Users",
                data: s.users?.data || [],
                borderColor: "#3b82f6",
                backgroundColor: "rgba(59,130,246,0.1)",
                tension: 0.4,
                fill: true,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: {
                grid: { color: "rgba(255,255,255,0.1)" },
                ticks: { color: "#7f8ea3" },
              },
              x: { grid: { display: false }, ticks: { color: "#7f8ea3" } },
            },
          },
        });

        charts.volume = new Chart(document.getElementById("volumeChart"), {
          type: "bar",
          data: {
            labels: s.volume?.labels || [],
            datasets: [
              {
                label: "Volume",
                data: s.volume?.data || [],
                backgroundColor: "#a78bfa",
                borderRadius: 8,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: {
                grid: { color: "rgba(255,255,255,0.1)" },
                ticks: {
                  color: "#7f8ea3",
                  callback: (value) => formatCompactUSD(value),
                },
              },
              x: { grid: { display: false }, ticks: { color: "#7f8ea3" } },
            },
          },
        });

        charts.dvsw = new Chart(document.getElementById("depositWithdrawalChart"), {
          type: "line",
          data: {
            labels: s.dvsw?.labels || [],
            datasets: [
              {
                label: "Deposits",
                data: s.dvsw?.deposits || [],
                borderColor: "#10b981",
                tension: 0.4,
              },
              {
                label: "Withdrawals",
                data: s.dvsw?.withdrawals || [],
                borderColor: "#ef4444",
                tension: 0.4,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: {
                grid: { color: "rgba(255,255,255,0.1)" },
                ticks: {
                  color: "#7f8ea3",
                  callback: (value) => formatCompactUSD(value),
                },
              },
              x: { grid: { display: false }, ticks: { color: "#7f8ea3" } },
            },
          },
        });

        charts.pie = new Chart(document.getElementById("transactionPieChart"), {
          type: "doughnut",
          data: {
            labels: ["Deposits", "Withdrawals", "Trades"],
            datasets: [
              {
                data: [1, 1, 1],
                backgroundColor: ["#10b981", "#ef4444", "#3b82f6"],
                borderWidth: 0,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: "70%",
          },
        });
      }

      function updatePieLegend(pie) {
        const dep = Number(pie.deposits) || 0;
        const wd = Number(pie.withdrawals) || 0;
        const tr = Number(pie.trades) || 0;
        const total = dep + wd + tr;
        function pct(v) {
          if (total <= 0) return "0%";
          return Math.round((v / total) * 100) + "%";
        }
        document.getElementById("pieDepositsPct").textContent = pct(dep);
        document.getElementById("pieWithdrawalsPct").textContent = pct(wd);
        document.getElementById("pieTradesPct").textContent = pct(tr);
      }

      function applyPeriod(period) {
        const key = periodKey(period);
        const s = (ANALYTICS.series && ANALYTICS.series[key]) ? ANALYTICS.series[key] : ANALYTICS.series.today;

        if (charts.revenue) {
          charts.revenue.data.labels = s.revenue?.labels || [];
          charts.revenue.data.datasets[0].data = s.revenue?.data || [];
          charts.revenue.update();
        }
        if (charts.users) {
          charts.users.data.labels = s.users?.labels || [];
          charts.users.data.datasets[0].data = s.users?.data || [];
          charts.users.update();
        }
        if (charts.volume) {
          charts.volume.data.labels = s.volume?.labels || [];
          charts.volume.data.datasets[0].data = s.volume?.data || [];
          charts.volume.update();
        }
        if (charts.dvsw) {
          charts.dvsw.data.labels = s.dvsw?.labels || [];
          charts.dvsw.data.datasets[0].data = s.dvsw?.deposits || [];
          charts.dvsw.data.datasets[1].data = s.dvsw?.withdrawals || [];
          charts.dvsw.update();
        }
        if (charts.pie) {
          const dep = Number(s.pie?.deposits) || 0;
          const wd = Number(s.pie?.withdrawals) || 0;
          const tr = Number(s.pie?.trades) || 0;
          charts.pie.data.datasets[0].data = [dep, wd, tr];
          charts.pie.update();
          updatePieLegend(s.pie || {});
        }
        setDateRangeLabel(period);
        currentPeriod = period;
        refreshDynamicSections(period);
      }

      // Time filter change
      window.changeTimeFilter = function (period, btnEl) {
        document.querySelectorAll(".time-btn").forEach((btn) => btn.classList.remove("active"));
        if (btnEl) btnEl.classList.add("active");
        applyPeriod(period);
      };

      // Generate world map grid
      function generateWorldMap() {
        const map = document.getElementById("worldMap");
        map.innerHTML = "";
        for (let i = 0; i < 120; i++) {
          const heat = Math.floor(Math.random() * 5) + 1;
          const item = document.createElement("div");
          item.className = "map-grid-item";
          item.setAttribute("data-heat", heat);
          map.appendChild(item);
        }
      }

      // Real-time updates (poll)
      setInterval(async () => {
        try {
          const feed = await fetchFeed(5);
          renderFeed(feed);
        } catch (e) {
          // ignore
        }
      }, 8000);

      setInterval(() => {
        refreshDynamicSections(currentPeriod);
      }, 30000);

      // AI Assistant
      document.getElementById("aiBtn").addEventListener("click", () => {
        alert(
          "🤖 AI Analytics Assistant:\n\n• Revenue up 7.8% this week\n• User growth accelerating (+12.5%)\n• Trading volume spike detected\n• 3 suspicious patterns identified\n• Recommended: Review withdrawal anomaly",
        );
      });

      // Initialize
      generateWorldMap();
      initCharts();
      setKpis(ANALYTICS.kpis || {});
      applyPeriod("today");

      // Exports
      document.getElementById("exportPdfBtn").addEventListener("click", () => {
        window.print();
      });
      document.getElementById("exportCsvBtn").addEventListener("click", () => {
        const activeBtn = document.querySelector(".time-btn.active");
        const period = activeBtn ? (activeBtn.textContent || "").trim().toLowerCase() : "today";
        const p = period.includes("30") ? "30d" : period.includes("7") ? "7d" : "today";
        const key = periodKey(p);
        const s = (ANALYTICS.series && ANALYTICS.series[key]) ? ANALYTICS.series[key] : ANALYTICS.series.today;

        let csv = "Metric,Value\n";
        const k = ANALYTICS.kpis || {};
        csv += `Total Users,${Number(k.total_users) || 0}\n`;
        csv += `Active Users,${Number(k.active_users) || 0}\n`;
        csv += `Total Deposits USD,${Number(k.total_deposits_usd) || 0}\n`;
        csv += `Total Withdrawals USD,${Number(k.total_withdrawals_usd) || 0}\n`;
        csv += `Trading Volume USD,${Number(k.trading_volume_usd) || 0}\n`;
        csv += `Revenue USD,${Number(k.revenue_usd) || 0}\n\n`;

        csv += "Revenue Series\nDate,Revenue\n";
        (s.revenue?.labels || []).forEach((lab, i) => {
          const v = (s.revenue?.data || [])[i] ?? 0;
          csv += `${lab},${v}\n`;
        });
        csv += "\nVolume Series\nBucket,Volume\n";
        (s.volume?.labels || []).forEach((lab, i) => {
          const v = (s.volume?.data || [])[i] ?? 0;
          csv += `${lab},${v}\n`;
        });

        const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `crypterra_analytics_${key}.csv`;
        link.click();
      });

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
