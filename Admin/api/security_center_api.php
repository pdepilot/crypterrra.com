<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_csrf.php';
require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';
require_once dirname(__DIR__) . '/includes/admin_security_center_data.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  $payload = admin_security_center_payload($db, (string)$DB_NAME);
  $db->close();
  echo json_encode(['ok' => true, 'payload' => $payload, 'csrf' => admin_csrf_token()], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  error_log('[security_center_api] ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Could not load security activity.']);
}

