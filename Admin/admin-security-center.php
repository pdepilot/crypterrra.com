<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
require_once dirname(__DIR__) . '/Userprofile/includes/user_db.php';
require_once __DIR__ . '/includes/admin_security_settings.php';
require_once __DIR__ . '/includes/admin_security_center_data.php';

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

$adminSecurityCsrf = admin_csrf_token();
$adminSecuritySettings = admin_security_defaults();
$securityCenterPayload = [
  'stats' => [
    'active_threats' => 0,
    'blocked_attacks' => 0,
    'suspicious' => 0,
    'security_score' => 85,
    'risk_score' => 50,
    'threat_level' => 'Medium',
    'threat_level_class' => 'risk-medium',
    'anomalies' => 0,
  ],
  'threat_feed' => [],
  'user_risks' => [],
  'login_activity' => [],
  'security_logs' => [],
  'chart' => [
    'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
    'critical' => [0, 0, 0, 0, 0, 0, 0],
    'warning' => [0, 0, 0, 0, 0, 0, 0],
  ],
];
$adminDisplayName = isset($_SESSION['admin_username']) && is_string($_SESSION['admin_username'])
  ? (string)$_SESSION['admin_username']
  : 'Admin';
$adminDisplayEmail = isset($_SESSION['admin_email']) && is_string($_SESSION['admin_email'])
  ? (string)$_SESSION['admin_email']
  : '';
$adminInitial = $adminDisplayName !== ''
  ? strtoupper(function_exists('mb_substr') ? mb_substr($adminDisplayName, 0, 2, 'UTF-8') : substr($adminDisplayName, 0, 2))
  : 'AD';

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  $adminSecuritySettings = admin_get_security_settings($db);
  $securityCenterPayload = admin_security_center_payload($db, (string)$DB_NAME);
  $db->close();
} catch (Throwable $e) {
  error_log('[admin-security-center] ' . $e->getMessage());
}

$secStats = $securityCenterPayload['stats'];
$threatProgressPct = min(100, max(8, (int)($secStats['active_threats'] ?? 0) * 7));
$blockedAttacksN = (int)($secStats['blocked_attacks'] ?? 0);
$blockedPct = min(100, max(10, $blockedAttacksN > 0 ? min(100, 22 + (int)ceil($blockedAttacksN / 40)) : 12));
$suspPct = min(100, max(8, (int)($secStats['suspicious'] ?? 0) * 3));
$scorePct = min(100, max(0, (int)($secStats['security_score'] ?? 85)));
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · Admin · Security Center</title>
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

      /* Security Overview Cards */
      .analytics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .analytics-icon.critical {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .analytics-icon.warning {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .analytics-icon.success {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .analytics-icon.info {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }

      .analytics-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text);
        line-height: 1.2;
      }

      .analytics-label {
        color: var(--text-muted);
        font-size: 0.85rem;
      }

      .progress-bar {
        width: 100%;
        height: 6px;
        background: var(--surface-light);
        border-radius: 10px;
        margin-top: 0.5rem;
        overflow: hidden;
      }

      .progress-fill {
        height: 100%;
        background: var(--primary);
        border-radius: 10px;
        width: 0%;
        transition: width 0.3s;
      }

      /* Live Threat Feed */
      .threat-feed {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
      }

      .threat-feed h3 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.2rem;
        color: var(--primary);
      }

      .threat-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        margin-bottom: 0.5rem;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        animation:
          slideIn 0.3s,
          pulse 2s infinite;
        flex-wrap: wrap;
      }

      @media (max-width: 640px) {
        .threat-item {
          flex-direction: column;
          align-items: flex-start;
        }
        .threat-time {
          margin-left: 0 !important;
          align-self: flex-end;
        }
      }

      .threat-item.critical {
        border-left: 4px solid var(--danger);
      }

      .threat-item.warning {
        border-left: 4px solid var(--warning);
      }

      .threat-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
      }

      .threat-icon.critical {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .threat-icon.warning {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .threat-content {
        flex: 1;
      }

      .threat-content h4 {
        font-size: 1rem;
        margin-bottom: 0.2rem;
      }

      .threat-content p {
        font-size: 0.85rem;
        color: var(--text-secondary);
      }

      .threat-time {
        color: var(--text-muted);
        font-size: 0.8rem;
      }

      /* AI Threat Detection Panel */
      .ai-panel {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
      }

      .ai-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
      }

      .ai-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
        font-size: 1.5rem;
        flex-shrink: 0;
      }

      .ai-status {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
      }

      .scanning-animation {
        display: flex;
        gap: 4px;
        margin-top: 0.5rem;
      }

      .scanning-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary);
        animation: scanning 1.5s infinite;
      }

      .scanning-dot:nth-child(2) {
        animation-delay: 0.2s;
      }
      .scanning-dot:nth-child(3) {
        animation-delay: 0.4s;
      }
      .scanning-dot:nth-child(4) {
        animation-delay: 0.6s;
      }
      .scanning-dot:nth-child(5) {
        animation-delay: 0.8s;
      }

      @keyframes scanning {
        0%,
        100% {
          opacity: 0.3;
          transform: scale(1);
        }
        50% {
          opacity: 1;
          transform: scale(1.2);
        }
      }

      .risk-score {
        display: inline-block;
        padding: 0.3rem 1rem;
        border-radius: 40px;
        font-weight: 600;
        margin-left: 0.5rem;
      }

      .risk-low {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .risk-medium {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .risk-high {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
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

      @media (max-width: 768px) {
        .control-filters {
          width: 100%;
        }
        .filter-select {
          width: 100%;
        }
        .control-filters button {
          width: 100%;
        }
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
        background: var(--warning);
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

      /* User Risk Table */
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
        min-width: 1000px;
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
        text-align: left;
      }

      td {
        padding: 1rem 0.8rem;
        border-bottom: 1px solid var(--glass-edge);
        text-align: left;
      }

      tr {
        transition: 0.2s;
      }

      tr:hover {
        background: var(--primary-soft);
      }

      .risk-score-badge {
        padding: 0.25rem 0.8rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
      }

      .risk-score-0-30 {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .risk-score-31-60 {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .risk-score-61-100 {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
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

      .status-locked {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      .status-suspended {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
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

      /* Modal (replace browser alerts/prompts) */
      .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 24px;
        z-index: 1000;
      }
      .modal-overlay.show {
        display: flex;
      }
      .modal-card {
        width: min(560px, 100%);
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 26px;
        box-shadow: var(--shadow);
        padding: 18px 18px 16px 18px;
      }
      .modal-title {
        font-weight: 700;
        font-size: 1.05rem;
        margin-bottom: 6px;
      }
      .modal-message {
        color: var(--text-secondary);
        font-size: 0.92rem;
        line-height: 1.4;
        margin-bottom: 12px;
      }
      .modal-input {
        width: 100%;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 16px;
        padding: 10px 12px;
        color: var(--text);
        outline: none;
        margin-bottom: 12px;
      }
      .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 4px;
      }

      /* Access Control Section */
      .access-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin: 1.5rem 0;
      }

      .access-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
      }

      .access-card h4 {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1rem;
        color: var(--primary);
      }

      .access-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.8rem 0;
        border-bottom: 1px solid var(--glass-edge);
        flex-wrap: wrap;
        gap: 0.5rem;
      }

      @media (max-width: 480px) {
        .access-item {
          flex-direction: column;
          align-items: flex-start;
        }
      }

      .access-item:last-child {
        border-bottom: none;
      }

      /* Toggle Switch */
      .market-toggle {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 30px;
      }

      .market-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
      }

      .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: var(--danger);
        transition: 0.3s;
        border-radius: 34px;
      }

      .toggle-slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
      }

      input:checked + .toggle-slider {
        background-color: var(--success);
      }

      input:checked + .toggle-slider:before {
        transform: translateX(30px);
      }

      /* Security Settings */
      .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin: 1.5rem 0;
      }

      .setting-item {
        background: var(--glass-bg);
        border-radius: 40px;
        padding: 1rem;
      }

      .setting-item label {
        display: block;
        color: var(--text-muted);
        font-size: 0.85rem;
        margin-bottom: 0.3rem;
      }

      .setting-item input {
        width: 100%;
        background: transparent;
        border: none;
        color: var(--text);
        font-size: 1.2rem;
        font-weight: 600;
      }

      /* Login Activity Table */
      .login-table {
        margin: 1.5rem 0;
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

      /* Security Logs */
      .log-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.8rem;
        border-bottom: 1px solid var(--glass-edge);
        font-size: 0.9rem;
        flex-wrap: wrap;
      }

      @media (max-width: 640px) {
        .log-item {
          flex-direction: column;
          align-items: flex-start;
        }
        .log-time {
          min-width: auto;
        }
      }

      .log-item:last-child {
        border-bottom: none;
      }

      .log-time {
        color: var(--text-muted);
        min-width: 80px;
      }

      .log-action {
        flex: 1;
      }

      .log-result.success {
        color: var(--success);
      }

      .log-result.failed {
        color: var(--danger);
      }

      /* Two column layout */
      .two-column {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin: 1.5rem 0;
      }

      @media (max-width: 768px) {
        .two-column {
          grid-template-columns: 1fr;
        }
      }

      /* Chart container */
      .chart-container {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
      }

      .chart-wrapper {
        height: 200px;
        position: relative;
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
        .analytics-value {
          font-size: 1.5rem;
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
      <!-- SIDEBAR (active on Security Center) -->
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
          <div class="admin-avatar"><?php echo htmlspecialchars($adminInitial, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
          <div class="admin-details">
            <h4><?php echo htmlspecialchars($adminDisplayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></h4>
            <p><?php echo htmlspecialchars($adminDisplayEmail !== '' ? $adminDisplayEmail : '—', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></p>
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
          <a href="./admin-security-center.php" class="sidebar-link active"
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
              <span class="admin-short"><?php echo htmlspecialchars($adminInitial, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
            </div>
          </div>
        </div>

        <!-- SECURITY CENTER CONTENT -->
        <div class="content">
          <!-- Page Header -->
          <div class="page-header" data-aos="fade-up">
            <h1>Security Center</h1>
            <p>Monitor and protect the platform in real time.</p>
            <div class="breadcrumb">
              <a href="#">Admin</a> <span>/</span>
              <span style="color: var(--primary)">Security Center</span>
            </div>
          </div>

          <!-- Security Overview Cards -->
          <div class="analytics-grid" data-aos="fade-up">
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon critical"
                  ><i class="fa-solid fa-bolt"></i
                ></span>
              </div>
              <div class="analytics-value" id="activeThreats"><?php echo (int)($secStats['active_threats'] ?? 0); ?></div>
              <div class="analytics-label">Active Threats</div>
              <div class="progress-bar">
                <div
                  class="progress-fill"
                  id="threatProgress"
                  style="width: <?php echo (int)$threatProgressPct; ?>%"
                ></div>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon info"
                  ><i class="fa-solid fa-shield-halved"></i
                ></span>
              </div>
              <div class="analytics-value" id="blockedAttacks"><?php echo number_format((int)($secStats['blocked_attacks'] ?? 0)); ?></div>
              <div class="analytics-label">Blocked Attacks</div>
              <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo (int)$blockedPct; ?>%"></div>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon warning"
                  ><i class="fa-solid fa-triangle-exclamation"></i
                ></span>
              </div>
              <div class="analytics-value" id="suspiciousActivities"><?php echo number_format((int)($secStats['suspicious'] ?? 0)); ?></div>
              <div class="analytics-label">Suspicious Activities</div>
              <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo (int)$suspPct; ?>%"></div>
              </div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon success"
                  ><i class="fa-solid fa-chart-line"></i
                ></span>
              </div>
              <div class="analytics-value" id="securityScore"><?php echo (int)($secStats['security_score'] ?? 0); ?>%</div>
              <div class="analytics-label">Security Score</div>
              <div class="progress-bar">
                <div class="progress-fill" id="securityScoreBar" style="width: <?php echo (int)$scorePct; ?>%"></div>
              </div>
            </div>
          </div>

          <!-- Live Threat Feed -->
          <div class="threat-feed" data-aos="fade-up">
            <h3><i class="fa-solid fa-rss"></i> Live Threat Feed</h3>
            <div id="threatFeed"></div>
          </div>

          <!-- AI Threat Detection Panel -->
          <div class="ai-panel" data-aos="fade-up">
            <div class="ai-header">
              <div class="ai-icon"><i class="fa-solid fa-microchip"></i></div>
              <div>
                <h3>AI Threat Detection</h3>
                <div class="ai-status">
                  <span
                    >Status:
                    <span style="color: var(--success)">Active</span></span
                  >
                  <span>|</span>
                  <span
                    >Scanning: <span id="aiScanning">User behavior</span></span
                  >
                </div>
                <div class="scanning-animation">
                  <div class="scanning-dot"></div>
                  <div class="scanning-dot"></div>
                  <div class="scanning-dot"></div>
                  <div class="scanning-dot"></div>
                  <div class="scanning-dot"></div>
                </div>
              </div>
            </div>
            <div style="display: flex; gap: 2rem; flex-wrap: wrap">
              <div>
                <span style="color: var(--text-muted)">Risk Score</span>
                <div
                  style="
                    font-size: 2.5rem;
                    font-weight: 700;
                    color: var(--primary);
                  "
                  id="riskScore"
                >
                  <?php echo (int)($secStats['risk_score'] ?? 0); ?>
                </div>
              </div>
              <div>
                <span style="color: var(--text-muted)">Threat Level</span>
                <div>
                  <span class="risk-score-badge <?php echo htmlspecialchars((string)($secStats['threat_level_class'] ?? 'risk-medium'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" id="threatLevel"
                    ><?php echo htmlspecialchars((string)($secStats['threat_level'] ?? 'Medium'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span
                  >
                </div>
              </div>
              <div>
                <span style="color: var(--text-muted)">Anomalies Detected</span>
                <div
                  style="font-size: 1.5rem; color: var(--danger)"
                  id="anomalies"
                >
                  12
                </div>
              </div>
            </div>
          </div>

          <!-- Access Control & Settings -->
          <div class="two-column" data-aos="fade-up">
            <!-- Access Control -->
            <div class="access-card">
              <h4><i class="fa-solid fa-lock"></i> Access Control</h4>
              <div class="access-item">
                <span>Admin 2FA</span>
                <label class="market-toggle">
                  <input type="checkbox" id="admin2FA" <?php echo !empty($adminSecuritySettings['admin_2fa_enabled']) ? 'checked' : ''; ?> />
                  <span class="toggle-slider"></span>
                </label>
              </div>
              <div class="access-item">
                <span>Role-Based Access</span>
                <select class="filter-select" style="width: 120px">
                  <option>Admin</option>
                  <option>Super Admin</option>
                  <option>Viewer</option>
                </select>
              </div>
              <div class="access-item">
                <span>IP Restriction</span>
                <button class="btn-outline" style="padding: 0.2rem 0.8rem">
                  Configure
                </button>
              </div>
              <div class="access-item">
                <span>Session Timeout</span>
                <input
                  id="accessSessionTimeoutMin"
                  type="number"
                  min="5"
                  max="480"
                  value="<?php echo (int)($adminSecuritySettings['access_session_timeout_min'] ?? 30); ?>"
                  style="
                    width: 60px;
                    background: var(--glass-bg);
                    border: 1px solid var(--glass-edge);
                    border-radius: 40px;
                    padding: 0.2rem 0.5rem;
                  "
                />
                min
              </div>
            </div>

            <!-- Account Control -->
            <div class="access-card">
              <h4><i class="fa-solid fa-user-lock"></i> Account Control</h4>
              <div class="access-item">
                <span>Freeze Account</span>
                <button class="btn-warning" style="padding: 0.2rem 0.8rem">
                  Freeze
                </button>
              </div>
              <div class="access-item">
                <span>Enable 2FA</span>
                <label class="market-toggle">
                  <input type="checkbox" id="user2FA" <?php echo !empty($adminSecuritySettings['default_user_2fa_enabled']) ? 'checked' : ''; ?> />
                  <span class="toggle-slider"></span>
                </label>
              </div>
            </div>
          </div>

          <!-- Security Settings -->
          <div class="access-card" data-aos="fade-up">
            <h4><i class="fa-solid fa-gear"></i> Security Settings</h4>
            <div class="settings-grid">
              <div class="setting-item">
                <label>Max Login Attempts</label>
                <input id="maxLoginAttempts" type="number" value="<?php echo (int)($adminSecuritySettings['max_login_attempts'] ?? 5); ?>" min="1" max="20" />
              </div>
              <div class="setting-item">
                <label>Withdrawal Limit ($)</label>
                <input id="withdrawalLimitUsd" type="number" value="<?php echo (int)($adminSecuritySettings['withdrawal_limit_usd'] ?? 10000); ?>" step="1000" min="0" />
              </div>
              <div class="setting-item">
                <label>Auto-Lock After (min)</label>
                <input id="autoLockMin" type="number" value="<?php echo (int)($adminSecuritySettings['auto_lock_min'] ?? 15); ?>" min="1" max="240" />
              </div>
              <div class="setting-item">
                <label>Session Timeout (min)</label>
                <input id="userSessionTimeoutMin" type="number" value="<?php echo (int)($adminSecuritySettings['user_session_timeout_min'] ?? 30); ?>" min="5" max="480" />
              </div>
            </div>
            <button type="button" class="btn-primary" id="saveAdminSecurityBtn">Save Settings</button>
          </div>

          <!-- Search & Filters -->
          <div class="control-bar" data-aos="fade-up">
            <div class="control-search">
              <i class="fa-solid fa-search"></i>
              <input
                type="text"
                id="userSearch"
                placeholder="Search users by email or name..."
              />
            </div>
            <div class="control-filters">
              <select class="filter-select" id="riskFilter">
                <option>All Risk Levels</option>
                <option>Low Risk</option>
                <option>Medium Risk</option>
                <option>High Risk</option>
              </select>
              <button class="btn-outline">
                <i class="fa-regular fa-file-pdf"></i> Export Logs
              </button>
              <button class="btn-outline">
                <i class="fa-regular fa-file-excel"></i> CSV
              </button>
            </div>
          </div>

          <!-- User Risk Monitoring Table -->
          <div class="table-wrapper" data-aos="fade-up">
            <table>
              <thead>
                <tr>
                  <th>User</th>
                  <th>Email</th>
                  <th>Risk Score</th>
                  <th>Last Activity</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="userRiskTable">
                <!-- JS will populate -->
              </tbody>
            </table>
          </div>

          <!-- Login Activity Tracker -->
          <div class="access-card login-table" data-aos="fade-up">
            <h4><i class="fa-solid fa-clock"></i> Recent Login Activity</h4>
            <div class="table-wrapper" style="margin: 1rem 0 0 0">
              <table style="min-width: 800px">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Location</th>
                    <th>Device</th>
                    <th>IP Address</th>
                    <th>Time</th>
                  </tr>
                </thead>
                <tbody id="loginActivityBody"></tbody>
              </table>
            </div>
          </div>

          <!-- Security Analytics & Logs -->
          <div class="two-column" data-aos="fade-up">
            <!-- Threat Trends Chart -->
            <div class="chart-container">
              <h4 style="margin-bottom: 1rem">Threat Trends (Last 24h)</h4>
              <div class="chart-wrapper">
                <canvas id="threatChart"></canvas>
              </div>
            </div>

            <!-- Security Logs -->
            <div class="access-card">
              <h4><i class="fa-solid fa-history"></i> Security Logs</h4>
              <div id="securityLogs"></div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- AI Assistant -->
    <div class="ai-assistant" id="aiBtn">
      <i class="fa-solid fa-robot"></i>
    </div>

    <!-- Shared modal for actions (no browser alerts) -->
    <div class="modal-overlay" id="adminModalOverlay" aria-hidden="true">
      <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="adminModalTitle">
        <div class="modal-title" id="adminModalTitle">Confirm</div>
        <div class="modal-message" id="adminModalMessage"></div>
        <textarea class="modal-input" id="adminModalReason" rows="3" style="display:none" placeholder="Optional reason..."></textarea>
        <div class="modal-actions">
          <button type="button" class="btn-outline" id="adminModalCancelBtn">Cancel</button>
          <button type="button" class="btn-primary" id="adminModalOkBtn">OK</button>
        </div>
      </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <?php
    $jsSec = json_encode($adminSecuritySettings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $jsCsrf = json_encode($adminSecurityCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $jsCenter = json_encode($securityCenterPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <script>
      window.__ADMIN_SEC__ = <?php echo $jsSec; ?>;
      window.__ADMIN_CSRF__ = <?php echo $jsCsrf; ?>;
      window.__SECURITY_CENTER__ = <?php echo $jsCenter; ?>;
    </script>
    <script>
      AOS.init({ duration: 700, once: true });

      const ADMIN_SECURITY_API = "api/security_api.php";
      const SECURITY_CENTER_API = "api/security_center_api.php";
      let adminSecCsrf =
        typeof window.__ADMIN_CSRF__ === "string" ? window.__ADMIN_CSRF__ : "";

      function applyAdminSecuritySettings(s) {
        if (!s || typeof s !== "object") return;
        const a2 = document.getElementById("admin2FA");
        if (a2) a2.checked = !!s.admin_2fa_enabled;
        const u2 = document.getElementById("user2FA");
        if (u2) u2.checked = !!s.default_user_2fa_enabled;
        const st = document.getElementById("accessSessionTimeoutMin");
        if (st) st.value = String(s.access_session_timeout_min ?? 30);
        const mx = document.getElementById("maxLoginAttempts");
        if (mx) mx.value = String(s.max_login_attempts ?? 5);
        const wl = document.getElementById("withdrawalLimitUsd");
        if (wl) wl.value = String(Math.round(s.withdrawal_limit_usd ?? 10000));
        const al = document.getElementById("autoLockMin");
        if (al) al.value = String(s.auto_lock_min ?? 15);
        const ut = document.getElementById("userSessionTimeoutMin");
        if (ut) ut.value = String(s.user_session_timeout_min ?? 30);
      }

      async function refreshAdminSecurityFromServer() {
        try {
          const r = await fetch(ADMIN_SECURITY_API, {
            method: "GET",
            credentials: "same-origin",
          });
          const j = await r.json().catch(() => ({}));
          if (j && j.ok && j.settings) {
            applyAdminSecuritySettings(j.settings);
            window.__ADMIN_SEC__ = j.settings;
          }
        } catch (_) {
          /* ignore */
        }
      }

      async function saveAdminSecuritySettings() {
        const btn = document.getElementById("saveAdminSecurityBtn");
        if (btn) btn.disabled = true;
        const body = {
          csrf_token: adminSecCsrf,
          admin_2fa_enabled: !!document.getElementById("admin2FA")?.checked,
          default_user_2fa_enabled: !!document.getElementById("user2FA")?.checked,
          access_session_timeout_min: parseInt(
            document.getElementById("accessSessionTimeoutMin")?.value || "30",
            10,
          ),
          max_login_attempts: parseInt(
            document.getElementById("maxLoginAttempts")?.value || "5",
            10,
          ),
          withdrawal_limit_usd: parseFloat(
            document.getElementById("withdrawalLimitUsd")?.value || "0",
          ),
          auto_lock_min: parseInt(
            document.getElementById("autoLockMin")?.value || "15",
            10,
          ),
          user_session_timeout_min: parseInt(
            document.getElementById("userSessionTimeoutMin")?.value || "30",
            10,
          ),
        };
        try {
          const r = await fetch(ADMIN_SECURITY_API, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify(body),
          });
          const j = await r.json().catch(() => ({}));
          if (j && j.ok && j.settings) {
            applyAdminSecuritySettings(j.settings);
            window.__ADMIN_SEC__ = j.settings;
            adminSecCsrf = j.csrf || adminSecCsrf;
            infoModal("Security settings saved.", "Saved");
          } else {
            infoModal(j.error || "Could not save settings.", "Error");
          }
        } catch (e) {
          infoModal("Network error while saving.", "Error");
        } finally {
          if (btn) btn.disabled = false;
        }
      }

      document
        .getElementById("saveAdminSecurityBtn")
        ?.addEventListener("click", () => {
          saveAdminSecuritySettings();
        });

      if (window.__ADMIN_SEC__) {
        applyAdminSecuritySettings(window.__ADMIN_SEC__);
      }
      refreshAdminSecurityFromServer();

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

      const USER_ACTION_API = "api/user_security_actions.php";

      // Modal helpers (replace alert/prompt)
      const modalOverlay = document.getElementById("adminModalOverlay");
      const modalTitleEl = document.getElementById("adminModalTitle");
      const modalMsgEl = document.getElementById("adminModalMessage");
      const modalReasonEl = document.getElementById("adminModalReason");
      const modalCancelBtn = document.getElementById("adminModalCancelBtn");
      const modalOkBtn = document.getElementById("adminModalOkBtn");

      function openModal({ title, message, showReason, okText, cancelText }) {
        return new Promise((resolve) => {
          modalTitleEl.textContent = title || "Confirm";
          modalMsgEl.textContent = message || "";
          modalReasonEl.value = "";
          modalReasonEl.style.display = showReason ? "block" : "none";
          modalOkBtn.textContent = okText || "OK";
          modalCancelBtn.textContent = cancelText || "Cancel";

          modalOverlay.classList.add("show");
          modalOverlay.setAttribute("aria-hidden", "false");
          if (showReason) {
            setTimeout(() => modalReasonEl.focus(), 0);
          } else {
            setTimeout(() => modalOkBtn.focus(), 0);
          }

          const cleanup = () => {
            modalOverlay.classList.remove("show");
            modalOverlay.setAttribute("aria-hidden", "true");
            modalOkBtn.onclick = null;
            modalCancelBtn.onclick = null;
            modalOverlay.onclick = null;
            window.onkeydown = null;
          };

          modalOkBtn.onclick = () => {
            const reason = modalReasonEl.style.display !== "none" ? modalReasonEl.value : "";
            cleanup();
            resolve({ ok: true, reason: reason || "" });
          };
          modalCancelBtn.onclick = () => {
            cleanup();
            resolve({ ok: false, reason: "" });
          };
          modalOverlay.onclick = (e) => {
            if (e.target === modalOverlay) {
              cleanup();
              resolve({ ok: false, reason: "" });
            }
          };
          window.onkeydown = (e) => {
            if (e.key === "Escape") {
              cleanup();
              resolve({ ok: false, reason: "" });
            }
          };
        });
      }

      async function infoModal(message, title) {
        await openModal({
          title: title || "Info",
          message: message || "",
          showReason: false,
          okText: "OK",
          cancelText: "Close",
        });
      }

      async function adminUserAction(action, identifier, reason) {
        const body = {
          csrf_token: adminSecCsrf,
          action,
          identifier,
        };
        if (reason) body.reason = reason;

        const r = await fetch(USER_ACTION_API, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify(body),
        });
        const j = await r.json().catch(() => ({}));
        if (j && typeof j.csrf === "string") adminSecCsrf = j.csrf;
        if (!j || !j.ok) throw new Error(j.error || "Request failed");
        return j;
      }

      function secEsc(s) {
        return String(s ?? "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;");
      }

      function getSecurityPayload() {
        return window.__SECURITY_CENTER__ && typeof window.__SECURITY_CENTER__ === "object"
          ? window.__SECURITY_CENTER__
          : {};
      }

      let lastActivityRefreshAt = 0;
      async function refreshSecurityCenterRealtime() {
        const now = Date.now();
        // avoid overlapping calls
        if (now - lastActivityRefreshAt < 2500) return;
        lastActivityRefreshAt = now;
        try {
          const r = await fetch(SECURITY_CENTER_API, {
            method: "GET",
            credentials: "same-origin",
            headers: { "Cache-Control": "no-cache" },
          });
          const j = await r.json().catch(() => ({}));
          if (!j || !j.ok || !j.payload) return;
          if (j && typeof j.csrf === "string") adminSecCsrf = j.csrf;
          window.__SECURITY_CENTER__ = j.payload;
          // Re-render only the dynamic sections
          renderThreatFeedFromPayload();
          renderSecurityLogsFromPayload();
          renderLoginActivityFromPayload();
          applyOverviewFromPayload();
          renderUserRiskTable();
          initCharts();
        } catch (_) {
          // ignore network errors (keep last rendered data)
        }
      }

      function renderThreatFeedFromPayload() {
        const feed = document.getElementById("threatFeed");
        if (!feed) return;
        const items = getSecurityPayload().threat_feed || [];
        if (!items.length) {
          feed.innerHTML =
            '<div style="padding:1rem;color:var(--text-muted)">No security events in the queue.</div>';
          return;
        }
        feed.innerHTML = items
          .map((it) => {
            const ty = it.type === "critical" ? "critical" : "warning";
            const icon =
              ty === "critical" ? "fa-skull" : "fa-triangle-exclamation";
            return `
            <div class="threat-item ${ty}">
              <div class="threat-icon ${ty}"><i class="fa-solid ${icon}"></i></div>
              <div class="threat-content">
                <h4>${secEsc(it.title)}</h4>
                <p>${secEsc(it.description)}</p>
              </div>
              <div class="threat-time">${secEsc(it.time)}</div>
            </div>`;
          })
          .join("");
      }

      function renderLoginActivityFromPayload() {
        const tbody = document.getElementById("loginActivityBody");
        if (!tbody) return;
        const rows = getSecurityPayload().login_activity || [];
        if (!rows.length) {
          tbody.innerHTML =
            '<tr><td colspan="5" style="padding:1rem;color:var(--text-muted)">No login history yet.</td></tr>';
          return;
        }
        tbody.innerHTML = rows
          .map((r) => {
            return `<tr>
              <td>${secEsc(r.user)}</td>
              <td>${secEsc(r.location)}</td>
              <td>${secEsc(r.device)}</td>
              <td>${secEsc(r.ip)}</td>
              <td>${secEsc(r.time)}</td>
            </tr>`;
          })
          .join("");
      }

      function renderSecurityLogsFromPayload() {
        const box = document.getElementById("securityLogs");
        if (!box) return;
        const items = getSecurityPayload().security_logs || [];
        if (!items.length) {
          box.innerHTML =
            '<div style="padding:1rem;color:var(--text-muted)">No log entries yet.</div>';
          return;
        }
        box.innerHTML = items
          .map((it) => {
            const rc = it.result_class === "failed" ? "failed" : "success";
            return `<div class="log-item">
              <span class="log-time">${secEsc(it.time)}</span>
              <span class="log-action">${secEsc(it.action)}</span>
              <span class="log-result ${rc}">${secEsc(it.result)}</span>
            </div>`;
          })
          .join("");
      }

      function applyOverviewFromPayload() {
        const st = getSecurityPayload().stats || {};
        const at = document.getElementById("activeThreats");
        const ba = document.getElementById("blockedAttacks");
        const sa = document.getElementById("suspiciousActivities");
        const ss = document.getElementById("securityScore");
        const tp = document.getElementById("threatProgress");
        const bars = document.querySelectorAll(".analytics-card .progress-fill");
        if (at) at.textContent = String(st.active_threats ?? 0);
        if (ba)
          ba.textContent = Number(st.blocked_attacks ?? 0).toLocaleString();
        if (sa)
          sa.textContent = Number(st.suspicious ?? 0).toLocaleString();
        if (ss) ss.textContent = String(st.security_score ?? 0) + "%";
        const scoreBar = document.getElementById("securityScoreBar");
        if (scoreBar)
          scoreBar.style.width =
            String(Math.min(100, Math.max(0, Number(st.security_score ?? 0)))) +
            "%";
        if (tp) {
          const tpct = Math.min(
            100,
            Math.max(8, Number(st.active_threats ?? 0) * 7),
          );
          tp.style.width = tpct + "%";
        }
        if (bars && bars[1])
          bars[1].style.width =
            Math.min(
              100,
              Math.max(
                10,
                Number(st.blocked_attacks ?? 0) > 0
                  ? 22 + Math.ceil(Number(st.blocked_attacks ?? 0) / 40)
                  : 12,
              ),
            ) + "%";
        if (bars && bars[2])
          bars[2].style.width =
            Math.min(
              100,
              Math.max(8, Number(st.suspicious ?? 0) * 3),
            ) + "%";
        const rs = document.getElementById("riskScore");
        const an = document.getElementById("anomalies");
        const tl = document.getElementById("threatLevel");
        if (rs) rs.textContent = String(st.risk_score ?? 0);
        if (an) an.textContent = String(st.anomalies ?? 0);
        if (tl) {
          tl.textContent = String(st.threat_level ?? "—");
          tl.className =
            "risk-score-badge " + String(st.threat_level_class ?? "risk-medium");
        }
      }

      // Render user risk table
      function renderUserRiskTable() {
        const tbody = document.getElementById("userRiskTable");
        tbody.innerHTML = "";
        const userRisks = getSecurityPayload().user_risks || [];
        if (!userRisks.length) {
          tbody.innerHTML =
            '<tr><td colspan="6" style="padding:1rem;color:var(--text-muted)">No users found.</td></tr>';
          return;
        }
        userRisks.forEach((user) => {
          let riskClass = "";
          if (user.risk < 30) riskClass = "risk-score-0-30";
          else if (user.risk < 60) riskClass = "risk-score-31-60";
          else riskClass = "risk-score-61-100";

          let statusClass = "";
          if (user.status === "Active") statusClass = "status-active";
          else if (user.status === "Suspended")
            statusClass = "status-suspended";
          else statusClass = "status-locked";

          const emailJs = JSON.stringify(user.email ?? "");
          const nameJs = JSON.stringify(user.user ?? "");
          const row = document.createElement("tr");
          row.innerHTML = `
            <td><strong>${secEsc(user.user)}</strong></td>
            <td>${secEsc(user.email)}</td>
            <td><span class="risk-score-badge ${riskClass}">${secEsc(user.risk)}</span></td>
            <td>${secEsc(user.lastActivity)}</td>
            <td><span class="status-badge ${statusClass}">${secEsc(user.status)}</span></td>
            <td>
              <div class="actions-dropdown">
                <button class="actions-btn" onclick='toggleDropdown(event, this)'><i class="fa-solid fa-ellipsis-vertical"></i> Actions</button>
                <div class="dropdown-menu">
                  <a class="dropdown-item" onclick='viewActivity(${nameJs})'><i class="fa-regular fa-eye"></i> View Activity</a>
                  <a class="dropdown-item" onclick='lockAccount(${emailJs})'><i class="fa-solid fa-lock"></i> Lock Account</a>
                  <a class="dropdown-item" onclick='unlockAccount(${emailJs})'><i class="fa-solid fa-unlock"></i> Unlock Account</a>
                  <a class="dropdown-item" onclick='forceLogout(${emailJs})'><i class="fa-solid fa-sign-out-alt"></i> Force Logout</a>
                  <a class="dropdown-item" onclick='freezeAccount(${emailJs})'><i class="fa-solid fa-snowflake"></i> Freeze Account</a>
                  <a class="dropdown-item" onclick='unfreezeAccount(${emailJs})'><i class="fa-solid fa-sun"></i> Unfreeze Account</a>
                </div>
              </div>
            </td>
          `;
          tbody.appendChild(row);
        });
      }

      // Dropdown toggle
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

      // Action functions
      window.viewActivity = function (user) {
        openModal({
          title: "User activity",
          message: `Viewing activity for: ${user}`,
          showReason: false,
          okText: "Close",
          cancelText: "Close",
        });
      };

      window.lockAccount = async function (email) {
        const m = await openModal({
          title: "Lock account",
          message: `Lock this account?\n\n${email}`,
          showReason: true,
          okText: "Lock",
          cancelText: "Cancel",
        });
        if (!m.ok) return;
        try {
          await adminUserAction("lock", email, m.reason);
          await openModal({
            title: "Success",
            message: `Account locked for ${email}`,
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
          addToThreatFeed("warning", "Account locked", `${email} locked by admin`);
        } catch (e) {
          openModal({
            title: "Error",
            message: e.message || "Could not lock account",
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
        }
      };

      window.unlockAccount = async function (email) {
        const m = await openModal({
          title: "Unlock account",
          message: `Unlock this account?\n\n${email}`,
          showReason: false,
          okText: "Unlock",
          cancelText: "Cancel",
        });
        if (!m.ok) return;
        try {
          await adminUserAction("unlock", email);
          await openModal({
            title: "Success",
            message: `Account unlocked for ${email}`,
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
          addToThreatFeed("warning", "Account unlocked", `${email} unlocked by admin`);
        } catch (e) {
          openModal({
            title: "Error",
            message: e.message || "Could not unlock account",
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
        }
      };

      window.freezeAccount = async function (email) {
        const m = await openModal({
          title: "Freeze (suspend) account",
          message: `Freeze/suspend this account?\n\n${email}`,
          showReason: true,
          okText: "Freeze",
          cancelText: "Cancel",
        });
        if (!m.ok) return;
        try {
          await adminUserAction("freeze", email, m.reason);
          await openModal({
            title: "Success",
            message: `Account frozen for ${email}`,
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
          addToThreatFeed("warning", "Account frozen", `${email} frozen by admin`);
        } catch (e) {
          openModal({
            title: "Error",
            message: e.message || "Could not freeze account",
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
        }
      };

      window.unfreezeAccount = async function (email) {
        const m = await openModal({
          title: "Unfreeze account",
          message: `Unfreeze this account?\n\n${email}`,
          showReason: false,
          okText: "Unfreeze",
          cancelText: "Cancel",
        });
        if (!m.ok) return;
        try {
          await adminUserAction("unfreeze", email);
          await openModal({
            title: "Success",
            message: `Account unfrozen for ${email}`,
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
          addToThreatFeed("warning", "Account unfrozen", `${email} unfrozen by admin`);
        } catch (e) {
          openModal({
            title: "Error",
            message: e.message || "Could not unfreeze account",
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
        }
      };

      window.forceLogout = async function (email) {
        const m = await openModal({
          title: "Force logout",
          message: `Force logout this user on their next request?\n\n${email}`,
          showReason: false,
          okText: "Force logout",
          cancelText: "Cancel",
        });
        if (!m.ok) return;
        try {
          await adminUserAction("force_logout", email);
          await openModal({
            title: "Success",
            message: `Force logout sent for ${email}`,
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
        } catch (e) {
          openModal({
            title: "Error",
            message: e.message || "Could not force logout",
            showReason: false,
            okText: "OK",
            cancelText: "OK",
          });
        }
      };

      // Keep existing function but no-op (UI placeholder)
      window.flagUser = function () {};

      // Add to threat feed
      function addToThreatFeed(type, title, description) {
        const feed = document.getElementById("threatFeed");
        const threatItem = document.createElement("div");
        threatItem.className = `threat-item ${type}`;

        let icon = type === "critical" ? "fa-skull" : "fa-triangle-exclamation";

        threatItem.innerHTML = `
          <div class="threat-icon ${type}"><i class="fa-solid ${icon}"></i></div>
          <div class="threat-content">
            <h4>${title}</h4>
            <p>${description}</p>
          </div>
          <div class="threat-time">just now</div>
        `;

        feed.prepend(threatItem);
        if (feed.children.length > 5) {
          feed.removeChild(feed.lastChild);
        }
      }

      // Search functionality
      document.getElementById("userSearch").addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll("#userRiskTable tr");

        rows.forEach((row) => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(searchTerm) ? "" : "none";
        });
      });

      // Filter functionality
      document.getElementById("riskFilter").addEventListener("change", (e) => {
        const filter = e.target.value.toLowerCase();
        const rows = document.querySelectorAll("#userRiskTable tr");

        rows.forEach((row) => {
          const riskCell = row.querySelector(".risk-score-badge");
          if (riskCell) {
            const risk = parseInt(riskCell.textContent);
            if (filter === "all risk levels") {
              row.style.display = "";
            } else if (filter === "low risk" && risk < 30) {
              row.style.display = "";
            } else if (filter === "medium risk" && risk >= 30 && risk < 60) {
              row.style.display = "";
            } else if (filter === "high risk" && risk >= 60) {
              row.style.display = "";
            } else {
              row.style.display = "none";
            }
          }
        });
      });

      let threatChartInstance = null;

      // Initialize charts (7-day transaction-based signals from DB)
      function initCharts() {
        const ch = getSecurityPayload().chart || {};
        const labels = Array.isArray(ch.labels) ? ch.labels : [];
        const crit = Array.isArray(ch.critical) ? ch.critical : [];
        const warn = Array.isArray(ch.warning) ? ch.warning : [];
        const canvas = document.getElementById("threatChart");
        if (!canvas || typeof Chart === "undefined") return;
        if (threatChartInstance) {
          threatChartInstance.destroy();
        }
        threatChartInstance = new Chart(canvas, {
          type: "line",
          data: {
            labels: labels.length ? labels : ["—", "—", "—", "—", "—", "—", "—"],
            datasets: [
              {
                label: "Critical",
                data: crit.length ? crit : [0, 0, 0, 0, 0, 0, 0],
                borderColor: "#ef4444",
                tension: 0.4,
                fill: false,
              },
              {
                label: "Warning",
                data: warn.length ? warn : [0, 0, 0, 0, 0, 0, 0],
                borderColor: "#f59e0b",
                tension: 0.4,
                fill: false,
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
      }

      // Rotate scanning label only (no fake metrics)
      const scanningTexts = [
        "User behavior",
        "Transactions",
        "Wallet activity",
        "Login patterns",
        "Withdrawals",
      ];
      let scanIndex = 0;
      setInterval(() => {
        const el = document.getElementById("aiScanning");
        if (!el) return;
        el.textContent = scanningTexts[scanIndex];
        scanIndex = (scanIndex + 1) % scanningTexts.length;
      }, 4000);

      // AI Assistant
      document.getElementById("aiBtn").addEventListener("click", () => {
        const st = getSecurityPayload().stats || {};
        const at = st.active_threats ?? 0;
        const ba = st.blocked_attacks ?? 0;
        const sc = st.security_score ?? 0;
        const hi = (getSecurityPayload().user_risks || []).filter(
          (u) => Number(u.risk) >= 60,
        ).length;
        infoModal(
          `Active threats (signals): ${at}\nFailed admin attempts (sum): ${ba}\nHigh-risk users (score ≥ 60): ${hi}\nSecurity score: ${sc}%\n\nReview pending withdrawals and KYC queue in production.`,
          "AI Security Assistant",
        );
      });

      // Initialize
      renderThreatFeedFromPayload();
      renderLoginActivityFromPayload();
      renderSecurityLogsFromPayload();
      applyOverviewFromPayload();
      renderUserRiskTable();
      initCharts();

      // Real-time refresh (activity feed + counters)
      refreshSecurityCenterRealtime();
      setInterval(refreshSecurityCenterRealtime, 7000);

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
