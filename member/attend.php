<?php
/**
 * Member Portal — कार्यक्रम उपस्थिति (Program Attendance)
 * View attendance history + upcoming programs check-in
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/program-tables.php';
requireMemberLogin();
memberSecurityHeaders();

$db  = getDB();
$mem = currentMember();
if (!$mem) { header('Location: login.php?msg=session_expired'); exit; }

$memberId   = (int)$mem['id'];
$memEmail   = trim((string)($mem['email'] ?? ''));
$memPhone   = preg_replace('/[^0-9]/', '', (string)($mem['phone'] ?? ''));

/* KYC for sadasyata number */
$kycRow = null;
try {
    $kycLinkId = (int)($mem['kyc_application_id'] ?? 0);
    if ($kycLinkId > 0) {
        $ks = $db->prepare("SELECT full_name, member_id, mobile FROM kyc_applications WHERE id=? LIMIT 1");
        $ks->execute([$kycLinkId]);
        $kycRow = $ks->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $e) { $kycRow = null; }

$memName    = trim((string)($kycRow['full_name'] ?? $mem['name'] ?? ''));
$memCard    = trim((string)($kycRow['member_id'] ?? $mem['sadasyata_number'] ?? ''));

ensureProgramTables($db);

/* ── Handle self check-in ── */
$checkInMsg = '';
$checkInErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkin') {
    if (!verifyCSRFToken()) {
        $checkInErr = 'सुरक्षा जाँच असफल।';
    } else {
        $progId = (int)($_POST['program_id'] ?? 0);
        if ($progId > 0) {
            try {
                /* Verify program exists and is active */
                $prog = $db->prepare("SELECT id, title, is_active FROM upcoming_programs WHERE id=? LIMIT 1");
                $prog->execute([$progId]);
                $progRow = $prog->fetch(PDO::FETCH_ASSOC);
                if (!$progRow || !$progRow['is_active']) {
                    $checkInErr = 'यो कार्यक्रम उपलब्ध छैन।';
                } else {
                    /* Check duplicate */
                    $dup = $db->prepare("SELECT id FROM member_program_attendance WHERE member_id=? AND program_id=? LIMIT 1");
                    $dup->execute([$memberId, $progId]);
                    if ($dup->fetchColumn()) {
                        $checkInErr = 'तपाईं यो कार्यक्रममा पहिल्यै check-in हुनुभएको छ।';
                    } else {
                        $ins = $db->prepare("INSERT INTO member_program_attendance
                            (member_id, member_card_no, program_id, program_title, source, verified_by_ip)
                            VALUES (?,?,?,?,?,?)");
                        $ins->execute([$memberId, $memCard, $progId, $progRow['title'], 'member_portal', $_SERVER['REMOTE_ADDR'] ?? '']);
                        $checkInMsg = '"' . htmlspecialchars($progRow['title']) . '" मा उपस्थिति दर्ता भयो!';
                    }
                }
            } catch (Throwable $e) {
                $checkInErr = 'Check-in गर्न समस्या भयो।';
                error_log('[attend checkin] ' . $e->getMessage());
            }
        }
    }
}

/* ── Handle pre-registration ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'prereg') {
    if (verifyCSRFToken()) {
        $progId = (int)($_POST['program_id'] ?? 0);
        if ($progId > 0) {
            try {
                $prog = $db->prepare("SELECT id, title, event_date, pre_registration_open FROM upcoming_programs WHERE id=? LIMIT 1");
                $prog->execute([$progId]);
                $progRow = $prog->fetch(PDO::FETCH_ASSOC);
                if ($progRow && $progRow['pre_registration_open']) {
                    $dup = $db->prepare("SELECT id FROM member_program_preregistrations WHERE member_id=? AND program_id=? LIMIT 1");
                    $dup->execute([$memberId, $progId]);
                    if (!$dup->fetchColumn()) {
                        $ins = $db->prepare("INSERT INTO member_program_preregistrations
                            (member_id, member_card_no, member_name, phone, email, program_id, program_title, event_date, source)
                            VALUES (?,?,?,?,?,?,?,?,?)");
                        $ins->execute([$memberId, $memCard, $memName, $memPhone, $memEmail, $progId, $progRow['title'], $progRow['event_date'], 'member_portal']);
                        $checkInMsg = '"' . htmlspecialchars($progRow['title']) . '" मा pre-registration सफल भयो!';
                    } else {
                        $checkInErr = 'तपाईं पहिल्यै register हुनुभएको छ।';
                    }
                } else {
                    $checkInErr = 'Pre-registration खुला छैन।';
                }
            } catch (Throwable $e) { $checkInErr = 'Register गर्न समस्या।'; }
        }
    }
}

/* ── Fetch data ── */
/* My attendance history */
$myAttendance = [];
try {
    $st = $db->prepare("SELECT a.*, p.description, p.event_time, p.location
                        FROM member_program_attendance a
                        LEFT JOIN upcoming_programs p ON p.id=a.program_id
                        WHERE a.member_id=? ORDER BY a.attended_at DESC LIMIT 50");
    $st->execute([$memberId]);
    $myAttendance = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $myAttendance = []; }

/* QR deep-link (admin programs → Member Portal URL) */
$qrToken = trim(preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['qr_token'] ?? ''));
$qrProgramRow = null;
$qrAlreadyAttended = false;
if ($qrToken !== '') {
    try {
        $qst = $db->prepare('SELECT * FROM upcoming_programs WHERE qr_token = ? AND is_active = 1 LIMIT 1');
        $qst->execute([$qrToken]);
        $qrProgramRow = $qst->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $qrProgramRow = null;
    }
}
if ($qrProgramRow) {
    $qpid = (int)$qrProgramRow['id'];
    foreach ($myAttendance as $a) {
        if ((int)($a['program_id'] ?? 0) === $qpid) {
            $qrAlreadyAttended = true;
            break;
        }
    }
}

/* My pre-registrations */
$myPreregs = [];
try {
    $st = $db->prepare("SELECT pr.*, p.is_active, p.location, p.event_time
                        FROM member_program_preregistrations pr
                        LEFT JOIN upcoming_programs p ON p.id=pr.program_id
                        WHERE pr.member_id=? ORDER BY pr.created_at DESC LIMIT 20");
    $st->execute([$memberId]);
    $myPreregs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $myPreregs = []; }

/* Upcoming programs for check-in */
$upcoming = [];
try {
    $attended_ids = array_column($myAttendance, 'program_id');
    $prereg_ids   = array_column($myPreregs, 'program_id');
    $st = $db->query("SELECT * FROM upcoming_programs WHERE is_active=1 ORDER BY COALESCE(event_date,'9999-12-31') ASC, id DESC LIMIT 20");
    $upcoming = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $upcoming = []; }

/* QR code for this member */
$siteUrl   = SITE_URL;
$memberQr  = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($siteUrl . 'verify.php?id=' . urlencode($memCard)) . '&size=140x140&margin=4';
$siteName  = getSetting('site_name', 'सहकारी');
$pageTitle = 'कार्यक्रम उपस्थिति — ' . $siteName;
$csrfField = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';

$extraHead = <<<HTML
<style>
.prog-card { background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin-bottom:12px;transition:box-shadow .2s; }
.prog-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.08); }
.prog-date-badge { background:var(--primary-color,#1a8754);color:#fff;border-radius:8px;padding:6px 10px;text-align:center;min-width:50px;flex-shrink:0; }
.prog-date-badge .day { font-size:1.4rem;font-weight:800;line-height:1; }
.prog-date-badge .mon { font-size:.65rem;text-transform:uppercase;letter-spacing:.05em; }
.att-badge { display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:.75rem;font-weight:700;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
.empty-msg { text-align:center;padding:28px;color:#9ca3af;font-size:.88rem; }
.empty-msg i { display:block;font-size:2.2rem;margin-bottom:8px; }
.tabs-row { display:flex;gap:2px;border-bottom:2px solid #f3f4f6;margin-bottom:18px; }
.tab-btn { padding:9px 16px;font-size:.85rem;font-weight:600;background:none;border:none;cursor:pointer;color:#6b7280;border-bottom:3px solid transparent;margin-bottom:-2px;font-family:inherit;transition:all .2s; }
.tab-btn.active { color:var(--primary-color,#1a8754);border-bottom-color:var(--primary-color,#1a8754); }
.tab-pane { display:none; }
.tab-pane.active { display:block; }
</style>
HTML;
?>
<?php require __DIR__ . '/includes/chrome.php'; ?>

<main class="mp-main">
<div class="mp-container">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;flex-wrap:wrap;gap:10px;">
    <h1 style="font-size:1.25rem;font-weight:700;color:var(--primary-color,#1a8754);margin:0;">
      <i class="fas fa-calendar-check" style="margin-right:8px;"></i>कार्यक्रम उपस्थिति
    </h1>
    <div class="att-badge"><i class="fas fa-check-double"></i> <?= count($myAttendance) ?> कार्यक्रम उपस्थित</div>
  </div>
  <p style="font-size:.78rem;color:#64748b;line-height:1.5;margin:0 0 16px;padding:10px 12px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
    <strong>QR</strong> ले कार्यक्रम स्थलमा उपस्थित भइसकेपछि दर्ता गर्नुहोस् (मोबाइल फुटरको <strong>स्क्यान</strong> वा बाहिरबाट QR खोलेर पनि) — Admin को attendance सूची र तलको <strong>उपस्थिति इतिहास</strong>मा थपिन्छ।
    <strong>Pre-register</strong> भन्दा फरक: pre-reg = अगाडि नाम दर्ता मात्र; <strong>गणना बढाउने</strong> check-in (QR वा आजको मितिमा बटन) हो।
  </p>

  <?php if ($checkInMsg): ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;color:#166534;font-size:.88rem;margin-bottom:14px;display:flex;gap:8px;">
    <i class="fas fa-circle-check" style="flex-shrink:0;margin-top:2px;"></i><?= $checkInMsg ?>
  </div>
  <?php endif; ?>
  <?php if ($checkInErr): ?>
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;color:#dc2626;font-size:.88rem;margin-bottom:14px;display:flex;gap:8px;">
    <i class="fas fa-circle-xmark" style="flex-shrink:0;margin-top:2px;"></i><?= htmlspecialchars($checkInErr) ?>
  </div>
  <?php endif; ?>

  <?php if ($qrProgramRow): ?>
  <div style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1.5px solid #6ee7b7;border-radius:14px;padding:16px;margin-bottom:16px;box-shadow:0 4px 14px rgba(16,185,129,.12);">
    <div style="display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;">
      <div style="width:48px;height:48px;border-radius:12px;background:var(--primary-color,#1a8754);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;"><i class="fas fa-qrcode"></i></div>
      <div style="flex:1;min-width:200px;">
        <div style="font-size:.72rem;font-weight:800;color:#047857;text-transform:uppercase;letter-spacing:.04em;">स्थल उपस्थिति — कार्यक्रम QR</div>
        <div style="font-size:1rem;font-weight:800;color:#064e3b;margin-top:4px;"><?= htmlspecialchars($qrProgramRow['title']) ?></div>
        <?php if (!empty($qrProgramRow['event_date']) || !empty($qrProgramRow['event_time']) || !empty($qrProgramRow['location'])): ?>
        <div style="font-size:.78rem;color:#047857;margin-top:6px;">
          <?php if (!empty($qrProgramRow['event_date'])): ?><i class="fas fa-calendar me-1"></i><?= htmlspecialchars($qrProgramRow['event_date']) ?><?php endif; ?>
          <?php if (!empty($qrProgramRow['event_time'])): ?> · <i class="fas fa-clock me-1"></i><?= htmlspecialchars($qrProgramRow['event_time']) ?><?php endif; ?>
          <?php if (!empty($qrProgramRow['location'])): ?><br><i class="fas fa-location-dot me-1"></i><?= htmlspecialchars($qrProgramRow['location']) ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <p style="font-size:.75rem;color:#065f46;margin:10px 0 0;line-height:1.45;">कार्यक्रम स्थलमा हुनुहुन्छ भने मात्र थिच्नुहोस्। <strong><?= htmlspecialchars($memName) ?></strong> को विवरण (KYC/कार्ड) Admin attendance र तपाईंको <strong>उपस्थिति इतिहास</strong>मा जान्छ — उपस्थित कार्यक्रमको संख्या <strong>१ ले बढ्छ</strong>।</p>
      </div>
      <div style="flex-shrink:0;width:100%;max-width:220px;">
        <?php if ($qrAlreadyAttended): ?>
        <div class="att-badge" style="width:100%;justify-content:center;"><i class="fas fa-circle-check"></i> उपस्थित भइसकेको</div>
        <?php else: ?>
        <form method="POST" style="margin:0;">
          <?= $csrfField ?><input type="hidden" name="action" value="checkin"><input type="hidden" name="program_id" value="<?= (int)$qrProgramRow['id'] ?>">
          <button type="submit" style="width:100%;padding:12px 16px;background:var(--primary-color,#1a8754);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:.9rem;font-weight:800;cursor:pointer;">
            <i class="fas fa-user-check me-2"></i>यही कार्यक्रममा Check-in
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php elseif ($qrToken !== ''): ?>
  <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:12px 14px;color:#92400e;font-size:.85rem;margin-bottom:14px;">
    <i class="fas fa-triangle-exclamation me-2"></i>यो QR मान्य छैन वा कार्यक्रम निष्क्रिय छ।
  </div>
  <?php endif; ?>

  <!-- Member QR + stats bar -->
  <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin-bottom:18px;display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
    <div style="text-align:center;">
      <img src="<?= htmlspecialchars($memberQr) ?>" alt="QR" width="70" height="70" style="border-radius:6px;border:1px solid #e5e7eb;">
      <div style="font-size:.65rem;color:#9ca3af;margin-top:3px;">मेरो QR</div>
    </div>
    <div style="flex:1;min-width:0;">
      <div style="font-size:.88rem;font-weight:700;color:#1f2937;"><?= htmlspecialchars($memName) ?></div>
      <?php if ($memCard): ?>
      <div style="font-size:.78rem;color:#6b7280;font-family:monospace;"><?= htmlspecialchars($memCard) ?></div>
      <?php endif; ?>
      <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap;">
        <div style="text-align:center;">
          <div style="font-size:1.3rem;font-weight:800;color:var(--primary-color,#1a8754);"><?= count($myAttendance) ?></div>
          <div style="font-size:.7rem;color:#9ca3af;">उपस्थित कार्यक्रम</div>
        </div>
        <div style="text-align:center;">
          <div style="font-size:1.3rem;font-weight:800;color:#1565c0;"><?= count($myPreregs) ?></div>
          <div style="font-size:.7rem;color:#9ca3af;">Pre-reg</div>
        </div>
        <div style="text-align:center;">
          <div style="font-size:1.3rem;font-weight:800;color:#d97706;"><?= count($upcoming) ?></div>
          <div style="font-size:.7rem;color:#9ca3af;">आगामी</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs-row">
    <button class="tab-btn active" onclick="showAtTab('upcoming',this)"><i class="fas fa-calendar-star" style="margin-right:5px;"></i>आगामी कार्यक्रम</button>
    <button class="tab-btn" onclick="showAtTab('history',this)"><i class="fas fa-history" style="margin-right:5px;"></i>उपस्थिति इतिहास</button>
    <?php if (!empty($myPreregs)): ?>
    <button class="tab-btn" onclick="showAtTab('prereg',this)"><i class="fas fa-clipboard-list" style="margin-right:5px;"></i>Pre-reg</button>
    <?php endif; ?>
  </div>

  <!-- Tab: Upcoming -->
  <div class="tab-pane active" id="tab-upcoming">
    <?php if (empty($upcoming)): ?>
    <div class="empty-msg"><i class="fas fa-calendar-xmark"></i>अहिले कुनै आगामी कार्यक्रम छैन।</div>
    <?php else: ?>
    <?php
    $attended_ids = array_map('intval', array_column($myAttendance, 'program_id'));
    $prereg_ids   = array_map('intval', array_column($myPreregs, 'program_id'));
    foreach ($upcoming as $prog):
        $progId    = (int)$prog['id'];
        $isAttended = in_array($progId, $attended_ids);
        $isPrereg   = in_array($progId, $prereg_ids);
        $evDate     = $prog['event_date'] ? date('Y-m-d', strtotime($prog['event_date'])) : '';
        $evDay      = $prog['event_date'] ? date('d', strtotime($prog['event_date'])) : '—';
        $evMon      = $prog['event_date'] ? date('M Y', strtotime($prog['event_date'])) : '';
        $isToday    = $evDate === date('Y-m-d');
        $isPast     = $evDate && $evDate < date('Y-m-d');
    ?>
    <div class="prog-card">
      <div style="display:flex;gap:12px;align-items:flex-start;">
        <div class="prog-date-badge" style="<?= $isToday ? 'background:#dc2626;' : ($isPast ? 'background:#9ca3af;' : '') ?>">
          <div class="day"><?= $evDay ?></div>
          <div class="mon"><?= $evMon ?></div>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-size:.95rem;font-weight:700;color:#1f2937;margin-bottom:3px;">
            <?= htmlspecialchars($prog['title']) ?>
            <?php if ($isToday): ?><span style="background:#dc2626;color:#fff;font-size:.65rem;padding:2px 7px;border-radius:10px;margin-left:6px;">आज</span><?php endif; ?>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;font-size:.78rem;color:#6b7280;margin-bottom:8px;">
            <?php if ($prog['event_time']): ?><span><i class="fas fa-clock" style="margin-right:3px;"></i><?= htmlspecialchars($prog['event_time']) ?></span><?php endif; ?>
            <?php if ($prog['location']): ?><span><i class="fas fa-location-dot" style="margin-right:3px;"></i><?= htmlspecialchars($prog['location']) ?></span><?php endif; ?>
          </div>
          <?php if ($prog['description']): ?>
          <div style="font-size:.8rem;color:#6b7280;margin-bottom:8px;"><?= htmlspecialchars(mb_substr($prog['description'],0,120)) ?></div>
          <?php endif; ?>

          <?php if ($isAttended): ?>
          <div class="att-badge"><i class="fas fa-circle-check"></i> उपस्थित भइसकेको</div>
          <?php elseif ($isPast): ?>
          <div style="font-size:.78rem;color:#9ca3af;"><i class="fas fa-calendar-xmark" style="margin-right:4px;"></i>कार्यक्रम सकियो</div>
          <?php else: ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if ($isToday): ?>
            <form method="POST" style="display:inline;">
              <?= $csrfField ?><input type="hidden" name="action" value="checkin"><input type="hidden" name="program_id" value="<?= $progId ?>">
              <button type="submit" style="padding:7px 16px;background:var(--primary-color,#1a8754);color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;">
                <i class="fas fa-user-check" style="margin-right:4px;"></i>Check-in गर्नुहोस्
              </button>
            </form>
            <?php elseif ($prog['pre_registration_open'] && !$isPrereg): ?>
            <form method="POST" style="display:inline;">
              <?= $csrfField ?><input type="hidden" name="action" value="prereg"><input type="hidden" name="program_id" value="<?= $progId ?>">
              <button type="submit" style="padding:7px 16px;background:#1565c0;color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;">
                <i class="fas fa-clipboard-check" style="margin-right:4px;"></i>Pre-register
              </button>
            </form>
            <?php elseif ($isPrereg): ?>
            <span style="font-size:.78rem;color:#1565c0;font-weight:600;"><i class="fas fa-bookmark" style="margin-right:4px;"></i>Pre-registered</span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tab: History -->
  <div class="tab-pane" id="tab-history">
    <?php if (empty($myAttendance)): ?>
    <div class="empty-msg"><i class="fas fa-calendar-days"></i>अझसम्म कुनै कार्यक्रममा उपस्थित हुनुभएको छैन।</div>
    <?php else: ?>
    <?php foreach ($myAttendance as $att): ?>
    <div class="prog-card" style="display:flex;gap:12px;align-items:center;">
      <div style="width:40px;height:40px;border-radius:50%;background:var(--primary-color,#1a8754);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;flex-shrink:0;">
        <i class="fas fa-check"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:.9rem;font-weight:700;color:#1f2937;"><?= htmlspecialchars($att['program_title']) ?></div>
        <div style="font-size:.75rem;color:#6b7280;margin-top:2px;">
          <i class="fas fa-calendar" style="margin-right:4px;"></i><?= date('Y-m-d', strtotime($att['attended_at'])) ?>
          <?php if ($att['location']): ?><span style="margin-left:8px;"><i class="fas fa-location-dot" style="margin-right:3px;"></i><?= htmlspecialchars($att['location']) ?></span><?php endif; ?>
        </div>
      </div>
      <div class="att-badge"><i class="fas fa-circle-check"></i> उपस्थित</div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tab: Pre-registrations -->
  <?php if (!empty($myPreregs)): ?>
  <div class="tab-pane" id="tab-prereg">
    <?php foreach ($myPreregs as $pr): ?>
    <div class="prog-card" style="display:flex;gap:12px;align-items:center;">
      <div style="width:40px;height:40px;border-radius:50%;background:#1565c0;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.9rem;flex-shrink:0;">
        <i class="fas fa-bookmark"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:.9rem;font-weight:700;color:#1f2937;"><?= htmlspecialchars($pr['program_title']) ?></div>
        <div style="font-size:.75rem;color:#6b7280;margin-top:2px;">
          <?php if ($pr['event_date']): ?><i class="fas fa-calendar" style="margin-right:4px;"></i><?= $pr['event_date'] ?><?php endif; ?>
          <?php if ($pr['location']): ?><span style="margin-left:8px;"><i class="fas fa-location-dot" style="margin-right:3px;"></i><?= htmlspecialchars($pr['location']) ?></span><?php endif; ?>
        </div>
      </div>
      <span style="font-size:.75rem;font-weight:700;color:#1565c0;background:#eff6ff;padding:4px 10px;border-radius:20px;">Pre-reg</span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</main>
<script>
function showAtTab(tab, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    var el = document.getElementById('tab-'+tab);
    if (el) el.classList.add('active');
    if (btn) btn.classList.add('active');
}
</script>
<?php require __DIR__ . '/includes/chrome-foot.php'; ?>
