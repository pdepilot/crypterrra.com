<?php
/**
 * One-off generator: run from CLI to rebuild kyc_country_nationality_data.php
 *   php build_kyc_country_data.php
 *
 * Place these next to this script before running:
 *   _iso_countries.json — https://raw.githubusercontent.com/lukes/ISO-3166-Countries-with-Regional-Codes/master/all/all.json
 *   _mledoze_countries.json — https://raw.githubusercontent.com/mledoze/countries/master/dist/countries.json
 */
declare(strict_types=1);

$base = __DIR__;
$lukesPath = $base . '/_iso_countries.json';
$mledozePath = $base . '/_mledoze_countries.json';
$outPath = $base . '/kyc_country_nationality_data.php';

if (!is_readable($lukesPath) || !is_readable($mledozePath)) {
  fwrite(STDERR, "Missing source JSON files.\n");
  exit(1);
}

$lukes = json_decode((string)file_get_contents($lukesPath), true);
if (!is_array($lukes)) {
  fwrite(STDERR, "Invalid lukes JSON.\n");
  exit(1);
}

$mledoze = json_decode((string)file_get_contents($mledozePath), true);
if (!is_array($mledoze)) {
  fwrite(STDERR, "Invalid mledoze JSON.\n");
  exit(1);
}

$countries = [];
foreach ($lukes as $row) {
  if (!is_array($row)) {
    continue;
  }
  $code = isset($row['alpha-2']) && is_string($row['alpha-2']) ? strtoupper(trim($row['alpha-2'])) : '';
  $name = isset($row['name']) && is_string($row['name']) ? trim($row['name']) : '';
  if ($code === '' || $name === '') {
    continue;
  }
  $countries[] = ['code' => $code, 'name' => $name];
}

usort($countries, static function (array $a, array $b): int {
  return strcasecmp($a['name'], $b['name']);
});

$demonymsByCode = [];
foreach ($mledoze as $row) {
  if (!is_array($row)) {
    continue;
  }
  $code = isset($row['cca2']) && is_string($row['cca2']) ? strtoupper(trim($row['cca2'])) : '';
  if ($code === '') {
    continue;
  }
  $eng = isset($row['demonyms']['eng']) && is_array($row['demonyms']['eng']) ? $row['demonyms']['eng'] : null;
  if ($eng === null) {
    continue;
  }
  $m = isset($eng['m']) && is_string($eng['m']) ? trim($eng['m']) : '';
  $f = isset($eng['f']) && is_string($eng['f']) ? trim($eng['f']) : '';
  $demo = $m !== '' ? $m : $f;
  if ($demo === '') {
    continue;
  }
  $demonymsByCode[$code] = $demo;
}

$nationalities = array_values(array_unique(array_values($demonymsByCode)));
natcasesort($nationalities);
$nationalities = array_values($nationalities);

$exportCountries = var_export($countries, true);
$exportNationalities = var_export($nationalities, true);

$php = <<<PHP
<?php
declare(strict_types=1);

/**
 * ISO 3166-1 country names (lukes/ISO list) and English demonyms (mledoze/countries).
 * Regenerate with: php build_kyc_country_data.php
 */

/** @return list<array{code: string, name: string}> */
function kyc_countries_sorted(): array {
  static \$cached = null;
  if (\$cached === null) {
    \$cached = {$exportCountries};
  }
  return \$cached;
}

/** @return list<string> Unique English nationality/demonym labels, sorted */
function kyc_nationalities_sorted(): array {
  static \$cached = null;
  if (\$cached === null) {
    \$cached = {$exportNationalities};
  }
  return \$cached;
}

/** Map legacy 2-letter country codes from the old KYC form to ISO English country names */
function kyc_country_prefill_aliases(): array {
  return [
    'US' => 'United States of America',
    'UK' => 'United Kingdom of Great Britain and Northern Ireland',
    'GB' => 'United Kingdom of Great Britain and Northern Ireland',
  ];
}

/** Map legacy nationality option values (old form used ISO-like codes) to demonyms */
function kyc_nationality_prefill_aliases(): array {
  return [
    'US' => 'American',
    'UK' => 'British',
    'GB' => 'British',
    'CA' => 'Canadian',
    'AU' => 'Australian',
    'DE' => 'German',
    'FR' => 'French',
    'JP' => 'Japanese',
    'SG' => 'Singaporean',
  ];
}

function kyc_normalize_country_prefill(string \$stored): string {
  \$s = trim(\$stored);
  if (\$s === '') {
    return '';
  }
  if (strlen(\$s) === 2) {
    \$up = strtoupper(\$s);
    \$aliases = kyc_country_prefill_aliases();
    if (isset(\$aliases[\$up])) {
      return \$aliases[\$up];
    }
  }
  return \$s;
}

function kyc_normalize_nationality_prefill(string \$stored): string {
  \$s = trim(\$stored);
  if (\$s === '') {
    return '';
  }
  if (strlen(\$s) === 2) {
    \$up = strtoupper(\$s);
    \$aliases = kyc_nationality_prefill_aliases();
    if (isset(\$aliases[\$up])) {
      return \$aliases[\$up];
    }
  }
  return \$s;
}

PHP;

file_put_contents($outPath, $php);
echo "Wrote {$outPath}\n";
