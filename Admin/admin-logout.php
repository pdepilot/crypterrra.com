<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=1.0"
    />
    <title>Crypterra · Secure Logout</title>
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
    <style>
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
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
      }

      /* Animated background grid */
      body::before {
        content: "";
        position: absolute;
        width: 100%;
        height: 100%;
        background:
          radial-gradient(
            circle at 20% 50%,
            rgba(34, 197, 94, 0.05) 0%,
            transparent 50%
          ),
          radial-gradient(
            circle at 80% 80%,
            rgba(34, 197, 94, 0.05) 0%,
            transparent 50%
          );
        pointer-events: none;
      }

      body::after {
        content: "";
        position: absolute;
        width: 100%;
        height: 100%;
        background-image:
          linear-gradient(rgba(34, 197, 94, 0.03) 1px, transparent 1px),
          linear-gradient(90deg, rgba(34, 197, 94, 0.03) 1px, transparent 1px);
        background-size: 50px 50px;
        pointer-events: none;
      }

      .logout-container {
        width: 100%;
        max-width: 480px;
        padding: 1.5rem;
        position: relative;
        z-index: 10;
      }

      .logout-card {
        background: var(--card-bg);
        backdrop-filter: blur(20px) saturate(200%);
        -webkit-backdrop-filter: blur(20px) saturate(200%);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 3rem 2.5rem;
        box-shadow: var(--shadow-glow);
        text-align: center;
        position: relative;
        overflow: hidden;
        animation: cardFloat 0.6s ease-out;
      }

      .logout-card::before {
        content: "";
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(
          circle,
          var(--primary-soft) 0%,
          transparent 70%
        );
        opacity: 0.3;
        animation: rotate 20s linear infinite;
      }

      @keyframes rotate {
        from {
          transform: rotate(0deg);
        }
        to {
          transform: rotate(360deg);
        }
      }

      @keyframes cardFloat {
        0% {
          opacity: 0;
          transform: translateY(30px);
        }
        100% {
          opacity: 1;
          transform: translateY(0);
        }
      }

      /* Logo */
      .logout-logo {
        margin-bottom: 2rem;
        position: relative;
      }

      .logo-icon {
        width: 70px;
        height: 70px;
        background: linear-gradient(
          145deg,
          var(--primary),
          var(--primary-dark)
        );
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: white;
        box-shadow: 0 0 40px var(--primary-glow);
        margin: 0 auto 1rem;
        animation: logoPulse 2s infinite;
      }

      @keyframes logoPulse {
        0%,
        100% {
          box-shadow: 0 0 40px var(--primary-glow);
          transform: scale(1);
        }
        50% {
          box-shadow: 0 0 60px var(--primary-glow);
          transform: scale(1.05);
        }
      }

      .logo-icon i {
        animation: spin 10s linear infinite;
      }

      @keyframes spin {
        from {
          transform: rotate(0deg);
        }
        to {
          transform: rotate(360deg);
        }
      }

      .logout-logo h1 {
        font-size: 2rem;
        background: linear-gradient(135deg, #fff, var(--primary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-bottom: 0.5rem;
      }

      /* Animation states */
      .logout-stage {
        min-height: 200px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin-bottom: 2rem;
      }

      /* Stage 1 - Logging out */
      .logging-out {
        display: flex;
        flex-direction: column;
        align-items: center;
      }

      .spinner {
        width: 60px;
        height: 60px;
        border: 3px solid var(--primary-soft);
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 1.5rem;
      }

      .logging-out h2 {
        font-size: 1.8rem;
        margin-bottom: 1rem;
        background: linear-gradient(135deg, #fff, var(--text-secondary));
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      .logging-out p {
        color: var(--text-muted);
        font-size: 1rem;
      }

      /* Stage 2 - Security */
      .securing-stage {
        display: flex;
        flex-direction: column;
        align-items: center;
      }

      .security-progress {
        width: 200px;
        height: 4px;
        background: var(--surface-light);
        border-radius: 10px;
        margin: 1rem 0;
        overflow: hidden;
      }

      .security-bar {
        height: 100%;
        background: var(--primary);
        width: 0%;
        transition: width 2s ease-in-out;
      }

      .security-items {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
        margin: 1.5rem 0;
      }

      .security-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: var(--text-secondary);
        font-size: 0.95rem;
        opacity: 0.6;
        transition: 0.3s;
      }

      .security-item.completed {
        opacity: 1;
        color: var(--success);
      }

      .security-item i {
        width: 20px;
      }

      /* Stage 3 - Success */
      .success-stage {
        display: flex;
        flex-direction: column;
        align-items: center;
      }

      .checkmark {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--primary-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.5rem;
        animation: checkmarkPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      }

      @keyframes checkmarkPop {
        0% {
          transform: scale(0);
          opacity: 0;
        }
        50% {
          transform: scale(1.2);
        }
        100% {
          transform: scale(1);
          opacity: 1;
        }
      }

      .checkmark i {
        font-size: 2.5rem;
        color: var(--success);
        animation: checkmarkSpin 0.5s ease-out;
      }

      @keyframes checkmarkSpin {
        0% {
          transform: rotate(-180deg) scale(0);
        }
        100% {
          transform: rotate(0) scale(1);
        }
      }

      .success-stage h2 {
        font-size: 1.8rem;
        color: var(--success);
        margin-bottom: 0.5rem;
      }

      .success-stage p {
        color: var(--text-secondary);
        margin-bottom: 1rem;
      }

      .redirect-timer {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--glass-bg);
        padding: 0.5rem 1.2rem;
        border-radius: 40px;
        border: 1px solid var(--glass-edge);
        margin: 1rem 0;
      }

      .timer-progress {
        width: 120px;
        height: 4px;
        background: var(--surface-light);
        border-radius: 10px;
        overflow: hidden;
      }

      .timer-bar {
        height: 100%;
        background: var(--primary);
        width: 100%;
        animation: timer 5s linear forwards;
      }

      @keyframes timer {
        from {
          width: 100%;
        }
        to {
          width: 0%;
        }
      }

      .security-note {
        font-size: 0.85rem;
        color: var(--text-muted);
        background: var(--glass-bg);
        padding: 0.8rem 1.2rem;
        border-radius: 40px;
        border: 1px solid var(--glass-edge);
        margin-top: 1rem;
      }

      .security-note i {
        color: var(--warning);
        margin-right: 0.5rem;
      }

      /* CTA Buttons */
      .logout-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 2rem;
        opacity: 0;
        animation: fadeIn 0.5s 0.3s forwards;
      }

      @keyframes fadeIn {
        to {
          opacity: 1;
        }
      }

      .btn-primary {
        background: var(--primary);
        border: none;
        border-radius: 40px;
        padding: 0.8rem 2rem;
        color: white;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: 0.2s;
        border: 1px solid transparent;
      }

      .btn-primary:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
        box-shadow: var(--shadow-glow);
      }

      .btn-outline {
        background: transparent;
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 0.8rem 2rem;
        color: var(--text);
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        transition: 0.2s;
      }

      .btn-outline:hover {
        background: var(--primary-soft);
        border-color: var(--primary);
        color: var(--primary);
      }

      /* Hidden state */
      .hidden {
        display: none !important;
      }

      /* Responsive */
      @media (max-width: 480px) {
        .logout-card {
          padding: 2rem 1.5rem;
        }

        .logout-actions {
          flex-direction: column;
        }

        .btn-primary,
        .btn-outline {
          width: 100%;
          justify-content: center;
        }

        .security-items {
          text-align: left;
        }
      }

      /* Loading dots animation */
      .loading-dots {
        display: inline-flex;
        gap: 4px;
      }

      .loading-dots span {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--primary);
        animation: dots 1.4s infinite;
      }

      .loading-dots span:nth-child(2) {
        animation-delay: 0.2s;
      }

      .loading-dots span:nth-child(3) {
        animation-delay: 0.4s;
      }

      @keyframes dots {
        0%,
        60%,
        100% {
          transform: translateY(0);
          opacity: 0.6;
        }
        30% {
          transform: translateY(-10px);
          opacity: 1;
        }
      }

      /* Session message */
      .session-message {
        background: var(--glass-bg);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1rem;
        margin-top: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        text-align: left;
      }

      .session-message i {
        color: var(--warning);
        font-size: 1.2rem;
      }

      .session-message span {
        color: var(--text-secondary);
        font-size: 0.9rem;
        line-height: 1.4;
      }

      /* Glitch effect for security */
      @keyframes glitch {
        0% {
          text-shadow:
            0.05em 0 0 var(--primary),
            -0.05em -0.025em 0 var(--danger);
        }
        14% {
          text-shadow:
            0.05em 0 0 var(--primary),
            -0.05em -0.025em 0 var(--danger);
        }
        15% {
          text-shadow:
            -0.05em -0.025em 0 var(--primary),
            0.025em 0.025em 0 var(--danger);
        }
        49% {
          text-shadow:
            -0.05em -0.025em 0 var(--primary),
            0.025em 0.025em 0 var(--danger);
        }
        50% {
          text-shadow:
            0.025em 0.05em 0 var(--primary),
            0.05em 0 0 var(--danger);
        }
        99% {
          text-shadow:
            0.025em 0.05em 0 var(--primary),
            0.05em 0 0 var(--danger);
        }
        100% {
          text-shadow: none;
        }
      }

      .glitch {
        animation: glitch 1s infinite;
      }
    </style>
  </head>
  <body>
    <div class="logout-container">
      <div class="logout-card" data-aos="zoom-in" data-aos-duration="600">
        <!-- Logo -->
        <div class="logout-logo">
          <div class="logo-icon">
            <i class="fa-solid fa-globe"></i>
          </div>
          <h1>Crypterra</h1>
        </div>

        <!-- Stage 1: Logging Out -->
        <div id="stage1" class="logout-stage">
          <div class="logging-out">
            <div class="spinner"></div>
            <h2>Logging you out</h2>
            <div class="loading-dots">
              <span></span>
              <span></span>
              <span></span>
            </div>
            <p style="margin-top: 1rem">
              Please wait while we securely disconnect
            </p>
          </div>
        </div>

        <!-- Stage 2: Security (simulated) -->
        <div id="stage2" class="logout-stage hidden">
          <div class="securing-stage">
            <i
              class="fa-solid fa-shield"
              style="
                font-size: 3rem;
                color: var(--primary);
                margin-bottom: 1rem;
              "
            ></i>
            <h2>Securing your session</h2>

            <div class="security-progress">
              <div class="security-bar" id="securityBar"></div>
            </div>

            <div class="security-items">
              <div class="security-item" id="securityStep1">
                <i class="fa-regular fa-circle"></i>
                <span>Clearing session data...</span>
              </div>
              <div class="security-item" id="securityStep2">
                <i class="fa-regular fa-circle"></i>
                <span>Disconnecting wallet...</span>
              </div>
              <div class="security-item" id="securityStep3">
                <i class="fa-regular fa-circle"></i>
                <span>Removing authentication tokens...</span>
              </div>
            </div>

            <p class="security-note">
              <i class="fa-solid fa-shield-halved"></i>
              Securely erasing sensitive data
            </p>
          </div>
        </div>

        <!-- Stage 3: Success -->
        <div id="stage3" class="logout-stage hidden">
          <div class="success-stage">
            <div class="checkmark">
              <i class="fa-solid fa-check"></i>
            </div>
            <h2>Logged out successfully</h2>
            <p>You have been securely disconnected</p>

            <div class="redirect-timer">
              <i class="fa-regular fa-clock" style="color: var(--primary)"></i>
              <span>Redirecting to login in <span id="timer">5</span>s</span>
              <div class="timer-progress">
                <div class="timer-bar" id="timerBar"></div>
              </div>
            </div>

            <!-- Security message -->
            <div class="session-message">
              <i class="fa-solid fa-triangle-exclamation"></i>
              <span
                >For your security, please close any shared or public
                browsers.</span
              >
            </div>
          </div>
        </div>

        <!-- CTA Buttons (initially hidden, shown after success) -->
        <div id="ctaButtons" class="logout-actions hidden">
          <a href="admin-login.php" class="btn-primary">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            Go to Login
          </a>
          <a href="index.php" class="btn-outline">
            <i class="fa-solid fa-house"></i>
            Go to Home
          </a>
        </div>
      </div>
    </div>

    <!-- AOS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      AOS.init({ duration: 700, once: true });

      // Logout sequence simulation
      document.addEventListener("DOMContentLoaded", function () {
        const stage1 = document.getElementById("stage1");
        const stage2 = document.getElementById("stage2");
        const stage3 = document.getElementById("stage3");
        const ctaButtons = document.getElementById("ctaButtons");

        const securityBar = document.getElementById("securityBar");
        const securityStep1 = document.getElementById("securityStep1");
        const securityStep2 = document.getElementById("securityStep2");
        const securityStep3 = document.getElementById("securityStep3");

        const timerElement = document.getElementById("timer");
        const timerBar = document.getElementById("timerBar");

        // Step 1: Show logging out for 2 seconds
        setTimeout(() => {
          stage1.classList.add("hidden");
          stage2.classList.remove("hidden");

          // Start security bar animation
          setTimeout(() => {
            securityBar.style.width = "33%";

            // Update step 1
            securityStep1.innerHTML =
              '<i class="fa-solid fa-check-circle" style="color: var(--success);"></i> <span>Clearing session data...</span>';
            securityStep1.classList.add("completed");
          }, 100);

          setTimeout(() => {
            securityBar.style.width = "66%";

            // Update step 2
            securityStep2.innerHTML =
              '<i class="fa-solid fa-check-circle" style="color: var(--success);"></i> <span>Disconnecting wallet...</span>';
            securityStep2.classList.add("completed");
          }, 1500);

          setTimeout(() => {
            securityBar.style.width = "100%";

            // Update step 3
            securityStep3.innerHTML =
              '<i class="fa-solid fa-check-circle" style="color: var(--success);"></i> <span>Removing authentication tokens...</span>';
            securityStep3.classList.add("completed");
          }, 2500);
        }, 2000);

        // Step 2: Show success after security
        setTimeout(() => {
          stage2.classList.add("hidden");
          stage3.classList.remove("hidden");

          // Show CTA buttons
          setTimeout(() => {
            ctaButtons.classList.remove("hidden");
          }, 500);

          // Start countdown
          let timeLeft = 5;
          const countdown = setInterval(() => {
            timeLeft--;
            timerElement.textContent = timeLeft;

            if (timeLeft <= 0) {
              clearInterval(countdown);
              // Auto redirect to login
              window.location.href = "admin-login.php";
            }
          }, 1000);

          // Remove timer bar animation class and let CSS handle it
          timerBar.style.animation = "timer 5s linear forwards";
        }, 5000); // Total time: 2s (logging) + 3s (security) = 5s

        // Simulate clearing session storage
        try {
          sessionStorage.clear();
          localStorage.removeItem("auth_token");
          localStorage.removeItem("user_data");
          console.log("Session cleared successfully");
        } catch (e) {
          console.log("Session clear simulation");
        }

        // Simulate wallet disconnection (for Web3)
        if (window.ethereum) {
          console.log("Web3 wallet disconnected (simulated)");
        }

        // Prevent back button after logout (simple simulation)
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
          history.go(1);
        };
      });

      // Handle visibility change (for security)
      document.addEventListener("visibilitychange", function () {
        if (document.hidden) {
          console.log("User left page - security measure");
        }
      });

      // Prevent accidental form resubmission
      if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
      }
    </script>

    <!-- Additional security message for browsers with disabled JS -->
    <noscript>
      <div
        style="
          position: fixed;
          bottom: 20px;
          left: 20px;
          right: 20px;
          background: var(--danger);
          color: white;
          padding: 1rem;
          border-radius: 40px;
          text-align: center;
          z-index: 9999;
        "
      >
        <i class="fa-solid fa-triangle-exclamation"></i>
        JavaScript is disabled. For security, please enable JavaScript or
        manually clear your session.
      </div>
    </noscript>
  </body>
</html>
