<?php
/**
 * ════════════════════════════════════════════════════════════
 * PUBLIC MEMBER VERIFICATION — v10.2
 * ────────────────────────────────────────────────────────────
 * URL: /verify.php
 *
 * कुनै पनि व्यक्ति (हस्पिटल, पसल, अन्य संस्था) ले member ले
 * देखाएको ID Card को Verification Code (AKS-XXXX-XXXX) र
 * 4-अङ्कको CVV enter गरेर तुरुन्तै सक्रिय सदस्य हो/होइन
 * verify गर्न सक्छन्।
 *
 * Card duplicate/नक्कली कि होइन check गर्न सजिलो।
 * ════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/card-verify-helpers.php';
require_once __DIR__ . '/includes/program-tables.php';
require_once __DIR__ . '/includes/member-partner-services-tables.php';
$_t = static function (string $np, string $en): string {
    return isEnglish() ? $en : $np;
};

$pdo = getDB();
ensureProgramTables($pdo);
ensureMemberPartnerServicesTable($pdo);

$result = null;
$code   = '';
$cvv    = '';
$logSaved = false;
$programSaved = false;
$programAlreadyRegistered = false;
$preregSaved = false;
$preregAlreadyRegistered = false;
$preregError = '';
$activePrograms = [];
$openPreRegPrograms = [];
$postCsrfError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $postCsrfError = isEnglish() ? 'Security validation failed. Please retry.' : 'सुरक्षा जाँच असफल भयो। कृपया फेरि प्रयास गर्नुहोस्।';
    }
    $code = (string)($_POST['code'] ?? '');
    $code = function_exists('normalizeCardCode') ? normalizeCardCode($code) : $code;
    $cvv  = (string)($_POST['cvv']  ?? '');
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    /* (a) Service-log POST — verify पछि सेवा लिएको record छुट्टै submit */
    if ($postCsrfError !== '') {
        $result = ['ok' => false, 'error' => $postCsrfError];
    } elseif (($_POST['action'] ?? '') === 'log_service') {
        $mid       = (int)($_POST['member_id'] ?? 0);
        $cardNo    = trim($_POST['member_card_no'] ?? '');
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $partnerNm = trim($_POST['partner_name'] ?? '');
        $serviceNm = trim($_POST['service_name'] ?? '');
        $taken     = (isset($_POST['service_taken']) && $_POST['service_taken'] === 'yes') ? 1 : 0;
        $note      = trim($_POST['service_note'] ?? '');
        if ($mid && $partnerNm !== '' && $partnerId > 0) {
            try {
                $ins = $pdo->prepare("INSERT INTO member_partner_services
                    (member_id, member_card_no, partner_id, partner_name, service_name, service_taken, service_note, verified_by_ip)
                    VALUES (?,?,?,?,?,?,?,?)");
                $ins->execute([$mid, $cardNo, $partnerId, $partnerNm, mb_substr($serviceNm, 0, 255), $taken, mb_substr($note, 0, 500), $ip]);
                $logSaved = true;
            } catch (\Throwable $e) { error_log('mps insert: ' . $e->getMessage()); }
        }
        /* re-verify so the success card stays visible after logging */
        $code = trim($_POST['code'] ?? '');
        $code = function_exists('normalizeCardCode') ? normalizeCardCode($code) : $code;
        $cvv  = trim($_POST['cvv']  ?? '');
        if ($code && $cvv) {
            $result = verifyCardCredentials($pdo, $code, $cvv, $ip);
        }
    } elseif (($_POST['action'] ?? '') === 'program_preregister') {
        $programId = (int)($_POST['program_id'] ?? 0);
        $memberIdInput = trim((string)($_POST['member_id_input'] ?? ''));
        $note = trim((string)($_POST['prereg_note'] ?? ''));
        if ($programId <= 0 || $memberIdInput === '') {
            $preregError = $_t('कृपया कार्यक्रम र सदस्यता नं. दुवै भर्नुहोस्।', 'Please fill both program and member number.');
        } else {
            try {
                $pst = $pdo->prepare("SELECT id, title, pre_registration_open, is_active FROM upcoming_programs WHERE id=? LIMIT 1");
                $pst->execute([$programId]);
                $pg = $pst->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$pg || (int)$pg['is_active'] !== 1 || (int)$pg['pre_registration_open'] !== 1) {
                    $preregError = $_t('यो कार्यक्रमको pre-registration अहिले खुला छैन।', 'Pre-registration is currently closed for this program.');
                } else {
                    $mst = $pdo->prepare("SELECT m.id, m.name, m.phone, m.sadasyata_number, m.member_card_no, m.kyc_application_id, m.approval_status, m.is_active
                                          FROM members m
                                          WHERE m.sadasyata_number = ? OR m.member_card_no = ? OR m.id = ?
                                          LIMIT 1");
                    $mst->execute([$memberIdInput, $memberIdInput, (int)$memberIdInput]);
                    $member = $mst->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (!$member || (string)($member['approval_status'] ?? '') !== 'approved' || (int)($member['is_active'] ?? 0) !== 1) {
                        $preregError = $_t('Not member. कृपया पहिला सदस्य बन्नुहोस्।', 'Not a member. Please become a member first.');
                    } else {
                        $kycOk = false;
                        if (!empty($member['kyc_application_id'])) {
                            $kst = $pdo->prepare("SELECT id FROM kyc_applications WHERE id=? LIMIT 1");
                            $kst->execute([(int)$member['kyc_application_id']]);
                            $kycOk = (bool)$kst->fetchColumn();
                        } else {
                            $kst = $pdo->prepare("SELECT id FROM kyc_applications WHERE member_id=? OR mobile=? LIMIT 1");
                            $kst->execute([(string)($member['sadasyata_number'] ?? ''), preg_replace('/[^0-9]/', '', (string)($member['phone'] ?? ($member['phone'] ?? '') ?? ''))]);
                            $kycOk = (bool)$kst->fetchColumn();
                        }
                        if (!$kycOk) {
                            $preregError = $_t('Not member. कृपया पहिला सदस्य बन्नुहोस्।', 'Not a member. Please become a member first.');
                        } else {
                            $chk = $pdo->prepare("SELECT id FROM member_program_preregistrations WHERE member_id=? AND program_id=? LIMIT 1");
                            $chk->execute([(int)$member['id'], $programId]);
                            if ($chk->fetchColumn()) {
                                $preregAlreadyRegistered = true;
                            } else {
                                $ins = $pdo->prepare("INSERT INTO member_program_preregistrations
                                    (member_id, member_card_no, member_name, phone, program_id, program_title, note, source)
                                    VALUES (?,?,?,?,?,?,?,?)");
                                $ins->execute([
                                    (int)$member['id'],
                                    (string)($member['sadasyata_number'] ?: ($member['member_card_no'] ?? '')),
                                    mb_substr((string)($member['name'] ?? ''), 0, 150),
                                    mb_substr((string)($member['phone'] ?: (($member['phone'] ?? '') ?? '')), 0, 30),
                                    $programId,
                                    mb_substr((string)$pg['title'], 0, 180),
                                    mb_substr($note, 0, 500),
                                    'public_verify'
                                ]);
                                $preregSaved = true;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                $preregError = $_t('Pre-registration सुरक्षित गर्न समस्या भयो।', 'Could not save pre-registration.');
                error_log('program prereg insert: ' . $e->getMessage());
            }
        }
    } elseif (($_POST['action'] ?? '') === 'log_program_attendance') {
        $mid         = (int)($_POST['member_id'] ?? 0);
        $cardNo      = trim($_POST['member_card_no'] ?? '');
        $programId   = (int)($_POST['program_id'] ?? 0);
        $programTit  = trim($_POST['program_title'] ?? '');
        $isPriority  = !empty($_POST['is_priority']) ? 1 : 0;
        $note        = trim($_POST['attendance_note'] ?? '');
        if ($mid > 0 && $programId > 0 && $programTit !== '') {
            try {
                $chk = $pdo->prepare("SELECT id FROM member_program_attendance WHERE member_id=? AND program_id=? LIMIT 1");
                $chk->execute([$mid, $programId]);
                $exists = $chk->fetchColumn();
                if ($exists) {
                    $programAlreadyRegistered = true;
                } else {
                    $ins = $pdo->prepare("INSERT INTO member_program_attendance
                        (member_id, member_card_no, program_id, program_title, is_priority, attendance_note, verified_by_ip, source)
                        VALUES (?,?,?,?,?,?,?,?)");
                    $ins->execute([$mid, $cardNo, $programId, mb_substr($programTit, 0, 180), $isPriority, mb_substr($note, 0, 500), $ip, 'verify_portal']);
                    $programSaved = true;
                }
            } catch (\Throwable $e) { error_log('program attendance insert: ' . $e->getMessage()); }
        }
        $code = trim($_POST['code'] ?? '');
        $code = function_exists('normalizeCardCode') ? normalizeCardCode($code) : $code;
        $cvv  = trim($_POST['cvv']  ?? '');
        if ($code && $cvv) {
            $result = verifyCardCredentials($pdo, $code, $cvv, $ip);
        }
    } else {
        $result = verifyCardCredentials($pdo, $code, $cvv, $ip);
    }
}

$pageTitle  = $_t('सदस्य प्रमाणीकरण — Member Verify', 'Member Verification');
$siteName   = defined('SITE_URL') ? SITE_URL : '/';
$cardPrefix = function_exists('getCardPrefix') ? getCardPrefix() : 'AKS';
$coopPhone = function_exists('getSetting') ? getSetting('phone', getSetting('mobile', '01-XXXXXXX')) : '01-XXXXXXX';
$coopWebsite = function_exists('getSetting') ? trim((string)getSetting('site_url', (defined('SITE_URL') ? SITE_URL : ''))) : (defined('SITE_URL') ? SITE_URL : '');
$coopWebsite = preg_replace('#^https?://#i', '', rtrim((string)$coopWebsite, '/'));
$coopLogo = function_exists('getLocalizedLogoPath')
    ? trim((string) getLocalizedLogoPath(''))
    : (function_exists('getSetting') ? trim((string)getSetting('site_logo', getSetting('logo', ''))) : '');

/* DOCUMENT_ROOT बाट photo URL build गर्ने helper */
$photoUrl = '';
if ($result && !empty($result['ok'])) {
    $pp = $result['member']['photo_path'] ?? '';
    if ($pp) {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot && file_exists($docRoot . '/' . ltrim($pp, '/'))) {
            $photoUrl = '/' . ltrim($pp, '/');
        }
    }
    if (!$photoUrl) $photoUrl = '/member/assets/photo-placeholder.svg';
    try {
        $activePrograms = $pdo->query("SELECT id, title, event_date, event_time, location
                                       FROM upcoming_programs
                                       WHERE is_active=1
                                       ORDER BY COALESCE(event_date, '9999-12-31') ASC, id DESC
                                       LIMIT 80")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) { $activePrograms = []; }
}

try {
    $openPreRegPrograms = $pdo->query("SELECT id, title, event_date, event_time, location
                                       FROM upcoming_programs
                                       WHERE is_active=1 AND pre_registration_open=1
                                       ORDER BY COALESCE(event_date, '9999-12-31') ASC, id DESC
                                       LIMIT 80")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (\Throwable $e) { $openPreRegPrograms = []; }

/* Active partner list — only if verify successful, to keep guest queries low */
$partners = [];
if ($result && !empty($result['ok'])) {
    try {
        $partners = $pdo->query("SELECT id, partner_name FROM partner_facilities WHERE is_active=1 ORDER BY partner_name ASC")
                        ->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { $partners = []; }
}
?>
<!DOCTYPE html>
<html lang="<?php echo isEnglish() ? 'en' : 'ne'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?php echo htmlspecialchars($_t('Member ID card सत्यता check गर्नुहोस्। Verification Code र CVV राखेर सक्रिय सदस्य हो/होइन प्रमाणित गर्नुहोस्।', 'Check Member ID card authenticity. Verify active membership using Verification Code and CVV.'), ENT_QUOTES, 'UTF-8'); ?>">
<?php if (function_exists('seo_canonical_url')): ?>
<link rel="canonical" href="<?= htmlspecialchars(seo_canonical_url(), ENT_QUOTES, 'UTF-8') ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/design-tokens.css?v=3">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/auth-portals-unified.css?v=4">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/eye-candy-verify-v7.css?v=10">
<?php @require_once __DIR__ . '/assets/css/_color-vars.php'; ?>
<style>
*,*::before,*::after { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: var(--font-primary,'Mukta','Noto Sans Devanagari','Segoe UI',sans-serif);
    min-height: 100dvh;
    background:
        radial-gradient(ellipse 85% 55% at 15% -5%, color-mix(in srgb, var(--primary-color) 14%, transparent) 0%, transparent 52%),
        radial-gradient(ellipse 70% 50% at 100% 0%, color-mix(in srgb, var(--secondary-color) 10%, transparent) 0%, transparent 48%),
        radial-gradient(circle at 50% 120%, color-mix(in srgb, var(--accent-color,#17a2b8) 8%, transparent) 0%, transparent 45%),
        linear-gradient(165deg,#f0fdf4 0%,#ecfdf5 38%,#f0f9ff 100%);
    color: var(--text-color,#1f2937);
    padding: 20px 12px 40px;
}
.page-back {
    display: inline-flex; align-items: center; gap: 6px;
    color: var(--primary-color,#1a8754);
    text-decoration: none; font-weight: 700; font-size: .8rem;
    margin-bottom: 14px;
    padding: 6px 14px; border-radius: 999px;
    background: rgba(255,255,255,.85); border: 1px solid color-mix(in srgb, var(--primary-color) 16%, #e5e7eb);
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    transition: all .15s;
}
.page-back:hover { background: color-mix(in srgb, var(--primary-color) 8%, #ffffff); transform: translateX(-2px); }

/* Outer wrap */
.vp-outer { max-width: 560px; margin: 0 auto; }

/* Page header — logo centered above card */
.vp-page-header {
    text-align: center; margin-bottom: 18px;
}
.vp-page-logo {
    display: inline-flex; align-items: center; justify-content: center;
    margin-bottom: 0;
}
.vp-page-logo img {
    height: auto; width: auto;
    max-height: 64px; max-width: 190px;
    object-fit: contain; border-radius: 8px;
}
.vp-page-logo-icon {
    width: 60px; height: 60px; border-radius: 14px;
    background: linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));
    color: #fff; display: grid; place-items: center;
    font-size: 1.5rem; margin: 0 auto;
    box-shadow: 0 6px 18px rgba(26,95,42,.28);
}

/* Main stack card */
.vp-card {
    background: transparent;
    border-radius: 0;
    border: none;
    padding: 0;
    box-shadow: none;
}

/* inner content styles (preserved) */
.vp-icon {
    width: 56px; height: 56px; margin: 0 auto 10px;
    border-radius: 50%;
    background: linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));
    color: #fff; display: grid; place-items: center;
    font-size: 26px; box-shadow: 0 8px 20px rgba(13,92,46,.25);
}
h1 { text-align:center; margin:0 0 6px; font-size:1.45rem; font-weight:800; line-height:1.2; letter-spacing:-.22px; color:var(--primary-dark,#0a4a25); }
.vp-sub { text-align:center; color:#64748b; font-size:.88rem; margin:0 0 14px; line-height:1.45; }

.vp-field { margin-bottom:12px; }
.vp-field label { display:block; font-size:12.5px; font-weight:700; color:var(--text-color,#374151); text-transform:uppercase; letter-spacing:.05em; margin-bottom:6px; }
.vp-field input {
    width:100%; padding:11px 12px; border:1.5px solid color-mix(in srgb, var(--primary-color) 20%, #d1d5db);
    border-radius:9px; font-size:14px; font-family:inherit;
    transition:border-color .15s,box-shadow .15s; background:#fff;
}
.vp-field input:focus { outline:none; border-color:var(--primary-color,#1a8754); box-shadow:0 0 0 3px rgba(13,92,46,.12); }
.vp-field .hint { font-size:10px; color:#9ca3af; margin-top:3px; }
#code { font-family:'Courier New',monospace; letter-spacing:.12em; text-transform:uppercase; font-weight:700; }
#cvv  { font-family:'Courier New',monospace; letter-spacing:.35em; text-align:center; font-weight:700; font-size:16px; }

.vp-btn {
    width:100%; padding:11px; border:none; border-radius:9px;
    background:linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));
    color:#fff; font-size:14px; font-weight:700; cursor:pointer;
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    box-shadow:0 4px 14px rgba(26,135,84,.3);
    transition:all .15s;
}
.vp-btn:hover { filter:brightness(1.06); transform:translateY(-1px); }

.vp-result-ok { margin-bottom:18px; animation:pop .35s ease-out; }
@keyframes pop { from{transform:scale(.98);opacity:0} to{transform:scale(1);opacity:1} }
.vp-ok-head { display:flex; align-items:center; gap:10px; margin-bottom:16px; color:#065f46; font-weight:700; font-size:16px; }
.vp-ok-head i { font-size:22px; }
.vp-result-fail {
    background:color-mix(in srgb, var(--secondary-color) 12%, #ffffff); border:1.5px solid var(--secondary-color); border-radius:14px;
    padding:16px 18px; margin-bottom:18px;
    color:var(--secondary-dark,var(--secondary-color)); font-size:14px; font-weight:600;
    display:flex; align-items:center; gap:10px;
}
.vp-result-fail i { font-size:18px; }
.vp-help { font-size:12px; color:#6b7280; text-align:center; margin-top:22px; padding-top:16px; border-top:1px solid rgba(226,232,240,.95); line-height:1.65; max-width:520px; margin-left:auto; margin-right:auto; }
.vp-help b { color:var(--primary-color,#1a8754); }
.vp-mini-tab { border:1px solid #d1d5db; background:#fff; color:#334155; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:700; cursor:pointer; }
.vp-mini-tab.is-active { background:var(--primary-color,#1a8754); color:#fff; border-color:var(--primary-color,#1a8754); }
/* Form card — same language as program-attendance-verify */
.verify-form-card {
    margin-top: 10px;
    border-radius: 20px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 4px 6px rgba(0,0,0,.04), 0 24px 50px rgba(13,92,46,.12);
    border: 1px solid rgba(226,232,240,.9);
}
.vp-card > .vp-result-ok + .verify-form-card { margin-top: 20px; }
.verify-form-card__head {
    background: linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));
    padding: 20px 18px 18px;
    text-align: center;
    color: #fff;
}
.verify-form-card__head .vp-icon {
    width: 56px; height: 56px; margin: 0 auto 12px;
    border-radius: 50%;
    background: rgba(255,255,255,.18);
    box-shadow: 0 4px 16px rgba(0,0,0,.15);
    font-size: 24px;
}
.verify-form-card__head h1 {
    color: #fff !important;
    font-size: 1.15rem !important;
    margin: 0 0 8px !important;
    letter-spacing: -.02em;
}
.verify-form-card__head .vp-sub {
    color: rgba(255,255,255,.88) !important;
    font-size: .8rem !important;
    margin: 0 !important;
    line-height: 1.55 !important;
}
.verify-form-card__head .vp-sub b { color: #fff; font-weight: 700; }
.verify-form-card__body { padding: 18px 16px 20px; }
.verify-form-card__body .vp-field { margin-bottom: 14px; }
.verify-form-card__body .vp-field label {
    font-size: 11px;
    font-weight: 700;
    color: var(--text-light,#64748b);
    letter-spacing: .08em;
}
.verify-form-card__body .vp-field input {
    padding: 11px 14px;
    border-radius: 10px;
    border: 1.5px solid color-mix(in srgb, var(--primary-color) 20%, #d1d5db);
    font-size: 14px;
}
.verify-form-card__body .vp-field input:focus {
    border-color: var(--primary-color,#1a8754);
    box-shadow: 0 0 0 3px rgba(26,135,84,.14);
}
.verify-form-card__body .vp-btn {
    padding: 11px;
    border-radius: 10px;
    font-size: 14px;
    margin-top: 4px;
}
.vp-logo-hide { display: none !important; }

@media (max-width:768px) {
    body { padding:14px 12px 32px; }
    h1 { font-size:1.3rem; }
}
</style>
</head>
<body class="auth-portal-page verify-auth-page">
<div class="vp-outer">

    <a href="<?php echo SITE_URL; ?>" class="page-back"><i class="fas fa-arrow-left"></i> <?php echo $_t('मुख्य पृष्ठ', 'Home'); ?></a>

    <?php
    $v7vLogoAlt = function_exists('getSetting') ? getSetting('site_name','सहकारी') : 'सहकारी';
    $v7vLogoSrc = '';
    if (!empty($coopLogo)) {
        $v7vLogoSrc = (strpos($coopLogo,'http')===0) ? $coopLogo : (SITE_URL.ltrim($coopLogo,'/'));
    }
    $v7vSiteName = function_exists('getSetting') ? getSetting('site_name','सहकारी संस्था') : 'सहकारी संस्था';
    ?>
    <div class="vp-page-header">
        <?php if ($v7vLogoSrc): ?>
            <div class="vp-page-logo">
                <img src="<?= htmlspecialchars($v7vLogoSrc) ?>" alt="<?= htmlspecialchars($v7vLogoAlt) ?>"
                     onerror="this.classList.add('vp-logo-hide');var el=document.getElementById('vpLogoFallback');if(el)el.style.display='grid';">
                <div id="vpLogoFallback" class="vp-page-logo-icon" style="display:none" aria-hidden="true"><i class="fas fa-shield-halved"></i></div>
            </div>
        <?php else: ?>
            <div class="vp-page-logo-icon"><i class="fas fa-shield-halved"></i></div>
        <?php endif; ?>
    </div>

<div class="vp-card">
    <?php if ($result && !empty($result['ok'])): ?>
      <?php
        $vpLogoSrc = '';
        if (!empty($coopLogo)) {
          $vpLogoSrc = (strpos($coopLogo, 'http') === 0) ? $coopLogo : (SITE_URL . ltrim($coopLogo, '/'));
        }
      ?>
      <div class="vp-result-ok">
        <article class="vp-idcard" aria-label="प्रमाणित सदस्य परिचय">
          <div class="vp-idcard-shine" aria-hidden="true"></div>
          <div class="vp-idcard-ribbon" aria-hidden="true"><span><?php echo $_t('सक्रिय सदस्य', 'Active Member'); ?></span></div>
          <header class="vp-idcard-head">
            <div class="vp-idcard-head-brand">
              <?php if ($vpLogoSrc): ?>
                <img src="<?= htmlspecialchars($vpLogoSrc) ?>" alt="" class="vp-idcard-logo" width="48" height="48">
              <?php else: ?>
                <div class="vp-idcard-logo vp-idcard-logo--fallback" aria-hidden="true"><i class="fas fa-building"></i></div>
              <?php endif; ?>
              <div class="vp-idcard-head-text">
                <div class="vp-idcard-org"><?= htmlspecialchars($v7vSiteName) ?></div>
                <div class="vp-idcard-sub"><?php echo $_t('Member verification · सार्वजनिक प्रमाणीकरण', 'Member verification · Public verification'); ?></div>
              </div>
            </div>
            <div class="vp-idcard-seal" title="<?php echo $_t('सत्यता प्रमाणित', 'Verified'); ?>">
              <i class="fas fa-shield-halved" aria-hidden="true"></i>
              <span><?php echo $_t('प्रमाणित', 'Verified'); ?></span>
            </div>
          </header>
          <div class="vp-idcard-body">
            <div class="vp-idcard-photoCol">
              <div class="vp-idcard-photoFrame">
                <img src="<?= htmlspecialchars($photoUrl) ?>" alt="" class="vp-idcard-photo"
                     onerror="this.src='/member/assets/photo-placeholder.svg'">
              </div>
              <div class="vp-idcard-chip" aria-hidden="true"></div>
            </div>
            <div class="vp-idcard-info">
              <p class="vp-idcard-status"><i class="fas fa-circle-check" aria-hidden="true"></i> <?php echo $_t('सत्यता प्रमाणित — सक्रिय सदस्य', 'Verified — Active Member'); ?></p>
              <h2 class="vp-idcard-name"><?= htmlspecialchars($result['member']['full_name']) ?></h2>
              <div class="vp-idcard-mid">
                <span class="vp-idcard-mid-label"><?php echo $_t('सदस्यता नं.', 'Member No.'); ?></span>
                <span class="vp-idcard-mid-num"><?= htmlspecialchars($result['member']['member_id']) ?></span>
              </div>
              <dl class="vp-idcard-rows">
                <div class="vp-idcard-row">
                  <dt><?php echo $_t('बुबाको नाम', "Father's Name"); ?></dt>
                  <dd><?= htmlspecialchars($result['member']['father_name'] ?? '—') ?></dd>
                </div>
                <div class="vp-idcard-row">
                  <dt><?php echo $_t('मोबाइल', 'Mobile'); ?></dt>
                  <dd><?= htmlspecialchars($result['member']['mobile'] ?? '—') ?></dd>
                </div>
                <div class="vp-idcard-row vp-idcard-row--full">
                  <dt><?php echo $_t('इमेल', 'Email'); ?></dt>
                  <dd><?= htmlspecialchars($result['member']['email'] ?? '—') ?></dd>
                </div>
              </dl>
            </div>
          </div>
          <footer class="vp-idcard-foot">
            <span class="vp-idcard-foot-org"><?= htmlspecialchars($v7vSiteName) ?></span>
            <span class="vp-idcard-foot-meta"><?php echo $_t('सम्पर्क', 'Contact'); ?> <?= htmlspecialchars($coopPhone) ?> · <?= htmlspecialchars($coopWebsite !== '' ? $coopWebsite : '—') ?></span>
          </footer>
        </article>
      </div>

      <?php if ($logSaved || $programSaved || $programAlreadyRegistered): ?>
        <div class="vp-result-ok" style="padding:14px 18px;">
          <div class="vp-ok-head" style="margin:0;font-size:14px;">
            <i class="fas fa-floppy-disk"></i>
            <?php if ($programAlreadyRegistered): ?>
              <?php echo $_t('यो सदस्यको यो कार्यक्रममा पहिल्यै Registration भइसकेको छ।', 'This member is already registered for this program.'); ?>
            <?php elseif ($programSaved): ?>
              <?php echo $_t('कार्यक्रम उपस्थिति रेकर्ड सुरक्षित भयो — धन्यवाद।', 'Program attendance record saved — thank you.'); ?>
            <?php else: ?>
              <?php echo $_t('सेवा रेकर्ड सुरक्षित भयो — धन्यवाद।', 'Service record saved — thank you.'); ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <div style="background:#f9fafb;border:1.5px solid #e5e7eb;border-radius:12px;padding:14px 14px 16px;margin-bottom:18px;">
        <div class="vp-mini-tabs" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
          <button type="button" class="vp-mini-tab is-active" data-target="vpPaneService"><?php echo $_t('साझेदार सेवा रेकर्ड', 'Partner Service Record'); ?></button>
          <button type="button" class="vp-mini-tab" data-target="vpPaneProgram"><?php echo $_t('कार्यक्रम उपस्थिति', 'Program Attendance'); ?></button>
        </div>

        <div id="vpPaneService" class="vp-mini-pane">
          <form method="POST" autocomplete="off" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
            <input type="hidden" name="action" value="log_service">
            <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
            <input type="hidden" name="cvv"  value="<?= htmlspecialchars($cvv) ?>">
            <input type="hidden" name="member_id" value="<?= (int)($result['member']['id'] ?? 0) ?>">
            <input type="hidden" name="member_card_no" value="<?= htmlspecialchars($result['member']['member_id'] ?? '') ?>">

            <div class="vp-field" style="margin-bottom:10px;">
              <label><?php echo $_t('साझेदार संस्था चयन गर्नुहोस्', 'Select Partner Organization'); ?></label>
              <select name="partner_id" id="partnerSel" class="vp-field-input" style="width:100%;padding:11px 12px;border:1.5px solid #d1d5db;border-radius:9px;font-family:inherit;font-size:14px;">
                <option value="0"><?php echo $_t('— चयन गर्नुहोस् वा तल नाम लेख्नुहोस् —', '— Select or type name below —'); ?></option>
                <?php foreach ($partners as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" data-name="<?= htmlspecialchars($p['partner_name']) ?>"><?= htmlspecialchars($p['partner_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vp-field" style="margin-bottom:10px;">
              <label><?php echo $_t('संस्थाको नाम (custom)', 'Organization Name (custom)'); ?></label>
              <input type="text" name="partner_name" id="partnerName" placeholder="<?php echo $_t('उदाहरण: आकाश हस्पिटल', 'Example: Aakash Hospital'); ?>" required readonly>
            </div>
            <div class="vp-field" style="margin-bottom:10px;">
              <label><?php echo $_t('लिएको सेवा (के सेवा?)', 'Service Used'); ?></label>
              <input type="text" name="service_name" id="serviceName" maxlength="255" placeholder="<?php echo $_t('उदाहरण: OPD Discount / Lab / Banking Service', 'Example: OPD Discount / Lab / Banking Service'); ?>" required>
            </div>
            <div class="vp-field" style="margin-bottom:10px;">
              <label><?php echo $_t('सेवा लिनुभयो?', 'Service Taken?'); ?></label>
              <div style="display:flex;gap:14px;font-size:14px;color:#374151;">
                <label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;"><input type="radio" name="service_taken" value="yes" checked> <?php echo $_t('हो', 'Yes'); ?></label>
                <label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;"><input type="radio" name="service_taken" value="no"> <?php echo $_t('होइन', 'No'); ?></label>
              </div>
            </div>
            <div class="vp-field" style="margin-bottom:14px;">
              <label><?php echo $_t('नोट / सेवा विवरण (वैकल्पिक)', 'Note / Service Detail (optional)'); ?></label>
              <input type="text" name="service_note" maxlength="500" placeholder="<?php echo $_t('उदाहरण: OPD discount 10%', 'Example: OPD discount 10%'); ?>">
            </div>
            <button type="submit" class="vp-btn" style="padding:11px;">
              <i class="fas fa-floppy-disk"></i> <?php echo $_t('सेवा रेकर्ड सुरक्षित गर्नुहोस्', 'Save Service Record'); ?>
            </button>
          </form>
        </div>

        <div id="vpPaneProgram" class="vp-mini-pane" style="display:none;">
          <?php if (empty($activePrograms)): ?>
            <div class="hint" style="font-size:13px;"><?php echo $_t('हाल सक्रिय कार्यक्रम उपलब्ध छैन।', 'No active program available right now.'); ?></div>
          <?php else: ?>
          <form method="POST" autocomplete="off" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
            <input type="hidden" name="action" value="log_program_attendance">
            <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
            <input type="hidden" name="cvv"  value="<?= htmlspecialchars($cvv) ?>">
            <input type="hidden" name="member_id" value="<?= (int)($result['member']['id'] ?? 0) ?>">
            <input type="hidden" name="member_card_no" value="<?= htmlspecialchars($result['member']['member_id'] ?? '') ?>">
            <div class="vp-field" style="margin-bottom:10px;">
              <label><?php echo $_t('सक्रिय कार्यक्रम छान्नुहोस्', 'Select Active Program'); ?></label>
              <select name="program_id" id="programSel" required style="width:100%;padding:11px 12px;border:1.5px solid #d1d5db;border-radius:9px;font-family:inherit;font-size:14px;">
                <option value=""><?php echo $_t('— कार्यक्रम चयन गर्नुहोस् —', '— Select Program —'); ?></option>
                <?php foreach ($activePrograms as $pg): ?>
                  <option value="<?= (int)$pg['id'] ?>" data-title="<?= htmlspecialchars($pg['title']) ?>">
                    <?= htmlspecialchars($pg['title']) ?>
                    <?= !empty($pg['event_date']) ? ' (' . htmlspecialchars($pg['event_date']) . ')' : '' ?>
                    <?= !empty($pg['location']) ? ' - ' . htmlspecialchars($pg['location']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="program_title" id="programTitleInput" value="">
            </div>
            <div class="vp-field" style="margin-bottom:10px;">
              <label style="display:inline-flex;align-items:center;gap:8px;text-transform:none;letter-spacing:0;">
                <input type="checkbox" name="is_priority" value="1"> <?php echo $_t('यो कार्यक्रम प्राथमिकता हो', 'This is a priority program'); ?>
              </label>
            </div>
            <div class="vp-field" style="margin-bottom:14px;">
              <label><?php echo $_t('टिप्पणी (वैकल्पिक)', 'Comment (optional)'); ?></label>
              <input type="text" name="attendance_note" maxlength="500" placeholder="<?php echo $_t('उदाहरण: वार्षिक साधारण सभा उपस्थित', 'Example: Attended annual general meeting'); ?>">
            </div>
            <button type="submit" class="vp-btn" style="padding:11px;">
              <i class="fas fa-user-check"></i> <?php echo $_t('उपस्थिति सुरक्षित गर्नुहोस्', 'Save Attendance'); ?>
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>

    <?php elseif ($result && empty($result['ok'])): ?>
      <div class="vp-result-fail">
        <i class="fas fa-circle-exclamation"></i>
        <span><?= htmlspecialchars($result['error']) ?></span>
      </div>
    <?php endif; ?>

    <div class="verify-form-card">
      <div class="verify-form-card__head">
        <div class="vp-icon"><i class="fas fa-id-card-clip"></i></div>
        <h1><?php echo $_t('सदस्य प्रमाणीकरण', 'Member Verification'); ?></h1>
        <p class="vp-sub"><?php echo $_t('ID Card को <b>Verification Code / Card Number</b> र <b>4-अङ्कको CVV</b> राखेर सदस्य सत्यता जाँच गर्नुहोस्।', 'Enter <b>Verification Code / Card Number</b> and <b>4-digit CVV</b> to verify member authenticity.'); ?></p>
      </div>
      <div class="verify-form-card__body">
      <form method="POST" autocomplete="off" novalidate class="needs-validation">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken()) ?>">
        <div class="vp-field">
          <label for="code">Verification Code / Card Number</label>
          <input type="text" id="code" name="code" required maxlength="20"
                 placeholder="<?= htmlspecialchars($cardPrefix) ?>-XXXX-XXXX वा <?= htmlspecialchars($cardPrefix) ?>-2026-00001"
                 value="<?= htmlspecialchars($code) ?>">
          <div class="hint"><?php echo $_t('Code वा Card Number दुवै मान्य छन्।', 'Both code and card number are valid.'); ?></div>
          <div class="hint" id="codeLiveHint" style="color:var(--secondary-dark, var(--primary-dark, #0f766e));font-weight:600;"></div>
        </div>

        <div class="vp-field">
          <label for="cvv"><?php echo $_t('CVV (4 अङ्क)', 'CVV (4 digits)'); ?></label>
          <input type="text" id="cvv" name="cvv" required inputmode="numeric"
                 pattern="[0-9]{4}" maxlength="4" placeholder="••••"
                 value="<?= htmlspecialchars($cvv) ?>">
          <div class="hint"><?php echo $_t('कार्डको पछाडि वा कुनामा छापिएको गोप्य 4 अङ्क', 'Secret 4 digits printed on back/corner of card'); ?></div>
        </div>

        <button type="submit" class="vp-btn">
          <i class="fas fa-shield-halved"></i> <?php echo $_t('सदस्य प्रमाणित गर्नुहोस्', 'Verify Member'); ?>
        </button>
      </form>
      </div>
    </div>

    <div class="vp-help">
      🔒 <?php echo $_t('तपाईंको प्रत्येक प्रयास log गरिन्छ। Card अरूलाई share नगर्नुहोस्।', 'Every attempt is logged. Do not share card details with others.'); ?><br>
      <?php echo $_t('समस्या भए कार्यालयमा सम्पर्क गर्नुहोस्।', 'If you face issues, contact office.'); ?>
    </div>
  </div>
</div>

<script>
  // Input helper (accept verification code OR card number)
  const CARD_PREFIX = <?= json_encode((string)$cardPrefix) ?>;
  const codeInput = document.getElementById('code');
  const codeLiveHint = document.getElementById('codeLiveHint');

  function updateCodeHint(v) {
    const raw = (v || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (!codeLiveHint) return;
    if (!raw) {
      codeLiveHint.textContent = '';
      return;
    }
    const looksLikeCardNo = /^[A-Z]{3}[0-9]{4}[0-9]{5}$/.test(raw);
    const looksLikeVerify = /^[A-Z]{3}[A-Z0-9]{8}$/.test(raw) || /^[A-Z0-9]{8}$/.test(raw);
    if (looksLikeCardNo) {
      codeLiveHint.textContent = 'Card Number format detected';
    } else if (looksLikeVerify) {
      codeLiveHint.textContent = 'Verification Code format detected';
    } else {
      codeLiveHint.textContent = <?php echo json_encode($_t('Format: PREFIX-XXXX-XXXX वा PREFIX-YYYY-NNNNN', 'Format: PREFIX-XXXX-XXXX or PREFIX-YYYY-NNNNN')); ?>;
    }
  }

  codeInput.addEventListener('input', function (e) {
    e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
    updateCodeHint(e.target.value);
  });
  if (!codeInput.value) codeInput.placeholder = (CARD_PREFIX || 'AKS') + '-XXXX-XXXX' + <?php echo json_encode($_t(' वा ', ' or ')); ?> + (CARD_PREFIX || 'AKS') + '-2026-00001';
  updateCodeHint(codeInput.value);

  // CVV digits only
  document.getElementById('cvv').addEventListener('input', function (e) {
    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
  });

  // Partner/service + program tab helpers
  (function(){
    var s = document.getElementById('partnerSel');
    var n = document.getElementById('partnerName');
    if (s && n) {
      s.addEventListener('change', function(){
        var o = s.options[s.selectedIndex];
        n.value = (o && o.dataset.name) ? o.dataset.name : '';
      });
    }

    var pSel = document.getElementById('programSel');
    var pTitle = document.getElementById('programTitleInput');
    if (pSel && pTitle) {
      pSel.addEventListener('change', function(){
        var o = pSel.options[pSel.selectedIndex];
        pTitle.value = (o && o.dataset.title) ? o.dataset.title : '';
      });
    }

    var tabs = document.querySelectorAll('.vp-mini-tab');
    tabs.forEach(function(btn){
      btn.addEventListener('click', function(){
        tabs.forEach(function(b){ b.classList.remove('is-active'); });
        btn.classList.add('is-active');
        document.querySelectorAll('.vp-mini-pane').forEach(function(p){ p.style.display = 'none'; });
        var tgt = document.getElementById(btn.getAttribute('data-target'));
        if (tgt) tgt.style.display = '';
      });
    });
  })();
</script>

</div>
</body>
</html>
