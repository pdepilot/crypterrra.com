<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  @session_name('crypterra_admin');
  session_start();
}

function admin_csrf_token(): string {
  if (empty($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['admin_csrf_token'];
}

function admin_verify_csrf(?string $token): bool {
  if (!isset($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token'])) {
    return false;
  }
  if (!is_string($token) || $token === '') {
    return false;
  }
  return hash_equals($_SESSION['admin_csrf_token'], $token);
}

