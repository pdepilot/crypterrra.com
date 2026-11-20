<?php
declare(strict_types=1);

require_once __DIR__ . '/../Userprofile/includes/user_db.php';

global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
} catch (Throwable $e) {
  echo "DB connect failed: " . $e->getMessage() . PHP_EOL;
  exit(1);
}

ensure_kyc_requests_table($db);
ensure_users_kyc_columns($db, (string)$DB_NAME);

$sql = "SELECT u.id AS user_id, u.email, u.full_name, u.kyc_status, k.id AS kyc_id, k.status AS kyc_request_status, k.submitted_at, k.reviewed_at, k.rejection_reason
        FROM users u
        LEFT JOIN kyc_requests k ON k.user_id = u.id
        ORDER BY u.id DESC
        LIMIT 20";
$r = $db->query($sql);
if (!$r) {
  echo "Query failed: " . $db->error . PHP_EOL;
  $db->close();
  exit(1);
}

while ($row = $r->fetch_assoc()) {
  echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
$r->free();
$db->close();

