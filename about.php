<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover"
    />
    <meta name="format-detection" content="telephone=no" />
    <title>Crypterra · About – next‑gen crypto trading</title>
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
    <!-- lightweight charting library for live widget -->
    <script src="https://cdn.jsdelivr.net/npm/lightweight-charts@4.1.0/dist/lightweight-charts.standalone.production.js"></script>
    <style>
      /* ---------- CRITICAL RESET - NO OVERFLOW ---------- */
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
      }
      body, html {
        font-family: "Inter", system-ui, sans-serif;
        background: var(--dark-bg);
        color: var(--text-light);
        line-height: 1.5;
        scroll-behavior: smooth;
        overflow-x: hidden !important;
        width: 100% !important;
        max-width: 100% !important;
        position: relative;
        margin: 0;
        padding: 0;
      }
      /* CRITICAL: Add padding to body to prevent content from hiding under fixed navbar */
      body {
        padding-top: 70px;
      }
      h1, h2, h3 {
        font-weight: 700;
        letter-spacing: -0.02em;
      }
      
      /* ---------- NAVBAR - PERMANENTLY FIXED (100% GUARANTEED) ---------- */
      .navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        /* SOLID background - never transparent */
        background: #071422;
        background: linear-gradient(135deg, #0a1a2a 0%, #071422 100%);
        padding: 0.7rem 4%;
        box-shadow: 0 8px 28px rgba(0, 0, 0, 0.5), 0 1px 0 rgba(34, 197, 94, 0.3);
        /* CRITICAL: Use FIXED positioning - never scrolls away */
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 10000;
        border-bottom: 2px solid var(--primary);
        /* Remove ALL animations and transitions that could hide it */
        transform: none !important;
        transition: none !important;
        animation: none !important;
        will-change: auto;
      }
      /* Ensure no JavaScript can hide it */
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
        cursor: pointer;
      }
      .logo i {
        font-size: clamp(1.6rem, 6vw, 2.4rem);
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
        flex-shrink: 0;
      }
      .nav-links li a {
        text-decoration: none;
        color: var(--text-light);
        font-weight: 500;
        padding: 0.4rem 0;
        position: relative;
        font-size: 0.95rem;
        white-space: nowrap;
        transition: color 0.3s ease;
      }
      .nav-links li a::after {
        content: "";
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2px;
        background: linear-gradient(90deg, var(--primary), #8aecb0);
        transition: width 0.3s ease;
      }
      .nav-links li a:hover::after,
      .nav-links li a.active::after {
        width: 100%;
      }
      .nav-links li a:hover,
      .nav-links li a.active {
        color: var(--primary);
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
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        white-space: nowrap;
        transition: all 0.3s ease;
      }
      .wallet-btn:hover {
        background: var(--primary);
        color: var(--dark-bg);
        transform: scale(1.02);
      }
      .signup {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        border: none;
        color: var(--dark-bg);
        padding: 0.45rem 1.3rem;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.3s ease;
      }
      .signup:hover {
        transform: scale(1.02);
      }
      .hamburger-wrapper {
        display: none;
        position: relative;
        flex-shrink: 0;
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
        transition: all 0.3s ease;
      }
      .hamburger-icon:hover {
        background: rgba(34, 197, 94, 0.3);
        transform: scale(1.02);
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
        background: rgba(255,255,255,0.02);
        transition: all 0.2s ease;
      }
      .dropdown-links li a:hover {
        background: rgba(34, 197, 94, 0.2);
        transform: translateX(5px);
        color: var(--primary);
      }
      .dropdown-wallet, .dropdown-signup {
        padding: 0.8rem;
        border-radius: 40px;
        font-weight: 600;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
      }
      .dropdown-wallet {
        background: transparent;
        border: 2px solid var(--primary);
        color: var(--primary);
      }
      .dropdown-wallet:hover {
        background: var(--primary);
        color: var(--dark-bg);
      }
      .dropdown-signup {
        background: linear-gradient(145deg, var(--primary), var(--primary-dark));
        color: var(--dark-bg);
        border: none;
      }
      .dropdown-signup:hover {
        transform: scale(1.02);
      }
      @media (max-width: 900px) {
        .nav-links, .wallet-btn, .signup {
          display: none !important;
        }
        .hamburger-wrapper {
          display: block;
        }
        .navbar {
          padding: 0.7rem 4%;
        }
      }

      /* ---------- HERO SECTION - FULLY RESPONSIVE ---------- */
      .about-hero {
        min-height: auto;
        display: flex;
        align-items: center;
        position: relative;
        padding: 2rem 4%;
        width: 100%;
        overflow-x: hidden;
      }
      .about-hero .container {
        display: flex;
        flex-direction: column;
        gap: 2rem;
        width: 100%;
        max-width: 100%;
        position: relative;
        z-index: 5;
      }
      @media (min-width: 900px) {
        .about-hero .container {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 2rem;
          align-items: center;
        }
      }
      .hero-content {
        width: 100%;
      }
      .hero-title {
        font-size: clamp(1.6rem, 6vw, 3.5rem);
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: 1rem;
        background: linear-gradient(to right, #ffffff, #d4ffe0);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }
      .hero-description {
        font-size: clamp(0.85rem, 3.5vw, 1rem);
        margin: 1rem 0 1.5rem;
        background: rgba(0, 20, 10, 0.3);
        backdrop-filter: blur(6px);
        padding: 0.7rem 1rem;
        border-radius: 20px;
        border-left: 4px solid var(--primary);
        width: 100%;
      }
      .hero-cta-group {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: center;
      }
      .hero-cta-group .btn-primary,
      .hero-cta-group .btn-outline {
        padding: 0.7rem 1.5rem;
        font-size: clamp(0.85rem, 3vw, 1rem);
      }
      @media (max-width: 480px) {
        .hero-cta-group {
          flex-direction: column;
          width: 100%;
        }
        .hero-cta-group .btn-primary,
        .hero-cta-group .btn-outline {
          width: 100%;
          text-align: center;
        }
      }

      /* Trading widget - FIXED OVERFLOW */
      .hero-glow-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 24px;
        padding: 1rem;
        box-shadow: 0 0 60px var(--primary-glow);
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
      }
      .trading-widget {
        width: 100%;
        border-radius: 20px;
        background: rgba(0, 0, 0, 0.3);
        overflow-x: hidden;
      }
      #tvChart {
        width: 100%;
        height: clamp(160px, 30vw, 220px);
        background: transparent;
        min-height: 150px;
      }
      .sl-tp-panel {
        display: flex;
        gap: 0.5rem;
        margin: 0.8rem 0 0.4rem;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
      }
      .sl-tp-item {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.35rem 0.8rem;
        flex: 1 1 auto;
        min-width: 100px;
      }
      .sl-tp-item label {
        font-size: 0.7rem;
        font-weight: 500;
        opacity: 0.8;
      }
      .sl-tp-item input {
        width: 75px;
        background: transparent;
        border: none;
        color: white;
        font-weight: 600;
        font-size: 0.8rem;
        outline: none;
        border-bottom: 1px dashed var(--primary);
        font-family: monospace;
      }
      .live-badge {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        color: var(--primary);
        font-weight: 600;
        font-size: 0.75rem;
        background: rgba(34, 197, 94, 0.15);
        padding: 0.3rem 0.8rem;
        border-radius: 40px;
        white-space: nowrap;
      }
      .live-badge i {
        font-size: 0.6rem;
        animation: pulse 1.5s infinite;
      }
      @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.4; }
      }
      .widget-note {
        margin-top: 0.6rem;
        color: var(--primary);
        font-size: 0.7rem;
        text-align: center;
        opacity: 0.9;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        flex-wrap: wrap;
      }
      @media (max-width: 550px) {
        .sl-tp-panel {
          flex-direction: column;
          align-items: stretch;
          gap: 0.5rem;
        }
        .sl-tp-item {
          justify-content: space-between;
          width: 100%;
        }
        .sl-tp-item input {
          width: 85px;
          text-align: right;
        }
        .live-badge {
          justify-content: center;
          width: 100%;
        }
      }

      /* ---------- REST OF SECTIONS (responsive) ---------- */
      .section {
        padding: 2.5rem 4%;
        width: 100%;
      }
      @media (max-width: 768px) {
        .section {
          padding: 2rem 1.5rem;
        }
      }
      .container {
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
      }
      .section-title {
        font-size: clamp(1.6rem, 6vw, 2.8rem);
        margin-bottom: 1.5rem;
        background: linear-gradient(to right, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        display: inline-block;
      }
      .btn-primary {
        background: var(--primary);
        color: var(--dark-bg);
        padding: 0.7rem 1.5rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: clamp(0.85rem, 3vw, 1rem);
        border: none;
        cursor: pointer;
        transition: 0.3s;
      }
      .btn-primary:hover {
        transform: scale(1.02);
        background: #2ed573;
      }
      .btn-outline {
        background: transparent;
        border: 2px solid var(--primary);
        color: white;
        padding: 0.7rem 1.5rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: clamp(0.85rem, 3vw, 1rem);
        cursor: pointer;
        transition: 0.3s;
      }
      .btn-outline:hover {
        background: var(--primary);
        color: #000;
        transform: scale(1.02);
      }
      .glass-image {
        background: var(--glass-bg);
        backdrop-filter: blur(8px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
      }
      .glass-image img {
        width: 100%;
        height: auto;
        border-radius: 20px;
        display: block;
      }
      .two-col {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        align-items: center;
      }
      @media (min-width: 768px) {
        .two-col {
          grid-template-columns: 1fr 1fr;
          gap: 2rem;
        }
      }
      .values-grid, .stats-grid, .team-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-top: 1.5rem;
      }
      @media (min-width: 550px) {
        .values-grid {
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        .stats-grid {
          grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }
        .team-grid {
          grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }
      }
      .value-card, .stat-card, .team-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 24px;
        padding: 1.2rem;
        text-align: center;
        transition: all 0.3s ease;
      }
      .value-card:hover, .stat-card:hover, .team-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
        box-shadow: 0 0 30px var(--primary-glow);
      }
      .value-card i, .stat-card i {
        font-size: 2rem;
        color: var(--primary);
        margin-bottom: 0.8rem;
        display: inline-block;
      }
      .stat-number {
        font-size: clamp(1.6rem, 5vw, 2.2rem);
        font-weight: 800;
        background: linear-gradient(to right, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }
      .team-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(145deg, var(--primary), #2a7948);
        margin: 0 auto 0.8rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: white;
        border: 2px solid var(--primary);
      }
      .team-social i {
        margin: 0 0.3rem;
        color: var(--text-light);
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .team-social i:hover {
        color: var(--primary);
        transform: scale(1.2);
      }
      .timeline {
        position: relative;
        max-width: 100%;
        margin: 1.5rem auto 0;
      }
      .timeline::after {
        content: "";
        position: absolute;
        width: 2px;
        background: linear-gradient(to bottom, transparent, var(--primary), transparent);
        top: 0;
        bottom: 0;
        left: 20px;
      }
      .timeline-item {
        padding: 0.8rem 0 0.8rem 45px;
        position: relative;
        width: 100%;
      }
      .timeline-item .content {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 20px;
        padding: 1rem;
        transition: all 0.3s ease;
      }
      .timeline-item .content:hover {
        border-color: var(--primary);
        box-shadow: 0 0 20px var(--primary-glow);
      }
      .timeline-item .dot {
        position: absolute;
        width: 14px;
        height: 14px;
        background: var(--primary);
        border-radius: 50%;
        left: 13px;
        top: 1.2rem;
        z-index: 10;
        box-shadow: 0 0 10px var(--primary);
      }
      .cta-glass {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-edge);
        border-radius: 32px;
        padding: 2rem 1.2rem;
        text-align: center;
      }
      .footer {
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(20px);
        border-top: 1px solid var(--primary);
        padding: 2rem 4% 1.5rem;
        width: 100%;
      }
      .footer-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
      }
      @media (min-width: 550px) {
        .footer-grid {
          grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        }
      }
      .footer-col h4 {
        margin-bottom: 0.6rem;
        font-size: 0.9rem;
        color: var(--primary);
      }
      .footer-col a {
        display: block;
        color: #aac8ff;
        text-decoration: none;
        margin: 0.4rem 0;
        font-size: 0.75rem;
        transition: all 0.2s ease;
      }
      .footer-col a:hover {
        color: var(--primary);
        transform: translateX(5px);
      }
      .social-icons {
        display: flex;
        justify-content: center;
        gap: 1.2rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
        padding-top: 1rem;
        border-top: 1px solid rgba(34,197,94,0.2);
      }
      .social-icons i {
        font-size: 1.3rem;
        color: var(--text-light);
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .social-icons i:hover {
        color: var(--primary);
        transform: scale(1.2);
      }
      #scrollTop {
        position: fixed;
        right: 1rem;
        bottom: 1.5rem;
        background: rgba(34,197,94,0.85);
        width: 42px;
        height: 42px;
        border-radius: 50%;
        cursor: pointer;
        display: none;
        align-items: center;
        justify-content: center;
        border: none;
        z-index: 999;
        color: white;
        font-size: 1rem;
        transition: all 0.3s ease;
      }
      #scrollTop:hover {
        background: var(--primary);
        transform: scale(1.1);
      }
      .particle-overlay {
        position: absolute;
        inset: 0;
        overflow: hidden;
        z-index: 0;
        pointer-events: none;
      }
      .particle {
        position: absolute;
        width: 3px;
        height: 3px;
        background: var(--primary);
        border-radius: 50%;
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
      .glow-text {
        border-left: 3px solid var(--primary);
        padding-left: 1rem;
        font-size: clamp(0.9rem, 3vw, 1rem);
      }
      img, video, iframe, canvas, svg {
        max-width: 100%;
        height: auto;
      }
    </style>
  </head>
  <body>
    <!-- NAVBAR - PERMANENTLY FIXED. WILL NEVER SCROLL AWAY -->
    <nav class="navbar">
      <div class="logo" onclick="window.location.href='./index.php'">
        <i class="fa-solid fa-globe"></i><span>Crypterra</span>
      </div>
      <ul class="nav-links">
        <li><a href="./index.php">Home</a></li>
        <li><a href="./about.php" class="active">About</a></li>
        <li><a href="./contact.php">Contact</a></li>
        <li><a href="./faq.php">FAQs</a></li>
        <li><a href="./market.php">Markets</a></li>
        <!--<li>-->
        <!--  -->
        <!--</li>-->
      </ul>
      <button type="button" class="signup" onclick="window.location.href='./login.php'">Sign Up</button>
      <div class="hamburger-wrapper">
        <div class="hamburger-icon" id="hamburgerIcon">
          <div class="bar"></div>
          <div class="bar"></div>
          <div class="bar"></div>
        </div>
        <div class="hamburger-dropdown" id="hamburgerDropdown">
          <ul class="dropdown-links">
            <li><a href="./index.php">🏠 Home</a></li>
            <li><a href="./about.php" class="active">ℹ️ About</a></li>
            <li><a href="./contact.php">📧 Contact</a></li>
            <li><a href="./faq.php">❓ FAQs</a></li>
            <li><a href="./market.php">📈 Markets</a></li>
          </ul>
          <!---->
          <button type="button" class="dropdown-signup" onclick="window.location.href='./login.php'">Sign Up</button>
        </div>
      </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="about-hero">
      <div class="particle-overlay" id="particleHero"></div>
      <div class="container">
        <div class="hero-content" data-aos="fade-right">
          <h1 class="hero-title">Building the Future<br />of Crypto Trading</h1>
          <p class="hero-description">
            Crypterra is a next‑generation crypto trading platform designed to
            provide speed, security, and transparency for traders worldwide.
          </p>
          <div class="hero-cta-group">
            <button class="btn-primary">Learn More</button>
            <button class="btn-outline">Start Trading</button>
          </div>
        </div>
        <div data-aos="fade-left" class="hero-glow-card">
          <div class="trading-widget">
            <div id="tvChart"></div>
            <div class="sl-tp-panel">
              <div class="sl-tp-item">
                <label>SL</label>
                <input type="number" id="stopLoss" step="0.1" placeholder="64200" value="63950" />
              </div>
              <div class="sl-tp-item">
                <label>TP</label>
                <input type="number" id="takeProfit" step="0.1" placeholder="66700" value="66700" />
              </div>
              <div class="live-badge">
                <i class="fa-solid fa-circle"></i> <span>BTC/USDT live</span>
              </div>
            </div>
            <div class="widget-note">
              <i class="fa-regular fa-compass"></i> set SL/TP and simulate
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- REST OF SECTIONS (unchanged content) -->
    <section class="section" data-aos="fade-up">
      <div class="container two-col">
        <div>
          <h2 class="section-title">Our Mission</h2>
          <p style="font-size: clamp(0.9rem, 3vw, 1rem); margin-bottom: 1rem">
            At Crypterra, our mission is to make cryptocurrency trading
            accessible, secure, and efficient for everyone. We combine
            cutting‑edge technology with intuitive design to empower traders
            around the globe.
          </p>
          <p style="font-size: clamp(0.85rem, 3vw, 0.95rem);">
            No matter if you're a beginner or an institution, we provide the
            tools you need to succeed.
          </p>
        </div>
        <div class="glass-image">
          <img src="./IMG/vision.jpg" alt="Crypto trading vision" loading="lazy" />
        </div>
      </div>
    </section>
    
    <section class="section" style="background: radial-gradient(circle at 70% 30%, rgba(34, 197, 94, 0.08), transparent 70%);">
      <div class="particle-overlay" id="visionParticles"></div>
      <div class="container two-col">
        <div class="glass-image" data-aos="fade-right">
          <img src="./IMG/mission.jpg" alt="Future of finance vision" loading="lazy" />
        </div>
        <div data-aos="fade-left">
          <h2 class="section-title">Our Vision for the Future of Finance</h2>
          <p class="glow-text">
            We envision a world where decentralized finance empowers everyone –
            from any country, any background. Accessible trading, global
            inclusion, and self‑custody are not just ideals; they are the
            foundation.
          </p>
        </div>
      </div>
    </section>
    
    <section class="section">
      <div class="container">
        <h2 class="section-title" data-aos="fade-up">Our core values</h2>
        <div class="values-grid">
          <div class="value-card" data-aos="zoom-in"><i class="fa-solid fa-shield-halved"></i><h3>Security</h3><p>Bank‑grade encryption & cold storage</p></div>
          <div class="value-card" data-aos="zoom-in" data-aos-delay="50"><i class="fa-solid fa-scale-balanced"></i><h3>Transparency</h3><p>Real‑time proof of reserves</p></div>
          <div class="value-card" data-aos="zoom-in" data-aos-delay="100"><i class="fa-solid fa-lightbulb"></i><h3>Innovation</h3><p>Cutting‑edge trading tools</p></div>
          <div class="value-card" data-aos="zoom-in" data-aos-delay="150"><i class="fa-solid fa-user-check"></i><h3>User‑first</h3><p>Designed for all traders</p></div>
          <div class="value-card" data-aos="zoom-in" data-aos-delay="200"><i class="fa-solid fa-globe"></i><h3>Global access</h3><p>Serving 120+ countries</p></div>
        </div>
      </div>
    </section>
    
    <section class="section" id="statsSection">
      <div class="container">
        <h2 class="section-title" data-aos="fade-up">Crypterra by the numbers</h2>
        <div class="stats-grid">
          <div class="stat-card"><i class="fa-solid fa-users"></i><div class="stat-number" data-target="250" data-suffix="K+">0</div><div class="stat-label">Active Traders</div></div>
          <div class="stat-card"><i class="fa-solid fa-chart-simple"></i><div class="stat-number" data-target="4.2" data-prefix="$" data-suffix="B">0</div><div class="stat-label">Daily Volume</div></div>
          <div class="stat-card"><i class="fa-solid fa-coins"></i><div class="stat-number" data-target="200" data-suffix="+">0</div><div class="stat-label">Supported Assets</div></div>
          <div class="stat-card"><i class="fa-solid fa-flag"></i><div class="stat-number" data-target="120" data-suffix="+">0</div><div class="stat-label">Countries</div></div>
          <div class="stat-card"><i class="fa-solid fa-clock"></i><div class="stat-number" data-target="99.99" data-suffix="%">0</div><div class="stat-label">Uptime</div></div>
        </div>
      </div>
    </section>
    
    <section class="section">
      <div class="container">
        <h2 class="section-title" data-aos="fade-up">Leadership</h2>
        <div class="team-grid">
          <div class="team-card" data-aos="flip-left"><div class="team-avatar"><i class="fa-regular fa-user"></i></div><h3>Alex Rivera</h3><p style="color: var(--primary); font-size: 0.85rem;">CEO & Co‑founder</p><div class="team-social"><i class="fa-brands fa-twitter"></i><i class="fa-brands fa-linkedin"></i></div></div>
          <div class="team-card" data-aos="flip-left" data-aos-delay="50"><div class="team-avatar"><i class="fa-regular fa-user"></i></div><h3>Maya Chen</h3><p style="color: var(--primary); font-size: 0.85rem;">CTO</p><div class="team-social"><i class="fa-brands fa-twitter"></i><i class="fa-brands fa-github"></i></div></div>
          <div class="team-card" data-aos="flip-left" data-aos-delay="100"><div class="team-avatar"><i class="fa-regular fa-user"></i></div><h3>James Wright</h3><p style="color: var(--primary); font-size: 0.85rem;">Head of Security</p><div class="team-social"><i class="fa-brands fa-twitter"></i><i class="fa-brands fa-linkedin"></i></div></div>
          <div class="team-card" data-aos="flip-left" data-aos-delay="150"><div class="team-avatar"><i class="fa-regular fa-user"></i></div><h3>Nadia Khan</h3><p style="color: var(--primary); font-size: 0.85rem;">Lead Blockchain Eng.</p><div class="team-social"><i class="fa-brands fa-github"></i><i class="fa-brands fa-twitter"></i></div></div>
        </div>
      </div>
    </section>
    
    <section class="section">
      <div class="container">
        <h2 class="section-title" data-aos="fade-up">Our journey</h2>
        <div class="timeline">
          <div class="timeline-item" data-aos="fade-right"><div class="dot"></div><div class="content"><h3>2021</h3><p>Crypterra founded – a vision to reshape crypto trading.</p></div></div>
          <div class="timeline-item" data-aos="fade-left"><div class="dot"></div><div class="content"><h3>2022</h3><p>First trading engine launched with 50+ assets.</p></div></div>
          <div class="timeline-item" data-aos="fade-right"><div class="dot"></div><div class="content"><h3>2023</h3><p>Platform reaches 100K users & institutional liquidity.</p></div></div>
          <div class="timeline-item" data-aos="fade-left"><div class="dot"></div><div class="content"><h3>2024</h3><p>Global expansion – serving 120+ countries.</p></div></div>
        </div>
      </div>
    </section>
    
    <section class="section">
      <div class="container cta-glass" data-aos="zoom-in">
        <h2 style="font-size: clamp(1.5rem, 6vw, 2.5rem);">Join the Next Generation of Crypto Trading</h2>
        <p style="margin: 1rem 0; font-size: clamp(0.9rem, 4vw, 1.1rem);">Start your journey with Crypterra today.</p>
        <div class="hero-cta-group" style="justify-content: center;">
          <button class="btn-primary">Start Trading</button>
                  </div>
      </div>
    </section>
    
    <footer class="footer">
      <div class="footer-grid">
        <div class="footer-col"><h4>Product</h4><a href="#">Exchange</a><a href="#">Derivatives</a><a href="#">NFT</a></div>
        <div class="footer-col"><h4>Markets</h4><a href="#">Spot</a><a href="#">Futures</a><a href="#">Options</a></div>
        <div class="footer-col"><h4>Security</h4><a href="#">Audits</a><a href="#">Bug bounty</a></div>
        <div class="footer-col"><h4>API</h4><a href="#">Documentation</a><a href="#">WebSocket</a></div>
        <div class="footer-col"><h4>Company</h4><a href="#">Careers</a><a href="#">Blog</a><a href="#">Support</a></div>
      </div>
      <div class="social-icons">
        <i class="fa-brands fa-twitter"></i><i class="fa-brands fa-discord"></i>
        <i class="fa-brands fa-telegram"></i><i class="fa-brands fa-github"></i>
      </div>
      <div style="text-align: center; margin-top: 1.2rem; font-size: 0.65rem; opacity: 0.6;">&copy; 2025 Crypterra. All rights reserved.</div>
    </footer>
    
    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>

    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      (function () {
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
        
        AOS.init({ duration: 600, once: false, mirror: true });
        
        // Stats animation
        const statsSection = document.getElementById("statsSection");
        const statNumbers = document.querySelectorAll(".stat-number");
        let animated = false;
        function animateNumbers() {
          if (animated) return;
          statNumbers.forEach((stat) => {
            const target = parseFloat(stat.dataset.target);
            const suffix = stat.dataset.suffix || "";
            const prefix = stat.dataset.prefix || "";
            let counter = 0;
            const duration = 1800;
            const stepTime = 16;
            const steps = duration / stepTime;
            const increment = target / steps;
            const timer = setInterval(() => {
              counter += increment;
              if (counter >= target) {
                stat.textContent = prefix + target.toLocaleString() + suffix;
                clearInterval(timer);
              } else {
                if (Number.isInteger(target))
                  stat.textContent = prefix + Math.floor(counter).toLocaleString() + suffix;
                else stat.textContent = prefix + counter.toFixed(2) + suffix;
              }
            }, stepTime);
          });
          animated = true;
        }
        if (statsSection) {
          const observer = new IntersectionObserver(
            (entries) => {
              entries.forEach((entry) => {
                if (entry.isIntersecting) {
                  animateNumbers();
                  observer.unobserve(entry.target);
                }
              });
            },
            { threshold: 0.2 }
          );
          observer.observe(statsSection);
        }
        
        // Particles
        function createParticles(containerId, count = 20) {
          const container = document.getElementById(containerId);
          if (!container) return;
          for (let i = 0; i < count; i++) {
            let p = document.createElement("div");
            p.className = "particle";
            p.style.left = Math.random() * 100 + "%";
            p.style.animationDelay = Math.random() * 10 + "s";
            p.style.animationDuration = 15 + Math.random() * 10 + "s";
            container.appendChild(p);
          }
        }
        createParticles("particleHero", 20);
        createParticles("visionParticles", 15);
        
        // Chart
        const chartContainer = document.getElementById("tvChart");
        if (chartContainer && typeof window.LightweightCharts !== "undefined") {
          const chart = LightweightCharts.createChart(chartContainer, {
            layout: { textColor: "#d1d5db", background: { type: "solid", color: "transparent" } },
            grid: { vertLines: { color: "rgba(34,197,94,0.08)" }, horzLines: { color: "rgba(34,197,94,0.08)" } },
            timeScale: { borderColor: "rgba(34,197,94,0.15)" },
            rightPriceScale: { borderColor: "rgba(34,197,94,0.15)" },
            width: chartContainer.clientWidth,
            height: 200,
          });
          const candlestickSeries = chart.addCandlestickSeries({
            upColor: "#26a69a",
            downColor: "#ef5350",
            borderVisible: false,
            wickUpColor: "#26a69a",
            wickDownColor: "#ef5350",
          });
          const startTime = Math.floor(Date.now() / 1000) - 3600 * 24;
          let data = [];
          let price = 64500;
          for (let i = 0; i < 100; i++) {
            const time = startTime + i * 1200;
            const change = (Math.random() - 0.5) * 350;
            const open = price;
            const close = Math.max(30000, open + change);
            const high = Math.max(open, close) + Math.random() * 180;
            const low = Math.min(open, close) - Math.random() * 180;
            data.push({ time, open, high, low, close });
            price = close;
          }
          candlestickSeries.setData(data);
          chart.timeScale().fitContent();
          
          window.addEventListener('resize', () => {
            chart.applyOptions({ width: chartContainer.clientWidth });
          });
        }
      })();
    </script>
  <?php include __DIR__ . '/includes/smartsupp.php'; ?>
  </body>
</html>