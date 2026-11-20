<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_csrf.php';
require_once dirname(__DIR__, 2) . '/Userprofile/includes/user_db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function kyc_api_int($v, int $default = 0): int {
  if (is_numeric($v)) return (int)$v;
  return $default;
}

function kyc_api_status_norm(string $s): string {
  $s = strtolower(trim($s));
  return match ($s) {
    'pending', 'approved', 'rejected' => $s,
    default => 'pending',
  };
}

function kyc_api_risk_from_row(array $row): string {
  // Simple placeholder: rejected => high, pending => medium, approved => low.
  $st = strtolower((string)($row['status'] ?? ''));
  if ($st === 'approved') return 'Low';
  if ($st === 'rejected') return 'High';
  return 'Medium';
}

function kyc_api_time_ago(?string $dt): string {
  if (!is_string($dt) || $dt === '') return '';
  try {
    $t = new DateTimeImmutable($dt);
  } catch (Throwable) {
    return '';
  }
  $now = new DateTimeImmutable('now');
  $diff = $now->getTimestamp() - $t->getTimestamp();
  if ($diff < 0) $diff = 0;
  if ($diff < 60) return 'just now';
  if ($diff < 3600) return (int)floor($diff / 60) . ' min ago';
  if ($diff < 86400) return (int)floor($diff / 3600) . ' hrs ago';
  return (int)floor($diff / 86400) . ' days ago';
}

try {
  $db = user_db_connect((string)($GLOBALS['DB_HOST'] ?? 'localhost'), (string)($GLOBALS['DB_USER'] ?? 'root'), (string)($GLOBALS['DB_PASS'] ?? ''), (string)($GLOBALS['DB_NAME'] ?? 'crypterra'));
} catch (Throwable) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'db']);
  exit;
}

ensure_kyc_requests_table($db);
global $DB_NAME;
$schema = (string)$DB_NAME;
ensure_users_kyc_columns($db, $schema);

if ($method === 'GET') {
  if ($action === 'stats') {
    $stats = [
      'total' => 0,
      'pending' => 0,
      'approved' => 0,
      'rejected' => 0,
    ];
    if ($r = @$db->query("SELECT LOWER(status) AS s, COUNT(*) AS c FROM kyc_requests GROUP BY LOWER(status)")) {
      while ($row = $r->fetch_assoc()) {
        $s = (string)($row['s'] ?? '');
        $c = (int)($row['c'] ?? 0);
        if ($s === 'pending') $stats['pending'] = $c;
        if ($s === 'approved') $stats['approved'] = $c;
        if ($s === 'rejected') $stats['rejected'] = $c;
        $stats['total'] += $c;
      }
      $r->free();
    }
    $db->close();
    echo json_encode(['ok' => true, 'stats' => $stats], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'feed') {
    $limit = max(1, min(30, kyc_api_int($_GET['limit'] ?? 8, 8)));

    // Combine "submitted" + "reviewed" into a single chronological feed.
    $sql = "
      (SELECT k.id, k.user_id, u.email, k.full_name, 'submitted' AS event_type, k.submitted_at AS event_at, '' AS reason
       FROM kyc_requests k
       LEFT JOIN users u ON u.id = k.user_id)
      UNION ALL
      (SELECT k.id, k.user_id, u.email, k.full_name,
              CASE WHEN LOWER(k.status)='approved' THEN 'approved' ELSE 'rejected' END AS event_type,
              k.reviewed_at AS event_at,
              k.rejection_reason AS reason
       FROM kyc_requests k
       LEFT JOIN users u ON u.id = k.user_id
       WHERE k.reviewed_at IS NOT NULL)
      ORDER BY event_at DESC
      LIMIT " . (int)$limit;

    $items = [];
    if ($r = @$db->query($sql)) {
      while ($row = $r->fetch_assoc()) {
        $type = (string)($row['event_type'] ?? '');
        $email = (string)($row['email'] ?? '');
        $name = (string)($row['full_name'] ?? '');
        $label = $email !== '' ? $email : ($name !== '' ? $name : ('User #' . (int)($row['user_id'] ?? 0)));
        $at = (string)($row['event_at'] ?? '');
        $reason = (string)($row['reason'] ?? '');
        $items[] = [
          'type' => $type,
          'user' => $label,
          'time_ago' => kyc_api_time_ago($at),
          'reason' => $reason,
        ];
      }
      $r->free();
    }
    $db->close();
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($action === 'activity') {
    $limit = max(1, min(30, kyc_api_int($_GET['limit'] ?? 10, 10)));
    $sql = "SELECT k.id, k.user_id, u.email, u.full_name, k.status, k.rejection_reason, k.reviewed_at, k.reviewed_by_admin_id
            FROM kyc_requests k
            LEFT JOIN users u ON u.id = k.user_id
            WHERE k.reviewed_at IS NOT NULL
            ORDER BY k.reviewed_at DESC, k.id DESC
            LIMIT " . (int)$limit;
    $items = [];
    if ($r = @$db->query($sql)) {
      while ($row = $r->fetch_assoc()) {
        $st = strtolower((string)($row['status'] ?? ''));
        $email = (string)($row['email'] ?? '');
        $name = (string)($row['full_name'] ?? '');
        $label = $email !== '' ? $email : ($name !== '' ? $name : ('User #' . (int)($row['user_id'] ?? 0)));
        $reason = (string)($row['rejection_reason'] ?? '');
        $at = (string)($row['reviewed_at'] ?? '');
        $adminId = (int)($row['reviewed_by_admin_id'] ?? 0);
        $items[] = [
          'status' => $st !== '' ? $st : 'reviewed',
          'user' => $label,
          'reason' => $reason,
          'admin_id' => $adminId,
          'time_ago' => kyc_api_time_ago($at),
        ];
      }
      $r->free();
    }
    $db->close();
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES);
    exit;
  }

  $page = max(1, kyc_api_int($_GET['page'] ?? 1, 1));
  $perPage = max(1, min(100, kyc_api_int($_GET['per_page'] ?? 50, 50)));
  $q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
  $status = isset($_GET['status']) && is_string($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
  $sort = isset($_GET['sort']) && is_string($_GET['sort']) ? strtolower(trim($_GET['sort'])) : 'newest';

  $where = '1=1';
  $params = [];
  $types = '';

  if ($status !== '' && $status !== 'all') {
    $status = kyc_api_status_norm($status);
    $where .= ' AND LOWER(k.status) = ?';
    $params[] = $status;
    $types .= 's';
  }

  if ($q !== '') {
    $like = '%' . $q . '%';
    $where .= ' AND (u.email LIKE ? OR u.full_name LIKE ? OR k.full_name LIKE ? OR k.id_number LIKE ? OR CAST(k.user_id AS CHAR) LIKE ?)';
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
  }

  $order = ($sort === 'oldest') ? 'k.submitted_at ASC, k.id ASC' : 'k.submitted_at DESC, k.id DESC';

  $countSql = "SELECT COUNT(*) AS c
               FROM kyc_requests k
               LEFT JOIN users u ON u.id = k.user_id
               WHERE {$where}";
  $total = 0;
  if ($st = $db->prepare($countSql)) {
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $st->store_result();
    $c = 0;
    $st->bind_result($c);
    $st->fetch();
    $st->free_result();
    $st->close();
    $total = (int)$c;
  }

  $offset = ($page - 1) * $perPage;
  $sql = "SELECT
            k.id, k.user_id, k.full_name AS kyc_full_name, k.country, k.nationality, k.id_number, k.submitted_at, k.status, k.rejection_reason,
            k.doc_id_path, k.proof_address_path, k.selfie_path,
            u.full_name AS user_full_name, u.email
          FROM kyc_requests k
          LEFT JOIN users u ON u.id = k.user_id
          WHERE {$where}
          ORDER BY {$order}
          LIMIT {$perPage} OFFSET {$offset}";
  $items = [];
  if ($st = $db->prepare($sql)) {
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    if ($res) {
      while ($row = $res->fetch_assoc()) {
        $items[] = [
          'id' => (int)($row['id'] ?? 0),
          'user_id' => (int)($row['user_id'] ?? 0),
          'user' => (string)($row['email'] ?? ('user#' . (int)($row['user_id'] ?? 0))),
          'fullName' => (string)($row['kyc_full_name'] ?? ($row['user_full_name'] ?? '')),
          'country' => (string)($row['country'] ?? ''),
          'documentType' => 'Government ID',
          'submissionDate' => (string)($row['submitted_at'] ?? ''),
          'status' => ucfirst(strtolower((string)($row['status'] ?? 'pending'))),
          'riskLevel' => kyc_api_risk_from_row($row),
          'rejection_reason' => (string)($row['rejection_reason'] ?? ''),
          'documents' => [
            'id' => (string)($row['doc_id_path'] ?? ''),
            'address' => (string)($row['proof_address_path'] ?? ''),
            'selfie' => (string)($row['selfie_path'] ?? ''),
          ],
        ];
      }
      $res->free();
    }
    $st->close();
  }

  $db->close();
  echo json_encode([
    'ok' => true,
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
    'items' => $items,
  ], JSON_UNESCAPED_SLASHES);
  exit;
}

if ($method !== 'POST') {
  $db->close();
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'method']);
  exit;
}

$raw = file_get_contents('php://input');
$json = is_string($raw) ? json_decode($raw, true) : null;
$input = is_array($json) ? $json : $_POST;

$action = isset($input['action']) && is_string($input['action']) ? $input['action'] : '';
$csrf = isset($input['csrf_token']) && is_string($input['csrf_token']) ? $input['csrf_token'] : null;
if (!admin_verify_csrf($csrf)) {
  $db->close();
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'csrf']);
  exit;
}

$id = kyc_api_int($input['id'] ?? 0, 0);
if ($id <= 0) {
  $db->close();
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'id']);
  exit;
}

// Resolve user_id for status sync.
$userId = 0;
if ($st = $db->prepare('SELECT user_id FROM kyc_requests WHERE id = ? LIMIT 1')) {
  $st->bind_param('i', $id);
  $st->execute();
  $st->store_result();
  $st->bind_result($userId);
  $st->fetch();
  $st->free_result();
  $st->close();
}
if ($userId <= 0) {
  $db->close();
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'not_found']);
  exit;
}

$adminId = isset($GLOBALS['ADMIN_SESSION_ID']) ? (int)$GLOBALS['ADMIN_SESSION_ID'] : 0;

if ($action === 'approve') {
  $st = $db->prepare('UPDATE kyc_requests SET status = ?, rejection_reason = "", reviewed_at = NOW(), reviewed_by_admin_id = ? WHERE id = ?');
  if (!$st) {
    $db->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db']);
    exit;
  }
  $status = 'approved';
  $st->bind_param('sii', $status, $adminId, $id);
  $ok = (bool)@$st->execute();
  $st->close();
  if ($ok) {
    user_update_kyc_status($db, $schema, $userId, 'approved');
  }
  $db->close();
  echo json_encode(['ok' => $ok]);
  exit;
}

if ($action === 'reject') {
  $reason = isset($input['reason']) && is_string($input['reason']) ? trim($input['reason']) : 'Rejected';
  $reason = mb_substr($reason, 0, 255);
  $st = $db->prepare('UPDATE kyc_requests SET status = ?, rejection_reason = ?, reviewed_at = NOW(), reviewed_by_admin_id = ? WHERE id = ?');
  if (!$st) {
    $db->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db']);
    exit;
  }
  $status = 'rejected';
  $st->bind_param('ssii', $status, $reason, $adminId, $id);
  $ok = (bool)@$st->execute();
  $st->close();
  if ($ok) {
    user_update_kyc_status($db, $schema, $userId, 'rejected');
  }
  $db->close();
  echo json_encode(['ok' => $ok]);
  exit;
}

$db->close();
http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'unknown_action']);

