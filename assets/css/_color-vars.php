<?php
/**
 * 🎨 Dynamic Color Injector
 * ─────────────────────────────────────────────────────────────
 * `header.php` र `admin-header.php` मा `design-tokens.css` पछि include:
 *
 *   <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/design-tokens.css?v=1">
 *   <?php require ROOT_PATH . 'assets/css/_color-vars.php'; ?>
 *
 * Admin panel (Settings → Primary Color) बाट बदलिएको color
 * तुरुन्त public + member + admin तीनै ठाउँमा reflect हुन्छ।
 */
if (!function_exists('getSetting')) return;

$_p = getSetting('primary_color', 'var(--primary-color)');
$_s = getSetting('secondary_color', getSetting('topbar_color', '#c0392b'));
$_h = getSetting('header_color', getSetting('topbar_color', $_s));
$_f = getSetting('footer_color',  'var(--primary-color)');

/* HEX color लाई dark/light shift गर्ने helper (clamped) */
$__shift = function ($hex, $amt = 36) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) !== 6) return '#' . $hex;
    $clamp = function ($v) { return max(0, min(255, (int)$v)); };
    $r = $clamp(hexdec(substr($hex,0,2)) - $amt);
    $g = $clamp(hexdec(substr($hex,2,2)) - $amt);
    $b = $clamp(hexdec(substr($hex,4,2)) - $amt);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
};
$_pd = $__shift($_p, 36);
$_pl = $__shift($_p, -24);
$_sd = $__shift($_s, 30);
$_hd = $__shift($_h, 30);
$_fd = $__shift($_f, 24);

/* HEX → rgba helper for shadow */
$__rgba = function ($hex, $alpha = 0.18) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) !== 6) return "rgba(26,95,42,$alpha)";
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    return "rgba($r,$g,$b,$alpha)";
};

/* Relative luminance बाट readable foreground color निकाल्ने */
$__luminance = function ($hex) {
    $hex = ltrim((string)$hex, '#');
    if (strlen($hex) !== 6) return 0.25;
    $toLinear = function ($c) {
        $c = $c / 255;
        return ($c <= 0.03928) ? ($c / 12.92) : pow(($c + 0.055) / 1.055, 2.4);
    };
    $r = $toLinear(hexdec(substr($hex, 0, 2)));
    $g = $toLinear(hexdec(substr($hex, 2, 2)));
    $b = $toLinear(hexdec(substr($hex, 4, 2)));
    return (0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b);
};

$__bestText = function ($hex) use ($__luminance) {
    return ($__luminance($hex) > 0.58) ? '#111827' : '#ffffff';
};

$_onPrimary   = $__bestText($_p);
$_onSecondary = $__bestText($_s);
$_onHeader    = $__bestText($_h);
?>
<style id="dynamic-brand-colors">
:root {
    --primary-color:   <?= htmlspecialchars($_p,  ENT_QUOTES) ?>;
    --primary-dark:    <?= htmlspecialchars($_pd, ENT_QUOTES) ?>;
    --primary-light:   <?= htmlspecialchars($_pl, ENT_QUOTES) ?>;
    --secondary-color: <?= htmlspecialchars($_s,  ENT_QUOTES) ?>;
    --secondary-dark:  <?= htmlspecialchars($_sd, ENT_QUOTES) ?>;
    --header-color:    <?= htmlspecialchars($_h,  ENT_QUOTES) ?>;
    --header-dark:     <?= htmlspecialchars($_hd, ENT_QUOTES) ?>;
    --topbar-bg:       <?= htmlspecialchars($_h,  ENT_QUOTES) ?>;
    --footer-color:    <?= htmlspecialchars($_f,  ENT_QUOTES) ?>;
    --footer-dark:     <?= htmlspecialchars($_fd, ENT_QUOTES) ?>;
    --shadow-primary:  0 8px 24px <?= $__rgba($_p, 0.22) ?>;
    --text-on-primary: <?= htmlspecialchars($_onPrimary, ENT_QUOTES) ?>;
    --text-on-secondary: <?= htmlspecialchars($_onSecondary, ENT_QUOTES) ?>;
    --text-on-header: <?= htmlspecialchars($_onHeader, ENT_QUOTES) ?>;
}
</style>
