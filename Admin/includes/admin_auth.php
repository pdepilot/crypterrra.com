<?php
declare(strict_types=1);

// Keep admin session isolated from user session.
if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_name('crypterra_admin');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$aid = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
if ($aid <= 0) {
  header('Location: admin-login.php');
  exit;
}

$GLOBALS['ADMIN_SESSION_ID'] = $aid;
$GLOBALS['ADMIN_SESSION_EMAIL'] = isset($_SESSION['admin_email']) && is_string($_SESSION['admin_email'])
  ? $_SESSION['admin_email']
  : '';
