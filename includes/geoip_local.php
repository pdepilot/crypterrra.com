<?php
declare(strict_types=1);

/**
 * Local GeoIP using MaxMind GeoLite2 Country database (.mmdb).
 *
 * Setup:
 * - Run: composer install
 * - Download GeoLite2-Country.mmdb and place it at: /data/GeoLite2-Country.mmdb
 *
 * This helper is best-effort and will return empty values if unavailable.
 *
 * @return array{iso:string, name:string}
 */
function geoip_local_country(?string $ip): array {
  $ip = is_string($ip) ? trim($ip) : '';
  if ($ip === '') {
    return ['iso' => '', 'name' => ''];
  }

  // Handle common local/dev addresses quickly.
  if ($ip === '127.0.0.1' || $ip === '::1') {
    return ['iso' => '', 'name' => ''];
  }

  $autoload = dirname(__DIR__) . '/vendor/autoload.php';
  if (!is_file($autoload)) {
    return ['iso' => '', 'name' => ''];
  }
  require_once $autoload;

  $dbPath = dirname(__DIR__) . '/data/GeoLite2-Country.mmdb';
  if (!is_file($dbPath)) {
    return ['iso' => '', 'name' => ''];
  }

  try {
    if (!class_exists('\\MaxMind\\Db\\Reader')) {
      return ['iso' => '', 'name' => ''];
    }
    static $reader = null;
    if ($reader === null) {
      $reader = new \MaxMind\Db\Reader($dbPath);
    }
    $record = $reader->get($ip);
    if (!is_array($record)) {
      return ['iso' => '', 'name' => ''];
    }
    $iso = '';
    $name = '';
    if (isset($record['country']) && is_array($record['country'])) {
      $iso = isset($record['country']['iso_code']) && is_string($record['country']['iso_code'])
        ? $record['country']['iso_code']
        : '';
      if (isset($record['country']['names']) && is_array($record['country']['names'])) {
        $name = isset($record['country']['names']['en']) && is_string($record['country']['names']['en'])
          ? $record['country']['names']['en']
          : '';
      }
    }
    if ($iso === '' && isset($record['registered_country']) && is_array($record['registered_country'])) {
      $iso = isset($record['registered_country']['iso_code']) && is_string($record['registered_country']['iso_code'])
        ? $record['registered_country']['iso_code']
        : $iso;
      if ($name === '' && isset($record['registered_country']['names']) && is_array($record['registered_country']['names'])) {
        $name = isset($record['registered_country']['names']['en']) && is_string($record['registered_country']['names']['en'])
          ? $record['registered_country']['names']['en']
          : $name;
      }
    }

    return ['iso' => strtoupper($iso), 'name' => $name];
  } catch (Throwable) {
    return ['iso' => '', 'name' => ''];
  }
}

