<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <title>Crypterra · Admin · System Settings</title>
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

    /* Search Bar */
    .settings-search {
      margin-bottom: 1.8rem;
    }

    .search-container {
      background: var(--card-bg);
      backdrop-filter: blur(16px);
      border: 1px solid var(--glass-edge);
      border-radius: 60px;
      padding: 0.8rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .search-container i {
      color: var(--text-muted);
    }

    .search-container input {
      background: transparent;
      border: none;
      color: var(--text);
      outline: none;
      width: 100%;
      font-size: 1rem;
    }

    /* Tabs Navigation */
    .settings-tabs {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-bottom: 1.8rem;
      background: var(--card-bg);
      backdrop-filter: blur(16px);
      border: 1px solid var(--glass-edge);
      border-radius: 60px;
      padding: 0.5rem;
    }

    .tab-btn {
      padding: 0.8rem 1.5rem;
      background: transparent;
      border: none;
      border-radius: 40px;
      color: var(--text-secondary);
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: 0.2s;
      flex: 1 1 auto;
      justify-content: center;
    }

    .tab-btn:hover {
      color: var(--primary);
    }

    .tab-btn.active {
      background: var(--primary-soft);
      color: var(--primary);
      border: 1px solid var(--glass-edge);
    }

    @media (max-width: 768px) {
      .settings-tabs {
        border-radius: 30px;
        padding: 0.5rem;
      }
      .tab-btn {
        padding: 0.6rem 1rem;
        font-size: 0.9rem;
      }
    }

    /* Settings Panels */
    .settings-panel {
      display: none;
      animation: fadeIn 0.3s;
    }

    .settings-panel.active {
      display: block;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Settings Cards */
    .settings-card {
      background: var(--card-bg);
      backdrop-filter: blur(16px);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      padding: 1.8rem;
      margin-bottom: 1.8rem;
    }

    .settings-card-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .settings-card-header i {
      font-size: 1.5rem;
      color: var(--primary);
    }

    .settings-card-header h3 {
      color: var(--text);
      font-size: 1.2rem;
    }

    .settings-card-header p {
      color: var(--text-muted);
      font-size: 0.9rem;
      margin-left: auto;
    }

    /* Settings Grid */
    .settings-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1.5rem;
    }

    @media (max-width: 768px) {
      .settings-grid {
        grid-template-columns: 1fr;
      }
    }

    .setting-item {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .setting-item.full-width {
      grid-column: span 2;
    }

    @media (max-width: 768px) {
      .setting-item.full-width {
        grid-column: span 1;
      }
    }

    .setting-label {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .setting-label i {
      font-size: 1rem;
    }

    .setting-input {
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      padding: 0.8rem 1.2rem;
      color: var(--text);
      font-size: 1rem;
      outline: none;
      transition: 0.2s;
    }

    .setting-input:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 2px var(--primary-soft);
    }

    .setting-input[type="number"] {
      -moz-appearance: textfield;
    }

    .setting-input[type="number"]::-webkit-outer-spin-button,
    .setting-input[type="number"]::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    .setting-select {
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      padding: 0.8rem 2rem 0.8rem 1.2rem;
      color: var(--text);
      font-size: 1rem;
      outline: none;
      cursor: pointer;
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2322c55e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
    }

    /* Toggle Switch */
    .toggle-switch {
      position: relative;
      display: inline-block;
      width: 60px;
      height: 30px;
    }

    .toggle-switch input {
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

    .toggle-container {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .toggle-info {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .toggle-badge {
      padding: 0.2rem 0.8rem;
      border-radius: 40px;
      font-size: 0.8rem;
      background: var(--primary-soft);
      color: var(--primary);
    }

    /* Network Fees Grid */
    .network-fees {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }

    .fee-item {
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      padding: 1rem;
      text-align: center;
    }

    .fee-item label {
      display: block;
      color: var(--text-muted);
      font-size: 0.85rem;
      margin-bottom: 0.5rem;
    }

    .fee-item input {
      width: 100%;
      background: transparent;
      border: none;
      color: var(--text);
      font-size: 1.1rem;
      font-weight: 600;
      text-align: center;
    }

    /* Color Picker */
    .color-picker {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .color-option {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      cursor: pointer;
      border: 2px solid transparent;
      transition: 0.2s;
    }

    .color-option:hover {
      transform: scale(1.1);
    }

    .color-option.active {
      border-color: var(--text);
      box-shadow: var(--shadow-glow);
    }

    .color-option.green { background: #22c55e; }
    .color-option.blue { background: #3b82f6; }
    .color-option.purple { background: #a855f7; }
    .color-option.orange { background: #f97316; }
    .color-option.red { background: #ef4444; }

    /* API Key Management */
    .api-key-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem;
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      margin-bottom: 0.5rem;
      flex-wrap: wrap;
      gap: 0.5rem;
    }

    .api-key-info {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .api-key-value {
      font-family: monospace;
      background: var(--surface-light);
      padding: 0.3rem 0.8rem;
      border-radius: 40px;
    }

    .api-key-actions {
      display: flex;
      gap: 0.5rem;
    }

    /* System Health */
    .health-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1rem;
    }

    @media (max-width: 768px) {
      .health-grid {
        grid-template-columns: 1fr;
      }
    }

    .health-item {
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      padding: 1.2rem;
      text-align: center;
    }

    .health-status {
      display: inline-block;
      width: 12px;
      height: 12px;
      border-radius: 50%;
      margin-right: 8px;
    }

    .health-status.online { background: var(--success); box-shadow: 0 0 15px var(--success); }
    .health-status.offline { background: var(--danger); box-shadow: 0 0 15px var(--danger); }
    .health-status.warning { background: var(--warning); box-shadow: 0 0 15px var(--warning); }

    /* Change Log */
    .changelog-item {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem;
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      margin-bottom: 0.5rem;
      flex-wrap: wrap;
    }

    .changelog-time {
      color: var(--text-muted);
      font-size: 0.85rem;
      min-width: 100px;
    }

    .changelog-action {
      flex: 1;
    }

    .changelog-user {
      color: var(--primary);
      font-weight: 600;
    }

    /* Save Bar */
    .save-bar {
      position: sticky;
      bottom: 1rem;
      background: var(--card-bg);
      backdrop-filter: blur(16px);
      border: 1px solid var(--glass-edge);
      border-radius: 60px;
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
      margin-top: 2rem;
      z-index: 90;
    }

    .save-info {
      display: flex;
      align-items: center;
      gap: 1rem;
      color: var(--text-muted);
    }

    .save-info i {
      color: var(--primary);
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

    .btn-danger {
      background: var(--danger);
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

    .btn-danger:hover {
      background: #dc2626;
      transform: scale(1.02);
      box-shadow: 0 0 30px rgba(239, 68, 68, 0.5);
    }

    /* Warning Box */
    .warning-box {
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid var(--danger);
      border-radius: 40px;
      padding: 1rem;
      margin: 1.5rem 0;
      display: flex;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .warning-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: rgba(239, 68, 68, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--danger);
    }

    .warning-content {
      flex: 1;
    }

    .warning-content h4 {
      color: var(--danger);
      margin-bottom: 0.2rem;
    }

    /* Summary Panel */
    .summary-panel {
      background: var(--glass-bg);
      border: 1px solid var(--glass-edge);
      border-radius: 40px;
      padding: 1.5rem;
      margin-bottom: 1.8rem;
    }

    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }

    .summary-item {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .summary-label {
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .summary-value {
      color: var(--primary);
      font-weight: 600;
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
      .save-bar {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
      }
      .api-key-item {
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
  </style>
</head>
<body class="dark">
  <div class="admin">
    <!-- SIDEBAR (active on Settings) -->
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
        <a href="./admin-withdrawal.php" class="sidebar-link"><i class="fa-solid fa-arrow-up"></i> <span>Withdrawals</span></a>
        <a href="./admin-kyc.php" class="sidebar-link"><i class="fa-solid fa-id-card"></i> <span>KYC Management</span></a>
        <a href="./admin-wallet-control.php" class="sidebar-link"><i class="fa-solid fa-wallet"></i> <span>Wallet Control</span></a>
        <a href="./admin-market-control.php" class="sidebar-link"><i class="fa-solid fa-chart-line"></i> <span>Market Control</span></a>
        <a href="./admin-security-center.php" class="sidebar-link"><i class="fa-solid fa-shield"></i> <span>Security Center</span></a>
        <a href="./admin-analytics.php" class="sidebar-link"><i class="fa-solid fa-magnifying-glass-chart"></i> <span>Analytics</span></a>
        <a href="./admin-notifications.php" class="sidebar-link"><i class="fa-solid fa-bell"></i> <span>Notifications</span></a>
        <a href="./admin-settings.php" class="sidebar-link active"><i class="fa-solid fa-gear"></i> <span>Settings</span></a>
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

      <!-- SETTINGS CONTENT -->
      <div class="content">
        <!-- Page Header -->
        <div class="page-header" data-aos="fade-up">
          <h1>System Settings</h1>
          <p>Configure and manage platform-wide settings.</p>
          <div class="breadcrumb">
            <a href="#">Admin</a> <span>/</span> <span style="color: var(--primary);">Settings</span>
          </div>
        </div>

        <!-- Search Settings -->
        <div class="settings-search" data-aos="fade-up">
          <div class="search-container">
            <i class="fa-solid fa-search"></i>
            <input type="text" id="settingsSearch" placeholder="Search settings... (e.g., security, trading, fees)">
          </div>
        </div>

        <!-- Settings Tabs -->
        <div class="settings-tabs" data-aos="fade-up">
          <button class="tab-btn active" onclick="switchTab('general')"><i class="fa-solid fa-sliders"></i> General</button>
          <button class="tab-btn" onclick="switchTab('security')"><i class="fa-solid fa-shield"></i> Security</button>
          <button class="tab-btn" onclick="switchTab('payments')"><i class="fa-solid fa-credit-card"></i> Payments</button>
          <button class="tab-btn" onclick="switchTab('trading')"><i class="fa-solid fa-chart-line"></i> Trading</button>
          <button class="tab-btn" onclick="switchTab('notifications')"><i class="fa-solid fa-bell"></i> Notifications</button>
          <button class="tab-btn" onclick="switchTab('appearance')"><i class="fa-solid fa-palette"></i> Appearance</button>
          <button class="tab-btn" onclick="switchTab('advanced')"><i class="fa-solid fa-code"></i> Advanced</button>
        </div>

        <!-- Settings Summary Panel -->
        <div class="summary-panel" data-aos="fade-up">
          <div style="display: flex; align-items: center; gap: 1rem;">
            <i class="fa-solid fa-info-circle" style="color: var(--primary);"></i>
            <h4>Current Configuration Summary</h4>
          </div>
          <div class="summary-grid">
            <div class="summary-item"><span class="summary-label">Platform:</span> <span class="summary-value">Crypterra (Live)</span></div>
            <div class="summary-item"><span class="summary-label">Maintenance:</span> <span class="summary-value" id="summaryMaintenance">Disabled</span></div>
            <div class="summary-item"><span class="summary-label">Trading Fee:</span> <span class="summary-value">0.1%</span></div>
            <div class="summary-item"><span class="summary-label">2FA Required:</span> <span class="summary-value" id="summary2FA">Enabled</span></div>
            <div class="summary-item"><span class="summary-label">Min Deposit:</span> <span class="summary-value">$10</span></div>
            <div class="summary-item"><span class="summary-label">Min Withdrawal:</span> <span class="summary-value">$20</span></div>
          </div>
        </div>

        <!-- GENERAL SETTINGS PANEL -->
        <div id="general" class="settings-panel active" data-aos="fade-up">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-sliders"></i>
              <h3>General Settings</h3>
              <p>Basic platform configuration</p>
            </div>
            <div class="settings-grid">
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-globe"></i> Platform Name</div>
                <input type="text" class="setting-input" value="Crypterra" placeholder="Platform name">
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-envelope"></i> Support Email</div>
                <input type="email" class="setting-input" value="support@crypterra.io" placeholder="support@example.com">
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-coins"></i> Default Currency</div>
                <select class="setting-select">
                  <option value="USD" selected>USD ($)</option>
                  <option value="EUR">EUR (€)</option>
                  <option value="GBP">GBP (£)</option>
                  <option value="JPY">JPY (¥)</option>
                </select>
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-clock"></i> Timezone</div>
                <select class="setting-select">
                  <option value="UTC">UTC (Coordinated Universal Time)</option>
                  <option value="EST" selected>EST (Eastern Standard Time)</option>
                  <option value="PST">PST (Pacific Standard Time)</option>
                  <option value="GMT">GMT (Greenwich Mean Time)</option>
                </select>
              </div>
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-tools" style="color: var(--warning);"></i>
                    <div>
                      <div style="font-weight: 600;">Maintenance Mode</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Prevent user access during maintenance</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" id="maintenanceToggle" onchange="toggleMaintenance()">
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-language"></i>
              <h3>Localization</h3>
              <p>Regional settings</p>
            </div>
            <div class="settings-grid">
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-flag"></i> Default Language</div>
                <select class="setting-select">
                  <option value="en" selected>English</option>
                  <option value="es">Spanish</option>
                  <option value="fr">French</option>
                  <option value="de">German</option>
                  <option value="zh">Chinese</option>
                </select>
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-calendar"></i> Date Format</div>
                <select class="setting-select">
                  <option value="MM/DD/YYYY" selected>MM/DD/YYYY</option>
                  <option value="DD/MM/YYYY">DD/MM/YYYY</option>
                  <option value="YYYY-MM-DD">YYYY-MM-DD</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- SECURITY SETTINGS PANEL -->
        <div id="security" class="settings-panel">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-shield"></i>
              <h3>Security Settings</h3>
              <p>Protect your platform</p>
            </div>
            <div class="settings-grid">
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-lock" style="color: var(--success);"></i>
                    <div>
                      <div style="font-weight: 600;">Enable 2FA (Admin)</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Require 2FA for all admin accounts</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" id="twofaToggle" checked onchange="toggle2FA()">
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-key"></i> Max Login Attempts</div>
                <input type="number" class="setting-input" value="5" min="1" max="10">
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-hourglass"></i> Session Timeout (minutes)</div>
                <input type="number" class="setting-input" value="30" min="5" max="120">
              </div>
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-clock"></i>
                    <div>
                      <div style="font-weight: 600;">Auto-Lock After Inactivity</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Automatically lock accounts after period of inactivity</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
              <button class="btn-outline" onclick="resetAllSessions()">
                <i class="fa-solid fa-rotate-right"></i> Reset All Sessions
              </button>
              <button class="btn-danger" onclick="forceLogoutAll()">
                <i class="fa-solid fa-sign-out-alt"></i> Force Logout All Users
              </button>
            </div>
          </div>

          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-user-lock"></i>
              <h3>Password Policy</h3>
              <p>User password requirements</p>
            </div>
            <div class="settings-grid">
              <div class="setting-item">
                <div class="setting-label">Minimum Length</div>
                <input type="number" class="setting-input" value="8" min="6" max="20">
              </div>
              <div class="setting-item">
                <div class="setting-label">Require Special Characters</div>
                <select class="setting-select">
                  <option value="yes" selected>Yes</option>
                  <option value="no">No</option>
                </select>
              </div>
              <div class="setting-item">
                <div class="setting-label">Require Numbers</div>
                <select class="setting-select">
                  <option value="yes" selected>Yes</option>
                  <option value="no">No</option>
                </select>
              </div>
              <div class="setting-item">
                <div class="setting-label">Password Expiry (days)</div>
                <input type="number" class="setting-input" value="90" min="30" max="365">
              </div>
            </div>
          </div>
        </div>

        <!-- PAYMENTS SETTINGS PANEL -->
        <div id="payments" class="settings-panel">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-credit-card"></i>
              <h3>Payment Settings</h3>
              <p>Configure deposits and withdrawals</p>
            </div>
            <div class="settings-grid">
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-arrow-down" style="color: var(--success);"></i>
                    <div>
                      <div style="font-weight: 600;">Enable Deposits</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Allow users to deposit funds</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-arrow-up" style="color: var(--warning);"></i>
                    <div>
                      <div style="font-weight: 600;">Enable Withdrawals</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Allow users to withdraw funds</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-coins"></i> Minimum Deposit ($)</div>
                <input type="number" class="setting-input" value="10" min="1" step="1">
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-coins"></i> Minimum Withdrawal ($)</div>
                <input type="number" class="setting-input" value="20" min="5" step="1">
              </div>
            </div>

            <div style="margin-top: 2rem;">
              <h4 style="margin-bottom: 1rem;">Network Fees</h4>
              <div class="network-fees">
                <div class="fee-item">
                  <label>Bitcoin (BTC)</label>
                  <input type="text" value="0.0005 BTC">
                </div>
                <div class="fee-item">
                  <label>Ethereum (ETH)</label>
                  <input type="text" value="0.003 ETH">
                </div>
                <div class="fee-item">
                  <label>ERC20 (USDT)</label>
                  <input type="text" value="$5.00">
                </div>
                <div class="fee-item">
                  <label>TRC20 (USDT)</label>
                  <input type="text" value="$1.00">
                </div>
                <div class="fee-item">
                  <label>Solana (SOL)</label>
                  <input type="text" value="0.0001 SOL">
                </div>
                <div class="fee-item">
                  <label>BEP20 (BNB)</label>
                  <input type="text" value="0.0005 BNB">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- TRADING SETTINGS PANEL -->
        <div id="trading" class="settings-panel">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-chart-line"></i>
              <h3>Trading Settings</h3>
              <p>Configure trading parameters</p>
            </div>
            <div class="settings-grid">
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-chart-line" style="color: var(--success);"></i>
                    <div>
                      <div style="font-weight: 600;">Enable Trading</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Allow users to trade cryptocurrencies</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-percent"></i> Trading Fee (%)</div>
                <input type="number" class="setting-input" value="0.1" min="0" max="5" step="0.01">
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-chart-line"></i> Maker Fee (%)</div>
                <input type="number" class="setting-input" value="0.08" min="0" max="5" step="0.01">
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-chart-line"></i> Taker Fee (%)</div>
                <input type="number" class="setting-input" value="0.1" min="0" max="5" step="0.01">
              </div>
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-robot" style="color: var(--info);"></i>
                    <div>
                      <div style="font-weight: 600;">Auto Market Simulation</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Simulate market activity for testing</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox">
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- NOTIFICATIONS SETTINGS PANEL -->
        <div id="notifications" class="settings-panel">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-bell"></i>
              <h3>Notification Settings</h3>
              <p>Configure notification channels</p>
            </div>
            <div class="settings-grid">
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-bell" style="color: var(--primary);"></i>
                    <div>
                      <div style="font-weight: 600;">System Notifications</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Send system alerts and updates</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-envelope" style="color: var(--info);"></i>
                    <div>
                      <div style="font-weight: 600;">Email Alerts</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Send notifications via email</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-mobile" style="color: var(--warning);"></i>
                    <div>
                      <div style="font-weight: 600;">Push Notifications</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Send notifications to mobile devices</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox">
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- APPEARANCE SETTINGS PANEL -->
        <div id="appearance" class="settings-panel">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-palette"></i>
              <h3>Appearance Settings</h3>
              <p>Customize the UI</p>
            </div>
            <div class="settings-grid">
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-moon" style="color: var(--primary);"></i>
                    <div>
                      <div style="font-weight: 600;">Dark Mode</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Enable dark theme (default)</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" checked disabled>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
              <div class="setting-item full-width">
                <div class="setting-label"><i class="fa-solid fa-paint-brush"></i> Accent Color</div>
                <div class="color-picker">
                  <div class="color-option green active" onclick="selectColor('green')"></div>
                  <div class="color-option blue" onclick="selectColor('blue')"></div>
                  <div class="color-option purple" onclick="selectColor('purple')"></div>
                  <div class="color-option orange" onclick="selectColor('orange')"></div>
                  <div class="color-option red" onclick="selectColor('red')"></div>
                </div>
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-border-all"></i> UI Density</div>
                <select class="setting-select">
                  <option value="compact">Compact</option>
                  <option value="comfortable" selected>Comfortable</option>
                  <option value="spacious">Spacious</option>
                </select>
              </div>
              <div class="setting-item">
                <div class="setting-label"><i class="fa-solid fa-text-height"></i> Font Size</div>
                <select class="setting-select">
                  <option value="small">Small</option>
                  <option value="medium" selected>Medium</option>
                  <option value="large">Large</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- ADVANCED SETTINGS PANEL -->
        <div id="advanced" class="settings-panel">
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-code"></i>
              <h3>Advanced Settings</h3>
              <p>System-level configuration</p>
            </div>
            <div class="settings-grid">
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-key"></i>
                    <div>
                      <div style="font-weight: 600;">API Access</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Enable API access for developers</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-file-lines"></i>
                    <div>
                      <div style="font-weight: 600;">System Logs</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Enable detailed system logging</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox" checked>
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
              <div class="setting-item full-width">
                <div class="toggle-container">
                  <div class="toggle-info">
                    <i class="fa-solid fa-bug"></i>
                    <div>
                      <div style="font-weight: 600;">Debug Mode</div>
                      <div style="color: var(--text-muted); font-size: 0.85rem;">Enable debug mode (developers only)</div>
                    </div>
                  </div>
                  <label class="toggle-switch">
                    <input type="checkbox">
                    <span class="toggle-slider"></span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-key"></i>
              <h3>API Key Management</h3>
              <p>Manage API access keys</p>
            </div>
            <div class="api-key-item">
              <div class="api-key-info">
                <i class="fa-solid fa-key" style="color: var(--primary);"></i>
                <div>
                  <div>Production API Key</div>
                  <div class="api-key-value">••••••••••••••••</div>
                </div>
              </div>
              <div class="api-key-actions">
                <button class="btn-outline" onclick="revealApiKey('prod')">Show</button>
                <button class="btn-outline" onclick="rotateApiKey('prod')">Rotate</button>
              </div>
            </div>
            <div class="api-key-item">
              <div class="api-key-info">
                <i class="fa-solid fa-key" style="color: var(--warning);"></i>
                <div>
                  <div>Sandbox API Key</div>
                  <div class="api-key-value">••••••••••••••••</div>
                </div>
              </div>
              <div class="api-key-actions">
                <button class="btn-outline" onclick="revealApiKey('sandbox')">Show</button>
                <button class="btn-outline" onclick="rotateApiKey('sandbox')">Rotate</button>
              </div>
            </div>
            <button class="btn-primary" style="margin-top: 1rem;">
              <i class="fa-solid fa-plus"></i> Generate New API Key
            </button>
          </div>

          <!-- System Health Panel -->
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-heart-pulse"></i>
              <h3>System Health</h3>
              <p>Real-time system status</p>
            </div>
            <div class="health-grid">
              <div class="health-item">
                <div style="margin-bottom: 0.5rem;">Main Server</div>
                <div><span class="health-status online"></span> Online</div>
                <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">Response: 45ms</div>
              </div>
              <div class="health-item">
                <div style="margin-bottom: 0.5rem;">Database</div>
                <div><span class="health-status online"></span> Connected</div>
                <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">Queries: 245/s</div>
              </div>
              <div class="health-item">
                <div style="margin-bottom: 0.5rem;">API Gateway</div>
                <div><span class="health-status online"></span> Healthy</div>
                <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem;">Requests: 1.2k/s</div>
              </div>
            </div>
          </div>

          <!-- Change Log / History -->
          <div class="settings-card">
            <div class="settings-card-header">
              <i class="fa-solid fa-history"></i>
              <h3>Settings Change Log</h3>
              <p>Recent configuration changes</p>
            </div>
            <div class="changelog-item">
              <span class="changelog-time">10:45 AM</span>
              <span class="changelog-action"><span class="changelog-user">Admin</span> updated trading fee to 0.1%</span>
            </div>
            <div class="changelog-item">
              <span class="changelog-time">09:30 AM</span>
              <span class="changelog-action"><span class="changelog-user">Super Admin</span> enabled maintenance mode</span>
            </div>
            <div class="changelog-item">
              <span class="changelog-time">Yesterday</span>
              <span class="changelog-action"><span class="changelog-user">Admin</span> changed minimum withdrawal to $20</span>
            </div>
            <div class="changelog-item">
              <span class="changelog-time">Yesterday</span>
              <span class="changelog-action"><span class="changelog-user">Super Admin</span> rotated API keys</span>
            </div>
          </div>
        </div>

        <!-- Warning System (appears when critical settings changed) -->
        <div class="warning-box" id="maintenanceWarning" style="display: none;">
          <div class="warning-icon"><i class="fa-solid fa-exclamation-triangle"></i></div>
          <div class="warning-content">
            <h4>Critical Setting Changed</h4>
            <p>Maintenance mode will prevent all user access. Are you sure you want to enable this?</p>
          </div>
          <div style="display: flex; gap: 1rem;">
            <button class="btn-outline" onclick="hideWarning()">Cancel</button>
            <button class="btn-danger" onclick="confirmMaintenance()">Confirm</button>
          </div>
        </div>

        <!-- Save Bar -->
        <div class="save-bar">
          <div class="save-info">
            <i class="fa-solid fa-circle-info"></i>
            <span>Unsaved changes will be lost</span>
          </div>
          <div style="display: flex; gap: 1rem;">
            <button class="btn-outline" onclick="resetSettings()">Reset</button>
            <button class="btn-primary" onclick="saveSettings()">
              <i class="fa-solid fa-save"></i> Save Changes
            </button>
          </div>
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

    // Tab switching
    window.switchTab = function(tabId) {
      // Update tab buttons
      document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
      event.target.classList.add('active');
      
      // Update panels
      document.querySelectorAll('.settings-panel').forEach(panel => panel.classList.remove('active'));
      document.getElementById(tabId).classList.add('active');
    };

    // Maintenance mode toggle
    window.toggleMaintenance = function() {
      const toggle = document.getElementById('maintenanceToggle');
      const warning = document.getElementById('maintenanceWarning');
      
      if (toggle.checked) {
        warning.style.display = 'flex';
      } else {
        warning.style.display = 'none';
        document.getElementById('summaryMaintenance').textContent = 'Disabled';
      }
    };

    window.hideWarning = function() {
      document.getElementById('maintenanceWarning').style.display = 'none';
      document.getElementById('maintenanceToggle').checked = false;
    };

    window.confirmMaintenance = function() {
      document.getElementById('maintenanceWarning').style.display = 'none';
      document.getElementById('summaryMaintenance').textContent = 'Enabled';
      showToast('Maintenance mode enabled', 'warning');
    };

    // 2FA toggle
    window.toggle2FA = function() {
      const toggle = document.getElementById('twofaToggle');
      document.getElementById('summary2FA').textContent = toggle.checked ? 'Enabled' : 'Disabled';
    };

    // Color picker
    window.selectColor = function(color) {
      document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('active'));
      event.target.classList.add('active');
      
      // Change CSS variable
      const colors = {
        green: '#22c55e',
        blue: '#3b82f6',
        purple: '#a855f7',
        orange: '#f97316',
        red: '#ef4444'
      };
      
      document.documentElement.style.setProperty('--primary', colors[color]);
      showToast(`Accent color changed to ${color}`, 'success');
    };

    // Security actions
    window.resetAllSessions = function() {
      showToast('All sessions have been reset', 'success');
    };

    window.forceLogoutAll = function() {
      showToast('All users have been logged out', 'warning');
    };

    // API key actions
    window.revealApiKey = function(env) {
      showToast(`API key revealed (simulated)`, 'info');
    };

    window.rotateApiKey = function(env) {
      showToast(`API key rotated successfully`, 'success');
    };

    // Save settings with animation
    window.saveSettings = function() {
      const btn = event.target;
      const originalText = btn.innerHTML;
      
      btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
      btn.disabled = true;
      
      setTimeout(() => {
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Saved!';
        
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.disabled = false;
          showToast('Settings saved successfully!', 'success');
        }, 1500);
      }, 2000);
    };

    window.resetSettings = function() {
      showToast('Settings reset to default', 'info');
    };

    // Toast notification
    function showToast(message, type = 'success') {
      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      toast.style.position = 'fixed';
      toast.style.top = '20px';
      toast.style.right = '20px';
      toast.style.background = 'var(--surface)';
      toast.style.border = '1px solid var(--glass-edge)';
      toast.style.borderRadius = '40px';
      toast.style.padding = '1rem 1.5rem';
      toast.style.display = 'flex';
      toast.style.alignItems = 'center';
      toast.style.gap = '1rem';
      toast.style.boxShadow = 'var(--shadow-glow)';
      toast.style.zIndex = '2000';
      toast.style.animation = 'slideInRight 0.3s';
      
      const icon = type === 'success' ? 'check-circle' : (type === 'warning' ? 'exclamation-triangle' : 'info-circle');
      
      toast.innerHTML = `
        <i class="fa-solid fa-${icon}" style="color: ${type === 'success' ? 'var(--success)' : (type === 'warning' ? 'var(--warning)' : 'var(--info)')};"></i>
        <span>${message}</span>
      `;
      
      document.body.appendChild(toast);
      
      setTimeout(() => {
        toast.remove();
      }, 3000);
    }

    // Search functionality
    document.getElementById('settingsSearch').addEventListener('input', (e) => {
      const searchTerm = e.target.value.toLowerCase();
      const settingsCards = document.querySelectorAll('.settings-card');
      
      settingsCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(searchTerm) || searchTerm === '') {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });

    // AI Assistant
    document.getElementById('aiBtn').addEventListener('click', () => {
      alert('🤖 AI Settings Assistant:\n\n• 23 settings configured\n• 2 critical settings (Maintenance, 2FA)\n• All systems operational\n• Recommended: Review security settings');
    });

    // Resize handler
    window.addEventListener('resize', () => {
      if (window.innerWidth > 1100) {
        sidebar.classList.remove('mobile-open');
      }
    });

    // Add toast styles
    const style = document.createElement('style');
    style.textContent = `
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
    `;
    document.head.appendChild(style);
  </script>
  <?php require_once __DIR__ . '/includes/admin_notify_client.php'; ?>
</body>
</html>