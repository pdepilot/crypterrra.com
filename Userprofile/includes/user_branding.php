<?php
declare(strict_types=1);

require_once __DIR__ . '/user_db.php';

/**
 * Short wallet id for sidebar/header (e.g. 0x7F3...aB8d).
 */
function user_branding_h(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function user_format_wallet_short(string $walletId): string {
  $w = trim($walletId);
  if ($w === '') {
    return '—';
  }
  $len = strlen($w);
  if ($len <= 12) {
    return $w;
  }

  return substr($w, 0, 5) . '...' . substr($w, -4);
}

/**
 * Shared display context for sidebar + top bar (one DB hit per request, cached).
 *
 * @return array{full_name: string, avatar_letter: string, wallet_id: string, wallet_short: string}
 */
function user_branding_context(int $userId): array {
  static $cache = [];

  if ($userId <= 0) {
    return [
      'full_name' => 'User',
      'avatar_letter' => 'U',
      'wallet_id' => '',
      'wallet_short' => '—',
    ];
  }

  if (isset($cache[$userId])) {
    return $cache[$userId];
  }

  $fullName = (string)($_SESSION['user_full_name'] ?? '');
  $walletId = '';

  try {
    /** @var string $DB_HOST */
    /** @var string $DB_USER */
    /** @var string $DB_PASS */
    /** @var string $DB_NAME */
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
    $schema = (string)$DB_NAME;
    ensure_user_dashboard_schema($db, $schema);
    $row = user_fetch_by_id($db, $userId);
    if (is_array($row)) {
      $fullName = (string)($row['full_name'] ?? $fullName);
      $_SESSION['user_full_name'] = $fullName;
      $walletId = is_string($row['wallet_id'] ?? null) ? (string)$row['wallet_id'] : '';
      if ($walletId === '') {
        $walletId = user_ensure_wallet_id($db, $userId);
      }
    }
    $db->close();
  } catch (Throwable $e) {
    error_log('[user_branding] ' . $e->getMessage());
  }

  if ($fullName === '') {
    $fullName = 'User';
  }

  $avatarLetter = 'U';
  $first = function_exists('mb_substr') ? mb_substr($fullName, 0, 1, 'UTF-8') : substr($fullName, 0, 1);
  if (is_string($first) && $first !== '') {
    $avatarLetter = strtoupper($first);
  }

  $cache[$userId] = [
    'full_name' => $fullName,
    'avatar_letter' => $avatarLetter,
    'wallet_id' => $walletId,
    'wallet_short' => user_format_wallet_short($walletId),
  ];

  return $cache[$userId];
}
