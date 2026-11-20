<?php
declare(strict_types=1);

// -----------------------------
// Client Auth (Signup + Login)
// -----------------------------

// Defaults (XAMPP MySQL: root often has an empty password).
// Real credentials belong in db-config.php, which is not committed.
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'crypterra';

// Optional local overrides — create db-config.php with the same variables if your MySQL password differs.
if (is_file(__DIR__ . '/db-config.php')) {
  require __DIR__ . '/db-config.php';
}

$USERS_TABLE = 'users';

function h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function client_ip(): string {
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  return is_string($ip) ? $ip : '';
}

function session_harden_and_start(): void {
  // Must match `Userprofile/includes/user_auth.php` or login never persists after redirect.
  if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_name('crypterra_user');
  }
  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
      'lifetime' => 0,
      'path' => '/',
      'domain' => '',
      'secure' => $isHttps,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  } else {
    session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
  }
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
}

function csrf_token(): string {
  if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool {
  if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    return false;
  }
  if (!is_string($token) || $token === '') {
    return false;
  }
  return hash_equals($_SESSION['csrf_token'], $token);
}

function db_connect(string $host, string $user, string $pass, string $db): mysqli {
  mysqli_report(MYSQLI_REPORT_OFF);
  $mysqli = @new mysqli($host, $user, $pass);
  if ($mysqli->connect_errno) {
    throw new RuntimeException('Database connection failed: ' . $mysqli->connect_error);
  }
  $mysqli->set_charset('utf8mb4');
  if ($db !== '' && !preg_match('/^[a-zA-Z0-9_]+$/', $db)) {
    $mysqli->close();
    throw new RuntimeException('Invalid database name.');
  }
  if ($db !== '') {
    @$mysqli->query(
      'CREATE DATABASE IF NOT EXISTS `' . $db . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    if (!$mysqli->select_db($db)) {
      $err = $mysqli->error;
      $mysqli->close();
      throw new RuntimeException('Cannot open database: ' . $err);
    }
  }
  return $mysqli;
}

function ensure_users_table(mysqli $db, string $usersTable): void {
  $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$usersTable}` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `last_login_ip` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
  @$db->query($sql);
}

function schema_column_exists(mysqli $db, string $schema, string $table, string $column): bool {
  $s = $db->real_escape_string($schema);
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($column);
  $r = @$db->query(
    "SELECT COUNT(*) AS n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$s}' AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'"
  );
  if (!$r) return false;
  $row = $r->fetch_assoc();
  $r->free();
  return isset($row['n']) && (int)$row['n'] > 0;
}

session_harden_and_start();

$error = '';
$flash = '';
$prefillEmail = '';
$view = (isset($_GET['view']) && is_string($_GET['view'])) ? $_GET['view'] : '';
if ($view !== 'login' && $view !== 'signup') {
  $view = '';
}

// If redirected here due to admin block, show a clear message (once),
// then redirect to a clean URL so it doesn't stick after unlock.
if (isset($_GET['blocked']) && is_string($_GET['blocked']) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
  $b = strtolower(trim($_GET['blocked']));
  if ($b === 'locked') {
    $_SESSION['flash_error'] = 'Your account is locked. Please contact support.';
    $view = 'login';
  } elseif ($b === 'suspended') {
    $_SESSION['flash_error'] = 'Your account is suspended. Please contact support.';
    $view = 'login';
  }

  // Drop the blocked parameter from the address bar.
  $next = (isset($_GET['next']) && is_string($_GET['next'])) ? $_GET['next'] : '';
  $location = 'login.php?view=login';
  if ($next !== '') {
    $location .= '&next=' . rawurlencode($next);
  }
  header('Location: ' . $location);
  exit();
}

// Session-based one-time error (e.g. from blocked redirect).
if ($error === '' && isset($_SESSION['flash_error']) && is_string($_SESSION['flash_error']) && $_SESSION['flash_error'] !== '') {
  $error = $_SESSION['flash_error'];
  unset($_SESSION['flash_error']);
  $view = 'login';
}

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
  $flash = 'Account created successfully. Please log in.';
  $view = 'login';
}

// If already logged in, go straight to dashboard.
if (!empty($_SESSION['user_id'])) {
  header('Location: Userprofile/userprofile.php');
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
  $postedCsrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;

  if (!verify_csrf($postedCsrf)) {
    $error = 'Security verification failed. Please refresh and try again.';
  } else {
    try {
      $db = db_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
      ensure_users_table($db, $USERS_TABLE);

      if ($action === 'signup') {
        $fullName = isset($_POST['full_name']) && is_string($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? (string)$_POST['password'] : '';
        $confirm = isset($_POST['confirm_password']) && is_string($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

        $prefillEmail = $email;
        $view = 'signup';

        if ($fullName === '' || $email === '' || $password === '' || $confirm === '') {
          $error = 'Please complete all signup fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $error = 'Please enter a valid email address.';
        } elseif (mb_strlen($password) < 8) {
          $error = 'Password must be at least 8 characters.';
        } elseif (!hash_equals($password, $confirm)) {
          $error = 'Passwords do not match.';
        } else {
          $emailLower = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);

          $existsStmt = $db->prepare("SELECT id FROM `{$USERS_TABLE}` WHERE email = ? LIMIT 1");
          if (!$existsStmt) {
            throw new RuntimeException('prepare failed: ' . $db->error);
          }
          $existsStmt->bind_param('s', $emailLower);
          $existsStmt->execute();
          $existsStmt->store_result();
          $already = $existsStmt->num_rows > 0;
          $existsStmt->free_result();
          $existsStmt->close();

          if ($already) {
            $error = 'An account with that email already exists. Please log in.';
            $view = 'login';
          } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($hash) || $hash === '') {
              throw new RuntimeException('password_hash failed');
            }

            $ins = $db->prepare("INSERT INTO `{$USERS_TABLE}` (full_name, email, password, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
            if (!$ins) {
              throw new RuntimeException('prepare failed: ' . $db->error);
            }
            $ins->bind_param('sss', $fullName, $emailLower, $hash);
            if (!$ins->execute()) {
              $ins->close();
              throw new RuntimeException('insert failed: ' . $db->error);
            }
            $ins->close();
            $db->close();

            header('Location: login.php?registered=1&view=login');
            exit();
          }
        }
      } elseif ($action === 'login') {
        $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) && is_string($_POST['password']) ? (string)$_POST['password'] : '';

        $prefillEmail = $email;
        $view = 'login';

        if ($email === '' || $password === '') {
          $error = 'Please enter your email and password.';
        } else {
          $emailLower = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
          // Admin security controls: locked/suspended accounts must see a clear message.
          $hasLocked = schema_column_exists($db, (string)$DB_NAME, (string)$USERS_TABLE, 'is_locked');

          $cols = ['id', 'full_name', 'email', 'password', 'is_active'];
          if ($hasLocked) {
            $cols[] = 'is_locked';
          } else {
            $cols[] = '0 AS is_locked';
          }

          $stmt = $db->prepare("SELECT " . implode(', ', $cols) . " FROM `{$USERS_TABLE}` WHERE email = ? LIMIT 1");
          if (!$stmt) {
            throw new RuntimeException('prepare failed: ' . $db->error);
          }
          $stmt->bind_param('s', $emailLower);
          $stmt->execute();
          $stmt->store_result();

          $id = 0;
          $fullName = $dbEmail = $hash = '';
          $isActive = 0;
          $isLocked = 0;
          $stmt->bind_result($id, $fullName, $dbEmail, $hash, $isActive, $isLocked);
          $found = $stmt->fetch();
          $stmt->free_result();
          $stmt->close();

          $passwordOk = $found && is_string($hash) && $hash !== '' && password_verify($password, $hash);
          if (!$passwordOk) {
            $error = 'Invalid credentials. Please try again.';
          } elseif (((int)$isLocked) === 1) {
            $error = 'Your account is locked. Please contact support.';
          } elseif (((int)$isActive) !== 1) {
            $error = 'Your account is suspended. Please contact support.';
          } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$id;
            $_SESSION['user_full_name'] = (string)$fullName;
            $_SESSION['user_email'] = (string)$dbEmail;
            $_SESSION['user_login_time'] = time();

            $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $ip = client_ip();
            if ($up = $db->prepare("UPDATE `{$USERS_TABLE}` SET last_login_at = ?, last_login_ip = ? WHERE id = ?")) {
              $uid = (int)$id;
              $up->bind_param('ssi', $now, $ip, $uid);
              @$up->execute();
              $up->close();
            }
            require_once __DIR__ . '/Userprofile/includes/user_db.php';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ua = is_string($ua) ? $ua : '';
            $deviceLabel = user_agent_friendly_label($ua);
            $detail = $deviceLabel . ' • IP: ' . ($ip !== '' ? $ip : '—');
            user_log_security_activity($db, (int)$id, 'login_success', 'Login successful', $detail, $ip);
            $db->close();

            header('Location: Userprofile/userprofile.php');
            exit();
          }
        }
      } else {
        $error = 'Invalid request.';
      }

      $db->close();
    } catch (Throwable $e) {
      error_log('[client-auth] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
      $error = 'Authentication temporarily unavailable. Please try again.';
    }
  }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    />
    <title>Crypterra · Auth – next‑gen crypto trading</title>
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
    <!-- AOS scroll animation (lightweight) -->
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
        min-height: 100vh;
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
      }

      /* ---------- AUTH PAGE SPECIFIC (futuristic web3 ui) ---------- */
      .auth-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        position: relative;
        overflow: hidden;
        background: radial-gradient(
          ellipse at 70% 40%,
          rgba(34, 197, 94, 0.12),
          transparent 50%
        );
      }

      /* immersive particle / grid background */
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
      .floating-symbol {
        position: absolute;
        font-size: 2rem;
        color: var(--primary);
        opacity: 0.1;
        animation: floatSymbol 20s infinite alternate;
        filter: drop-shadow(0 0 20px var(--primary));
      }
      @keyframes floatSymbol {
        0% {
          transform: translate(0, 0) rotate(0deg);
        }
        100% {
          transform: translate(30px, -30px) rotate(10deg);
        }
      }

      /* main layout: left (auth card) + right (illustration) */
      .auth-split {
        display: flex;
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem 5%;
        gap: 4rem;
        align-items: center;
        position: relative;
        z-index: 10;
      }
      .auth-left {
        flex: 1;
        display: flex;
        justify-content: center;
      }
      .auth-right {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
      }

      /* floating glass card */
      .auth-card {
        width: 100%;
        max-width: 480px;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-edge);
        border-radius: 60px;
        padding: 2.5rem;
        box-shadow:
          0 0 60px var(--primary-glow),
          0 20px 40px -20px black;
        animation: floatCard 6s infinite alternate ease-in-out;
        transition: box-shadow 0.3s;
      }
      .auth-card:hover {
        box-shadow:
          0 0 80px var(--primary-glow),
          0 30px 50px -20px black;
      }
      @keyframes floatCard {
        0% {
          transform: translateY(0);
        }
        100% {
          transform: translateY(-10px);
        }
      }

      /* 3D cube switching container */
      .cube-container {
        perspective: 1200px;
        width: 100%;
        min-height: 500px;
        margin-bottom: 1.5rem;
      }
      .cube {
        position: relative;
        width: 100%;
        height: 100%;
        transform-style: preserve-3d;
        transition: transform 0.8s cubic-bezier(0.23, 1, 0.32, 1);
      }
      .cube.flipped {
        transform: rotateY(180deg);
      }
      .cube-face {
        position: absolute;
        width: 100%;
        backface-visibility: hidden;
        background: transparent;
        border-radius: 40px;
        padding: 0.5rem 0;
      }
      .cube-face.front {
        transform: rotateY(0deg);
      }
      .cube-face.back {
        transform: rotateY(180deg);
      }

      /* form styles */
      .form-group {
        margin-bottom: 1.5rem;
        position: relative;
      }
      .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        color: var(--text-light);
        transition: 0.2s;
      }
      .form-group input {
        width: 100%;
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid var(--glass-edge);
        border-radius: 40px;
        padding: 1rem 1.5rem;
        color: white;
        font-size: 1rem;
        outline: none;
        transition: 0.2s;
      }
      .form-group input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 30px var(--primary-glow);
        transform: scale(1.02);
      }
      .floating-label {
        position: absolute;
        left: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
        color: #aac8ff;
        pointer-events: none;
        transition: 0.2s;
      }
      .form-group input:focus ~ .floating-label,
      .form-group input:not(:placeholder-shown) ~ .floating-label {
        opacity: 0;
      }

      /* password strength */
      .strength-meter {
        display: flex;
        gap: 0.5rem;
        margin: 0.5rem 0 1rem;
      }
      .strength-segment {
        height: 6px;
        flex: 1;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        transition: 0.3s;
      }
      .strength-segment.weak {
        background: #ef5350;
        box-shadow: 0 0 10px #ef5350;
      }
      .strength-segment.medium {
        background: #ffa726;
        box-shadow: 0 0 10px #ffa726;
      }
      .strength-segment.strong {
        background: var(--primary);
        box-shadow: 0 0 15px var(--primary);
      }

      .validation-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.85rem;
        margin: 0.5rem 0;
      }
      .validation-item i {
        font-size: 1rem;
      }
      .valid {
        color: var(--primary);
      }
      .invalid {
        color: #aac8ff;
        opacity: 0.6;
      }

      /* buttons */
      .auth-btn {
        width: 100%;
        background: var(--primary);
        color: var(--dark-bg);
        border: none;
        border-radius: 60px;
        padding: 1rem;
        font-weight: 700;
        font-size: 1.1rem;
        cursor: pointer;
        transition: 0.3s;
        box-shadow: 0 0 30px var(--primary-glow);
        margin-top: 1rem;
      }
      .auth-btn:hover {
        transform: scale(1.02);
        box-shadow: 0 0 50px var(--primary);
      }

      /* social grid */
      .social-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.8rem;
        margin: 1.5rem 0;
      }
      .social-icon {
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid var(--glass-edge);
        border-radius: 50px;
        padding: 0.8rem;
        text-align: center;
        cursor: pointer;
        transition: 0.2s;
        font-size: 1.2rem;
      }
      .social-icon:hover {
        border-color: var(--primary);
        transform: translateY(-4px);
        box-shadow: 0 0 30px var(--primary-glow);
        color: var(--primary);
      }

      /* wallet connect button */
      .wallet-signup {
        background: transparent;
        border: 2px solid var(--primary);
        border-radius: 60px;
        padding: 1rem;
        width: 100%;
        color: white;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        cursor: pointer;
        transition: 0.3s;
      }
      .wallet-signup:hover {
        background: rgba(34, 197, 94, 0.1);
        border-color: var(--primary);
        box-shadow: 0 0 30px var(--primary-glow);
      }
      .wallet-icons {
        display: flex;
        gap: 0.5rem;
      }
      .wallet-icons i {
        font-size: 1.2rem;
      }

      /* right side illustration */
      .illustration {
        position: relative;
        width: 100%;
        height: 500px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
      }
      .node {
        position: absolute;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(34, 197, 94, 0.1);
        border: 2px solid var(--primary);
        box-shadow: 0 0 50px var(--primary-glow);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        animation: orbit 15s infinite linear;
      }
      .node:nth-child(1) {
        animation-delay: 0s;
        top: 20%;
        left: 20%;
      }
      .node:nth-child(2) {
        animation-delay: -5s;
        top: 60%;
        right: 20%;
      }
      .node:nth-child(3) {
        animation-delay: -10s;
        bottom: 20%;
        left: 40%;
      }
      @keyframes orbit {
        0% {
          transform: rotate(0deg) translateX(30px) rotate(0deg);
        }
        100% {
          transform: rotate(360deg) translateX(30px) rotate(-360deg);
        }
      }

      /* success modal */
      .success-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(20px);
        z-index: 1000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: 0.3s;
      }
      .success-overlay.open {
        opacity: 1;
        visibility: visible;
      }
      .success-card {
        background: var(--glass-bg);
        border: 2px solid var(--primary);
        border-radius: 60px;
        padding: 3rem;
        text-align: center;
        box-shadow: 0 0 100px var(--primary);
        transform: scale(0.9);
        transition: 0.3s;
      }
      .success-overlay.open .success-card {
        transform: scale(1);
      }
      .checkmark-circle {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2.5rem;
        color: #0b1c2f;
        animation: pulseCheck 2s infinite;
      }
      @keyframes pulseCheck {
        0% {
          box-shadow: 0 0 0 0 var(--primary-glow);
        }
        70% {
          box-shadow: 0 0 0 20px rgba(34, 197, 94, 0);
        }
      }

      /* footer (same) */
      .footer {
        background: rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(20px);
        border-top: 1px solid var(--primary);
        padding: 2rem 5% 1.5rem;
      }
      .footer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 1.5rem;
      }
      .footer-col a {
        display: block;
        color: #aac8ff;
        text-decoration: none;
        margin: 0.5rem 0;
        transition: 0.2s;
      }
      .footer-col a:hover {
        color: var(--primary);
        transform: translateX(5px);
      }
      .social-icons {
        display: flex;
        gap: 1.2rem;
        margin-top: 1.5rem;
      }
      .social-icons i {
        font-size: 1.5rem;
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
        right: 2rem;
        bottom: 2rem;
        background: rgba(34, 197, 94, 0.8);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.35);
        color: white;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.4rem;
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

      @media (max-width: 1000px) {
        .auth-split {
          flex-direction: column;
        }
        .auth-right {
          display: none;
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
        <li><a href="index.php">Home</a></li>
        <li><a href="about.php">About</a></li>
        <li><a href="contact.php">Contact</a></li>
        <li><a href="faqs.php">FAQs</a></li>
        <li><a href="markets.php">Markets</a></li>
</ul>
      <button class="signup">Sign Up</button>
      <div class="hamburger-wrapper">
        <div class="hamburger-icon" id="hamburgerIcon">
          <div class="bar"></div>
          <div class="bar"></div>
          <div class="bar"></div>
        </div>
        <div class="hamburger-dropdown" id="hamburgerDropdown">
          <ul class="dropdown-links">
            <li>
              <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
            </li>
            <li>
              <a href="about.php"
                ><i class="fa-solid fa-info-circle"></i> About</a
              >
            </li>
            <li>
              <a href="contact.php"
                ><i class="fa-solid fa-envelope"></i> Contact</a
              >
            </li>
            <li>
              <a href="faqs.php"
                ><i class="fa-solid fa-question-circle"></i> FAQs</a
              >
            </li>
            <li>
              <a href="markets.php"
                ><i class="fa-solid fa-chart-line"></i> Markets</a
              >
            </li>
          </ul>
          <button class="dropdown-signup">Sign Up</button>
        </div>
      </div>
    </nav>

    <!-- MAIN AUTH SECTION with immersive background -->
    <div class="auth-container">
      <!-- particle overlay -->
      <div class="particle-overlay" id="authParticles"></div>
      <!-- floating crypto symbols -->
      <div class="floating-symbol" style="top: 10%; left: 5%">₿</div>
      <div class="floating-symbol" style="bottom: 15%; right: 8%">Ξ</div>
      <div class="floating-symbol" style="top: 30%; right: 15%">◎</div>
      <div class="floating-symbol" style="bottom: 25%; left: 10%">✦</div>

      <div class="auth-split">
        <!-- left: floating glass auth card with 3D cube -->
        <div class="auth-left">
          <div class="auth-card">
            <div
              style="
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 2rem;
              "
            >
              <h2 style="font-size: 2rem" id="formTitle">Sign Up</h2>
              <div style="display: flex; gap: 0.5rem">
                <button
                  id="switchToLogin"
                  style="
                    background: none;
                    border: none;
                    color: var(--text-light);
                    cursor: pointer;
                    padding: 0.5rem 1rem;
                    border-radius: 40px;
                    transition: 0.2s;
                  "
                >
                  Login
                </button>
                <button
                  id="switchToSignup"
                  style="
                    background: var(--primary);
                    color: #0b1c2f;
                    border: none;
                    padding: 0.5rem 1.5rem;
                    border-radius: 40px;
                    font-weight: 600;
                    cursor: pointer;
                    box-shadow: 0 0 20px var(--primary);
                  "
                >
                  Sign Up
                </button>
              </div>
            </div>

            <?php if ($flash !== ''): ?>
              <div style="margin: 0 0 1rem; padding: 0.85rem 1.2rem; border-radius: 24px; border: 1px solid rgba(34,197,94,.35); background: rgba(34,197,94,.12); color: #eafff2;">
                <?php echo h($flash); ?>
              </div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
              <div style="margin: 0 0 1rem; padding: 0.85rem 1.2rem; border-radius: 24px; border: 1px solid rgba(239,83,80,.6); background: rgba(239,83,80,.12); color: #ffd9d9;">
                <?php echo h($error); ?>
              </div>
            <?php endif; ?>

            <!-- 3D CUBE CONTAINER -->
            <div class="cube-container">
              <div class="cube" id="authCube">
                <!-- FRONT: Signup -->
                <div class="cube-face front">
                  <form method="post" action="login.php" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="action" value="signup">

                    <div class="form-group">
                      <input
                        type="text"
                        id="signupName"
                        name="full_name"
                        placeholder=" "
                        autocomplete="name"
                        required
                      />
                      <label class="floating-label">Full Name</label>
                    </div>
                    <div class="form-group">
                      <input
                        type="email"
                        id="signupEmail"
                        name="email"
                        placeholder=" "
                        autocomplete="email"
                        required
                      />
                      <label class="floating-label">Email Address</label>
                    </div>
                    <div class="validation-item" id="emailValidIndicator">
                      <i class="fa-regular fa-circle-check"></i>
                      <span>Password</span>
                    </div>
                    <div class="form-group">
                      <input
                        type="password"
                        id="signupPassword"
                        name="password"
                        placeholder=" "
                        autocomplete="new-password"
                        required
                      />
                      <label class="floating-label">Password</label>
                    </div>
                    <div class="strength-meter">
                      <div class="strength-segment" id="strength1"></div>
                      <div class="strength-segment" id="strength2"></div>
                      <div class="strength-segment" id="strength3"></div>
                    </div>
                    <div class="validation-item" id="passLengthIndicator">
                      <i class="fa-regular fa-circle-check"></i>
                      <span>At least 8 characters</span>
                    </div>
                    <div class="form-group">
                      <input type="password" id="signupConfirm" name="confirm_password" placeholder=" " autocomplete="new-password" required />
                      <label class="floating-label">Confirm Password</label>
                    </div>
                    <div class="validation-item" id="matchIndicator">
                      <i class="fa-regular fa-circle-check"></i>
                      <span>Passwords match</span>
                    </div>
                    <button class="auth-btn" id="signupBtn" type="submit">
                      Create Account
                    </button>
                  </form>
                </div>

                <!-- BACK: Login -->
                <div class="cube-face back">
                  <form method="post" action="login.php" autocomplete="on">
                    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                    <input type="hidden" name="action" value="login">

                    <div class="form-group">
                      <input type="email" id="loginEmail" name="email" placeholder=" " autocomplete="email" value="<?php echo h($prefillEmail); ?>" required />
                      <label class="floating-label">Email Address</label>
                    </div>
                    <div class="form-group">
                      <input type="password" id="loginPassword" name="password" placeholder=" " autocomplete="current-password" required />
                      <label class="floating-label">Password</label>
                    </div>
                    <div
                      style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin: 1rem 0;
                      "
                    >
                      <label><input type="checkbox" disabled /> Remember me</label>
                      <a
                        href="#"
                        style="color: var(--primary); text-decoration: none"
                        >Forgot password?</a
                      >
                    </div>
                    <button class="auth-btn" id="loginBtn" type="submit">Login</button>
                  </form>
                </div>
              </div>
            </div>

            <!-- social & wallet (common) -->
            <div class="social-grid">
              <div class="social-icon"><i class="fa-brands fa-google"></i></div>
              <div class="social-icon"><i class="fa-brands fa-apple"></i></div>
              <div class="social-icon">
                <i class="fa-brands fa-x-twitter"></i>
              </div>
              <div class="social-icon"><i class="fa-brands fa-github"></i></div>
            </div>

                      </div>
        </div>

        <!-- right: web3 illustration with orbiting nodes -->
        <div class="auth-right">
          <div class="illustration">
            <div class="node"><i class="fa-brands fa-bitcoin"></i></div>
            <div class="node"><i class="fa-brands fa-ethereum"></i></div>
            <div class="node"><i class="fa-solid fa-link"></i></div>
            <div
              class="grid-bg"
              style="
                width: 100%;
                height: 100%;
                border-radius: 60px;
                border: 1px solid var(--glass-edge);
              "
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SUCCESS MODAL removed (server redirects after signup) -->

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

        // PARTICLES for auth background
        const authParticles = document.getElementById("authParticles");
        for (let i = 0; i < 40; i++) {
          let p = document.createElement("div");
          p.className = "particle";
          p.style.left = Math.random() * 100 + "%";
          p.style.animationDelay = Math.random() * 10 + "s";
          p.style.animationDuration = 15 + Math.random() * 10 + "s";
          authParticles.appendChild(p);
        }

        // 3D CUBE SWITCHING (revolutionary!)
        const cube = document.getElementById("authCube");
        const switchToLogin = document.getElementById("switchToLogin");
        const switchToSignup = document.getElementById("switchToSignup");
        const formTitle = document.getElementById("formTitle");

        switchToLogin.addEventListener("click", () => {
          cube.classList.add("flipped");
          formTitle.innerText = "Login";
          switchToLogin.style.background = "var(--primary)";
          switchToLogin.style.color = "#0b1c2f";
          switchToSignup.style.background = "none";
          switchToSignup.style.color = "var(--text-light)";
        });

        switchToSignup.addEventListener("click", () => {
          cube.classList.remove("flipped");
          formTitle.innerText = "Sign Up";
          switchToSignup.style.background = "var(--primary)";
          switchToSignup.style.color = "#0b1c2f";
          switchToLogin.style.background = "none";
          switchToLogin.style.color = "var(--text-light)";
        });

        // Password strength indicator
        const passInput = document.getElementById("signupPassword");
        const strength1 = document.getElementById("strength1");
        const strength2 = document.getElementById("strength2");
        const strength3 = document.getElementById("strength3");
        const passLength = document.getElementById("passLengthIndicator");
        const matchIndicator = document.getElementById("matchIndicator");
        const confirmInput = document.getElementById("signupConfirm");
        const emailInput = document.getElementById("signupEmail");
        const emailValid = document.getElementById("emailValidIndicator");

        passInput.addEventListener("input", () => {
          const val = passInput.value;
          const length = val.length;
          strength1.classList.remove("weak", "medium", "strong");
          strength2.classList.remove("weak", "medium", "strong");
          strength3.classList.remove("weak", "medium", "strong");
          if (length > 0) strength1.classList.add("weak");
          if (length >= 5) strength2.classList.add("medium");
          if (length >= 8) strength3.classList.add("strong");

          if (length >= 8)
            passLength.querySelector("i").className =
              "fa-regular fa-circle-check valid";
          else
            passLength.querySelector("i").className =
              "fa-regular fa-circle-check invalid";
        });

        confirmInput.addEventListener("input", () => {
          if (
            confirmInput.value === passInput.value &&
            passInput.value.length > 0
          ) {
            matchIndicator.querySelector("i").className =
              "fa-regular fa-circle-check valid";
          } else {
            matchIndicator.querySelector("i").className =
              "fa-regular fa-circle-check invalid";
          }
        });

        emailInput.addEventListener("input", () => {
          const email = emailInput.value;
          const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
          if (isValid) {
            emailValid.querySelector("i").className =
              "fa-regular fa-circle-check valid";
          } else {
            emailValid.querySelector("i").className =
              "fa-regular fa-circle-check invalid";
          }
        });

        // Server handles signup/login redirects via POST.

        // Default view control (query param)
        const desiredView = "<?php echo h($view); ?>";
        if (desiredView === "login") {
          cube.classList.add("flipped");
          formTitle.innerText = "Login";
          switchToLogin.style.background = "var(--primary)";
          switchToLogin.style.color = "#0b1c2f";
          switchToSignup.style.background = "none";
          switchToSignup.style.color = "var(--text-light)";
        } else if (desiredView === "signup") {
          cube.classList.remove("flipped");
        }
      })();
    </script>
  <?php include __DIR__ . '/includes/smartsupp.php'; ?>
  </body>
</html>
