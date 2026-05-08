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

$__normalizeHex = function ($value, $fallback = '#1a5f2a') {
    $v = trim((string)$value);
    if ($v === '') return strtolower($fallback);
    if (preg_match('/^#([0-9a-fA-F]{3})$/', $v, $m)) {
        $h = strtolower($m[1]);
        return '#' . $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
    }
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $v, $m)) {
        return '#' . strtolower($m[1]);
    }
    return strtolower($fallback);
};

$_pRaw = getSetting('primary_color', '#1a5f2a');
$_sRaw = getSetting('secondary_color', getSetting('topbar_color', '#c0392b'));
$_hRaw = getSetting('header_color', getSetting('topbar_color', $_sRaw));
$_fRaw = getSetting('footer_color', $_pRaw);

$_p = $__normalizeHex($_pRaw, '#1a5f2a');
$_s = $__normalizeHex($_sRaw, '#c0392b');
$_h = $__normalizeHex($_hRaw, $_s);
$_f = $__normalizeHex($_fRaw, $_p);

/* rgba(var(--primary-rgb), α) — ट्र्याकर/श्याडो सँग मेल */
$_pHex = ltrim($_p, '#');
$_primaryRgb = '26, 95, 42';
if (strlen($_pHex) === 6) {
    $_primaryRgb = hexdec(substr($_pHex, 0, 2)) . ', ' . hexdec(substr($_pHex, 2, 2)) . ', ' . hexdec(substr($_pHex, 4, 2));
}

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
    return ($__luminance($hex) > 0.56) ? '#111827' : '#ffffff';
};

$_onPrimary   = $__bestText($_p);
$_onSecondary = $__bestText($_s);
$_onHeader    = $__bestText($_h);
$_onFooter    = $__bestText($_f);
?>
<style id="dynamic-brand-colors">
:root {
    --primary-color:   <?= htmlspecialchars($_p,  ENT_QUOTES) ?>;
    --primary-dark:    <?= htmlspecialchars($_pd, ENT_QUOTES) ?>;
    --primary-light:   <?= htmlspecialchars($_pl, ENT_QUOTES) ?>;
    --primary-rgb:     <?= $_primaryRgb ?>;
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
    --text-on-footer: <?= htmlspecialchars($_onFooter, ENT_QUOTES) ?>;
}

/* Contrast-safe brand surfaces (public/admin/member/verify) */
.btn-primary,
.bg-primary,
.badge.bg-primary,
.text-bg-primary,
.submit-btn,
.vp-btn,
.mem-submit-btn,
.pav-btn {
    color: var(--text-on-primary, #fff) !important;
}

.btn-secondary,
.bg-secondary,
.badge.bg-secondary,
.text-bg-secondary {
    color: var(--text-on-secondary, #fff) !important;
}

.nav-pills .nav-link.active,
.nav-tabs .nav-link.active {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: var(--text-on-primary, #fff) !important;
}

.sidebar-nav li.active > a,
.sidebar-nav a.active {
    color: var(--text-on-primary, #fff) !important;
}

.top-bar, .topbar, .header-top, .site-topbar {
    color: var(--text-on-header, #fff);
}

footer,
.site-footer {
    color: var(--text-on-footer, #fff);
}

/* Keep text/icon readable after admin color changes */
.btn-primary:hover,
.btn-primary:focus,
.btn-primary:active,
.btn-check:checked + .btn-primary,
.btn-check:active + .btn-primary {
    color: var(--text-on-primary, #fff) !important;
}

.btn-outline-primary:hover,
.btn-outline-primary:focus,
.btn-outline-primary:active {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: var(--text-on-primary, #fff) !important;
}

.btn-outline-secondary:hover,
.btn-outline-secondary:focus,
.btn-outline-secondary:active {
    background: var(--secondary-color) !important;
    border-color: var(--secondary-color) !important;
    color: var(--text-on-secondary, #fff) !important;
}

.bg-primary a,
.text-bg-primary a,
.bg-secondary a,
.text-bg-secondary a {
    color: inherit;
}

/* Gradient headers/cards using brand colors */
.gradient-card-header,
.svc-form-header-grad,
.tracker-search-header,
.public-id-card-cta {
    color: var(--text-on-primary, #fff) !important;
}

.gradient-card-header i,
.svc-form-header-grad i,
.tracker-search-header i,
.public-id-card-cta i {
    color: inherit !important;
}

/* Header/Footer contrast-safe links */
.top-bar a,
.topbar a,
.header-top a,
.site-topbar a {
    color: inherit;
}

.footer-policy-links a {
    color: color-mix(in srgb, var(--text-on-footer, #fff) 86%, transparent) !important;
}

.footer-policy-links a:hover,
.footer-bottom .developer a:hover {
    color: var(--text-on-footer, #fff) !important;
}
</style>
