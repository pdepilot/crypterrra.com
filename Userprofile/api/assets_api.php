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

/** @var string $DB_HOST */
/** @var string $DB_USER */
/** @var string $DB_PASS */
/** @var string $DB_NAME */
global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;

try {
  $db = user_db_connect((string)$DB_HOST, (string)$DB_USER, (string)$DB_PASS, (string)$DB_NAME);
  ensure_asset_portfolio_schema($db);

  $assets = [];
  $coingeckoIds = [];

  $sql = <<<SQL
SELECT c.asset_id, c.name, c.symbol, c.network, c.coingecko_id, c.icon_url, c.gas_asset_id,
       COALESCE(b.balance, 0) AS balance,
       COALESCE(c.is_nft, 0) AS is_nft,
       c.sort_order
FROM asset_catalog c
LEFT JOIN user_asset_balances b
  ON b.asset_id = c.asset_id AND b.user_id = ?
WHERE c.is_active = 1
ORDER BY c.sort_order ASC, c.asset_id ASC
SQL;

  $stmt = $db->prepare($sql);
  if (!$stmt) {
    throw new RuntimeException('prepare');
  }
  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
      $cg = (string)($row['coingecko_id'] ?? '');
      if ($cg !== '') {
        $coingeckoIds[$cg] = true;
      }
      $assets[] = [
        'asset_id' => (string)($row['asset_id'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'symbol' => (string)($row['symbol'] ?? ''),
        'network' => (string)($row['network'] ?? ''),
        'balance' => (string)($row['balance'] ?? '0'),
        'coingecko_id' => $cg,
        'icon_url' => (string)($row['icon_url'] ?? ''),
        'gas_asset_id' => isset($row['gas_asset_id']) ? (string)$row['gas_asset_id'] : '',
        'is_nft' => ((int)($row['is_nft'] ?? 0)) === 1,
        'sort_order' => (int)($row['sort_order'] ?? 100),
      ];
    }
    $res->free();
  }
  $stmt->close();

  $quotes = asset_market_get_quotes($db, array_keys($coingeckoIds), 60);

  $out = [];
  foreach ($assets as $a) {
    $cg = $a['coingecko_id'];
    $price = isset($quotes[$cg]) ? (float)$quotes[$cg]['price_usd'] : 0.0;
    if ($price <= 0.0 && ($cg === 'tether' || $cg === 'usd-coin' || $cg === 'dai')) {
      $price = 1.0;
    }
    $chg = isset($quotes[$cg]) ? (float)$quotes[$cg]['change_24h_pct'] : 0.0;
    $bal = (float)$a['balance'];
    $valueUsd = $bal * $price;

    $icon = '';
    if (isset($a['icon_url']) && is_string($a['icon_url']) && trim($a['icon_url']) !== '') {
      $icon = trim($a['icon_url']);
    }

    $isNft = (bool)($a['is_nft'] ?? false);
    if (!$isNft) {
      $aid = strtolower((string)($a['asset_id'] ?? ''));
      $nm = strtolower((string)($a['name'] ?? ''));
      $nw = strtolower((string)($a['network'] ?? ''));
      if (
        str_contains($nw, 'nft')
        || str_contains($nm, 'nft')
        || str_starts_with($aid, 'nft')
      ) {
        $isNft = true;
      }
    }

    $out[] = [
      'id' => $a['asset_id'],
      'name' => $a['name'],
      'symbol' => $a['symbol'],
      'network' => $a['network'],
      'balance' => $a['balance'],
      'price_usd' => $price,
      'change_24h_pct' => $chg,
      'value_usd' => $valueUsd,
      'icon_url' => $icon,
      'gas_asset_id' => $a['gas_asset_id'],
      'is_nft' => $isNft,
      '_sort_order' => (int)($a['sort_order'] ?? 100),
    ];
  }

  $mainRows = [];
  $nftRows = [];
  foreach ($out as $row) {
    if (!empty($row['is_nft'])) {
      $nftRows[] = $row;
    } else {
      $mainRows[] = $row;
    }
  }

  usort(
    $mainRows,
    static function (array $a, array $b): int {
      $ida = strtolower((string)($a['id'] ?? ''));
      $idb = strtolower((string)($b['id'] ?? ''));
      $pin = static function (string $id): int {
        if ($id === 'btc') {
          return 0;
        }
        if ($id === 'eth') {
          return 1;
        }
        return 50;
      };
      $pa = $pin($ida);
      $pb = $pin($idb);
      if ($pa !== $pb) {
        return $pa <=> $pb;
      }
      $sa = (int)($a['_sort_order'] ?? 100);
      $sb = (int)($b['_sort_order'] ?? 100);
      if ($sa !== $sb) {
        return $sa <=> $sb;
      }
      return strcmp($ida, $idb);
    }
  );

  foreach ($mainRows as &$r) {
    unset($r['_sort_order']);
  }
  unset($r);
  foreach ($nftRows as &$r) {
    unset($r['_sort_order']);
  }
  unset($r);

  $out = array_merge($mainRows, $nftRows);

  $portfolioValue = getPortfolioValue($userId);
  $db->close();

  echo json_encode(
    [
      'ok' => true,
      'assets' => $out,
      'portfolio_value_usd' => $portfolioValue,
      'ts' => time(),
    ],
    JSON_UNESCAPED_SLASHES
  );
} catch (Throwable $e) {
  error_log('[assets_api] ' . $e->getMessage());
  if (isset($db) && $db instanceof mysqli) {
    @$db->close();
  }
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'server'], JSON_UNESCAPED_SLASHES);
}

