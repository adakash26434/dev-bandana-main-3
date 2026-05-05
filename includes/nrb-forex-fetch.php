<?php
/**
 * NRB Forex Rate Fetcher — नेपाल राष्ट्र बैंकको विनिमय दर
 *
 * Nepal Rastra Bank को Public API बाट Real-time Exchange Rates fetch गर्छ।
 * Data 6 घण्टासम्म cache गरिन्छ — हरेक page load मा API call हुँदैन।
 *
 * API Source: https://www.nrb.org.np/api/forex/v1/rates
 * Usage: $forexData = nrbFetchForex();
 */

function nrbFetchForex(): array {
    /* Cache file path — 6 घण्टा cache */
    $cacheDir  = ROOT_PATH . 'cache/';
    $cacheFile = $cacheDir . 'nrb_forex_' . date('Y-m-d') . '.json';
    $cacheTTL  = 6 * 3600; /* 6 hours in seconds */

    /* ── Cache हेर्नुहोस् ── */
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        $cached = @json_decode(file_get_contents($cacheFile), true);
        if ($cached && !empty($cached['rates'])) {
            return $cached;
        }
    }

    /* ── NRB API call गर्नुहोस् ── */
    $today = date('Y-m-d');
    $apiUrl = "https://www.nrb.org.np/api/forex/v1/rates?per=100&page=1&from={$today}&to={$today}";

    $ctx = stream_context_create([
        'http' => [
            'timeout'    => 8,
            'method'     => 'GET',
            'user_agent' => 'Mozilla/5.0 (compatible; CooperativeWebsite/1.0)',
            'header'     => "Accept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer'      => false, /* XAMPP/local server SSL issue bypass */
            'verify_peer_name' => false,
        ],
    ]);

    $raw = @file_get_contents($apiUrl, false, $ctx);

    if ($raw === false) {
        /* cURL fallback */
        if (function_exists('curl_init')) {
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'Mozilla/5.0',
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
    }

    if ($raw) {
        $json = json_decode($raw, true);
        if (isset($json['data']['payload'][0]['rates'])) {
            $payload     = $json['data']['payload'][0];
            $publishedOn = $payload['date'] ?? $today;
            $rates       = [];

            /* मुद्राको flag code map — currency ISO3 → flag country code */
            $flagMap = [
                'USD' => 'us', 'EUR' => 'eu', 'GBP' => 'gb', 'AUD' => 'au',
                'CAD' => 'ca', 'CHF' => 'ch', 'JPY' => 'jp', 'CNY' => 'cn',
                'INR' => 'in', 'AED' => 'ae', 'MYR' => 'my', 'SAR' => 'sa',
                'QAR' => 'qa', 'KRW' => 'kr', 'SGD' => 'sg', 'THB' => 'th',
                'KWD' => 'kw', 'SEK' => 'se', 'DKK' => 'dk', 'HKD' => 'hk',
                'NOK' => 'no', 'PKR' => 'pk',
            ];

            foreach ($payload['rates'] as $r) {
                $iso = $r['currency']['iso3'] ?? '';
                $rates[] = [
                    'iso'    => $iso,
                    'name'   => $r['currency']['name'] ?? $iso,
                    'unit'   => $r['currency']['unit'] ?? 1,
                    'buy'    => number_format((float)($r['buy'] ?? 0), 2),
                    'sell'   => number_format((float)($r['sell'] ?? 0), 2),
                    'flag'   => $flagMap[$iso] ?? strtolower(substr($iso, 0, 2)),
                ];
            }

            $result = [
                'source'       => 'nrb_live',
                'published_on' => $publishedOn,
                'fetched_at'   => date('Y-m-d H:i:s'),
                'rates'        => $rates,
            ];

            /* Cache मा सेभ गर्नुहोस् */
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
            @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
            return $result;
        }
    }

    /* ── Fallback: पुरानो cache वा static data ── */
    /* पुरानो कुनै cache छ भने त्यही दिनुहोस् */
    $anyCache = glob($cacheDir . 'nrb_forex_*.json');
    if ($anyCache) {
        rsort($anyCache); /* newest first */
        $old = @json_decode(file_get_contents($anyCache[0]), true);
        if ($old && !empty($old['rates'])) {
            $old['source'] = 'nrb_cached';
            return $old;
        }
    }

    /* Final fallback — static data (API र cache दुवै fail हुँदा) */
    return nrbStaticFallback();
}

/* Static fallback — API available नभएमा */
function nrbStaticFallback(): array {
    return [
        'source'       => 'static_fallback',
        'published_on' => date('Y-m-d'),
        'fetched_at'   => date('Y-m-d H:i:s'),
        'rates'        => [
            ['iso'=>'USD','name'=>'U.S. Dollar',          'unit'=>1,   'buy'=>'133.45','sell'=>'133.95','flag'=>'us'],
            ['iso'=>'EUR','name'=>'European Euro',         'unit'=>1,   'buy'=>'145.20','sell'=>'145.75','flag'=>'eu'],
            ['iso'=>'GBP','name'=>'UK Pound Sterling',     'unit'=>1,   'buy'=>'168.50','sell'=>'169.15','flag'=>'gb'],
            ['iso'=>'AUD','name'=>'Australian Dollar',     'unit'=>1,   'buy'=>'87.30', 'sell'=>'87.65', 'flag'=>'au'],
            ['iso'=>'CAD','name'=>'Canadian Dollar',       'unit'=>1,   'buy'=>'98.45', 'sell'=>'98.85', 'flag'=>'ca'],
            ['iso'=>'CHF','name'=>'Swiss Franc',           'unit'=>1,   'buy'=>'150.80','sell'=>'151.35','flag'=>'ch'],
            ['iso'=>'JPY','name'=>'Japanese Yen',          'unit'=>100, 'buy'=>'89.20', 'sell'=>'89.55', 'flag'=>'jp'],
            ['iso'=>'CNY','name'=>'Chinese Yuan',          'unit'=>1,   'buy'=>'18.35', 'sell'=>'18.45', 'flag'=>'cn'],
            ['iso'=>'INR','name'=>'Indian Rupee',          'unit'=>100, 'buy'=>'160.00','sell'=>'160.15','flag'=>'in'],
            ['iso'=>'AED','name'=>'UAE Dirham',            'unit'=>1,   'buy'=>'36.35', 'sell'=>'36.50', 'flag'=>'ae'],
            ['iso'=>'MYR','name'=>'Malaysian Ringgit',     'unit'=>1,   'buy'=>'30.85', 'sell'=>'30.95', 'flag'=>'my'],
            ['iso'=>'SAR','name'=>'Saudi Riyal',           'unit'=>1,   'buy'=>'35.55', 'sell'=>'35.70', 'flag'=>'sa'],
            ['iso'=>'QAR','name'=>'Qatari Riyal',          'unit'=>1,   'buy'=>'36.65', 'sell'=>'36.80', 'flag'=>'qa'],
            ['iso'=>'KRW','name'=>'South Korean Won',      'unit'=>100, 'buy'=>'9.85',  'sell'=>'9.90',  'flag'=>'kr'],
            ['iso'=>'SGD','name'=>'Singapore Dollar',      'unit'=>1,   'buy'=>'99.25', 'sell'=>'99.80', 'flag'=>'sg'],
            ['iso'=>'KWD','name'=>'Kuwaiti Dinar',         'unit'=>1,   'buy'=>'435.00','sell'=>'437.00','flag'=>'kw'],
        ],
    ];
}
