<?php
require_once __DIR__ . '/../../includes/config.php';
/* Notification system — email/SMS पठाउन — सबै admin pages मा available */
require_once __DIR__ . '/../../includes/notifications.php';
/* Admin tables auto-create — DB मा tables नभएमा automatically बनाउँछ */
require_once __DIR__ . '/../includes/ensure-admin-tables.php';

/* IS_ADMIN_PAGE — admin-ui.php को security guard — यहाँ एकै पटक define गर्नुहोस् */
if (!defined('IS_ADMIN_PAGE')) define('IS_ADMIN_PAGE', true);
/* adminPageHeader() लगायत UI helper हरू सबै admin pages मा सुनिश्चित */
require_once __DIR__ . '/admin-ui.php';

// DB configured छैन भने db-setup.php मा redirect गर्नुस् (login page र db-setup बाहेक)
if (DB_NAME === '') {
    $_cur = basename($_SERVER['PHP_SELF'] ?? '');
    if ($_cur !== 'db-setup.php' && $_cur !== 'index.php') {
        header('Location: ' . ADMIN_URL . 'db-setup.php');
        exit;
    }
}

// Check if admin is logged in - prevent redirect loops
if (!isAdminLoggedIn()) {
    // Use absolute path to prevent loop
    $loginUrl = ADMIN_URL . 'index.php';
    if (!headers_sent()) {
        header("Location: " . $loginUrl);
    } else {
        echo '<script>window.location="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '";</script>';
    }
    exit();
}

$__licPage = basename($_SERVER['PHP_SELF'] ?? '');
$__licExempt = in_array($__licPage, ['index.php', 'logout.php', 'site-license.php', 'site-license-blocked.php', 'db-setup.php'], true);
/* म्याद सकिए पनि Superadmin लाई कुनै redirect छैन — मिति/नवीकरण भित्रै गर्न पाउनुपर्छ। साधारण admin मात्र blocked। */
if (!$__licExempt && function_exists('site_license_expired') && site_license_expired() && empty($_SESSION['is_superadmin'])) {
    header('Location: ' . ADMIN_URL . 'site-license-blocked.php');
    exit;
}

// Global CSRF protection for all admin POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        setFlash('error', 'सुरक्षा जाँच असफल। कृपया पुनः प्रयास गर्नुहोस्।');
        redirect($_SERVER['HTTP_REFERER'] ?? ADMIN_URL . 'dashboard.php');
    }
}

// Pre-generate CSRF token so it is available for all admin forms
$csrfToken = generateCSRFToken();

$adminName    = $_SESSION['admin_name'] ?? 'Admin';
$adminEmail   = '';
$adminPhoto   = '';
/* Superadmin check — सबै admin pages मा available */
$isSuperAdmin = !empty($_SESSION['is_superadmin']);
$currentPage  = getCurrentPage();
$siteName     = function_exists('getSetting') ? getSetting('site_name', 'आकाश सहकारी') : 'आकाश सहकारी';
$siteLogo     = function_exists('getLocalizedLogoPath')
    ? trim((string) getLocalizedLogoPath('assets/images/logo.png'))
    : (function_exists('getSetting')
        ? trim((string) getSetting('site_logo', getSetting('logo', 'assets/images/logo.png')))
        : 'assets/images/logo.png');
$hasSiteLogo  = !empty($siteLogo);
$adminLang = function_exists('getCurrentLang') ? getCurrentLang() : 'np';
$adminIsEnglish = ($adminLang === 'en');
$adminT = static function (string $np, string $en) use ($adminIsEnglish): string {
    return $adminIsEnglish ? $en : $np;
};
$adminLangQuery = $_GET;
$adminLangQuery['lang'] = $adminIsEnglish ? 'np' : 'en';
$adminLangToggleUrl = strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($adminLangQuery);
$adminLangBadge = $adminIsEnglish ? 'EN' : 'ने';

// Get unread messages count — DB नभए gracefully skip गर्छ
$db = null;
try {
    $db = getDB();
    try {
        $aid = (int)($_SESSION['admin_id'] ?? 0);
        if ($aid > 0) {
            $cols = $db->query("SHOW COLUMNS FROM admin_users")->fetchAll(PDO::FETCH_COLUMN, 0);
            $hasPhoto = in_array('photo', $cols, true) || in_array('avatar_url', $cols, true) || in_array('profile_photo', $cols, true);
            $photoExpr = "''";
            if (in_array('photo', $cols, true)) $photoExpr = "NULLIF(photo,'')";
            elseif (in_array('avatar_url', $cols, true)) $photoExpr = "NULLIF(avatar_url,'')";
            elseif (in_array('profile_photo', $cols, true)) $photoExpr = "NULLIF(profile_photo,'')";
            $stAdmin = $db->prepare("SELECT email, COALESCE({$photoExpr}, '') AS photo_path FROM admin_users WHERE id=? LIMIT 1");
            $stAdmin->execute([$aid]);
            $aRow = $stAdmin->fetch(PDO::FETCH_ASSOC) ?: [];
            $adminEmail = trim((string)($aRow['email'] ?? ''));
            $rawAdminPhoto = trim((string)($aRow['photo_path'] ?? ''));
            if ($rawAdminPhoto !== '') {
                if (preg_match('#^https?://#i', $rawAdminPhoto)) {
                    $adminPhoto = $rawAdminPhoto;
                } else {
                    $adminPhoto = SITE_URL . ltrim($rawAdminPhoto, '/');
                }
            } elseif ($adminEmail !== '') {
                $adminPhoto = 'https://www.gravatar.com/avatar/' . md5(strtolower($adminEmail)) . '?s=64&d=mp';
            }
        }
    } catch (\Throwable $e) {}
    try {
        $msgStmt = $db->query("SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0");
        $unreadMessages = $msgStmt->fetch()['count'] ?? 0;
    } catch (\Throwable $e) {
        $unreadMessages = 0;
        /* PDO जोगाउनुहोस् — अरु admin पेजले $db चाहिन्छ; मात्र unread count fail भएको हो */
    }
} catch (\Throwable $e) {
    $unreadMessages = 0;
    $db = null;
}

$adminAlertCounts = [
    'job'         => 0,
    'kyc'         => 0,
    'loan'        => 0,
    'feedback'    => 0,
    'grievance'   => 0,
    'welfare'     => 0,
    'auction'     => 0,
    'account'     => 0,
    'digital'     => 0,
    'kyc_risk'    => 0,
    'vendor'      => 0,
    'appointment' => 0,   /* नयाँ: pending भेटघाट */
    'survey'      => 0,   /* नयाँ: unread survey */
];
try {
    /* पुरानो DB मा job_applications.is_read नहुन सक्छ — fallback */
    $hasJobIsRead = function_exists('safeColumnExists') ? safeColumnExists('job_applications', 'is_read') : false;
    if ($hasJobIsRead) {
        $adminAlertCounts['job'] = (int)($db->query("SELECT COUNT(*) as count FROM job_applications WHERE is_read = 0")->fetch()['count'] ?? 0);
    } else {
        $adminAlertCounts['job'] = (int)($db->query("SELECT COUNT(*) as count FROM job_applications WHERE status = 'pending'")->fetch()['count'] ?? 0);
    }
} catch (\Throwable $e) {}
try { $adminAlertCounts['kyc'] = (int)($db->query("SELECT COUNT(*) as count FROM kyc_applications WHERE status IN ('pending','incomplete')")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['loan'] = (int)($db->query("SELECT COUNT(*) as count FROM loan_applications WHERE status = 'pending'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['feedback'] = (int)($db->query("SELECT COUNT(*) as count FROM member_feedback WHERE status = 'pending'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['grievance'] = (int)($db->query("SELECT COUNT(*) as count FROM grievances WHERE status = 'pending'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['welfare'] = (int)($db->query("SELECT COUNT(*) as count FROM member_welfare_claims WHERE status = 'pending'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['auction'] = (int)($db->query("SELECT COUNT(*) as count FROM auction_bids WHERE status = 'pending'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['vendor'] = (int)($db->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'pending'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['account'] = (int)($db->query("SELECT COUNT(*) as count FROM account_applications WHERE status = 'pending'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['digital']      = (int)($db->query("SELECT COUNT(*) as count FROM digital_service_requests WHERE status = 'pending'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['kyc_risk'] = (int)($db->query("SELECT COUNT(*) as count FROM kyc_applications WHERE status='approved' AND risk_review_status='due_review'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['appointment'] = (int)($db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'")->fetch()['count'] ?? 0); } catch (\Throwable $e) {}
try {
    /* member_survey पुरानो schema मा is_read नहुन सक्छ — fallback */
    $hasSurveyRead = function_exists('safeColumnExists') ? safeColumnExists('member_survey', 'is_read') : false;
    if ($hasSurveyRead) {
        $adminAlertCounts['survey'] = (int)($db->query("SELECT COUNT(*) as count FROM member_survey WHERE is_read = 0")->fetch()['count'] ?? 0);
    } else {
        $adminAlertCounts['survey'] = (int)($db->query("SELECT COUNT(*) as count FROM member_survey")->fetch()['count'] ?? 0);
    }
} catch (\Throwable $e) {}
/* Member Online Portal badges */
$adminAlertCounts['mem_pending']  = 0;
$adminAlertCounts['mem_resets']   = 0;
try { $adminAlertCounts['mem_pending'] = (int)($db->query("SELECT COUNT(*) FROM members WHERE approval_status='pending'")->fetchColumn() ?? 0); } catch (\Throwable $e) {}
try { $adminAlertCounts['mem_resets']  = (int)($db->query("SELECT COUNT(*) FROM member_password_reset_requests WHERE status='pending'")->fetchColumn() ?? 0); } catch (\Throwable $e) {}
$memPortalBadge = $adminAlertCounts['mem_pending'] + $adminAlertCounts['mem_resets'];

/* अस्थायी पासवर्ड — अनिवार्य परिवर्तन (public reset URL छैन)।
 * Superadmin: पासवर्ड `superadmin-config.local.php` मा राखिन्छ — UI बाट change गर्नु पर्दैन। */
$mustChangeExempt = in_array(getCurrentPage(), ['change-password', 'logout', 'index'], true)
    || !empty($_SESSION['is_superadmin']);
if (!$mustChangeExempt && $db instanceof PDO && (int) ($_SESSION['admin_id'] ?? 0) > 0) {
    try {
        if (function_exists('safeColumnExists') && safeColumnExists('admin_users', 'must_change_password')) {
            $qMc = $db->prepare('SELECT must_change_password FROM admin_users WHERE id = ? LIMIT 1');
            $qMc->execute([(int) $_SESSION['admin_id']]);
            if ((int) ($qMc->fetchColumn() ?: 0) === 1) {
                redirect(ADMIN_URL . 'change-password.php');
                exit;
            }
        }
    } catch (Throwable $e) { /* ignore */ }
}

// Determine which group the current page belongs to (for auto-open)
$pageGroups = [
    'samgri' => ['notices','designations','news','sliders','gallery','services','interest-rates','pages','pages-v2','downloads','faqs','useful-links','awards','reports','app-features','why-choose','partner-facilities'],
    'toli'   => ['team','team-karmachari','committees','info-officer','grievance-officer'],
    'rojgar' => ['careers','job-applications'],
    'aavedan'=> ['kyc','kyc-risk-reviews','loans','account-apps','digital-service-requests','auctions','auction-bids','vendor-enlistment'],
    'program' => ['programs','program-attendance','program-attendance-verify'],
    'nirvachan' => ['election-information','election-posts','election-candidates','election-results'],
    'sampark'=> ['messages','feedbacks','grievances','appointments','welfare-claims','help-center','members','member-activities'],
    'memportal'=> ['member-online-portal'],
    'sanstha'=> ['service-centers','institutional-profile','notification-settings','notification-templates','member-of-year','about-settings','satisfaction-settings','settings'],
    'prawidhi'=> ['system-info','run-migration','backup-restore','update-checklist','site-health','db-setup','site-license'],
    /* admin management pages */
    'superadmin'=> ['manage-admins','site-setup'],
];
$activeGroup = '';
foreach ($pageGroups as $group => $pages) {
    if (in_array($currentPage, $pages)) {
        $activeGroup = $group;
        break;
    }
}

/* ─────────────────────────────────────────────────────────────
   GLOBAL EXCEPTION HANDLER
   Admin pages मा uncaught DB/PHP exceptions भएमा blank page को
   सट्टा Nepali friendly error card देखाउँछ + HTML properly closes.
   ───────────────────────────────────────────────────────────── */
set_exception_handler(function (\Throwable $ex) {
    error_log('Admin page exception [' . basename($_SERVER['PHP_SELF'] ?? '') . ']: '
        . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    echo '<div class="container-fluid mt-4">'
       . '<div class="alert alert-danger border-start border-danger border-4 shadow-sm" role="alert">'
       . '<h5 class="mb-2"><i class="fas fa-triangle-exclamation me-2"></i>डेटाबेस त्रुटि भयो</h5>'
       . '<p class="mb-1">यो पृष्ठ लोड गर्दा समस्या आयो। सम्भावित कारणहरू:</p>'
       . '<ul class="mb-2 ps-3">'
       . '<li>डेटाबेस तालिका अझसम्म बनिसकेको छैन</li>'
       . '<li>डेटाबेस जडान अस्थायी रूपमा अनुपलब्ध</li>'
       . '<li>Migration अपूर्ण — <a href="run-migration.php" class="alert-link">Migration चलाउनुहोस्</a></li>'
       . '</ul>'
       . '<small class="text-muted d-block font-monospace">'
       . '<i class="fas fa-code me-1"></i>'
       . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8')
       . '</small>'
       . '</div></div>';
    @include_once __DIR__ . '/admin-footer.php';
    exit;
});
?>
<!DOCTYPE html>
<html lang="<?php echo $adminIsEnglish ? 'en' : 'ne'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo $adminT('एडमिन प्यानल', 'Admin Panel'); ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts — Mukta (UI) + Noto Sans Devanagari (नेपाली) — public/member सँग एकरूप -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <!-- Nepali Datepicker CSS (self-hosted v5) -->
    <link rel="stylesheet" href="../assets/css/nepali.datepicker.min.css">

    <!-- 🎨 Design Tokens (universal) + Admin tokens overlay — सबै panel मा एकै font/color/spacing -->
    <link rel="stylesheet" href="../assets/css/design-tokens.css?v=3">
    <link rel="stylesheet" href="../assets/css/admin-tokens.css?v=3">
    <?php @require_once __DIR__ . '/../../assets/css/_color-vars.php'; ?>

    <!-- Admin CSS -->
    <link rel="stylesheet" href="assets/admin.css?v=9.7">
    <link rel="stylesheet" href="assets/admin-modern.css?v=5.8">
    <link rel="stylesheet" href="../assets/css/v9-mobile-fix.css?v=9.7">
    <link rel="stylesheet" href="../assets/css/site-banner-logo.css?v=1">

    <style>
    /* ── Collapsible Nav Groups ── */
    .nav-group-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 15px;
        margin: 2px 10px;
        color: #1f2937;
        font-size: 0.82rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        cursor: pointer;
        border-radius: 8px;
        transition: background 0.2s, color 0.2s;
        user-select: none;
        list-style: none;
    }
    .nav-group-header:hover {
        background: #f3f4f6;
        color: #111827;
    }
    .nav-group-header.open {
        background: #ecfdf3;
        color: #14532d;
    }
    .nav-group-header .nav-group-icon {
        width: 22px;
        text-align: center;
        font-size: 0.9rem;
        opacity: 0.85;
    }
    .nav-group-header .nav-group-label {
        flex: 1;
    }
    .nav-group-header .nav-arrow {
        font-size: 0.65rem;
        transition: transform 0.25s ease;
        opacity: 0.55;
        color: #6b7280;
        order: 10;   /* सधैं सबैभन्दा दायाँ */
        flex-shrink: 0;
    }
    .nav-group-header.open .nav-arrow {
        transform: rotate(90deg);
        opacity: 1;
        color: #166534;
    }
    /* ── Group Header Badge — reference image style ── */
    .nav-group-header .group-badge {
        background: #e53e3e;
        color: #fff;
        font-size: 0.62rem;
        font-weight: 800;
        padding: 2px 7px;
        border-radius: 20px;
        min-width: 20px;
        text-align: center;
        line-height: 1.4;
        letter-spacing: 0.2px;
        box-shadow: 0 2px 6px rgba(229,62,62,0.5);
        animation: badge-pulse 2s ease-in-out infinite;
        flex-shrink: 0;
    }
    @keyframes badge-pulse {
        0%, 100% { box-shadow: 0 2px 6px rgba(229,62,62,0.5); }
        50%       { box-shadow: 0 2px 12px rgba(229,62,62,0.85); }
    }

    /* Submenu — बन्द हुँदा पूरै hide गर्ने, खुल्दा slide-down animation */
    .nav-submenu {
        list-style: none;
        padding: 0;
        margin: 0 8px 0 0;       /* sidebar width भित्रै रहोस् */
        overflow: hidden;        /* बन्द हुँदा content clip गर्ने */
        max-height: 0;           /* height 0 = completely gone */
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transform: translateY(-6px);   /* slight upward shift — हुँदा छिपिएको हैन, animate हो */
        transition: max-height 0.3s cubic-bezier(0.4,0,0.2,1),
                    opacity 0.2s ease,
                    visibility 0.2s,
                    transform 0.2s ease;
    }
    .nav-submenu.open {
        max-height: 900px;
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: translateY(0);  /* normal position मा आउँछ */
        /* subtle left border — open छ भन्ने clear indicator */
        border-left: 2px solid rgba(74,222,128,0.35);
        margin-left: 18px;
        padding-left: 2px;
    }
    .nav-submenu li {
        margin: 0 8px 0 20px;
    }
    .nav-submenu li a {
        padding: 5px 12px;
        font-size: 0.83rem;
    }
    .nav-submenu li a .nav-icon-wrap {
        width: 20px;
        text-align: center;
        font-size: 0.8rem;
    }

    /* Active badge in submenu */
    .nav-submenu li a .badge {
        margin-left: auto;
        background: #e53e3e !important;
        color: #fff !important;
        font-size: 0.6rem;
        font-weight: 800;
        padding: 2px 6px;
        border-radius: 20px;
        min-width: 18px;
        text-align: center;
        line-height: 1.4;
        box-shadow: 0 1px 4px rgba(229,62,62,0.45);
        flex-shrink: 0;
    }
    /* Make nav link flex for proper badge alignment */
    .nav-submenu li a {
        display: flex !important;
        align-items: center;
    }

    /* Section separator before group */
    .nav-group-wrap {
        margin-top: 4px;
    }

    /* Superadmin badge tag */
    .sa-label-badge {
        background: var(--primary-color);
        color: #fff;
        font-size: 0.55rem;
        font-weight: 700;
        padding: 1px 5px;
        border-radius: 7px;
        margin-left: 4px;
        flex-shrink: 0;
    }

    /* Dashboard nav item — total pending dot */
    .nav-total-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #e53e3e;
        margin-left: auto;
        flex-shrink: 0;
        box-shadow: 0 0 0 2px rgba(229,62,62,0.3);
        animation: badge-pulse 1.8s ease-in-out infinite;
    }
    
      /* ── Sidebar logo — prevent clipping, always contained ── */
      .sidebar-header {
          overflow: hidden !important;
      }
      .sidebar-brand.has-logo {
          display: flex !important;
          align-items: center !important;
          gap: 10px !important;
          padding: 8px 14px !important;
          text-decoration: none !important;
          overflow: hidden !important;
          min-width: 0 !important;
      }
      .sidebar-brand-logo {
          display: block !important;
          height: auto !important;
          width: auto !important;
          max-height: 44px !important;
          max-width: min(176px, 72%) !important;
          object-fit: contain !important;
          object-position: left center !important;
          border-radius: 6px !important;
          background: #f3f4f6 !important;
          padding: 3px 5px !important;
          flex-shrink: 0 !important;
      }
      /* No-logo fallback: show real site name text */
      .sidebar-brand.no-logo .sidebar-brand-text {
          font-size: 0.85rem;
          font-weight: 700;
          color: #1f2937;
          white-space: normal;
          word-break: break-word;
          overflow-wrap: break-word;
          line-height: 1.3;
          max-width: 160px;
      }
      /* Devanagari word-break global admin fix */
      .adm-info-group-header,
      .content-header h4, .content-header h5,
      th, label.form-label,
      .admin-page-title {
          overflow-wrap: break-word;
          word-break: break-word;
      }

      /* Primary form actions — सानो, दायाँ पङ्क्ति (पूरै चौडाइको ठूलो बटन हटाउन) */
      .admin-form-actions {
          display: flex;
          flex-wrap: wrap;
          align-items: center;
          gap: 0.5rem;
          justify-content: flex-end;
      }
      .admin-form-actions .btn {
          width: auto;
          max-width: 100%;
      }
    </style>
</head>
<body class="admin-page-<?php echo htmlspecialchars((string)$currentPage, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="<?php echo SITE_URL; ?>" class="logo sidebar-brand <?php echo $hasSiteLogo ? 'has-logo' : 'no-logo'; ?>">
                    <?php if ($hasSiteLogo): ?>
                    <img src="<?php echo SITE_URL . htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-brand-logo">
                    <?php else: ?>
                    <div class="admin-logo-fallback"><i class="fas fa-landmark"></i></div>
                    <span class="sidebar-brand-text"><?php echo $adminT('एडमिन प्यानल', 'Admin Panel'); ?></span>
                    <?php endif; ?>
                </a>
                <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <!-- ── ड्यासबोर्ड (direct link) ── -->
                    <li class="<?php echo $currentPage == 'dashboard' ? 'active' : ''; ?>">
                        <?php $__dash_total = array_sum($adminAlertCounts) + $unreadMessages; ?>
                        <a href="dashboard.php" class="sidebar-link-flex">
                            <span class="nav-icon-wrap"><i class="fas fa-gauge-high"></i></span>
                            <span class="sidebar-link-label"><?php echo $adminT('ड्यासबोर्ड', 'Dashboard'); ?></span>
                            <?php if ($__dash_total > 0): ?>
                            <span class="group-badge"><?php echo $__dash_total; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- ── Admin User Management — सबै admin ले देख्छन् ── -->
                    <li class="<?php echo $currentPage === 'manage-admins' ? 'active' : ''; ?>">
                        <a href="manage-admins.php">
                            <span class="nav-icon-wrap"><i class="fas fa-users-gear"></i></span>
                            <span><?php echo $adminT('Admin व्यवस्थापन', 'Admin Management'); ?></span>
                            <?php if (!empty($_SESSION['is_superadmin'])): ?>
                            <span class="sa-mini-badge">SA</span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- ── सामग्री ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='samgri' ? 'open' : ''; ?>" data-group="samgri">
                            <span class="nav-group-icon"><i class="fas fa-folder-open"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('सामग्री', 'Content'); ?></span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='samgri' ? 'open' : ''; ?>" id="group-samgri">
                            <li class="<?php echo $currentPage=='notices' ? 'active' : ''; ?>">
                                <a href="notices.php"><span class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></span><span><?php echo $adminT('सूचनाहरू', 'Notices'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='designations' ? 'active' : ''; ?>">
                                <a href="designations.php"><span class="nav-icon-wrap"><i class="fas fa-id-badge"></i></span><span><?php echo $adminT('पद मास्टर', 'Designation Master'); ?></span></a>
                            </li>
                            <?php /* निर्वाचन सम्बन्धी menus छुट्टै “निर्वाचन” group मा (पद Master = election-posts) */ ?>
                            <li class="<?php echo $currentPage=='news' ? 'active' : ''; ?>">
                                <a href="news.php"><span class="nav-icon-wrap"><i class="fas fa-newspaper"></i></span><span><?php echo $adminT('समाचार', 'News'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='sliders' ? 'active' : ''; ?>">
                                <a href="sliders.php"><span class="nav-icon-wrap"><i class="fas fa-images"></i></span><span><?php echo $adminT('स्लाइडर', 'Sliders'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='gallery' ? 'active' : ''; ?>">
                                <a href="gallery.php"><span class="nav-icon-wrap"><i class="fas fa-photo-film"></i></span><span><?php echo $adminT('ग्यालरी', 'Gallery'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='services' ? 'active' : ''; ?>">
                                <a href="services.php"><span class="nav-icon-wrap"><i class="fas fa-hand-holding-heart"></i></span><span><?php echo $adminT('सेवाहरू', 'Services'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='interest-rates' ? 'active' : ''; ?>">
                                <a href="interest-rates.php"><span class="nav-icon-wrap"><i class="fas fa-percent"></i></span><span><?php echo $adminT('ब्याज दर', 'Interest Rates'); ?></span></a>
                            </li>
                            <li class="<?php echo ($currentPage === 'pages-v2' && (($_GET['tab'] ?? 'dynamic') === 'dynamic')) ? 'active' : ''; ?>">
                                <a href="pages-v2.php?tab=dynamic">
                                    <span class="nav-icon-wrap"><i class="fas fa-file-lines"></i></span>
                                    <span><?php echo $adminT('गतिशील पृष्ठ', 'Dynamic Pages'); ?></span>
                                </a>
                            </li>
                            <li class="<?php echo ($currentPage === 'pages-v2' && (($_GET['tab'] ?? '') === 'static')) ? 'active' : ''; ?>">
                                <a href="pages-v2.php?tab=static">
                                    <span class="nav-icon-wrap"><i class="fas fa-layer-group"></i></span>
                                    <span><?php echo $adminT('स्थिर पृष्ठ', 'Static Pages'); ?></span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='downloads' ? 'active' : ''; ?>">
                                <a href="downloads.php"><span class="nav-icon-wrap"><i class="fas fa-file-arrow-down"></i></span><span><?php echo $adminT('डाउनलोड', 'Downloads'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='faqs' ? 'active' : ''; ?>">
                                <a href="faqs.php"><span class="nav-icon-wrap"><i class="fas fa-circle-question"></i></span><span><?php echo $adminT('प्रश्नोत्तर (FAQs)', 'FAQs'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='useful-links' ? 'active' : ''; ?>">
                                <a href="useful-links.php"><span class="nav-icon-wrap"><i class="fas fa-link"></i></span><span><?php echo $adminT('उपयोगी लिंकहरू', 'Useful Links'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='awards' ? 'active' : ''; ?>">
                                <a href="awards.php"><span class="nav-icon-wrap"><i class="fas fa-trophy"></i></span><span><?php echo $adminT('सम्मान/पुरस्कार', 'Awards'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='reports' ? 'active' : ''; ?>">
                                <a href="reports.php"><span class="nav-icon-wrap"><i class="fas fa-chart-column"></i></span><span><?php echo $adminT('प्रतिवेदन', 'Reports'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='app-features' ? 'active' : ''; ?>">
                                <a href="app-features.php"><span class="nav-icon-wrap"><i class="fas fa-mobile-screen"></i></span><span><?php echo $adminT('एप सुविधाहरू', 'App Features'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='why-choose' ? 'active' : ''; ?>">
                                <a href="why-choose.php"><span class="nav-icon-wrap"><i class="fas fa-star nav-icon-accent nav-icon-gold"></i></span><span><?php echo $adminT('किन हामीलाई छान्ने?', 'Why Choose Us'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='partner-facilities' ? 'active' : ''; ?>">
                                <a href="partner-facilities.php"><span class="nav-icon-wrap"><i class="fas fa-handshake nav-icon-accent nav-icon-primary-soft"></i></span><span><?php echo $adminT('साझेदार सुविधा', 'Partner Facilities'); ?></span></a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── टोली ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='toli' ? 'open' : ''; ?>" data-group="toli">
                            <span class="nav-group-icon"><i class="fas fa-users"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('टोली', 'Team'); ?></span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='toli' ? 'open' : ''; ?>" id="group-toli">
                            <li class="<?php echo $currentPage=='team' ? 'active' : ''; ?>">
                                <a href="team.php"><span class="nav-icon-wrap"><i class="fas fa-building-columns"></i></span><span><?php echo $adminT('सञ्चालक / समिति', 'Directors / Committee'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='team-karmachari' ? 'active' : ''; ?>">
                                <a href="team-karmachari.php"><span class="nav-icon-wrap"><i class="fas fa-user-tie"></i></span><span><?php echo $adminT('कर्मचारी / व्यवस्थापन', 'Staff / Management'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='committees' ? 'active' : ''; ?>">
                                <a href="committees.php"><span class="nav-icon-wrap"><i class="fas fa-people-group"></i></span><span><?php echo $adminT('समिति/उपसमिति', 'Committee/Subcommittee'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='info-officer' ? 'active' : ''; ?>">
                                <a href="info-officer.php"><span class="nav-icon-wrap"><i class="fas fa-user-shield nav-icon-accent nav-icon-cyan"></i></span><span><?php echo $adminT('सूचना अधिकारी (RTI)', 'Information Officer (RTI)'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='grievance-officer' ? 'active' : ''; ?>">
                                <a href="grievance-officer.php"><span class="nav-icon-wrap"><i class="fas fa-user-tie nav-icon-accent nav-icon-purple"></i></span><span><?php echo $adminT('गुनासो अधिकारी', 'Grievance Officer'); ?></span></a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── रोजगारी ── -->
                    <li class="nav-group-wrap">
                        <?php $rojgarBadge = $adminAlertCounts['job']; ?>
                        <div class="nav-group-header <?php echo $activeGroup=='rojgar' ? 'open' : ''; ?>" data-group="rojgar">
                            <span class="nav-group-icon"><i class="fas fa-briefcase"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('रोजगारी', 'Career'); ?></span>
                            <?php if ($rojgarBadge > 0): ?><span class="group-badge"><?php echo $rojgarBadge; ?></span><?php endif; ?>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='rojgar' ? 'open' : ''; ?>" id="group-rojgar">
                            <li class="<?php echo $currentPage=='careers' ? 'active' : ''; ?>">
                                <a href="careers.php"><span class="nav-icon-wrap"><i class="fas fa-briefcase"></i></span><span><?php echo $adminT('रोजगारी पोस्ट', 'Career Posts'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='job-applications' ? 'active' : ''; ?>">
                                <a href="job-applications.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-file-circle-check"></i></span>
                                    <span><?php echo $adminT('जागिर आवेदन', 'Job Applications'); ?></span>
                                    <?php if ($adminAlertCounts['job'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['job']; ?></span><?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── आवेदनहरू ── -->
                    <li class="nav-group-wrap">
                        <?php $aavedan_total = $adminAlertCounts['kyc'] + $adminAlertCounts['kyc_risk'] + $adminAlertCounts['loan'] + $adminAlertCounts['account'] + $adminAlertCounts['digital'] + $adminAlertCounts['auction'] + $adminAlertCounts['vendor']; ?>
                        <div class="nav-group-header <?php echo $activeGroup=='aavedan' ? 'open' : ''; ?>" data-group="aavedan">
                            <span class="nav-group-icon"><i class="fas fa-inbox"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('आवेदनहरू', 'Applications'); ?></span>
                            <?php if ($aavedan_total > 0): ?><span class="group-badge"><?php echo $aavedan_total; ?></span><?php endif; ?>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='aavedan' ? 'open' : ''; ?>" id="group-aavedan">
                            <li class="<?php echo $currentPage=='kyc' ? 'active' : ''; ?>">
                                <a href="kyc-applications.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-id-card-clip"></i></span>
                                    <span><?php echo $adminT('KYC आवेदन', 'KYC Applications'); ?></span>
                                    <?php if ($adminAlertCounts['kyc'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['kyc']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='kyc-risk-reviews' ? 'active' : ''; ?>">
                                <a href="kyc-risk-reviews.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-shield-halved"></i></span>
                                    <span><?php echo $adminT('KYC जोखिम समीक्षा', 'KYC Risk Review'); ?></span>
                                    <?php if ($adminAlertCounts['kyc_risk'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['kyc_risk']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='loans' ? 'active' : ''; ?>">
                                <a href="loan-applications.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-hand-holding-dollar"></i></span>
                                    <span><?php echo $adminT('ऋण आवेदन', 'Loan Applications'); ?></span>
                                    <?php if ($adminAlertCounts['loan'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['loan']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='account-apps' ? 'active' : ''; ?>">
                                <a href="account-applications.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-user-plus"></i></span>
                                    <span><?php echo $adminT('खाता आवेदन', 'Account Applications'); ?></span>
                                    <?php if ($adminAlertCounts['account'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['account']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='digital-service-requests' ? 'active' : ''; ?>">
                                <a href="digital-service-requests.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-laptop-code"></i></span>
                                    <span><?php echo $adminT('डिजिटल सेवा', 'Digital Services'); ?></span>
                                    <?php if ($adminAlertCounts['digital'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['digital']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='auctions' ? 'active' : ''; ?>">
                                <a href="auctions.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-gavel"></i></span>
                                    <span><?php echo $adminT('लिलामी / बोलपत्र', 'Auction / Bids'); ?></span>
                                    <?php if ($adminAlertCounts['auction'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['auction']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='auction-bids' ? 'active' : ''; ?>">
                                <a href="auction-bids.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-list-ol"></i></span>
                                    <span><?php echo $adminT('बोलपत्र सूची', 'Bid List'); ?></span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='vendor-enlistment' ? 'active' : ''; ?>">
                                <a href="vendor-enlistment.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-store"></i></span>
                                    <span><?php echo $adminT('भेन्डर सूचीकरण', 'Vendor Enlistment'); ?></span>
                                    <?php if ($adminAlertCounts['vendor'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['vendor']; ?></span><?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── कार्यक्रम व्यवस्थापन (All program tools) ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='program' ? 'open' : ''; ?>" data-group="program">
                            <span class="nav-group-icon"><i class="fas fa-calendar-check"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('कार्यक्रम व्यवस्थापन', 'Program Management'); ?></span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='program' ? 'open' : ''; ?>" id="group-program">
                            <li class="<?php echo $currentPage=='programs' ? 'active' : ''; ?>">
                                <a href="programs.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-calendar-plus"></i></span>
                                    <span><?php echo $adminT('कार्यक्रम बनाउने / सूची', 'Program Create / List'); ?></span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='program-attendance-verify' ? 'active' : ''; ?>">
                                <a href="../program-attendance-verify.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-user-check"></i></span>
                                    <span><?php echo $adminT('उपस्थिति प्रमाणिकरण', 'Attendance Verify'); ?></span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='program-attendance' ? 'active' : ''; ?>">
                                <a href="program-attendance.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-clipboard-check"></i></span>
                                    <span><?php echo $adminT('उपस्थिति रिपोर्ट', 'Attendance Report'); ?></span>
                                </a>
                            </li>
                            <?php /* निर्वाचन जानकारी छुट्टै group मा सरेको */ ?>
                            <li class="<?php echo $currentPage=='analytics' ? 'active' : ''; ?>">
                                <a href="analytics.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-chart-line"></i></span>
                                    <span><?php echo $adminT('विश्लेषण ड्यासबोर्ड', 'Analytics Dashboard'); ?></span>
                                </a>
                            </li>
                        </ul>
                    </li>


                    <!-- ── निर्वाचन (छुट्टै group) ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='nirvachan' ? 'open' : ''; ?>" data-group="nirvachan">
                            <span class="nav-group-icon"><i class="fas fa-check-to-slot"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('निर्वाचन', 'Election'); ?></span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='nirvachan' ? 'open' : ''; ?>" id="group-nirvachan">
                            <li class="<?php echo $currentPage=='election-information' ? 'active' : ''; ?>">
                                <a href="election-information.php"><span class="nav-icon-wrap"><i class="fas fa-circle-info"></i></span><span><?php echo $adminT('निर्वाचन जानकारी', 'Election Information'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='election-posts' ? 'active' : ''; ?>">
                                <a href="election-posts.php"><span class="nav-icon-wrap"><i class="fas fa-briefcase"></i></span><span><?php echo $adminT('पद Master', 'Post Master'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='election-candidates' ? 'active' : ''; ?>">
                                <a href="election-candidates.php"><span class="nav-icon-wrap"><i class="fas fa-user-tie"></i></span><span><?php echo $adminT('उम्मेदवार/पद', 'Candidates/Posts'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='election-results' ? 'active' : ''; ?>">
                                <a href="election-results.php"><span class="nav-icon-wrap"><i class="fas fa-chart-bar"></i></span><span><?php echo $adminT('निर्वाचन नतिजा', 'Election Results'); ?></span></a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── सम्पर्क / संचार ── -->
                    <li class="nav-group-wrap">
                        <?php $sampark_total = $unreadMessages + $adminAlertCounts['feedback'] + $adminAlertCounts['grievance'] + $adminAlertCounts['welfare'] + $adminAlertCounts['appointment']; ?>
                        <div class="nav-group-header <?php echo $activeGroup=='sampark' ? 'open' : ''; ?>" data-group="sampark">
                            <span class="nav-group-icon"><i class="fas fa-comments"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('सम्पर्क', 'Contact'); ?></span>
                            <?php if ($sampark_total > 0): ?><span class="group-badge"><?php echo $sampark_total; ?></span><?php endif; ?>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='sampark' ? 'open' : ''; ?>" id="group-sampark">
                            <li class="<?php echo $currentPage=='messages' ? 'active' : ''; ?>">
                                <a href="messages.php" class="sidebar-link-flex">
                                    <span class="nav-icon-wrap"><i class="fas fa-envelope-open-text"></i></span>
                                    <span class="sidebar-link-label"><?php echo $adminT('सन्देशहरू', 'Messages'); ?></span>
                                    <?php if ($unreadMessages > 0): ?><span class="badge"><?php echo $unreadMessages; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='feedbacks' ? 'active' : ''; ?>">
                                <a href="feedbacks.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-comments"></i></span>
                                    <span><?php echo $adminT('सुझाव/प्रतिक्रिया', 'Feedback'); ?></span>
                                    <?php if ($adminAlertCounts['feedback'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['feedback']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='grievances' ? 'active' : ''; ?>">
                                <a href="grievances.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-triangle-exclamation"></i></span>
                                    <span><?php echo $adminT('गुनासो', 'Grievances'); ?></span>
                                    <?php if ($adminAlertCounts['grievance'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['grievance']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='appointments' ? 'active' : ''; ?>">
                                <a href="appointments.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-calendar-check"></i></span>
                                    <span><?php echo $adminT('भेटघाट', 'Appointments'); ?></span>
                                    <?php if ($adminAlertCounts['appointment'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['appointment']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='welfare-claims' ? 'active' : ''; ?>">
                                <a href="welfare-claims.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-heart-circle-plus"></i></span>
                                    <span><?php echo $adminT('कल्याण दाबी', 'Welfare Claims'); ?></span>
                                    <?php if ($adminAlertCounts['welfare'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['welfare']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='help-center' ? 'active' : ''; ?>">
                                <a href="help-center.php"><span class="nav-icon-wrap"><i class="fas fa-headset"></i></span><span><?php echo $adminT('सहायता केन्द्र', 'Help Center'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='members' ? 'active' : ''; ?>">
                                <a href="members.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-user-check nav-icon-accent nav-icon-primary"></i></span>
                                    <span><?php echo $adminT('सदस्य पोर्टल', 'Member Portal'); ?></span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='member-activities' ? 'active' : ''; ?>">
                                <a href="member-activities.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-magnifying-glass-chart nav-icon-accent nav-icon-amber"></i></span>
                                    <span><?php echo $adminT('सदस्य गतिविधि खोज', 'Member Activities Search'); ?></span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── Member Online Portal ── -->
                    <li class="nav-group-wrap">
                        <?php $memPortalBadgeTotal = $memPortalBadge ?? 0; ?>
                        <div class="nav-group-header <?php echo $activeGroup=='memportal' ? 'open' : ''; ?>" data-group="memportal">
                            <span class="nav-group-icon"><i class="fas fa-globe"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('सदस्य अनलाइन पोर्टल', 'Member Online Portal'); ?></span>
                            <?php if ($memPortalBadgeTotal > 0): ?><span class="group-badge"><?php echo $memPortalBadgeTotal; ?></span><?php endif; ?>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='memportal' ? 'open' : ''; ?>" id="group-memportal">
                            <li class="<?php echo $currentPage=='member-online-portal' ? 'active' : ''; ?>">
                                <a href="member-online-portal.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-users-line nav-icon-accent nav-icon-primary"></i></span>
                                    <span><?php echo $adminT('दर्ता अनुमोदन', 'Registration Approval'); ?></span>
                                    <?php if (!empty($adminAlertCounts['mem_pending'])): ?><span class="badge"><?php echo $adminAlertCounts['mem_pending']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo ($currentPage=='member-online-portal' && ($_GET['tab'] ?? '')=='resets') ? 'active' : ''; ?>">
                                <a href="member-online-portal.php?tab=resets">
                                    <span class="nav-icon-wrap"><i class="fas fa-key nav-icon-accent nav-icon-amber"></i></span>
                                    <span><?php echo $adminT('पासवर्ड Reset', 'Password Reset'); ?></span>
                                    <?php if (!empty($adminAlertCounts['mem_resets'])): ?><span class="badge"><?php echo $adminAlertCounts['mem_resets']; ?></span><?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── संस्था ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='sanstha' ? 'open' : ''; ?>" data-group="sanstha">
                            <span class="nav-group-icon"><i class="fas fa-building-columns"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('संस्था', 'Institution'); ?></span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='sanstha' ? 'open' : ''; ?>" id="group-sanstha">
                            <li class="<?php echo $currentPage=='service-centers' ? 'active' : ''; ?>">
                                <a href="service-centers.php"><span class="nav-icon-wrap"><i class="fas fa-location-dot"></i></span><span><?php echo $adminT('शाखाहरू', 'Branches'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='institutional-profile' ? 'active' : ''; ?>">
                                <a href="institutional-profile.php"><span class="nav-icon-wrap"><i class="fas fa-building-columns"></i></span><span><?php echo $adminT('संस्थागत प्रोफाइल', 'Institutional Profile'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='notification-settings' ? 'active' : ''; ?>">
                                <a href="notification-settings.php">
                                <span class="nav-icon-wrap"><i class="fas fa-bell"></i></span>
                                <span><?php echo $adminT('सूचना सेटिङ्स', 'Notification Settings'); ?></span>
                            </a>
                            </li>
                            <li class="<?php echo $currentPage=='notification-templates' ? 'active' : ''; ?>">
                                <a href="notification-templates.php">
                                <span class="nav-icon-wrap"><i class="fas fa-envelope-open-text nav-icon-accent nav-icon-violet"></i></span>
                                <span><?php echo $adminT('सूचना Templates', 'Notification Templates'); ?></span>
                            </a>
                            </li>
                            <li class="<?php echo $currentPage=='member-of-year' ? 'active' : ''; ?>">
                                <a href="member-of-year.php"><span class="nav-icon-wrap"><i class="fas fa-trophy nav-icon-accent nav-icon-gold"></i></span><span><?php echo $adminT('वर्षको सर्वश्रेष्ठ सदस्य', 'Member of the Year'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='about-settings' ? 'active' : ''; ?>">
                                <a href="about-settings.php"><span class="nav-icon-wrap"><i class="fas fa-landmark"></i></span><span><?php echo $adminT('बारेमा पृष्ठ', 'About Page'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='satisfaction-settings' ? 'active' : ''; ?>">
                                <a href="satisfaction-settings.php"><span class="nav-icon-wrap"><i class="fas fa-smile nav-icon-accent nav-icon-pink"></i></span><span><?php echo $adminT('सन्तुष्टि Widget', 'Satisfaction Widget'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='settings' ? 'active' : ''; ?>">
                                <a href="settings.php"><span class="nav-icon-wrap"><i class="fas fa-sliders"></i></span><span><?php echo $adminT('सेटिङ्स', 'Settings'); ?></span></a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── प्रविधि ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='prawidhi' ? 'open' : ''; ?>" data-group="prawidhi">
                            <span class="nav-group-icon"><i class="fas fa-server"></i></span>
                            <span class="nav-group-label"><?php echo $adminT('प्रविधि', 'Technical'); ?></span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='prawidhi' ? 'open' : ''; ?>" id="group-prawidhi">
                            <li class="<?php echo $currentPage=='system-info' ? 'active' : ''; ?>">
                                <a href="system-info.php"><span class="nav-icon-wrap"><i class="fas fa-server"></i></span><span><?php echo $adminT('प्रणाली जानकारी', 'System Info'); ?></span></a>
                            </li>
                            <?php if (!empty($_SESSION['is_superadmin'])): ?>
                            <li class="<?php echo $currentPage=='run-migration' ? 'active' : ''; ?>">
                                <a href="run-migration.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-database"></i></span>
                                    <span><?php echo $adminT('डेटाबेस Migration', 'Database Migration'); ?></span>
                                    <span class="sa-label-badge">SA</span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='backup-restore' ? 'active' : ''; ?>">
                                <a href="backup-restore.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-shield-alt"></i></span>
                                    <span><?php echo $adminT('ब्याकअप / पुनर्स्थापना', 'Backup / Restore'); ?></span>
                                    <span class="sa-label-badge">SA</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li class="<?php echo $currentPage=='update-checklist' ? 'active' : ''; ?>">
                                <a href="update-checklist.php"><span class="nav-icon-wrap"><i class="fas fa-list-check"></i></span><span><?php echo $adminT('अपडेट सूची', 'Update Checklist'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='site-health' ? 'active' : ''; ?>">
                                <a href="site-health.php"><span class="nav-icon-wrap"><i class="fas fa-heart-pulse"></i></span><span><?php echo $adminT('साइट स्वास्थ्य', 'Site Health'); ?></span></a>
                            </li>
                            <li class="<?php echo $currentPage=='error-log' ? 'active' : ''; ?>">
                                <a href="error-log.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-bug nav-icon-accent nav-icon-red"></i></span>
                                    <span><?php echo $adminT('त्रुटि लग', 'Error Log'); ?></span>
                                </a>
                            </li>
                            <!-- v5: In-app User Manual / Help & Guide (non-developer friendly) -->
                            <li class="<?php echo ($currentPage=='help-guide' || $currentPage=='help-center') ? 'active' : ''; ?>">
                                <a href="help-center.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-book-open nav-icon-accent nav-icon-green"></i></span>
                                    <span><?php echo $adminT('📖 सहायता / Help', '📖 Help / Guide'); ?></span>
                                </a>
                            </li>
                            <!-- Site Setup Manager — setup.php को काम admin panel भित्रबाट -->
                            <li class="<?php echo $currentPage=='site-setup' ? 'active' : ''; ?>">
                                <a href="site-setup.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-sliders"></i></span>
                                    <span><?php echo $adminT('साइट सेटअप', 'Site Setup'); ?></span>
                                </a>
                            </li>
                            <!-- साइट म्याद — Superadmin only -->
                            <?php if (!empty($_SESSION['is_superadmin'])): ?>
                            <li class="<?php echo $currentPage=='site-license' ? 'active' : ''; ?>">
                                <a href="site-license.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-calendar-check nav-icon-accent nav-icon-amber"></i></span>
                                    <span><?php echo $adminT('साइट म्याद', 'Site License'); ?></span>
                                    <span class="sa-label-badge">SA</span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='db-setup' ? 'active' : ''; ?>">
                                <a href="db-setup.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-database nav-icon-accent nav-icon-primary-soft"></i></span>
                                    <span><?php echo $adminT('DB सेटअप', 'DB Setup'); ?></span>
                                    <span class="sa-label-badge">SA</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </li>

                </ul>
            </nav>

            <!-- v9.6 — sidebar "Website हेर्नुहोस्" link removed per request -->

            <!-- Sidebar user strip — logged-in admin को नाम तल देखाउँछ -->
            <div class="sidebar-user-strip">
                <div class="sidebar-user-avatar sidebar-user-avatar-media">
                    <?php if ($adminPhoto !== ''): ?>
                        <img src="<?php echo htmlspecialchars($adminPhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="Admin"
                             class="sidebar-user-avatar-img"
                             onerror="this.style.display='none';this.parentNode.innerHTML='<i class=&quot;fas fa-user sidebar-user-fallback-icon&quot;></i>';">
                    <?php else: ?>
                        <i class="fas fa-user sidebar-user-fallback-icon"></i>
                    <?php endif; ?>
                </div>
                <div class="sidebar-user-meta">
                    <div class="sidebar-user-name">
                        <?php echo htmlspecialchars($adminName ?? 'Admin'); ?>
                    </div>
                    <div class="sidebar-user-role">
                        <?php echo !empty($_SESSION['is_superadmin']) ? $adminT('सुपर एडमिन', 'Superadmin') : $adminT('प्रशासक', 'Administrator'); ?>
                    </div>
                </div>
                <a href="logout.php" title="<?php echo $adminT('लगआउट', 'Logout'); ?>" class="sidebar-strip-logout">
                    <i class="fas fa-right-from-bracket"></i>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navbar -->
            <header class="admin-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <a href="<?php echo SITE_URL; ?>" class="admin-topbar-brand <?php echo $hasSiteLogo ? 'has-logo' : 'no-logo'; ?>">
                        <?php if ($hasSiteLogo): ?>
                        <img src="<?php echo SITE_URL . htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php else: ?>
                        <div class="admin-logo-fallback"><i class="fas fa-landmark"></i></div>
                        <span class="brand-text"><?php echo $adminT('एडमिन', 'Admin'); ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="page-title-wrap">
                        <h1 class="page-title"><?php echo $pageTitle ?? $adminT('ड्यासबोर्ड', 'Dashboard'); ?></h1>
                        <span class="header-date-pill" title="<?php echo $adminT('आजको मिति', 'Today'); ?>">
                            <i class="fas fa-calendar-day"></i>
                            <?php echo function_exists('formatNepaliDate') ? formatNepaliDate(date('Y-m-d')) : date('Y-m-d'); ?>
                        </span>
                    </div>
                </div>

                <div class="header-right">
                    <div class="header-item d-none d-md-flex">
                        <a href="<?php echo htmlspecialchars($adminLangToggleUrl, ENT_QUOTES, 'UTF-8'); ?>" class="version-badge text-decoration-none" title="<?php echo $adminT('भाषा परिवर्तन', 'Switch Language'); ?>">
                            <i class="fas fa-language"></i>
                            <?php echo $adminLangBadge; ?>
                        </a>
                    </div>
                    <!-- Website version badge -->
                    <?php
                    $siteVer = getSetting('site_version') ?? '1.0.0';
                    ?>
                    <div class="header-item d-none d-md-flex">
                        <!-- Version badge — click गर्यो भने admin/settings.php#version मा जान्छ -->
                        <a href="<?php echo ADMIN_URL; ?>settings.php#version"
                           class="version-badge text-decoration-none"
                           title="क्लिक गर्नुहोस् — Version Settings मा अपडेट गर्न सकिन्छ">
                            <i class="fas fa-code-branch"></i>
                            v<?php echo htmlspecialchars($siteVer); ?>
                            <i class="fas fa-pen-to-square edit-hint"></i>
                        </a>
                    </div>

                    <!-- Notifications bell — clickable dropdown -->
                    <?php
                    $totalAlerts = array_sum($adminAlertCounts) + $unreadMessages;

                    /* Dropdown मा देखाउने items — label, count, link, icon, tone */
                    $notifItems = [
                        ['label'=>$adminT('अपठित सन्देश', 'Unread Messages'),     'count'=>$unreadMessages,                        'href'=>'messages.php',                       'icon'=>'fa-envelope',            'tone'=>'red'],
                        ['label'=>$adminT('KYC आवेदन', 'KYC Applications'),        'count'=>$adminAlertCounts['kyc'],               'href'=>'kyc-applications.php?status=pending', 'icon'=>'fa-id-card',             'tone'=>'orange'],
                        ['label'=>$adminT('ऋण आवेदन', 'Loan Applications'),         'count'=>$adminAlertCounts['loan'],              'href'=>'loan-applications.php?status=pending','icon'=>'fa-hand-holding-usd',   'tone'=>'amber'],
                        ['label'=>$adminT('खाता आवेदन', 'Account Applications'),       'count'=>$adminAlertCounts['account'],           'href'=>'account-applications.php?status=pending','icon'=>'fa-university',       'tone'=>'purple'],
                        ['label'=>$adminT('डिजिटल सेवा', 'Digital Services'),      'count'=>$adminAlertCounts['digital'],           'href'=>'digital-service-requests.php?status=pending','icon'=>'fa-mobile-alt', 'tone'=>'cyan'],
                        ['label'=>$adminT('जागिर आवेदन', 'Job Applications'),      'count'=>$adminAlertCounts['job'],               'href'=>'job-applications.php?status=pending', 'icon'=>'fa-briefcase',           'tone'=>'green'],
                        ['label'=>$adminT('गुनासो', 'Grievances'),            'count'=>$adminAlertCounts['grievance'],         'href'=>'grievances.php?status=pending',       'icon'=>'fa-comment-dots',        'tone'=>'red'],
                        ['label'=>$adminT('सुझाव/प्रतिक्रिया', 'Feedback'), 'count'=>$adminAlertCounts['feedback'],          'href'=>'feedbacks.php?status=pending',        'icon'=>'fa-star',                'tone'=>'orange'],
                        ['label'=>$adminT('कल्याण दाबी', 'Welfare Claims'),      'count'=>$adminAlertCounts['welfare'],           'href'=>'welfare-claims.php?status=pending',   'icon'=>'fa-hand-holding-heart',  'tone'=>'teal'],
                        ['label'=>$adminT('लिलामी बिड', 'Auction Bids'),       'count'=>$adminAlertCounts['auction'],           'href'=>'auction-bids.php',                    'icon'=>'fa-gavel',               'tone'=>'slate'],
                        ['label'=>$adminT('भेन्डर आवेदन', 'Vendor Requests'),      'count'=>$adminAlertCounts['vendor'],            'href'=>'vendor-enlistment.php?status=pending','icon'=>'fa-store',               'tone'=>'blue'],
                    ];
                    /* pending मात्र filter गर्ने — count > 0 भएका मात्र dropdown मा देखाउने */
                    $activeNotifs = array_filter($notifItems, function ($i) {
                        return ($i['count'] ?? 0) > 0;
                    });
                    ?>
                    <div class="header-item notif-wrapper">
                        <!-- Bell button — सधैं देखिन्छ, count > 0 भए red badge -->
                        <button type="button" class="notification-bell notif-toggle-btn"
                                title="<?php echo $adminT('सूचनाहरू', 'Notifications'); ?> — <?php echo $totalAlerts > 0 ? $totalAlerts . ' pending' : $adminT('सबै हेरिएको', 'All clear'); ?>"
                                aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($totalAlerts > 0): ?>
                            <span class="notif-count"><?php echo $totalAlerts; ?></span>
                            <?php endif; ?>
                        </button>

                        <!-- Dropdown panel -->
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-dropdown-header">
                                <span><i class="fas fa-bell me-1"></i><?php echo $adminT('सूचनाहरू', 'Notifications'); ?></span>
                                <?php if ($totalAlerts > 0): ?>
                                <span class="notif-total-badge"><?php echo $totalAlerts; ?> <?php echo $adminT('पेन्डिङ', 'pending'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="notif-dropdown-body">
                                <?php if (empty($activeNotifs)): ?>
                                <div class="notif-empty">
                                    <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                    <p class="mb-0"><?php echo $adminT('सबै हेरिएको छ!', 'All caught up!'); ?></p>
                                </div>
                                <?php else: ?>
                                <?php foreach ($activeNotifs as $ni): ?>
                                <a href="<?php echo ADMIN_URL . htmlspecialchars($ni['href']); ?>"
                                   class="notif-item">
                                    <span class="notif-item-icon notif-tone-<?php echo htmlspecialchars($ni['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas <?php echo $ni['icon']; ?>"></i>
                                    </span>
                                    <span class="notif-item-label"><?php echo $ni['label']; ?></span>
                                    <span class="notif-item-count notif-tone-bg-<?php echo htmlspecialchars($ni['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo $ni['count']; ?>
                                    </span>
                                </a>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="notif-dropdown-footer">
                                <a href="<?php echo ADMIN_URL; ?>dashboard.php">
                                    <i class="fas fa-th-large me-1"></i><?php echo $adminT('ड्यासबोर्ड हेर्नुहोस्', 'Open Dashboard'); ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="header-item">
                        <div class="admin-info">
                            <span class="admin-name admin-name-inline">
                                <?php if ($adminPhoto !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($adminPhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="Admin"
                                         class="admin-avatar-sm"
                                         onerror="this.style.display='none';">
                                <?php endif; ?>
                                <?php echo htmlspecialchars($adminName); ?>
                                <?php if (!empty($_SESSION['is_superadmin'])): ?>
                                    <!-- Superadmin badge — superadmin login गर्दा देखिन्छ -->
                                    <span class="superadmin-pill"><?php echo $adminT('सुपर एडमिन', 'SUPERADMIN'); ?></span>
                                <?php endif; ?>
                            </span>
                            <div class="admin-menu">
                                <?php if (empty($_SESSION['is_superadmin'])): ?>
                                    <!-- Normal admin मात्र profile र change-password देख्छ -->
                                    <a href="profile.php"><i class="fas fa-user"></i> <?php echo $adminT('प्रोफाइल', 'Profile'); ?></a>
                                    <a href="change-password.php"><i class="fas fa-key"></i> <?php echo $adminT('पासवर्ड', 'Password'); ?></a>
                                <?php else: ?>
                                    <!-- Superadmin को लागि admin management link -->
                                    <a href="manage-admins.php"><i class="fas fa-users-gear"></i> <?php echo $adminT('Admin व्यवस्थापन', 'Admin Management'); ?></a>
                                <?php endif; ?>
                                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <?php echo $adminT('लगआउट', 'Logout'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

          <!-- Breadcrumb hटाइएको — space बचाउनुस् -->

              <!-- Flash Messages — icon थपिएको, modern border-left style -->
              <?php
              $flash = getFlash();
              if ($flash):
                  $fType  = $flash['type'] === 'error' ? 'danger' : ($flash['type'] ?: 'info');
                  $fIcons = ['success' => 'fa-circle-check', 'danger' => 'fa-circle-xmark',
                             'warning' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'];
                  $fIcon  = $fIcons[$fType] ?? 'fa-circle-info';
              ?>
              <div class="alert alert-<?php echo $fType; ?> alert-dismissible fade show mx-3 mt-3" role="alert">
                  <i class="fas <?php echo $fIcon; ?> fa-fw flex-shrink-0"></i>
                  <span><?php echo htmlspecialchars($flash['message']); ?></span>
                  <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
              </div>
              <?php endif; ?>

          <!-- Page content wrapper — admin-footer.php मा </div> छ -->
          <div class="page-content">

          <!-- Page content starts here -->

    <script>
    // ── Collapsible Nav Groups ──
    document.addEventListener('DOMContentLoaded', function () {
        var headers = document.querySelectorAll('.nav-group-header');
        headers.forEach(function (header) {
            var group = header.getAttribute('data-group');
            var submenu = document.getElementById('group-' + group);
            if (!submenu) return;

            header.addEventListener('click', function () {
                var isOpen = header.classList.contains('open');
                // Close all others
                headers.forEach(function (h) {
                    h.classList.remove('open');
                    var g = h.getAttribute('data-group');
                    var s = document.getElementById('group-' + g);
                    if (s) s.classList.remove('open');
                });
                // Toggle clicked
                if (!isOpen) {
                    header.classList.add('open');
                    submenu.classList.add('open');
                }
            });
        });
    });

    // ── Notification Bell Dropdown Toggle ──
    (function () {
        var btn   = document.querySelector('.notif-toggle-btn');
        var panel = document.getElementById('notifDropdown');
        if (!btn || !panel) return;

        /* Bell click — dropdown खोल्नुहोस् / बन्द गर्नुहोस् */
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = panel.style.display === 'block';
            panel.style.display = isOpen ? 'none' : 'block';
            btn.setAttribute('aria-expanded', String(!isOpen));
        });

        /* Panel बाहिर click गर्यो भने बन्द गर्नुहोस् */
        document.addEventListener('click', function (e) {
            if (!btn.contains(e.target) && !panel.contains(e.target)) {
                panel.style.display = 'none';
                btn.setAttribute('aria-expanded', 'false');
            }
        });

        /* Escape key — dropdown बन्द */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                panel.style.display = 'none';
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    })();

    // ── Global Client-side Table Search ──
    // Card भित्र .admin-table-subtab-content भए खुला उप-ट्याबको tbody मात्र
    function adminRunTableSearch(input) {
        if (!input || !input.classList.contains('admin-table-search')) return;
        var val = input.value.toLowerCase().trim();
        var card = input.closest('.card');
        var subContent = card ? card.querySelector('.admin-table-subtab-content') : null;
        var tbody;
        if (subContent) {
            var pane = subContent.querySelector('.tab-pane.active');
            tbody = pane ? pane.querySelector('tbody') : null;
        } else {
            var container = input.closest('.tab-pane') || input.closest('.card') || input.closest('[id$="-list"]') || document;
            tbody = container ? container.querySelector('tbody') : null;
        }
        if (!tbody) return;
        var rows = tbody.querySelectorAll('tr');
        var shown = 0;
        var total = 0;
        rows.forEach(function (row) {
            var cells = row.querySelectorAll('td');
            var isPlaceholder = cells.length === 1 && cells[0].getAttribute('colspan');
            if (isPlaceholder) {
                row.style.display = val ? 'none' : '';
                return;
            }
            total++;
            var match = !val || row.textContent.toLowerCase().includes(val);
            row.style.display = match ? '' : 'none';
            if (match) shown++;
        });
        var badge = input.closest('.admin-search-wrap') ? input.closest('.admin-search-wrap').querySelector('.search-count') : null;
        if (badge) badge.textContent = shown + ' / ' + total;
    }
    document.addEventListener('input', function (e) {
        if (e.target.classList && e.target.classList.contains('admin-table-search')) {
            adminRunTableSearch(e.target);
        }
    });
    document.addEventListener('shown.bs.tab', function (e) {
        var t = e.target;
        if (!t || t.getAttribute('data-bs-toggle') !== 'tab') return;
        var sel = t.getAttribute('data-bs-target');
        if (!sel) return;
        var panel = document.querySelector(sel);
        if (!panel || !panel.closest('.admin-table-subtab-content')) return;
        var c = t.closest('.card');
        if (!c) return;
        var inp = c.querySelector('.admin-table-search');
        if (inp) adminRunTableSearch(inp);
    });
    function adminInitAllTableSearches() {
        document.querySelectorAll('.admin-table-search').forEach(adminRunTableSearch);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', adminInitAllTableSearches);
    } else {
        adminInitAllTableSearches();
    }
    </script>
