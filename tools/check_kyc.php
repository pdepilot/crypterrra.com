<?php
declare(strict_types=1);

require_once __DIR__ . '/../Userprofile/includes/user_db.php';

global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
} catch (Throwable $e) {
  http_response_code(500);
  echo "DB connect failed: " . $e->getMessage() . PHP_EOL;
  exit(1);
}

$tables = [
  'users',
  'kyc_requests',
];

foreach ($tables as $t) {
  $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
  $exists = ($r && $r->num_rows > 0);
  if ($r) $r->free();
  echo $t . ': ' . ($exists ? 'exists' : 'missing') . PHP_EOL;
}

// Also show current DB name to ensure we're in expected schema.
$r = $db->query("SELECT DATABASE() AS d");
if ($r) {
  $row = $r->fetch_assoc();
  $r->free();
  echo "database: " . (string)($row['d'] ?? '') . PHP_EOL;
}

$db->close();

