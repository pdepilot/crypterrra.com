<?php
declare(strict_types=1);

/**
 * Key-value store for platform security policy (admin Security Center).
 * Table lives in the same schema as users (crypterra_admin).
 */

function admin_security_ensure_table(mysqli $db): void {
  $sql = "CREATE TABLE IF NOT EXISTS `platform_security_settings` (
    `k` VARCHAR(64) NOT NULL,
    `v` TEXT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`k`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
  @$db->query($sql);
}

function admin_security_defaults(): array {
  return [
    'admin_2fa_enabled' => true,
    'default_user_2fa_enabled' => false,
    'access_session_timeout_min' => 30,
    'max_login_attempts' => 5,
    'withdrawal_limit_usd' => 10000,
    'auto_lock_min' => 15,
    'user_session_timeout_min' => 30,
  ];
}

function admin_get_security_settings(mysqli $db): array {
  admin_security_ensure_table($db);
  $out = admin_security_defaults();

  $stmt = $db->prepare(
    "SELECT k, v FROM platform_security_settings WHERE k IN ('admin_2fa_enabled','default_user_2fa_enabled','access_session_timeout_min','max_login_attempts','withdrawal_limit_usd','auto_lock_min','user_session_timeout_min')"
  );
  if (!$stmt) {
    return $out;
  }
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res) {
    while ($row = $res->fetch_assoc()) {
      $k = isset($row['k']) && is_string($row['k']) ? $row['k'] : '';
      $v = isset($row['v']) && is_string($row['v']) ? $row['v'] : '';
      if ($k === '') {
        continue;
      }
      match ($k) {
        'admin_2fa_enabled' => $out['admin_2fa_enabled'] = $v === '1' || strtolower($v) === 'true',
        'default_user_2fa_enabled' => $out['default_user_2fa_enabled'] = $v === '1' || strtolower($v) === 'true',
        'access_session_timeout_min' => $out['access_session_timeout_min'] = max(5, min(480, (int)$v ?: 30)),
        'max_login_attempts' => $out['max_login_attempts'] = max(1, min(20, (int)$v ?: 5)),
        'withdrawal_limit_usd' => $out['withdrawal_limit_usd'] = max(0, (float)$v),
        'auto_lock_min' => $out['auto_lock_min'] = max(1, min(240, (int)$v ?: 15)),
        'user_session_timeout_min' => $out['user_session_timeout_min'] = max(5, min(480, (int)$v ?: 30)),
        default => null,
      };
    }
    $res->free();
  }
  $stmt->close();
  return $out;
}

function admin_save_security_settings(mysqli $db, array $in): void {
  admin_security_ensure_table($db);
  $d = admin_security_defaults();
  $merged = array_merge($d, $in);

  $pairs = [
    'admin_2fa_enabled' => $merged['admin_2fa_enabled'] ? '1' : '0',
    'default_user_2fa_enabled' => $merged['default_user_2fa_enabled'] ? '1' : '0',
    'access_session_timeout_min' => (string)(int)$merged['access_session_timeout_min'],
    'max_login_attempts' => (string)(int)$merged['max_login_attempts'],
    'withdrawal_limit_usd' => (string)(float)$merged['withdrawal_limit_usd'],
    'auto_lock_min' => (string)(int)$merged['auto_lock_min'],
    'user_session_timeout_min' => (string)(int)$merged['user_session_timeout_min'],
  ];

  $stmt = $db->prepare('INSERT INTO platform_security_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
  if (!$stmt) {
    throw new RuntimeException('prepare failed: ' . $db->error);
  }
  foreach ($pairs as $k => $v) {
    $stmt->bind_param('ss', $k, $v);
    if (!$stmt->execute()) {
      $stmt->close();
      throw new RuntimeException('save failed: ' . $db->error);
    }
  }
  $stmt->close();
}
