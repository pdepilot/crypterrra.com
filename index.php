<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover"
    />
    <meta name="format-detection" content="telephone=no" />
    <title>Crypterra · next‑gen crypto trading | Fully Responsive</title>
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
    <style>
      /* ---------- RESET & VARIABLES ---------- */
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
      body {
        font-family: "Inter", system-ui, sans-serif;
        background: var(--dark-bg);
        color: var(--text-light);
        line-height: 1.5;
        scroll-behavior: smooth;
        overflow-x: hidden;
        width: 100%;
        position: relative;
        /* CRITICAL: Add padding to body to prevent content from hiding under fixed navbar */
        padding-top: 70px;
      }
      /* CRITICAL: Prevent any external widget from breaking layout */
      body,
      html {
        max-width: 100%;
        overflow-x: hidden;
      }
      h1,
      h2,
      h3 {
        font-weight: 700;
        letter-spacing: -0.02em;
      }
      /* ---------- PRELOADER ---------- */
      #preloader {
        position: fixed;
        inset: 0;
        background: rgba(7, 20, 34, 0.98);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        z-index: 10001;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        transition:
          opacity 0.8s ease,
          visibility 0.8s;
        opacity: 1;
        visibility: visible;
      }
      #preloader.hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
      }
      .preloader-content {
        text-align: center;
        position: relative;
      }
      .glowing-logo {
        font-size: clamp(3rem, 10vw, 5rem);
        display: flex;
        gap: 0.4rem;
        align-items: center;
        justify-content: center;
        margin-bottom: 2rem;
        animation: pulseGlow 2s infinite alternate;
      }
      .glowing-logo i {
        font-size: clamp(3.5rem, 12vw, 5.5rem);
        background: linear-gradient(145deg, var(--primary), #7ef0a2);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }
      .glowing-logo span {
        background: linear-gradient(145deg, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        font-size: clamp(1.8rem, 8vw, 3rem);
      }
      @keyframes pulseGlow {
        0% {
          filter: drop-shadow(0 0 15px var(--primary-glow));
        }
        100% {
          filter: drop-shadow(0 0 45px #22c55e);
        }
      }
      .orbit-ring {
        width: min(200px, 60vw);
        height: min(200px, 60vw);
        border: 2px solid rgba(34, 197, 94, 0.3);
        border-radius: 50%;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        animation: spin 6s linear infinite;
      }
      .orbit-ring::before {
        content: "";
        position: absolute;
        width: 16px;
        height: 16px;
        background: var(--primary);
        border-radius: 50%;
        top: -8px;
        left: 50%;
        transform: translateX(-50%);
      }
      @keyframes spin {
        0% {
          transform: translate(-50%, -50%) rotate(0deg);
        }
        100% {
          transform: translate(-50%, -50%) rotate(360deg);
        }
      }
      .particle-container {
        position: absolute;
        width: 100%;
        height: 100%;
        overflow: hidden;
        z-index: -1;
      }
      .particle {
        position: absolute;
        width: 4px;
        height: 4px;
        background: var(--primary);
        border-radius: 50%;
        opacity: 0.4;
        animation: floatUp 8s infinite;
      }
      @keyframes floatUp {
        0% {
          transform: translateY(0) scale(1);
          opacity: 0.2;
        }
        100% {
          transform: translateY(-100vh) scale(0);
          opacity: 0;
        }
      }
      .progress-bar {
        width: min(260px, 70vw);
        height: 4px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        margin: 2rem auto 0;
        overflow: hidden;
      }
      .progress-fill {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, var(--primary), #9ef0b0);
        transition: width 0.2s ease;
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
        box-shadow:
          0 8px 28px rgba(0, 0, 0, 0.5),
          0 1px 0 rgba(34, 197, 94, 0.3);
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
        backdrop-filter: blur(
          0px
        ); /* No blur that might cause rendering issues */
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
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
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
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
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
      /* ---------- HERO SLIDER ---------- */
      .hero-slider {
        position: relative;
        height: 85vh;
        min-height: 500px;
        max-height: 800px;
        overflow: hidden;
        isolation: isolate;
        width: 100%;
        margin-top: 0;
      }
      @media (max-width: 768px) {
        .hero-slider {
          height: 80vh;
          min-height: 450px;
        }
      }
      @media (max-width: 480px) {
        .hero-slider {
          height: 75vh;
          min-height: 400px;
        }
      }
      #candlestick-canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 5;
        pointer-events: none;
        opacity: 0.4;
      }
      .slide {
        position: absolute;
        inset: 0;
        opacity: 0;
        visibility: hidden;
        background-size: cover;
        background-position: center;
        transition:
          opacity 1s ease,
          visibility 1s;
        display: flex;
        align-items: center;
        padding: 0 5%;
        z-index: 10;
      }
      .slide-1 {
        background-image:
          linear-gradient(97deg, rgba(0, 20, 10, 0.8), rgba(0, 40, 25, 0.6)),
          url("https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?q=80&w=2070&auto=format&fit=crop");
      }
      .slide-2 {
        background-image:
          linear-gradient(112deg, rgba(0, 30, 20, 0.8), rgba(0, 20, 30, 0.6)),
          url("https://images.unsplash.com/photo-1621761191319-c6fb3e40b669?q=80&w=2070&auto=format&fit=crop");
      }
      .slide-3 {
        background-image:
          linear-gradient(135deg, rgba(0, 40, 25, 0.8), rgba(10, 20, 40, 0.7)),
          url("https://images.unsplash.com/photo-1639762681485-074b7f938ba0?q=80&w=2232&auto=format&fit=crop");
      }
      .slide.active-slide {
        opacity: 1;
        visibility: visible;
        z-index: 15;
      }
      .hero-content {
        max-width: 700px;
        color: white;
        z-index: 20;
        padding: 0.5rem 0;
        width: 100%;
      }
      .hero-content h1 {
        font-size: clamp(1.8rem, 7vw, 4.5rem);
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: 1rem;
        background: linear-gradient(to right, #ffffff, #d4ffe0);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }
      .hero-content p {
        font-size: clamp(0.85rem, 3.5vw, 1.2rem);
        backdrop-filter: blur(6px);
        padding: 0.7rem 1.2rem;
        border-radius: 40px;
        border-left: 4px solid var(--primary);
        margin-bottom: 1.5rem;
        background: rgba(0, 20, 10, 0.3);
        width: fit-content;
        max-width: 90%;
      }
      .hero-cta .btn {
        background: rgba(34, 197, 94, 0.95);
        backdrop-filter: blur(6px);
        padding: 0.7rem 1.5rem;
        border-radius: 50px;
        font-weight: 600;
        font-size: clamp(0.85rem, 3vw, 1rem);
        color: #0b1c2f;
        border: none;
        box-shadow: 0 12px 20px -6px var(--primary);
        cursor: pointer;
        transition: 0.2s;
      }
      .glass-orb {
        position: absolute;
        right: 2%;
        top: 20%;
        width: min(35vw, 280px);
        height: min(35vw, 280px);
        border-radius: 50%;
        background: rgba(34, 197, 94, 0.05);
        border: 2px solid rgba(34, 197, 94, 0.2);
        backdrop-filter: blur(12px);
        pointer-events: none;
      }
      @media (max-width: 600px) {
        .glass-orb {
          display: none;
        }
      }
      /* ---------- TICKER ---------- */
      .ticker-wrap {
        background: rgba(7, 20, 34, 0.7);
        backdrop-filter: blur(16px);
        border-top: 1px solid var(--glass-edge);
        border-bottom: 1px solid var(--glass-edge);
        padding: 0.7rem 0;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        white-space: nowrap;
        width: 100%;
      }
      .ticker {
        display: inline-flex;
        gap: 1rem;
        animation: tickerScroll 45s linear infinite;
        padding-left: 1rem;
      }
      .ticker-item {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.03);
        padding: 0.35rem 1rem;
        border-radius: 60px;
        border: 1px solid rgba(34, 197, 94, 0.2);
        white-space: nowrap;
      }
      .ticker-coin-icon {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        flex-shrink: 0;
      }
      .ticker-info {
        display: inline-flex;
        gap: 0.4rem;
        align-items: baseline;
        flex-wrap: nowrap;
      }
      .ticker-symbol {
        font-weight: 700;
        font-size: 0.85rem;
      }
      .ticker-price {
        font-family: monospace;
        font-size: 0.85rem;
      }
      @keyframes tickerScroll {
        0% {
          transform: translateX(0);
        }
        100% {
          transform: translateX(-50%);
        }
      }
      /* ---------- MAIN SECTIONS ---------- */
      .section {
        padding: 3rem 5% 4rem;
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
        font-size: clamp(1.8rem, 6vw, 3rem);
        margin-bottom: 1.8rem;
        background: linear-gradient(to right, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        display: inline-block;
      }
      .feature-grid,
      .asset-grid,
      .security-grid,
      .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
      }
      @media (max-width: 600px) {
        .feature-grid,
        .asset-grid,
        .stats-grid {
          grid-template-columns: 1fr;
          gap: 1rem;
        }
      }
      .feature-card,
      .asset-card,
      .stat-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 1.5rem;
        text-align: center;
        transition: 0.2s;
      }
      .security-item {
        display: flex;
        gap: 1rem;
        background: rgba(255, 255, 255, 0.02);
        padding: 1.2rem;
        border-radius: 24px;
        align-items: center;
        flex-wrap: wrap;
      }
      .security-item i {
        font-size: 2rem;
        color: var(--primary);
      }
      .two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        align-items: center;
      }
      @media (max-width: 780px) {
        .two-col {
          grid-template-columns: 1fr;
          text-align: center;
          gap: 1.5rem;
        }
      }
      .preview-frame {
        background: rgba(0, 0, 0, 0.3);
        border-radius: 28px;
        padding: 1rem;
        border: 1px solid rgba(34, 197, 94, 0.4);
      }
      .preview-frame img {
        width: 100%;
        height: auto;
        border-radius: 20px;
        display: block;
      }
      .step-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 1.2rem;
      }
      .step {
        flex: 1 1 200px;
        background: var(--glass-bg);
        backdrop-filter: blur(8px);
        padding: 1.5rem;
        border-radius: 32px;
        text-align: center;
        position: relative;
      }
      @media (max-width: 700px) {
        .step {
          flex: 1 1 100%;
        }
      }
      .testimonial-marquee {
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        white-space: nowrap;
        width: 100%;
        padding-bottom: 0.5rem;
      }
      .marquee-row {
        display: inline-flex;
        gap: 1.2rem;
        white-space: nowrap;
      }
      .testimonial-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border-radius: 24px;
        padding: 1.2rem;
        display: inline-block;
        width: 270px;
        vertical-align: top;
        white-space: normal;
      }
      @media (max-width: 600px) {
        .testimonial-card {
          width: 250px;
        }
      }
      .testimonial-card .stars {
        color: gold;
        margin: 0.5rem 0;
      }
      .faq-item {
        background: rgba(255, 255, 255, 0.02);
        border-radius: 20px;
        margin-bottom: 0.8rem;
      }
      .faq-question {
        padding: 1rem 1.2rem;
        font-weight: 600;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        gap: 0.8rem;
      }
      .faq-answer {
        padding: 0 1.2rem 1rem;
        display: none;
        line-height: 1.5;
      }
      .faq-item.open .faq-answer {
        display: block;
      }
      .cta-section {
        text-align: center;
        border-radius: 48px;
        padding: 2.5rem 1.5rem;
        background: radial-gradient(
          circle at 50% 50%,
          rgba(34, 197, 94, 0.08),
          transparent
        );
        margin: 1rem 0;
      }
      .cta-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 1.5rem;
      }
      .btn-primary,
      .btn-outline {
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
      .btn-outline {
        background: transparent;
        border: 2px solid var(--primary);
        color: white;
      }
      /* ---------- FOOTER - FULLY RESPONSIVE ---------- */
      .footer {
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(20px);
        border-top: 1px solid var(--primary);
        padding: 3rem 5% 2rem;
        margin-top: 2rem;
        width: 100%;
        clear: both;
        position: relative;
        z-index: 1;
      }
      .footer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 2rem;
        width: 100%;
      }
      @media (max-width: 550px) {
        .footer-grid {
          grid-template-columns: repeat(2, 1fr);
          gap: 1.5rem;
        }
      }
      .footer-col h4 {
        margin-bottom: 1rem;
        font-size: 1rem;
        color: var(--primary);
      }
      .footer-col a {
        display: block;
        color: #aac8ff;
        text-decoration: none;
        margin: 0.6rem 0;
        transition: 0.2s;
        font-size: 0.85rem;
      }
      .footer-col a:hover {
        color: var(--primary);
        transform: translateX(5px);
      }
      .social-icons {
        display: flex;
        justify-content: center;
        gap: 1.8rem;
        margin-top: 2.5rem;
        flex-wrap: wrap;
        padding-top: 1rem;
        border-top: 1px solid rgba(34, 197, 94, 0.2);
      }
      .social-icons i {
        font-size: 1.6rem;
        color: var(--text-light);
        transition: 0.2s;
        cursor: pointer;
      }
      .social-icons i:hover {
        color: var(--primary);
        transform: scale(1.1);
      }
      /* ---------- SCROLL TOP ---------- */
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
        font-size: 1.2rem;
      }
      /* ---------- NOTIFICATIONS ---------- */
      .notification-container {
        position: fixed;
        top: 80px;
        left: 12px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 8px;
        pointer-events: none;
        max-width: calc(100% - 24px);
      }
      .notification-container.bottom {
        top: auto;
        bottom: 80px;
      }
      .notification-toast {
        background: rgba(7, 20, 34, 0.95);
        backdrop-filter: blur(20px);
        border-left: 4px solid var(--primary);
        border-radius: 16px;
        padding: 0.7rem 1rem;
        font-size: 0.75rem;
        max-width: 280px;
        transform: translateX(-120%);
        opacity: 0;
        transition: 0.4s ease;
        color: white;
        display: flex;
        align-items: center;
        gap: 8px;
      }
      .notification-toast.show {
        transform: translateX(0);
        opacity: 1;
      }
      .positive {
        color: #26a69a;
      }
      .negative {
        color: #ef5350;
      }
      .neutral-change {
        color: rgba(224, 237, 255, 0.65);
      }
      img,
      video,
      iframe,
      canvas {
        max-width: 100%;
        height: auto;
      }
      body,
      html {
        overflow-x: hidden !important;
        width: 100%;
      }
    </style>
  </head>
  <body>
    <div id="preloader">
      <div class="particle-container" id="particles"></div>
      <div class="preloader-content">
        <div class="glowing-logo">
          <i class="fa-solid fa-globe"></i><span>Crypterra</span>
        </div>
        <div class="orbit-ring"></div>
        <div class="progress-bar">
          <div class="progress-fill" id="progressFill"></div>
        </div>
      </div>
    </div>

    <nav class="navbar">
      <div class="logo">
        <i class="fa-solid fa-globe"></i><span>Crypterra</span>
      </div>
      <ul class="nav-links">
        <li><a href="./index.php" class="active">Home</a></li>
        <li><a href="./about.php">About</a></li>
        <li><a href="./contact.php">Contact</a></li>
        <li><a href="./faq.php">FAQs</a></li>
        <li><a href="./market.php">Markets</a></li>
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
          <a href="./login.php" class="dropdown-signup-link">Sign Up</a>
        </div>
      </div>
    </nav>

    <section class="hero-slider">
      <canvas id="candlestick-canvas"></canvas>
      <div class="glass-orb"></div>
      <div class="slide slide-1">
        <div class="hero-content">
          <h1>Smart trading,<br />powered by clarity</h1>
          <p>Institutional‑grade security meets intuitive design. Join Crypterra.</p>
          <div class="hero-cta">
            <button class="btn" onclick="window.location.href='./login.php'">Start trading →</button>
          </div>
        </div>
      </div>
      <div class="slide slide-2">
        <div class="hero-content">
          <h1>Own your financial<br />future with crypto</h1>
          <p>Diversify with 200+ assets, zero hidden fees.</p>
          <div class="hero-cta">
            <button class="btn" onclick="window.location.href='./market.php'">Explore markets →</button>
          </div>
        </div>
      </div>
      <div class="slide slide-3">
        <div class="hero-content">
          <h1>Trade. Scale.<br />Stay in control.</h1>
          <p>Real‑time charts, deep liquidity, and fast execution.</p>
          <div class="hero-cta">
            <button class="btn" onclick="window.location.href='./login.php'">Start trading →</button>
          </div>
        </div>
      </div>
    </section>

    <div class="ticker-wrap"><div class="ticker" id="ticker"></div></div>

    <main>
      <section class="section" data-aos="fade-up">
        <div class="container">
          <h2 class="section-title">Platform power</h2>
          <div class="feature-grid">
            <div class="feature-card">
              <i class="fa-solid fa-bolt"></i>
              <h3>Lightning execution</h3>
              <p>0.1ms order routing</p>
            </div>
            <div class="feature-card">
              <i class="fa-solid fa-shield-halved"></i>
              <h3>Bank-grade security</h3>
              <p>Multisig cold storage</p>
            </div>
            <div class="feature-card">
              <i class="fa-solid fa-chart-line"></i>
              <h3>Advanced charts</h3>
              <p>TradingView integrated</p>
            </div>
            <div class="feature-card">
              <i class="fa-solid fa-coins"></i>
              <h3>Zero hidden fees</h3>
              <p>0.1% per trade</p>
            </div>
          </div>
        </div>
      </section>

      <section class="section" data-aos="fade-up">
        <div class="container">
          <h2 class="section-title">Security first</h2>
          <div class="security-grid">
            <div class="security-item">
              <i class="fa-solid fa-user-shield"></i>
              <div>
                <h3>AI fraud detection</h3>
                <p>Real‑time monitoring</p>
              </div>
            </div>
            <div class="security-item">
              <i class="fa-solid fa-shield"></i>
              <div>
                <h3>Encrypted ledger</h3>
                <p>Blockchain backed</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="section" data-aos="fade-up">
        <div class="container">
          <h2 class="section-title">Start in minutes</h2>
          <div class="step-container">
            <div class="step">
              <i class="fa-solid fa-user-plus"></i>
              <h3>Create account</h3>
              <p>Email signup in a few steps</p>
            </div>
            <div class="step">
              <i class="fa-solid fa-building-columns"></i>
              <h3>Fund your account</h3>
              <p>Deposit and you're ready</p>
            </div>
            <div class="step">
              <i class="fa-solid fa-rocket"></i>
              <h3>Start trading</h3>
              <p>Trade instantly</p>
            </div>
          </div>
        </div>
      </section>

      <section class="section performance-section" data-aos="fade-up" id="performanceSection">
        <div class="container">
          <h2 class="section-title">Platform Performance</h2>
          <div class="stats-grid">
            <div class="stat-card">
              <i class="fa-solid fa-users"></i>
              <div class="stat-number" data-target="250" data-suffix="K+">0</div>
              <div class="stat-label">Active Traders</div>
            </div>
            <div class="stat-card">
              <i class="fa-solid fa-chart-simple"></i>
              <div class="stat-number" data-target="4.2" data-prefix="$" data-suffix="B">0</div>
              <div class="stat-label">Daily Volume</div>
            </div>
            <div class="stat-card">
              <i class="fa-solid fa-coins"></i>
              <div class="stat-number" data-target="200" data-suffix="+">0</div>
              <div class="stat-label">Crypto Assets</div>
            </div>
            <div class="stat-card">
              <i class="fa-solid fa-flag"></i>
              <div class="stat-number" data-target="120" data-suffix="+">0</div>
              <div class="stat-label">Countries</div>
            </div>
            <div class="stat-card">
              <i class="fa-solid fa-clock"></i>
              <div class="stat-number" data-target="99.99" data-suffix="%">0</div>
              <div class="stat-label">Platform Uptime</div>
            </div>
          </div>
        </div>
      </section>

      <section class="section" data-aos="fade-up">
        <div class="container">
          <h2 class="section-title">Frequently asked</h2>
          <div class="faq-list">
            <div class="faq-item">
              <div class="faq-question">
                <span>Is Crypterra secure?</span><i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">Yes, bank‑grade encryption, cold wallets, regular audits.</div>
            </div>
            <div class="faq-item">
              <div class="faq-question">
                <span>Trading fees?</span><i class="fa-solid fa-chevron-down"></i>
              </div>
              <div class="faq-answer">0.1% per trade, zero hidden fees. Discounts for high volume.</div>
            </div>
          </div>
        </div>
      </section>

      <section class="section" data-aos="flip-up">
        <div class="container cta-section">
          <h2 style="font-size: clamp(1.8rem, 7vw, 3rem)">Ready to start trading?</h2>
          <p style="font-size: clamp(1rem, 4vw, 1.3rem)">Join thousands building their future with Crypterra.</p>
          <div class="cta-buttons">
            <button class="btn-primary" onclick="window.location.href='./login.php'">Start Trading</button>
          </div>
        </div>
      </section>
    </main>

    <footer class="footer">
      <div class="footer-grid">
        <div class="footer-col"><h4>Product</h4><a href="#">Exchange</a><a href="#">Derivatives</a><a href="#">NFT</a></div>
        <div class="footer-col"><h4>Markets</h4><a href="#">Spot</a><a href="#">Futures</a><a href="#">Options</a></div>
        <div class="footer-col"><h4>API</h4><a href="#">Documentation</a><a href="#">WebSocket</a></div>
        <div class="footer-col"><h4>Security</h4><a href="#">Audits</a><a href="#">Bug bounty</a></div>
        <div class="footer-col"><h4>Company</h4><a href="#">Careers</a><a href="#">Blog</a><a href="#">Support</a></div>
      </div>
      <div class="social-icons">
        <i class="fa-brands fa-twitter"></i><i class="fa-brands fa-discord"></i><i class="fa-brands fa-telegram"></i><i class="fa-brands fa-github"></i>
      </div>
      <div style="text-align: center; margin-top: 2rem; font-size: 0.75rem; opacity: 0.6;">&copy; 2025 Crypterra. All rights reserved.</div>
    </footer>

    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      (function () {
        // Preloader
        const preloader = document.getElementById("preloader"),
          progressFill = document.getElementById("progressFill");
        let progress = 0;
        const interval = setInterval(() => {
          progress += Math.random() * 15;
          if (progress >= 100) {
            progress = 100;
            clearInterval(interval);
            setTimeout(() => preloader.classList.add("hidden"), 500);
          }
          progressFill.style.width = progress + "%";
        }, 180);
        const container = document.getElementById("particles");
        for (let i = 0; i < 30; i++) {
          let p = document.createElement("div");
          p.className = "particle";
          p.style.left = Math.random() * 100 + "%";
          p.style.animationDelay = Math.random() * 5 + "s";
          container.appendChild(p);
        }

        // Crypto ticker and assets
        const tickerEl = document.getElementById("ticker"),
          assetGrid = document.getElementById("assetGrid");
        const COINGECKO_IDS = [
          "bitcoin",
          "ethereum",
          "solana",
          "tether",
          "binancecoin",
          "dogecoin",
        ];
        const MARKETS_URL =
          "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=" +
          COINGECKO_IDS.join(",") +
          "&order=market_cap_desc&per_page=20&page=1&sparkline=false";
        let liveCoins = [];
        function formatUsd(n) {
          if (n == null || isNaN(n)) return "—";
          if (n >= 1)
            return (
              "$" + n.toLocaleString(undefined, { maximumFractionDigits: 2 })
            );
          return (
            "$" + n.toLocaleString(undefined, { maximumFractionDigits: 6 })
          );
        }
        function formatPct(p) {
          if (p == null || isNaN(p)) return "—";
          const s = p >= 0 ? "+" : "";
          return s + p.toFixed(2) + "%";
        }
        function sortCoins(arr) {
          return [...arr].sort(
            (a, b) => COINGECKO_IDS.indexOf(a.id) - COINGECKO_IDS.indexOf(b.id),
          );
        }
        function renderTicker() {
          if (!tickerEl || !liveCoins.length) return;
          const row = liveCoins
            .map(
              (c) =>
                `<div class="ticker-item"><img class="ticker-coin-icon" src="${c.image}" alt="" width="22" height="22" loading="lazy" /><div class="ticker-info"><span class="ticker-symbol">${(c.symbol || "").toUpperCase()}</span><span class="ticker-price">${formatUsd(c.current_price)}</span><span class="ticker-24h-label">24h</span><span class="ticker-change ${c.price_change_percentage_24h >= 0 ? "positive" : "negative"}">${formatPct(c.price_change_percentage_24h)}</span></div></div>`,
            )
            .join("");
          let html = "";
          for (let i = 0; i < 4; i++) html += row;
          tickerEl.innerHTML = html + html;
        }
        function renderAssets() {
          if (!assetGrid) return;
          if (!liveCoins.length) {
            assetGrid.innerHTML =
              '<p style="text-align:center">Loading prices...</p>';
            return;
          }
          assetGrid.innerHTML = liveCoins
            .map(
              (c) =>
                `<div class="asset-card"><img class="asset-icon" src="${c.image}" alt="${c.name}" width="52" height="52" loading="lazy" /><div class="name">${c.name}</div><div class="price">${formatUsd(c.current_price)}</div><div class="change-24h ${c.price_change_percentage_24h >= 0 ? "positive" : "negative"}"><span class="lbl">24h</span>${formatPct(c.price_change_percentage_24h)}</div></div>`,
            )
            .join("");
        }
        async function fetchLiveMarkets() {
          try {
            const res = await fetch(MARKETS_URL);
            if (!res.ok) throw new Error();
            const data = await res.json();
            if (data.length) {
              liveCoins = sortCoins(data);
              renderTicker();
              renderAssets();
            }
          } catch (e) {
            console.log("Market fetch fallback");
          }
        }
        renderAssets();
        fetchLiveMarkets();
        setInterval(fetchLiveMarkets, 60000);

        // Hamburger menu
        const hamburgerIcon = document.getElementById("hamburgerIcon"),
          dropdown = document.getElementById("hamburgerDropdown");
        if (hamburgerIcon) {
          hamburgerIcon.addEventListener("click", (e) => {
            e.stopPropagation();
            dropdown.classList.toggle("open");
          });
          document.addEventListener("click", (e) => {
            if (
              !hamburgerIcon.contains(e.target) &&
              !dropdown.contains(e.target)
            )
              dropdown.classList.remove("open");
          });
        }

        // Scroll top btn
        const scrollBtn = document.getElementById("scrollTop");
        window.addEventListener("scroll", () => {
          scrollBtn.style.display = window.scrollY > 250 ? "flex" : "none";
        });
        scrollBtn.addEventListener("click", () =>
          window.scrollTo({ top: 0, behavior: "smooth" }),
        );

        // FAQ
        document.querySelectorAll(".faq-question").forEach((q) =>
          q.addEventListener("click", function () {
            this.closest(".faq-item").classList.toggle("open");
          }),
        );

        // Canvas candlestick animation
        const canvas = document.getElementById("candlestick-canvas");
        if (canvas) {
          const ctx = canvas.getContext("2d");
          let w, h;
          function resize() {
            w = canvas.clientWidth;
            h = document.querySelector(".hero-slider")?.clientHeight || 500;
            canvas.width = w;
            canvas.height = h;
          }
          resize();
          window.addEventListener("resize", resize);
          function draw() {
            if (!ctx) return;
            ctx.clearRect(0, 0, w, h);
            for (let i = 0; i < 18; i++) {
              let x = (i * 50 + Date.now() * 0.02) % w;
              let y = h / 2 + Math.sin(i + Date.now() * 0.002) * 25;
              ctx.fillStyle = i % 2 ? "#26a69a80" : "#ef535080";
              ctx.fillRect(x - 6, y - 10, 12, 20);
              ctx.strokeStyle = i % 2 ? "#26a69a" : "#ef5350";
              ctx.lineWidth = 1.5;
              ctx.beginPath();
              ctx.moveTo(x, y - 18);
              ctx.lineTo(x, y + 18);
              ctx.stroke();
            }
            requestAnimationFrame(draw);
          }
          draw();
        }

        // Stats counter
        const statsSection = document.getElementById("performanceSection");
        let animated = false;
        function animateNumbers() {
          if (animated) return;
          document.querySelectorAll(".stat-number").forEach((stat) => {
            const target = parseFloat(stat.dataset.target),
              suffix = stat.dataset.suffix || "",
              prefix = stat.dataset.prefix || "";
            let counter = 0;
            const duration = 1800,
              stepTime = 16,
              increment = target / (duration / stepTime);
            const timer = setInterval(() => {
              counter += increment;
              if (counter >= target) {
                stat.textContent = prefix + target.toLocaleString() + suffix;
                clearInterval(timer);
              } else {
                if (Number.isInteger(target))
                  stat.textContent =
                    prefix + Math.floor(counter).toLocaleString() + suffix;
                else stat.textContent = prefix + counter.toFixed(2) + suffix;
              }
            }, stepTime);
          });
          animated = true;
        }
        if (statsSection) {
          const observer = new IntersectionObserver(
            (e) => {
              e.forEach((entry) => {
                if (entry.isIntersecting) {
                  animateNumbers();
                  observer.disconnect();
                }
              });
            },
            { threshold: 0.2 },
          );
          observer.observe(statsSection);
        }

        AOS.init({ duration: 700, once: false });

        // Hero slider
        let currentIndex = 0;
        const slides = document.querySelectorAll(".slide");
        function nextSlide() {
          currentIndex = (currentIndex + 1) % slides.length;
          slides.forEach((s) => s.classList.remove("active-slide"));
          slides[currentIndex].classList.add("active-slide");
        }
        setInterval(nextSlide, 5500);
        if (slides.length) slides[0].classList.add("active-slide");
      })();
    </script>
  <?php include __DIR__ . '/includes/smartsupp.php'; ?>
  </body>
</html>
