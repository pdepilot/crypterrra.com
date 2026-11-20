<?php
declare(strict_types=1);

/**
 * ISO 3166-1 country names (lukes/ISO list) and English demonyms (mledoze/countries).
 * Regenerate with: php build_kyc_country_data.php
 */

/** @return list<array{code: string, name: string}> */
function kyc_countries_sorted(): array {
  static $cached = null;
  if ($cached === null) {
    $cached = array (
  0 => 
  array (
    'code' => 'AF',
    'name' => 'Afghanistan',
  ),
  1 => 
  array (
    'code' => 'AL',
    'name' => 'Albania',
  ),
  2 => 
  array (
    'code' => 'DZ',
    'name' => 'Algeria',
  ),
  3 => 
  array (
    'code' => 'AS',
    'name' => 'American Samoa',
  ),
  4 => 
  array (
    'code' => 'AD',
    'name' => 'Andorra',
  ),
  5 => 
  array (
    'code' => 'AO',
    'name' => 'Angola',
  ),
  6 => 
  array (
    'code' => 'AI',
    'name' => 'Anguilla',
  ),
  7 => 
  array (
    'code' => 'AQ',
    'name' => 'Antarctica',
  ),
  8 => 
  array (
    'code' => 'AG',
    'name' => 'Antigua and Barbuda',
  ),
  9 => 
  array (
    'code' => 'AR',
    'name' => 'Argentina',
  ),
  10 => 
  array (
    'code' => 'AM',
    'name' => 'Armenia',
  ),
  11 => 
  array (
    'code' => 'AW',
    'name' => 'Aruba',
  ),
  12 => 
  array (
    'code' => 'AU',
    'name' => 'Australia',
  ),
  13 => 
  array (
    'code' => 'AT',
    'name' => 'Austria',
  ),
  14 => 
  array (
    'code' => 'AZ',
    'name' => 'Azerbaijan',
  ),
  15 => 
  array (
    'code' => 'BS',
    'name' => 'Bahamas',
  ),
  16 => 
  array (
    'code' => 'BH',
    'name' => 'Bahrain',
  ),
  17 => 
  array (
    'code' => 'BD',
    'name' => 'Bangladesh',
  ),
  18 => 
  array (
    'code' => 'BB',
    'name' => 'Barbados',
  ),
  19 => 
  array (
    'code' => 'BY',
    'name' => 'Belarus',
  ),
  20 => 
  array (
    'code' => 'BE',
    'name' => 'Belgium',
  ),
  21 => 
  array (
    'code' => 'BZ',
    'name' => 'Belize',
  ),
  22 => 
  array (
    'code' => 'BJ',
    'name' => 'Benin',
  ),
  23 => 
  array (
    'code' => 'BM',
    'name' => 'Bermuda',
  ),
  24 => 
  array (
    'code' => 'BT',
    'name' => 'Bhutan',
  ),
  25 => 
  array (
    'code' => 'BO',
    'name' => 'Bolivia, Plurinational State of',
  ),
  26 => 
  array (
    'code' => 'BQ',
    'name' => 'Bonaire, Sint Eustatius and Saba',
  ),
  27 => 
  array (
    'code' => 'BA',
    'name' => 'Bosnia and Herzegovina',
  ),
  28 => 
  array (
    'code' => 'BW',
    'name' => 'Botswana',
  ),
  29 => 
  array (
    'code' => 'BV',
    'name' => 'Bouvet Island',
  ),
  30 => 
  array (
    'code' => 'BR',
    'name' => 'Brazil',
  ),
  31 => 
  array (
    'code' => 'IO',
    'name' => 'British Indian Ocean Territory',
  ),
  32 => 
  array (
    'code' => 'BN',
    'name' => 'Brunei Darussalam',
  ),
  33 => 
  array (
    'code' => 'BG',
    'name' => 'Bulgaria',
  ),
  34 => 
  array (
    'code' => 'BF',
    'name' => 'Burkina Faso',
  ),
  35 => 
  array (
    'code' => 'BI',
    'name' => 'Burundi',
  ),
  36 => 
  array (
    'code' => 'CV',
    'name' => 'Cabo Verde',
  ),
  37 => 
  array (
    'code' => 'KH',
    'name' => 'Cambodia',
  ),
  38 => 
  array (
    'code' => 'CM',
    'name' => 'Cameroon',
  ),
  39 => 
  array (
    'code' => 'CA',
    'name' => 'Canada',
  ),
  40 => 
  array (
    'code' => 'KY',
    'name' => 'Cayman Islands',
  ),
  41 => 
  array (
    'code' => 'CF',
    'name' => 'Central African Republic',
  ),
  42 => 
  array (
    'code' => 'TD',
    'name' => 'Chad',
  ),
  43 => 
  array (
    'code' => 'CL',
    'name' => 'Chile',
  ),
  44 => 
  array (
    'code' => 'CN',
    'name' => 'China',
  ),
  45 => 
  array (
    'code' => 'CX',
    'name' => 'Christmas Island',
  ),
  46 => 
  array (
    'code' => 'CC',
    'name' => 'Cocos (Keeling) Islands',
  ),
  47 => 
  array (
    'code' => 'CO',
    'name' => 'Colombia',
  ),
  48 => 
  array (
    'code' => 'KM',
    'name' => 'Comoros',
  ),
  49 => 
  array (
    'code' => 'CG',
    'name' => 'Congo',
  ),
  50 => 
  array (
    'code' => 'CD',
    'name' => 'Congo, Democratic Republic of the',
  ),
  51 => 
  array (
    'code' => 'CK',
    'name' => 'Cook Islands',
  ),
  52 => 
  array (
    'code' => 'CR',
    'name' => 'Costa Rica',
  ),
  53 => 
  array (
    'code' => 'HR',
    'name' => 'Croatia',
  ),
  54 => 
  array (
    'code' => 'CU',
    'name' => 'Cuba',
  ),
  55 => 
  array (
    'code' => 'CW',
    'name' => 'Curaçao',
  ),
  56 => 
  array (
    'code' => 'CY',
    'name' => 'Cyprus',
  ),
  57 => 
  array (
    'code' => 'CZ',
    'name' => 'Czechia',
  ),
  58 => 
  array (
    'code' => 'CI',
    'name' => 'Côte d\'Ivoire',
  ),
  59 => 
  array (
    'code' => 'DK',
    'name' => 'Denmark',
  ),
  60 => 
  array (
    'code' => 'DJ',
    'name' => 'Djibouti',
  ),
  61 => 
  array (
    'code' => 'DM',
    'name' => 'Dominica',
  ),
  62 => 
  array (
    'code' => 'DO',
    'name' => 'Dominican Republic',
  ),
  63 => 
  array (
    'code' => 'EC',
    'name' => 'Ecuador',
  ),
  64 => 
  array (
    'code' => 'EG',
    'name' => 'Egypt',
  ),
  65 => 
  array (
    'code' => 'SV',
    'name' => 'El Salvador',
  ),
  66 => 
  array (
    'code' => 'GQ',
    'name' => 'Equatorial Guinea',
  ),
  67 => 
  array (
    'code' => 'ER',
    'name' => 'Eritrea',
  ),
  68 => 
  array (
    'code' => 'EE',
    'name' => 'Estonia',
  ),
  69 => 
  array (
    'code' => 'SZ',
    'name' => 'Eswatini',
  ),
  70 => 
  array (
    'code' => 'ET',
    'name' => 'Ethiopia',
  ),
  71 => 
  array (
    'code' => 'FK',
    'name' => 'Falkland Islands (Malvinas)',
  ),
  72 => 
  array (
    'code' => 'FO',
    'name' => 'Faroe Islands',
  ),
  73 => 
  array (
    'code' => 'FJ',
    'name' => 'Fiji',
  ),
  74 => 
  array (
    'code' => 'FI',
    'name' => 'Finland',
  ),
  75 => 
  array (
    'code' => 'FR',
    'name' => 'France',
  ),
  76 => 
  array (
    'code' => 'GF',
    'name' => 'French Guiana',
  ),
  77 => 
  array (
    'code' => 'PF',
    'name' => 'French Polynesia',
  ),
  78 => 
  array (
    'code' => 'TF',
    'name' => 'French Southern Territories',
  ),
  79 => 
  array (
    'code' => 'GA',
    'name' => 'Gabon',
  ),
  80 => 
  array (
    'code' => 'GM',
    'name' => 'Gambia',
  ),
  81 => 
  array (
    'code' => 'GE',
    'name' => 'Georgia',
  ),
  82 => 
  array (
    'code' => 'DE',
    'name' => 'Germany',
  ),
  83 => 
  array (
    'code' => 'GH',
    'name' => 'Ghana',
  ),
  84 => 
  array (
    'code' => 'GI',
    'name' => 'Gibraltar',
  ),
  85 => 
  array (
    'code' => 'GR',
    'name' => 'Greece',
  ),
  86 => 
  array (
    'code' => 'GL',
    'name' => 'Greenland',
  ),
  87 => 
  array (
    'code' => 'GD',
    'name' => 'Grenada',
  ),
  88 => 
  array (
    'code' => 'GP',
    'name' => 'Guadeloupe',
  ),
  89 => 
  array (
    'code' => 'GU',
    'name' => 'Guam',
  ),
  90 => 
  array (
    'code' => 'GT',
    'name' => 'Guatemala',
  ),
  91 => 
  array (
    'code' => 'GG',
    'name' => 'Guernsey',
  ),
  92 => 
  array (
    'code' => 'GN',
    'name' => 'Guinea',
  ),
  93 => 
  array (
    'code' => 'GW',
    'name' => 'Guinea-Bissau',
  ),
  94 => 
  array (
    'code' => 'GY',
    'name' => 'Guyana',
  ),
  95 => 
  array (
    'code' => 'HT',
    'name' => 'Haiti',
  ),
  96 => 
  array (
    'code' => 'HM',
    'name' => 'Heard Island and McDonald Islands',
  ),
  97 => 
  array (
    'code' => 'VA',
    'name' => 'Holy See',
  ),
  98 => 
  array (
    'code' => 'HN',
    'name' => 'Honduras',
  ),
  99 => 
  array (
    'code' => 'HK',
    'name' => 'Hong Kong',
  ),
  100 => 
  array (
    'code' => 'HU',
    'name' => 'Hungary',
  ),
  101 => 
  array (
    'code' => 'IS',
    'name' => 'Iceland',
  ),
  102 => 
  array (
    'code' => 'IN',
    'name' => 'India',
  ),
  103 => 
  array (
    'code' => 'ID',
    'name' => 'Indonesia',
  ),
  104 => 
  array (
    'code' => 'IR',
    'name' => 'Iran, Islamic Republic of',
  ),
  105 => 
  array (
    'code' => 'IQ',
    'name' => 'Iraq',
  ),
  106 => 
  array (
    'code' => 'IE',
    'name' => 'Ireland',
  ),
  107 => 
  array (
    'code' => 'IM',
    'name' => 'Isle of Man',
  ),
  108 => 
  array (
    'code' => 'IL',
    'name' => 'Israel',
  ),
  109 => 
  array (
    'code' => 'IT',
    'name' => 'Italy',
  ),
  110 => 
  array (
    'code' => 'JM',
    'name' => 'Jamaica',
  ),
  111 => 
  array (
    'code' => 'JP',
    'name' => 'Japan',
  ),
  112 => 
  array (
    'code' => 'JE',
    'name' => 'Jersey',
  ),
  113 => 
  array (
    'code' => 'JO',
    'name' => 'Jordan',
  ),
  114 => 
  array (
    'code' => 'KZ',
    'name' => 'Kazakhstan',
  ),
  115 => 
  array (
    'code' => 'KE',
    'name' => 'Kenya',
  ),
  116 => 
  array (
    'code' => 'KI',
    'name' => 'Kiribati',
  ),
  117 => 
  array (
    'code' => 'KP',
    'name' => 'Korea, Democratic People\'s Republic of',
  ),
  118 => 
  array (
    'code' => 'KR',
    'name' => 'Korea, Republic of',
  ),
  119 => 
  array (
    'code' => 'KW',
    'name' => 'Kuwait',
  ),
  120 => 
  array (
    'code' => 'KG',
    'name' => 'Kyrgyzstan',
  ),
  121 => 
  array (
    'code' => 'LA',
    'name' => 'Lao People\'s Democratic Republic',
  ),
  122 => 
  array (
    'code' => 'LV',
    'name' => 'Latvia',
  ),
  123 => 
  array (
    'code' => 'LB',
    'name' => 'Lebanon',
  ),
  124 => 
  array (
    'code' => 'LS',
    'name' => 'Lesotho',
  ),
  125 => 
  array (
    'code' => 'LR',
    'name' => 'Liberia',
  ),
  126 => 
  array (
    'code' => 'LY',
    'name' => 'Libya',
  ),
  127 => 
  array (
    'code' => 'LI',
    'name' => 'Liechtenstein',
  ),
  128 => 
  array (
    'code' => 'LT',
    'name' => 'Lithuania',
  ),
  129 => 
  array (
    'code' => 'LU',
    'name' => 'Luxembourg',
  ),
  130 => 
  array (
    'code' => 'MO',
    'name' => 'Macao',
  ),
  131 => 
  array (
    'code' => 'MG',
    'name' => 'Madagascar',
  ),
  132 => 
  array (
    'code' => 'MW',
    'name' => 'Malawi',
  ),
  133 => 
  array (
    'code' => 'MY',
    'name' => 'Malaysia',
  ),
  134 => 
  array (
    'code' => 'MV',
    'name' => 'Maldives',
  ),
  135 => 
  array (
    'code' => 'ML',
    'name' => 'Mali',
  ),
  136 => 
  array (
    'code' => 'MT',
    'name' => 'Malta',
  ),
  137 => 
  array (
    'code' => 'MH',
    'name' => 'Marshall Islands',
  ),
  138 => 
  array (
    'code' => 'MQ',
    'name' => 'Martinique',
  ),
  139 => 
  array (
    'code' => 'MR',
    'name' => 'Mauritania',
  ),
  140 => 
  array (
    'code' => 'MU',
    'name' => 'Mauritius',
  ),
  141 => 
  array (
    'code' => 'YT',
    'name' => 'Mayotte',
  ),
  142 => 
  array (
    'code' => 'MX',
    'name' => 'Mexico',
  ),
  143 => 
  array (
    'code' => 'FM',
    'name' => 'Micronesia, Federated States of',
  ),
  144 => 
  array (
    'code' => 'MD',
    'name' => 'Moldova, Republic of',
  ),
  145 => 
  array (
    'code' => 'MC',
    'name' => 'Monaco',
  ),
  146 => 
  array (
    'code' => 'MN',
    'name' => 'Mongolia',
  ),
  147 => 
  array (
    'code' => 'ME',
    'name' => 'Montenegro',
  ),
  148 => 
  array (
    'code' => 'MS',
    'name' => 'Montserrat',
  ),
  149 => 
  array (
    'code' => 'MA',
    'name' => 'Morocco',
  ),
  150 => 
  array (
    'code' => 'MZ',
    'name' => 'Mozambique',
  ),
  151 => 
  array (
    'code' => 'MM',
    'name' => 'Myanmar',
  ),
  152 => 
  array (
    'code' => 'NA',
    'name' => 'Namibia',
  ),
  153 => 
  array (
    'code' => 'NR',
    'name' => 'Nauru',
  ),
  154 => 
  array (
    'code' => 'NP',
    'name' => 'Nepal',
  ),
  155 => 
  array (
    'code' => 'NL',
    'name' => 'Netherlands, Kingdom of the',
  ),
  156 => 
  array (
    'code' => 'NC',
    'name' => 'New Caledonia',
  ),
  157 => 
  array (
    'code' => 'NZ',
    'name' => 'New Zealand',
  ),
  158 => 
  array (
    'code' => 'NI',
    'name' => 'Nicaragua',
  ),
  159 => 
  array (
    'code' => 'NE',
    'name' => 'Niger',
  ),
  160 => 
  array (
    'code' => 'NG',
    'name' => 'Nigeria',
  ),
  161 => 
  array (
    'code' => 'NU',
    'name' => 'Niue',
  ),
  162 => 
  array (
    'code' => 'NF',
    'name' => 'Norfolk Island',
  ),
  163 => 
  array (
    'code' => 'MK',
    'name' => 'North Macedonia',
  ),
  164 => 
  array (
    'code' => 'MP',
    'name' => 'Northern Mariana Islands',
  ),
  165 => 
  array (
    'code' => 'NO',
    'name' => 'Norway',
  ),
  166 => 
  array (
    'code' => 'OM',
    'name' => 'Oman',
  ),
  167 => 
  array (
    'code' => 'PK',
    'name' => 'Pakistan',
  ),
  168 => 
  array (
    'code' => 'PW',
    'name' => 'Palau',
  ),
  169 => 
  array (
    'code' => 'PS',
    'name' => 'Palestine, State of',
  ),
  170 => 
  array (
    'code' => 'PA',
    'name' => 'Panama',
  ),
  171 => 
  array (
    'code' => 'PG',
    'name' => 'Papua New Guinea',
  ),
  172 => 
  array (
    'code' => 'PY',
    'name' => 'Paraguay',
  ),
  173 => 
  array (
    'code' => 'PE',
    'name' => 'Peru',
  ),
  174 => 
  array (
    'code' => 'PH',
    'name' => 'Philippines',
  ),
  175 => 
  array (
    'code' => 'PN',
    'name' => 'Pitcairn',
  ),
  176 => 
  array (
    'code' => 'PL',
    'name' => 'Poland',
  ),
  177 => 
  array (
    'code' => 'PT',
    'name' => 'Portugal',
  ),
  178 => 
  array (
    'code' => 'PR',
    'name' => 'Puerto Rico',
  ),
  179 => 
  array (
    'code' => 'QA',
    'name' => 'Qatar',
  ),
  180 => 
  array (
    'code' => 'RO',
    'name' => 'Romania',
  ),
  181 => 
  array (
    'code' => 'RU',
    'name' => 'Russian Federation',
  ),
  182 => 
  array (
    'code' => 'RW',
    'name' => 'Rwanda',
  ),
  183 => 
  array (
    'code' => 'RE',
    'name' => 'Réunion',
  ),
  184 => 
  array (
    'code' => 'BL',
    'name' => 'Saint Barthélemy',
  ),
  185 => 
  array (
    'code' => 'SH',
    'name' => 'Saint Helena, Ascension and Tristan da Cunha',
  ),
  186 => 
  array (
    'code' => 'KN',
    'name' => 'Saint Kitts and Nevis',
  ),
  187 => 
  array (
    'code' => 'LC',
    'name' => 'Saint Lucia',
  ),
  188 => 
  array (
    'code' => 'MF',
    'name' => 'Saint Martin (French part)',
  ),
  189 => 
  array (
    'code' => 'PM',
    'name' => 'Saint Pierre and Miquelon',
  ),
  190 => 
  array (
    'code' => 'VC',
    'name' => 'Saint Vincent and the Grenadines',
  ),
  191 => 
  array (
    'code' => 'WS',
    'name' => 'Samoa',
  ),
  192 => 
  array (
    'code' => 'SM',
    'name' => 'San Marino',
  ),
  193 => 
  array (
    'code' => 'ST',
    'name' => 'Sao Tome and Principe',
  ),
  194 => 
  array (
    'code' => 'SA',
    'name' => 'Saudi Arabia',
  ),
  195 => 
  array (
    'code' => 'SN',
    'name' => 'Senegal',
  ),
  196 => 
  array (
    'code' => 'RS',
    'name' => 'Serbia',
  ),
  197 => 
  array (
    'code' => 'SC',
    'name' => 'Seychelles',
  ),
  198 => 
  array (
    'code' => 'SL',
    'name' => 'Sierra Leone',
  ),
  199 => 
  array (
    'code' => 'SG',
    'name' => 'Singapore',
  ),
  200 => 
  array (
    'code' => 'SX',
    'name' => 'Sint Maarten (Dutch part)',
  ),
  201 => 
  array (
    'code' => 'SK',
    'name' => 'Slovakia',
  ),
  202 => 
  array (
    'code' => 'SI',
    'name' => 'Slovenia',
  ),
  203 => 
  array (
    'code' => 'SB',
    'name' => 'Solomon Islands',
  ),
  204 => 
  array (
    'code' => 'SO',
    'name' => 'Somalia',
  ),
  205 => 
  array (
    'code' => 'ZA',
    'name' => 'South Africa',
  ),
  206 => 
  array (
    'code' => 'GS',
    'name' => 'South Georgia and the South Sandwich Islands',
  ),
  207 => 
  array (
    'code' => 'SS',
    'name' => 'South Sudan',
  ),
  208 => 
  array (
    'code' => 'ES',
    'name' => 'Spain',
  ),
  209 => 
  array (
    'code' => 'LK',
    'name' => 'Sri Lanka',
  ),
  210 => 
  array (
    'code' => 'SD',
    'name' => 'Sudan',
  ),
  211 => 
  array (
    'code' => 'SR',
    'name' => 'Suriname',
  ),
  212 => 
  array (
    'code' => 'SJ',
    'name' => 'Svalbard and Jan Mayen',
  ),
  213 => 
  array (
    'code' => 'SE',
    'name' => 'Sweden',
  ),
  214 => 
  array (
    'code' => 'CH',
    'name' => 'Switzerland',
  ),
  215 => 
  array (
    'code' => 'SY',
    'name' => 'Syrian Arab Republic',
  ),
  216 => 
  array (
    'code' => 'TW',
    'name' => 'Taiwan, Province of China',
  ),
  217 => 
  array (
    'code' => 'TJ',
    'name' => 'Tajikistan',
  ),
  218 => 
  array (
    'code' => 'TZ',
    'name' => 'Tanzania, United Republic of',
  ),
  219 => 
  array (
    'code' => 'TH',
    'name' => 'Thailand',
  ),
  220 => 
  array (
    'code' => 'TL',
    'name' => 'Timor-Leste',
  ),
  221 => 
  array (
    'code' => 'TG',
    'name' => 'Togo',
  ),
  222 => 
  array (
    'code' => 'TK',
    'name' => 'Tokelau',
  ),
  223 => 
  array (
    'code' => 'TO',
    'name' => 'Tonga',
  ),
  224 => 
  array (
    'code' => 'TT',
    'name' => 'Trinidad and Tobago',
  ),
  225 => 
  array (
    'code' => 'TN',
    'name' => 'Tunisia',
  ),
  226 => 
  array (
    'code' => 'TM',
    'name' => 'Turkmenistan',
  ),
  227 => 
  array (
    'code' => 'TC',
    'name' => 'Turks and Caicos Islands',
  ),
  228 => 
  array (
    'code' => 'TV',
    'name' => 'Tuvalu',
  ),
  229 => 
  array (
    'code' => 'TR',
    'name' => 'Türkiye',
  ),
  230 => 
  array (
    'code' => 'UG',
    'name' => 'Uganda',
  ),
  231 => 
  array (
    'code' => 'UA',
    'name' => 'Ukraine',
  ),
  232 => 
  array (
    'code' => 'AE',
    'name' => 'United Arab Emirates',
  ),
  233 => 
  array (
    'code' => 'GB',
    'name' => 'United Kingdom of Great Britain and Northern Ireland',
  ),
  234 => 
  array (
    'code' => 'UM',
    'name' => 'United States Minor Outlying Islands',
  ),
  235 => 
  array (
    'code' => 'US',
    'name' => 'United States of America',
  ),
  236 => 
  array (
    'code' => 'UY',
    'name' => 'Uruguay',
  ),
  237 => 
  array (
    'code' => 'UZ',
    'name' => 'Uzbekistan',
  ),
  238 => 
  array (
    'code' => 'VU',
    'name' => 'Vanuatu',
  ),
  239 => 
  array (
    'code' => 'VE',
    'name' => 'Venezuela, Bolivarian Republic of',
  ),
  240 => 
  array (
    'code' => 'VN',
    'name' => 'Viet Nam',
  ),
  241 => 
  array (
    'code' => 'VG',
    'name' => 'Virgin Islands (British)',
  ),
  242 => 
  array (
    'code' => 'VI',
    'name' => 'Virgin Islands (U.S.)',
  ),
  243 => 
  array (
    'code' => 'WF',
    'name' => 'Wallis and Futuna',
  ),
  244 => 
  array (
    'code' => 'EH',
    'name' => 'Western Sahara',
  ),
  245 => 
  array (
    'code' => 'YE',
    'name' => 'Yemen',
  ),
  246 => 
  array (
    'code' => 'ZM',
    'name' => 'Zambia',
  ),
  247 => 
  array (
    'code' => 'ZW',
    'name' => 'Zimbabwe',
  ),
  248 => 
  array (
    'code' => 'AX',
    'name' => 'Åland Islands',
  ),
);
  }
  return $cached;
}

/** @return list<string> Unique English nationality/demonym labels, sorted */
function kyc_nationalities_sorted(): array {
  static $cached = null;
  if ($cached === null) {
    $cached = array (
  0 => 'Afghan',
  1 => 'Albanian',
  2 => 'Algerian',
  3 => 'American',
  4 => 'American Islander',
  5 => 'American Samoan',
  6 => 'Andorran',
  7 => 'Angolan',
  8 => 'Anguillian',
  9 => 'Antarctican',
  10 => 'Antiguan, Barbudan',
  11 => 'Argentine',
  12 => 'Armenian',
  13 => 'Aruban',
  14 => 'Australian',
  15 => 'Austrian',
  16 => 'Azerbaijani',
  17 => 'Bahamian',
  18 => 'Bahraini',
  19 => 'Bangladeshi',
  20 => 'Barbadian',
  21 => 'Belarusian',
  22 => 'Belgian',
  23 => 'Belizean',
  24 => 'Beninese',
  25 => 'Bermudian',
  26 => 'Bhutanese',
  27 => 'Bolivian',
  28 => 'Bosnian, Herzegovinian',
  29 => 'Brazilian',
  30 => 'British',
  31 => 'Bruneian',
  32 => 'Bulgarian',
  33 => 'Burkinabe',
  34 => 'Burmese',
  35 => 'Burundian',
  36 => 'Cambodian',
  37 => 'Cameroonian',
  38 => 'Canadian',
  39 => 'Cape Verdian',
  40 => 'Caymanian',
  41 => 'Central African',
  42 => 'Chadian',
  43 => 'Channel Islander',
  44 => 'Chilean',
  45 => 'Chinese',
  46 => 'Christmas Islander',
  47 => 'Cocos Islander',
  48 => 'Colombian',
  49 => 'Comoran',
  50 => 'Congolese',
  51 => 'Cook Islander',
  52 => 'Costa Rican',
  53 => 'Croatian',
  54 => 'Cuban',
  55 => 'Curaçaoan',
  56 => 'Cypriot',
  57 => 'Czech',
  58 => 'Danish',
  59 => 'Djibouti',
  60 => 'Dominican',
  61 => 'Dutch',
  62 => 'East Timorese',
  63 => 'Ecuadorean',
  64 => 'Egyptian',
  65 => 'Emirati',
  66 => 'Equatorial Guinean',
  67 => 'Eritrean',
  68 => 'Estonian',
  69 => 'Ethiopian',
  70 => 'Falkland Islander',
  71 => 'Faroese',
  72 => 'Fijian',
  73 => 'Filipino',
  74 => 'Finnish',
  75 => 'French',
  76 => 'French Polynesian',
  77 => 'Gabonese',
  78 => 'Gambian',
  79 => 'Georgian',
  80 => 'German',
  81 => 'Ghanaian',
  82 => 'Gibraltar',
  83 => 'Greek',
  84 => 'Greenlandic',
  85 => 'Grenadian',
  86 => 'Guadeloupian',
  87 => 'Guamanian',
  88 => 'Guatemalan',
  89 => 'Guianan',
  90 => 'Guinea-Bissauan',
  91 => 'Guinean',
  92 => 'Guyanese',
  93 => 'Haitian',
  94 => 'Heard and McDonald Islander',
  95 => 'Honduran',
  96 => 'Hong Konger',
  97 => 'Hungarian',
  98 => 'I-Kiribati',
  99 => 'Icelander',
  100 => 'Indian',
  101 => 'Indonesian',
  102 => 'Iranian',
  103 => 'Iraqi',
  104 => 'Irish',
  105 => 'Israeli',
  106 => 'Italian',
  107 => 'Ivorian',
  108 => 'Jamaican',
  109 => 'Japanese',
  110 => 'Jordanian',
  111 => 'Kazakhstani',
  112 => 'Kenyan',
  113 => 'Kirghiz',
  114 => 'Kittitian or Nevisian',
  115 => 'Kosovar',
  116 => 'Kuwaiti',
  117 => 'Laotian',
  118 => 'Latvian',
  119 => 'Lebanese',
  120 => 'Liberian',
  121 => 'Libyan',
  122 => 'Liechtensteiner',
  123 => 'Lithuanian',
  124 => 'Luxembourger',
  125 => 'Macanese',
  126 => 'Macedonian',
  127 => 'Mahoran',
  128 => 'Malagasy',
  129 => 'Malawian',
  130 => 'Malaysian',
  131 => 'Maldivan',
  132 => 'Malian',
  133 => 'Maltese',
  134 => 'Manx',
  135 => 'Marshallese',
  136 => 'Martinican',
  137 => 'Mauritanian',
  138 => 'Mauritian',
  139 => 'Mexican',
  140 => 'Micronesian',
  141 => 'Moldovan',
  142 => 'Monegasque',
  143 => 'Mongolian',
  144 => 'Montenegrin',
  145 => 'Montserratian',
  146 => 'Moroccan',
  147 => 'Mosotho',
  148 => 'Motswana',
  149 => 'Mozambican',
  150 => 'Namibian',
  151 => 'Nauruan',
  152 => 'Nepalese',
  153 => 'New Caledonian',
  154 => 'New Zealander',
  155 => 'Ni-Vanuatu',
  156 => 'Nicaraguan',
  157 => 'Nigerian',
  158 => 'Nigerien',
  159 => 'Niuean',
  160 => 'Norfolk Islander',
  161 => 'North Korean',
  162 => 'Norwegian',
  163 => 'Omani',
  164 => 'Pakistani',
  165 => 'Palauan',
  166 => 'Palestinian',
  167 => 'Panamanian',
  168 => 'Papua New Guinean',
  169 => 'Paraguayan',
  170 => 'Peruvian',
  171 => 'Pitcairn Islander',
  172 => 'Polish',
  173 => 'Portuguese',
  174 => 'Puerto Rican',
  175 => 'Qatari',
  176 => 'Romanian',
  177 => 'Russian',
  178 => 'Rwandan',
  179 => 'Réunionese',
  180 => 'Sahrawi',
  181 => 'Saint-Pierrais, Miquelonnais',
  182 => 'Saint Barthélemy Islander',
  183 => 'Saint Helenian',
  184 => 'Saint Lucian',
  185 => 'Saint Martin Islander',
  186 => 'Saint Vincentian',
  187 => 'Salvadoran',
  188 => 'Sammarinese',
  189 => 'Samoan',
  190 => 'Sao Tomean',
  191 => 'Saudi Arabian',
  192 => 'Senegalese',
  193 => 'Serbian',
  194 => 'Seychellois',
  195 => 'Sierra Leonean',
  196 => 'Singaporean',
  197 => 'Slovak',
  198 => 'Slovene',
  199 => 'Solomon Islander',
  200 => 'Somali',
  201 => 'South African',
  202 => 'South Georgian South Sandwich Islander',
  203 => 'South Korean',
  204 => 'South Sudanese',
  205 => 'Spanish',
  206 => 'Sri Lankan',
  207 => 'St. Maartener',
  208 => 'Sudanese',
  209 => 'Surinamer',
  210 => 'Swazi',
  211 => 'Swedish',
  212 => 'Swiss',
  213 => 'Syrian',
  214 => 'Tadzhik',
  215 => 'Taiwanese',
  216 => 'Tanzanian',
  217 => 'Thai',
  218 => 'Togolese',
  219 => 'Tokelauan',
  220 => 'Tongan',
  221 => 'Trinidadian',
  222 => 'Tunisian',
  223 => 'Turkish',
  224 => 'Turkmen',
  225 => 'Turks and Caicos Islander',
  226 => 'Tuvaluan',
  227 => 'Ugandan',
  228 => 'Ukrainian',
  229 => 'Uruguayan',
  230 => 'Uzbekistani',
  231 => 'Vatican',
  232 => 'Venezuelan',
  233 => 'Vietnamese',
  234 => 'Virgin Islander',
  235 => 'Wallis and Futuna Islander',
  236 => 'Yemeni',
  237 => 'Zambian',
  238 => 'Zimbabwean',
  239 => 'Ålandish',
);
  }
  return $cached;
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

function kyc_normalize_country_prefill(string $stored): string {
  $s = trim($stored);
  if ($s === '') {
    return '';
  }
  if (strlen($s) === 2) {
    $up = strtoupper($s);
    $aliases = kyc_country_prefill_aliases();
    if (isset($aliases[$up])) {
      return $aliases[$up];
    }
  }
  return $s;
}

function kyc_normalize_nationality_prefill(string $stored): string {
  $s = trim($stored);
  if ($s === '') {
    return '';
  }
  if (strlen($s) === 2) {
    $up = strtoupper($s);
    $aliases = kyc_nationality_prefill_aliases();
    if (isset($aliases[$up])) {
      return $aliases[$up];
    }
  }
  return $s;
}
