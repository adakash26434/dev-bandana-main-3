<?php
/**
 * Simple site service expiry — Superadmin मात्र म्याद बदल्न सक्छ।
 *
 * स्रोत सत्य: site_settings.site_license_valid_until_bs (बि.सं. Latin Y-m-d)।
 * DB मirror (छान्दा/सेभ गर्दा अपडेट): site_license_valid_until_ad (ई.सं. Latin),
 * site_license_valid_until_bs_np (बि.सं. नेपाली अंक)। खाली = म्याद बन्द।
 *
 * म्याद सकियो: काठमाडौंको आजको बि.सं. > अन्तिम बि.सं. दिन → site_license_expired() === true
 */
declare(strict_types=1);

require_once __DIR__ . '/nepali-bs-convert.php';

if (!function_exists('site_license_sync_mirror_settings')) {
    /** बि.सं. Latin बाट ई.सं. र बि.सं. नेपाली अंक सेटिङमा लेख्छ (खाली BS = सबै खाली) */
    function site_license_sync_mirror_settings(string $bsLatin): void {
        if (!function_exists('updateSetting')) {
            return;
        }
        if ($bsLatin === '') {
            updateSetting('site_license_valid_until_ad', '');
            updateSetting('site_license_valid_until_bs_np', '');
            return;
        }
        $ad = nepali_bs_to_ad_string($bsLatin) ?? '';
        updateSetting('site_license_valid_until_ad', $ad);
        updateSetting('site_license_valid_until_bs_np', nepali_latin_digits_to_devanagari($bsLatin));
    }
}

if (!function_exists('site_license_until_bs')) {
    function site_license_until_bs(): string {
        if (!function_exists('getSetting')) {
            return '';
        }
        return trim((string) getSetting('site_license_valid_until_bs', ''));
    }
}

if (!function_exists('site_license_until_ad')) {
    /** ई.सं. अन्तिम दिन (Latin) — DB मा बच्छ; खाली भए BS बाट निकालिन्छ */
    function site_license_until_ad(): string {
        $bs = site_license_until_bs();
        if ($bs === '') {
            return '';
        }
        if (!function_exists('getSetting')) {
            return nepali_bs_to_ad_string($bs) ?? '';
        }
        $stored = trim((string) getSetting('site_license_valid_until_ad', ''));
        if ($stored !== '') {
            return $stored;
        }
        return nepali_bs_to_ad_string($bs) ?? '';
    }
}

if (!function_exists('site_license_until_bs_np')) {
    /** बि.सं. नेपाली अंकमा (उदा. २०८३-०१-१९) — DB मा बच्छ */
    function site_license_until_bs_np(): string {
        $bs = site_license_until_bs();
        if ($bs === '') {
            return '';
        }
        if (!function_exists('getSetting')) {
            return nepali_latin_digits_to_devanagari($bs);
        }
        $stored = trim((string) getSetting('site_license_valid_until_bs_np', ''));
        if ($stored !== '') {
            return $stored;
        }
        return nepali_latin_digits_to_devanagari($bs);
    }
}

if (!function_exists('site_license_is_configured')) {
    function site_license_is_configured(): bool {
        $d = site_license_until_bs();
        if ($d === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) !== 1) {
            return false;
        }
        $p = nepali_parse_bs_ymd($d);
        return $p !== null && nepali_bs_date_valid($p);
    }
}

if (!function_exists('site_license_expired')) {
    /**
     * म्याद सकियो (Expired): काठमाडौं आजको बि.सं. क्यालेन्डर दिन > सेट गरिएको अन्तिम बि.सं. दिन।
     * अन्तिम दिनसम्म वैध; त्यसपछिको दिनदेखि true।
     */
    function site_license_expired(): bool {
        if (!site_license_is_configured()) {
            return false;
        }
        $until = site_license_until_bs();
        $today = nepali_kathmandu_today_bs();
        return nepali_bs_ymd_compare($today, $until) === 1;
    }
}

if (!function_exists('site_license_login_blocked_for_user')) {
    function site_license_login_blocked_for_user(array $userRow): bool {
        if (!site_license_expired()) {
            return false;
        }
        return !admin_db_role_is_superadmin($userRow['role'] ?? '');
    }
}

if (!function_exists('site_license_public_guard')) {
    /**
     * सार्वजनिक / member — म्याद सकिएमा पूर्ण पृष्ठ सन्देश र exit।
     */
    function site_license_public_guard(): void {
        if (!function_exists('site_license_expired') || !site_license_expired()) {
            return;
        }
        $site = function_exists('getSetting') ? getSetting('site_name', 'सहकारी') : 'सहकारी';
        $siteH = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
        $bs = function_exists('site_license_until_bs') ? site_license_until_bs() : '';
        $bsNp = function_exists('site_license_until_bs_np') ? site_license_until_bs_np() : '';
        $ad = function_exists('site_license_until_ad') ? site_license_until_ad() : '';
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $svgIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="16" rx="2" stroke="#d97706" stroke-width="1.5"/><path d="M8 2.5v4M16 2.5v4M3.5 9.5h17" stroke="#d97706" stroke-width="1.5" stroke-linecap="round"/><path d="M9 15l6-6M15 15l-6-6" stroke="#dc2626" stroke-width="1.8" stroke-linecap="round"/></svg>';
        echo '<!DOCTYPE html><html lang="ne"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow">'
            . '<title>सेवा अस्थायी उपलब्ध छैन</title>'
            . '<style>:root{--p:#166534;--p2:#15803d;--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--card:#fff;--amber:#d97706;--amberbg:#fffbeb;}*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Devanagari","Mukta",sans-serif;background:linear-gradient(165deg,#ecfdf5 0%,#f0fdf4 28%,#f8fafc 72%);color:var(--ink);display:flex;align-items:center;justify-content:center;padding:clamp(16px,4vw,32px);}'
            . '.wrap{width:100%;max-width:540px}.card{background:var(--card);border-radius:20px;box-shadow:0 4px 6px -1px rgba(15,23,42,.06),0 20px 50px -12px rgba(22,101,52,.12);border:1px solid var(--line);overflow:hidden}'
            . '.top{background:linear-gradient(135deg,#166534 0%,#15803d 100%);color:#fff;padding:22px 24px 20px;text-align:center}.top h1{margin:0;font-size:clamp(1.15rem,3.5vw,1.35rem);font-weight:700;letter-spacing:-.02em;line-height:1.35}'
            . '.top p{margin:10px 0 0;opacity:.92;font-size:.9rem;line-height:1.55}.ico{display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:50%;background:var(--amberbg);border:1px solid #fde68a;margin:0 auto 14px;box-shadow:0 2px 8px rgba(217,119,6,.15)}'
            . '.bd{padding:22px 24px 26px}.lead{margin:0 0 18px;text-align:center;font-size:.95rem;line-height:1.65;color:#334155}.status{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px;margin-bottom:18px}'
            . '.pill{font-size:.78rem;font-weight:600;padding:6px 12px;border-radius:999px;background:#fef3c7;color:#92400e;border:1px solid #fcd34d}.dates{background:#f8fafc;border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin-bottom:20px}'
            . '.dates dt{margin:0 0 4px;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}.dates dd{margin:0 0 14px;font-size:.95rem;color:var(--ink);font-weight:600}.dates dd:last-child{margin-bottom:0}'
            . '.dates .sub{font-weight:500;color:var(--muted);font-size:.85rem}.foot{margin:0;text-align:center;font-size:.85rem;line-height:1.65;color:var(--muted)}.foot strong{color:#475569}</style></head><body>'
            . '<div class="wrap"><div class="card"><div class="top"><div class="ico">' . $svgIcon . '</div><h1>सेवा म्याद सकियो</h1><p><strong style="font-weight:700">' . $siteH . '</strong> को वेबसाइट सेवा हाल अस्थायी रूपमा उपलब्ध छैन।</p></div><div class="bd">'
            . '<p class="lead">यो सन्देश साइट सेवा अवधि समाप्त भएपछि देखाइन्छ। नवीकरण पछि सेवा पुनः सामान्य हुन्छ।</p>'
            . ($bs !== '' ? '<div class="status"><span class="pill">स्थिति / Status: म्याद सकियो · Expired</span></div>'
                . '<dl class="dates"><dt>अन्तिम वैध दिन — बि.सं.</dt><dd>' . htmlspecialchars($bsNp !== '' ? $bsNp : $bs, ENT_QUOTES, 'UTF-8') . ' <span class="sub">(' . htmlspecialchars($bs, ENT_QUOTES, 'UTF-8') . ')</span></dd>'
                . ($ad !== '' ? '<dt>अन्तिम वैध दिन — ई.सं. / AD</dt><dd>' . htmlspecialchars($ad, ENT_QUOTES, 'UTF-8') . '</dd>' : '')
                . '</dl>' : '<div class="status"><span class="pill">स्थिति / Status: म्याद सकियो · Expired</span></div>')
            . '<p class="foot">लाइसेन्स नवीकरण वा प्राविधिक सहयोगका लागि कृपया <strong>विक्रेता / प्राविधिक टोली</strong> लाई सम्पर्क गर्नुहोस्।</p></div></div></div></body></html>';
        exit;
    }
}
