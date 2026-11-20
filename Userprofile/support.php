<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_sidebar_state.php';

function sup_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userFullName = (string)($_SESSION['user_full_name'] ?? '');
$userEmail = (string)($_SESSION['user_email'] ?? '');
$userTickets = [];

try {
  /** @var string $DB_HOST */
  /** @var string $DB_USER */
  /** @var string $DB_PASS */
  /** @var string $DB_NAME */
  $db = user_db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
  $row = user_fetch_by_id($db, $userId);
  if (is_array($row)) {
    $userFullName = (string)$row['full_name'];
    $userEmail = (string)$row['email'];
    $_SESSION['user_full_name'] = $userFullName;
    $_SESSION['user_email'] = $userEmail;
  }
  $userTickets = user_support_list_for_user($db, $userId, 25);
  $db->close();
} catch (Throwable $e) {
  error_log('[support] ' . $e->getMessage());
}

$kycSidebarBadge = kyc_sidebar_badge_for_session();
$csrfToken = user_csrf_token();

function support_ticket_status_label(string $status): string {
  $s = strtolower($status);
  return match ($s) {
    'open' => 'Open',
    'in_progress' => 'In progress',
    'resolved' => 'Resolved',
    'closed' => 'Closed',
    default => ucfirst($s),
  };
}

function support_ticket_status_class(string $status): string {
  $s = strtolower($status);
  if ($s === 'resolved' || $s === 'closed') {
    return 'status-resolved';
  }
  if ($s === 'in_progress') {
    return 'status-open';
  }
  return 'status-open';
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$supportDir = $scriptName !== '' ? dirname($scriptName) : '/Userprofile';
$supportApiUrl = rtrim($supportDir, '/') . '/api/support_api.php';

$supportBootJson = json_encode(
  ['csrf' => $csrfToken, 'apiUrl' => $supportApiUrl],
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
if ($supportBootJson === false) {
  $supportBootJson = '{}';
}

require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes"
    />
    <title>Crypterra | Support Center</title>
    <?php $UI_MODAL_PREFIX = '../'; include __DIR__ . '/../includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"
    />
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family:
          "Inter",
          system-ui,
          -apple-system,
          "Segoe UI",
          Roboto,
          sans-serif;
      }

      @import url("https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap");

      /* ========== ROOT COLORS - GREEN THEME ========== */
      :root {
        --primary-color: #22c55e;
        --primary-light: #4ade80;
        --primary-dark: #16a34a;
        --primary-glow: rgba(34, 197, 94, 0.5);
        --primary-soft: rgba(34, 197, 94, 0.15);
        --secondary-color: #2ecc71;
        --danger-color: #ef5350;
        --warning-color: #ffa726;

        /* dark theme (default) */
        --bg: #0a1525;
        --bg-secondary: #07101f;
        --surface: #0f1e30;
        --surface-light: #1a2a40;
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
        --light-border: #2d2d2d;
        --input-bg: rgba(10, 20, 35, 0.8);
      }

      /* light theme class */
      body.light {
        --primary-color: #22c55e;
        --primary-light: #4ade80;
        --primary-glow: rgba(34, 197, 94, 0.4);
        --bg: #f5f7fb;
        --bg-secondary: #eef2f6;
        --surface: #ffffff;
        --surface-light: #f0f4fa;
        --text: #333333;
        --text-secondary: #555555;
        --text-muted: #7f8c8d;
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-edge: rgba(34, 197, 94, 0.3);
        --card-bg: #ffffff;
        --positive: #2ecc71;
        --negative: #e74c3c;
        --shadow: 0 6px 15px rgba(0, 0, 0, 0.08);
        --shadow-glow: 0 0 20px rgba(34, 197, 94, 0.4);
        --light-border: #eaeaea;
        --input-bg: #f8fafc;
      }

      body {
        background-color: var(--bg);
        color: var(--text);
        transition:
          background-color 0.3s ease,
          color 0.2s ease;
      }

      /* Dashboard Layout */
      .dashboard {
        display: flex;
        min-height: 100vh;
      }

      /* SIDEBAR */
      .sidebar {
        width: 280px;
        background: var(--bg-secondary);
        border-right: 1px solid var(--light-border);
        transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 20;
      }

      .sidebar.collapsed {
        width: 80px;
      }

      .sidebar.collapsed .logo-wrapper h3,
      .sidebar.collapsed .user-info,
      .sidebar.collapsed .sidebar-link span:not(.action-badge),
      .sidebar.collapsed .nav-section {
        display: none;
      }

      .sidebar.collapsed .sidebar-link {
        justify-content: center;
        padding: 12px;
      }

      .sidebar.collapsed .sidebar-link i {
        margin-right: 0;
      }

      .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px 20px;
        border-bottom: 1px solid var(--light-border);
      }

      .logo-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .logo-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        box-shadow: 0 4px 10px var(--primary-glow);
      }

      .collapse-btn {
        cursor: pointer;
        background: var(--surface);
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: 0.2s;
        color: var(--text-secondary);
      }

      .sidebar-user {
        padding: 24px 20px;
        display: flex;
        align-items: center;
        gap: 14px;
        border-bottom: 1px solid var(--light-border);
      }

      .user-avatar-large {
        width: 52px;
        height: 52px;
        background: linear-gradient(
          145deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border-radius: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 24px;
      }

      .user-info h4 {
        font-size: 1rem;
        font-weight: 600;
      }

      .user-info p {
        font-size: 0.75rem;
        color: var(--text-muted);
      }

      .sidebar-nav {
        padding: 20px 12px;
        display: flex;
        flex-direction: column;
        gap: 6px;
      }

      .nav-section {
        font-size: 0.7rem;
        letter-spacing: 1px;
        font-weight: 600;
        color: var(--text-muted);
        margin: 16px 0 8px 12px;
      }

      .sidebar-link {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        border-radius: 12px;
        color: var(--text-secondary);
        text-decoration: none;
        transition: 0.2s;
      }

      .sidebar-link i {
        width: 22px;
        font-size: 1.1rem;
      }

      .sidebar-link:hover {
        background: var(--primary-soft);
        color: var(--primary-color);
      }

      .sidebar-link.active {
        background: var(--primary-soft);
        color: var(--primary-color);
        border-left: 3px solid var(--primary-color);
      }

      .action-badge {
        background: var(--primary-color);
        font-size: 0.6rem;
        padding: 2px 8px;
        border-radius: 20px;
        margin-left: auto;
        color: white;
      }

      /* MAIN CONTENT */
      .main-content {
        flex: 1;
        overflow-x: auto;
      }

      /* FIXED TOP NAVBAR */
      .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 28px;
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid var(--glass-edge);
        box-sizing: border-box;
      }

      @media (max-width: 768px) {
        #scrollTop{
          right: 1.5rem;
          bottom: 5.4rem;
        }
      }

      .header-left {
        display: flex;
        align-items: center;
        gap: 20px;
      }

      .mobile-menu-btn {
        display: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--text);
      }

      .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        color: inherit;
      }

      .user-avatar {
        width: 40px;
        height: 40px;
        background: var(--primary-soft);
        border-radius: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: var(--primary-color);
      }

      .header-right {
        display: flex;
        gap: 16px;
        align-items: center;
      }

      .icon-button {
        background: var(--surface);
        width: 40px;
        height: 40px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: 0.2s;
        color: var(--text-secondary);
        border: 1px solid var(--light-border);
      }

      .theme-toggle {
        width: 40px;
        height: 40px;
        background: var(--surface);
        border-radius: 30px;
        cursor: pointer;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2322c55e'%3E%3Cpath d='M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: center;
        background-size: 20px;
      }

      /* Dashboard Content */
      .dashboard-content {
        padding: 24px 28px;
        margin-top: 80px;
      }

      /* Support Page Specific Styles */
      .support-container {
        max-width: 1200px;
        margin: 0 auto;
      }

      .page-header {
        margin-bottom: 32px;
        text-align: center;
      }

      .page-header h1 {
        font-size: 2rem;
        background: linear-gradient(135deg, var(--text), var(--primary-light));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      /* Search Bar */
      .search-section {
        margin-bottom: 40px;
      }

      .search-box {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 8px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        max-width: 600px;
        margin: 0 auto;
      }

      .search-box i {
        font-size: 1.2rem;
        color: var(--text-muted);
      }

      .search-box input {
        flex: 1;
        background: transparent;
        border: none;
        padding: 14px 0;
        color: var(--text);
        font-size: 1rem;
        outline: none;
      }

      /* Quick Actions */
      .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
      }

      .action-card {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 24px;
        padding: 24px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
      }

      .action-card:hover {
        transform: translateY(-4px);
        border-color: var(--primary-color);
        box-shadow: var(--shadow-glow);
      }

      .action-card i {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 12px;
      }

      .action-card h4 {
        font-size: 1rem;
        margin-bottom: 8px;
      }

      .action-card p {
        font-size: 0.8rem;
        color: var(--text-muted);
      }

      /* FAQ Section */
      .faq-section {
        margin-bottom: 40px;
      }

      .section-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
      }

      .faq-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 20px;
      }

      .faq-item {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 20px;
        cursor: pointer;
        transition: all 0.2s;
      }

      .faq-item:hover {
        border-color: var(--primary-color);
      }

      .faq-question {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
      }

      .faq-answer {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        color: var(--text-muted);
        font-size: 0.9rem;
        line-height: 1.5;
        margin-top: 8px;
      }

      .faq-item.active .faq-answer {
        max-height: 200px;
      }

      /* Contact Form */
      .contact-section {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 32px;
        margin-bottom: 40px;
      }

      .contact-form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
      }

      .form-group {
        margin-bottom: 0;
      }

      .form-group.full-width {
        grid-column: span 2;
      }

      .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-secondary);
      }

      .form-group input,
      .form-group select,
      .form-group textarea {
        width: 100%;
        background: var(--input-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 16px;
        padding: 12px 16px;
        color: var(--text);
        font-size: 0.95rem;
        transition: all 0.2s;
      }

      .form-group input:focus,
      .form-group select:focus,
      .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px var(--primary-soft);
      }

      .submit-btn {
        background: linear-gradient(
          135deg,
          var(--primary-color),
          var(--primary-dark)
        );
        border: none;
        padding: 14px 28px;
        border-radius: 40px;
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        width: 100%;
        margin-top: 16px;
      }

      .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }

      /* Support Channels */
      .channels-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
      }

      .channel-card {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 24px;
        padding: 24px;
        text-align: center;
        transition: all 0.3s;
      }

      .channel-card:hover {
        transform: translateY(-4px);
        border-color: var(--primary-color);
      }

      .channel-card i {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 12px;
      }

      .channel-card h4 {
        font-size: 1.1rem;
        margin-bottom: 8px;
      }

      .channel-card p {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 16px;
      }

      .channel-link {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
      }

      /* Ticket History */
      .ticket-section {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 24px;
      }

      .ticket-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0;
        border-bottom: 1px solid var(--glass-edge);
      }

      .ticket-item:last-child {
        border-bottom: none;
      }

      .ticket-info h4 {
        font-size: 0.95rem;
        margin-bottom: 4px;
      }

      .ticket-info p {
        font-size: 0.75rem;
        color: var(--text-muted);
      }

      .ticket-status {
        padding: 4px 12px;
        border-radius: 40px;
        font-size: 0.7rem;
        font-weight: 500;
      }

      .status-open {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary-light);
      }

      .status-resolved {
        background: rgba(255, 167, 38, 0.2);
        color: var(--warning-color);
      }

      /* Modal */
      .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        justify-content: center;
        align-items: center;
      }

      .modal-content {
        background: var(--card-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 32px;
        max-width: 500px;
        width: 90%;
      }

      .modal-content h3 {
        margin-bottom: 16px;
      }

      .modal-buttons {
        display: flex;
        gap: 12px;
        margin-top: 24px;
      }

      .modal-btn {
        flex: 1;
        padding: 12px;
        border-radius: 40px;
        border: none;
        cursor: pointer;
        font-weight: 600;
      }

      .modal-btn.confirm {
        background: var(--primary-color);
        color: white;
      }

      .modal-btn.cancel {
        background: var(--surface);
        color: var(--text-secondary);
      }

            #scrollTop {
        position: fixed;
        right: 1.5rem;
        bottom: 5.4rem;
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
        .sidebar {
          position: fixed;
          transform: translateX(-100%);
          transition: transform 0.3s;
          z-index: 1000;
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
        .mobile-menu-btn {
          display: block;
        }
        .dashboard-content {
          padding: 16px;
          margin-top: 70px;
        }
        .contact-form {
          grid-template-columns: 1fr;
        }
        .form-group.full-width {
          grid-column: span 1;
        }
        .faq-grid {
          grid-template-columns: 1fr;
        }

        #scrollTop {
          right: 1.5rem;
          bottom: 5.4rem;
        }
      }
    </style>
    <link rel="stylesheet" href="includes/user_sidebar_branding.css" />
  </head>
  <body class="dark">
    <div class="dashboard">
      <!-- SIDEBAR -->
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
          <div class="user-avatar-large"><?php echo sup_h($ub['avatar_letter']); ?></div>
          <div class="user-info">
            <h4><?php echo sup_h($ub['full_name']); ?></h4>
            <p><?php echo sup_h($ub['wallet_short']); ?></p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section">MAIN</div>
          <a href="./userprofile.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a
          >
          <a href="./trade.php" class="sidebar-link"
            ><i class="fa-solid fa-chart-line"></i> <span>Trade</span></a
          >
          <a href="./wallet.php" class="sidebar-link"
            ><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a
          >
          <div class="nav-section">TRADING</div>
          <!-- <a href="./deposit.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-down"></i> <span>Deposit</span>
            <span class="action-badge">NEW</span></a
          >
          <a href="./withdraw.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-up"></i> <span>Withdraw</span></a
          > -->
          <a href="./send.php" class="sidebar-link"
            ><i class="fa-solid fa-paper-plane"></i> <span>Send</span></a
          >
          <a href="./receive.php" class="sidebar-link"
            ><i class="fa-solid fa-inbox"></i> <span>Receive</span></a
          >
          <div class="nav-section">ACCOUNT</div>
          <a href="./kycverification.php" class="sidebar-link"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Verification</span>
            <?php echo kyc_sidebar_badge_span($kycSidebarBadge); ?></a
          >
                    <a href="./security.php" class="sidebar-link"
            ><i class="fa-solid fa-shield"></i> <span>Security</span></a
          >
          <a href="./settings.php" class="sidebar-link"
            ><i class="fa-solid fa-gear"></i> <span>Settings</span></a
          >
          <div class="nav-section">SUPPORT</div>
          <a href="./support.php" class="sidebar-link active"
            ><i class="fa-solid fa-headset"></i> <span>Support</span></a
          >
          <a href="./logout.php" class="sidebar-link"
            ><i class="fa-solid fa-arrow-right-from-bracket"></i>
            <span>Logout</span></a
          >
        </nav>
      </aside>

      <!-- MAIN CONTENT -->
      <main class="main-content">
        <!-- FIXED TOP NAVBAR -->
        <div class="top-bar top-bar--main">
          <div class="header-left">
            <div class="mobile-menu-btn" id="mobileMenuBtn">
              <i class="fa-solid fa-bars"></i>
            </div>
            <a href="./userprofile.php" class="user-profile">
              <div class="user-avatar"><?php echo sup_h($ub['avatar_letter']); ?></div>
              <div class="user-profile-meta">
                <h3 class="user-name">Hello, <?php echo sup_h($ub['full_name']); ?></h3>
              </div>
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

        <div class="dashboard-content">
          <div class="support-container">
            <div class="page-header" data-aos="fade-up">
              <h1><i class="fa-solid fa-headset"></i> How can we help you?</h1>
              <p style="color: var(--text-muted); margin-top: 8px">
                Get instant support, browse FAQs, or contact our support team
              </p>
            </div>

            <!-- Search Bar -->
            <div class="search-section" data-aos="fade-up">
              <div class="search-box">
                <i class="fa-solid fa-search"></i>
                <input
                  type="text"
                  id="searchInput"
                  placeholder="Search for help articles, FAQs, or topics..."
                />
              </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions" data-aos="fade-up">
              <div class="action-card" data-action="ticket">
                <i class="fa-solid fa-ticket"></i>
                <h4>Submit Ticket</h4>
                <p>Get personalized support from our team</p>
              </div>
              <!--<div class="action-card" data-action="chat">-->
              <!--  <i class="fa-solid fa-comment-dots"></i>-->
              <!--  <h4>Live Chat</h4>-->
              <!--  <p>Chat with our support agents 24/7</p>-->
              <!--</div>-->
              <div class="action-card" data-action="faq">
                <i class="fa-solid fa-question-circle"></i>
                <h4>Browse FAQs</h4>
                <p>Find answers to common questions</p>
              </div>
              <div class="action-card" data-action="status">
                <i class="fa-solid fa-chart-line"></i>
                <h4>System Status</h4>
                <p>Check platform availability</p>
              </div>
            </div>

            <!-- FAQ Section -->
            <div class="faq-section" data-aos="fade-up">
              <div class="section-title">
                <i class="fa-solid fa-circle-question"></i>
                <span>Frequently Asked Questions</span>
              </div>
              <div class="faq-grid" id="faqGrid">
                <div class="faq-item">
                  <div class="faq-question">
                    How do I deposit funds into my account?
                    <i class="fa-solid fa-chevron-down"></i>
                  </div>
                  <div class="faq-answer">
                    To deposit funds, go to the Deposit page, select your
                    preferred cryptocurrency, copy the deposit address, and send
                    funds from your external wallet. Deposits typically take
                    10-30 minutes to confirm.
                  </div>
                </div>
                <div class="faq-item">
                  <div class="faq-question">
                    What are the withdrawal limits?
                    <i class="fa-solid fa-chevron-down"></i>
                  </div>
                  <div class="faq-answer">
                    Withdrawal limits depend on your KYC level. Level 1 (Basic):
                    $10,000/day, Level 2 (Verified): $50,000/day, Level 3
                    (Advanced): $200,000/day. Complete KYC to increase your
                    limits.
                  </div>
                </div>
                <div class="faq-item">
                  <div class="faq-question">
                    How long do withdrawals take?
                    <i class="fa-solid fa-chevron-down"></i>
                  </div>
                  <div class="faq-answer">
                    Withdrawals are processed instantly and typically arrive
                    within 5-30 minutes depending on network congestion. For
                    security, large withdrawals may require additional
                    verification.
                  </div>
                </div>
                <div class="faq-item">
                  <div class="faq-question">
                    How do I enable 2FA?
                    <i class="fa-solid fa-chevron-down"></i>
                  </div>
                  <div class="faq-answer">
                    Go to Security Settings, click "Enable 2FA", scan the QR
                    code with Google Authenticator or Authy, and enter the
                    verification code to complete setup.
                  </div>
                </div>
                <div class="faq-item">
                  <div class="faq-question">
                    What is the minimum deposit amount?
                    <i class="fa-solid fa-chevron-down"></i>
                  </div>
                  <div class="faq-answer">
                    Minimum deposit amounts: Bitcoin: 0.001 BTC, Ethereum: 0.01
                    ETH, USDT: 10 USDT, Solana: 0.1 SOL. Deposits below these
                    amounts may not be credited.
                  </div>
                </div>
                <div class="faq-item">
                  <div class="faq-question">
                    How do I reset my password?
                    <i class="fa-solid fa-chevron-down"></i>
                  </div>
                  <div class="faq-answer">
                    Click "Forgot Password" on the login page, enter your email
                    address, and follow the instructions sent to your inbox. For
                    security, you may need to verify your identity.
                  </div>
                </div>
              </div>
            </div>

            <!-- Contact Form -->
            <div class="contact-section" data-aos="fade-up">
              <div class="section-title">
                <i class="fa-solid fa-envelope"></i>
                <span>Submit a Support Ticket</span>
              </div>
              <form id="supportForm" class="contact-form">
                <div class="form-group">
                  <label>Full Name</label>
                  <input
                    type="text"
                    id="ticketName"
                    value="<?php echo sup_h($userFullName); ?>"
                    required
                  />
                </div>
                <div class="form-group">
                  <label>Email Address</label>
                  <input
                    type="email"
                    id="ticketEmail"
                    value="<?php echo sup_h($userEmail); ?>"
                    required
                  />
                </div>
                <div class="form-group">
                  <label>Issue Type</label>
                  <select id="ticketType">
                    <option value="account">Account Issue</option>
                    <option value="deposit">Deposit Problem</option>
                    <option value="withdrawal">Withdrawal Issue</option>
                    <option value="kyc">KYC Verification</option>
                    <option value="security">Security Concern</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div class="form-group">
                  <label>Priority</label>
                  <select id="ticketPriority">
                    <option value="low">Low - General Question</option>
                    <option value="medium">Medium - Need Help</option>
                    <option value="high">High - Urgent Issue</option>
                  </select>
                </div>
                <div class="form-group full-width">
                  <label>Subject</label>
                  <input
                    type="text"
                    id="ticketSubject"
                    placeholder="Brief description of your issue"
                    required
                  />
                </div>
                <div class="form-group full-width">
                  <label>Message</label>
                  <textarea
                    rows="4"
                    id="ticketMessage"
                    placeholder="Please provide detailed information about your issue..."
                    required
                  ></textarea>
                </div>
                <button type="submit" class="submit-btn">
                  Submit Ticket <i class="fa-solid fa-paper-plane"></i>
                </button>
              </form>
            </div>

            <!-- Support Channels -->
            <!--<div class="channels-section" data-aos="fade-up">-->
            <!--  <div class="channel-card">-->
            <!--    <i class="fa-brands fa-whatsapp"></i>-->
            <!--    <h4>WhatsApp Support</h4>-->
            <!--    <p>Chat with us directly on WhatsApp for instant help</p>-->
            <!--    <a href="https://wa.me/+447511617001?text=Hello%20Crypterra%20Support%2C%20I%20need%20assistance%20with%20my%20account." target="_blank" rel="noopener noreferrer" class="channel-link" id="liveChatBtn"-->
            <!--      >Start Chat <i class="fa-solid fa-arrow-right"></i-->
            <!--    ></a>-->
            <!--  </div>-->
              <div class="channel-card">
                <i class="fa-regular fa-envelope"></i>
                <h4>Email Support</h4>
                <p>Response within 24 hours</p>
                <a href="mailto:support@crypterra.com" class="channel-link"
                  >supportcrypterra@gmail.com</a
                >
              </div>
              <div class="channel-card">
                <i class="fa-brands fa-telegram"></i>
                <h4>Telegram Community</h4>
                <p>Join our community for updates</p>
                <a href="#" class="channel-link" id="telegramBtn"
                  >@CrypterraSupport</a
                >
              </div>
              <div class="channel-card">
                <i class="fa-brands fa-twitter"></i>
                <h4>Twitter Updates</h4>
                <p>Follow for announcements</p>
                <a href="#" class="channel-link">@Crypterra</a>
              </div>
            </div>

            <!-- Ticket History -->
            <div class="ticket-section" data-aos="fade-up">
              <div class="section-title">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Recent Support Tickets</span>
              </div>
              <div id="ticketHistory">
                <?php if ($userTickets === []) { ?>
                <div class="ticket-item" style="border: none; color: var(--text-muted)">
                  <div class="ticket-info">
                    <h4>No tickets yet</h4>
                    <p>Submit a request above — it will appear here and in the admin Help Center.</p>
                  </div>
                </div>
                <?php } else { ?>
                <?php foreach ($userTickets as $t) {
                  $created = $t['created_at'] ?? '';
                  $dt = $created !== '' ? date_create($created) : false;
                  $dateStr = $dt ? $dt->format('M j, Y') : $created;
                  $subj = (string)($t['subject'] ?? '');
                  $ref = (string)($t['ticket_ref'] ?? '');
                  $st = (string)($t['status'] ?? 'open');
                  ?>
                <div class="ticket-item">
                  <div class="ticket-info">
                    <h4><?php echo sup_h($subj !== '' ? $subj : 'Support request'); ?></h4>
                    <p>
                      Ticket #<?php echo sup_h($ref); ?> • Created
                      <?php echo sup_h($dateStr); ?>
                    </p>
                  </div>
                  <span class="ticket-status <?php echo sup_h(support_ticket_status_class($st)); ?>"><?php echo sup_h(support_ticket_status_label($st)); ?></span>
                </div>
                <?php } ?>
                <?php } ?>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="includes/user_theme.js"></script>
    <script>

      const SUPPORT_API =
        (window.__SUPPORT_BOOT && window.__SUPPORT_BOOT.apiUrl) ||
        "./api/support_api.php";
      let supportCsrf =
        (window.__SUPPORT_BOOT && window.__SUPPORT_BOOT.csrf) || "";

      // FAQ Accordion
      const faqItems = document.querySelectorAll(".faq-item");
      faqItems.forEach((item) => {
        const question = item.querySelector(".faq-question");
        question.addEventListener("click", () => {
          item.classList.toggle("active");
          const icon = question.querySelector("i");
          if (item.classList.contains("active")) {
            icon.classList.remove("fa-chevron-down");
            icon.classList.add("fa-chevron-up");
          } else {
            icon.classList.remove("fa-chevron-up");
            icon.classList.add("fa-chevron-down");
          }
        });
      });

      // Search functionality
      const searchInput = document.getElementById("searchInput");
      searchInput.addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const faqItemsAll = document.querySelectorAll(".faq-item");

        faqItemsAll.forEach((item) => {
          const question = item
            .querySelector(".faq-question")
            .innerText.toLowerCase();
          const answer = item
            .querySelector(".faq-answer")
            .innerText.toLowerCase();
          if (
            question.includes(searchTerm) ||
            answer.includes(searchTerm) ||
            searchTerm === ""
          ) {
            item.style.display = "block";
          } else {
            item.style.display = "none";
          }
        });
      });

      // Quick Actions
      const actionCards = document.querySelectorAll(".action-card");
      actionCards.forEach((card) => {
        card.addEventListener("click", () => {
          const action = card.getAttribute("data-action");
          if (action === "ticket") {
            document
              .querySelector(".contact-section")
              .scrollIntoView({ behavior: "smooth" });
          } else if (action === "chat") {
            window.open("https://wa.me/+447511617001?text=Hello%20Crypterra%20Support%2C%20I%20need%20assistance%20with%20my%20account.", "_blank", "noopener,noreferrer");
          } else if (action === "faq") {
            document
              .querySelector(".faq-section")
              .scrollIntoView({ behavior: "smooth" });
          } else if (action === "status") {
            showNotification(
              "All systems operational. Platform is running smoothly.",
              "success",
            );
          }
        });
      });

      // Support Form Submission
      const supportForm = document.getElementById("supportForm");
      const modal = document.getElementById("supportModal");
      const modalConfirm = document.getElementById("modalConfirmBtn");

      supportForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const name = document.getElementById("ticketName").value.trim();
        const email = document.getElementById("ticketEmail").value.trim();
        const type = document.getElementById("ticketType").value;
        const priority = document.getElementById("ticketPriority").value;
        const subject = document.getElementById("ticketSubject").value.trim();
        const message = document.getElementById("ticketMessage").value.trim();

        if (!subject || !message) {
          showNotification(
            "Please fill in subject and message fields",
            "error",
          );
          return;
        }

        const btn = supportForm.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;
        try {
          const res = await fetch(SUPPORT_API, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
              csrf_token: supportCsrf,
              full_name: name,
              email,
              issue_type: type,
              priority,
              subject,
              message,
            }),
          });
          const data = await res.json().catch(() => ({}));
          if (!res.ok || !data.ok) {
            showNotification(
              data.error || "Could not submit ticket. Try again.",
              "error",
            );
            return;
          }
          if (data.csrf) supportCsrf = data.csrf;
          const ticketNum = data.ticket_ref || "submitted";

          const ticketHistory = document.getElementById("ticketHistory");
          const emptyRow = ticketHistory.querySelector(".ticket-item");
          if (
            emptyRow &&
            emptyRow.textContent &&
            emptyRow.textContent.includes("No tickets yet")
          ) {
            emptyRow.remove();
          }

          const newTicket = document.createElement("div");
          newTicket.className = "ticket-item";
          const date = new Date().toLocaleDateString(undefined, {
            month: "short",
            day: "numeric",
            year: "numeric",
          });
          newTicket.innerHTML = `
      <div class="ticket-info">
        <h4>${subject.substring(0, 80).replace(/</g, "&lt;")}</h4>
        <p>Ticket #${String(ticketNum).replace(/</g, "&lt;")} • Created ${date}</p>
      </div>
      <span class="ticket-status status-open">Open</span>
    `;
          ticketHistory.insertBefore(newTicket, ticketHistory.firstChild);

          document.getElementById("modalTitle").innerText = "Ticket Submitted";
          document.getElementById("modalMessage").innerHTML =
            `Your support ticket (#${ticketNum}) has been created successfully. Our team will respond within 24 hours.`;
          modal.style.display = "flex";

          document.getElementById("ticketSubject").value = "";
          document.getElementById("ticketMessage").value = "";

          showNotification(
            `Ticket #${ticketNum} submitted successfully!`,
            "success",
          );
        } catch (err) {
          showNotification(
            err && err.message ? err.message : "Network error",
            "error",
          );
        } finally {
          if (btn) btn.disabled = false;
        }
      });

      modalConfirm.addEventListener("click", () => {
        modal.style.display = "none";
      });

      // Telegram Button
      const telegramBtn = document.getElementById("telegramBtn");
      telegramBtn.addEventListener("click", (e) => {
        e.preventDefault();
        showNotification(
          "Join our Telegram community: https://t.me/crypterra",
          "info",
        );
      });

      function showNotification(message, type = "success") {
        const notif = document.createElement("div");
        notif.className = "notification-popup show";
        notif.style.position = "fixed";
        notif.style.top = "90px";
        notif.style.right = "24px";
        notif.style.background = "var(--surface)";
        notif.style.padding = "12px 20px";
        notif.style.borderRadius = "40px";
        notif.style.borderLeft = `4px solid ${type === "success" ? "var(--primary-color)" : "var(--warning-color)"}`;
        notif.style.zIndex = "200";
        notif.style.boxShadow = "var(--shadow)";
        notif.innerHTML = `<i class="fa-solid ${type === "success" ? "fa-check-circle" : "fa-info-circle"}"></i> ${message}`;
        document.body.appendChild(notif);
        setTimeout(() => notif.remove(), 3000);
      }

      // Sidebar functionality
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      const mobileBtn = document.getElementById("mobileMenuBtn");

      collapseBtn.addEventListener("click", () =>
        sidebar.classList.toggle("collapsed"),
      );
      mobileBtn.addEventListener("click", () =>
        sidebar.classList.toggle("mobile-open"),
      );

      document.addEventListener("click", (e) => {
        if (
          window.innerWidth <= 768 &&
          !sidebar.contains(e.target) &&
          !mobileBtn.contains(e.target) &&
          sidebar.classList.contains("mobile-open")
        ) {
          sidebar.classList.remove("mobile-open");
        }
      });

      // Scroll to top
      const scrollBtn = document.getElementById("scrollTop");
      window.addEventListener("scroll", () => {
        if (window.scrollY > 400) scrollBtn.style.display = "flex";
        else scrollBtn.style.display = "none";
      });
      scrollBtn.addEventListener("click", () =>
        window.scrollTo({ top: 0, behavior: "smooth" }),
      );

      // Notification bell
      const notifBtn = document.getElementById("notifications-btn");
      notifBtn.addEventListener("click", () => {
        showNotification(
          "📢 Need help? Check our FAQ or submit a support ticket!",
          "info",
        );
      });

    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>