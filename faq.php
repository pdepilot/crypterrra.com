<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · FAQ – next‑gen crypto trading</title>
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
      /* ---------- RESET & VARIABLES (identical to other pages) ---------- */
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
      }
      h1,
      h2,
      h3 {
        font-weight: 700;
        letter-spacing: -0.02em;
      }
      /* ---------- NAVBAR (identical) ---------- */
      .navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(11, 28, 47, 0.8);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
        padding: 0.8rem 5%;
        box-shadow: 0 12px 30px -10px rgba(0, 0, 0, 0.5);
        position: sticky;
        top: 0;
        z-index: 100;
        border-bottom: 1px solid rgba(34, 197, 94, 0.3);
      }
      .logo {
        display: flex;
        align-items: center;
        font-size: 2rem;
        font-weight: 700;
        letter-spacing: -0.02em;
      }
      .logo i {
        font-size: 2.4rem;
        background: linear-gradient(145deg, var(--primary), #0f994a);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-right: 0.4rem;
        filter: drop-shadow(0 2px 8px var(--primary-glow));
      }
      .logo span {
        background: linear-gradient(135deg, #ffffff 0%, var(--primary) 90%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }
      .nav-links {
        display: flex;
        list-style: none;
        gap: 2.5rem;
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
      }
      .nav-links li a::after {
        content: "";
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 2.5px;
        background: linear-gradient(90deg, var(--primary), #8aecb0);
        transition: width 0.3s;
        border-radius: 4px;
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
        padding: 0.55rem 1.8rem;
        border-radius: 40px;
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: var(--transition-smooth);
        margin-left: 0.5rem;
        backdrop-filter: blur(4px);
      }
      .wallet-btn:hover {
        background: var(--primary);
        color: var(--dark-bg);
        transform: scale(1.05) translateY(-2px);
        box-shadow: 0 12px 24px -8px var(--primary);
      }
      .signup {
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
        border: none;
        color: var(--dark-bg);
        padding: 0.6rem 2rem;
        border-radius: 40px;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        box-shadow: 0 8px 20px -4px var(--primary);
        transition: var(--transition-smooth);
        margin-left: 0.75rem;
        border: 1px solid rgba(255, 255, 255, 0.2);
      }
      .signup:hover {
        transform: scale(1.05) translateY(-2px);
        background: linear-gradient(145deg, #2ed573, #1e9e53);
        box-shadow: 0 18px 30px -6px var(--primary);
      }
      /* hamburger */
      .hamburger-wrapper {
        display: none;
        position: relative;
      }
      .hamburger-icon {
        width: 48px;
        height: 48px;
        background: rgba(34, 197, 94, 0.15);
        border: 1px solid rgba(34, 197, 94, 0.4);
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: var(--transition-smooth);
        backdrop-filter: blur(8px);
      }
      .hamburger-icon:hover {
        background: var(--primary);
        border-color: white;
        transform: scale(1.05);
      }
      .hamburger-icon:hover .bar {
        background: #0b1c2f;
      }
      .bar {
        width: 22px;
        height: 2.5px;
        background: var(--primary);
        border-radius: 6px;
        transition: 0.25s;
      }
      .hamburger-dropdown {
        position: absolute;
        top: 65px;
        right: 0;
        width: 300px;
        background: rgba(7, 20, 34, 0.95);
        backdrop-filter: blur(20px);
        border: 1px solid var(--primary);
        border-radius: 24px;
        padding: 2rem 1.5rem;
        box-shadow: 0 30px 50px -20px black;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        transform: translateY(-20px);
        opacity: 0;
        visibility: hidden;
        transition: 0.35s cubic-bezier(0.2, 0.9, 0.3, 1.2);
        pointer-events: none;
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
        gap: 1.2rem;
      }
      .dropdown-links li a {
        color: var(--text-light);
        text-decoration: none;
        font-size: 1.4rem;
        font-weight: 600;
        padding: 0.4rem 0.8rem;
        display: block;
        border-radius: 16px;
        transition: 0.2s;
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid transparent;
      }
      .dropdown-links li a:hover {
        background: rgba(34, 197, 94, 0.2);
        border-color: var(--primary);
        transform: translateX(6px);
        color: white;
      }
      .dropdown-wallet {
        background: transparent;
        border: 2px solid var(--primary);
        color: var(--primary);
        padding: 1rem;
        border-radius: 40px;
        font-weight: 700;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        cursor: pointer;
        transition: var(--transition-smooth);
        width: 100%;
      }
      .dropdown-wallet:hover {
        background: var(--primary);
        color: var(--dark-bg);
        box-shadow: 0 12px 24px -6px var(--primary);
      }
      .dropdown-signup {
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
        border: none;
        color: var(--dark-bg);
        padding: 1rem;
        border-radius: 40px;
        font-weight: 700;
        font-size: 1.2rem;
        cursor: pointer;
        box-shadow: 0 8px 20px -4px var(--primary);
        transition: var(--transition-smooth);
        border: 1px solid rgba(255, 255, 255, 0.2);
      }
      @media (max-width: 900px) {
        .nav-links,
        .wallet-btn,
        .signup {
          display: none !important;
        }
        .hamburger-wrapper {
          display: block;
        }

        #scrollTop {
          right: 1.5rem;
          bottom: 5.2rem;
        }
      }

      /* ---------- faq page specific (reuses same glass/glow) ---------- */
      .section {
        padding: 6rem 5%;
        position: relative;
      }
      .container {
        max-width: 1400px;
        margin: 0 auto;
      }
      .section-title {
        font-size: 3rem;
        margin-bottom: 3rem;
        background: linear-gradient(to right, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        display: inline-block;
      }
      .btn-primary {
        background: var(--primary);
        color: var(--dark-bg);
        padding: 1rem 3rem;
        border-radius: 60px;
        font-weight: 700;
        font-size: 1.2rem;
        border: none;
        box-shadow: 0 0 30px var(--primary);
        transition: 0.3s;
        cursor: pointer;
      }
      .btn-primary:hover {
        transform: scale(1.05);
        background: #2ed573;
        box-shadow: 0 0 50px var(--primary);
      }
      .btn-outline {
        background: transparent;
        border: 2px solid var(--primary);
        color: white;
        padding: 1rem 3rem;
        border-radius: 60px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.3s;
      }
      .btn-outline:hover {
        background: var(--primary);
        color: #000;
      }

      /* particle / grid background */
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
      .grid-bg {
        background-image:
          linear-gradient(rgba(34, 197, 94, 0.05) 1px, transparent 1px),
          linear-gradient(90deg, rgba(34, 197, 94, 0.05) 1px, transparent 1px);
        background-size: 40px 40px;
      }

      /* faq hero */
      .faq-hero {
        min-height: 40vh;
        display: flex;
        align-items: center;
        position: relative;
        isolation: isolate;
        background: radial-gradient(
          circle at 30% 30%,
          rgba(34, 197, 94, 0.08),
          transparent 60%
        );
      }
      .faq-hero .container {
        position: relative;
        z-index: 5;
        text-align: center;
      }
      .faq-hero h1 {
        font-size: clamp(2.5rem, 6vw, 4.5rem);
        font-weight: 800;
      }
      .faq-hero p {
        font-size: 1.2rem;
        max-width: 700px;
        margin: 2rem auto;
        background: rgba(0, 20, 10, 0.3);
        backdrop-filter: blur(6px);
        padding: 1rem 2rem;
        border-radius: 60px 20px 20px 60px;
        border-left: 6px solid var(--primary);
      }
      .search-bar {
        max-width: 600px;
        margin: 0 auto;
        position: relative;
      }
      .search-bar input {
        width: 100%;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 1.2rem 2rem;
        color: white;
        font-size: 1.1rem;
        outline: none;
        backdrop-filter: blur(8px);
      }
      .search-bar input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 30px var(--primary-glow);
      }
      .search-bar i {
        position: absolute;
        right: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary);
        font-size: 1.4rem;
      }

      /* category pills */
      .category-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: center;
        margin: 3rem 0 2rem;
      }
      .pill {
        background: rgba(34, 197, 94, 0.1);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-smooth);
        backdrop-filter: blur(8px);
      }
      .pill:hover {
        border-color: var(--primary);
        background: rgba(34, 197, 94, 0.2);
      }
      .pill.active {
        background: var(--primary);
        color: #0b1c2f;
        border-color: white;
        box-shadow: 0 0 20px var(--primary);
      }

      /* faq accordion list */
      .faq-list {
        max-width: 900px;
        margin: 0 auto;
      }
      .faq-item {
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-edge);
        border-radius: 30px;
        margin-bottom: 1.2rem;
        overflow: hidden;
        transition: 0.2s;
      }
      .faq-item:hover {
        border-color: var(--primary);
        box-shadow: 0 0 20px var(--primary-glow);
      }
      .faq-question {
        padding: 1.8rem 2rem;
        font-weight: 600;
        font-size: 1.2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
      }
      .faq-question i {
        transition: transform 0.4s ease;
        color: var(--primary);
        font-size: 1.4rem;
      }
      .faq-item.open .faq-question i {
        transform: rotate(180deg);
      }
      .faq-answer {
        max-height: 0;
        padding: 0 2rem;
        opacity: 0;
        transition:
          max-height 0.5s cubic-bezier(0.2, 0.9, 0.3, 1),
          opacity 0.4s ease,
          padding 0.3s;
        overflow: hidden;
        color: #b0d0ff;
      }
      .faq-item.open .faq-answer {
        max-height: 300px; /* enough for answers */
        opacity: 1;
        padding: 0 2rem 1.8rem 2rem;
      }

      /* support cta */
      .support-cta {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-edge);
        border-radius: 80px;
        padding: 4rem 3rem;
        text-align: center;
        margin-top: 3rem;
      }
      .cta-buttons {
        display: flex;
        gap: 1.5rem;
        justify-content: center;
        flex-wrap: wrap;
        margin-top: 2rem;
      }

      /* footer */
      .footer {
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(20px);
        border-top: 1px solid var(--primary);
        padding: 3rem 5% 2rem;
      }
      .footer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 2rem;
      }
      .footer-col a {
        display: block;
        color: #aac8ff;
        text-decoration: none;
        margin: 0.7rem 0;
        transition: 0.2s;
      }
      .footer-col a:hover {
        color: var(--primary);
        transform: translateX(5px);
      }
      .social-icons {
        display: flex;
        gap: 1.5rem;
        margin-top: 2rem;
      }
      .social-icons i {
        font-size: 1.8rem;
        color: var(--text-light);
        transition: 0.2s;
      }
      .social-icons i:hover {
        color: var(--primary);
        transform: scale(1.2);
        filter: drop-shadow(0 0 10px var(--primary));
      }
      #scrollTop {
        position: fixed;
        right: 1.5rem;
        bottom: 5.2rem;
        background: rgba(34, 197, 94, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.35);
        color: white;
        width: 54px;
        height: 54px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.6rem;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 99;
        border: none;
      }
      #scrollTop:hover {
        background: var(--primary);
        transform: scale(1.1);
      }

      @media (max-width: 768px) {
        .faq-hero p {
          border-radius: 20px;
          border-left-width: 6px;
        }
        .faq-question {
          font-size: 1rem;
          padding: 1.5rem;
        }

        #scrollTop {
          right: 1.5rem;
          bottom: 5.2rem;
      }
    }
    </style>
  </head>
  <body>
    <!-- NAVBAR (identical) -->
    <nav class="navbar">
      <div class="logo">
        <i class="fa-solid fa-globe"></i><span>Crypterra</span>
      </div>
      <ul class="nav-links">
        <li><a href="./index.php">Home</a></li>
        <li><a href="./about.php">About</a></li>
        <li><a href="./contact.php">Contact</a></li>
        <li><a href="./faq.php" class="active">FAQs</a></li>
        <li><a href="./market.php">Markets</a></li>
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
            <li><a href="./about.php">ℹ️ About</a></li>
            <li><a href="./contact.php">📧 Contact</a></li>
            <li><a href="./faq.php">❓ FAQs</a></li>
            <li><a href="./market.php">📈 Markets</a></li>
          </ul>
          <button type="button" class="dropdown-signup" onclick="window.location.href='./login.php'">Sign Up</button>
        </div>
      </div>
    </nav>

    <!-- 1️⃣ FAQ HERO with search -->
    <section class="faq-hero">
      <div class="particle-overlay" id="heroParticles"></div>
      <div
        class="container grid-bg"
        style="padding: 2rem; border-radius: 80px"
        data-aos="fade-up"
      >
        <h1>Frequently Asked Questions</h1>
        <p>
          Find answers to common questions about Crypterra, trading, wallets,
          security, and platform features.
        </p>
        <div class="search-bar">
          <input
            type="text"
            id="faqSearch"
            placeholder="Search for a question..."
          />
          <i class="fa-solid fa-magnifying-glass"></i>
        </div>
      </div>
    </section>

    <!-- 2️⃣ CATEGORY PILLS -->
    <section class="section" style="padding-top: 0">
      <div class="container">
        <div class="category-pills" id="categoryPills">
          <span class="pill active" data-cat="all">All</span>
          <span class="pill" data-cat="general">General</span>
          <span class="pill" data-cat="account">Account</span>
          <span class="pill" data-cat="trading">Trading</span>
          <span class="pill" data-cat="deposits">Deposits & Withdrawals</span>
          <span class="pill" data-cat="security">Security</span>
          <span class="pill" data-cat="support">Technical Support</span>
        </div>
      </div>
    </section>

    <!-- 3️⃣ FAQ ACCORDION SECTION -->
    <section class="section">
      <div class="container">
        <div class="faq-list" id="faqContainer">
          <!-- General -->
          <div class="faq-item" data-category="general">
            <div class="faq-question">
              <span>What is Crypterra?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Crypterra is a next‑generation crypto trading platform offering
              institutional‑grade security, deep liquidity, and an intuitive
              interface for traders worldwide.
            </div>
          </div>
          <div class="faq-item" data-category="general">
            <div class="faq-question">
              <span>Is Crypterra available worldwide?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Yes, we serve traders in over 120 countries, with the exception of
              restricted jurisdictions. Check our terms for details.
            </div>
          </div>
          <!-- Account -->
          <div class="faq-item" data-category="account">
            <div class="faq-question">
              <span>How do I create an account on Crypterra?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Click the Sign Up button, enter your email, create a strong
              password, and verify your account via the confirmation email.
            </div>
          </div>
          <div class="faq-item" data-category="account">
            <div class="faq-question">
              <span>How do I verify my identity?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Go to Account Settings → Verification, upload a government‑issued
              ID and a selfie. Verification usually takes 5‑10 minutes.
            </div>
          </div>
          <!-- Trading -->
          <div class="faq-item" data-category="trading">
            <div class="faq-question">
              <span>What cryptocurrencies can I trade?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              We support 200+ assets including BTC, ETH, SOL, ADA, and major
              altcoins. Spot and futures markets are available.
            </div>
          </div>
          <div class="faq-item" data-category="trading">
            <div class="faq-question">
              <span>Are there trading fees?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Yes, we charge a flat 0.1% per trade. High‑volume traders enjoy
              discounted fees. No hidden fees.
            </div>
          </div>
          <!-- Deposits & Withdrawals -->
          <div class="faq-item" data-category="deposits">
            <div class="faq-question">
              <span>How do I deposit funds?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Navigate to your wallet, select Deposit, choose your preferred
              asset, and transfer from an external wallet or exchange.
            </div>
          </div>
          <div class="faq-item" data-category="deposits">
            <div class="faq-question">
              <span>How long do withdrawals take?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Most withdrawals are processed within 5‑30 minutes, depending on
              network congestion and security checks.
            </div>
          </div>
          <!-- Security -->
          <div class="faq-item" data-category="security">
            <div class="faq-question">
              <span>Is Crypterra secure?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Absolutely. We use bank‑grade encryption, multi‑sig cold storage,
              and real‑time AI fraud detection.
            </div>
          </div>
          <div class="faq-item" data-category="security">
            <div class="faq-question">
              <span>Does Crypterra use cold storage?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Yes, 95% of customer funds are kept in offline, geographically
              distributed cold wallets.
            </div>
          </div>
          <!-- Support -->
          <div class="faq-item" data-category="support">
            <div class="faq-question">
              <span>How do I contact support?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              You can reach us via live chat, email at support@crypterra.com, or
              through our 24/7 phone line.
            </div>
          </div>
          <div class="faq-item" data-category="support">
            <div class="faq-question">
              <span>Is customer service available 24/7?</span>
              <i class="fa-solid fa-chevron-down"></i>
            </div>
            <div class="faq-answer">
              Yes, our multilingual support team is here around the clock to
              assist you.
            </div>
          </div>
        </div>

        <!-- 5️⃣ STILL NEED HELP? CTA -->
        <div class="support-cta" data-aos="zoom-in">
          <h2 style="font-size: 2.5rem">Still need help?</h2>
          <p style="margin: 1.5rem 0; font-size: 1.2rem">
            Our support team is ready to assist you 24/7.
          </p>
          <div class="cta-buttons">
            <button class="btn-primary">Contact Support</button>
            <button class="btn-outline">Start Live Chat</button>
          </div>
        </div>
      </div>
    </section>

    <!-- 6️⃣ FOOTER (identical) -->
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
          <h4>Security</h4>
          <a href="#">Audits</a><a href="#">Bug bounty</a>
        </div>
        <div class="footer-col">
          <h4>API</h4>
          <a href="#">Documentation</a><a href="#">WebSocket</a>
        </div>
        <div class="footer-col">
          <h4>Company</h4>
          <a href="#">Careers</a><a href="#">Blog</a><a href="#">Support</a>
        </div>
      </div>
      <div class="social-icons">
        <i class="fa-brands fa-twitter"></i><i class="fa-brands fa-discord"></i
        ><i class="fa-brands fa-telegram"></i
        ><i class="fa-brands fa-github"></i>
      </div>
    </footer>

    <button id="scrollTop"><i class="fa-solid fa-arrow-up"></i></button>

    

    <!-- scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      (function () {
        // HAMBURGER
        const hamburgerIcon = document.getElementById("hamburgerIcon");
        const dropdown = document.getElementById("hamburgerDropdown");
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

        // SCROLL TOP
        const scrollBtn = document.getElementById("scrollTop");
        window.addEventListener("scroll", () => {
          scrollBtn.style.display = window.scrollY > 250 ? "flex" : "none";
        });
        scrollBtn.addEventListener("click", () => {
          window.scrollTo({ top: 0, behavior: "smooth" });
        });

        // AOS init
        AOS.init({ duration: 800, once: false, mirror: true });

        // PARTICLES for hero
        const heroParticles = document.getElementById("heroParticles");
        if (heroParticles) {
          for (let i = 0; i < 30; i++) {
            let p = document.createElement("div");
            p.className = "particle";
            p.style.left = Math.random() * 100 + "%";
            p.style.animationDelay = Math.random() * 10 + "s";
            p.style.animationDuration = 15 + Math.random() * 10 + "s";
            heroParticles.appendChild(p);
          }
        }

        // FAQ ACCORDION (one at a time)
        const faqItems = document.querySelectorAll(".faq-item");
        faqItems.forEach((item) => {
          const question = item.querySelector(".faq-question");
          question.addEventListener("click", () => {
            // close others
            faqItems.forEach((i) => {
              if (i !== item && i.classList.contains("open")) {
                i.classList.remove("open");
              }
            });
            // toggle current
            item.classList.toggle("open");
          });
        });

        // CATEGORY FILTER + SEARCH
        const pills = document.querySelectorAll(".pill");
        const searchInput = document.getElementById("faqSearch");
        let activeCategory = "all";
        let searchTerm = "";

        function filterFAQs() {
          const items = document.querySelectorAll(".faq-item");
          items.forEach((item) => {
            const cat = item.dataset.category;
            const questionText = item
              .querySelector(".faq-question span")
              .innerText.toLowerCase();
            const matchesCategory =
              activeCategory === "all" || cat === activeCategory;
            const matchesSearch =
              searchTerm === "" ||
              questionText.includes(searchTerm.toLowerCase());

            if (matchesCategory && matchesSearch) {
              item.style.display = "block";
            } else {
              item.style.display = "none";
            }
          });
        }

        pills.forEach((pill) => {
          pill.addEventListener("click", () => {
            pills.forEach((p) => p.classList.remove("active"));
            pill.classList.add("active");
            activeCategory = pill.dataset.cat;
            filterFAQs();
          });
        });

        searchInput.addEventListener("input", (e) => {
          searchTerm = e.target.value;
          filterFAQs();
        });
      })();
    </script>
  <?php include __DIR__ . '/includes/smartsupp.php'; ?>
  </body>
</html>
