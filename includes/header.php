<?php
require_once __DIR__ . '/config.php';
if (file_exists(__DIR__ . '/member-auth.php')) {
    require_once __DIR__ . '/member-auth.php';
}

if (function_exists('site_license_public_guard')) {
    site_license_public_guard();
}

/* Member portal apply-frame iframe: हल्का UI, लोडर/नेभ नछोपोस् */
$__embed_frame = isset($_GET['embed']) && (string)$_GET['embed'] === '1';

// Get site settings
$siteName = getSetting('site_name', 'आकाश सहकारी');
$siteNameEn = getSetting('site_name_en', 'Aakash Cooperative');
$siteSlogan = getSetting('site_slogan', 'समुदायमा आधारित सहकारी संस्था');
$siteSloganEn = trim((string) getSetting('site_slogan_en', ''));
$phone = getSetting('phone', '061590067');
$mobile = getSetting('mobile', '9827157000');
$email = getSetting('email', 'info@sahakari.org.np');
$address = getSetting('address', 'काठमाडौं, नेपाल');
$facebookUrl = getSetting('facebook_url', '#');
$youtubeUrl = getSetting('youtube_url', '#');
$twitterUrl = getSetting('twitter_url', '');
$instagramUrl = getSetting('instagram_url', '');
$logo = function_exists('getLocalizedLogoPath')
    ? trim((string) getLocalizedLogoPath('assets/images/logo.png'))
    : trim((string) getSetting('site_logo', getSetting('logo', 'assets/images/logo.png')));
$primaryColor = getSetting('primary_color', 'var(--primary-color)');
$secondaryColor = getSetting('secondary_color', getSetting('topbar_color', '#c0392b'));
/* Public portal strict 2-color scheme:
   Primary = brand green, Secondary = utility/accent.
   header/topbar use Secondary; footer uses Primary. */
$headerColor  = $secondaryColor;
$footerColor  = $primaryColor;
$topbarColor  = $secondaryColor;
$mobileAppPhoto = getSetting('mobile_app_photo', 'assets/images/mobile-app.png');
$himalBg = getSetting('himal_bg', '');
$visionMenuLabelNp = trim((string)getSetting('vision_content_title_np', 'हाम्रो दृष्टिकोण'));
$visionMenuLabelEn = trim((string)getSetting('vision_content_title_en', 'Our Vision'));
$missionMenuLabelNp = trim((string)getSetting('mission_content_title_np', 'हाम्रो लक्ष्य'));
$missionMenuLabelEn = trim((string)getSetting('mission_content_title_en', 'Our Mission'));
$valuesMenuLabelNp = trim((string)getSetting('values_content_title_np', 'मूल मान्यताहरू'));
$valuesMenuLabelEn = trim((string)getSetting('values_content_title_en', 'Core Values'));
$chairmanMenuLabelNp = trim((string)getSetting('chairman_message_title_np', 'अध्यक्षको सन्देश'));
$chairmanMenuLabelEn = trim((string)getSetting('chairman_message_title_en', "Chairman's Message"));
$ceoMenuLabelNp = trim((string)getSetting('ceo_message_title_np', 'प्रमुख कार्यकारी अधिकृतको सन्देश'));
$ceoMenuLabelEn = trim((string)getSetting('ceo_message_title_en', "CEO's Message"));
$visionMissionMenuNp = trim($visionMenuLabelNp . ' / ' . $missionMenuLabelNp);
$visionMissionMenuEn = trim($visionMenuLabelEn . ' / ' . $missionMenuLabelEn);

/* ── Shared DB handle for header queries ── */
try { $db = getDB(); } catch (Throwable $e) { $db = null; }
if ($db) {
    require_once __DIR__ . '/election-tables.php';
    ensureElectionTables($db);
}

/* ── Bell Notification: latest active notices ── */
$bellNotices    = [];
$bellNewCount   = 0;
try {
    $bellNotices = $db->query(
        "SELECT id, title, notice_date, attachment
         FROM notices WHERE is_active = 1
         ORDER BY id DESC LIMIT 6"
    )->fetchAll();
    /* count notices from last 14 days as "new" */
    foreach ($bellNotices as $bn) {
        if (!empty($bn['notice_date'])) {
            /* notice_date may be BS string — compare as string prefix YYYY */
            /* treat first 3 items as potentially new if date within ~14 days */
        }
    }
    /* simpler: top 3 most-recent = new if total > 0 */
    $bellNewCount = min(count($bellNotices), 3);
} catch (Throwable $e) { $bellNotices = []; $bellNewCount = 0; }

/* ── Navbar dropdown: admin बाट select गरिएका committees मात्र देखाउने ── */
$navCommittees = [];
try {
    if ($db) {
        $navCommittees = $db->query(
            "SELECT id, name, name_np FROM committee_types
             WHERE is_active = 1 AND show_in_navbar = 1
             ORDER BY display_order, id"
        )->fetchAll();
    }
} catch (Exception $e) { $navCommittees = []; }

/* ── Active cooperative programs count (for notices dropdown badge) ── */
$activeProgramCount = 0;
$hasRecentNotice = false;
$hasRecentProgram = false;
try {
    if ($db) {
        $activeProgramCount = (int)$db->query("SELECT COUNT(*) FROM upcoming_programs WHERE is_active=1")->fetchColumn();
        $recentNoticeStmt = $db->query("SELECT COUNT(*) FROM notices WHERE is_active=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $hasRecentNotice = ((int)($recentNoticeStmt ? $recentNoticeStmt->fetchColumn() : 0) > 0);
        $recentProgramStmt = $db->query("SELECT COUNT(*) FROM upcoming_programs WHERE is_active=1 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $hasRecentProgram = ((int)($recentProgramStmt ? $recentProgramStmt->fetchColumn() : 0) > 0);
    }
} catch (Exception $e) { $activeProgramCount = 0; }

/* ── निर्वाचन जानकारी — मेनुमा देखाउने चक्र (admin बाट show_in_navbar) ── */
$electionNavOn = false;
$hasRecentElectionMilestone = false;
try {
    if ($db) {
        $electionNavOn = ((int)$db->query(
            "SELECT COUNT(*) FROM election_cycles WHERE is_published = 1 AND show_in_navbar = 1"
        )->fetchColumn() > 0);
        if ($electionNavOn) {
            $emStmt = $db->query(
                "SELECT COUNT(*) FROM election_milestones em
                 INNER JOIN election_cycles ec ON ec.id = em.cycle_id
                 WHERE ec.is_published = 1 AND ec.show_in_navbar = 1 AND em.is_active = 1
                   AND (em.created_at >= DATE_SUB(NOW(), INTERVAL 21 DAY)
                        OR (em.event_date IS NOT NULL AND em.event_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)))"
            );
            $hasRecentElectionMilestone = ((int)($emStmt ? $emStmt->fetchColumn() : 0) > 0);
        }
    }
} catch (Exception $e) {
    $electionNavOn = false;
    $hasRecentElectionMilestone = false;
}

require_once __DIR__ . '/nav-menu-badges.php';
require_once __DIR__ . '/service-products-tables.php';
$navMenuBadges = nav_get_public_submenu_badges($db);
$currentLang = getCurrentLang();

/* ── Services dropdown links (admin/services.php बाट) ── */
$navServiceLinks = [];
try {
    if ($db) {
        if (function_exists('ensureServiceProductsTables')) {
            ensureServiceProductsTables($db);
        }
        $svcRows = $db->query("SELECT id, title, title_en, title_np, icon FROM services WHERE is_active = 1 ORDER BY display_order, id LIMIT 30")->fetchAll();
        $productsByService = [];
        try {
            $spRows = $db->query("SELECT service_id, title_np, title_en, display_order, id
                                  FROM service_products
                                  WHERE is_active = 1
                                  ORDER BY service_id, display_order, id")->fetchAll();
            foreach (($spRows ?: []) as $sp) {
                $sid = (int)($sp['service_id'] ?? 0);
                if ($sid <= 0) continue;
                if (!isset($productsByService[$sid])) $productsByService[$sid] = [];
                $productsByService[$sid][] = $sp;
            }
        } catch (Throwable $e) {
            $productsByService = [];
        }
        $serviceAnchorId = static function (array $service): string {
            $id = (int)($service['id'] ?? 0);
            $title = trim((string)($service['title'] ?? ''));
            $titleNp = trim((string)($service['title_np'] ?? ''));
            $label = $title !== '' ? $title : $titleNp;
            $norm = mb_strtolower($label, 'UTF-8');
            if (mb_strpos($norm, 'बचत') !== false || mb_strpos($norm, 'saving') !== false) return 'saving';
            if (mb_strpos($norm, 'ऋण') !== false || mb_strpos($norm, 'loan') !== false || mb_strpos($norm, 'rin') !== false) return 'loan';
            if (mb_strpos($norm, 'रेमिट') !== false || mb_strpos($norm, 'remit') !== false) return 'remittance';
            $ascii = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $title)), '-');
            if ($ascii !== '') return $ascii;
            return $id > 0 ? ('service-' . $id) : 'service';
        };
        foreach ($svcRows as $sv) {
            $rawTitle = (string) (($currentLang === 'en' && !empty($sv['title_en'])) ? $sv['title_en'] : (!empty($sv['title_np']) ? $sv['title_np'] : ($sv['title'] ?? '')));
            $title = trim($rawTitle);
            if ($title === '') continue;
            $icon = trim((string) ($sv['icon'] ?? 'fas fa-star'));
            if ($icon === '') $icon = 'fas fa-star';
            $anchor = $serviceAnchorId($sv);
            $products = [];
            foreach (($productsByService[(int)($sv['id'] ?? 0)] ?? []) as $sp) {
                $pTitleRaw = (string)(($currentLang === 'en' && !empty($sp['title_en'])) ? $sp['title_en'] : ($sp['title_np'] ?? ''));
                $pTitle = trim($pTitleRaw);
                if ($pTitle === '') continue;
                $products[] = ['title' => $pTitle, 'anchor' => $anchor];
                if (count($products) >= 4) break;
            }
            $navServiceLinks[] = ['title' => $title, 'icon' => $icon, 'anchor' => $anchor, 'products' => $products];
        }
    }
} catch (Throwable $e) {
    $navServiceLinks = [];
}

$currentPage = getCurrentPage();
$L = getLangStrings();

$siteBrandName = ($currentLang === 'en' && trim($siteNameEn) !== '') ? $siteNameEn : $siteName;

$__defaultMetaDesc = ($currentLang === 'en')
    ? trim((string) getSetting('meta_description_en', ''))
    : trim((string) getSetting('meta_description', ''));
if ($__defaultMetaDesc === '') {
    $__defaultMetaDesc = ($currentLang === 'en' && $siteSloganEn !== '')
        ? $siteSloganEn
        : (string) $siteSlogan;
}
$__seoDesc = isset($pageDescription) && (string) $pageDescription !== ''
    ? (string) $pageDescription
    : $__defaultMetaDesc;

$__seoKeywords = trim((string) getSetting('meta_keywords', ''));
if ($__seoKeywords === '') {
    $__seoKeywords = $siteName . ', सहकारी, cooperative, बचत, ऋण, Nepal';
}

$__seoCanon = function_exists('seo_canonical_url') ? seo_canonical_url() : (rtrim(SITE_URL, '/') . '/');
$__seoOgImg = '';
if (isset($pageOgImage) && (string) $pageOgImage !== '') {
    $__seoOgImg = function_exists('seo_absolute_asset_url') ? seo_absolute_asset_url((string) $pageOgImage) : (SITE_URL . ltrim((string) $pageOgImage, '/'));
} else {
    $seoOgPath = trim((string) getSetting('seo_og_image', ''));
    $seoOgSafe = $seoOgPath !== '' && function_exists('safe_public_upload_path') ? safe_public_upload_path($seoOgPath) : '';
    if ($seoOgSafe !== '' && function_exists('seo_absolute_asset_url')) {
        $__seoOgImg = seo_absolute_asset_url($seoOgSafe);
    } else {
        $__seoOgImg = function_exists('seo_absolute_asset_url') ? seo_absolute_asset_url($logo) : (SITE_URL . ltrim($logo, '/'));
    }
}
$__htmlLang = ($currentLang === 'en') ? 'en' : 'ne';
$__ogLocale = ($currentLang === 'en') ? 'en_US' : 'ne_NP';
$__ogLocaleAlt = ($currentLang === 'en') ? 'ne_NP' : 'en_US';
$__robots = isset($robotsMeta) && (string) $robotsMeta !== '' ? (string) $robotsMeta : 'index, follow';
$__hrefLangSep = str_contains($__seoCanon, '?') ? '&' : '?';
$__hrefLangNe = $__seoCanon . $__hrefLangSep . 'lang=np';
$__hrefLangEn = $__seoCanon . $__hrefLangSep . 'lang=en';
?>
<!DOCTYPE html>
<html lang="<?php echo e($__htmlLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="<?php echo e($__robots); ?>">
    <meta name="description" content="<?php echo e($__seoDesc); ?>">
    <meta name="keywords" content="<?php echo e($__seoKeywords); ?>">
    <meta name="author" content="<?php echo e($siteBrandName); ?>">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo e($siteBrandName); ?></title>

    <!-- Canonical URL (query string बिना) -->
    <link rel="canonical" href="<?php echo e($__seoCanon); ?>">

    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo e($siteBrandName); ?>">
    <meta property="og:url" content="<?php echo e($__seoCanon); ?>">
    <meta property="og:title" content="<?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo e($siteBrandName); ?>">
    <meta property="og:description" content="<?php echo e($__seoDesc); ?>">
    <meta property="og:image" content="<?php echo e($__seoOgImg); ?>">
    <meta property="og:locale" content="<?php echo e($__ogLocale); ?>">
    <meta property="og:locale:alternate" content="<?php echo e($__ogLocaleAlt); ?>">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo isset($pageTitle) ? e($pageTitle) . ' - ' : ''; ?><?php echo e($siteBrandName); ?>">
    <meta name="twitter:description" content="<?php echo e($__seoDesc); ?>">
    <meta name="twitter:image" content="<?php echo e($__seoOgImg); ?>">

    <!-- hreflang — NP/EN (यो साइट ?lang= ले भाषा बदल्छ) -->
    <link rel="alternate" hreflang="ne-NP" href="<?php echo e($__hrefLangNe); ?>" />
    <link rel="alternate" hreflang="en" href="<?php echo e($__hrefLangEn); ?>" />
    <link rel="alternate" hreflang="x-default" href="<?php echo e($__seoCanon); ?>" />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>assets/images/favicon.png">

    <!-- Preload Logo for faster display -->
    <link rel="preload" href="<?php echo SITE_URL . $logo; ?>" as="image">

    <!-- Google Fonts — Mukta (UI) + Noto Sans Devanagari (नेपाली अक्षर) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- AOS Animation CSS -->
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">

    <!-- Nepali Datepicker CSS (self-hosted v5) -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/nepali.datepicker.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/style.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/style.css') ?: '1'; ?>">
    <!-- Universal CSS — सबै पृष्ठमा uniform font/colour/form/card -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/universal.css?v=4">
    <!-- 🎨 Design Tokens — Public + Member + Admin एकै font/color/spacing -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/design-tokens.css?v=5">
    <?php @require_once __DIR__ . '/../assets/css/_color-vars.php'; ?>
    <!-- Header v2 — Pokhara Finance Style (Testing) -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/header-v2.css?v=24">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/v9-mobile-fix.css?v=9.8">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/site-banner-logo.css?v=3">

    <!-- Dynamic Theme Color -->
    <style>
        :root {
            --primary-color: <?php echo $primaryColor; ?>;
            --primary-dark: <?php echo adjustBrightness($primaryColor, -20); ?>;
            --primary-light: <?php echo adjustBrightness($primaryColor, 30); ?>;
            --secondary-color: <?php echo $secondaryColor; ?>;
            --header-color: <?php echo $headerColor; ?>;
            --footer-color: <?php echo $footerColor; ?>;
            --footer-dark: <?php echo adjustBrightness($footerColor, -20); ?>;
            --topbar-bg: <?php echo $headerColor; ?>;
        }
        /* Hide page loader after load */
        .page-loaded .page-loader {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        /* Expose logo URL to mobile drawer (used by v9-mobile-fix.css) */
        :root {
            --pfl-mobile-logo: url('<?php echo SITE_URL . ltrim($logo, '/'); ?>');
            --pfl-site-name: '<?php echo addslashes($siteName); ?>';
        }

        /* ── Mobile Header Responsive Fixes ── */
        @media (max-width: 991px) {
            /* Top utility bar — hide most links on mobile to save space */
            .pfl-top-bar .pfl-quick-links { display: none !important; }
            .pfl-top-bar .container { justify-content: flex-end; }
            .pfl-top-bar { padding: 5px 0; }
            .pfl-top-bar .container { min-height: 42px; }

            /* Brand area — compact on mobile */
            .pfl-brand-area { padding: 8px 12px; flex: 1; min-width: 0; }
            .pfl-brand-content.has-logo .pfl-brand-logo {
                max-height: 48px !important;
                height: auto !important;
                width: auto !important;
                max-width: min(330px, 86vw) !important;
                object-fit: contain !important;
            }
            .pfl-brand-content.no-logo .pfl-brand-logo { height: 42px !important; width: auto !important; }
            .pfl-brand-name-np { font-size: 1rem !important; }
            .pfl-brand-name-en, .pfl-brand-slogan { font-size: 0.68rem !important; }
            .pfl-since-badge { display: none !important; }

            /* Mobile toggle button — ensure visibility */
            .pfl-mobile-toggle {
                display: flex !important;
                align-items: center; justify-content: center;
                width: 40px; height: 40px;
                background: var(--primary-color);
                color: #fff;
                border: none;
                border-radius: 8px;
                font-size: 1.05rem;
                cursor: pointer;
                flex-shrink: 0;
                margin-left: 6px;
            }

            /* Mobile drawer base — DARK admin-style. Detailed look in v9-mobile-fix.css */
            .main-nav {
                position: fixed !important;
                top: 0; left: 0; right: auto;
                width: min(300px, 85vw);
                height: 100vh;
                height: 100dvh;
                z-index: 200010 !important;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);
                transform: translate3d(-110%, 0, 0);
                box-shadow: 4px 0 24px rgba(0,0,0,.25);
                padding: 0;
            }
            .main-nav.nav-open,
            .main-nav.open,
            .main-nav.active { transform: translate3d(0, 0, 0) !important; }
            .close-menu { display: flex !important; }

            /* Header-v2 mobile drawer only — disable legacy public menu/overlay */
            body.header-v2 #mainNav,
            body.header-v2 #mobileMenuToggle,
            body.header-v2 #closeMenu,
            body.header-v2 #menuOverlay {
                display: none !important;
            }

            /* Overlay backdrop — must be BELOW main-nav (200000) */
            .mobile-nav-backdrop {
                display: none;
                position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 199998;
            }
            #pflMobileBackdrop { z-index: 200000 !important; }
            .mobile-nav-backdrop.active { display: block; }

            /* Main header flex layout fix */
            .pfl-main-header {
                display: flex !important;
                align-items: center !important;
                padding: 5px 10px !important;
                gap: 6px !important;
            }
            .pfl-nav-area {
                display: flex !important;
                align-items: center !important;
                justify-content: flex-end !important;
                gap: 6px !important;
                flex: 0 0 auto !important;
            }

            /* Mobile मा right utility icons neat/compact राख्ने */
            .pfl-top-right { display: flex !important; align-items: center; gap: 6px; }
            .pfl-top-right > li > a,
            .pfl-top-right .pfl-bell-btn {
                min-width: 34px;
                height: 34px;
                padding: 0 9px;
                border-radius: 8px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .pfl-top-right .pfl-lang-wrap {
                display: inline-flex !important;
                align-items: center;
                gap: 3px;
                background: rgba(255,255,255,.16);
                border: 1px solid rgba(255,255,255,.24);
                box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
                border-radius: 999px;
                padding: 2px 4px;
            }
            .pfl-top-right .pfl-lang-wrap a {
                min-width: 32px;
                text-align: center;
                padding: 4px 8px;
                border-radius: 999px;
                font-size: 10px;
                line-height: 1;
                font-weight: 800;
                letter-spacing: .2px;
                color: rgba(255,255,255,.92) !important;
                transition: all .18s ease;
            }
            .pfl-top-right .pfl-lang-wrap a.active {
                background: #fff !important;
                color: #14532d !important;
                box-shadow: 0 1px 3px rgba(0,0,0,.18);
            }
            .pfl-top-right .pfl-lang-wrap a:not(.active):hover {
                background: rgba(255,255,255,.22) !important;
            }
            .pfl-top-right .pfl-dark-wrap > a i { font-size: 14px; }
            .pfl-login-drop-wrap { position: relative; z-index: 210000; }
            .pfl-login-menu { z-index: 210001; right: 0; left: auto; }
            .pfl-top-right a,
            .pfl-top-right button,
            .pfl-mobile-toggle {
                -webkit-tap-highlight-color: transparent;
                outline: none !important;
                box-shadow: none !important;
            }
            .pfl-top-right a:focus-visible,
            .pfl-top-right button:focus-visible,
            .pfl-mobile-toggle:focus-visible {
                outline: 2px solid rgba(255,255,255,.45) !important;
                outline-offset: 1px;
            }

            /* Fixed mobile order: EN/NP -> Dark -> Bell -> Login */
            .pfl-top-right { display: flex !important; }
            .pfl-top-right .pfl-lang-wrap { order: 1; }
            .pfl-top-right .pfl-dark-wrap { order: 2; }
            .pfl-top-right .pfl-bell-wrap { order: 3; }
            .pfl-top-right .pfl-login-drop-wrap { order: 4; }
        }

        @media (max-width: 575px) {
            /* Very small screens */
            .pfl-brand-content.has-logo .pfl-brand-logo {
                max-height: 42px !important;
                height: auto !important;
                width: auto !important;
                max-width: min(300px, 84vw) !important;
            }
            .pfl-brand-content.no-logo .pfl-brand-logo { height: 36px !important; }
            .pfl-brand-name-np { font-size: 0.88rem !important; }
            .pfl-top-right { gap: 4px !important; }
            .pfl-top-right .pfl-lang-wrap { display: inline-flex !important; }
            .pfl-top-right .pfl-lang-wrap span { display: none; }
            .pfl-top-right .pfl-lang-wrap { padding: 2px 3px; gap: 2px; }
            .pfl-top-right .pfl-lang-wrap a {
                min-width: 30px;
                font-size: 10px;
                padding: 4px 7px;
            }
            .pfl-top-right .pfl-login-toggle { padding: 0 8px; }
            .pfl-top-right .pfl-login-toggle .pfl-login-caret { display: none; }
            .pfl-top-right .pfl-login-toggle { font-size: 0; }
            .pfl-top-right .pfl-login-toggle i { font-size: 15px; margin: 0; }
            .pfl-top-right #topbarSearchBtn { display: none !important; }
            .pfl-mobile-toggle { width: 38px; height: 38px; }
            .pfl-main-header { padding: 4px 8px !important; }

            /* Login dropdown: mobile मा full visible panel (no awkward overlap/cutoff) */
            .pfl-top-bar { z-index: 300000 !important; }
            .pfl-login-drop-wrap { position: static !important; }
            .pfl-login-menu {
                position: fixed !important;
                top: calc(env(safe-area-inset-top, 0px) + 52px) !important;
                left: 8px !important;
                right: 8px !important;
                width: auto !important;
                max-width: none !important;
                max-height: min(72vh, 520px) !important;
                overflow-y: auto !important;
                z-index: 300010 !important;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/public-modern.css?v=1">
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
    <?php if (!empty($__embed_frame)): ?>
    <style id="public-embed-frame-css">
    /* Member portal भित्र iframe — mixed/ठूलो हेडर बिना फारम देखिने */
    body.embed-in-member-portal { overflow: auto !important; padding-top: 0 !important; }
    body.embed-in-member-portal .pfl-header-wrapper,
    body.embed-in-member-portal .main-header { display: none !important; }
    body.embed-in-member-portal .page-banner { display: none !important; }
    body.embed-in-member-portal .main-footer { display: none !important; }
    </style>
    <?php endif; ?>
</head>
<body class="header-v2<?php echo !empty($__embed_frame) ? ' embed-in-member-portal' : ''; ?>">
    <?php if (empty($__embed_frame)): ?>
    <!-- Page Loader -->
    <div class="page-loader" id="pageLoader">
        <div class="loader-inner">
            <div class="loader-spinner-wrap">
                <div class="loader-spinner"></div>
            </div>
            <div class="loader-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-text"><span id="progressPercent">0</span>%</div>
            </div>
            <div class="loader-text"><?php echo isEnglish() ? 'Loading...' : 'लोड हुँदैछ...'; ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════
         HEADER v2 — Pokhara Finance Style (Testing)
         body.header-v2 ले old top-bar र main-header hide गर्छ
         Rollback: admin → body class बाट "header-v2" हटाउनुस्
         ═══════════════════════════════════════════════ -->

    <!-- PFL Sticky Wrapper — utility bar + main header stick together -->
    <div class="pfl-header-wrapper">
    <!-- PFL Top Utility Bar -->
    <div class="pfl-top-bar">
        <div class="container">
            <!-- Left: Quick Utility Links -->
            <ul class="pfl-quick-links">
                <li>
                    <a href="<?php echo SITE_URL; ?>auction.php">
                        <i class="fas fa-gavel"></i>
                        <?php echo isEnglish() ? 'Auction Portal' : 'लिलामी पोर्टल'; ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>downloads.php">
                        <i class="fas fa-download"></i>
                        <?php echo isEnglish() ? 'Downloads' : 'डाउनलोड'; ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>career.php">
                        <i class="fas fa-briefcase"></i>
                        <?php echo isEnglish() ? 'Career' : 'बिज्ञापन'; ?>
                        <?php echo nav_submenu_count_badge_html($navMenuBadges['career_open']); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>contact.php">
                        <i class="fas fa-envelope"></i>
                        <?php echo isEnglish() ? 'Contact Us' : 'सम्पर्क'; ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo SITE_URL; ?>digital-services.php">
                        <i class="fas fa-laptop"></i>
                        <?php echo isEnglish() ? 'Digital Services' : 'डिजिटल सेवा'; ?>
                    </a>
                </li>
                <li class="has-drop">
                    <a href="javascript:void(0);">
                        <i class="fas fa-th"></i>
                        <?php echo isEnglish() ? 'Quick Links' : 'छिटो लिंक'; ?>
                        <i class="fas fa-caret-down ms-1" style="font-size:10px;"></i>
                    </a>
                    <div class="pfl-drop">
                        <a href="<?php echo SITE_URL; ?>emi-calculator.php"><i class="fas fa-calculator me-1"></i><?php echo isEnglish() ? 'EMI Calculator' : 'ईएमआई क्याल्कुलेटर'; ?></a>
                        <a href="<?php echo SITE_URL; ?>exchange-rate.php"><i class="fas fa-exchange-alt me-1"></i><?php echo isEnglish() ? 'Exchange Rate' : 'विनिमय दर'; ?></a>
                        <a href="<?php echo SITE_URL; ?>date-converter.php"><i class="fas fa-calendar-alt me-1"></i><?php echo isEnglish() ? 'Date Converter' : 'मिति परिवर्तन'; ?></a>
                        <a href="<?php echo SITE_URL; ?>partner-facilities.php"><i class="fas fa-handshake me-1 text-success"></i><?php echo isEnglish() ? 'Partner Facilities' : 'अन्य सुविधा'; ?></a>
                        <a href="<?php echo SITE_URL; ?>application-tracker.php"><i class="fas fa-search me-1"></i><?php echo isEnglish() ? 'Track Application' : 'आवेदन ट्र्याक'; ?></a>
                        <a href="<?php echo SITE_URL; ?>service-centers.php"><i class="fas fa-map-marker-alt me-1"></i><?php echo isEnglish() ? 'Branches' : 'शाखाहरू'; ?></a>
                        <a href="<?php echo SITE_URL; ?>reports.php"><i class="fas fa-chart-bar me-1"></i><?php echo isEnglish() ? 'Reports' : 'प्रतिवेदन'; ?></a>
                        <a href="<?php echo SITE_URL; ?>faqs.php"><i class="fas fa-question-circle me-1"></i><?php echo isEnglish() ? 'FAQs' : 'प्रश्नोत्तर'; ?></a>
                    </div>
                </li>
            </ul>

            <!-- Right: Login + Language + Notification + Search -->
            <ul class="pfl-top-right">
                <!-- Login Dropdown -->
                <li class="pfl-login-btn pfl-login-drop-wrap">
                    <a href="javascript:void(0);" class="pfl-login-toggle" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-user-circle"></i>
                        <?php echo isEnglish() ? 'Login' : 'लगिन'; ?>
                        <i class="fas fa-caret-down pfl-login-caret"></i>
                    </a>
                    <ul class="pfl-login-menu" role="menu">
                        <?php $ibUrl = getSetting('internet_banking_url',''); if($ibUrl): ?>
                        <li>
                            <a href="<?php echo $ibUrl; ?>" target="_blank" rel="noopener">
                                <span class="pfl-lm-icon pfl-lm-web"><i class="fas fa-globe"></i></span>
                                <span class="pfl-lm-text">
                                    <strong><?php echo isEnglish() ? 'E‑banking login' : 'इ‑बैंकिङ लगिन'; ?></strong>
                                    <small><?php echo isEnglish() ? 'Secure web banking' : 'अनलाइन बैंकिङ प्रवेश'; ?></small>
                                </span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php $psUrl = getSetting('play_store_url',''); if($psUrl): ?>
                        <li>
                            <a href="<?php echo $psUrl; ?>" target="_blank" rel="noopener">
                                <span class="pfl-lm-icon pfl-lm-android"><i class="fab fa-google-play"></i></span>
                                <span class="pfl-lm-text">
                                    <strong><?php echo isEnglish() ? 'Mobile app (Android)' : 'मोबाइल एप (एन्ड्रोइड)'; ?></strong>
                                    <small><?php echo isEnglish() ? 'Google Play' : 'गुगल प्ले बाट डाउनलोड'; ?></small>
                                </span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php $asUrl = getSetting('app_store_url',''); if($asUrl): ?>
                        <li>
                            <a href="<?php echo $asUrl; ?>" target="_blank" rel="noopener">
                                <span class="pfl-lm-icon pfl-lm-ios"><i class="fab fa-apple"></i></span>
                                <span class="pfl-lm-text">
                                    <strong><?php echo isEnglish() ? 'Mobile app (iOS)' : 'मोबाइल एप (आइओएस)'; ?></strong>
                                    <small><?php echo isEnglish() ? 'App Store' : 'एप स्टोर बाट डाउनलोड'; ?></small>
                                </span>
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="pfl-lm-divider"></li>
                        <!-- Member Portal -->
                        <li>
                            <?php
                            $isMemberLoggedIn = function_exists('memberIsLoggedIn') && memberIsLoggedIn();
                            $memberPortalHref = $isMemberLoggedIn ? SITE_URL . 'member/' : SITE_URL . 'member/login.php';
                            ?>
                            <a href="<?php echo $memberPortalHref; ?>">
                                <span class="pfl-lm-icon pfl-lm-member"><i class="fas fa-user-check"></i></span>
                                <span class="pfl-lm-text">
                                    <strong><?php echo isEnglish() ? 'Member login' : 'सदस्य लगिन'; ?></strong>
                                    <small><?php echo $isMemberLoggedIn ? htmlspecialchars($_SESSION['member_name'] ?? 'Member') : (isEnglish() ? 'Sign in · New registration' : 'लगिन · नयाँ दर्ता'); ?></small>
                                </span>
                                <?php if ($isMemberLoggedIn && function_exists('getMemberUnreadCount') && ($unreadMemCount = getMemberUnreadCount($_SESSION['member_id'])) > 0): ?>
                                <span class="pfl-lm-badge"><?php echo $unreadMemCount > 9 ? '9+' : $unreadMemCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="pfl-lm-divider"></li>
                        <!-- Member Verify (public card verification — for hospitals, vendors, etc.) -->
                        <li>
                            <a href="<?php echo SITE_URL; ?>verify.php">
                                <span class="pfl-lm-icon pfl-lm-verify"><i class="fas fa-shield-halved"></i></span>
                                <span class="pfl-lm-text">
                                    <strong><?php echo isEnglish() ? 'Member verify' : 'सदस्य परिचयपत्र जाँच'; ?></strong>
                                    <small><?php echo isEnglish() ? 'ID card check — code & CVV' : 'कोड र CVV ले प्रमाणित गर्नुहोस्'; ?></small>
                                </span>
                            </a>
                        </li>
                        <li class="pfl-lm-divider"></li>
                        <li>
                            <a href="<?php echo SITE_URL; ?>admin/">
                                <span class="pfl-lm-icon pfl-lm-admin"><i class="fas fa-user-shield"></i></span>
                                <span class="pfl-lm-text">
                                    <strong><?php echo isEnglish() ? 'Office login' : 'कार्यालय लगिन'; ?></strong>
                                    <small><?php echo isEnglish() ? 'Staff & admin access' : 'कर्मचारी / प्रशासन प्रवेश'; ?></small>
                                </span>
                            </a>
                        </li>
                    </ul>
                </li>
                <!-- Bell Notification Dropdown -->
                <li class="pfl-bell-wrap" id="pflBellWrap">
                    <button class="pfl-bell-btn" id="pflBellBtn"
                            aria-label="<?php echo isEnglish() ? 'Notifications' : 'सूचनाहरू'; ?>"
                            aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <?php if ($bellNewCount > 0): ?>
                        <span class="pfl-bell-badge"><?php echo $bellNewCount; ?></span>
                        <?php endif; ?>
                    </button>
                    <!-- Dropdown panel -->
                    <div class="pfl-bell-drop" id="pflBellDrop" role="dialog" aria-label="सूचनाहरू">
                        <div class="pfl-bell-header">
                            <span><i class="fas fa-bell me-1"></i><?php echo isEnglish() ? 'Notices' : 'सूचनाहरू'; ?></span>
                            <?php if ($bellNewCount > 0): ?>
                            <span class="pfl-bell-new-chip"><?php echo $bellNewCount; ?> New</span>
                            <?php endif; ?>
                        </div>
                        <ul class="pfl-bell-list">
                        <?php if (empty($bellNotices)): ?>
                            <li class="pfl-bell-empty">
                                <i class="fas fa-bell-slash"></i>
                                <span><?php echo isEnglish() ? 'No notices available' : 'कुनै सूचना छैन'; ?></span>
                            </li>
                        <?php else: foreach ($bellNotices as $bni => $bn): ?>
                            <li class="pfl-bell-item<?php echo $bni < 3 ? ' pfl-bell-item--new' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>notices.php?id=<?php echo (int)$bn['id']; ?>">
                                    <span class="pfl-bell-item-icon">
                                        <?php if (!empty($bn['attachment'])): ?>
                                            <i class="fas fa-paperclip"></i>
                                        <?php else: ?>
                                            <i class="fas fa-file-alt"></i>
                                        <?php endif; ?>
                                    </span>
                                    <span class="pfl-bell-item-body">
                                        <span class="pfl-bell-item-title"><?php echo e(mb_substr($bn['title'], 0, 70) . (mb_strlen($bn['title']) > 70 ? '…' : '')); ?></span>
                                        <?php if (!empty($bn['notice_date'])): ?>
                                        <span class="pfl-bell-item-date"><?php echo e($bn['notice_date']); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($bni < 3): ?>
                                    <span class="pfl-bell-new-dot" title="नयाँ">NEW</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; endif; ?>
                        </ul>
                        <div class="pfl-bell-footer">
                            <a href="<?php echo SITE_URL; ?>notices.php">
                                <?php echo isEnglish() ? 'View All Notices' : 'सबै सूचना हेर्नुहोस्'; ?>
                                <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </li>
                <li class="pfl-lang-wrap">
                    <a href="?lang=en" class="<?php echo $currentLang === 'en' ? 'active' : ''; ?>">EN</a>
                    <span>|</span>
                    <a href="?lang=np" class="<?php echo $currentLang === 'np' ? 'active' : ''; ?>">NP</a>
                </li>
                <li>
                    <a href="javascript:void(0);" id="topbarSearchBtn" title="<?php echo isEnglish() ? 'Search' : 'खोज्नुहोस्'; ?>">
                        <i class="fas fa-search"></i>
                    </a>
                </li>
                <li class="pfl-dark-wrap">
                    <a href="javascript:void(0);" id="topbarDarkModeToggle" title="<?php echo isEnglish() ? 'Dark Mode' : 'डार्क मोड'; ?>">
                        <i class="fas fa-moon"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- PFL Main Header: Brand + Navigation (himal bg on nav/right side) -->
    <header class="pfl-main-header" id="pflMainHeader"<?php if ($himalBg): ?> style="--himal-bg:url('<?php echo SITE_URL . htmlspecialchars($himalBg); ?>')"<?php endif; ?>>
        <!-- LEFT: Logo area — clean white background -->
        <div class="pfl-brand-area">
            <div class="pfl-himal-silhouette"></div>
            <a href="<?php echo SITE_URL; ?>" class="pfl-brand-content <?php echo !empty($logo) ? 'has-logo' : 'no-logo'; ?>">
                <?php if (!empty($logo)): ?>
                <img src="<?php echo SITE_URL . $logo; ?>"
                     alt="<?php echo e($siteNameEn); ?>"
                     class="pfl-brand-logo"
                     onerror="this.style.display='none';var p=this.parentElement;p.classList.remove('has-logo');p.classList.add('no-logo');if(!p.querySelector('.pfl-brand-logo-fallback')){var fb=document.createElement('div');fb.className='pfl-brand-logo-fallback';fb.innerHTML='<i class=\'fas fa-landmark\'></i>';p.insertBefore(fb,this);}">
                <?php else: ?>
                <div class="pfl-brand-logo-fallback"><i class="fas fa-landmark"></i></div>
                <?php endif; ?>
                <?php if (empty($logo)): ?>
                <div class="pfl-brand-text">
                    <span class="pfl-brand-name-np"><?php echo e($siteName); ?></span>
                    <span class="pfl-brand-name-en"><?php echo e($siteNameEn); ?></span>
                    <span class="pfl-brand-slogan"><?php echo e($siteSlogan); ?></span>
                </div>
                <?php endif; ?>
                <?php
                $estYear = getSetting('established_year', '');
                if ($estYear):
                ?>
                <div class="pfl-since-badge">
                    <span class="since-text">Since</span>
                    <span class="since-year"><?php echo htmlspecialchars($estYear); ?></span>
                </div>
                <?php endif; ?>
            </a>
        </div>

        <!-- RIGHT: Navigation (reuses existing nav styles) -->
        <div class="pfl-nav-area">
            <!-- Mobile Toggle (visible < lg) -->
            <button class="pfl-mobile-toggle d-lg-none" id="mobileMenuToggle2" aria-label="Menu" aria-controls="mainNavV2" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Navigation — same structure as original -->
            <nav class="main-nav" id="mainNavV2" aria-hidden="true" data-mobile-drawer="public">
                <button class="close-menu d-lg-none" id="closeMenuV2">
                    <i class="fas fa-times"></i>
                </button>
                <ul class="nav-menu">
                    <li class="<?php echo $currentPage == 'index' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>index.php"><?php echo $L['home']; ?></a>
                    </li>
                    <li class="has-dropdown <?php echo $currentPage == 'about' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>about.php"><?php echo $L['about']; ?> <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown">
                            <li><a href="<?php echo SITE_URL; ?>about.php"><i class="fas fa-info-circle"></i> <?php echo isEnglish() ? 'About Us' : 'हाम्रो बारेमा'; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>about.php#history"><i class="fas fa-clock"></i> <?php echo isEnglish() ? 'History' : 'हाम्रो इतिहास'; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>about.php#vision"><i class="fas fa-eye"></i> <?php echo htmlspecialchars(isEnglish() ? $visionMissionMenuEn : $visionMissionMenuNp, ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>about.php#values"><i class="fas fa-heart"></i> <?php echo htmlspecialchars(isEnglish() ? $valuesMenuLabelEn : $valuesMenuLabelNp, ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>about.php#chairman"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(isEnglish() ? $chairmanMenuLabelEn : $chairmanMenuLabelNp, ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>about.php#ceo-message"><i class="fas fa-user"></i> <?php echo htmlspecialchars(isEnglish() ? $ceoMenuLabelEn : $ceoMenuLabelNp, ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>institutional-profile.php"><i class="fas fa-building-columns text-success me-1"></i> <?php echo isEnglish() ? 'Institutional Profile' : 'संस्थागत प्रोफाइल'; ?></a></li>
                        </ul>
                    </li>
                    <li class="has-dropdown <?php echo $currentPage == 'services' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>services.php"><?php echo $L['services']; ?> <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown">
                            <?php if (!empty($navServiceLinks)): ?>
                                <?php foreach ($navServiceLinks as $_svc): ?>
                                    <li><a href="<?php echo SITE_URL; ?>services.php#<?php echo htmlspecialchars($_svc['anchor']); ?>"><i class="<?php echo htmlspecialchars($_svc['icon']); ?>"></i> <?php echo htmlspecialchars($_svc['title']); ?></a></li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li><a href="<?php echo SITE_URL; ?>services.php#saving"><i class="fas fa-piggy-bank"></i> <?php echo $L['saving']; ?></a></li>
                                <li><a href="<?php echo SITE_URL; ?>services.php#loan"><i class="fas fa-hand-holding-usd"></i> <?php echo $L['loan']; ?></a></li>
                                <li><a href="<?php echo SITE_URL; ?>services.php#remittance"><i class="fas fa-money-bill-wave"></i> <?php echo $L['remittance']; ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="<?php echo $currentPage == 'interest-rates' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>interest-rates.php"><?php echo $L['interest_rates']; ?></a>
                    </li>
                    <li class="has-dropdown <?php echo in_array($currentPage, ['notices', 'cooperative-programs', 'election-information']) ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>notices.php"><?php echo $L['notices']; ?> <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown">
                            <li><a href="<?php echo SITE_URL; ?>notices.php"><i class="fas fa-bullhorn"></i> <?php echo isEnglish() ? 'Latest Notices' : 'नवीनतम सूचना'; ?><?php if ($hasRecentNotice): ?><span class="nav-new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span><?php endif; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>cooperative-programs.php"><i class="fas fa-calendar-check"></i> <?php echo isEnglish() ? 'Cooperative Programs' : 'सहकारी कार्यक्रम'; ?><?php if ($hasRecentProgram): ?><span class="nav-new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span><?php endif; ?><?php if ($activeProgramCount > 0): ?><span class="nav-new-badge"><?php echo (int)$activeProgramCount; ?></span><?php endif; ?></a></li>
                            <?php if ($electionNavOn): ?>
                            <li><a href="<?php echo SITE_URL; ?>election-information.php"><i class="fas fa-check-to-slot"></i> <?php echo htmlspecialchars($L['election_information'] ?? 'निर्वाचन जानकारी'); ?><?php if ($hasRecentElectionMilestone): ?><span class="nav-new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span><?php endif; ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="<?php echo $currentPage == 'gallery' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>gallery.php"><?php echo $L['gallery']; ?></a>
                    </li>
                    <li class="has-dropdown <?php echo in_array($currentPage, ['team', 'committees']) ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>team.php"><?php echo $L['team']; ?> <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown">
                            <li><a href="<?php echo SITE_URL; ?>team.php"><i class="fas fa-id-card-clip"></i> <?php echo isEnglish() ? 'Contact Officers' : 'सम्पर्क अधिकारी'; ?></a></li>
                            <?php /* Admin बाट 'मेनुमा देखाउनुहोस्' check गरिएका committees मात्र */ ?>
                            <?php foreach ($navCommittees as $_nc): ?>
                                <li><a href="<?php echo SITE_URL; ?>committees.php?id=<?php echo (int)$_nc['id']; ?>"><i class="fas fa-users-gear"></i> <?php echo isEnglish() ? htmlspecialchars($_nc['name']) : htmlspecialchars($_nc['name_np']); ?></a></li>
                            <?php endforeach; ?>
                            <?php if (empty($navCommittees)): ?>
                                <li><a href="<?php echo SITE_URL; ?>committees.php"><i class="fas fa-sitemap"></i> <?php echo isEnglish() ? 'All Committees' : 'सबै समिति'; ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="has-dropdown">
                        <a href="javascript:void(0);"><?php echo isEnglish() ? 'More' : 'थप'; ?> <i class="fas fa-chevron-down"></i></a>
                        <ul class="dropdown">
                            <li><a href="<?php echo SITE_URL; ?>news.php"><i class="fas fa-newspaper"></i> <?php echo isEnglish() ? 'News & Activities' : 'समाचार'; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>career.php"><i class="fas fa-briefcase"></i> <?php echo isEnglish() ? 'Career' : 'बिज्ञापन'; ?><?php echo nav_submenu_count_badge_html($navMenuBadges['career_open']); ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>reports.php"><i class="fas fa-chart-line"></i> <?php echo isEnglish() ? 'Reports & Publications' : 'प्रतिवेदन'; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>downloads.php"><i class="fas fa-download"></i> <?php echo isEnglish() ? 'Downloads' : 'डाउनलोड'; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>service-centers.php"><i class="fas fa-map-marker-alt"></i> <?php echo isEnglish() ? 'Branches' : 'शाखाहरू'; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>faqs.php"><i class="fas fa-question-circle"></i> <?php echo isEnglish() ? 'FAQs' : 'प्रश्नोत्तर'; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>member-survey.php"><i class="fas fa-comment-dots"></i> <?php echo isEnglish() ? 'Suggestion Box' : 'सुझाव बक्स'; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>partner-facilities.php"><i class="fas fa-handshake"></i> <?php echo isEnglish() ? 'Partner Facilities' : 'अन्य सुविधा'; ?></a></li>
                            <li><a href="<?php echo SITE_URL; ?>application-tracker.php"><i class="fas fa-search"></i> <?php echo isEnglish() ? 'Track Application' : 'आवेदन ट्र्याक'; ?></a></li>
                        </ul>
                    </li>
                    <li class="<?php echo $currentPage == 'contact' ? 'active' : ''; ?>">
                        <a href="<?php echo SITE_URL; ?>contact.php"><?php echo $L['contact']; ?></a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    </div><!-- /pfl-header-wrapper -->

    <!-- PFL Mobile Toggle + Scroll Shadow Script -->
    <script>
    (function(){
        /* Mobile toggle handled by v9-mobile-fix.js — inline duplicate removed in v9.4 */
        /* Scroll shadow on sticky wrapper */
        var pflWrapper = document.querySelector('.pfl-header-wrapper');
        if (pflWrapper) {
            window.addEventListener('scroll', function () {
                pflWrapper.classList.toggle('scrolled', window.scrollY > 10);
            }, { passive: true });
        }

        /* ── Bell Notification Dropdown ── */
        var bellBtn  = document.getElementById('pflBellBtn');
        var bellDrop = document.getElementById('pflBellDrop');
        var bellWrap = document.getElementById('pflBellWrap');
        if (bellBtn && bellDrop) {
            bellBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                var isOpen = bellDrop.classList.toggle('open');
                bellBtn.setAttribute('aria-expanded', isOpen);
            });
            /* Close on outside click */
            document.addEventListener('click', function(e) {
                if (bellWrap && !bellWrap.contains(e.target)) {
                    bellDrop.classList.remove('open');
                    bellBtn.setAttribute('aria-expanded', 'false');
                }
            });
            /* Close on Escape */
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    bellDrop.classList.remove('open');
                    bellBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        /* ── Login Dropdown ── */
        (function() {
            var wrap   = document.querySelector('.pfl-login-drop-wrap');
            var toggle = document.querySelector('.pfl-login-toggle');
            if (!wrap || !toggle) return;
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                /* Mobile: login open गर्दा drawer/menu side effects बन्द */
                var nav = document.getElementById('mainNavV2');
                var backdrop = document.getElementById('pflMobileBackdrop');
                if (nav) nav.classList.remove('nav-open', 'open', 'active');
                if (backdrop) backdrop.classList.remove('active');
                document.body.classList.remove('mobile-nav-open');
                var open = wrap.classList.toggle('open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            document.addEventListener('click', function(e) {
                if (!wrap.contains(e.target)) {
                    wrap.classList.remove('open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    wrap.classList.remove('open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        })();
    })();
    </script>

    <!-- Top Bar (OLD — hidden when header-v2 active) -->
    <div class="top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 col-md-12">
                    <ul class="top-info">
                        <li><i class="fas fa-phone-alt"></i> <?php echo $phone; ?></li>
                        <li><i class="fas fa-mobile-alt"></i> <?php echo $mobile; ?></li>
                        <li><i class="fas fa-envelope"></i> <?php echo $email; ?></li>
                        <li><i class="fas fa-map-marker-alt"></i> <?php echo $address; ?></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-12 text-lg-end">
                    <ul class="social-links">
                        <li class="lang-switch">
                            <a href="?lang=en" class="lang-btn <?php echo $currentLang === 'en' ? 'active' : ''; ?>">EN</a>
                            <span class="lang-divider">|</span>
                            <a href="?lang=np" class="lang-btn <?php echo $currentLang === 'np' ? 'active' : ''; ?>">NP</a>
                        </li>
                        <li class="internet-banking-btn">
                            <a href="<?php echo getSetting('internet_banking_url', '#'); ?>" target="_blank" title="<?php echo isEnglish() ? 'Internet Banking' : 'इन्टरनेट बैंकिङ'; ?>">
                                <i class="fas fa-laptop"></i>
                            </a>
                        </li>
                        <li><a href="<?php echo $facebookUrl; ?>" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a href="<?php echo $youtubeUrl; ?>" target="_blank"><i class="fab fa-youtube"></i></a></li>
                        <li><a href="mailto:<?php echo $email; ?>"><i class="fas fa-envelope"></i></a></li>
                            <li class="topbar-search-btn d-none d-lg-inline-block">
                                <a href="javascript:void(0);" id="topbarSearchBtn" title="<?php echo isEnglish() ? 'Search' : 'खोज्नुहोस्'; ?>">
                                <i class="fas fa-search"></i>
                            </a>
                        </li>
                            <li class="topbar-darkmode-btn d-none d-lg-inline-block">
                                <a href="javascript:void(0);" id="topbarDarkModeToggle" title="<?php echo isEnglish() ? 'Dark Mode' : 'डार्क मोड'; ?>">
                                <i class="fas fa-moon"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-4 col-md-5 col-8">
                    <div class="logo-banner">
                        <a href="<?php echo SITE_URL; ?>" class="logo-banner-link">
                            <img src="<?php echo SITE_URL . $logo; ?>?v=<?php echo time(); ?>" alt="<?php echo $siteNameEn; ?>" class="logo-banner-img">
                        </a>
                    </div>
                </div>
                <div class="col-lg-8 col-md-7 col-4">
                    <!-- Mobile Menu Toggle -->
                    <button class="mobile-menu-toggle d-lg-none" id="mobileMenuToggle" aria-label="<?php echo isEnglish() ? 'Open Menu' : 'मेनु खोल्नुहोस्'; ?>">
                        <i class="fas fa-bars"></i>
                    </button>

                    <!-- Navigation -->
                    <nav class="main-nav" id="mainNav">
                        <button class="close-menu d-lg-none" id="closeMenu">
                            <i class="fas fa-times"></i>
                        </button>
                        <ul class="nav-menu">
                            <li class="<?php echo $currentPage == 'index' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>index.php"><?php echo $L['home']; ?></a>
                            </li>
                            <li class="has-dropdown <?php echo $currentPage == 'about' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>about.php"><?php echo $L['about']; ?> <i class="fas fa-chevron-down"></i></a>
                                <ul class="dropdown">
                                    <li><a href="<?php echo SITE_URL; ?>about.php"><i class="fas fa-info-circle"></i> <?php echo isEnglish() ? 'About Us' : 'हाम्रो बारेमा'; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>about.php#history"><i class="fas fa-clock"></i> <?php echo isEnglish() ? 'History' : 'हाम्रो इतिहास'; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>about.php#vision"><i class="fas fa-eye"></i> <?php echo htmlspecialchars(isEnglish() ? $visionMissionMenuEn : $visionMissionMenuNp, ENT_QUOTES, 'UTF-8'); ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>about.php#values"><i class="fas fa-heart"></i> <?php echo htmlspecialchars(isEnglish() ? $valuesMenuLabelEn : $valuesMenuLabelNp, ENT_QUOTES, 'UTF-8'); ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>about.php#chairman"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(isEnglish() ? $chairmanMenuLabelEn : $chairmanMenuLabelNp, ENT_QUOTES, 'UTF-8'); ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>about.php#ceo-message"><i class="fas fa-user"></i> <?php echo htmlspecialchars(isEnglish() ? $ceoMenuLabelEn : $ceoMenuLabelNp, ENT_QUOTES, 'UTF-8'); ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>institutional-profile.php"><i class="fas fa-building-columns text-success me-1"></i> <?php echo isEnglish() ? 'Institutional Profile' : 'संस्थागत प्रोफाइल'; ?></a></li>
                                    <?php
                                    // Fetch dynamic pages that should show in about menu
                                    // Safe query with error handling for missing columns
                                    try {
                                        $db = getDB();
                                        // Check if show_in_menu column exists
                                        $checkCol = $db->query("SHOW COLUMNS FROM pages LIKE 'show_in_menu'");
                                        if ($checkCol && $checkCol->fetch() !== false) {
                                            $pagesStmt = $db->query("SELECT id, slug, title,
                                                COALESCE(title_en, '') as title_en,
                                                COALESCE(is_new, 0) as is_new,
                                                new_until
                                                FROM pages
                                                WHERE is_active = 1 AND show_in_menu = 1 AND menu_position = 'about'
                                                ORDER BY menu_order ASC LIMIT 5");
                                            if ($pagesStmt) {
                                                $menuPages = $pagesStmt->fetchAll();
                                                foreach ($menuPages as $mp):
                                                    $mpTitle = isEnglish() ? ($mp['title_en'] ?: $mp['title']) : ($mp['title'] ?: $mp['title_en']);
                                                    $isNewPage = !empty($mp['is_new']) && (!$mp['new_until'] || strtotime($mp['new_until']) >= time());
                                    ?>
                                    <li><a href="<?php echo SITE_URL; ?>page.php?slug=<?php echo htmlspecialchars($mp['slug']); ?>"><?php echo htmlspecialchars($mpTitle); ?><?php if ($isNewPage): ?><span class="nav-new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span><?php endif; ?></a></li>
                                    <?php endforeach; } } } catch (Exception $e) { /* Columns may not exist yet - safe to ignore */ } ?>
                                </ul>
                            </li>
                            <li class="has-dropdown <?php echo $currentPage == 'services' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>services.php"><?php echo $L['services']; ?> <i class="fas fa-chevron-down"></i></a>
                                <ul class="dropdown">
                                    <?php if (!empty($navServiceLinks)): ?>
                                        <?php foreach ($navServiceLinks as $_svc): ?>
                                            <li><a href="<?php echo SITE_URL; ?>services.php#<?php echo htmlspecialchars($_svc['anchor']); ?>"><i class="<?php echo htmlspecialchars($_svc['icon']); ?>"></i> <?php echo htmlspecialchars($_svc['title']); ?></a></li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li><a href="<?php echo SITE_URL; ?>services.php#saving"><i class="fas fa-piggy-bank"></i> <?php echo $L['saving']; ?></a></li>
                                        <li><a href="<?php echo SITE_URL; ?>services.php#loan"><i class="fas fa-hand-holding-usd"></i> <?php echo $L['loan']; ?></a></li>
                                        <li><a href="<?php echo SITE_URL; ?>services.php#remittance"><i class="fas fa-money-bill-wave"></i> <?php echo $L['remittance']; ?></a></li>
                                    <?php endif; ?>
                                    <?php
                                    // Fetch dynamic pages for services menu
                                    try {
                                        $db = getDB();
                                        $checkCol = $db->query("SHOW COLUMNS FROM pages LIKE 'show_in_menu'");
                                        if ($checkCol && $checkCol->fetch() !== false) {
                                            $servicesPagesStmt = $db->query("SELECT id, slug, title,
                                                COALESCE(title_en, '') as title_en,
                                                COALESCE(is_new, 0) as is_new,
                                                new_until
                                                FROM pages
                                                WHERE is_active = 1 AND show_in_menu = 1 AND menu_position = 'services'
                                                ORDER BY menu_order ASC LIMIT 5");
                                            if ($servicesPagesStmt) {
                                                $servicesMenuPages = $servicesPagesStmt->fetchAll();
                                                foreach ($servicesMenuPages as $sp):
                                                    $spTitle = isEnglish() ? ($sp['title_en'] ?: $sp['title']) : ($sp['title'] ?: $sp['title_en']);
                                                    $isNewPage = !empty($sp['is_new']) && (!$sp['new_until'] || strtotime($sp['new_until']) >= time());
                                    ?>
                                    <li><a href="<?php echo SITE_URL; ?>page.php?slug=<?php echo htmlspecialchars($sp['slug']); ?>"><?php echo htmlspecialchars($spTitle); ?><?php if ($isNewPage): ?><span class="nav-new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span><?php endif; ?></a></li>
                                    <?php endforeach; } } } catch (Exception $e) { /* Safe to ignore */ } ?>
                                </ul>
                            </li>
                            <li class="<?php echo $currentPage == 'interest-rates' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>interest-rates.php"><?php echo $L['interest_rates']; ?></a>
                            </li>
                            <li class="has-dropdown <?php echo in_array($currentPage, ['notices', 'cooperative-programs', 'election-information']) ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>notices.php"><?php echo $L['notices']; ?> <i class="fas fa-chevron-down"></i></a>
                                <ul class="dropdown">
                                    <li><a href="<?php echo SITE_URL; ?>notices.php"><i class="fas fa-bullhorn"></i> <?php echo isEnglish() ? 'Latest Notices' : 'नवीनतम सूचना'; ?><?php if ($hasRecentNotice): ?><span class="nav-new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span><?php endif; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>cooperative-programs.php"><i class="fas fa-calendar-check"></i> <?php echo isEnglish() ? 'Cooperative Programs' : 'सहकारी कार्यक्रम'; ?><?php if ($hasRecentProgram): ?><span class="nav-new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span><?php endif; ?><?php if ($activeProgramCount > 0): ?><span class="nav-new-badge"><?php echo (int)$activeProgramCount; ?></span><?php endif; ?></a></li>
                                    <?php if ($electionNavOn): ?>
                                    <li><a href="<?php echo SITE_URL; ?>election-information.php"><i class="fas fa-check-to-slot"></i> <?php echo htmlspecialchars($L['election_information'] ?? 'निर्वाचन जानकारी'); ?><?php if ($hasRecentElectionMilestone): ?><span class="nav-new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span><?php endif; ?></a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                            <li class="<?php echo $currentPage == 'gallery' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>gallery.php"><?php echo $L['gallery']; ?></a>
                            </li>
                            <li class="has-dropdown <?php echo in_array($currentPage, ['team', 'committees']) ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>team.php"><?php echo $L['team']; ?> <i class="fas fa-chevron-down"></i></a>
                                <ul class="dropdown">
                                    <li><a href="<?php echo SITE_URL; ?>team.php"><i class="fas fa-id-card-clip"></i> <?php echo isEnglish() ? 'Contact Officers' : 'सम्पर्क अधिकारी'; ?></a></li>
                                    <?php foreach ($navCommittees as $_nc): ?>
                                        <li><a href="<?php echo SITE_URL; ?>committees.php?id=<?php echo (int)$_nc['id']; ?>"><i class="fas fa-users-gear"></i> <?php echo isEnglish() ? htmlspecialchars($_nc['name']) : htmlspecialchars($_nc['name_np']); ?></a></li>
                                    <?php endforeach; ?>
                                    <?php if (empty($navCommittees)): ?>
                                        <li><a href="<?php echo SITE_URL; ?>committees.php"><i class="fas fa-sitemap"></i> <?php echo isEnglish() ? 'All Committees' : 'सबै समिति'; ?></a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                            <li class="has-dropdown">
                                <a href="javascript:void(0);"><?php echo isEnglish() ? 'More' : 'थप'; ?> <i class="fas fa-chevron-down"></i></a>
                                <ul class="dropdown">
                                    <li><a href="<?php echo SITE_URL; ?>news.php"><i class="fas fa-newspaper"></i> <?php echo isEnglish() ? 'News & Activities' : 'समाचार'; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>career.php"><i class="fas fa-briefcase"></i> <?php echo isEnglish() ? 'Career' : 'बिज्ञापन'; ?><?php echo nav_submenu_count_badge_html($navMenuBadges['career_open']); ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>reports.php"><i class="fas fa-chart-line"></i> <?php echo isEnglish() ? 'Reports & Publications' : 'प्रतिवेदन तथा प्रकाशनहरू'; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>downloads.php"><i class="fas fa-download"></i> <?php echo isEnglish() ? 'Downloads' : 'डाउनलोड'; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>service-centers.php"><i class="fas fa-map-marker-alt"></i> <?php echo isEnglish() ? 'Branches' : 'शाखाहरू'; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>faqs.php"><i class="fas fa-question-circle"></i> <?php echo isEnglish() ? 'FAQs' : 'प्रश्नोत्तर'; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>member-survey.php"><i class="fas fa-comment-dots"></i> <?php echo isEnglish() ? 'Suggestion Box' : 'सुझाव बक्स'; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>partner-facilities.php"><i class="fas fa-handshake"></i> <?php echo isEnglish() ? 'Partner Facilities' : 'अन्य सुविधा'; ?></a></li>
                                    <li><a href="<?php echo SITE_URL; ?>application-tracker.php"><i class="fas fa-search"></i> <?php echo isEnglish() ? 'Track Application' : 'आवेदन ट्र्याक'; ?></a></li>
                                    <?php
                                    // Fetch dynamic pages for more menu
                                    try {
                                        $db = getDB();
                                        $checkCol = $db->query("SHOW COLUMNS FROM pages LIKE 'show_in_menu'");
                                        if ($checkCol && $checkCol->fetch() !== false) {
                                            $morePagesStmt = $db->query("SELECT id, slug, title,
                                                COALESCE(title_en, '') as title_en,
                                                COALESCE(is_new, 0) as is_new,
                                                new_until
                                                FROM pages
                                                WHERE is_active = 1 AND show_in_menu = 1 AND menu_position = 'more'
                                                ORDER BY menu_order ASC LIMIT 5");
                                            if ($morePagesStmt) {
                                                $moreMenuPages = $morePagesStmt->fetchAll();
                                                foreach ($moreMenuPages as $mmp):
                                                    $mmpTitle = isEnglish() ? ($mmp['title_en'] ?: $mmp['title']) : ($mmp['title'] ?: $mmp['title_en']);
                                                    $isNewPage = !empty($mmp['is_new']) && (!$mmp['new_until'] || strtotime($mmp['new_until']) >= time());
                                    ?>
                                    <li><a href="<?php echo SITE_URL; ?>page.php?slug=<?php echo htmlspecialchars($mmp['slug']); ?>"><?php echo htmlspecialchars($mmpTitle); ?><?php if ($isNewPage): ?><span class="nav-new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span><?php endif; ?></a></li>
                                    <?php endforeach; } } } catch (Exception $e) { /* Safe to ignore */ } ?>
                                </ul>
                            </li>
                            <li class="<?php echo $currentPage == 'contact' ? 'active' : ''; ?>">
                                <a href="<?php echo SITE_URL; ?>contact.php"><?php echo $L['contact']; ?></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <!-- PFL Mobile Nav Backdrop -->
    <div class="mobile-nav-backdrop" id="pflMobileBackdrop" aria-hidden="true"></div>

    <script>/* v9.4: PFL mobile nav toggle moved to assets/js/v9-mobile-fix.js */</script>

    <?php
    // Get notices for ticker - with safe query
    $tickerNotices = [];
    try {
        $db = getDB();
        $tickerStmt = $db->query("SELECT id, title, title_np FROM notices WHERE is_active = 1 ORDER BY id DESC LIMIT 10");
        if ($tickerStmt) $tickerNotices = $tickerStmt->fetchAll() ?: [];
    } catch (Exception $e) {
        $tickerNotices = [];
    }
    ?>

    <?php if (!empty($tickerNotices)): ?>
    <!-- Notice Ticker -->
    <div class="notice-ticker">
        <div class="container">
            <div class="ticker-wrapper">
                <div class="ticker-label">
                    <i class="fas fa-bullhorn"></i>
                    <span><?php echo isEnglish() ? 'NOTICES' : 'सूचना'; ?></span>
                </div>
                <div class="ticker-content">
                    <div class="ticker-scroll">
                        <?php foreach ($tickerNotices as $index => $tNotice): ?>
                            <a href="<?php echo SITE_URL; ?>notices.php?id=<?php echo $tNotice['id']; ?>" class="ticker-item">
                                <i class="fas fa-circle"></i>
                                <?php echo isEnglish() ? ($tNotice['title'] ?: $tNotice['title_np']) : ($tNotice['title_np'] ?: $tNotice['title']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // Check for popup notices - fetch multiple for carousel
    $popupNotices = [];
    try {
        $db = getDB();
        $popupStmt = $db->query("SELECT * FROM notices WHERE is_popup = 1 AND is_active = 1 ORDER BY id DESC LIMIT 5");
        if ($popupStmt) $popupNotices = $popupStmt->fetchAll();
    } catch (Exception $e) {
        $popupNotices = [];
    }

    // Only output popup HTML if notices exist
    if (!empty($popupNotices)):
    $noticeIds = implode(',', array_column($popupNotices, 'id'));
    ?>
    <!-- Popup Notice Modal - Enhanced V3 with Carousel -->
    <div class="notice-popup-enhanced" id="noticePopup" data-notice-ids="<?php echo $noticeIds; ?>">
        <div class="popup-overlay"></div>
        <div class="popup-dialog popup-v3">
            <div class="popup-top-accent"></div>
            <div class="popup-actions-top">
                <div class="popup-doc-actions" id="popupDocActions">
                    <!-- Dynamic PDF button will be shown here -->
                </div>
                <button class="popup-close-btn" id="popupClose" title="<?php echo isEnglish() ? 'Close' : 'बन्द गर्नुहोस्'; ?>">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Carousel Container -->
            <div class="popup-carousel" id="popupCarousel">
                <?php foreach ($popupNotices as $index => $notice):
                    $attachPath = '';
                    if (!empty($notice['attachment'])) {
                        $attachPath = $notice['attachment'];
                        if (strpos($attachPath, 'uploads/') !== 0 && strpos($attachPath, '/') !== 0) {
                            $attachPath = 'uploads/notices/' . $attachPath;
                        }
                    }
                ?>
                <div class="popup-slide <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>" data-attachment="<?php echo $attachPath; ?>">
                    <div class="popup-header-text">
                        <span class="popup-badge animated"><?php echo isEnglish() ? 'Important Notice' : 'महत्त्वपूर्ण सूचना'; ?></span>
                    </div>
                    <div class="popup-content-body">
                        <h4 class="popup-title"><?php echo htmlspecialchars(isEnglish() ? ($notice['title'] ?: $notice['title_np']) : ($notice['title_np'] ?: $notice['title']), ENT_QUOTES, 'UTF-8'); ?></h4>
                        <div class="popup-text">
                            <?php echo nl2br(htmlspecialchars(isEnglish() ? ($notice['content'] ?: $notice['content_np']) : ($notice['content_np'] ?: $notice['content']), ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                        <?php if (!empty($notice['notice_date'])): ?>
                        <div class="popup-date">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo formatDate($notice['notice_date'], 'Y-m-d'); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($popupNotices) > 1): ?>
            <!-- Navigation Controls -->
            <div class="popup-nav">
                <button class="popup-nav-btn popup-prev" id="popupPrev" title="<?php echo isEnglish() ? 'Previous' : 'अघिल्लो'; ?>">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="popup-dots" id="popupDots">
                    <?php foreach ($popupNotices as $index => $notice): ?>
                    <span class="popup-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></span>
                    <?php endforeach; ?>
                </div>
                <button class="popup-nav-btn popup-next" id="popupNext" title="<?php echo isEnglish() ? 'Next' : 'अर्को'; ?>">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <!-- Progress Bar for Auto-rotate -->
            <div class="popup-progress">
                <div class="popup-progress-bar" id="popupProgressBar"></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
