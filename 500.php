<?php
/**
 * Custom 500 Server Error Page
 * यो page automatically देखिन्छ जब server error आउँछ।
 * (.htaccess: ErrorDocument 500 /500.php)
 */
http_response_code(500);
if (!function_exists('isEnglish')) {
    function isEnglish(): bool {
        $lang = strtolower((string)($_GET['lang'] ?? $_COOKIE['lang'] ?? 'ne'));
        return $lang === 'en';
    }
}
$_t = static function (string $np, string $en): string {
    return isEnglish() ? $en : $np;
};

/* config.php load नभए पनि standalone देखाउने */
$siteName = $_t('सहकारी संस्था', 'Cooperative');
$siteUrl  = '/';
if (file_exists(__DIR__ . '/includes/config.php')) {
    /* silent — config पनि fail हुन सक्छ */
    try {
        require_once __DIR__ . '/includes/config.php';
        $siteName = defined('SITE_NAME') ? SITE_NAME : (function_exists('getSetting') ? getSetting('site_name', $siteName) : $siteName);
        $siteUrl  = defined('SITE_URL')  ? SITE_URL  : '/';
    } catch (Throwable $t) { /* silent */ }
}
?><!DOCTYPE html>
<html lang="<?php echo isEnglish() ? 'en' : 'ne'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo htmlspecialchars($_t('सर्भर त्रुटि', 'Server Error'), ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { background:#f5f7f5; font-family:'Segoe UI',sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .err-box { max-width:520px; width:100%; background:#fff; border-radius:16px; padding:48px 40px; text-align:center; box-shadow:0 4px 24px rgba(0,0,0,.10); }
    .err-num  { font-size:5rem; font-weight:900; color:#f3f4f6; line-height:1; }
    .err-icon { font-size:2.5rem; color:#dc3545; margin:10px 0; }
    @media(max-width:575px){ .err-box{padding:32px 20px;} }
</style>
</head>
<body>
<div class="err-box">
    <div class="err-num">500</div>
    <div class="err-icon"><i class="fas fa-triangle-exclamation"></i></div>
    <h2 class="fw-bold mb-2">Server Error</h2>
    <p class="text-muted mb-4">
        <?php echo $_t('माफ गर्नुहोस् — केही गलत भयो।', 'Sorry — something went wrong.'); ?><br>
        <small><?php echo $_t('हाम्रो टोली यो समस्या हल गर्दैछ। कृपया थोरै पछि पुनः प्रयास गर्नुहोस्।', 'Our team is working on this issue. Please try again in a while.'); ?></small>
    </p>
    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
        <a href="<?php echo htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary px-4">
            <i class="fas fa-home me-2"></i><?php echo $_t('गृहपृष्ठमा जानुहोस्', 'Go to Homepage'); ?>
        </a>
        <button onclick="location.reload()" class="btn btn-outline-secondary px-4">
            <i class="fas fa-rotate me-2"></i><?php echo $_t('पुनः प्रयास गर्नुहोस्', 'Try Again'); ?>
        </button>
    </div>
    <p class="text-muted small mt-4 mb-0">
        <?php echo $_t('समस्या जारी रहे कृपया हामीलाई सम्पर्क गर्नुहोस्।', 'If the problem persists, please contact us.'); ?>
    </p>
</div>
</body>
</html>
