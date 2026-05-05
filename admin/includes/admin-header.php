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
$siteLogo     = function_exists('getSetting')
    ? trim((string) getSetting('site_logo', getSetting('logo', 'assets/images/logo.png')))
    : 'assets/images/logo.png';
$hasSiteLogo  = !empty($siteLogo);

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
    'samgri' => ['notices','designations','news','sliders','gallery','services','interest-rates','pages','downloads','faqs','useful-links','awards','reports','app-features','why-choose','partner-facilities'],
    'toli'   => ['team','committees','info-officer','grievance-officer'],
    'rojgar' => ['careers','job-applications'],
    'aavedan'=> ['kyc','kyc-risk-reviews','loans','account-apps','digital-service-requests','auctions','auction-bids','vendor-enlistment'],
    'program' => ['programs','program-attendance','program-attendance-verify'],
    'nirvachan' => ['election-information','election-posts','election-candidates','election-results'],
    'sampark'=> ['messages','feedbacks','grievances','appointments','welfare-claims','help-center','members'],
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
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin Panel</title>

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
    <link rel="stylesheet" href="assets/admin-modern.css?v=3.7">
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
        color: rgba(255,255,255,0.75);
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
        background: rgba(255,255,255,0.1);
        color: #fff;
    }
    .nav-group-header.open {
        background: rgba(255,255,255,0.08);
        color: #fff;
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
        opacity: 0.6;
        order: 10;   /* सधैं सबैभन्दा दायाँ */
        flex-shrink: 0;
    }
    .nav-group-header.open .nav-arrow {
        transform: rotate(90deg);
        opacity: 1;
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
          background: rgba(255,255,255,0.08) !important;
          padding: 3px 5px !important;
          flex-shrink: 0 !important;
      }
      /* No-logo fallback: show real site name text */
      .sidebar-brand.no-logo .sidebar-brand-text {
          font-size: 0.85rem;
          font-weight: 700;
          color: #fff;
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
                    <span class="sidebar-brand-text">Admin Panel</span>
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
                            <span class="sidebar-link-label">ड्यासबोर्ड</span>
                            <?php if ($__dash_total > 0): ?>
                            <span class="group-badge"><?php echo $__dash_total; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- ── Admin User Management — सबै admin ले देख्छन् ── -->
                    <li class="<?php echo $currentPage === 'manage-admins' ? 'active' : ''; ?>">
                        <a href="manage-admins.php">
                            <span class="nav-icon-wrap"><i class="fas fa-users-cog"></i></span>
                            <span>Admin व्यवस्थापन</span>
                            <?php if (!empty($_SESSION['is_superadmin'])): ?>
                            <span class="sa-mini-badge">SA</span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- ── सामग्री ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='samgri' ? 'open' : ''; ?>" data-group="samgri">
                            <span class="nav-group-icon"><i class="fas fa-folder-open"></i></span>
                            <span class="nav-group-label">सामग्री</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='samgri' ? 'open' : ''; ?>" id="group-samgri">
                            <li class="<?php echo $currentPage=='notices' ? 'active' : ''; ?>">
                                <a href="notices.php"><span class="nav-icon-wrap"><i class="fas fa-bullhorn"></i></span><span>सूचनाहरू</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='designations' ? 'active' : ''; ?>">
                                <a href="designations.php"><span class="nav-icon-wrap"><i class="fas fa-id-badge"></i></span><span>पद मास्टर</span></a>
                            </li>
                            <?php /* निर्वाचन सम्बन्धी menus छुट्टै “निर्वाचन” group मा (पद Master = election-posts) */ ?>
                            <li class="<?php echo $currentPage=='news' ? 'active' : ''; ?>">
                                <a href="news.php"><span class="nav-icon-wrap"><i class="fas fa-newspaper"></i></span><span>समाचार</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='sliders' ? 'active' : ''; ?>">
                                <a href="sliders.php"><span class="nav-icon-wrap"><i class="fas fa-images"></i></span><span>स्लाइडर</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='gallery' ? 'active' : ''; ?>">
                                <a href="gallery.php"><span class="nav-icon-wrap"><i class="fas fa-photo-film"></i></span><span>ग्यालरी</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='services' ? 'active' : ''; ?>">
                                <a href="services.php"><span class="nav-icon-wrap"><i class="fas fa-hand-holding-heart"></i></span><span>सेवाहरू</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='interest-rates' ? 'active' : ''; ?>">
                                <a href="interest-rates.php"><span class="nav-icon-wrap"><i class="fas fa-percent"></i></span><span>ब्याज दर</span></a>
                            </li>
                            <li class="<?php echo ($currentPage === 'pages' && (($_GET['tab'] ?? '') !== 'dynamic')) ? 'active' : ''; ?>">
                                <a href="pages.php"><span class="nav-icon-wrap"><i class="fas fa-file-lines"></i></span><span>पृष्ठहरू</span></a>
                            </li>
                            <li class="<?php echo ($currentPage === 'pages' && (($_GET['tab'] ?? '') === 'dynamic')) ? 'active' : ''; ?>">
                                <!-- Policy pages direct shortcut:
                                     गोपनीयता नीति / सेवाका सर्तहरू / कुकी नीति -->
                                <a href="pages.php?tab=dynamic&f_menu=footer">
                                    <span class="nav-icon-wrap"><i class="fas fa-shield-halved"></i></span>
                                    <span>नीति पृष्ठहरू</span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='downloads' ? 'active' : ''; ?>">
                                <a href="downloads.php"><span class="nav-icon-wrap"><i class="fas fa-file-arrow-down"></i></span><span>डाउनलोड</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='faqs' ? 'active' : ''; ?>">
                                <a href="faqs.php"><span class="nav-icon-wrap"><i class="fas fa-circle-question"></i></span><span>प्रश्नोत्तर (FAQs)</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='useful-links' ? 'active' : ''; ?>">
                                <a href="useful-links.php"><span class="nav-icon-wrap"><i class="fas fa-link"></i></span><span>उपयोगी लिंकहरू</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='awards' ? 'active' : ''; ?>">
                                <a href="awards.php"><span class="nav-icon-wrap"><i class="fas fa-trophy"></i></span><span>सम्मान/पुरस्कार</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='reports' ? 'active' : ''; ?>">
                                <a href="reports.php"><span class="nav-icon-wrap"><i class="fas fa-chart-column"></i></span><span>प्रतिवेदन</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='app-features' ? 'active' : ''; ?>">
                                <a href="app-features.php"><span class="nav-icon-wrap"><i class="fas fa-mobile-screen"></i></span><span>एप सुविधाहरू</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='why-choose' ? 'active' : ''; ?>">
                                <a href="why-choose.php"><span class="nav-icon-wrap"><i class="fas fa-star nav-icon-accent nav-icon-gold"></i></span><span>किन हामीलाई छान्ने?</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='partner-facilities' ? 'active' : ''; ?>">
                                <a href="partner-facilities.php"><span class="nav-icon-wrap"><i class="fas fa-handshake nav-icon-accent nav-icon-primary-soft"></i></span><span>साझेदार सुविधा</span></a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── टोली ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='toli' ? 'open' : ''; ?>" data-group="toli">
                            <span class="nav-group-icon"><i class="fas fa-users"></i></span>
                            <span class="nav-group-label">टोली</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='toli' ? 'open' : ''; ?>" id="group-toli">
                            <li class="<?php echo $currentPage=='team' ? 'active' : ''; ?>">
                                <a href="team.php"><span class="nav-icon-wrap"><i class="fas fa-users"></i></span><span>टोली सदस्य</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='committees' ? 'active' : ''; ?>">
                                <a href="committees.php"><span class="nav-icon-wrap"><i class="fas fa-people-group"></i></span><span>समिति/उपसमिति</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='info-officer' ? 'active' : ''; ?>">
                                <a href="info-officer.php"><span class="nav-icon-wrap"><i class="fas fa-user-shield nav-icon-accent nav-icon-cyan"></i></span><span>सूचना अधिकारी (RTI)</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='grievance-officer' ? 'active' : ''; ?>">
                                <a href="grievance-officer.php"><span class="nav-icon-wrap"><i class="fas fa-user-tie nav-icon-accent nav-icon-purple"></i></span><span>गुनासो अधिकारी</span></a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── रोजगारी ── -->
                    <li class="nav-group-wrap">
                        <?php $rojgarBadge = $adminAlertCounts['job']; ?>
                        <div class="nav-group-header <?php echo $activeGroup=='rojgar' ? 'open' : ''; ?>" data-group="rojgar">
                            <span class="nav-group-icon"><i class="fas fa-briefcase"></i></span>
                            <span class="nav-group-label">रोजगारी</span>
                            <?php if ($rojgarBadge > 0): ?><span class="group-badge"><?php echo $rojgarBadge; ?></span><?php endif; ?>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='rojgar' ? 'open' : ''; ?>" id="group-rojgar">
                            <li class="<?php echo $currentPage=='careers' ? 'active' : ''; ?>">
                                <a href="careers.php"><span class="nav-icon-wrap"><i class="fas fa-briefcase"></i></span><span>रोजगारी पोस्ट</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='job-applications' ? 'active' : ''; ?>">
                                <a href="job-applications.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-file-circle-check"></i></span>
                                    <span>जागिर आवेदन</span>
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
                            <span class="nav-group-label">आवेदनहरू</span>
                            <?php if ($aavedan_total > 0): ?><span class="group-badge"><?php echo $aavedan_total; ?></span><?php endif; ?>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='aavedan' ? 'open' : ''; ?>" id="group-aavedan">
                            <li class="<?php echo $currentPage=='kyc' ? 'active' : ''; ?>">
                                <a href="kyc-applications.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-id-card-clip"></i></span>
                                    <span>KYC आवेदन</span>
                                    <?php if ($adminAlertCounts['kyc'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['kyc']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='kyc-risk-reviews' ? 'active' : ''; ?>">
                                <a href="kyc-risk-reviews.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-shield-halved"></i></span>
                                    <span>KYC Risk Review</span>
                                    <?php if ($adminAlertCounts['kyc_risk'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['kyc_risk']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='loans' ? 'active' : ''; ?>">
                                <a href="loan-applications.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-hand-holding-dollar"></i></span>
                                    <span>ऋण आवेदन</span>
                                    <?php if ($adminAlertCounts['loan'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['loan']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='account-apps' ? 'active' : ''; ?>">
                                <a href="account-applications.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-user-plus"></i></span>
                                    <span>खाता आवेदन</span>
                                    <?php if ($adminAlertCounts['account'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['account']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='digital-service-requests' ? 'active' : ''; ?>">
                                <a href="digital-service-requests.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-laptop-code"></i></span>
                                    <span>डिजिटल सेवा</span>
                                    <?php if ($adminAlertCounts['digital'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['digital']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='auctions' ? 'active' : ''; ?>">
                                <a href="auctions.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-gavel"></i></span>
                                    <span>लिलामी / बोलपत्र</span>
                                    <?php if ($adminAlertCounts['auction'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['auction']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='auction-bids' ? 'active' : ''; ?>">
                                <a href="auction-bids.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-list-ol"></i></span>
                                    <span>बोलपत्र सूची</span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='vendor-enlistment' ? 'active' : ''; ?>">
                                <a href="vendor-enlistment.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-store"></i></span>
                                    <span>भेन्डर सूचीकरण</span>
                                    <?php if ($adminAlertCounts['vendor'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['vendor']; ?></span><?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── कार्यक्रम व्यवस्थापन (All program tools) ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='program' ? 'open' : ''; ?>" data-group="program">
                            <span class="nav-group-icon"><i class="fas fa-calendar-check"></i></span>
                            <span class="nav-group-label">कार्यक्रम व्यवस्थापन</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='program' ? 'open' : ''; ?>" id="group-program">
                            <li class="<?php echo $currentPage=='programs' ? 'active' : ''; ?>">
                                <a href="programs.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-calendar-plus"></i></span>
                                    <span>Program Create / List</span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='program-attendance-verify' ? 'active' : ''; ?>">
                                <a href="../program-attendance-verify.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-user-check"></i></span>
                                    <span>Attendance Verify</span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='program-attendance' ? 'active' : ''; ?>">
                                <a href="program-attendance.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-clipboard-check"></i></span>
                                    <span>Attendance Report</span>
                                </a>
                            </li>
                            <?php /* निर्वाचन जानकारी छुट्टै group मा सरेको */ ?>
                            <li class="<?php echo $currentPage=='analytics' ? 'active' : ''; ?>">
                                <a href="analytics.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-chart-line"></i></span>
                                    <span>Analytics Dashboard</span>
                                </a>
                            </li>
                        </ul>
                    </li>


                    <!-- ── निर्वाचन (छुट्टै group) ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='nirvachan' ? 'open' : ''; ?>" data-group="nirvachan">
                            <span class="nav-group-icon"><i class="fas fa-check-to-slot"></i></span>
                            <span class="nav-group-label">निर्वाचन</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='nirvachan' ? 'open' : ''; ?>" id="group-nirvachan">
                            <li class="<?php echo $currentPage=='election-information' ? 'active' : ''; ?>">
                                <a href="election-information.php"><span class="nav-icon-wrap"><i class="fas fa-circle-info"></i></span><span>निर्वाचन जानकारी</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='election-posts' ? 'active' : ''; ?>">
                                <a href="election-posts.php"><span class="nav-icon-wrap"><i class="fas fa-briefcase"></i></span><span>पद Master</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='election-candidates' ? 'active' : ''; ?>">
                                <a href="election-candidates.php"><span class="nav-icon-wrap"><i class="fas fa-user-tie"></i></span><span>उम्मेदवार/पद</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='election-results' ? 'active' : ''; ?>">
                                <a href="election-results.php"><span class="nav-icon-wrap"><i class="fas fa-chart-bar"></i></span><span>निर्वाचन नतिजा</span></a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── सम्पर्क / संचार ── -->
                    <li class="nav-group-wrap">
                        <?php $sampark_total = $unreadMessages + $adminAlertCounts['feedback'] + $adminAlertCounts['grievance'] + $adminAlertCounts['welfare'] + $adminAlertCounts['appointment']; ?>
                        <div class="nav-group-header <?php echo $activeGroup=='sampark' ? 'open' : ''; ?>" data-group="sampark">
                            <span class="nav-group-icon"><i class="fas fa-comments"></i></span>
                            <span class="nav-group-label">सम्पर्क</span>
                            <?php if ($sampark_total > 0): ?><span class="group-badge"><?php echo $sampark_total; ?></span><?php endif; ?>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='sampark' ? 'open' : ''; ?>" id="group-sampark">
                            <li class="<?php echo $currentPage=='messages' ? 'active' : ''; ?>">
                                <a href="messages.php" class="sidebar-link-flex">
                                    <span class="nav-icon-wrap"><i class="fas fa-envelope-open-text"></i></span>
                                    <span class="sidebar-link-label">सन्देशहरू</span>
                                    <?php if ($unreadMessages > 0): ?><span class="badge"><?php echo $unreadMessages; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='feedbacks' ? 'active' : ''; ?>">
                                <a href="feedbacks.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-comments"></i></span>
                                    <span>सुझाव/प्रतिक्रिया</span>
                                    <?php if ($adminAlertCounts['feedback'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['feedback']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='grievances' ? 'active' : ''; ?>">
                                <a href="grievances.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-triangle-exclamation"></i></span>
                                    <span>गुनासो</span>
                                    <?php if ($adminAlertCounts['grievance'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['grievance']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='appointments' ? 'active' : ''; ?>">
                                <a href="appointments.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-calendar-check"></i></span>
                                    <span>भेटघाट</span>
                                    <?php if ($adminAlertCounts['appointment'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['appointment']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='welfare-claims' ? 'active' : ''; ?>">
                                <a href="welfare-claims.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-heart-circle-plus"></i></span>
                                    <span>कल्याण दाबी</span>
                                    <?php if ($adminAlertCounts['welfare'] > 0): ?><span class="badge"><?php echo $adminAlertCounts['welfare']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='help-center' ? 'active' : ''; ?>">
                                <a href="help-center.php"><span class="nav-icon-wrap"><i class="fas fa-headset"></i></span><span>सहायता केन्द्र</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='members' ? 'active' : ''; ?>">
                                <a href="members.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-user-check nav-icon-accent nav-icon-primary"></i></span>
                                    <span>Member Portal</span>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── Member Online Portal ── -->
                    <li class="nav-group-wrap">
                        <?php $memPortalBadgeTotal = $memPortalBadge ?? 0; ?>
                        <div class="nav-group-header <?php echo $activeGroup=='memportal' ? 'open' : ''; ?>" data-group="memportal">
                            <span class="nav-group-icon"><i class="fas fa-globe"></i></span>
                            <span class="nav-group-label">Member Online Portal</span>
                            <?php if ($memPortalBadgeTotal > 0): ?><span class="group-badge"><?php echo $memPortalBadgeTotal; ?></span><?php endif; ?>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='memportal' ? 'open' : ''; ?>" id="group-memportal">
                            <li class="<?php echo $currentPage=='member-online-portal' ? 'active' : ''; ?>">
                                <a href="member-online-portal.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-users-line nav-icon-accent nav-icon-primary"></i></span>
                                    <span>दर्ता अनुमोदन</span>
                                    <?php if (!empty($adminAlertCounts['mem_pending'])): ?><span class="badge"><?php echo $adminAlertCounts['mem_pending']; ?></span><?php endif; ?>
                                </a>
                            </li>
                            <li class="<?php echo ($currentPage=='member-online-portal' && ($_GET['tab'] ?? '')=='resets') ? 'active' : ''; ?>">
                                <a href="member-online-portal.php?tab=resets">
                                    <span class="nav-icon-wrap"><i class="fas fa-key nav-icon-accent nav-icon-amber"></i></span>
                                    <span>पासवर्ड Reset</span>
                                    <?php if (!empty($adminAlertCounts['mem_resets'])): ?><span class="badge"><?php echo $adminAlertCounts['mem_resets']; ?></span><?php endif; ?>
                                </a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── संस्था ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='sanstha' ? 'open' : ''; ?>" data-group="sanstha">
                            <span class="nav-group-icon"><i class="fas fa-building-columns"></i></span>
                            <span class="nav-group-label">संस्था</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='sanstha' ? 'open' : ''; ?>" id="group-sanstha">
                            <li class="<?php echo $currentPage=='service-centers' ? 'active' : ''; ?>">
                                <a href="service-centers.php"><span class="nav-icon-wrap"><i class="fas fa-location-dot"></i></span><span>शाखाहरू</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='institutional-profile' ? 'active' : ''; ?>">
                                <a href="institutional-profile.php"><span class="nav-icon-wrap"><i class="fas fa-building-columns"></i></span><span>संस्थागत प्रोफाइल</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='notification-settings' ? 'active' : ''; ?>">
                                <a href="notification-settings.php">
                                <span class="nav-icon-wrap"><i class="fas fa-bell"></i></span>
                                <span>सूचना सेटिङ्स</span>
                            </a>
                            </li>
                            <li class="<?php echo $currentPage=='notification-templates' ? 'active' : ''; ?>">
                                <a href="notification-templates.php">
                                <span class="nav-icon-wrap"><i class="fas fa-envelope-open-text nav-icon-accent nav-icon-violet"></i></span>
                                <span>सूचना Templates</span>
                            </a>
                            </li>
                            <li class="<?php echo $currentPage=='member-of-year' ? 'active' : ''; ?>">
                                <a href="member-of-year.php"><span class="nav-icon-wrap"><i class="fas fa-trophy nav-icon-accent nav-icon-gold"></i></span><span>वर्षको सर्वश्रेष्ठ सदस्य</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='about-settings' ? 'active' : ''; ?>">
                                <a href="about-settings.php"><span class="nav-icon-wrap"><i class="fas fa-landmark"></i></span><span>बारेमा पृष्ठ</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='satisfaction-settings' ? 'active' : ''; ?>">
                                <a href="satisfaction-settings.php"><span class="nav-icon-wrap"><i class="fas fa-smile nav-icon-accent nav-icon-pink"></i></span><span>सन्तुष्टि Widget</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='settings' ? 'active' : ''; ?>">
                                <a href="settings.php"><span class="nav-icon-wrap"><i class="fas fa-sliders"></i></span><span>सेटिङ्स</span></a>
                            </li>
                        </ul>
                    </li>

                    <!-- ── प्रविधि ── -->
                    <li class="nav-group-wrap">
                        <div class="nav-group-header <?php echo $activeGroup=='prawidhi' ? 'open' : ''; ?>" data-group="prawidhi">
                            <span class="nav-group-icon"><i class="fas fa-server"></i></span>
                            <span class="nav-group-label">प्रविधि</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu <?php echo $activeGroup=='prawidhi' ? 'open' : ''; ?>" id="group-prawidhi">
                            <li class="<?php echo $currentPage=='system-info' ? 'active' : ''; ?>">
                                <a href="system-info.php"><span class="nav-icon-wrap"><i class="fas fa-server"></i></span><span>प्रणाली जानकारी</span></a>
                            </li>
                            <?php if (!empty($_SESSION['is_superadmin'])): ?>
                            <li class="<?php echo $currentPage=='run-migration' ? 'active' : ''; ?>">
                                <a href="run-migration.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-database"></i></span>
                                    <span>डेटाबेस Migration</span>
                                    <span class="sa-label-badge">SA</span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='backup-restore' ? 'active' : ''; ?>">
                                <a href="backup-restore.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-shield-alt"></i></span>
                                    <span>ब्याकअप / पुनर्स्थापना</span>
                                    <span class="sa-label-badge">SA</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            <li class="<?php echo $currentPage=='update-checklist' ? 'active' : ''; ?>">
                                <a href="update-checklist.php"><span class="nav-icon-wrap"><i class="fas fa-list-check"></i></span><span>अपडेट सूची</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='site-health' ? 'active' : ''; ?>">
                                <a href="site-health.php"><span class="nav-icon-wrap"><i class="fas fa-heart-pulse"></i></span><span>साइट स्वास्थ्य</span></a>
                            </li>
                            <li class="<?php echo $currentPage=='error-log' ? 'active' : ''; ?>">
                                <a href="error-log.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-bug nav-icon-accent nav-icon-red"></i></span>
                                    <span>Error Log</span>
                                </a>
                            </li>
                            <!-- v5: In-app User Manual / Help & Guide (non-developer friendly) -->
                            <li class="<?php echo $currentPage=='help-guide' ? 'active' : ''; ?>">
                                <a href="help-guide.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-book-open nav-icon-accent nav-icon-green"></i></span>
                                    <span>📖 सहायता / Help</span>
                                </a>
                            </li>
                            <!-- Site Setup Manager — setup.php को काम admin panel भित्रबाट -->
                            <li class="<?php echo $currentPage=='site-setup' ? 'active' : ''; ?>">
                                <a href="site-setup.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-sliders"></i></span>
                                    <span>साइट सेटअप</span>
                                </a>
                            </li>
                            <!-- साइट म्याद — Superadmin only -->
                            <?php if (!empty($_SESSION['is_superadmin'])): ?>
                            <li class="<?php echo $currentPage=='site-license' ? 'active' : ''; ?>">
                                <a href="site-license.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-calendar-check nav-icon-accent nav-icon-amber"></i></span>
                                    <span>साइट म्याद</span>
                                    <span class="sa-label-badge">SA</span>
                                </a>
                            </li>
                            <li class="<?php echo $currentPage=='db-setup' ? 'active' : ''; ?>">
                                <a href="db-setup.php">
                                    <span class="nav-icon-wrap"><i class="fas fa-database nav-icon-accent nav-icon-primary-soft"></i></span>
                                    <span>DB सेटअप</span>
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
                        <?php echo !empty($_SESSION['is_superadmin']) ? 'Superadmin' : 'Administrator'; ?>
                    </div>
                </div>
                <a href="logout.php" title="लगआउट" class="sidebar-strip-logout">
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
                        <span class="brand-text">Admin</span>
                        <?php endif; ?>
                    </a>
                    <div class="page-title-wrap">
                        <h1 class="page-title"><?php echo $pageTitle ?? 'ड्यासबोर्ड'; ?></h1>
                        <span class="header-date-pill" title="आजको मिति">
                            <i class="fas fa-calendar-day"></i>
                            <?php echo function_exists('formatNepaliDate') ? formatNepaliDate(date('Y-m-d')) : date('Y-m-d'); ?>
                        </span>
                    </div>
                </div>

                <div class="header-right">
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
                        ['label'=>'अपठित सन्देश',     'count'=>$unreadMessages,                        'href'=>'messages.php',                       'icon'=>'fa-envelope',            'tone'=>'red'],
                        ['label'=>'KYC आवेदन',        'count'=>$adminAlertCounts['kyc'],               'href'=>'kyc-applications.php?status=pending', 'icon'=>'fa-id-card',             'tone'=>'orange'],
                        ['label'=>'ऋण आवेदन',         'count'=>$adminAlertCounts['loan'],              'href'=>'loan-applications.php?status=pending','icon'=>'fa-hand-holding-usd',   'tone'=>'amber'],
                        ['label'=>'खाता आवेदन',       'count'=>$adminAlertCounts['account'],           'href'=>'account-applications.php?status=pending','icon'=>'fa-university',       'tone'=>'purple'],
                        ['label'=>'डिजिटल सेवा',      'count'=>$adminAlertCounts['digital'],           'href'=>'digital-service-requests.php?status=pending','icon'=>'fa-mobile-alt', 'tone'=>'cyan'],
                        ['label'=>'जागिर आवेदन',      'count'=>$adminAlertCounts['job'],               'href'=>'job-applications.php?status=pending', 'icon'=>'fa-briefcase',           'tone'=>'green'],
                        ['label'=>'गुनासो',            'count'=>$adminAlertCounts['grievance'],         'href'=>'grievances.php?status=pending',       'icon'=>'fa-comment-dots',        'tone'=>'red'],
                        ['label'=>'सुझाव/प्रतिक्रिया', 'count'=>$adminAlertCounts['feedback'],          'href'=>'feedbacks.php?status=pending',        'icon'=>'fa-star',                'tone'=>'orange'],
                        ['label'=>'कल्याण दाबी',      'count'=>$adminAlertCounts['welfare'],           'href'=>'welfare-claims.php?status=pending',   'icon'=>'fa-hand-holding-heart',  'tone'=>'teal'],
                        ['label'=>'लिलामी बिड',       'count'=>$adminAlertCounts['auction'],           'href'=>'auction-bids.php',                    'icon'=>'fa-gavel',               'tone'=>'slate'],
                        ['label'=>'भेन्डर आवेदन',      'count'=>$adminAlertCounts['vendor'],            'href'=>'vendor-enlistment.php?status=pending','icon'=>'fa-store',               'tone'=>'blue'],
                    ];
                    /* pending मात्र filter गर्ने — count > 0 भएका मात्र dropdown मा देखाउने */
                    $activeNotifs = array_filter($notifItems, function ($i) {
                        return ($i['count'] ?? 0) > 0;
                    });
                    ?>
                    <div class="header-item notif-wrapper">
                        <!-- Bell button — सधैं देखिन्छ, count > 0 भए red badge -->
                        <button type="button" class="notification-bell notif-toggle-btn"
                                title="सूचनाहरू — <?php echo $totalAlerts > 0 ? $totalAlerts . ' pending' : 'सबै हेरिएको'; ?>"
                                aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($totalAlerts > 0): ?>
                            <span class="notif-count"><?php echo $totalAlerts; ?></span>
                            <?php endif; ?>
                        </button>

                        <!-- Dropdown panel -->
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="notif-dropdown-header">
                                <span><i class="fas fa-bell me-1"></i>सूचनाहरू</span>
                                <?php if ($totalAlerts > 0): ?>
                                <span class="notif-total-badge"><?php echo $totalAlerts; ?> pending</span>
                                <?php endif; ?>
                            </div>
                            <div class="notif-dropdown-body">
                                <?php if (empty($activeNotifs)): ?>
                                <div class="notif-empty">
                                    <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                    <p class="mb-0">सबै हेरिएको छ!</p>
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
                                    <i class="fas fa-th-large me-1"></i>ड्यासबोर्ड हेर्नुहोस्
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
                                    <span class="superadmin-pill">SUPERADMIN</span>
                                <?php endif; ?>
                            </span>
                            <div class="admin-menu">
                                <?php if (empty($_SESSION['is_superadmin'])): ?>
                                    <!-- Normal admin मात्र profile र change-password देख्छ -->
                                    <a href="profile.php"><i class="fas fa-user"></i> प्रोफाइल</a>
                                    <a href="change-password.php"><i class="fas fa-key"></i> पासवर्ड</a>
                                <?php else: ?>
                                    <!-- Superadmin को लागि admin management link -->
                                    <a href="manage-admins.php"><i class="fas fa-users-cog"></i> Admin व्यवस्थापन</a>
                                <?php endif; ?>
                                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> लगआउट</a>
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
    // सबै .admin-table-search input ले नजिकको <tbody> rows filter गर्छ
    document.addEventListener('input', function (e) {
        var input = e.target;
        if (!input.classList.contains('admin-table-search')) return;
        var val = input.value.toLowerCase().trim();
        /* table खोज्नुहोस् — input को नजिकको card / tab-pane मा */
        var container = input.closest('.tab-pane') || input.closest('.card') || input.closest('[id$="-list"]') || document;
        var tbody = container ? container.querySelector('tbody') : document.querySelector('tbody');
        if (!tbody) return;
        var rows = tbody.querySelectorAll('tr');
        var shown = 0;
        rows.forEach(function (row) {
            var match = !val || row.textContent.toLowerCase().includes(val);
            row.style.display = match ? '' : 'none';
            if (match) shown++;
        });
        /* Count badge अपडेट */
        var badge = input.closest('.admin-search-wrap') ? input.closest('.admin-search-wrap').querySelector('.search-count') : null;
        if (badge) badge.textContent = shown + ' / ' + rows.length;
    });
    </script>
