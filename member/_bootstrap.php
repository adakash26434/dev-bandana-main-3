<?php
/**
 * ════════════════════════════════════════════════════════════
 * MEMBER PANEL BOOTSTRAP — Global Error Guard (v2)
 * ════════════════════════════════════════════════════════════
 * सबै member/*.php files को सुरुमा यो file include गरिन्छ।
 * के गर्छ?
 *   - PHP fatal error → user-friendly Nepali error page (white-screen रोक्छ)
 *   - Unhandled exceptions लाई gracefully handle गर्छ
 *   - Production मा error details hide, log मा मात्र लेख्छ
 *   - Development mode मा detailed error देखाउँछ (?debug=1)
 * ════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../includes/config.php';
if (function_exists('site_license_public_guard')) {
    site_license_public_guard();
}
require_once __DIR__ . '/../includes/member-auth.php';
if (file_exists(__DIR__ . '/../includes/panel-uniform.php')) {
    require_once __DIR__ . '/../includes/panel-uniform.php';
}

/**
 * memberHeadAssets() — Member panel pages को <head> मा एकै style assets emit गर्छ।
 * Public र Admin जस्तै font/color/spacing tokens पाउन सबै member/*.php मा यो call गर्नुहोस्।
 */
if (!function_exists('memberHeadAssets')) {
    function memberHeadAssets(): void {
        $base = defined('SITE_URL') ? SITE_URL : '/';
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@300;400;500;600;700&display=swap" rel="stylesheet">' . "\n";
        echo '<link rel="stylesheet" href="' . htmlspecialchars($base, ENT_QUOTES) . 'assets/css/design-tokens.css?v=5">' . "\n";
        echo '<link rel="stylesheet" href="' . htmlspecialchars($base, ENT_QUOTES) . 'assets/css/universal.css?v=2">' . "\n";
        echo '<link rel="stylesheet" href="' . htmlspecialchars($base, ENT_QUOTES) . 'assets/css/v9-mobile-fix.css?v=9.7">' . "\n";
        $cv = __DIR__ . '/../assets/css/_color-vars.php';
        if (file_exists($cv)) {
            include $cv;
        }
    }
}

/* Production safe — errors display गर्दैन, log मा मात्र लेख्छ */
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);

/* Friendly fatal handler — white screen कहिल्यै नदेखियोस् */
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    /* Output पहिले नै केहि गइसकेको छ भने rewrite गर्न सकिन्न */
    if (headers_sent()) {
        echo "\n<!-- Fatal: see server error log -->\n";
        return;
    }
    @http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    $hostLc = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $allowDebugUrl = str_starts_with($hostLc, '127.0.0.1')
        || str_starts_with($hostLc, 'localhost')
        || str_starts_with($hostLc, '[::1]');
    $isDebug = $allowDebugUrl && isset($_GET['debug']) && (string) $_GET['debug'] === '1';
    $msg = $isDebug
        ? htmlspecialchars($err['message'] . ' @ ' . basename($err['file']) . ':' . $err['line'])
        : 'अप्रत्याशित त्रुटि भयो। कार्यालयमा सम्पर्क गर्नुहोस्।';
    $home = defined('SITE_URL') ? SITE_URL : '/';
    error_log('[member-panel-fatal] ' . $err['message'] . ' @ ' . $err['file'] . ':' . $err['line']);

    echo '<!DOCTYPE html><html lang="ne"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>त्रुटि — Member Portal</title>';
    echo '<style>
        body{margin:0;background:linear-gradient(135deg,#fef2f2,#fee2e2);min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:"Mukta","Noto Sans Devanagari","Segoe UI",sans-serif;padding:20px}
        .err-box{max-width:480px;background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.1);padding:32px;text-align:center}
        .err-icon{width:72px;height:72px;background:#fee2e2;color:#b91c1c;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 18px}
        h1{color:#1f2937;font-size:1.25rem;margin:0 0 10px}
        p{color:#6b7280;font-size:.9rem;line-height:1.6;margin:0 0 22px}
        .err-detail{background:#fef2f2;color:#991b1b;padding:10px 14px;border-radius:8px;font-family:monospace;font-size:.78rem;margin:14px 0;text-align:left;border:1px solid #fecaca;word-break:break-all}
        .err-btn{display:inline-block;background:linear-gradient(135deg,var(--primary-color),var(--primary-light));color:#fff;text-decoration:none;padding:11px 26px;border-radius:10px;font-weight:600;font-size:.88rem}
        .err-btn:hover{opacity:.92}
    </style></head><body>';
    echo '<div class="err-box">';
    echo '<div class="err-icon">⚠</div>';
    echo '<h1>केहि गलत भयो</h1>';
    echo '<p>हाम्रो team लाई स्वतः सूचित गरियो। केहि समय पछि पुनः प्रयास गर्नुहोस्।</p>';
    if ($isDebug) echo '<div class="err-detail">' . $msg . '</div>';
    echo '<a class="err-btn" href="' . htmlspecialchars($home) . 'member/login.php">लगिन पृष्ठमा फर्किनुहोस्</a>';
    echo '</div></body></html>';
});

/* Uncaught exception handler */
set_exception_handler(function ($e) {
    error_log('[member-panel-exception] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (headers_sent()) return;
    @http_response_code(500);
    /* Trigger shutdown handler बाट uniform error page देखाउन */
    trigger_error($e->getMessage(), E_USER_ERROR);
});
