<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/card-verify-helpers.php';
require_once __DIR__ . '/includes/program-tables.php';

$pageTitle = isEnglish() ? 'Program Attendance Verify' : 'कार्यक्रम उपस्थिति प्रमाणीकरण';
$attendanceStaffMode = isAdminLoggedIn();
if (!$attendanceStaffMode) {
    redirect(ADMIN_URL . 'index.php');
}
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();
ensureProgramTables($pdo);
$saved = false;
$already = false;
$error = '';
$memberInfo = null;
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$programId = (int)($_POST['program_id'] ?? ($_GET['program_id'] ?? 0));
$code = mb_substr(trim((string)($_POST['code'] ?? '')), 0, 80, 'UTF-8');
$cvv  = mb_substr(trim((string)($_POST['cvv'] ?? '')), 0, 32, 'UTF-8');

$programs = [];
try {
    $programs = $pdo->query("SELECT id, title, event_date, event_time, location, qr_token
                             FROM upcoming_programs
                             WHERE is_active=1
                             ORDER BY COALESCE(event_date, '9999-12-31') ASC, id DESC")->fetchAll() ?: [];
} catch (Throwable $e) { $programs = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = isEnglish() ? 'Security validation failed.' : 'सुरक्षा जाँच असफल भयो।';
    } elseif ($programId <= 0 || $code === '' || $cvv === '') {
        $error = isEnglish() ? 'Please select program and enter code/CVV.' : 'कृपया कार्यक्रम छान्नुहोस् र code/CVV राख्नुहोस्।';
    } else {
        $result = verifyCardCredentials($pdo, $code, $cvv, $ip);
        if (empty($result['ok'])) {
            $error = (string)($result['error'] ?? (isEnglish() ? 'Verification failed.' : 'Verification असफल भयो।'));
        } else {
            $memberInfo = $result['member'] ?? null;
            try {
                $pst = $pdo->prepare("SELECT id, title FROM upcoming_programs WHERE id=? AND is_active=1 LIMIT 1");
                $pst->execute([$programId]);
                $pg = $pst->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$pg || !$memberInfo) {
                    $error = isEnglish() ? 'Program not found.' : 'कार्यक्रम फेला परेन।';
                } else {
                    $mid = (int)($memberInfo['id'] ?? 0);
                    $cardNo = trim((string)($memberInfo['member_id'] ?? ''));
                    $chk = $pdo->prepare("SELECT id FROM member_program_attendance WHERE member_id=? AND program_id=? LIMIT 1");
                    $chk->execute([$mid, $programId]);
                    if ($chk->fetchColumn()) {
                        $already = true;
                    } else {
                        $ins = $pdo->prepare("INSERT INTO member_program_attendance
                            (member_id, member_card_no, program_id, program_title, is_priority, attendance_note, verified_by_ip, source)
                            VALUES (?,?,?,?,?,?,?,?)");
                        $ins->execute([$mid, $cardNo, $programId, mb_substr((string)$pg['title'], 0, 180), 0, 'Program attendance verify page', $ip, 'program_verify_page']);
                        $saved = true;
                    }
                }
            } catch (Throwable $e) {
                $error = isEnglish() ? 'Could not save attendance.' : 'Attendance सुरक्षित गर्न सकिएन।';
            }
        }
    }
}
?>
<style>
/* ── Program Attendance Verify — Modern UI v11 ── */
.pav-shell{
  background:
    radial-gradient(ellipse 85% 55% at 12% -8%, rgba(26,135,84,.10) 0%, transparent 52%),
    radial-gradient(ellipse 72% 48% at 100% 5%, rgba(59,130,246,.09) 0%, transparent 48%),
    linear-gradient(165deg,#f0fdf4 0%,#ecfdf5 42%,#f0f9ff 100%);
  padding:22px 0 34px;
  min-height: calc(100dvh - 64px);
  display:flex;
  align-items:center;
}
.pav-shell.section-padding{padding-top:22px !important;padding-bottom:34px !important;}
.pav-shell .container{max-width:560px;}

/* Nav pills */
.pav-nav{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;}
.pav-nav-pill{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;border:none;cursor:pointer;transition:all .15s;background:#fff;color:#374151;border:1.5px solid #e5e7eb;box-shadow:0 1px 3px rgba(0,0,0,.07);}
.pav-nav-pill:hover{background:#f8fafc;box-shadow:0 4px 12px rgba(0,0,0,.1);}
.pav-nav-pill.member{background:#dcfce7;color:#166534;border-color:#86efac;}
.pav-nav-pill.program{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd;}
.pav-nav-pill.att{background:linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));color:var(--text-on-primary,#fff);border-color:transparent;box-shadow:0 4px 14px rgba(13,92,46,.3);}

/* Main card */
.pav-card{border-radius:20px;box-shadow:0 4px 6px rgba(0,0,0,.04),0 24px 50px rgba(13,92,46,.12);overflow:hidden;background:#fff;border:1px solid rgba(226,232,240,.9);}
.pav-card-header{background:linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));padding:20px 18px 18px;text-align:center;color:var(--text-on-primary,#fff);}
.pav-card-icon{width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.18);display:grid;place-items:center;font-size:24px;margin:0 auto 12px;box-shadow:0 4px 16px rgba(0,0,0,.15);}
.pav-card-title{font-size:1.15rem;font-weight:800;letter-spacing:-.02em;margin:0 0 8px;}
.pav-card-sub{font-size:.8rem;opacity:.88;margin:0;line-height:1.55;}
.pav-card-body{padding:18px 16px 20px;}

/* Form fields */
.pav-field{margin-bottom:14px;}
.pav-field label{display:block;font-size:11.5px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.07em;margin-bottom:7px;}
.pav-field-input{width:100%;min-height:44px;padding:11px 14px;border:1.5px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;transition:border-color .15s,box-shadow .15s;background:#fff;color:#111827;}
.pav-field-input:focus{outline:none;border-color:var(--primary-color,#1a8754);box-shadow:0 0 0 3px rgba(26,135,84,.14);}
.pav-field-input[name="cvv"]{font-family:'Courier New',monospace;letter-spacing:.35em;text-align:center;font-weight:700;font-size:16px;}
.pav-field-input[name="code"]{font-family:'Courier New',monospace;letter-spacing:.1em;text-transform:uppercase;font-weight:700;}

/* Compact selected-program QR preview */
.pav-program-qr{
  display:none;
  margin-top:10px;
  background:#f8fafc;
  border:1px solid #e2e8f0;
  border-radius:10px;
  padding:10px 12px;
}
.pav-program-qr.is-visible{display:flex;align-items:center;gap:12px;}
.pav-program-qr-img{
  width:72px;
  height:72px;
  border-radius:8px;
  background:#fff;
  border:1px solid #dbe3ee;
  object-fit:contain;
  flex-shrink:0;
}
.pav-program-qr-meta{min-width:0;}
.pav-program-qr-title{font-size:.79rem;font-weight:700;color:#334155;line-height:1.35;}
.pav-program-qr-link{
  display:inline-block;
  margin-top:4px;
  font-size:.73rem;
  color:#166534;
  font-weight:700;
  text-decoration:none;
}
.pav-program-qr-empty{
  font-size:.74rem;
  color:#64748b;
  margin-top:6px;
}

/* Submit button */
.pav-btn{width:100%;min-height:44px;padding:11px 14px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));color:var(--text-on-primary,#fff);font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:9px;box-shadow:0 4px 16px rgba(26,135,84,.32);transition:all .15s;letter-spacing:.02em;}
.pav-btn:hover{filter:brightness(1.07);transform:translateY(-1px);box-shadow:0 8px 22px rgba(26,135,84,.38);}
.pav-btn:active{transform:translateY(0);}

/* Result card */
.pav-result{border-radius:16px;padding:0;margin-top:20px;animation:pavPop .4s cubic-bezier(.22,.61,.36,1);}
@keyframes pavPop{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
.pav-result-ok{background:linear-gradient(135deg,#ecfdf5,#f0fdf4);border:1.5px solid #6ee7b7;padding:20px;}
.pav-result-warn{background:#fffbeb;border:1.5px solid #fcd34d;padding:20px;}
.pav-result-fail{background:#fef2f2;border:1.5px solid #fca5a5;padding:20px;}
.pav-result-head{display:flex;align-items:center;gap:10px;font-weight:800;font-size:15px;margin-bottom:14px;}
.pav-result-head.ok{color:#065f46;} .pav-result-head.warn{color:#92400e;} .pav-result-head.fail{color:#991b1b;}
.pav-result-head i{font-size:20px;}
.pav-member-card{display:flex;align-items:center;gap:14px;background:#fff;border-radius:12px;padding:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);}
.pav-member-photo{width:56px;height:56px;border-radius:50%;object-fit:cover;border:3px solid #d1fae5;flex-shrink:0;background:#e5e7eb;}
.pav-member-photo-fallback{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));display:grid;place-items:center;font-size:22px;color:var(--text-on-primary,#fff);flex-shrink:0;}
.pav-member-info{flex:1;min-width:0;}
.pav-member-name{font-size:1.05rem;font-weight:800;color:#111827;margin:0 0 2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.pav-member-id{font-size:.8rem;color:#6b7280;font-weight:600;font-family:'Courier New',monospace;}
.pav-result-badge{display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px;margin-top:6px;}
.pav-result-badge.saved{background:#dcfce7;color:#166534;}
.pav-result-badge.already{background:#fef3c7;color:#92400e;}

/* Alert */
.pav-alert{border-radius:10px;padding:11px 14px;font-size:13px;font-weight:600;display:flex;align-items:flex-start;gap:10px;margin-bottom:14px;}
.pav-alert.error{background:#fef2f2;border:1.5px solid #fca5a5;color:#991b1b;}
.pav-alert i{font-size:14px;margin-top:2px;}

@media(max-width:640px){
  .pav-shell{align-items:flex-start;min-height:0;}
  .pav-card-header{padding:18px 16px 16px;}
  .pav-card-body{padding:20px 16px;}
  .pav-card-title{font-size:1.05rem;}
}
</style>
<section class="section-padding pav-shell">
    <div class="container">

        <!-- Navigation pills -->
        <div class="pav-nav">
            <a href="<?php echo SITE_URL; ?>verify.php" class="pav-nav-pill member">
                <i class="fas fa-id-card"></i> Member Verify
            </a>
            <a href="<?php echo SITE_URL; ?>cooperative-programs.php" class="pav-nav-pill program">
                <i class="fas fa-user-plus"></i> Program Registration
            </a>
            <span class="pav-nav-pill att">
                <i class="fas fa-user-check"></i> Attendance Verify
            </span>
        </div>

        <div class="pav-card">
            <!-- Header -->
            <div class="pav-card-header">
                <div class="pav-card-icon"><i class="fas fa-user-check"></i></div>
                <h1 class="pav-card-title"><?php echo isEnglish() ? 'Program Attendance' : 'कार्यक्रम उपस्थिति प्रमाणीकरण'; ?></h1>
                <p class="pav-card-sub"><?php echo isEnglish() ? 'Select program and verify with card number + CVV to record attendance.' : 'कार्यक्रम छानी Card Number र CVV राखेर उपस्थिति प्रमाणित गर्नुहोस्।'; ?></p>
            </div>

            <div class="pav-card-body">

                <?php if ($error !== ''): ?>
                    <div class="pav-alert error"><i class="fas fa-circle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="pav-field">
                        <label><i class="fas fa-calendar-check me-1"></i><?php echo isEnglish() ? 'Select Program' : 'कार्यक्रम छान्नुहोस्'; ?></label>
                        <select name="program_id" class="pav-field-input" id="pavProgramSelect" required>
                            <option value=""><?php echo isEnglish() ? '— Choose a program —' : '— कार्यक्रम छान्नुहोस् —'; ?></option>
                            <?php foreach ($programs as $pg): ?>
                                <?php
                                    $qrToken = trim((string)($pg['qr_token'] ?? ''));
                                    $memberAttendUrl = $qrToken !== '' ? (rtrim(SITE_URL, '/') . '/member/attend.php?qr_token=' . rawurlencode($qrToken)) : '';
                                ?>
                                <option value="<?php echo (int)$pg['id']; ?>" <?php echo $programId === (int)$pg['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pg['title']); ?>
                                    <?php if (!empty($pg['event_date'])): ?> · <?php echo htmlspecialchars($pg['event_date']); ?><?php endif; ?>
                                    <?php if (!empty($pg['location'])): ?> · <?php echo htmlspecialchars($pg['location']); ?><?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="pavProgramQr" class="pav-program-qr" aria-live="polite"></div>
                        <div id="pavProgramQrEmpty" class="pav-program-qr-empty">
                            <?php echo isEnglish() ? 'Selected program QR दिखाउन, त्यो कार्यक्रममा QR token generate भएको हुनुपर्छ।' : 'कार्यक्रम select गरेपछि QR देखाउन, त्यो कार्यक्रममा QR token generate भएको हुनुपर्छ।'; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-7">
                            <div class="pav-field" style="margin-bottom:0;">
                                <label><i class="fas fa-id-card me-1"></i>Verification Code / Card No.</label>
                                <input type="text" name="code" class="pav-field-input"
                                       value="<?php echo htmlspecialchars($code); ?>"
                                       placeholder="AKS-XXXX-XXXX"
                                       autocomplete="off" required>
                            </div>
                        </div>
                        <div class="col-sm-5">
                            <div class="pav-field" style="margin-bottom:0;">
                                <label><i class="fas fa-lock me-1"></i>CVV (4 digit)</label>
                                <input type="text" name="cvv" class="pav-field-input"
                                       maxlength="4" inputmode="numeric" pattern="[0-9]{4}"
                                       value="<?php echo htmlspecialchars($cvv); ?>"
                                       placeholder="••••" required>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="pav-btn">
                            <i class="fas fa-circle-check"></i>
                            <?php echo isEnglish() ? 'Verify & Record Attendance' : 'Verify गरेर Attendance सुरक्षित गर्नुहोस्'; ?>
                        </button>
                    </div>
                </form>

                <!-- Result card -->
                <?php if ($memberInfo && ($saved || $already)): ?>
                <div class="pav-result <?php echo $saved ? 'pav-result-ok' : 'pav-result-warn'; ?>">
                    <div class="pav-result-head <?php echo $saved ? 'ok' : 'warn'; ?>">
                        <i class="fas <?php echo $saved ? 'fa-circle-check' : 'fa-circle-info'; ?>"></i>
                        <?php if ($saved): ?>
                            <?php echo isEnglish() ? 'Attendance recorded successfully!' : 'उपस्थिति सफलतापूर्वक दर्ता भयो!'; ?>
                        <?php else: ?>
                            <?php echo isEnglish() ? 'Already marked for this program.' : 'यो कार्यक्रममा attendance पहिल्यै दर्ता भइसकेको छ।'; ?>
                        <?php endif; ?>
                    </div>
                    <div class="pav-member-card">
                        <?php
                            $photoPath = (string)($memberInfo['photo_path'] ?? '');
                            $photoSrc = '';
                            if ($photoPath) {
                                $photoSrc = (strpos($photoPath, 'http') === 0)
                                    ? $photoPath
                                    : (SITE_URL . ltrim($photoPath, '/'));
                            }
                        ?>
                        <?php if ($photoSrc): ?>
                            <img src="<?php echo htmlspecialchars($photoSrc); ?>" alt="" class="pav-member-photo"
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='grid'">
                            <div class="pav-member-photo-fallback" style="display:none;"><i class="fas fa-user"></i></div>
                        <?php else: ?>
                            <div class="pav-member-photo-fallback"><i class="fas fa-user"></i></div>
                        <?php endif; ?>
                        <div class="pav-member-info">
                            <div class="pav-member-name"><?php echo htmlspecialchars((string)($memberInfo['full_name'] ?? '—')); ?></div>
                            <div class="pav-member-id"><?php echo htmlspecialchars((string)($memberInfo['member_id'] ?? '')); ?></div>
                            <span class="pav-result-badge <?php echo $saved ? 'saved' : 'already'; ?>">
                                <i class="fas <?php echo $saved ? 'fa-check' : 'fa-rotate-left'; ?>"></i>
                                <?php echo $saved ? (isEnglish() ? 'Attendance Saved' : 'उपस्थिति दर्ता') : (isEnglish() ? 'Already Recorded' : 'पहिल्यै दर्ता'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</section>
<script>
(function(){
    var programQrMap = <?php
        $qrMap = [];
        foreach ($programs as $pg) {
            $id = (int)($pg['id'] ?? 0);
            if ($id <= 0) continue;
            $token = trim((string)($pg['qr_token'] ?? ''));
            if ($token === '') continue;
            $qrMap[$id] = rtrim(SITE_URL, '/') . '/member/attend.php?qr_token=' . rawurlencode($token);
        }
        echo json_encode($qrMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?> || {};
    var programSel = document.getElementById('pavProgramSelect');
    var programQrBox = document.getElementById('pavProgramQr');
    var programQrEmpty = document.getElementById('pavProgramQrEmpty');
    function updateProgramQr() {
        if (!programSel || !programQrBox) return;
        var pid = (programSel.value || '').trim();
        var url = (pid && programQrMap[pid]) ? programQrMap[pid] : '';
        if (!url) {
            programQrBox.classList.remove('is-visible');
            programQrBox.innerHTML = '';
            if (programQrEmpty) programQrEmpty.style.display = '';
            return;
        }
        var qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=144x144&margin=4&data=' + encodeURIComponent(url);
        programQrBox.innerHTML =
            '<img class="pav-program-qr-img" src="' + qrSrc + '" alt="Program QR">' +
            '<div class="pav-program-qr-meta">' +
            '<div class="pav-program-qr-title"><?php echo isEnglish() ? 'Selected program QR' : 'छानिएको कार्यक्रमको QR'; ?></div>' +
            '<a class="pav-program-qr-link" target="_blank" rel="noopener" href="' + url + '"><i class="fas fa-up-right-from-square me-1"></i><?php echo isEnglish() ? 'Open attendance link' : 'Attendance link खोल्नुहोस्'; ?></a>' +
            '</div>';
        programQrBox.classList.add('is-visible');
        if (programQrEmpty) programQrEmpty.style.display = 'none';
    }
    if (programSel) {
        programSel.addEventListener('change', updateProgramQr);
        updateProgramQr();
    }

    var code = document.querySelector('.pav-card-body input[name="code"]');
    var cvv  = document.querySelector('.pav-card-body input[name="cvv"]');
    if (code) code.addEventListener('input', function(e){
        e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
    });
    if (cvv) cvv.addEventListener('input', function(e){
        e.target.value = e.target.value.replace(/\D/g,'').slice(0,4);
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
