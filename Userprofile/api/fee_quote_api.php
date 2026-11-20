<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/user_auth.php';
require_once dirname(__DIR__) . '/includes/user_db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'auth'], JSON_UNESCAPED_SLASHES);
  exit;
}

$assetId = isset($_GET['asset_id']) && is_string($_GET['asset_id']) ? strtolower(trim($_GET['asset_id'])) : '';
$network = isset($_GET['network']) && is_string($_GET['network']) ? trim($_GET['network']) : '';
$amount = isset($_GET['amount']) && is_numeric($_GET['amount']) ? (float)$_GET['amount'] : 0.0;

if ($assetId === '' || $amount <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'input'], JSON_UNESCAPED_SLASHES);
  exit;
}

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  ensure_network_fee_rules_schema($db);

  $q = user_quote_network_fees($db, $userId, $assetId, $network, $amount);
  if (!($q['ok'] ?? false)) {
    $db->close();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string)($q['error'] ?? 'fee')], JSON_UNESCAPED_SLASHES);
    exit;
  }

  $gasAssetId = (string)($q['gas_asset_id'] ?? '');
  $gasFee = (float)($q['gas_fee_amount'] ?? 0.0);
  $assetFee = (float)($q['asset_fee_amount'] ?? 0.0);
  $reserve = (float)($q['min_gas_reserve'] ?? 0.0);

  $assetBal = (float)getUserAssetBalance($userId, $assetId);
  $gasBal = $gasAssetId !== '' ? (float)getUserAssetBalance($userId, $gasAssetId) : 0.0;

  $totalAssetDebit = $amount + $assetFee;

  $okAsset = $assetBal + 1e-12 >= $totalAssetDebit;
  $okGas = true;
  if ($gasAssetId === $assetId) {
    $okGas = $assetBal + 1e-12 >= ($totalAssetDebit + $gasFee + $reserve);
  } else {
    $okGas = $gasBal + 1e-12 >= ($gasFee + $reserve);
  }

  $db->close();
  echo json_encode(
    [
      'ok' => true,
      'network_code' => (string)($q['network_code'] ?? ''),
      'asset_id' => (string)($q['asset_id'] ?? ''),
      'asset_symbol' => (string)($q['asset_symbol'] ?? ''),
      'gas_asset_id' => $gasAssetId,
      'gas_symbol' => (string)($q['gas_symbol'] ?? strtoupper($gasAssetId)),
      'gas_fee_amount' => $gasFee,
      'asset_fee_amount' => $assetFee,
      'total_asset_debit' => $totalAssetDebit,
      'min_gas_reserve' => $reserve,
      'balances' => [
        'asset' => $assetBal,
        'gas' => $gasBal,
      ],
      'can_proceed' => $okAsset && $okGas,
      'ok_asset' => $okAsset,
      'ok_gas' => $okGas,
      'ts' => time(),
    ],
    JSON_UNESCAPED_SLASHES
  );
} catch (Throwable $e) {
  error_log('[fee_quote_api] ' . $e->getMessage());
  if (isset($db) && $db instanceof mysqli) {
    @$db->close();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server'], JSON_UNESCAPED_SLASHES);
}

