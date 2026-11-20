<?php
declare(strict_types=1);

/**
 * Maps recovery phrase + passkey to a registered external wallet address (demo registry).
 * Add more rows in data/wallet_external_registry.json (sha256 fields) or rely on builtins.
 */

function wallet_external_registry_normalize_phrase(string $s): string {
  $s = preg_replace('/\s+/u', ' ', trim($s));
  if (function_exists('mb_strtolower')) {
    return mb_strtolower($s, 'UTF-8');
  }
  return strtolower($s);
}

function wallet_external_registry_path(): string {
  return dirname(__DIR__) . '/data/wallet_external_registry.json';
}

/**
 * @return list<array{phrase_sha256: string, passkey_sha256: string, address: string, chain: string, asset: string, provider_label: string}>
 */
function wallet_external_registry_builtin(): array {
  // Demo credentials (testing only): see Crypterra docs / admin — do not use real funds.
  $phrase = 'crypterra secure link wallet phrase alpha beta gamma delta epsilon zeta omega';
  $passkey = 'TradeSecure2026';
  return [[
    'phrase_sha256' => hash('sha256', wallet_external_registry_normalize_phrase($phrase)),
    'passkey_sha256' => hash('sha256', $passkey),
    'address' => '0x6e608254be0acb503d8855c9a223afcd61631bd0',
    'chain' => 'Ethereum',
    'asset' => 'ETH',
    'provider_label' => 'Registered wallet',
  ]];
}

/**
 * @return list<array{phrase_sha256: string, passkey_sha256: string, address: string, chain: string, asset: string, provider_label: string}>
 */
function wallet_external_registry_load_file(): array {
  $path = wallet_external_registry_path();
  if (!is_readable($path)) {
    return [];
  }
  $raw = @file_get_contents($path);
  if (!is_string($raw) || $raw === '') {
    return [];
  }
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    return [];
  }
  $wallets = $data['wallets'] ?? [];
  if (!is_array($wallets)) {
    return [];
  }
  $out = [];
  foreach ($wallets as $row) {
    if (!is_array($row)) {
      continue;
    }
    $ph = (string)($row['phrase_sha256'] ?? '');
    $pk = (string)($row['passkey_sha256'] ?? '');
    $addr = (string)($row['address'] ?? '');
    if ($ph === '' || $pk === '' || $addr === '') {
      continue;
    }
    $out[] = [
      'phrase_sha256' => $ph,
      'passkey_sha256' => $pk,
      'address' => strtolower($addr),
      'chain' => (string)($row['chain'] ?? 'Ethereum'),
      'asset' => (string)($row['asset'] ?? ''),
      'provider_label' => (string)($row['provider_label'] ?? 'External wallet'),
    ];
  }
  return $out;
}

/**
 * @return list<array{phrase_sha256: string, passkey_sha256: string, address: string, chain: string, asset: string, provider_label: string}>
 */
function wallet_external_registry_all_rows(): array {
  return array_merge(wallet_external_registry_builtin(), wallet_external_registry_load_file());
}

/**
 * Resolve wallet: phrase only, passkey only, or both. When both are sent, any of these
 * may match (same row both, or phrase-only row, or passkey-only row).
 *
 * @return array{address: string, chain: string, asset: string, provider_label: string}|null
 */
function wallet_external_registry_lookup(string $phrase, string $passkey): ?array {
  return wallet_external_registry_try_resolve($phrase, $passkey);
}

/**
 * @return array{address: string, chain: string, asset: string, provider_label: string}|null
 */
function wallet_external_registry_try_resolve(string $phrase, string $passkey): ?array {
  $phrase = trim($phrase);
  $passkey = trim($passkey);
  if ($phrase === '' && $passkey === '') {
    return null;
  }

  $rows = wallet_external_registry_all_rows();
  $hasPhrase = $phrase !== '';
  $hasPasskey = $passkey !== '';
  $pHash = $hasPhrase ? hash('sha256', wallet_external_registry_normalize_phrase($phrase)) : null;
  $kHash = $hasPasskey ? hash('sha256', $passkey) : null;

  if ($hasPhrase && $hasPasskey) {
    foreach ($rows as $row) {
      if (
        hash_equals($row['phrase_sha256'], $pHash)
        && hash_equals($row['passkey_sha256'], $kHash)
      ) {
        return wallet_external_registry_row_out($row);
      }
    }
    foreach ($rows as $row) {
      if (hash_equals($row['phrase_sha256'], $pHash)) {
        return wallet_external_registry_row_out($row);
      }
    }
    foreach ($rows as $row) {
      if (hash_equals($row['passkey_sha256'], $kHash)) {
        return wallet_external_registry_row_out($row);
      }
    }
    return null;
  }
  if ($hasPhrase) {
    foreach ($rows as $row) {
      if (hash_equals($row['phrase_sha256'], $pHash)) {
        return wallet_external_registry_row_out($row);
      }
    }
    return null;
  }
  foreach ($rows as $row) {
    if (hash_equals($row['passkey_sha256'], $kHash)) {
      return wallet_external_registry_row_out($row);
    }
  }
  return null;
}

/**
 * Human-readable reason when try_resolve returns null (phrase/passkey not empty).
 */
function wallet_external_registry_fail_message(string $phrase, string $passkey): string {
  $phrase = trim($phrase);
  $passkey = trim($passkey);
  $rows = wallet_external_registry_all_rows();
  $hasPhrase = $phrase !== '';
  $hasPasskey = $passkey !== '';

  if ($hasPhrase) {
    $pHash = hash('sha256', wallet_external_registry_normalize_phrase($phrase));
    $phraseOk = false;
    foreach ($rows as $row) {
      if (hash_equals($row['phrase_sha256'], $pHash)) {
        $phraseOk = true;
        break;
      }
    }
    if (!$phraseOk) {
      return 'That recovery phrase is not correct or is not registered with Crypterra.';
    }
  }
  if ($hasPasskey) {
    $kHash = hash('sha256', $passkey);
    $pkOk = false;
    foreach ($rows as $row) {
      if (hash_equals($row['passkey_sha256'], $kHash)) {
        $pkOk = true;
        break;
      }
    }
    if (!$pkOk) {
      return 'That passkey is not correct or is not registered with Crypterra.';
    }
  }
  return 'No registered wallet matches the phrase or passkey you entered.';
}

/**
 * @param array{phrase_sha256: string, passkey_sha256: string, address: string, chain: string, asset: string, provider_label: string} $row
 * @return array{address: string, chain: string, asset: string, provider_label: string}
 */
function wallet_external_registry_row_out(array $row): array {
  return [
    'address' => (string)$row['address'],
    'chain' => (string)$row['chain'],
    'asset' => (string)$row['asset'],
    'provider_label' => (string)$row['provider_label'],
  ];
}
