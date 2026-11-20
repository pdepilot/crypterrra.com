<?php
declare(strict_types=1);

require __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/user_db.php';
require_once __DIR__ . '/includes/kyc_sidebar_state.php';
$kycSidebarBadge = kyc_sidebar_badge_for_session();

$userId = (int)($_SESSION['user_id'] ?? 0);
$userCsrf = user_csrf_token();
require_once __DIR__ . '/includes/user_branding.php';
$ub = user_branding_context($userId);
$userFullName = $ub['full_name'];

$__upBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=yes"
    />
    <title>Crypterra | Security Settings</title>
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

      /* Dashboard Layout — sidebar is fixed so the main column can never sit on top and block clicks */
      .dashboard {
        display: block;
        min-height: 100vh;
      }

      /* SIDEBAR */
      .sidebar {
        width: 280px;
        background: var(--bg-secondary);
        border-right: 1px solid var(--light-border);
        transition:
          width 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1),
          transform 0.3s;
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
        height: 100vh;
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 2147483000;
        pointer-events: auto;
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
        position: relative;
        z-index: 2;
        cursor: pointer;
        pointer-events: auto;
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

      /* MAIN CONTENT — offset for fixed sidebar */
      .main-content {
        margin-left: 280px;
        min-height: 100vh;
        overflow-x: auto;
        transition: margin-left 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
      }

      .sidebar.collapsed ~ .main-content {
        margin-left: 80px;
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

      /* Add padding to main content to account for fixed navbar */
      .dashboard-content {
        padding: 24px 28px;
        margin-top: 80px;
      }

      /* Security Page Specific Styles */
      .security-container {
        max-width: 1200px;
        margin: 0 auto;
      }

      .page-header {
        margin-bottom: 32px;
      }

      .page-header h1 {
        font-size: 2rem;
        background: linear-gradient(135deg, var(--text), var(--primary-light));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      /* Security Score Card */
      .security-score-card {
        background: linear-gradient(
          135deg,
          var(--primary-soft),
          var(--surface)
        );
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 28px;
        margin-bottom: 32px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
      }

      .score-circle {
        width: 120px;
        height: 120px;
        border-radius: 60px;
        background: conic-gradient(
          var(--primary-color) 0deg 280deg,
          var(--surface) 280deg 360deg
        );
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
      }

      .score-inner {
        width: 100px;
        height: 100px;
        background: var(--bg);
        border-radius: 50px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
      }

      .score-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary-color);
      }

      .score-text {
        font-size: 0.8rem;
        color: var(--text-muted);
      }

      .security-message h3 {
        margin-bottom: 8px;
      }

      .security-message p {
        color: var(--text-muted);
      }

      /* Security Grid */
      .security-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
      }

      .security-card {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 24px;
        transition:
          transform 0.2s,
          box-shadow 0.2s;
      }

      .security-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-glow);
      }

      .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
      }

      .card-title {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.2rem;
        font-weight: 600;
      }

      .card-title i {
        font-size: 1.5rem;
        color: var(--primary-color);
      }

      .status-badge {
        padding: 4px 12px;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 500;
      }

      .status-enabled {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary-light);
      }

      .status-disabled {
        background: rgba(239, 68, 68, 0.2);
        color: var(--negative);
      }

      .status-warning {
        background: rgba(255, 167, 38, 0.2);
        color: var(--warning-color);
      }

      .card-description {
        color: var(--text-muted);
        font-size: 0.85rem;
        margin-bottom: 20px;
        line-height: 1.5;
      }

      .card-action {
        margin-top: 16px;
      }

      .security-btn {
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        padding: 10px 20px;
        border-radius: 40px;
        color: var(--text);
        cursor: pointer;
        transition: 0.2s;
        font-weight: 500;
      }

      .security-btn:hover {
        background: var(--primary-soft);
        border-color: var(--primary-color);
        color: var(--primary-color);
      }

      .security-btn.primary {
        background: var(--primary-color);
        color: white;
        border: none;
      }

      .security-btn.primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
      }

      .security-btn.danger {
        background: rgba(239, 68, 68, 0.2);
        border-color: var(--negative);
        color: var(--negative);
      }

      .security-btn.danger:hover {
        background: var(--negative);
        color: white;
      }

      /* Activity Log */
      .activity-log {
        background: var(--card-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 24px;
      }

      .activity-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 12px;
      }

      .activity-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 0;
        border-bottom: 1px solid var(--glass-edge);
      }

      .activity-item:last-child {
        border-bottom: none;
      }

      .activity-info {
        display: flex;
        align-items: center;
        gap: 16px;
      }

      .activity-icon {
        width: 40px;
        height: 40px;
        background: var(--primary-soft);
        border-radius: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary-color);
      }

      .activity-details h4 {
        font-size: 0.95rem;
        margin-bottom: 4px;
      }

      .activity-details p {
        font-size: 0.75rem;
        color: var(--text-muted);
      }

      .activity-time {
        font-size: 0.75rem;
        color: var(--text-muted);
      }

      /* Modal — only covers the main column so the sidebar stays clickable (see #securityModal rules below) */
      .modal {
        display: none;
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        justify-content: center;
        align-items: center;
      }

      /* Overlay starts after the sidebar; full-viewport sibling modals would stack above the whole dashboard */
      #securityModal.modal {
        left: 280px;
        width: auto;
        z-index: 1003;
      }

      .sidebar.collapsed ~ .main-content #securityModal.modal {
        left: 80px;
      }

      @media (max-width: 768px) {
        #securityModal.modal {
          left: 0 !important;
          width: 100% !important;
        }
      }

      .modal-content {
        background: var(--card-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 32px;
        max-width: 450px;
        width: 90%;
      }

      .modal-content h3 {
        margin-bottom: 16px;
      }

      .modal-content input {
        width: 100%;
        background: var(--input-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 16px;
        padding: 14px;
        color: var(--text);
        margin: 16px 0;
      }

      .modal-buttons {
        display: flex;
        gap: 12px;
        margin-top: 20px;
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
        .main-content {
          margin-left: 0 !important;
        }
        .sidebar {
          position: fixed;
          transform: translateX(-100%);
          transition: transform 0.3s;
          z-index: 2147483000;
        }
        .sidebar.mobile-open {
          transform: translateX(0);
        }
        .mobile-menu-btn {
          display: block;
        }
        .top-bar {
          left: 0 !important;
        }
        .dashboard-content {
          padding: 16px;
          margin-top: 70px;
        }
        .security-grid {
          grid-template-columns: 1fr;
        }
        .security-score-card {
          flex-direction: column;
          text-align: center;
        }

        #scrollTop {
          right: 1.5rem;
          bottom: 5.4rem;
        }
      }#sidebar {
        z-index: 2147483647 !important;
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
          <div class="user-avatar-large"><?php echo user_branding_h($ub['avatar_letter']); ?></div>
          <div class="user-info">
            <h4><?php echo user_branding_h($ub['full_name']); ?></h4>
            <p><?php echo user_branding_h($ub['wallet_short']); ?></p>
          </div>
        </div>
        <nav class="sidebar-nav">
          <div class="nav-section">MAIN</div>
          <a href="<?php echo htmlspecialchars($__upBase . '/userprofile.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link"
            ><i class="fa-solid fa-chart-pie"></i> <span>Dashboard</span></a
          >
          <a href="<?php echo htmlspecialchars($__upBase . '/trade.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link"
            ><i class="fa-solid fa-chart-line"></i> <span>Trade</span></a
          >
          <a href="<?php echo htmlspecialchars($__upBase . '/wallet.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link"
            ><i class="fa-solid fa-wallet"></i> <span>Wallet</span></a
          >
          <div class="nav-section">TRADING</div>
          <a href="<?php echo htmlspecialchars($__upBase . '/send.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link"
            ><i class="fa-solid fa-paper-plane"></i> <span>Send</span></a
          >
          <a href="<?php echo htmlspecialchars($__upBase . '/receive.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link"
            ><i class="fa-solid fa-inbox"></i> <span>Receive</span></a
          >
          <div class="nav-section">ACCOUNT</div>
          <a href="<?php echo htmlspecialchars($__upBase . '/kycverification.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link"
            ><i class="fa-solid fa-id-card"></i> <span>KYC Verification</span>
            <?php echo kyc_sidebar_badge_span($kycSidebarBadge); ?></a
          >
                    <a href="<?php echo htmlspecialchars($__upBase . '/security.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link active"
            ><i class="fa-solid fa-shield"></i> <span>Security</span></a
          >
          <a href="<?php echo htmlspecialchars($__upBase . '/settings.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link"
            ><i class="fa-solid fa-gear"></i> <span>Settings</span></a
          >
          <div class="nav-section">SUPPORT</div>
          <a href="<?php echo htmlspecialchars($__upBase . '/support.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link"
            ><i class="fa-solid fa-headset"></i> <span>Support</span></a
          >
          <a href="<?php echo htmlspecialchars($__upBase . '/logout.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="sidebar-link"
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
            <a href="<?php echo htmlspecialchars($__upBase . '/userprofile.php', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" class="user-profile">
              <div class="user-avatar"><?php echo user_branding_h($ub['avatar_letter']); ?></div>
              <div class="user-profile-meta">
                <h3 class="user-name">Hello, <?php echo user_branding_h($ub['full_name']); ?></h3>
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
          <div class="security-container">
            <div class="page-header" data-aos="fade-up">
              <h1>
                <i class="fa-solid fa-shield-haltered"></i> Security Settings
              </h1>
              <p style="color: var(--text-muted); margin-top: 8px">
                Protect your account with advanced security features
              </p>
            </div>

            <!-- Security Score Card -->
            <div class="security-score-card" data-aos="fade-up">
              <div class="score-circle">
                <div class="score-inner">
                  <div class="score-number" id="securityScore">78</div>
                  <div class="score-text">/ 100</div>
                </div>
              </div>
              <div class="security-message">
                <h3>
                  <i class="fa-solid fa-chart-line"></i> Your Security Score:
                  Good
                </h3>
                <p>
                  Enable 2FA and withdrawal whitelist to achieve maximum
                  security score of 100.
                </p>
              </div>
              <button class="security-btn primary" id="improveSecurityBtn">
                Improve Security <i class="fa-solid fa-arrow-right"></i>
              </button>
            </div>

            <!-- Security Grid -->
            <div class="security-grid" data-aos="fade-up">
              <!-- 2FA Card -->
              <div class="security-card" id="twofaCard">
                <div class="card-header">
                  <div class="card-title">
                    <i class="fa-solid fa-mobile-alt"></i>
                    <span>Two-Factor Authentication</span>
                  </div>
                  <span class="status-badge status-disabled" id="twofaStatus"
                    >Disabled</span
                  >
                </div>
                <div class="card-description">
                  Add an extra layer of security by requiring a verification
                  code from your mobile device when logging in.
                </div>
                <div class="card-action">
                  <button class="security-btn" id="enableTwofaBtn">
                    Enable 2FA
                  </button>
                </div>
              </div>

              <!-- Withdrawal Whitelist Card -->
              <div class="security-card">
                <div class="card-header">
                  <div class="card-title">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Withdrawal Whitelist</span>
                  </div>
                  <span
                    class="status-badge status-disabled"
                    id="whitelistStatus"
                    >Disabled</span
                  >
                </div>
                <div class="card-description">
                  Restrict withdrawals to only pre-approved wallet addresses for
                  maximum security.
                </div>
                <div class="card-action">
                  <button class="security-btn" id="whitelistBtn">
                    Manage Whitelist
                  </button>
                </div>
              </div>

              <!-- Session Management Card -->
              <div class="security-card">
                <div class="card-header">
                  <div class="card-title">
                    <i class="fa-solid fa-laptop"></i>
                    <span>Active Sessions</span>
                  </div>
                  <span class="status-badge status-enabled">1 Active</span>
                </div>
                <div class="card-description">
                  Manage devices that are currently logged into your account.
                </div>
                <div class="card-action">
                  <button class="security-btn" id="sessionsBtn">
                    View Sessions
                  </button>
                </div>
              </div>

              <!-- Login Alerts Card -->
              <div class="security-card">
                <div class="card-header">
                  <div class="card-title">
                    <i class="fa-solid fa-envelope"></i>
                    <span>Login Alerts</span>
                  </div>
                  <span class="status-badge status-enabled" id="alertsStatus"
                    >Enabled</span
                  >
                </div>
                <div class="card-description">
                  Receive email notifications for new login attempts and
                  suspicious activities.
                </div>
                <div class="card-action">
                  <button class="security-btn" id="alertsBtn">Configure</button>
                </div>
              </div>

              <!-- Change Password Card -->
              <div class="security-card">
                <div class="card-header">
                  <div class="card-title">
                    <i class="fa-solid fa-key"></i>
                    <span>Password</span>
                  </div>
                  <span class="status-badge status-enabled" id="passwordLastChangedBadge"
                    >Last changed —</span
                  >
                </div>
                <div class="card-description">
                  Change your password regularly to keep your account secure.
                </div>
                <div class="card-action">
                  <button class="security-btn" id="changePasswordBtn">
                    Change Password
                  </button>
                </div>
              </div>

              <!-- API Keys Card -->
              <div class="security-card">
                <div class="card-header">
                  <div class="card-title">
                    <i class="fa-solid fa-code"></i>
                    <span>API Keys</span>
                  </div>
                  <span class="status-badge status-disabled">No Keys</span>
                </div>
                <div class="card-description">
                  Generate API keys for programmatic access to your account.
                </div>
                <div class="card-action">
                  <button class="security-btn" id="apiKeysBtn">
                    Manage API Keys
                  </button>
                </div>
              </div>
            </div>

            <!-- Activity Log -->
            <div class="activity-log" data-aos="fade-up">
              <div class="activity-header">
                <h3>
                  <i class="fa-solid fa-clock-rotate-left"></i> Recent Security
                  Activity
                </h3>
                <button class="security-btn" id="clearActivityBtn">
                  Clear All
                </button>
              </div>
              <div id="activityList">
                <div
                  style="
                    text-align: center;
                    padding: 40px;
                    color: var(--text-muted);
                  "
                >
                  Loading activity…
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal (inside main so #securityModal + .sidebar.collapsed ~ .main-content selector works) -->
        <div id="securityModal" class="modal" style="display: none" aria-hidden="true">
          <div class="modal-content">
            <i
              class="fa-solid fa-shield"
              style="font-size: 3rem; color: var(--primary-color)"
            ></i>
            <h3 id="modalTitle">Enable 2FA</h3>
            <p id="modalMessage">
              Scan the QR code with Google Authenticator or similar app.
            </p>
            <div id="modalExtra"></div>
            <div class="modal-buttons">
              <button class="modal-btn cancel" id="modalCancelBtn">Cancel</button>
              <button class="modal-btn confirm" id="modalConfirmBtn">
                Confirm
              </button>
            </div>
          </div>
        </div>
      </main>
    </div>

    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="includes/user_theme.js"></script>
    <script>
      // Sidebar nav MUST register before AOS: if aos.js fails to load, AOS.init throws and nothing below ran before.
      (function () {
        const sb = document.getElementById("sidebar");
        if (!sb) return;
        sb.addEventListener(
          "click",
          function (e) {
            const a = e.target && e.target.closest && e.target.closest("a.sidebar-link");
            if (!a) return;
            const href = a.getAttribute("href");
            if (!href || href === "#" || href.trim().startsWith("javascript:")) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            window.location.href = a.href;
          },
          true
        );
      })();

      if (typeof AOS !== "undefined" && typeof AOS.init === "function") {
        try {
          AOS.init({ duration: 500, once: true });
        } catch (e) {}
      }

      const SECURITY_API = "api/security_api.php";
      const SESSIONS_API = "api/sessions_api.php";
      let CSRF_TOKEN = <?php echo json_encode($userCsrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

      // Security settings state
      let twofaEnabled = false;
      let whitelistEnabled = false;
      let alertsEnabled = true;
      let securityScore = 78;

      function passwordChangedLabel(iso) {
        if (!iso || typeof iso !== "string") return "Last changed —";
        const t = new Date(iso.replace(" ", "T"));
        if (Number.isNaN(t.getTime())) return "Recently updated";
        const days = Math.floor((Date.now() - t.getTime()) / 86400000);
        if (days <= 0) return "Changed today";
        if (days === 1) return "Last changed 1 day ago";
        return "Last changed " + days + " days ago";
      }

      async function postSecurity(action, body) {
        const r = await fetch(SECURITY_API, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ ...(body || {}), action, csrf_token: CSRF_TOKEN }),
        });
        const j = await r.json().catch(() => ({}));
        if (j && typeof j.csrf === "string") CSRF_TOKEN = j.csrf;
        return j;
      }

      function escHtml(s) {
        if (s == null) return "";
        return String(s)
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#39;");
      }

      function formatRelativeTime(iso) {
        if (!iso || typeof iso !== "string") return "";
        const t = new Date(iso.replace(" ", "T"));
        if (Number.isNaN(t.getTime())) return iso;
        const diff = Date.now() - t.getTime();
        const sec = Math.floor(diff / 1000);
        if (sec < 45) return "Just now";
        const min = Math.floor(sec / 60);
        if (min < 60) return min <= 1 ? "1 minute ago" : min + " minutes ago";
        const hr = Math.floor(min / 60);
        if (hr < 24) return hr === 1 ? "1 hour ago" : hr + " hours ago";
        const day = Math.floor(hr / 24);
        if (day < 7) return day === 1 ? "1 day ago" : day + " days ago";
        const week = Math.floor(day / 7);
        if (week < 5) return week === 1 ? "1 week ago" : week + " weeks ago";
        return t.toLocaleDateString(undefined, {
          year: "numeric",
          month: "short",
          day: "numeric",
        });
      }

      function renderActivityList(rows) {
        const activityList = document.getElementById("activityList");
        if (!activityList) return;
        if (!Array.isArray(rows) || rows.length === 0) {
          activityList.innerHTML =
            '<div style="text-align: center; padding: 40px; color: var(--text-muted);">No recent activity</div>';
          return;
        }
        activityList.innerHTML = rows
          .map((a) => {
            const icon = escHtml((a.icon_class || "fa-solid fa-shield-halved").toString());
            const title = escHtml((a.title || "").toString());
            const detail = escHtml((a.detail || "").toString());
            const when = formatRelativeTime((a.created_at || "").toString());
            return `
                <div class="activity-item">
                  <div class="activity-info">
                    <div class="activity-icon">
                      <i class="${icon}"></i>
                    </div>
                    <div class="activity-details">
                      <h4>${title}</h4>
                      <p>${detail}</p>
                    </div>
                  </div>
                  <div class="activity-time">${escHtml(when)}</div>
                </div>`;
          })
          .join("");
      }

      async function fetchSessions() {
        const r = await fetch(SESSIONS_API, { method: "GET", credentials: "same-origin" });
        const j = await r.json().catch(() => ({}));
        if (j && typeof j.csrf === "string") CSRF_TOKEN = j.csrf;
        return j;
      }

      async function terminateSession(sessionId) {
        const r = await fetch(SESSIONS_API, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          credentials: "same-origin",
          body: JSON.stringify({ action: "terminate", session_id: sessionId, csrf_token: CSRF_TOKEN }),
        });
        const j = await r.json().catch(() => ({}));
        if (j && typeof j.csrf === "string") CSRF_TOKEN = j.csrf;
        return j;
      }

      function bindTerminateSessionButtons(box) {
        if (!box) return;
        box.querySelectorAll(".terminate-session-btn").forEach((btn) => {
          btn.addEventListener("click", async () => {
            const id = parseInt(btn.getAttribute("data-session-id") || "0", 10);
            if (!id) return;
            const r = await terminateSession(id);
            if (!r.ok) {
              showNotification(r.error || "Could not terminate session", "error");
              return;
            }
            showNotification("Session terminated successfully", "success");
            await loadSecurityState();
            await fetchAndRenderSessionsList();
          });
        });
      }

      async function fetchAndRenderSessionsList() {
        const box = document.getElementById("sessionsBox");
        if (!box) return;
        box.innerHTML = "Loading sessions…";
        const j = await fetchSessions();
        if (!j.ok) {
          box.innerHTML = "Could not load sessions.";
          return;
        }
        const sessions = Array.isArray(j.sessions) ? j.sessions : [];
        if (!sessions.length) {
          box.innerHTML = "No active sessions found.";
          return;
        }
        box.innerHTML = sessions
          .map((s) => {
            const ip = (s.ip || "—").toString();
            const uaRaw = (s.user_agent || "").toString();
            const uaShort =
              uaRaw.length > 100 ? uaRaw.slice(0, 100) + "…" : uaRaw;
            const deviceLine = (s.device_label || uaRaw || "—").toString();
            const last = (s.last_seen_at || "").toString();
            const label = s.is_current
              ? "Current session"
              : last
                ? `Last seen: ${last}`
                : "Active";
            const bg = s.is_current ? "var(--primary-soft)" : "var(--surface)";
            const sid = parseInt(String(s.id), 10) || 0;
            const right = s.is_current
              ? '<span style="color: var(--primary-color);">Active</span>'
              : `<button type="button" class="terminate-session-btn" data-session-id="${sid}" style="background:none;border:none;color:var(--negative);cursor:pointer;">Terminate</button>`;
            const uaRow = uaShort
              ? `<div style="font-size:0.75rem;opacity:0.85;margin-top:4px;word-break:break-all;">${escHtml(uaShort)}</div>`
              : "";
            return `
                <div style="background:${bg}; padding:12px; border-radius:12px; margin-bottom:12px; display:flex; justify-content:space-between; gap:12px;">
                  <div style="min-width:0;">
                    <div><i class="fa-solid fa-laptop"></i> <strong>${escHtml(deviceLine)}</strong></div>
                    ${uaRow}
                    <small>IP: ${escHtml(ip)} • ${escHtml(label)}</small>
                  </div>
                  ${right}
                </div>`;
          })
          .join("");
        bindTerminateSessionButtons(box);
      }

      function applySecurityState(state) {
        if (!state || typeof state !== "object") return;
        twofaEnabled = !!state.twofa_enabled;
        alertsEnabled = !!state.login_alerts_enabled;

        const twEl = document.getElementById("twofaStatus");
        if (twEl) {
          twEl.innerText = twofaEnabled ? "Enabled" : "Disabled";
          twEl.className =
            "status-badge " + (twofaEnabled ? "status-enabled" : "status-disabled");
        }
        const alEl = document.getElementById("alertsStatus");
        if (alEl) {
          alEl.innerText = alertsEnabled ? "Enabled" : "Disabled";
          alEl.className =
            "status-badge " + (alertsEnabled ? "status-enabled" : "status-disabled");
        }
        const pwEl = document.getElementById("passwordLastChangedBadge");
        if (pwEl) {
          pwEl.innerText = passwordChangedLabel(state.password_changed_at);
          pwEl.className = "status-badge status-enabled";
        }
        updateSecurityScore();
      }

      async function loadSecurityState() {
        const r = await fetch(SECURITY_API, { method: "GET", credentials: "same-origin" });
        const j = await r.json().catch(() => ({}));
        if (!j.ok) {
          const activityList = document.getElementById("activityList");
          if (activityList) {
            activityList.innerHTML =
              '<div style="text-align: center; padding: 40px; color: var(--text-muted);">Could not load activity.</div>';
          }
          return;
        }
        if (typeof j.csrf === "string") CSRF_TOKEN = j.csrf;
        applySecurityState(j.state);
        if (Array.isArray(j.activity)) renderActivityList(j.activity);
      }

      // Update security score
      function updateSecurityScore() {
        let score = 0;
        if (twofaEnabled) score += 35;
        else score += 0;
        if (whitelistEnabled) score += 35;
        else score += 0;
        if (alertsEnabled) score += 15;
        else score += 0;
        score += 15; // Base score for having a password

        securityScore = score;
        const scoreNumEl = document.getElementById("securityScore");
        if (scoreNumEl) scoreNumEl.innerText = String(securityScore);

        // Update the ring fill to match the numeric score.
        const circle = document.querySelector(".score-circle");
        if (circle) {
          const deg = Math.max(0, Math.min(100, securityScore)) * 3.6;
          circle.style.background = `conic-gradient(var(--primary-color) 0deg ${deg}deg, var(--surface) ${deg}deg 360deg)`;
        }

        const scoreMessage = document.querySelector(".security-message p");
        if (scoreMessage) {
          if (securityScore >= 85) {
            scoreMessage.innerHTML = "Excellent! Your account is highly secure.";
          } else if (securityScore >= 60) {
            scoreMessage.innerHTML =
              "Good security. Enable 2FA and whitelist for maximum protection.";
          } else {
            scoreMessage.innerHTML =
              "Your account needs attention. Enable security features to protect your funds.";
          }
        }
      }

      // Show notification
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

      // Modal handling
      const modal = document.getElementById("securityModal");
      const modalTitle = document.getElementById("modalTitle");
      const modalMessage = document.getElementById("modalMessage");
      const modalExtra = document.getElementById("modalExtra");
      const modalConfirm = document.getElementById("modalConfirmBtn");
      const modalCancel = document.getElementById("modalCancelBtn");
      let currentAction = null;

      function closeModal() {
        if (!modal) return;
        modal.style.display = "none";
        modal.setAttribute("aria-hidden", "true");
        currentAction = null;
        if (modalExtra) modalExtra.innerHTML = "";
      }

      modalCancel?.addEventListener("click", closeModal);
      window.addEventListener("click", (e) => {
        if (e.target === modal) closeModal();
      });

      // 2FA Button
      document
        .getElementById("enableTwofaBtn")
        ?.addEventListener("click", () => {
          if (!twofaEnabled) {
            modalTitle.innerText = "Enable Two-Factor Authentication";
            modalMessage.innerText =
              "Scan the QR code with Google Authenticator or Authy:";
            modalExtra.innerHTML = `
        <div style="background: white; padding: 16px; border-radius: 16px; margin: 16px 0; text-align: center;">
          <i class="fas fa-qrcode" style="font-size: 4rem; color: black;"></i>
          <p style="font-size: 0.75rem; margin-top: 8px;">Setup Key: CRYP-2FA-${Math.random().toString(36).substring(2, 10).toUpperCase()}</p>
        </div>
        <input type="text" id="twofaCode" placeholder="Enter 6-digit code" maxlength="6">
      `;
            currentAction = "enable2fa";
            modal.style.display = "flex";
          } else {
            modalTitle.innerText = "Disable Two-Factor Authentication";
            modalMessage.innerText =
              "Enter your 2FA code to disable this feature:";
            modalExtra.innerHTML = `<input type="text" id="twofaCode" placeholder="Enter 6-digit code" maxlength="6">`;
            currentAction = "disable2fa";
            modal.style.display = "flex";
          }
        });

      // Whitelist Button
      document.getElementById("whitelistBtn")?.addEventListener("click", () => {
        modalTitle.innerText = "Withdrawal Whitelist";
        modalMessage.innerText = "Manage approved withdrawal addresses:";
        modalExtra.innerHTML = `
      <div style="margin: 16px 0;">
        <div style="background: var(--primary-soft); padding: 12px; border-radius: 12px; margin-bottom: 12px;">
          <i class="fa-solid fa-check-circle" style="color: var(--primary-color);"></i> bc1q3jpcmus3ms2hrc2wqq36l6damvnvm6uuygevp0
        </div>
        <div style="display: flex; gap: 8px; margin-top: 12px;">
          <input type="text" id="newAddress" placeholder="Add new address" style="flex: 1;">
          <button id="addAddressBtn" style="background: var(--primary-color); border: none; padding: 10px 16px; border-radius: 40px; color: white; cursor: pointer;">Add</button>
        </div>
      </div>
    `;
        currentAction = "whitelist";
        modal.style.display = "flex";

        setTimeout(() => {
          const addBtn = document.getElementById("addAddressBtn");
          if (addBtn) {
            addBtn.addEventListener("click", () => {
              const newAddr = document.getElementById("newAddress").value;
              if (newAddr) {
                showNotification(
                  `Address ${newAddr.substring(0, 10)}... added to whitelist`,
                  "success",
                );
                if (!whitelistEnabled) {
                  whitelistEnabled = true;
                  document.getElementById("whitelistStatus").innerText =
                    "Enabled";
                  document.getElementById("whitelistStatus").className =
                    "status-badge status-enabled";
                  updateSecurityScore();
                }
                closeModal();
              }
            });
          }
        }, 100);
      });

      // Sessions Button
      document.getElementById("sessionsBtn")?.addEventListener("click", () => {
        modalTitle.innerText = "Active Sessions";
        modalMessage.innerText = "Manage your active sessions:";
        modalExtra.innerHTML =
          '<div id="sessionsBox" style="margin: 16px 0; color: var(--text-secondary);">Loading sessions…</div>';
        currentAction = "sessions";
        modal.style.display = "flex";

        setTimeout(() => fetchAndRenderSessionsList(), 50);
      });

      // Alerts Button
      document.getElementById("alertsBtn")?.addEventListener("click", () => {
        modalTitle.innerText = "Login Alerts";
        modalMessage.innerText = "Configure email notifications:";
        modalExtra.innerHTML = `
      <div style="margin: 16px 0;">
        <label style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; cursor: pointer;">
          <input type="checkbox" id="emailAlerts" ${alertsEnabled ? "checked" : ""}> Receive email alerts for new logins
        </label>
        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
          <input type="checkbox" id="withdrawAlerts" checked> Receive alerts for withdrawal attempts
        </label>
      </div>
    `;
        currentAction = "alerts";
        modal.style.display = "flex";
      });

      // Change Password
      document
        .getElementById("changePasswordBtn")
        ?.addEventListener("click", () => {
          modalTitle.innerText = "Change Password";
          modalMessage.innerText =
            "Enter your current password and new password:";
          modalExtra.innerHTML = `
      <input type="password" id="currentPw" placeholder="Current Password">
      <input type="password" id="newPw" placeholder="New Password">
      <input type="password" id="confirmPw" placeholder="Confirm New Password">
    `;
          currentAction = "password";
          modal.style.display = "flex";
        });

      // API Keys Button
      document.getElementById("apiKeysBtn")?.addEventListener("click", () => {
        modalTitle.innerText = "API Keys";
        modalMessage.innerText = "Manage your API access:";
        modalExtra.innerHTML = `
      <div style="margin: 16px 0; text-align: center;">
        <p style="color: var(--text-muted); margin-bottom: 16px;">No API keys generated yet.</p>
        <button id="generateApiBtn" style="background: var(--primary-color); border: none; padding: 10px 20px; border-radius: 40px; color: white; cursor: pointer;">Generate New Key</button>
      </div>
    `;
        currentAction = "api";
        modal.style.display = "flex";

        setTimeout(() => {
          const generateBtn = document.getElementById("generateApiBtn");
          if (generateBtn) {
            generateBtn.addEventListener("click", () => {
              showNotification(
                "New API key generated: cryp_xyz123...",
                "success",
              );
              closeModal();
            });
          }
        }, 100);
      });

      // Improve Security Button
      document
        .getElementById("improveSecurityBtn")
        ?.addEventListener("click", () => {
          if (!twofaEnabled) {
            document.getElementById("enableTwofaBtn").click();
          } else if (!whitelistEnabled) {
            document.getElementById("whitelistBtn").click();
          } else {
            showNotification("Your security is already optimized!", "success");
          }
        });

      // Modal Confirm handler
      modalConfirm?.addEventListener("click", async () => {
        if (currentAction === "enable2fa") {
          const code = document.getElementById("twofaCode")?.value;
          if (!code || String(code).replace(/\D/g, "").length !== 6) {
            showNotification("Invalid 2FA code. Please try again.", "error");
            return;
          }
          const j = await postSecurity("enable_2fa", {
            code: String(code).replace(/\D/g, ""),
          });
          if (j.ok) {
            applySecurityState(j.state);
            if (Array.isArray(j.activity)) renderActivityList(j.activity);
            showNotification("2FA enabled successfully!", "success");
            closeModal();
          } else {
            showNotification(j.error || "Could not enable 2FA.", "error");
          }
        } else if (currentAction === "disable2fa") {
          const code = document.getElementById("twofaCode")?.value;
          if (!code || String(code).replace(/\D/g, "").length !== 6) {
            showNotification("Enter your 6-digit code to disable 2FA.", "error");
            return;
          }
          const j = await postSecurity("disable_2fa", {
            code: String(code).replace(/\D/g, ""),
          });
          if (j.ok) {
            applySecurityState(j.state);
            if (Array.isArray(j.activity)) renderActivityList(j.activity);
            showNotification("2FA disabled", "info");
            closeModal();
          } else {
            showNotification(j.error || "Could not disable 2FA.", "error");
          }
        } else if (currentAction === "alerts") {
          const on = document.getElementById("emailAlerts")?.checked || false;
          const j = await postSecurity("set_alerts", { enabled: on });
          if (j.ok) {
            applySecurityState(j.state);
            if (Array.isArray(j.activity)) renderActivityList(j.activity);
            showNotification("Alert settings updated", "success");
            closeModal();
          } else {
            showNotification(j.error || "Could not update alerts.", "error");
          }
        } else if (currentAction === "password") {
          const currentPw = document.getElementById("currentPw")?.value ?? "";
          const newPw = document.getElementById("newPw")?.value ?? "";
          const confirmPw = document.getElementById("confirmPw")?.value ?? "";
          if (!newPw || newPw.length < 8) {
            showNotification("New password must be at least 8 characters.", "error");
            return;
          }
          if (newPw !== confirmPw) {
            showNotification("Passwords do not match.", "error");
            return;
          }
          const j = await postSecurity("change_password", {
            current_password: currentPw,
            new_password: newPw,
          });
          if (j.ok) {
            applySecurityState(j.state);
            if (Array.isArray(j.activity)) renderActivityList(j.activity);
            showNotification("Password changed successfully!", "success");
            closeModal();
          } else {
            showNotification(j.error || "Could not change password.", "error");
          }
        } else {
          closeModal();
        }
      });

      // Clear Activity
      document
        .getElementById("clearActivityBtn")
        ?.addEventListener("click", async () => {
          const j = await postSecurity("clear_activity", {});
          if (j.ok) {
            if (typeof j.csrf === "string") CSRF_TOKEN = j.csrf;
            if (Array.isArray(j.activity)) renderActivityList(j.activity);
            showNotification("Activity log cleared", "info");
          } else {
            showNotification(j.error || "Could not clear activity.", "error");
          }
        });

      // Sidebar functionality
      const sidebar = document.getElementById("sidebar");
      const collapseBtn = document.getElementById("collapseSidebar");
      const mobileBtn = document.getElementById("mobileMenuBtn");

      collapseBtn?.addEventListener("click", () => {
        sidebar?.classList.toggle("collapsed");
        // Update top-bar left position via CSS class (handled by CSS rule)
      });

      mobileBtn?.addEventListener("click", () =>
        sidebar?.classList.toggle("mobile-open"),
      );

      document.addEventListener("click", (e) => {
        if (
          window.innerWidth <= 768 &&
          sidebar &&
          mobileBtn &&
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
        if (!scrollBtn) return;
        if (window.scrollY > 400) scrollBtn.style.display = "flex";
        else scrollBtn.style.display = "none";
      });
      scrollBtn?.addEventListener("click", () =>
        window.scrollTo({ top: 0, behavior: "smooth" }),
      );

      // Notification bell
      const notifBtn = document.getElementById("notifications-btn");
      notifBtn?.addEventListener("click", () => {
        showNotification(
          "🔒 Enable 2FA to increase your security score to 100%!",
          "info",
        );
      });

      // Initialize
      loadSecurityState();
    </script>
  <?php include __DIR__ . '/../includes/smartsupp.php'; ?>
  </body>
</html>
