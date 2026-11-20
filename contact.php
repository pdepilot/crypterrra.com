<?php
session_start();
if (empty($_SESSION['contact_csrf']) || !is_string($_SESSION['contact_csrf'])) {
  $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}
$contactCsrf = $_SESSION['contact_csrf'];
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover"
    />
    <meta name="format-detection" content="telephone=no" />
    <title>Crypterra · Contact – next‑gen crypto trading</title>
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

      /* ---------- CONTACT PAGE SPECIFIC STYLES ---------- */
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

      /* contact hero */
      .contact-hero {
        min-height: 40vh;
        display: flex;
        align-items: center;
        position: relative;
        isolation: isolate;
        background: radial-gradient(circle at 30% 30%, rgba(34, 197, 94, 0.08), transparent 60%);
        margin-top: 0;
      }
      .contact-hero .container {
        position: relative;
        z-index: 5;
        text-align: center;
      }
      .contact-hero h1 {
        font-size: clamp(2rem, 6vw, 4rem);
        font-weight: 800;
      }
      .contact-hero p {
        font-size: clamp(0.9rem, 3.5vw, 1.2rem);
        max-width: 700px;
        margin: 1.5rem auto;
        background: rgba(0, 20, 10, 0.3);
        backdrop-filter: blur(6px);
        padding: 0.8rem 1.5rem;
        border-radius: 60px 20px 20px 60px;
        border-left: 6px solid var(--primary);
      }

      /* info cards grid */
      .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
      }
      @media (max-width: 600px) {
        .info-grid {
          grid-template-columns: 1fr;
          gap: 1rem;
        }
      }
      .info-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 1.5rem;
        text-align: center;
        transition: var(--transition-smooth);
      }
      .info-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
        box-shadow: 0 20px 35px -15px rgba(34, 197, 94, 0.2);
      }
      .info-card i {
        font-size: 2.2rem;
        color: var(--primary);
        margin-bottom: 0.8rem;
      }

      /* form + map grid */
      .form-map-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        align-items: start;
      }
      @media (max-width: 900px) {
        .form-map-grid {
          grid-template-columns: 1fr;
          gap: 1.5rem;
        }
      }
      .glass-form {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 1.8rem;
      }
      .form-group {
        margin-bottom: 1.2rem;
      }
      .form-group input,
      .form-group textarea,
      .form-group select {
        width: 100%;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.8rem 1.2rem;
        color: white;
        font-size: 0.95rem;
        outline: none;
        transition: 0.2s;
        font-family: inherit;
      }
      .form-group textarea {
        border-radius: 24px;
        resize: vertical;
      }
      .form-group input:focus,
      .form-group textarea:focus,
      .form-group select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 15px var(--primary-glow);
      }
      .error-message {
        color: #ef5350;
        font-size: 0.75rem;
        margin-top: 0.3rem;
        padding-left: 1rem;
      }
      .success-message {
        background: rgba(34, 197, 94, 0.2);
        border: 1px solid var(--primary);
        border-radius: 40px;
        padding: 0.8rem;
        text-align: center;
        margin-top: 1rem;
        color: white;
        font-size: 0.85rem;
      }
      .map-container {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 1rem;
        height: 100%;
        min-height: 380px;
        position: relative;
        overflow: hidden;
      }
      .map-iframe {
        width: 100%;
        height: 100%;
        border-radius: 20px;
        filter: grayscale(0.3) brightness(0.8) hue-rotate(80deg);
        min-height: 300px;
      }
      .map-overlay-card {
        position: absolute;
        bottom: 1rem;
        left: 1rem;
        right: 1rem;
        background: rgba(7, 20, 34, 0.9);
        backdrop-filter: blur(12px);
        border: 1px solid var(--primary);
        border-radius: 20px;
        padding: 0.8rem;
        text-align: center;
      }
      .map-overlay-card h4 {
        font-size: 0.9rem;
      }
      .map-overlay-card p {
        font-size: 0.75rem;
      }

      /* FAQ accordion */
      .faq-list {
        margin-top: 1rem;
      }
      .faq-item {
        background: rgba(255, 255, 255, 0.02);
        border-radius: 20px;
        margin-bottom: 0.8rem;
        border: 1px solid transparent;
        transition: 0.2s;
      }
      .faq-item.open {
        border-color: var(--primary);
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
      .faq-question i {
        transition: transform 0.3s;
      }
      .faq-item.open .faq-question i {
        transform: rotate(180deg);
      }
      .faq-answer {
        padding: 0 1.2rem 1rem;
        display: none;
        line-height: 1.5;
        font-size: 0.9rem;
      }
      .faq-item.open .faq-answer {
        display: block;
      }

      /* community grid */
      .community-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
      }
      @media (max-width: 500px) {
        .community-grid {
          grid-template-columns: repeat(2, 1fr);
        }
      }
      .community-card {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 28px;
        padding: 1.5rem;
        text-align: center;
        transition: 0.3s;
        font-size: 2.5rem;
        color: var(--text-light);
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .community-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
        box-shadow: 0 0 30px var(--primary-glow);
        color: var(--primary);
      }

      /* CTA glass section */
      .cta-glass {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-edge);
        border-radius: 48px;
        padding: 2rem 1.5rem;
        text-align: center;
      }
      .cta-glass h2 {
        font-size: clamp(1.5rem, 6vw, 2.5rem);
        margin-bottom: 1rem;
      }

      /* FOOTER - FULLY RESPONSIVE */
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
        font-size: 1.2rem;
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
        <li><a href="./contact.php" class="active">Contact</a></li>
        <li><a href="./faq.php">FAQs</a></li>
        <li><a href="./market.php">Markets</a></li>
        <!--<li>-->
        <!--  -->
        <!--</li>-->
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

    <!-- HERO SECTION -->
    <section class="contact-hero">
      <div class="particle-overlay" id="heroParticles"></div>
      <div class="container" data-aos="fade-up">
        <h1>We're Here to Help</h1>
        <p>
          Have questions about Crypterra? Our support team is available 24/7 to
          assist you with trading, wallets, and security.
        </p>
        <button class="btn-primary" onclick="document.getElementById('contactForm').scrollIntoView({behavior:'smooth'})">Contact Support</button>
      </div>
    </section>

    <!-- CONTACT INFORMATION CARDS -->
    <section class="section">
      <div class="container">
        <h2 class="section-title" data-aos="fade-up">Get in touch</h2>
        <div class="info-grid">
          <div class="info-card" data-aos="zoom-in">
            <i class="fa-regular fa-envelope"></i>
            <h3>Support Email</h3>
            <p>support@crypterra.com</p>
          </div>
          <div class="info-card" data-aos="zoom-in" data-aos-delay="50">
            <i class="fa-regular fa-comment-dots"></i>
            <h3>Live Chat</h3>
            <p>Available 24/7</p>
          </div>
          <div class="info-card" data-aos="zoom-in" data-aos-delay="100">
            <i class="fa-solid fa-phone"></i>
            <h3>Phone Support</h3>
            <p>+86 130-CRYPTERRA</p>
          </div>
          <div class="info-card" data-aos="zoom-in" data-aos-delay="150">
            <i class="fa-regular fa-building"></i>
            <h3>Office Location</h3>
            <p>Taiwan (Global HQ)</p>
          </div>
        </div>
      </div>
    </section>

    <!-- CONTACT FORM + MAP SECTION -->
    <section class="section" id="contactForm">
      <div class="container form-map-grid">
        <!-- left side: form -->
        <div class="glass-form" data-aos="fade-right">
          <h3 style="margin-bottom: 1.5rem; font-size: 1.6rem">Send us a message</h3>
          <input type="hidden" id="csrfToken" value="<?php echo htmlspecialchars($contactCsrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" />
          <div class="form-group" style="position:absolute;left:-9999px;opacity:0" aria-hidden="true">
            <label for="website">Website</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off" />
          </div>
          <div class="form-group">
            <input type="text" id="name" name="name" placeholder="Your name *" autocomplete="name" />
          </div>
          <div class="form-group">
            <input type="email" id="email" name="email" placeholder="Email address *" autocomplete="email" />
            <div class="error-message" id="emailError"></div>
          </div>
          <div class="form-group">
            <select id="category" name="category">
              <option value="general">General Inquiry</option>
              <option value="support">Technical Support</option>
              <option value="security">Security Issue</option>
              <option value="billing">Billing / Withdrawal</option>
            </select>
          </div>
          <div class="form-group">
            <input type="text" id="subject" name="subject" placeholder="Subject" autocomplete="off" />
          </div>
          <div class="form-group">
            <textarea id="message" name="message" rows="5" placeholder="Your message *"></textarea>
          </div>
          <div class="error-message" id="formError" style="display:none;margin-bottom:0.75rem"></div>
          <button type="button" class="btn-primary" id="submitForm" style="width: 100%">
            Send Message
          </button>
          <div id="formSuccess" class="success-message" style="display: none">
            Message sent successfully!
          </div>
        </div>

        <!-- right side: map + overlay -->
        <div class="map-container" data-aos="fade-left">
          <iframe class="map-iframe" src="https://www.google.com/maps?q=Taipei%20101%2C%20Taiwan&output=embed" allowfullscreen="" loading="lazy"></iframe>
          <div class="map-overlay-card">
            <i class="fa-solid fa-location-dot" style="color: var(--primary); font-size: 1.2rem"></i>
            <h4>Crypterra Global Headquarters</h4>
            <p>Taipei, Taiwan</p>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ ACCORDION SECTION -->
    <section class="section">
      <div class="container">
        <h2 class="section-title" data-aos="fade-up">Frequently asked questions</h2>
        <div class="faq-list" data-aos="fade-up">
          <div class="faq-item">
            <div class="faq-question">
              <span>Is Crypterra secure?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Yes, we use bank‑grade encryption, cold storage for 95% of funds, regular audits, and AI fraud detection.
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question">
              <span>What are the trading fees?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              0.1% per trade with zero hidden fees. Volume discounts available for high-frequency traders.
            </div>
          </div>
          <div class="faq-item">
            <div class="faq-question">
              <span>How do I withdraw funds?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Go to your wallet dashboard, select "Withdraw", choose your asset and amount, then confirm the on‑chain transaction.
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- SOCIAL COMMUNITY SECTION -->
    <section class="section">
      <div class="container">
        <h2 class="section-title" data-aos="fade-up">Join the Crypterra Community</h2>
        <div class="community-grid">
          <a href="#" class="community-card" data-aos="flip-left"><i class="fa-brands fa-twitter"></i></a>
          <a href="#" class="community-card" data-aos="flip-left" data-aos-delay="50"><i class="fa-brands fa-discord"></i></a>
          <a href="#" class="community-card" data-aos="flip-left" data-aos-delay="100"><i class="fa-brands fa-telegram"></i></a>
          <a href="#" class="community-card" data-aos="flip-left" data-aos-delay="150"><i class="fa-brands fa-github"></i></a>
        </div>
      </div>
    </section>

    <!-- FINAL CTA SECTION -->
    <section class="section">
      <div class="container cta-glass" data-aos="zoom-in">
        <h2>Ready to start trading?</h2>
        <p style="margin: 1rem 0; font-size: clamp(1rem, 4vw, 1.2rem)">
          Create your account today and experience the future of crypto trading.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
          <button class="btn-primary" onclick="window.location.href='./login.php'">Start Trading</button>
                  </div>
      </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
      <div class="footer-grid">
        <div class="footer-col">
          <h4>Product</h4>
          <a href="#">Exchange</a><a href="#">Derivatives</a><a href="#">NFT</a>
        </div>
        <div class="footer-col">
          <h4>Markets</h4>
          <a href="#">Spot</a><a href="#">Futures</a><a href="#">Options</a>
        </div>
        <div class="footer-col">
          <h4>API</h4>
          <a href="#">Documentation</a><a href="#">WebSocket</a>
        </div>
        <div class="footer-col">
          <h4>Security</h4>
          <a href="#">Audits</a><a href="#">Bug bounty</a>
        </div>
        <div class="footer-col">
          <h4>Company</h4>
          <a href="#">Careers</a><a href="#">Blog</a><a href="#">Support</a>
        </div>
      </div>
      <div class="social-icons">
        <i class="fa-brands fa-twitter"></i><i class="fa-brands fa-discord"></i>
        <i class="fa-brands fa-telegram"></i><i class="fa-brands fa-github"></i>
      </div>
      <div style="text-align: center; margin-top: 2rem; font-size: 0.75rem; opacity: 0.6;">
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
        AOS.init({ duration: 700, once: false });

        // Particles for hero section
        const heroParticles = document.getElementById("heroParticles");
        if (heroParticles) {
          for (let i = 0; i < 35; i++) {
            let p = document.createElement("div");
            p.className = "particle";
            p.style.left = Math.random() * 100 + "%";
            p.style.animationDelay = Math.random() * 12 + "s";
            p.style.animationDuration = 12 + Math.random() * 12 + "s";
            heroParticles.appendChild(p);
          }
        }

        // FAQ accordion
        document.querySelectorAll(".faq-question").forEach((q) => {
          q.addEventListener("click", function() {
            this.closest(".faq-item").classList.toggle("open");
          });
        });

        // Form submission
        const submitBtn = document.getElementById("submitForm");
        const nameInput = document.getElementById("name");
        const emailInput = document.getElementById("email");
        const messageInput = document.getElementById("message");
        const subjectInput = document.getElementById("subject");
        const categoryInput = document.getElementById("category");
        const csrfInput = document.getElementById("csrfToken");
        const emailError = document.getElementById("emailError");
        const formError = document.getElementById("formError");
        const successDiv = document.getElementById("formSuccess");

        function validateEmail(email) {
          return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        if (submitBtn) {
          submitBtn.addEventListener("click", (e) => {
            e.preventDefault();
            let isValid = true;
            emailError.textContent = "";
            formError.style.display = "none";
            formError.textContent = "";

            if (!nameInput.value.trim()) {
              nameInput.style.borderColor = "#ef5350";
              isValid = false;
            } else {
              nameInput.style.borderColor = "";
            }

            if (!emailInput.value.trim()) {
              emailError.textContent = "Please enter your email address.";
              emailInput.style.borderColor = "#ef5350";
              isValid = false;
            } else if (!validateEmail(emailInput.value.trim())) {
              emailError.textContent = "Enter a valid email (e.g., name@domain.com).";
              emailInput.style.borderColor = "#ef5350";
              isValid = false;
            } else {
              emailInput.style.borderColor = "";
            }

            if (!messageInput.value.trim()) {
              messageInput.style.borderColor = "#ef5350";
              isValid = false;
            } else {
              messageInput.style.borderColor = "";
            }

            if (!isValid) {
              successDiv.style.display = "none";
              return;
            }

            const fd = new FormData();
            fd.append("csrf_token", csrfInput.value);
            fd.append("name", nameInput.value.trim());
            fd.append("email", emailInput.value.trim());
            fd.append("subject", subjectInput ? subjectInput.value.trim() : "");
            fd.append("message", messageInput.value.trim());
            if (categoryInput) fd.append("category", categoryInput.value);
            const hp = document.getElementById("website");
            if (hp) fd.append("website", hp.value);

            submitBtn.disabled = true;
            submitBtn.textContent = "Sending...";
            fetch("contact_submit.php", {
              method: "POST",
              body: fd,
              credentials: "same-origin",
            })
              .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
              .then(({ ok, data }) => {
                submitBtn.disabled = false;
                submitBtn.textContent = "Send Message";
                if (data && data.ok) {
                  successDiv.style.display = "block";
                  if (data.ticket) {
                    successDiv.textContent = "Message sent successfully! Reference: " + data.ticket;
                  }
                  nameInput.value = "";
                  emailInput.value = "";
                  if (subjectInput) subjectInput.value = "";
                  messageInput.value = "";
                  setTimeout(() => {
                    successDiv.style.display = "none";
                    successDiv.textContent = "Message sent successfully!";
                  }, 5000);
                } else {
                  let msg = "Could not send your message. Please try again.";
                  if (data && data.error === "rate_limit") {
                    msg = "Too many messages. Please wait and try again.";
                  } else if (data && data.error === "csrf") {
                    msg = "Session expired. Refresh the page and try again.";
                  }
                  formError.textContent = msg;
                  formError.style.display = "block";
                }
              })
              .catch(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = "Send Message";
                formError.textContent = "Network error. Check your connection and try again.";
                formError.style.display = "block";
              });
          });
        }
      })();
    </script>
  <?php include __DIR__ . '/includes/smartsupp.php'; ?>
  </body>
</html>