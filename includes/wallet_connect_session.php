<?php
declare(strict_types=1);

/**
 * Shared session bootstrap for wallet connect (public + logged-in).
 * Ensures CSRF and a stable guest id for anonymous visitors.
 */
function wallet_connect_session_start(): void {
  if (session_status() === PHP_SESSION_ACTIVE) {
    return;
  }
  // Must match login.php / Userprofile user_auth.php so wallet connect sees logged-in users.
  @session_name('crypterra_user');
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
  session_start();
}

wallet_connect_session_start();

if (empty($_SESSION['wallet_guest_id']) || !is_string($_SESSION['wallet_guest_id'])) {
  $_SESSION['wallet_guest_id'] = bin2hex(random_bytes(16));
}
if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function wallet_connect_owner_key(): string {
  if (!empty($_SESSION['user_id'])) {
    return 'user:' . (int)$_SESSION['user_id'];
  }
  $g = $_SESSION['wallet_guest_id'] ?? '';
  return 'guest:' . (is_string($g) ? $g : '');
}

function wallet_connect_csrf(): string {
  return (string)$_SESSION['csrf_token'];
}
