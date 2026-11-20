<?php
declare(strict_types=1);
/**
 * Server-side logout: clears session so login.php does not redirect back to Userprofile.
 * Called after the user confirms logout (or uses "Back to login" from the logout screen).
 */
require_once __DIR__ . '/includes/user_auth.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(
    (string)session_name(),
    '',
    time() - 42000,
    (string)($params['path'] ?? '/'),
    (string)($params['domain'] ?? ''),
    (bool)($params['secure'] ?? false),
    (bool)($params['httponly'] ?? false)
  );
}
if (session_status() === PHP_SESSION_ACTIVE) {
  session_destroy();
}

header('Location: ' . userprofile_login_url('view=login'));
exit;
