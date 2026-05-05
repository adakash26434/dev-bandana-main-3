<?php
/**
 * QR-Based Program Attendance Check-in (Public)
 * URL: attend.php?token=XXXX
 * Member opens QR from phone → login check → mark present
 */
require_once 'includes/config.php';
require_once 'includes/ensure-tables.php';

$token = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['token'] ?? ''));
$db    = null;
$prog  = null;
$err   = '';
$done  = false;
$requestSubmitted = false;
$alreadyDone = false;
$memberId = null;
$memName  = '';
$memCard  = '';

try { $db = getDB(); } catch (Throwable $e) { $err = 'Database unavailable.'; }

if ($db) {
    ensureProgramTables($db);
}

/* ── Load program from token ── */
if ($db && $token) {
    try {
        $st = $db->prepare("SELECT * FROM upcoming_programs WHERE qr_token=? AND is_active=1 LIMIT 1");
        $st->execute([$token]);
        $prog = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$prog) $err = 'यो QR code मान्य छैन वा कार्यक्रम समाप्त भयो।';
    } catch (Throwable $e) { $err = 'कार्यक्रम लोड गर्न सकिएन।'; }
} elseif (!$token) {
    $err = 'QR code token आवश्यक छ।';
}

/* ── Check if member is already logged in via member portal session ── */
session_start();
if (!empty($_SESSION['member_id']) && $prog && $db) {
    $memberId = (int)$_SESSION['member_id'];
    try {
        $ms = $db->prepare("SELECT m.name, m.sadasyata_number, k.member_id AS kyc_member_id
                            FROM members m
                            LEFT JOIN kyc_applications k ON k.id = m.kyc_application_id
                            WHERE m.id=? LIMIT 1");
        $ms->execute([$memberId]);
        $mr = $ms->fetch(PDO::FETCH_ASSOC) ?: [];
        $memName = trim((string)($mr['name'] ?? ''));
        $kycMid = trim((string)($mr['kyc_member_id'] ?? ''));
        $memCard = $kycMid !== '' ? $kycMid : trim((string)($mr['sadasyata_number'] ?? ''));
        /* Check if already checked in */
        $dup = $db->prepare("SELECT id FROM member_program_attendance WHERE member_id=? AND program_id=? LIMIT 1");
        $dup->execute([$memberId, (int)$prog['id']]);
        if ($dup->fetchColumn()) $alreadyDone = true;
    } catch (Throwable $e) { $memberId = null; }
}

/* ── Handle check-in form submit ── */
$csrfOk = true;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $prog && $db && !$err) {
    if (!verifyCSRFToken()) {
        $err = 'Security check failed.';
        $csrfOk = false;
    }
    if ($csrfOk) {
        $action = $_POST['action'] ?? '';
        if ($action === 'checkin_logged') {
            /* Logged-in member check-in */
            if ($memberId && !$alreadyDone) {
                try {
                    $ins = $db->prepare("INSERT INTO member_program_attendance
                        (member_id, member_card_no, program_id, program_title, source, verified_by_ip)
                        VALUES (?,?,?,?,?,?)");
                    $ins->execute([$memberId, $memCard, (int)$prog['id'], $prog['title'], 'qr_scan', $_SERVER['REMOTE_ADDR'] ?? '']);
                    $done = true; $alreadyDone = true;
                } catch (Throwable $e) { $err = 'Check-in गर्न समस्या भयो।'; }
            }
        } elseif ($action === 'checkin_manual') {
            /* Manual: enter member ID + phone */
            $inputCard  = trim(preg_replace('/\s+/', '', strtoupper($_POST['member_card'] ?? '')));
            $inputPhone = preg_replace('/[^0-9]/', '', $_POST['phone'] ?? '');
            if (!$inputCard && !$inputPhone) {
                $err = 'सदस्यता नम्बर वा फोन नम्बर आवश्यक छ।';
            } else {
                try {
                    /* Find member by card or phone */
                    $kw = []; $kp = [];
                    if ($inputCard)  { $kw[] = 'UPPER(sadasyata_number)=?'; $kp[] = $inputCard; }
                    if ($inputPhone) { $kw[] = 'phone=?'; $kp[] = $inputPhone; }
                    $mst = $db->prepare("SELECT m.id, m.name, m.sadasyata_number FROM members m WHERE (" . implode(' OR ', $kw) . ") AND is_active=1 LIMIT 1");
                    $mst->execute($kp);
                    $mRow = $mst->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (!$mRow) {
                        /* Try via KYC */
                        $kwk = []; $kpk = [];
                        if ($inputCard)  { $kwk[] = 'UPPER(member_id)=?'; $kpk[] = $inputCard; }
                        if ($inputPhone) { $kwk[] = 'mobile=?'; $kpk[] = $inputPhone; }
                        $kst = $db->prepare("SELECT m.id, m.name, m.sadasyata_number FROM members m
                            JOIN kyc_applications k ON (LOWER(k.email)=LOWER(m.email) OR k.mobile=m.phone)
                            WHERE (" . implode(' OR ', $kwk) . ") AND m.is_active=1 LIMIT 1");
                        $kst->execute($kpk);
                        $mRow = $kst->fetch(PDO::FETCH_ASSOC) ?: null;
                    }
                    if (!$mRow) {
                        $err = 'सदस्य फेला परेन। सदस्यता नम्बर वा फोन जाँच्नुहोस्।';
                    } else {
                        $mId   = (int)$mRow['id'];
                        $mCard = trim((string)($mRow['sadasyata_number'] ?? ''));
                        $mNm   = trim((string)($mRow['name'] ?? ''));
                        /* Already counted as attended */
                        $dup = $db->prepare("SELECT id FROM member_program_attendance WHERE member_id=? AND program_id=? LIMIT 1");
                        $dup->execute([$mId, (int)$prog['id']]);
                        if ($dup->fetchColumn()) {
                            $alreadyDone = true; $memName = $mNm; $memCard = $mCard; $memberId = $mId;
                        } elseif ($memberId && $mId === $memberId) {
                            /* आफै लगइन भएको सदस्यले manual फारम प्रयोग — तुरुन्त दर्ता */
                            $ins = $db->prepare("INSERT INTO member_program_attendance
                                (member_id, member_card_no, program_id, program_title, source, verified_by_ip)
                                VALUES (?,?,?,?,?,?)");
                            $ins->execute([$mId, $mCard, (int)$prog['id'], $prog['title'], 'qr_manual', $_SERVER['REMOTE_ADDR'] ?? '']);
                            $done = true; $memName = $mNm; $memCard = $mCard; $memberId = $mId;
                        } else {
                            /* अरुको वा लगइन बिना: Admin स्वीकृति पछि मात्र सूचीमा */
                            $pend = $db->prepare("SELECT id FROM member_program_attendance_requests WHERE member_id=? AND program_id=? AND status='pending' LIMIT 1");
                            $pend->execute([$mId, (int)$prog['id']]);
                            if ($pend->fetchColumn()) {
                                $err = 'तपाईंको उपस्थिति अनुरोध पहिले नै Admin सामु प्रक्रियामा छ। स्वीकृत भएपछि सूचीमा देखिनेछ।';
                            } else {
                                $ins = $db->prepare("INSERT INTO member_program_attendance_requests
                                    (member_id, member_card_no, member_name, program_id, program_title, status, verified_by_ip, source)
                                    VALUES (?,?,?,?,?,'pending',?,?)");
                                $ins->execute([
                                    $mId,
                                    $mCard,
                                    $mNm,
                                    (int)$prog['id'],
                                    mb_substr((string)$prog['title'], 0, 180),
                                    $_SERVER['REMOTE_ADDR'] ?? '',
                                    'public_qr_request',
                                ]);
                                $requestSubmitted = true;
                                $memName = $mNm;
                                $memCard = $mCard;
                                $memberId = $mId;
                            }
                        }
                    }
                } catch (Throwable $e) { $err = 'Check-in गर्न समस्या भयो।'; error_log('[attend] ' . $e->getMessage()); }
            }
        }
    }
}

$siteName     = function_exists('getSetting') ? getSetting('site_name', 'सहकारी') : 'सहकारी';
$primaryColor = function_exists('getSetting') ? getSetting('primary_color', '#1a5f2a') : '#1a5f2a';
$siteLogo     = function_exists('getSetting') ? getSetting('site_logo', 'assets/images/logo.png') : 'assets/images/logo.png';
$csrfField    = function_exists('generateCSRFToken') ? '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">' : '';
$evDate       = $prog ? ($prog['event_date'] ? date('Y F d', strtotime($prog['event_date'])) : '') : '';
?>
<!DOCTYPE html>
<html lang="ne" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>QR Attendance — <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Mukta',sans-serif;background:linear-gradient(135deg,#f0f9f2,#e8f5e9);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;}
.card{background:#fff;border-radius:18px;box-shadow:0 8px 40px rgba(0,0,0,.12);max-width:420px;width:100%;overflow:hidden;}
.card-top{background:<?= htmlspecialchars($primaryColor) ?>;color:#fff;padding:20px 24px;text-align:center;}
.card-top .prog-icon{width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.2);display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:10px;}
.card-top h1{font-size:1.15rem;font-weight:800;line-height:1.3;}
.card-top .meta{font-size:.8rem;opacity:.85;margin-top:6px;}
.card-body{padding:24px;}
.success-icon{width:72px;height:72px;border-radius:50%;background:#f0fdf4;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#16a34a;margin:0 auto 14px;border:3px solid #bbf7d0;}
.error-box{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px;color:#dc2626;font-size:.88rem;margin-bottom:14px;display:flex;gap:8px;}
.info-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:12px 14px;font-size:.83rem;color:#1e40af;margin-bottom:14px;display:flex;gap:8px;}
.form-group{margin-bottom:13px;}
.form-group label{display:block;font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;}
.form-control{width:100%;padding:10px 14px;min-height:44px;border:1.5px solid #d1d5db;border-radius:10px;font-family:inherit;font-size:.9rem;background:#f9fafb;transition:border-color .2s;line-height:1.4;}
.form-control:focus{outline:none;border-color:<?= htmlspecialchars($primaryColor) ?>;background:#fff;}
.btn-primary{width:100%;padding:12px;background:<?= htmlspecialchars($primaryColor) ?>;color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:opacity .2s;}
.btn-primary:hover{opacity:.9;}
.btn-secondary{width:100%;padding:10px;background:#fff;color:<?= htmlspecialchars($primaryColor) ?>;border:2px solid <?= htmlspecialchars($primaryColor) ?>;border-radius:10px;font-family:inherit;font-size:.9rem;font-weight:700;cursor:pointer;margin-top:10px;display:flex;align-items:center;justify-content:center;gap:8px;}
.divider{text-align:center;font-size:.78rem;color:#9ca3af;margin:14px 0;position:relative;}
.divider::before{content:'';position:absolute;top:50%;left:0;right:0;height:1px;background:#e5e7eb;z-index:0;}
.divider span{background:#fff;padding:0 10px;position:relative;z-index:1;}
.footer-link{text-align:center;margin-top:14px;font-size:.78rem;color:#9ca3af;}
.footer-link a{color:<?= htmlspecialchars($primaryColor) ?>;text-decoration:none;font-weight:600;}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <div class="prog-icon"><i class="fas fa-calendar-check"></i></div>
    <h1><?= $prog ? htmlspecialchars($prog['title']) : htmlspecialchars($siteName) . ' — QR Attendance' ?></h1>
    <?php if ($prog): ?>
    <div class="meta">
      <?php if ($evDate): ?><i class="fas fa-calendar" style="margin-right:4px;"></i><?= $evDate ?><?php endif; ?>
      <?php if ($prog['event_time']): ?> &nbsp;·&nbsp; <i class="fas fa-clock" style="margin-right:3px;"></i><?= htmlspecialchars($prog['event_time']) ?><?php endif; ?>
      <?php if ($prog['location']): ?><br><i class="fas fa-location-dot" style="margin-right:3px;"></i><?= htmlspecialchars($prog['location']) ?><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <?php if ($err && !$done && !$requestSubmitted): ?>
    <div class="error-box"><i class="fas fa-circle-xmark" style="flex-shrink:0;margin-top:2px;"></i><div><?= htmlspecialchars($err) ?></div></div>
    <?php elseif ($requestSubmitted && $prog): ?>
    <div style="text-align:center;padding:8px 0;">
      <div class="success-icon" style="background:#fffbeb;border-color:#fcd34d;"><i class="fas fa-hourglass-half" style="color:#b45309;"></i></div>
      <h2 style="font-size:1.15rem;font-weight:800;color:#92400e;margin-bottom:8px;">अनुरोध पठाइयो</h2>
      <p style="font-size:.88rem;color:#6b7280;margin-bottom:12px;">
        <?= htmlspecialchars($memName ?: 'सदस्य') ?><?php if ($memCard): ?> (<?= htmlspecialchars($memCard) ?>)<?php endif; ?> — <strong><?= htmlspecialchars($prog['title']) ?></strong>
      </p>
      <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:12px;font-size:.84rem;color:#78350f;text-align:left;line-height:1.5;">
        <i class="fas fa-user-shield" style="margin-right:6px;"></i><strong>Admin</strong> ले कार्यक्रम स्थलमा/panel मा <strong>स्वीकृत</strong> गरेपछि मात्र उपस्थिति सूची र सदस्यको इतिहासमा देखिन्छ। कृपया प्रतिक्षा गर्नुहोस् वा कर्मचारीलाई भन्नुहोस्।
      </div>
    </div>
    <?php elseif ($done): ?>
    <div style="text-align:center;padding:8px 0;">
      <div class="success-icon"><i class="fas fa-check"></i></div>
      <h2 style="font-size:1.2rem;font-weight:800;color:#166534;margin-bottom:6px;">उपस्थिति दर्ता भयो!</h2>
      <p style="font-size:.88rem;color:#6b7280;margin-bottom:16px;">
        <?= htmlspecialchars($memName ?: 'सदस्य') ?><?php if ($memCard): ?> (<?= htmlspecialchars($memCard) ?>)<?php endif; ?> — <strong><?= htmlspecialchars($prog['title']) ?></strong>
      </p>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px;font-size:.85rem;color:#166534;">
        <i class="fas fa-circle-check" style="margin-right:6px;"></i>Check-in समय: <?= date('H:i A') ?>, <?= date('Y-m-d') ?>
      </div>
    </div>
    <?php elseif ($alreadyDone && $prog): ?>
    <div style="text-align:center;padding:8px 0;">
      <div class="success-icon" style="background:#eff6ff;border-color:#bfdbfe;"><i class="fas fa-bookmark" style="color:#1565c0;"></i></div>
      <h2 style="font-size:1.1rem;font-weight:800;color:#1565c0;margin-bottom:6px;">पहिल्यै Check-in भइसक्यो</h2>
      <p style="font-size:.85rem;color:#6b7280;">तपाईं यो कार्यक्रममा पहिल्यै उपस्थित दर्ता हुनुभएको छ।</p>
    </div>
    <?php elseif ($prog): ?>
      <div class="info-box" style="background:#f0fdf4;border-color:#86efac;color:#166534;">
        <i class="fas fa-circle-info" style="flex-shrink:0;"></i>
        <div style="text-align:left;line-height:1.5;">
          <strong>कार्यक्रम उपस्थिति QR:</strong> सदस्यले <strong>स्थलमा उपस्थित भइसकेपछि</strong> मोबाइलबाट Member Portal खोली scan गर्दा दर्ता हुन्छ — Admin को attendance सूची र सदस्यको उपस्थिति इतिहासमा जान्छ, <strong>जति कार्यक्रममा check-in, त्यति नै गणना बढ्छ</strong>।
          (Pre-registration भन्दा फरक — त्यो अगाडि नाम दर्ता मात्र हो।)
          <br><span style="opacity:.95;font-size:.9em;"><strong>मोबाइल छैन?</strong> तल सदस्यता/फोन भरेर <strong>उपस्थिति अनुरोध</strong> पठाउनुहोस् — Admin ले स्वीकृत गरेपछि मात्र सूचीमा आउँछ।</span>
          <br><span style="opacity:.9;font-size:.9em;">थप कडा जाँच: Staff <strong>कार्ड + CVV</strong> verify।</span>
        </div>
      </div>
      <?php if ($memberId): ?>
      <!-- Logged-in: one-click check-in -->
      <div class="info-box">
        <i class="fas fa-user-check" style="flex-shrink:0;"></i>
        <div>तपाईं <strong><?= htmlspecialchars($memName ?: 'Member') ?></strong> को रूपमा login हुनुभएको छ।</div>
      </div>
      <form method="POST">
        <?= $csrfField ?><input type="hidden" name="action" value="checkin_logged">
        <button type="submit" class="btn-primary"><i class="fas fa-user-check"></i> उपस्थिति दिनुहोस्</button>
      </form>
      <div class="divider"><span>वा</span></div>
      <?php endif; ?>

      <!-- Manual check-in form -->
      <form method="POST" id="manualForm" <?= $memberId ? 'style="display:none;"' : '' ?>>
        <?= $csrfField ?><input type="hidden" name="action" value="checkin_manual">
        <div class="form-group">
          <label><i class="fas fa-id-card" style="margin-right:4px;color:<?= htmlspecialchars($primaryColor) ?>;"></i>सदस्यता नम्बर</label>
          <input type="text" name="member_card" class="form-control" placeholder="जस्तै: A-001, SA-2025-001">
        </div>
        <div class="form-group">
          <label><i class="fas fa-phone" style="margin-right:4px;color:<?= htmlspecialchars($primaryColor) ?>;"></i>फोन नम्बर</label>
          <input type="text" name="phone" class="form-control" placeholder="9XXXXXXXXX">
        </div>
        <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> उपस्थिति अनुरोध पठाउनुहोस्</button>
      </form>

      <?php if ($memberId): ?>
      <button type="button" class="btn-secondary" onclick="document.getElementById('manualForm').style.display=document.getElementById('manualForm').style.display==='none'?'block':'none';">
        <i class="fas fa-keyboard"></i> Manual Check-in
      </button>
      <?php endif; ?>
    <?php endif; ?>

    <div class="footer-link">
      <?php
        $memberAfterLogin = '/member/attend.php?qr_token=' . rawurlencode($token);
        $loginNext = rtrim(SITE_URL, '/') . '/member/login.php?next=' . rawurlencode($memberAfterLogin);
      ?>
      <?php if (!$memberId && $prog && $token): ?>
      <div style="margin-bottom:10px;">
        <a href="<?= htmlspecialchars($loginNext) ?>" class="btn-primary" style="text-decoration:none;display:inline-flex;width:auto;padding:10px 16px;font-size:.9rem;">
          <i class="fas fa-right-to-bracket"></i> Member Portal मा लगिन गरेर Check-in
        </a>
      </div>
      <?php endif; ?>
      <a href="<?= htmlspecialchars(SITE_URL) ?>"><i class="fas fa-home" style="margin-right:4px;"></i><?= htmlspecialchars($siteName) ?></a>
      &nbsp;·&nbsp;
      <a href="<?= htmlspecialchars(SITE_URL) ?>member/"><i class="fas fa-user" style="margin-right:4px;"></i>Member Portal</a>
    </div>
  </div>
</div>
</body>
</html>
