<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
  exit;
}

require_once __DIR__ . '/includes/contact_helpers.php';

$csrf = $_POST['csrf_token'] ?? '';
$expected = $_SESSION['contact_csrf'] ?? '';
if (!is_string($csrf) || !is_string($expected) || $csrf === '' || !hash_equals($expected, $csrf)) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'csrf']);
  exit;
}

// Honeypot (bots fill hidden fields)
$hp = $_POST['website'] ?? '';
if (is_string($hp) && $hp !== '') {
  echo json_encode(['ok' => true]);
  exit;
}

$name = isset($_POST['name']) && is_string($_POST['name']) ? $_POST['name'] : '';
$email = isset($_POST['email']) && is_string($_POST['email']) ? $_POST['email'] : '';
$subject = isset($_POST['subject']) && is_string($_POST['subject']) ? $_POST['subject'] : '';
$message = isset($_POST['message']) && is_string($_POST['message']) ? $_POST['message'] : '';
$category = isset($_POST['category']) && is_string($_POST['category']) ? $_POST['category'] : 'general';

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ip = is_string($ip) ? $ip : '';
if (contact_count_recent_by_ip($ip, 3600) >= 8) {
  http_response_code(429);
  echo json_encode(['ok' => false, 'error' => 'rate_limit']);
  exit;
}

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$ua = is_string($ua) ? mb_substr($ua, 0, 255) : '';

$result = contact_insert_submission($name, $email, $subject, $message, $ip, $ua, $category);
if (!$result['ok']) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'unknown']);
  exit;
}

echo json_encode(['ok' => true, 'ticket' => contact_ticket_ref((int)$result['id'])]);
