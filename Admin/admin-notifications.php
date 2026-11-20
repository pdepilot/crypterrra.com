<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_csrf.php';
$ADMIN_PAGE_CSRF = admin_csrf_token();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · Admin · Notification Center</title>
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
    <!-- Quill Rich Text Editor -->
    <link
      href="https://cdn.quilljs.com/1.3.6/quill.snow.css"
      rel="stylesheet"
    />
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
        display: flex;
        align-items: center;
        justify-content: center;
      }

      .analytics-icon.total {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }
      .analytics-icon.delivery {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }
      .analytics-icon.open {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary);
      }
      .analytics-icon.engagement {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
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

      /* Notification Composer */
      .composer-card {
        background: var(--card-bg);
        backdrop-filter: blur(16px);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.8rem;
      }

      .composer-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1.5rem;
        color: var(--primary);
      }

      .composer-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1rem;
      }

      @media (max-width: 768px) {
        .composer-grid {
          grid-template-columns: 1fr;
        }
      }

      .composer-field {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
      }

      .composer-field label {
        font-size: 0.85rem;
        color: var(--text-muted);
      }

      .composer-field input,
      .composer-field select,
      .composer-field textarea {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.8rem 1rem;
        color: var(--text);
        font-size: 0.95rem;
        outline: none;
        transition: 0.2s;
      }

      .composer-field input:focus,
      .composer-field select:focus,
      .composer-field textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px var(--primary-soft);
      }

      .composer-field.full-width {
        grid-column: span 3;
      }

      @media (max-width: 768px) {
        .composer-field.full-width {
          grid-column: span 1;
        }
      }

      /* Rich Text Editor */
      .ql-toolbar.ql-snow {
        border: 1px solid var(--glass-edge);
        border-radius: 40px 40px 0 0;
        background: var(--glass-bg);
      }

      .ql-container.ql-snow {
        border: 1px solid var(--glass-edge);
        border-radius: 0 0 40px 40px;
        background: var(--glass-bg);
        min-height: 150px;
      }

      .ql-editor {
        color: var(--text);
      }

      .ql-snow .ql-stroke {
        stroke: var(--text-muted);
      }

      .ql-snow .ql-fill {
        fill: var(--text-muted);
      }

      .ql-snow .ql-picker {
        color: var(--text-muted);
      }

      .ql-snow.ql-toolbar button:hover .ql-stroke {
        stroke: var(--primary);
      }

      .ql-snow.ql-toolbar button:hover .ql-fill {
        fill: var(--primary);
      }

      /* Channel Pills */
      .channel-pills {
        display: flex;
        gap: 1rem;
        margin: 1rem 0;
        flex-wrap: wrap;
      }

      .channel-pill {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0.5rem 1.2rem;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        cursor: pointer;
        transition: 0.2s;
      }

      .channel-pill:hover {
        border-color: var(--primary);
      }

      .channel-pill.active {
        background: var(--primary-soft);
        border-color: var(--primary);
      }

      .channel-pill input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
      }

      /* Quick Alert Buttons */
      .quick-alerts {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.8rem;
        flex-wrap: wrap;
      }

      .quick-alert-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0.8rem 1.5rem;
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        cursor: pointer;
        transition: 0.2s;
      }

      .quick-alert-btn:hover {
        transform: translateY(-2px);
      }

      .quick-alert-btn.maintenance {
        border-left: 4px solid var(--warning);
      }
      .quick-alert-btn.security {
        border-left: 4px solid var(--danger);
      }
      .quick-alert-btn.market {
        border-left: 4px solid var(--info);
      }

      /* Templates Grid */
      .templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.8rem;
      }

      .template-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        padding: 1rem;
        cursor: pointer;
        transition: 0.2s;
      }

      .template-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
      }

      .template-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.8rem;
      }

      .template-icon.welcome {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }
      .template-icon.deposit {
        background: rgba(34, 197, 94, 0.2);
        color: var(--primary);
      }
      .template-icon.withdrawal {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }
      .template-icon.security {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .template-title {
        font-weight: 600;
        margin-bottom: 0.2rem;
      }

      .template-preview {
        font-size: 0.8rem;
        color: var(--text-muted);
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
        padding: 0.8rem 2rem;
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
        padding: 0.6rem 1.2rem;
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

      /* Status Badges */
      .status-badge {
        padding: 0.25rem 0.8rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
      }

      .status-sent {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .status-pending {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .status-failed {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      /* Type Badges */
      .type-badge {
        padding: 0.25rem 0.8rem;
        border-radius: 40px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
      }

      .type-info {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }

      .type-success {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }

      .type-warning {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }

      .type-alert {
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
        min-width: 150px;
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

      /* Live Feed */
      .feed-panel {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-top: 1.5rem;
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
        flex-wrap: wrap;
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
        flex-shrink: 0;
      }

      .feed-icon.info {
        background: rgba(59, 130, 246, 0.2);
        color: var(--info);
      }
      .feed-icon.success {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
      }
      .feed-icon.warning {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
      }
      .feed-icon.alert {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
      }

      /* Chart container */
      .chart-container {
        background: var(--card-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
      }

      .chart-header {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 1rem;
        color: var(--primary);
      }

      .chart-wrapper {
        height: 200px;
        position: relative;
      }

      /* Two column layout */
      .two-column {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin-bottom: 1.8rem;
      }

      @media (max-width: 768px) {
        .two-column {
          grid-template-columns: 1fr;
        }
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

      /* Toast Notification */
      .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--surface);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: var(--shadow-glow);
        z-index: 2000;
        animation: slideInRight 0.3s;
      }

      @keyframes slideInRight {
        from {
          transform: translateX(100%);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }

      .toast.success {
        border-left: 4px solid var(--success);
      }
      .toast.error {
        border-left: 4px solid var(--danger);
      }
      .toast.warning {
        border-left: 4px solid var(--warning);
      }

      /* Responsive */
      @media (max-width: 1100px) {
        .mobile-menu-btn {
          display: flex;
        }
      }

      @media (max-width: 768px) {
        .content {
          padding: 1rem;
        }
        .analytics-value {
          font-size: 1.5rem;
        }
        .quick-alerts {
          flex-direction: column;
        }
        .quick-alert-btn {
          width: 100%;
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
      <!-- SIDEBAR (active on Notifications) -->
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
          <a href="./admin-analytics.php" class="sidebar-link"
            ><i class="fa-solid fa-magnifying-glass-chart"></i>
            <span>Analytics</span></a
          >
          <a href="./admin-notifications.php" class="sidebar-link active"
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
              <input type="text" placeholder="Search notifications..." />
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

        <!-- NOTIFICATION CENTER CONTENT -->
        <div class="content">
          <!-- Page Header -->
          <div class="page-header" data-aos="fade-up">
            <h1>Notification Center</h1>
            <p>
              Manage and send notifications across the platform.
              <span id="adminLiveInboxHint" style="color: var(--primary); font-weight: 600"></span>
            </p>
            <div class="breadcrumb">
              <a href="#">Admin</a> <span>/</span>
              <span style="color: var(--primary)">Notifications</span>
            </div>
          </div>

          <!-- Analytics Cards (values from database via API) -->
          <div class="analytics-grid" data-aos="fade-up">
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon total"
                  ><i class="fa-solid fa-bell"></i
                ></span>
              </div>
              <div class="analytics-value" id="totalNotifications">—</div>
              <div class="analytics-label">Total Notifications Sent</div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon delivery"
                  ><i class="fa-solid fa-check-circle"></i
                ></span>
              </div>
              <div class="analytics-value" id="deliveryRate">—</div>
              <div class="analytics-label">Delivery rate (sent vs all statuses)</div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon open"
                  ><i class="fa-solid fa-envelope"></i
                ></span>
              </div>
              <div class="analytics-value" id="openRate">—</div>
              <div class="analytics-label">Email attempts (last send)</div>
            </div>
            <div class="analytics-card">
              <div class="analytics-header">
                <span class="analytics-icon engagement"
                  ><i class="fa-solid fa-mobile-screen"></i
                ></span>
              </div>
              <div class="analytics-value" id="engagement">—</div>
              <div class="analytics-label">In-app / dashboard deliveries</div>
            </div>
          </div>

          <!-- Quick Alert Buttons -->
          <div class="quick-alerts" data-aos="fade-up">
            <button
              class="quick-alert-btn maintenance"
              onclick="quickAlert('maintenance')"
            >
              <i class="fa-solid fa-tools" style="color: var(--warning)"></i>
              <span>Maintenance Alert</span>
            </button>
            <button
              class="quick-alert-btn security"
              onclick="quickAlert('security')"
            >
              <i class="fa-solid fa-shield" style="color: var(--danger)"></i>
              <span>Security Alert</span>
            </button>
            <button
              class="quick-alert-btn market"
              onclick="quickAlert('market')"
            >
              <i class="fa-solid fa-chart-line" style="color: var(--info)"></i>
              <span>Market Update</span>
            </button>
            <button class="btn-outline" onclick="showTemplatePanel()">
              <i class="fa-regular fa-file-lines"></i> Templates
            </button>
          </div>

          <!-- Templates Grid (Hidden by default) -->
          <div
            class="templates-grid"
            id="templatesGrid"
            style="display: none"
            data-aos="fade-up"
          >
            <div class="template-card" onclick="useTemplate('welcome')">
              <div class="template-icon welcome">
                <i class="fa-solid fa-hand-wave"></i>
              </div>
              <div class="template-title">Welcome Message</div>
              <div class="template-preview">
                Welcome to Crypterra! Get started with...
              </div>
            </div>
            <div class="template-card" onclick="useTemplate('deposit')">
              <div class="template-icon deposit">
                <i class="fa-solid fa-arrow-down"></i>
              </div>
              <div class="template-title">Deposit Success</div>
              <div class="template-preview">
                Your deposit of {amount} has been confirmed...
              </div>
            </div>
            <div class="template-card" onclick="useTemplate('withdrawal')">
              <div class="template-icon withdrawal">
                <i class="fa-solid fa-arrow-up"></i>
              </div>
              <div class="template-title">Withdrawal Alert</div>
              <div class="template-preview">
                Your withdrawal of {amount} is being processed...
              </div>
            </div>
            <div class="template-card" onclick="useTemplate('security')">
              <div class="template-icon security">
                <i class="fa-solid fa-shield"></i>
              </div>
              <div class="template-title">Security Warning</div>
              <div class="template-preview">
                Suspicious login detected from new device...
              </div>
            </div>
          </div>

          <!-- Notification Composer -->
          <div class="composer-card" data-aos="fade-up">
            <div class="composer-header">
              <i class="fa-solid fa-pen"></i>
              <h3>Send Notification</h3>
            </div>
            <div class="composer-grid">
              <div class="composer-field">
                <label>Recipient</label>
                <select id="recipientSelect">
                  <option value="all">All Users</option>
                  <option value="specific">Specific User</option>
                  <option value="verified">Verified Users</option>
                  <option value="unverified">Unverified Users</option>
                  <option value="high-value">High-Value Users (>$10k)</option>
                </select>
              </div>
              <div class="composer-field">
                <label>Specific User (if selected)</label>
                <input type="hidden" id="specificUserId" value="" />
                <input
                  type="text"
                  id="specificUser"
                  placeholder="Type name, email, wallet ID, or user # — pick from list"
                  disabled
                  autocomplete="off"
                />
                <div
                  id="userSearchResults"
                  style="
                    display: none;
                    margin-top: 8px;
                    max-height: 180px;
                    overflow-y: auto;
                    border: 1px solid var(--glass-edge);
                    border-radius: 16px;
                    background: var(--glass-bg);
                  "
                ></div>
              </div>
              <div class="composer-field">
                <label>Notification Type</label>
                <select id="typeSelect">
                  <option value="info">Info</option>
                  <option value="success">Success</option>
                  <option value="warning">Warning</option>
                  <option value="alert">Alert</option>
                </select>
              </div>
              <div class="composer-field">
                <label>Title</label>
                <input
                  type="text"
                  id="titleInput"
                  placeholder="Notification title"
                />
              </div>
              <div class="composer-field full-width">
                <label>Message</label>
                <div id="editor"></div>
              </div>
            </div>

            <div class="channel-pills">
              <label class="channel-pill">
                <input type="checkbox" class="channel-checkbox" checked />
                <i class="fa-solid fa-bell"></i> In-app
              </label>
              <label class="channel-pill">
                <input type="checkbox" class="channel-checkbox" checked />
                <i class="fa-solid fa-envelope"></i> Email
              </label>
              <label class="channel-pill">
                <input type="checkbox" class="channel-checkbox" />
                <i class="fa-solid fa-mobile"></i> Push
              </label>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end">
              <button class="btn-outline" onclick="clearComposer()">
                Clear
              </button>
              <button class="btn-primary" onclick="sendNotification()">
                <i class="fa-solid fa-paper-plane"></i> Send Notification
              </button>
            </div>
          </div>

          <!-- Charts Section -->
          <div class="two-column" data-aos="fade-up">
            <!-- Notifications Over Time Chart -->
            <div class="chart-container">
              <div class="chart-header">
                <i class="fa-solid fa-chart-line"></i>
                <h4>Notifications Over Time</h4>
              </div>
              <div class="chart-wrapper">
                <canvas id="notificationsChart"></canvas>
              </div>
            </div>

            <!-- User Engagement Chart -->
            <div class="chart-container">
              <div class="chart-header">
                <i class="fa-solid fa-chart-pie"></i>
                <h4>User Engagement</h4>
              </div>
              <div class="chart-wrapper">
                <canvas id="engagementChart"></canvas>
              </div>
            </div>
          </div>

          <!-- Search & Filters -->
          <div class="control-bar" data-aos="fade-up">
            <div class="control-search">
              <i class="fa-solid fa-search"></i>
              <input
                type="text"
                id="searchInput"
                placeholder="Search by title, recipient..."
              />
            </div>
            <div class="control-filters">
              <select class="filter-select" id="typeFilter">
                <option>All Types</option>
                <option>Info</option>
                <option>Success</option>
                <option>Warning</option>
                <option>Alert</option>
              </select>
              <select class="filter-select" id="statusFilter">
                <option>All Status</option>
                <option>Sent</option>
                <option>Pending</option>
                <option>Failed</option>
              </select>
              <button class="btn-outline">
                <i class="fa-regular fa-file-pdf"></i> PDF
              </button>
              <button class="btn-outline">
                <i class="fa-regular fa-file-excel"></i> CSV
              </button>
            </div>
          </div>

          <!-- Notification History Table -->
          <div class="table-wrapper" data-aos="fade-up">
            <table>
              <thead>
                <tr>
                  <th>Notification ID</th>
                  <th>Title</th>
                  <th>Recipient</th>
                  <th>Type</th>
                  <th>Channel</th>
                  <th>Status</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="notificationTableBody">
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

          <!-- Live Notification Feed -->
          <div class="feed-panel" data-aos="fade-up">
            <div class="feed-header">
              <i class="fa-solid fa-rss"></i>
              <h4>Live Notification Feed</h4>
            </div>
            <div id="notificationFeed">
              <div class="feed-item" style="color: var(--text-muted); border: none">
                Loading recent broadcasts…
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
        <h3 style="margin-bottom: 1.5rem">Sending Notification</h3>

        <div class="processing-step" id="step1">
          <div class="step-icon" id="step1Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Sending to users...</span>
        </div>

        <div class="processing-step" id="step2">
          <div class="step-icon" id="step2Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Delivering via channels...</span>
        </div>

        <div class="processing-step" id="step3">
          <div class="step-icon" id="step3Icon">
            <i class="fa-solid fa-circle-notch"></i>
          </div>
          <span>Notification sent!</span>
        </div>
      </div>
    </div>

    <!-- AI Assistant -->
    <div class="ai-assistant" id="aiBtn">
      <i class="fa-solid fa-robot"></i>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    <script>
      const NOTIFICATIONS_API = "./api/notifications_center_api.php";
      const ADMIN_CSRF = <?php echo json_encode($ADMIN_PAGE_CSRF, JSON_UNESCAPED_SLASHES); ?>;

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

      // Initialize Rich Text Editor
      const quill = new Quill("#editor", {
        theme: "snow",
        placeholder: "Write your notification message here...",
        modules: {
          toolbar: [
            ["bold", "italic", "underline"],
            [{ list: "ordered" }, { list: "bullet" }],
            ["clean"],
          ],
        },
      });

      let notifications = [];

      async function loadRecentFeed() {
        const el = document.getElementById("notificationFeed");
        if (!el) return;
        try {
          const res = await fetch(NOTIFICATIONS_API + "?action=feed&limit=12", {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
          });
          const data = await res.json().catch(() => null);
          if (!data || !data.ok || !Array.isArray(data.items) || data.items.length === 0) {
            el.innerHTML =
              '<div class="feed-item" style="color:var(--text-muted);border:none">No broadcasts yet.</div>';
            return;
          }
          el.innerHTML = "";
          data.items.forEach((it) => {
            const type = String(it.type || "info");
            const icon =
              type === "success"
                ? "check"
                : type === "warning"
                  ? "exclamation"
                  : type === "alert"
                    ? "triangle-exclamation"
                    : "info";
            const row = document.createElement("div");
            row.className = "feed-item";
            row.innerHTML = `
            <span class="feed-icon ${type}"><i class="fa-solid fa-${icon}"></i></span>
            <span><strong>${String(it.title || "").replace(/</g, "&lt;")}</strong> · ${Number(it.recipient_count || 0).toLocaleString()} recipients</span>
            <span style="color: var(--text-muted); margin-left: auto">${String(it.created_at || "").replace(/</g, "&lt;")}</span>
          `;
            el.appendChild(row);
          });
        } catch (e) {
          el.innerHTML =
            '<div class="feed-item" style="color:var(--text-muted)">Could not load feed.</div>';
        }
      }

      async function loadNotificationsData() {
        try {
          const [listRes, statsRes] = await Promise.all([
            fetch(NOTIFICATIONS_API + "?limit=100&offset=0", {
              credentials: "same-origin",
              headers: { Accept: "application/json" },
            }),
            fetch(NOTIFICATIONS_API + "?action=stats", {
              credentials: "same-origin",
              headers: { Accept: "application/json" },
            }),
          ]);
          const listData = await listRes.json().catch(() => null);
          const statsData = await statsRes.json().catch(() => null);
          if (listData && listData.ok && Array.isArray(listData.items)) {
            notifications = listData.items;
          } else {
            notifications = [];
          }
          renderNotificationsTable();
          if (statsData && statsData.ok) {
            window.__ncStats = statsData;
            applyNotificationStats(statsData);
            window.__ncLast7 = statsData.last7 || [];
            initOrUpdateCharts();
          }
          loadRecentFeed();
        } catch (e) {
          notifications = [];
          renderNotificationsTable();
        }
      }

      function applyNotificationStats(s) {
        const t = document.getElementById("totalNotifications");
        const total = parseInt(s.total, 10) || 0;
        if (t) t.textContent = total > 0 ? total.toLocaleString() : "0";
        const sent = parseInt(s.sent, 10) || 0;
        const pend = parseInt(s.pending, 10) || 0;
        const fail = parseInt(s.failed, 10) || 0;
        const denom = sent + pend + fail;
        const rate = denom > 0 ? ((sent / denom) * 100).toFixed(1) : "—";
        const dr = document.getElementById("deliveryRate");
        if (dr) dr.textContent = rate === "—" ? "—" : rate + "%";
        const or = document.getElementById("openRate");
        if (or) or.textContent = "PHP mail()";
        const eg = document.getElementById("engagement");
        const r7 = parseInt(s.recipients_7d, 10);
        if (eg) eg.textContent = Number.isFinite(r7) && r7 > 0 ? r7.toLocaleString() : "0";
      }

      // Render notifications table
      function renderNotificationsTable() {
        const tbody = document.getElementById("notificationTableBody");
        if (!tbody) return;
        tbody.innerHTML = "";
        if (!notifications.length) {
          const row = document.createElement("tr");
          row.innerHTML =
            '<td colspan="8" style="color:var(--text-muted);padding:1.5rem">No notifications sent yet. Compose above to broadcast.</td>';
          tbody.appendChild(row);
          return;
        }
        notifications.forEach((notif) => {
          const st = String(notif.status || "Sent");
          const statusClass = `status-${st.toLowerCase()}`;
          const typeClass = `type-${notif.type}`;

          const row = document.createElement("tr");
          const nid = parseInt(notif.id, 10) || 0;
          row.innerHTML = `
          <td><strong>${notif.public_id || ("NOT-" + String(notif.id).padStart(5, "0"))}</strong></td>
          <td>${String(notif.title || "").replace(/</g, "&lt;")}</td>
          <td>${String(notif.recipient || "").replace(/</g, "&lt;")}</td>
          <td><span class="type-badge ${typeClass}">${String(notif.type || "")}</span></td>
          <td>${String(notif.channel || "—").replace(/</g, "&lt;")}</td>
          <td><span class="status-badge ${statusClass}">${st}</span></td>
          <td>${String(notif.date || "").replace(/</g, "&lt;")}</td>
          <td>
            <div class="actions-dropdown">
              <button class="actions-btn" onclick="toggleDropdown(event, this)"><i class="fa-solid fa-ellipsis-vertical"></i> Actions</button>
              <div class="dropdown-menu">
                <a class="dropdown-item" onclick="viewNotification(${nid})"><i class="fa-regular fa-eye"></i> View</a>
                <a class="dropdown-item" onclick="resendNotification(${nid})"><i class="fa-regular fa-paper-plane"></i> Resend</a>
                <a class="dropdown-item" onclick="deleteNotification(${nid})"><i class="fa-regular fa-trash-can"></i> Delete</a>
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

      // Recipient select change
      document
        .getElementById("recipientSelect")
        .addEventListener("change", (e) => {
          const specificUser = document.getElementById("specificUser");
          const sid = document.getElementById("specificUserId");
          const box = document.getElementById("userSearchResults");
          const on = e.target.value === "specific";
          specificUser.disabled = !on;
          if (!on && sid) sid.value = "";
          if (!on && box) {
            box.style.display = "none";
            box.innerHTML = "";
          }
        });

      let userSearchTimer = null;
      const specificUserInput = document.getElementById("specificUser");
      if (specificUserInput) {
        specificUserInput.addEventListener("input", () => {
          const sid = document.getElementById("specificUserId");
          if (sid) sid.value = "";
          const box = document.getElementById("userSearchResults");
          clearTimeout(userSearchTimer);
          userSearchTimer = setTimeout(async () => {
            if (!box) return;
            const q = specificUserInput.value.trim();
            if (q.length < 2) {
              box.style.display = "none";
              box.innerHTML = "";
              return;
            }
            try {
              const res = await fetch(
                NOTIFICATIONS_API + "?action=users&q=" + encodeURIComponent(q) + "&limit=20",
                { credentials: "same-origin", headers: { Accept: "application/json" } },
              );
              const data = await res.json().catch(() => null);
              if (!data || !data.ok || !Array.isArray(data.users) || !data.users.length) {
                box.innerHTML =
                  '<div style="padding:10px 14px;color:var(--text-muted)">No users found</div>';
                box.style.display = "block";
                return;
              }
              box.innerHTML = "";
              data.users.forEach((u) => {
                const btn = document.createElement("button");
                btn.type = "button";
                btn.style.cssText =
                  "display:block;width:100%;text-align:left;padding:10px 14px;border:none;background:transparent;color:var(--text);cursor:pointer;border-bottom:1px solid var(--glass-edge)";
                btn.textContent =
                  (u.full_name || "—") + " · " + (u.email || "") + " · #" + u.id;
                btn.addEventListener("click", () => {
                  const idEl = document.getElementById("specificUserId");
                  if (idEl) idEl.value = String(u.id);
                  specificUserInput.value = u.email || u.full_name || String(u.id);
                  box.style.display = "none";
                });
                box.appendChild(btn);
              });
              box.style.display = "block";
            } catch (err) {
              box.innerHTML =
                '<div style="padding:10px;color:var(--danger)">Search failed</div>';
              box.style.display = "block";
            }
          }, 350);
        });
      }

      // Show templates panel
      window.showTemplatePanel = function () {
        const templates = document.getElementById("templatesGrid");
        templates.style.display =
          templates.style.display === "none" ? "grid" : "none";
      };

      // Use template
      window.useTemplate = function (template) {
        const templates = {
          welcome: {
            title: "Welcome to Crypterra!",
            message:
              "<p>Welcome to Crypterra! Get started with your crypto journey today.</p><p>Complete your KYC verification to unlock full features.</p>",
          },
          deposit: {
            title: "Deposit Confirmed",
            message:
              "<p>Your deposit of {amount} has been successfully confirmed and added to your wallet.</p>",
          },
          withdrawal: {
            title: "Withdrawal Request",
            message:
              "<p>Your withdrawal request of {amount} is being processed.</p><p>Please allow 1-3 business days for completion.</p>",
          },
          security: {
            title: "Security Alert",
            message:
              "<p>Suspicious login detected from a new device.</p><p>If this wasn't you, please secure your account immediately.</p>",
          },
        };

        document.getElementById("titleInput").value = templates[template].title;
        quill.root.innerHTML = templates[template].message;
        document.getElementById("templatesGrid").style.display = "none";
      };

      // Clear composer
      window.clearComposer = function () {
        document.getElementById("titleInput").value = "";
        quill.root.innerHTML = "";
        document.getElementById("recipientSelect").value = "all";
        document.getElementById("specificUser").disabled = true;
        document.getElementById("specificUser").value = "";
        const sid = document.getElementById("specificUserId");
        if (sid) sid.value = "";
        const ur = document.getElementById("userSearchResults");
        if (ur) {
          ur.innerHTML = "";
          ur.style.display = "none";
        }
        document.getElementById("typeSelect").value = "info";
        document.querySelectorAll(".channel-checkbox").forEach((cb) => {
          cb.checked =
            cb.parentElement.textContent.includes("In-app") ||
            cb.parentElement.textContent.includes("Email");
        });
      };

      // Send notification with animation (persists via API)
      window.sendNotification = async function () {
        const title = document.getElementById("titleInput").value.trim();
        const messageText = quill.root.innerText.trim();
        const messageHtml = quill.root.innerHTML;

        if (!title || !messageText) {
          alert("Please enter a title and message");
          return;
        }

        const recipient = document.getElementById("recipientSelect").value;
        const specificUser = document.getElementById("specificUser").value.trim();
        const _suidEl = document.getElementById("specificUserId");
        const specificUserId =
          _suidEl && _suidEl.value ? parseInt(String(_suidEl.value), 10) || 0 : 0;
        const typeSel = document.getElementById("typeSelect").value;

        if (recipient === "specific" && specificUserId <= 0 && specificUser === "") {
          alert("Choose a user from the search list or enter an email / wallet ID.");
          return;
        }
        const chBoxes = document.querySelectorAll(".channel-checkbox");
        const channels = { in_app: false, email: false, push: false };
        chBoxes.forEach((cb) => {
          const lab = cb.parentElement ? cb.parentElement.textContent : "";
          if (lab.includes("In-app")) channels.in_app = cb.checked;
          if (lab.includes("Email")) channels.email = cb.checked;
          if (lab.includes("Push")) channels.push = cb.checked;
        });

        const overlay = document.getElementById("processingOverlay");
        overlay.style.display = "flex";
        for (let i = 1; i <= 3; i++) {
          document.getElementById(`step${i}Icon`).className = "step-icon";
          document.getElementById(`step${i}Icon`).innerHTML =
            '<i class="fa-solid fa-circle-notch"></i>';
        }

        try {
          const res = await fetch(NOTIFICATIONS_API, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({
              action: "send",
              csrf_token: ADMIN_CSRF,
              title,
              message_html: messageHtml,
              recipient,
              specific_user: specificUser,
              specific_user_id: specificUserId,
              type: typeSel,
              channels,
            }),
          });
          const data = await res.json().catch(() => null);
          document.getElementById("step1Icon").className = "step-icon completed";
          document.getElementById("step1Icon").innerHTML = '<i class="fa-solid fa-check"></i>';
          document.getElementById("step2Icon").className = "step-icon completed";
          document.getElementById("step2Icon").innerHTML = '<i class="fa-solid fa-check"></i>';

          if (!res.ok || !data || !data.ok) {
            document.getElementById("step3Icon").className = "step-icon";
            document.getElementById("step3Icon").innerHTML = '<i class="fa-solid fa-xmark"></i>';
            overlay.style.display = "none";
            const err = data && data.error ? String(data.error) : "send_failed";
            showToast("Could not send: " + err, "error");
            return;
          }

          document.getElementById("step3Icon").className = "step-icon completed";
          document.getElementById("step3Icon").innerHTML = '<i class="fa-solid fa-check"></i>';
          setTimeout(() => {
            overlay.style.display = "none";
            const feed = document.getElementById("notificationFeed");
            const feedItem = document.createElement("div");
            feedItem.className = "feed-item";
            const rlabel =
              document.getElementById("recipientSelect").options[
                document.getElementById("recipientSelect").selectedIndex
              ].text;
            feedItem.innerHTML = `
            <span class="feed-icon info"><i class="fa-solid fa-info"></i></span>
            <span><strong>${title.replace(/</g, "&lt;")}</strong> sent to ${rlabel.replace(/</g, "&lt;")} (${data.recipient_count || 0} users)</span>
            <span style="color: var(--text-muted); margin-left: auto;">just now</span>
          `;
            feed.prepend(feedItem);
            if (feed.children.length > 5) feed.removeChild(feed.lastChild);
            showToast("Notification sent successfully!", "success");
            clearComposer();
            loadNotificationsData();
          }, 600);
        } catch (e) {
          overlay.style.display = "none";
          showToast("Network error", "error");
        }
      };

      // Quick alerts
      window.quickAlert = function (type) {
        const alerts = {
          maintenance: {
            title: "⚠️ Scheduled Maintenance",
            message:
              "<p>System maintenance scheduled for March 20, 2025 from 02:00 to 04:00 UTC. The platform may be temporarily unavailable.</p>",
          },
          security: {
            title: "🔐 Security Update",
            message:
              "<p>We've detected unusual activity. Please enable 2FA on your account for enhanced security.</p>",
          },
          market: {
            title: "📈 Market Update",
            message:
              "<p>BTC volatility detected. Trading fees reduced by 50% for the next hour.</p>",
          },
        };

        document.getElementById("titleInput").value = alerts[type].title;
        quill.root.innerHTML = alerts[type].message;
      };

      // View notification
      window.viewNotification = function (nid) {
        const n = notifications.find((x) => parseInt(x.id, 10) === nid);
        const t = n && n.title ? n.title : "(unknown)";
        alert("Notification #" + nid + "\n" + t);
      };

      // Resend notification
      window.resendNotification = function (nid) {
        alert("Resend: open the composer and send again (reference id #" + nid + ").");
      };

      // Delete notification
      window.deleteNotification = async function (numericId) {
        if (!numericId) return;
        if (!(await UiModal.confirm("Delete this notification log and all in-app copies for users?", { title: "Delete notification", danger: true }))) return;
        try {
          const res = await fetch(NOTIFICATIONS_API, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({
              action: "delete",
              csrf_token: ADMIN_CSRF,
              id: numericId,
            }),
          });
          const data = await res.json().catch(() => null);
          if (!res.ok || !data || !data.ok) {
            showToast("Could not delete", "error");
            return;
          }
          showToast("Notification deleted", "warning");
          loadNotificationsData();
        } catch (e) {
          showToast("Network error", "error");
        }
      };

      // Show toast
      function showToast(message, type = "success") {
        const toast = document.createElement("div");
        toast.className = `toast ${type}`;
        toast.innerHTML = `
        <i class="fa-solid fa-${type === "success" ? "check-circle" : type === "warning" ? "exclamation-circle" : type === "error" ? "circle-xmark" : "info-circle"}"></i>
        <span>${message}</span>
      `;
        document.body.appendChild(toast);

        setTimeout(() => {
          toast.remove();
        }, 3000);
      }

      let ncLineChart = null;
      let ncPieChart = null;

      function initOrUpdateCharts() {
        const last7 = window.__ncLast7 || [];
        const labels = last7.length
          ? last7.map((x) => String(x.date || "").slice(5))
          : ["", "", "", "", "", "", ""];
        const counts = last7.length ? last7.map((x) => parseInt(x.count, 10) || 0) : [0, 0, 0, 0, 0, 0, 0];

        const lineEl = document.getElementById("notificationsChart");
        if (lineEl && typeof Chart !== "undefined") {
          if (ncLineChart) ncLineChart.destroy();
          ncLineChart = new Chart(lineEl, {
            type: "line",
            data: {
              labels,
              datasets: [
                {
                  label: "Notifications",
                  data: counts,
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
                  ticks: { color: "#7f8ea3" },
                  beginAtZero: true,
                },
                x: { grid: { display: false }, ticks: { color: "#7f8ea3" } },
              },
            },
          });
        }

        const pieEl = document.getElementById("engagementChart");
        if (pieEl && typeof Chart !== "undefined") {
          const s = window.__ncStats || {};
          const sent = parseInt(s.sent, 10) || 0;
          const pend = parseInt(s.pending, 10) || 0;
          const fail = parseInt(s.failed, 10) || 0;
          if (ncPieChart) ncPieChart.destroy();
          ncPieChart = new Chart(pieEl, {
            type: "doughnut",
            data: {
              labels: ["Sent", "Pending", "Failed"],
              datasets: [
                {
                  data: [sent + pend + fail > 0 ? sent : 0, pend, fail],
                  backgroundColor: ["#22c55e", "#f59e0b", "#ef4444"],
                  borderWidth: 0,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: { legend: { display: true, labels: { color: "#7f8ea3" } } },
              cutout: "70%",
            },
          });
        }
      }

      // Search functionality
      document.getElementById("searchInput").addEventListener("input", (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll("#notificationTableBody tr");

        rows.forEach((row) => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(searchTerm) ? "" : "none";
        });
      });

      // Filter functionality
      document
        .getElementById("typeFilter")
        .addEventListener("change", filterTable);
      document
        .getElementById("statusFilter")
        .addEventListener("change", filterTable);

      function filterTable() {
        const typeFilter = document
          .getElementById("typeFilter")
          .value.toLowerCase();
        const statusFilter = document
          .getElementById("statusFilter")
          .value.toLowerCase();
        const rows = document.querySelectorAll("#notificationTableBody tr");

        rows.forEach((row) => {
          if (!row.cells || row.cells.length < 6) return;
          const type = row.cells[3].textContent.toLowerCase();
          const status = row.cells[5].textContent.toLowerCase();
          let show = true;

          if (typeFilter !== "all types" && !type.includes(typeFilter))
            show = false;
          if (statusFilter !== "all status" && !status.includes(statusFilter))
            show = false;

          row.style.display = show ? "" : "none";
        });
      }

      // AI Assistant
      document.getElementById("aiBtn").addEventListener("click", () => {
        alert(
          "🤖 AI Notification Assistant:\n\n• 1,284 total notifications sent\n• 98.5% delivery rate\n• 76.3% open rate\n• 12 pending notifications\n• Recommended: Send maintenance alert",
        );
      });

      // Animate numbers
      function animateValue(element, start, end, duration) {
        const range = end - start;
        const increment = range / (duration / 16);
        let current = start;

        const timer = setInterval(() => {
          current += increment;
          if (current >= end) {
            element.textContent =
              end.toLocaleString() +
              (element.id === "deliveryRate" ||
              element.id === "openRate" ||
              element.id === "engagement"
                ? "%"
                : "");
            clearInterval(timer);
          } else {
            if (
              element.id === "deliveryRate" ||
              element.id === "openRate" ||
              element.id === "engagement"
            ) {
              element.textContent = current.toFixed(1) + "%";
            } else {
              element.textContent = Math.floor(current).toLocaleString();
            }
          }
        }, 16);
      }

      // Initialize (load from DB)
      loadNotificationsData();

      // Resize handler
      window.addEventListener("resize", () => {
        if (window.innerWidth > 1100) {
          sidebar.classList.remove("mobile-open");
        }
      });
    </script>
  <?php require_once __DIR__ . '/includes/admin_notify_client.php'; ?>
  <script>
    window.addEventListener("adminNotifyUpdate", function (e) {
      var h = document.getElementById("adminLiveInboxHint");
      if (!h || !e.detail) return;
      var n = parseInt(e.detail.badge_total, 10) || 0;
      h.textContent =
        n > 0
          ? " · Pending items (inbox + platform): " + n + " (live)"
          : "";
    });
  </script>
  </body>
</html>
