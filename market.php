<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover"
    />
    <meta name="format-detection" content="telephone=no" />
    <title>Crypterra · Markets – next‑gen crypto trading</title>
    <?php include __DIR__ . '/includes/ui_modal_script.php'; ?>
    <!-- Font Awesome 6 (free) -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    />
    <!-- Google Fonts Inter -->
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&display=swap"
      rel="stylesheet"
    />
    <!-- AOS scroll animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <!-- lightweight-charts for sparklines -->
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.1.0/dist/lightweight-charts.standalone.production.js"></script>
    <style>
      /* ---------- RESET & VARIABLES (identical to index) ---------- */
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }
      :root {
        --primary: #22c55e;
        --primary-dark: #16a34a;
        --primary-glow: rgba(34, 197, 94, 0.5);
        --dark-bg: #0b1c2f;
        --darker-bg: #071422;
        --text-light: #e0edff;
        --text-white: #ffffff;
        --glass-bg: rgba(255, 255, 255, 0.08);
        --glass-edge: rgba(34, 197, 94, 0.2);
        --transition-smooth: all 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1);
        --positive: #26a69a;
        --negative: #ef5350;
      }
      body {
        font-family: "Inter", system-ui, sans-serif;
        background: var(--dark-bg);
        color: var(--text-light);
        line-height: 1.5;
        scroll-behavior: smooth;
        overflow-x: hidden;
        width: 100%;
        position: relative;
        padding-top: 70px;
      }
      body, html {
        max-width: 100%;
        overflow-x: hidden;
      }
      h1, h2, h3 {
        font-weight: 700;
        letter-spacing: -0.02em;
      }

      /* ---------- NAVBAR - PERMANENTLY FIXED (same as index) ---------- */
      .navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #071422;
        background: linear-gradient(135deg, #0a1a2a 0%, #071422 100%);
        padding: 0.7rem 4%;
        box-shadow: 0 8px 28px rgba(0, 0, 0, 0.5), 0 1px 0 rgba(34, 197, 94, 0.3);
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 10000;
        border-bottom: 2px solid var(--primary);
        transform: none !important;
        transition: none !important;
        animation: none !important;
        will-change: auto;
        backdrop-filter: blur(0px);
      }
      .navbar.hide-nav {
        display: flex !important;
        transform: none !important;
      }
      .logo {
        display: flex;
        align-items: center;
        font-size: clamp(1.4rem, 5vw, 2rem);
        font-weight: 700;
        flex-shrink: 0;
      }
      .logo i {
        font-size: clamp(1.6rem, 6vw, 2.2rem);
        background: linear-gradient(145deg, var(--primary), #0f994a);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-right: 0.3rem;
      }
      .logo span {
        background: linear-gradient(135deg, #ffffff 0%, var(--primary) 90%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        font-size: clamp(1.1rem, 4vw, 1.6rem);
      }
      .nav-links {
        display: flex;
        list-style: none;
        gap: 1.5rem;
        font-weight: 500;
        align-items: center;
      }
      .nav-links li a {
        text-decoration: none;
        color: var(--text-light);
        font-weight: 500;
        padding: 0.4rem 0;
        position: relative;
        transition: color 0.25s;
        font-size: 0.95rem;
        white-space: nowrap;
      }
      .nav-links li a::after {
        content: "";
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2px;
        background: linear-gradient(90deg, var(--primary), #8aecb0);
        transition: width 0.3s;
        border-radius: 4px;
      }
      .nav-links li a:hover::after,
      .nav-links li a.active::after {
        width: 100%;
      }
      .wallet-btn {
        background: transparent;
        border: 2px solid var(--primary);
        color: var(--primary);
        padding: 0.4rem 1.2rem;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition-smooth);
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
        font-family: inherit;
      }
      .wallet-btn:hover {
        background: var(--primary);
        color: var(--dark-bg);
        transform: scale(1.02);
      }
      .signup-link {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        color: var(--dark-bg);
        padding: 0.45rem 1.3rem;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
      }
      .hamburger-wrapper {
        display: none;
        position: relative;
      }
      .hamburger-icon {
        width: 42px;
        height: 42px;
        background: rgba(34, 197, 94, 0.15);
        border: 1px solid rgba(34, 197, 94, 0.4);
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 5px;
        cursor: pointer;
      }
      .bar {
        width: 20px;
        height: 2px;
        background: var(--primary);
        border-radius: 6px;
        transition: 0.25s;
      }
      .hamburger-dropdown {
        position: fixed;
        top: 70px;
        right: 16px;
        width: 280px;
        max-width: calc(100vw - 32px);
        background: rgba(7, 20, 34, 0.98);
        backdrop-filter: blur(24px);
        border: 1px solid var(--primary);
        border-radius: 24px;
        padding: 1.5rem;
        box-shadow: 0 30px 50px -20px black;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        transform: translateY(-20px);
        opacity: 0;
        visibility: hidden;
        transition: 0.3s ease;
        pointer-events: none;
        z-index: 20001;
      }
      .hamburger-dropdown.open {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
        pointer-events: all;
      }
      .dropdown-links {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
      }
      .dropdown-links li a {
        color: var(--text-light);
        text-decoration: none;
        font-size: 1.1rem;
        font-weight: 500;
        padding: 0.6rem;
        display: block;
        border-radius: 16px;
        transition: 0.2s;
        background: rgba(255, 255, 255, 0.02);
      }
      .dropdown-wallet,
      .dropdown-signup-link {
        padding: 0.8rem;
        border-radius: 40px;
        font-weight: 600;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
      }
      .dropdown-wallet {
        background: transparent;
        border: 2px solid var(--primary);
        color: var(--primary);
      }
      .dropdown-signup-link {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        color: var(--dark-bg);
        text-decoration: none;
      }
      @media (max-width: 900px) {
        .nav-links,
        .wallet-btn,
        .signup-link {
          display: none !important;
        }
        .hamburger-wrapper {
          display: block;
        }
      }

      /* ---------- MARKETS PAGE SPECIFIC STYLES ---------- */
      .section {
        padding: 2rem 5%;
        position: relative;
        width: 100%;
      }
      @media (max-width: 768px) {
        .section {
          padding: 1.5rem 1.5rem;
        }
      }
      .container {
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
      }
      .section-title {
        font-size: clamp(1.6rem, 5vw, 2.5rem);
        margin-bottom: 1.5rem;
        background: linear-gradient(to right, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        display: inline-block;
      }

      .btn-primary, .btn-outline {
        padding: 0.8rem 1.8rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: clamp(0.85rem, 3vw, 1rem);
        border: none;
        cursor: pointer;
        transition: 0.2s;
      }
      .btn-primary {
        background: var(--primary);
        color: var(--dark-bg);
      }
      .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
      }
      .btn-outline {
        background: transparent;
        border: 2px solid var(--primary);
        color: white;
      }
      .btn-outline:hover {
        background: var(--primary);
        color: var(--dark-bg);
      }

      /* particle overlay */
      .particle-overlay {
        position: absolute;
        inset: 0;
        overflow: hidden;
        z-index: 0;
        pointer-events: none;
      }
      .particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: var(--primary);
        border-radius: 50%;
        filter: blur(1px);
        opacity: 0.3;
        animation: floatParticle 18s infinite linear;
      }
      @keyframes floatParticle {
        0% {
          transform: translateY(0) translateX(0);
          opacity: 0.2;
        }
        100% {
          transform: translateY(-100vh) translateX(20px);
          opacity: 0;
        }
      }

      /* markets hero */
      .markets-hero {
        min-height: 35vh;
        display: flex;
        align-items: center;
        position: relative;
        isolation: isolate;
        background: radial-gradient(circle at 30% 30%, rgba(34, 197, 94, 0.08), transparent 60%);
        margin-top: 0;
      }
      .markets-hero .container {
        position: relative;
        z-index: 5;
        text-align: center;
      }
      .markets-hero h1 {
        font-size: clamp(1.8rem, 5vw, 3.5rem);
        font-weight: 800;
      }
      .markets-hero p {
        font-size: clamp(0.85rem, 3.5vw, 1.1rem);
        max-width: 700px;
        margin: 1rem auto;
        background: rgba(0, 20, 10, 0.3);
        backdrop-filter: blur(6px);
        padding: 0.7rem 1.5rem;
        border-radius: 60px 20px 20px 60px;
        border-left: 6px solid var(--primary);
      }

      /* stats grid */
      .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
      }
      @media (max-width: 600px) {
        .stats-grid {
          grid-template-columns: repeat(2, 1fr);
          gap: 0.8rem;
        }
      }
      .stat-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 1rem;
        text-align: center;
        transition: var(--transition-smooth);
      }
      .stat-card:hover {
        transform: translateY(-3px);
        border-color: var(--primary);
      }
      .stat-card i {
        font-size: 1.5rem;
        color: var(--primary);
        margin-bottom: 0.3rem;
      }
      .stat-number {
        font-size: 1.6rem;
        font-weight: 700;
        background: linear-gradient(to right, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      /* market toolbar */
      .market-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        align-items: center;
        justify-content: space-between;
        margin: 1rem 0 1.5rem;
      }
      .search-wrapper {
        flex: 1;
        min-width: 220px;
        position: relative;
      }
      .search-wrapper input {
        width: 100%;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 0.7rem 2rem 0.7rem 1.2rem;
        color: white;
        font-size: 0.9rem;
        outline: none;
      }
      .search-wrapper input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 15px var(--primary-glow);
      }
      .search-wrapper i {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary);
        font-size: 0.9rem;
      }
      .filter-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
      }
      .pill {
        background: rgba(34, 197, 94, 0.1);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.45rem 1rem;
        font-weight: 500;
        font-size: 0.8rem;
        cursor: pointer;
        transition: var(--transition-smooth);
      }
      .pill:hover,
      .pill.active {
        background: var(--primary);
        color: #0b1c2f;
        border-color: white;
      }

      /* market table */
      .table-container {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 0.8rem;
        overflow-x: auto;
        margin-bottom: 1.5rem;
      }
      .market-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 800px;
      }
      .market-table th {
        text-align: left;
        padding: 0.7rem 0.5rem;
        color: var(--primary);
        font-weight: 600;
        font-size: 0.85rem;
        border-bottom: 1px solid var(--glass-edge);
      }
      .market-table td {
        padding: 0.7rem 0.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        font-size: 0.85rem;
        transition: 0.2s;
      }
      .market-table tbody tr {
        cursor: pointer;
      }
      .market-table tbody tr:hover {
        background: rgba(34, 197, 94, 0.1);
      }
      .coin-cell {
        display: flex;
        align-items: center;
        gap: 0.6rem;
      }
      .coin-logo {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
      }
      .coin-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }
      .positive { color: var(--positive); }
      .negative { color: var(--negative); }
      .sparkline-container {
        width: 80px;
        height: 32px;
      }
      .trade-btn {
        background: transparent;
        border: 1px solid var(--primary);
        border-radius: 30px;
        padding: 0.25rem 0.8rem;
        color: var(--primary);
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.7rem;
      }
      .trade-btn:hover {
        background: var(--primary);
        color: #0b1c2f;
      }
      .market-loading {
        text-align: center;
        padding: 2rem;
        opacity: 0.85;
      }

      /* trending grid */
      .trending-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 1rem;
        margin: 1rem 0 1.5rem;
      }
      @media (max-width: 500px) {
        .trending-grid {
          grid-template-columns: repeat(2, 1fr);
        }
      }
      .trend-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 1rem;
        text-align: center;
        transition: var(--transition-smooth);
      }
      .trend-card:hover {
        transform: translateY(-3px);
        border-color: var(--primary);
      }
      .trend-card .coin-logo-small {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin: 0 auto 0.6rem;
        overflow: hidden;
      }
      .trend-card .coin-logo-small img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      /* gainers/losers panels */
      .panel-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
        margin: 1rem 0 1.5rem;
      }
      @media (max-width: 700px) {
        .panel-grid {
          grid-template-columns: 1fr;
          gap: 1rem;
        }
      }
      .panel {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 1.2rem;
      }
      .panel h3 {
        margin-bottom: 1rem;
        font-size: 1.2rem;
      }
      .coin-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
        padding: 0.6rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
      }
      .coin-row:last-child {
        border-bottom: none;
      }
      .coin-row-left {
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      .coin-row-left img {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        object-fit: cover;
      }

      /* heatmap */
      .heatmap-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: 0.6rem;
        margin: 1rem 0 1.5rem;
      }
      .heatmap-cell {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 0.7rem;
        text-align: center;
        transition: 0.3s;
        cursor: pointer;
      }
      .heatmap-cell:hover {
        transform: scale(1.02);
        border-color: var(--primary);
      }
      .heatmap-positive {
        border-left: 3px solid var(--positive);
      }
      .heatmap-negative {
        border-left: 3px solid var(--negative);
      }
      .heatmap-cell img {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        margin-bottom: 0.25rem;
      }

      /* activity feed */
      .activity-feed {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 1.2rem;
        max-height: 240px;
        overflow: hidden;
        margin: 1rem 0;
      }
      .feed-scroll {
        animation: scrollUp 25s linear infinite;
      }
      @keyframes scrollUp {
        0% { transform: translateY(0); }
        100% { transform: translateY(-50%); }
      }
      .feed-scroll:hover {
        animation-play-state: paused;
      }
      .feed-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        font-size: 0.8rem;
      }

      /* CTA glass */
      .cta-glass {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-edge);
        border-radius: 48px;
        padding: 2rem 1.5rem;
        text-align: center;
      }
      .cta-glass h2 {
        font-size: clamp(1.5rem, 5vw, 2.2rem);
      }

      /* FOOTER - FULLY RESPONSIVE */
      .footer {
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(20px);
        border-top: 1px solid var(--primary);
        padding: 2.5rem 5% 2rem;
        margin-top: 1.5rem;
        width: 100%;
        clear: both;
        position: relative;
        z-index: 1;
      }
      .footer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 1.5rem;
        width: 100%;
      }
      @media (max-width: 550px) {
        .footer-grid {
          grid-template-columns: repeat(2, 1fr);
          gap: 1rem;
        }
      }
      .footer-col h4 {
        margin-bottom: 0.8rem;
        font-size: 0.9rem;
        color: var(--primary);
      }
      .footer-col a {
        display: block;
        color: #aac8ff;
        text-decoration: none;
        margin: 0.5rem 0;
        transition: 0.2s;
        font-size: 0.8rem;
      }
      .footer-col a:hover {
        color: var(--primary);
        transform: translateX(5px);
      }
      .social-icons {
        display: flex;
        justify-content: center;
        gap: 1.5rem;
        margin-top: 2rem;
        flex-wrap: wrap;
        padding-top: 1rem;
        border-top: 1px solid rgba(34, 197, 94, 0.2);
      }
      .social-icons i {
        font-size: 1.4rem;
        color: var(--text-light);
        transition: 0.2s;
        cursor: pointer;
      }
      .social-icons i:hover {
        color: var(--primary);
        transform: scale(1.1);
      }

      /* SCROLL TOP */
      #scrollTop {
        position: fixed;
        right: 1.5rem;
        bottom: 5.5rem;
        background: rgba(34, 197, 94, 0.85);
        backdrop-filter: blur(10px);
        width: 44px;
        height: 44px;
        border-radius: 50%;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        border: none;
        z-index: 999;
        color: white;
        font-size: 1.1rem;
      }
      #scrollTop:hover {
        background: var(--primary);
        transform: scale(1.1);
      }img, video, iframe, canvas {
        max-width: 100%;
        height: auto;
      }
    </style>
  </head>
  <body>
    <!-- NAVBAR: PERMANENTLY FIXED - same as index -->
    <nav class="navbar">
      <div class="logo">
        <i class="fa-solid fa-globe"></i><span>Crypterra</span>
      </div>
      <ul class="nav-links">
        <li><a href="./index.php">Home</a></li>
        <li><a href="./about.php">About</a></li>
        <li><a href="./contact.php">Contact</a></li>
        <li><a href="./faq.php">FAQs</a></li>
        <li><a href="./market.php" class="active">Markets</a></li>
</ul>
      <a href="./login.php" class="signup-link">Sign Up</a>
      <div class="hamburger-wrapper">
        <div class="hamburger-icon" id="hamburgerIcon">
          <div class="bar"></div>
          <div class="bar"></div>
          <div class="bar"></div>
        </div>
        <div class="hamburger-dropdown" id="hamburgerDropdown">
          <ul class="dropdown-links">
            <li><a href="./index.php">🏠 Home</a></li>
            <li><a href="./about.php">ℹ️ About</a></li>
            <li><a href="./contact.php">📧 Contact</a></li>
            <li><a href="./faq.php">❓ FAQs</a></li>
            <li><a href="./market.php">📈 Markets</a></li>
          </ul>
          <!---->
          <a href="./login.php" class="dropdown-signup-link">Sign Up</a>
        </div>
      </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="markets-hero">
      <div class="particle-overlay" id="heroParticles"></div>
      <div class="container" data-aos="fade-up">
        <h1>Explore the Crypto Markets</h1>
        <p>
          Track real-time cryptocurrency prices, market trends, and trading
          activity across hundreds of digital assets.
        </p>
        <div class="stats-grid">
          <div class="stat-card">
            <i class="fa-solid fa-chart-pie"></i>
            <div class="stat-number" id="statMcap" data-target="2.41" data-prefix="$" data-suffix="T">—</div>
            <div>Total Market Cap</div>
          </div>
          <div class="stat-card">
            <i class="fa-solid fa-chart-line"></i>
            <div class="stat-number" id="statVol" data-target="98" data-prefix="$" data-suffix="B">—</div>
            <div>24h Volume</div>
          </div>
          <div class="stat-card">
            <i class="fa-solid fa-bitcoin"></i>
            <div class="stat-number" id="statBtcDom" data-target="52.1" data-suffix="%">—</div>
            <div>BTC Dominance</div>
          </div>
          <div class="stat-card">
            <i class="fa-solid fa-coins"></i>
            <div class="stat-number" id="statActive" data-target="12.3" data-suffix="K+">—</div>
            <div>Active Cryptos</div>
          </div>
        </div>
      </div>
    </section>

    <!-- MARKET SEARCH & FILTERS -->
    <section class="section" style="padding-top: 0.5rem">
      <div class="container">
        <div class="market-toolbar">
          <div class="search-wrapper">
            <input type="text" id="marketSearch" placeholder="Search coins..." />
            <i class="fa-solid fa-magnifying-glass"></i>
          </div>
          <div class="filter-pills" id="filterPills">
            <span class="pill active" data-filter="all">All</span>
            <span class="pill" data-filter="gainers">Top Gainers</span>
            <span class="pill" data-filter="losers">Top Losers</span>
            <span class="pill" data-filter="trending">Trending</span>
          </div>
        </div>
      </div>
    </section>

    <!-- CRYPTO MARKET TABLE -->
    <section class="section" style="padding-top: 0">
      <div class="container">
        <div class="table-container" data-aos="fade-up">
          <table class="market-table" id="marketTable">
            <thead>
              <tr><th>#</th><th>Coin</th><th>Price</th><th>24h Change</th><th>24h Volume</th><th>Market Cap</th><th>Chart (7d)</th><th></th></tr>
            </thead>
            <tbody id="tableBody">
              <tr><td colspan="8" class="market-loading">Loading live markets…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- TRENDING COINS -->
    <section class="section" style="padding-top: 0">
      <div class="container">
        <h2 class="section-title" data-aos="fade-up">🔥 Trending coins</h2>
        <div class="trending-grid" id="trendingGrid"></div>
      </div>
    </section>

    <!-- TOP GAINERS & LOSERS -->
    <section class="section" style="padding-top: 0">
      <div class="container">
        <div class="panel-grid">
          <div class="panel" data-aos="fade-right">
            <h3>📈 Top Gainers</h3>
            <div id="gainersPanel"></div>
          </div>
          <div class="panel" data-aos="fade-left">
            <h3>📉 Top Losers</h3>
            <div id="losersPanel"></div>
          </div>
        </div>
      </div>
    </section>

    <!-- MARKET HEATMAP -->
    <section class="section" style="padding-top: 0">
      <div class="container">
        <h2 class="section-title" data-aos="fade-up">Market Heatmap</h2>
        <div class="heatmap-grid" id="heatmapGrid"></div>
      </div>
    </section>

    <!-- LIVE ACTIVITY FEED -->
    <section class="section" style="padding-top: 0">
      <div class="container">
        <div class="activity-feed" data-aos="zoom-in">
          <h3 style="margin-bottom: 0.8rem; font-size: 1.1rem">
            <i class="fa-solid fa-rss" style="color: var(--primary)"></i> Live Activity
          </h3>
          <div class="feed-scroll" id="activityFeed"></div>
        </div>
      </div>
    </section>

    <!-- TRADING CTA -->
    <section class="section" style="padding-top: 0">
      <div class="container cta-glass" data-aos="zoom-in">
        <h2>Start Trading Crypto Today</h2>
        <p style="margin: 1rem 0; font-size: clamp(0.9rem, 4vw, 1.1rem)">
          Access hundreds of cryptocurrencies with lightning-fast execution and industry-leading security.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
          <button class="btn-primary" onclick="window.location.href='./login.php'">Start Trading</button>
                  </div>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
      <div class="footer-grid">
        <div class="footer-col"><h4>Product</h4><a href="#">Exchange</a><a href="#">Derivatives</a><a href="#">NFT</a></div>
        <div class="footer-col"><h4>Markets</h4><a href="#">Spot</a><a href="#">Futures</a><a href="#">Options</a></div>
        <div class="footer-col"><h4>API</h4><a href="#">Documentation</a><a href="#">WebSocket</a></div>
        <div class="footer-col"><h4>Security</h4><a href="#">Audits</a><a href="#">Bug bounty</a></div>
        <div class="footer-col"><h4>Company</h4><a href="#">Careers</a><a href="#">Blog</a><a href="#">Support</a></div>
      </div>
      <div class="social-icons">
        <i class="fa-brands fa-twitter"></i><i class="fa-brands fa-discord"></i>
        <i class="fa-brands fa-telegram"></i><i class="fa-brands fa-github"></i>
      </div>
      <div style="text-align: center; margin-top: 1.5rem; font-size: 0.7rem; opacity: 0.6;">
        &copy; 2025 Crypterra. All rights reserved.
      </div>
    </footer>

    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>

    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      (function() {
        // Hamburger menu
        const hamburgerIcon = document.getElementById("hamburgerIcon");
        const dropdown = document.getElementById("hamburgerDropdown");
        if (hamburgerIcon) {
          hamburgerIcon.addEventListener("click", (e) => {
            e.stopPropagation();
            dropdown.classList.toggle("open");
          });
          document.addEventListener("click", (e) => {
            if (!hamburgerIcon.contains(e.target) && !dropdown.contains(e.target))
              dropdown.classList.remove("open");
          });
        }

        // Scroll top button
        const scrollBtn = document.getElementById("scrollTop");
        window.addEventListener("scroll", () => {
          scrollBtn.style.display = window.scrollY > 250 ? "flex" : "none";
        });
        scrollBtn.addEventListener("click", () => {
          window.scrollTo({ top: 0, behavior: "smooth" });
        });

        // AOS init
        AOS.init({ duration: 600, once: false });

        // Particles for hero section
        const heroParticles = document.getElementById("heroParticles");
        if (heroParticles) {
          for (let i = 0; i < 25; i++) {
            let p = document.createElement("div");
            p.className = "particle";
            p.style.left = Math.random() * 100 + "%";
            p.style.animationDelay = Math.random() * 10 + "s";
            p.style.animationDuration = 12 + Math.random() * 8 + "s";
            heroParticles.appendChild(p);
          }
        }

        // ---------- LIVE DATA (CoinGecko public API) ----------
        const CG = "https://api.coingecko.com/api/v3";
        let chartInstances = [];
        let allMarkets = [];
        let trendingItems = [];
        let currentFilter = "all";
        let tableRowsSource = [];

        function fmtUsd(n) {
          if (n == null || Number.isNaN(n)) return "—";
          if (n >= 1) return "$" + n.toLocaleString(undefined, { maximumFractionDigits: 2 });
          return "$" + n.toLocaleString(undefined, { maximumFractionDigits: 6 });
        }

        function fmtPct(p) {
          if (p == null || Number.isNaN(p)) return "—";
          const s = p >= 0 ? "+" : "";
          return s + p.toFixed(2) + "%";
        }

        function fmtCompactUsd(n) {
          if (n == null || Number.isNaN(n)) return "—";
          const x = Math.abs(n);
          if (x >= 1e12) return "$" + (n / 1e12).toFixed(2) + "T";
          if (x >= 1e9) return "$" + (n / 1e9).toFixed(2) + "B";
          if (x >= 1e6) return "$" + (n / 1e6).toFixed(2) + "M";
          if (x >= 1e3) return "$" + (n / 1e3).toFixed(2) + "K";
          return "$" + n.toFixed(2);
        }

        function sparklineSeries(prices) {
          const arr = prices && prices.length ? prices : [];
          if (!arr.length) return [];
          const end = Math.floor(Date.now() / 1000);
          const start = end - 7 * 24 * 3600;
          const step = arr.length > 1 ? (end - start) / (arr.length - 1) : 1;
          return arr.map((v, i) => ({ time: Math.floor(start + i * step), value: v }));
        }

        function destroyCharts() {
          chartInstances.forEach((c) => { try { c.remove(); } catch(e) {} });
          chartInstances = [];
        }

        function applyTableFilter(list) {
          let rows = list.slice();
          if (currentFilter === "trending" && trendingItems.length) {
            const order = trendingItems.map((t) => t.id);
            const byId = {};
            (allMarkets.length ? allMarkets : list).forEach((c) => { byId[c.id] = c; });
            rows = order.map((id) => byId[id]).filter(Boolean);
            if (!rows.length) rows = list.slice();
            return rows;
          }
          if (currentFilter === "gainers") {
            rows = [...rows].sort((a, b) => (b.price_change_percentage_24h ?? -999) - (a.price_change_percentage_24h ?? -999));
          } else if (currentFilter === "losers") {
            rows = [...rows].sort((a, b) => (a.price_change_percentage_24h ?? 999) - (b.price_change_percentage_24h ?? 999));
          } else {
            rows = [...rows].sort((a, b) => (a.market_cap_rank || 99999) - (b.market_cap_rank || 99999));
          }
          return rows;
        }

        function renderTable(rows) {
          const tbody = document.getElementById("tableBody");
          destroyCharts();
          if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="market-loading">No data.</td></tr>';
            return;
          }
          let html = "";
          rows.forEach((c, idx) => {
            const sym = (c.symbol || "").toUpperCase();
            const ch = c.price_change_percentage_24h;
            const changeClass = ch == null ? "" : ch >= 0 ? "positive" : "negative";
            const safeId = String(c.id).replace(/[^a-z0-9_-]/gi, "");
            html += `<tr data-search="${(c.name + " " + sym).toLowerCase()}">
              <td>${c.market_cap_rank ?? idx + 1}</td>
              <td><div class="coin-cell"><div class="coin-logo"><img src="${c.image}" alt="" loading="lazy" width="28" height="28" /></div><span>${c.name} (${sym})</span></div></td>
              <td>${fmtUsd(c.current_price)}</td>
              <td class="${changeClass}">${fmtPct(ch)}</td>
              <td>${fmtCompactUsd(c.total_volume)}</td>
              <td>${fmtCompactUsd(c.market_cap)}</td>
              <td><div class="sparkline-container" id="spark-${safeId}"></div></td>
              <td><button type="button" class="trade-btn">Trade</button></td>
            </tr>`;
          });
          tbody.innerHTML = html;

          rows.forEach((c) => {
            const safeId = String(c.id).replace(/[^a-z0-9_-]/gi, "");
            const container = document.getElementById(`spark-${safeId}`);
            const prices = c.sparkline_in_7d && c.sparkline_in_7d.price;
            if (!container || !window.LightweightCharts) return;
            const ch = c.price_change_percentage_24h;
            const col = ch == null ? "#7a9bb8" : ch >= 0 ? "#26a69a" : "#ef5350";
            const chart = LightweightCharts.createChart(container, {
              width: container.clientWidth || 80, height: 32,
              layout: { background: { type: "solid", color: "transparent" }, textColor: "transparent" },
              grid: { vertLines: { visible: false }, horzLines: { visible: false } },
              rightPriceScale: { visible: false }, timeScale: { visible: false },
            });
            chartInstances.push(chart);
            const line = chart.addLineSeries({ color: col, lineWidth: 2, crosshairMarkerVisible: false });
            line.setData(sparklineSeries(prices));
            chart.timeScale().fitContent();
          });
        }

        function renderTrending(priceById) {
          const grid = document.getElementById("trendingGrid");
          if (!trendingItems.length) {
            grid.innerHTML = '<p class="market-loading" style="grid-column:1/-1">Trending data unavailable.</p>';
            return;
          }
          let html = "";
          trendingItems.slice(0, 8).forEach((item) => {
            const m = priceById[item.id];
            const price = m ? m.current_price : null;
            const ch = m ? m.price_change_percentage_24h : null;
            const cls = ch == null ? "" : ch >= 0 ? "positive" : "negative";
            const img = m ? m.image : item.thumb || "";
            html += `<div class="trend-card">
              <div class="coin-logo-small">${img ? `<img src="${img}" alt="" width="40" height="40" loading="lazy" />` : "◆"}</div>
              <h4 style="font-size:1rem;">${item.name}</h4>
              <p style="font-size:0.8rem;">${price != null ? fmtUsd(price) : "—"}</p>
              <p class="${cls}" style="font-weight:600; font-size:0.8rem;">${ch != null ? fmtPct(ch) : "24h —"}</p>
            </div>`;
          });
          grid.innerHTML = html;
        }

        function renderPanels(rows) {
          const sorted = [...rows].filter(c => c.price_change_percentage_24h != null);
          sorted.sort((a, b) => (b.price_change_percentage_24h ?? 0) - (a.price_change_percentage_24h ?? 0));
          const gainers = sorted.slice(0, 5);
          const losers = [...sorted].sort((a, b) => (a.price_change_percentage_24h ?? 0) - (b.price_change_percentage_24h ?? 0)).slice(0, 5);
          let gainHtml = "", loseHtml = "";
          gainers.forEach(c => {
            gainHtml += `<div class="coin-row"><div class="coin-row-left"><img src="${c.image}" alt="" width="24" height="24" /><span>${c.name}</span></div><span class="positive">${fmtPct(c.price_change_percentage_24h)}</span></div>`;
          });
          losers.forEach(c => {
            loseHtml += `<div class="coin-row"><div class="coin-row-left"><img src="${c.image}" alt="" width="24" height="24" /><span>${c.name}</span></div><span class="negative">${fmtPct(c.price_change_percentage_24h)}</span></div>`;
          });
          document.getElementById("gainersPanel").innerHTML = gainHtml || '<p class="market-loading">—</p>';
          document.getElementById("losersPanel").innerHTML = loseHtml || '<p class="market-loading">—</p>';
        }

        function renderHeatmap(rows) {
          const slice = rows.slice(0, 36);
          let html = "";
          slice.forEach(c => {
            const sym = (c.symbol || "").toUpperCase();
            const ch = c.price_change_percentage_24h;
            const heatClass = ch == null ? "" : ch >= 0 ? "heatmap-positive" : "heatmap-negative";
            html += `<div class="heatmap-cell ${heatClass}" title="${c.name}: ${fmtPct(ch)}">
              <img src="${c.image}" alt="" width="24" height="24" loading="lazy" />
              <div style="font-weight:600; font-size:0.7rem;">${sym}</div>
              <small style="font-size:0.65rem;">${fmtUsd(c.current_price)}</small>
            </div>`;
          });
          document.getElementById("heatmapGrid").innerHTML = html || '<p class="market-loading">—</p>';
        }

        async function fetchMarkets(sparkline) {
          const url = CG + "/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=100&page=1&sparkline=" + (sparkline ? "true" : "false") + "&price_change_percentage=24h";
          const res = await fetch(url, { cache: "no-store" });
          if (!res.ok) throw new Error("markets");
          return await res.json();
        }

        async function fetchTrendingMeta() {
          const res = await fetch(CG + "/search/trending", { cache: "no-store" });
          if (!res.ok) return [];
          const j = await res.json();
          return (j.coins || []).map(x => ({ id: x.item.id, name: x.item.name, symbol: (x.item.symbol || "").toUpperCase(), thumb: x.item.thumb || x.item.small || "" }));
        }

        async function fetchGlobalStats() {
          const res = await fetch(CG + "/global", { cache: "no-store" });
          if (!res.ok) return;
          const d = (await res.json()).data;
          if (!d) return;
          const mcapUsd = d.total_market_cap?.usd;
          const volUsd = d.total_volume?.usd;
          const btcDom = d.market_cap_percentage?.btc;
          const active = d.active_cryptocurrencies;
          if (mcapUsd) { document.getElementById("statMcap").dataset.target = (mcapUsd / 1e12).toFixed(2); }
          if (volUsd) { document.getElementById("statVol").dataset.target = (volUsd / 1e9).toFixed(2); }
          if (btcDom) { document.getElementById("statBtcDom").dataset.target = btcDom.toFixed(1); }
          if (active) { document.getElementById("statActive").dataset.target = active; }
        }

        function animateStatsOnce() {
          document.querySelectorAll(".stat-number").forEach(stat => {
            const target = parseFloat(stat.dataset.target);
            if (isNaN(target)) return;
            const suffix = stat.dataset.suffix || "";
            const prefix = stat.dataset.prefix || "";
            let counter = 0;
            const duration = 1400, stepTime = 16;
            const increment = target / (duration / stepTime);
            const timer = setInterval(() => {
              counter += increment;
              if (counter >= target) {
                stat.textContent = prefix + target.toLocaleString(undefined, { maximumFractionDigits: stat.id === "statActive" ? 0 : 2 }) + suffix;
                clearInterval(timer);
              } else {
                stat.textContent = prefix + counter.toFixed(stat.id === "statActive" ? 0 : 2) + suffix;
              }
            }, stepTime);
          });
        }

        async function loadMarketCore() {
          const [markets, trendMeta] = await Promise.all([fetchMarkets(true), fetchTrendingMeta()]);
          allMarkets = markets;
          trendingItems = trendMeta;
          tableRowsSource = allMarkets.slice();

          const priceById = {};
          allMarkets.forEach(c => { priceById[c.id] = c; });
          const missing = trendingItems.filter(t => !priceById[t.id]).map(t => t.id);
          if (missing.length) {
            const pr = await fetch(CG + "/simple/price?vs_currencies=usd&include_24hr_change=true&ids=" + missing.join(","), { cache: "no-store" });
            if (pr.ok) {
              const pj = await pr.json();
              missing.forEach(id => {
                if (pj[id]) {
                  const t = trendingItems.find(x => x.id === id);
                  priceById[id] = { id, name: t?.name || id, symbol: t?.symbol || "", image: t?.thumb || "", current_price: pj[id].usd, price_change_percentage_24h: pj[id].usd_24h_change };
                }
              });
            }
          }
          renderTrending(priceById);
          renderPanels(allMarkets);
          renderHeatmap(allMarkets);
          renderTable(applyTableFilter(tableRowsSource));
        }

        async function loadInitial() {
          try {
            await fetchGlobalStats();
            await loadMarketCore();
            animateStatsOnce();
          } catch(e) {
            document.getElementById("tableBody").innerHTML = '<tr><td colspan="8" class="market-loading">Could not load live market data. Try again later.</td></tr>';
          }
        }

        loadInitial();
        setInterval(async () => { try { await loadMarketCore(); } catch(e) {} }, 90000);

        // Activity feed
        const activities = ["Trader from Germany bought 2.1 BTC", "User from Canada sold 8 ETH", "Trader from Singapore bought 120 SOL", "User from UK deposited $45,000 USDT", "Trader from Brazil sold 5,000 ADA", "User from Japan bought 0.5 BTC", "Trader from Australia withdrew 2.3 ETH"];
        let feedHtml = "";
        for (let i = 0; i < 6; i++) activities.forEach(a => { feedHtml += `<div class="feed-item"><i class="fa-solid fa-circle" style="color:var(--primary); font-size:0.4rem;"></i> ${a}</div>`; });
        document.getElementById("activityFeed").innerHTML = feedHtml;

        // Search filter
        const searchInput = document.getElementById("marketSearch");
        searchInput.addEventListener("input", (e) => {
          const term = (e.target.value || "").toLowerCase().trim();
          document.querySelectorAll("#tableBody tr").forEach(row => {
            const ds = row.getAttribute("data-search") || "";
            row.style.display = (!term || ds.includes(term)) ? "" : "none";
          });
        });

        // Pill filters
        document.querySelectorAll(".pill").forEach(p => {
          p.addEventListener("click", () => {
            document.querySelectorAll(".pill").forEach(pl => pl.classList.remove("active"));
            p.classList.add("active");
            currentFilter = p.getAttribute("data-filter") || "all";
            renderTable(applyTableFilter(tableRowsSource));
          });
        });
      })();
    </script>
  <?php include __DIR__ . '/includes/smartsupp.php'; ?>
  </body>
</html>