<?php
require_once 'includes/config.php';
require_once 'includes/program-tables.php';
$pageTitle = isEnglish() ? 'Cooperative Programs' : 'सहकारी कार्यक्रम';
require_once 'includes/header.php';

$programs = [];
$preregSaved = false;
$preregAlready = false;
$preregError = '';
$preregProgramId = (int)($_POST['program_id'] ?? 0);
$preregMemberInput = trim((string)($_POST['member_id_input'] ?? ''));
$preregNoteInput = trim((string)($_POST['prereg_note'] ?? ''));
try {
    $db = getDB();
    ensureProgramTables($db);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'program_preregister')) {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $preregError = isEnglish() ? 'Security validation failed.' : 'सुरक्षा जाँच असफल भयो।';
        } else {
            $memberIdInput = trim((string)($_POST['member_id_input'] ?? ''));
            $note = trim((string)($_POST['prereg_note'] ?? ''));
            if ($preregProgramId <= 0 || $memberIdInput === '') {
                $preregError = isEnglish() ? 'Please fill program and member ID.' : 'कृपया कार्यक्रम र सदस्यता नं. दुवै भर्नुहोस्।';
            } else {
                $pst = $db->prepare("SELECT id, title, pre_registration_open, is_active FROM upcoming_programs WHERE id=? LIMIT 1");
                $pst->execute([$preregProgramId]);
                $pg = $pst->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$pg || (int)$pg['is_active'] !== 1 || (int)$pg['pre_registration_open'] !== 1) {
                    $preregError = isEnglish() ? 'Pre-registration is closed for this program.' : 'यो कार्यक्रमको pre-registration अहिले खुला छैन।';
                } else {
                    $mst = $db->prepare("SELECT m.id, m.name, m.phone, m.sadasyata_number, m.member_card_no, m.kyc_application_id, m.approval_status, m.is_active
                                          FROM members m
                                          WHERE m.sadasyata_number = ? OR m.member_card_no = ? OR m.id = ?
                                          LIMIT 1");
                    $mst->execute([$memberIdInput, $memberIdInput, (int)$memberIdInput]);
                    $member = $mst->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (!$member || (string)($member['approval_status'] ?? '') !== 'approved' || (int)($member['is_active'] ?? 0) !== 1) {
                        $preregError = isEnglish() ? 'Not member. Please become a member first.' : 'Not member. कृपया पहिला सदस्य बन्नुहोस्।';
                    } else {
                        $kycOk = false;
                        if (!empty($member['kyc_application_id'])) {
                            $kst = $db->prepare("SELECT id FROM kyc_applications WHERE id=? LIMIT 1");
                            $kst->execute([(int)$member['kyc_application_id']]);
                            $kycOk = (bool)$kst->fetchColumn();
                        } else {
                            $kst = $db->prepare("SELECT id FROM kyc_applications WHERE member_id=? OR mobile=? LIMIT 1");
                            $kst->execute([(string)($member['sadasyata_number'] ?? ''), preg_replace('/[^0-9]/', '', (string)($member['phone'] ?? ($member['phone'] ?? '') ?? ''))]);
                            $kycOk = (bool)$kst->fetchColumn();
                        }
                        if (!$kycOk) {
                            $preregError = isEnglish() ? 'Not member. Please become a member first.' : 'Not member. कृपया पहिला सदस्य बन्नुहोस्।';
                        } else {
                            $chk = $db->prepare("SELECT id FROM member_program_preregistrations WHERE member_id=? AND program_id=? LIMIT 1");
                            $chk->execute([(int)$member['id'], $preregProgramId]);
                            if ($chk->fetchColumn()) {
                                $preregAlready = true;
                            } else {
                                $ins = $db->prepare("INSERT INTO member_program_preregistrations
                                    (member_id, member_card_no, member_name, phone, program_id, program_title, note, source)
                                    VALUES (?,?,?,?,?,?,?,?)");
                                $ins->execute([
                                    (int)$member['id'],
                                    (string)($member['sadasyata_number'] ?: ($member['member_card_no'] ?? '')),
                                    mb_substr((string)($member['name'] ?? ''), 0, 150),
                                    mb_substr((string)($member['phone'] ?: (($member['phone'] ?? '') ?? '')), 0, 30),
                                    $preregProgramId,
                                    mb_substr((string)$pg['title'], 0, 180),
                                    mb_substr($note, 0, 500),
                                    'public_program_page'
                                ]);
                                $preregSaved = true;
                            }
                        }
                    }
                }
            }
        }
    }

    $programs = $db->query("SELECT id, title, description, event_date, event_time, location, pre_registration_open
                            FROM upcoming_programs
                            WHERE is_active=1
                            ORDER BY COALESCE(event_date, '9999-12-31') ASC, id DESC
                            LIMIT 120")->fetchAll();
} catch (Throwable $e) {
    // POST exception आए पनि page खाली नदेखियोस्; user लाई स्पष्ट error देखाऔं।
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'program_preregister') && $preregError === '' && !$preregSaved && !$preregAlready) {
        $preregError = isEnglish() ? 'Pre-registration could not be completed. Please try again.' : 'Pre-registration पूरा गर्न सकिएन। कृपया फेरि प्रयास गर्नुहोस्।';
    }
    try {
        $db = getDB();
        $programs = $db->query("SELECT id, title, description, event_date, event_time, location, pre_registration_open
                                FROM upcoming_programs
                                WHERE is_active=1
                                ORDER BY COALESCE(event_date, '9999-12-31') ASC, id DESC
                                LIMIT 120")->fetchAll();
    } catch (Throwable $e2) {
        $programs = [];
    }
}
?>
<style>
/* ══════════════════════════════════════════════
   Cooperative Programs — v2 Modern Design
══════════════════════════════════════════════ */
.cp-hero {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 60%, #2a8a4e 100%);
    padding: 2.5rem 0 2rem; color: #fff;
}
.cp-hero-inner { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
.cp-hero-icon {
    width: 72px; height: 72px; border-radius: 18px;
    background: rgba(255,255,255,.15); backdrop-filter: blur(10px);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.2rem; color: #fff; flex-shrink: 0;
    border: 1px solid rgba(255,255,255,.2);
}
.cp-hero-text { flex: 1; min-width: 180px; }
.cp-hero-text h2 { font-size: 1.65rem; font-weight: 800; color: #fff; margin-bottom: .3rem; }
.cp-hero-text p  { color: rgba(255,255,255,.72); margin: 0; font-size: .93rem; }
.cp-hero-stats { display: flex; gap: .75rem; flex-wrap: wrap; }
.cp-stat-box {
    background: rgba(255,255,255,.13); border: 1px solid rgba(255,255,255,.22);
    backdrop-filter: blur(8px); border-radius: 12px;
    padding: .7rem 1.1rem; text-align: center; min-width: 82px;
}
.cp-stat-num { font-size: 1.55rem; font-weight: 800; color: #fff; line-height: 1; }
.cp-stat-num.accent {
    background: linear-gradient(135deg,#4ecdc4,#44cf6c);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.cp-stat-lbl { font-size: .67rem; color: rgba(255,255,255,.65); text-transform: uppercase; letter-spacing: .4px; margin-top: .2rem; }

/* Shell */
.cp-shell { background: #f2f7f3; padding: 2.5rem 0 3.5rem; }

/* Section subtitle */
.cp-section-sub {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 1.5rem; gap: .75rem; flex-wrap: wrap;
}
.cp-section-sub h3 {
    font-size: 1.05rem; font-weight: 700; color: #1a3a2a; margin: 0;
    display: flex; align-items: center; gap: .5rem;
}
.cp-section-sub h3 i { color: var(--primary-color); }

/* Card */
.cp-card {
    background: #fff; border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,.07);
    border: 1px solid #dce8de; overflow: hidden;
    transition: transform .22s, box-shadow .22s;
    display: flex; flex-direction: column; height: 100%;
}
.cp-card:hover { transform: translateY(-4px); box-shadow: 0 10px 36px rgba(0,0,0,.13); }

/* Card header stripe */
.cp-card-head {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    padding: .9rem 1.25rem;
    display: flex; align-items: flex-start; gap: .9rem;
}
.cp-date-box {
    background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.3);
    border-radius: 10px; padding: .35rem .6rem;
    text-align: center; min-width: 44px; flex-shrink: 0;
}
.cp-date-box .dd { font-size: 1.3rem; font-weight: 800; color: #fff; line-height: 1; }
.cp-date-box .mm { font-size: .58rem; font-weight: 700; color: rgba(255,255,255,.8); text-transform: uppercase; letter-spacing: .3px; }
.cp-head-right { flex: 1; }
.cp-head-right h5 { color: #fff; font-weight: 700; margin: 0 0 .4rem; font-size: .97rem; line-height: 1.35; }
.cp-open-badge {
    background: #fff; color: #1d4ed8; font-size: .65rem; font-weight: 800;
    padding: .2em .72em; border-radius: 20px;
    display: inline-flex; align-items: center; gap: .25rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    animation: cp-blink 2s infinite;
}
.cp-closed-badge {
    background: rgba(255,255,255,.18); color: rgba(255,255,255,.75);
    font-size: .65rem; font-weight: 700; padding: .2em .72em; border-radius: 20px;
    display: inline-flex; align-items: center; gap: .25rem;
}
@keyframes cp-blink {
    0%,100% { box-shadow: 0 2px 8px rgba(0,0,0,.15), 0 0 0 0 rgba(29,78,216,.25); }
    50%      { box-shadow: 0 2px 8px rgba(0,0,0,.15), 0 0 0 5px rgba(29,78,216,0); }
}

/* Card body */
.cp-card-body { padding: 1.15rem 1.25rem; flex: 1; display: flex; flex-direction: column; }

/* Meta pills */
.cp-meta-row { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .9rem; }
.cp-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    border-radius: 20px; padding: .26rem .72rem;
    font-size: .77rem; font-weight: 500;
}
.cp-pill i { font-size: .68rem; }
.cp-pill-date { background: #eff6ff; color: #1d4ed8; }
.cp-pill-date i { color: #3b82f6; }
.cp-pill-time { background: #fefce8; color: #854d0e; }
.cp-pill-time i { color: #ca8a04; }
.cp-pill-loc  { background: #fdf4ff; color: #6b21a8; }
.cp-pill-loc  i { color: #a21caf; }
.cp-pill-tba  { background: #f1f5f9; color: #64748b; }

/* Description */
.cp-desc-text {
    color: #4b5563; font-size: .88rem; line-height: 1.62;
    flex: 1; margin-bottom: .9rem;
}

/* Pre-reg form area */
.cp-prereg-wrap { margin-top: .3rem; }
.cp-prereg-inner {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border-radius: 12px; border: 1px solid #bae6fd;
    padding: .95rem 1.1rem; margin-top: .4rem;
}
.cp-prereg-title {
    font-size: .81rem; font-weight: 700; color: #0369a1;
    margin-bottom: .7rem; display: flex; align-items: center; gap: .35rem;
}
.cp-btn-prereg {
    background: linear-gradient(135deg, #1d4ed8, #2563eb);
    color: #fff; border: none; border-radius: 9px;
    padding: .48rem 1.1rem; font-size: .82rem; font-weight: 700;
    cursor: pointer; display: inline-flex; align-items: center; gap: .4rem;
    transition: all .2s; box-shadow: 0 3px 10px rgba(29,78,216,.28);
}
.cp-btn-prereg:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(29,78,216,.4); color: #fff; }

/* Card footer */
.cp-card-footer {
    border-top: 1px solid #e5ede7; padding: .6rem 1.25rem;
    background: #f8fbf8;
    display: flex; align-items: center; justify-content: flex-end; gap: .5rem;
}
.cp-att-btn {
    font-size: .75rem; font-weight: 600; color: #16a34a;
    background: #f0fdf4; border: 1px solid #bbf7d0;
    border-radius: 8px; padding: .28rem .75rem;
    text-decoration: none; display: inline-flex; align-items: center; gap: .3rem;
    transition: all .2s;
}
.cp-att-btn:hover { background: #dcfce7; }

/* Empty state */
.cp-empty {
    background: #fff; border-radius: 16px; text-align: center;
    padding: 4rem 2rem; box-shadow: 0 2px 14px rgba(0,0,0,.06);
    border: 1.5px dashed #c8e6c9;
}
.cp-empty-icon {
    width: 82px; height: 82px; border-radius: 50%;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.2rem; font-size: 2.3rem; color: var(--primary-color);
}

/* Mobile */
@media (max-width: 575px) {
    .cp-hero { padding: 1.7rem 0 1.4rem; }
    .cp-hero-icon { display: none; }
    .cp-hero-text h2 { font-size: 1.35rem; }
    .cp-card-head { flex-direction: column; gap: .5rem; }
    .cp-card-body, .cp-card-footer { padding-left: 1rem; padding-right: 1rem; }
    .cp-stat-box { min-width: 72px; padding: .55rem .8rem; }
}
</style>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1><?php echo isEnglish() ? 'Cooperative Programs' : 'सहकारी कार्यक्रम'; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>"><?php echo isEnglish() ? 'Home' : 'गृहपृष्ठ'; ?></a></li>
                <li class="breadcrumb-item active"><?php echo isEnglish() ? 'Cooperative Programs' : 'सहकारी कार्यक्रम'; ?></li>
            </ol>
        </nav>
    </div>
</section>

<!-- Hero Stats Strip -->
<?php
$openPreregCount = count(array_filter($programs, fn($p) => !empty($p['pre_registration_open'])));
$upcomingCount   = count(array_filter($programs, fn($p) => !empty($p['event_date']) && strtotime($p['event_date']) >= strtotime('today')));
?>
<div class="cp-hero">
    <div class="container">
        <div class="cp-hero-inner">
            <div class="cp-hero-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="cp-hero-text">
                <h2><?php echo isEnglish() ? 'Upcoming Cooperative Programs' : 'आगामी सहकारी कार्यक्रमहरू'; ?></h2>
                <p><?php echo isEnglish()
                    ? 'Stay informed about cooperative events and register for programs you want to attend.'
                    : 'सहकारी कार्यक्रमहरूको जानकारी लिनुहोस् र आफू सहभागी हुन चाहेको कार्यक्रममा pre-registration गर्नुहोस्।'; ?>
                </p>
            </div>
            <?php if (!empty($programs)): ?>
            <div class="cp-hero-stats">
                <div class="cp-stat-box">
                    <div class="cp-stat-num"><?php echo count($programs); ?></div>
                    <div class="cp-stat-lbl"><?php echo isEnglish() ? 'Programs' : 'कार्यक्रम'; ?></div>
                </div>
                <?php if ($openPreregCount > 0): ?>
                <div class="cp-stat-box">
                    <div class="cp-stat-num accent"><?php echo $openPreregCount; ?></div>
                    <div class="cp-stat-lbl"><?php echo isEnglish() ? 'Pre-reg Open' : 'Pre-reg खुला'; ?></div>
                </div>
                <?php endif; ?>
                <?php if ($upcomingCount > 0): ?>
                <div class="cp-stat-box">
                    <div class="cp-stat-num"><?php echo $upcomingCount; ?></div>
                    <div class="cp-stat-lbl"><?php echo isEnglish() ? 'Upcoming' : 'आगामी'; ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Section -->
<section class="cp-shell">
    <div class="container">

        <?php if ($preregSaved || $preregAlready || $preregError !== ''): ?>
        <div class="mb-4">
            <div class="alert alert-dismissible fade show <?php echo $preregSaved ? 'alert-success' : ($preregAlready ? 'alert-warning' : 'alert-danger'); ?>">
                <i class="fas <?php echo $preregSaved ? 'fa-check-circle' : ($preregAlready ? 'fa-info-circle' : 'fa-exclamation-circle'); ?> me-2"></i>
                <?php if ($preregSaved): ?>
                    <?php echo isEnglish() ? 'Program pre-registration successful!' : 'कार्यक्रम pre-registration सफल भयो!'; ?>
                <?php elseif ($preregAlready): ?>
                    <?php echo isEnglish() ? 'This member is already pre-registered for this program.' : 'यो सदस्यको यो कार्यक्रममा pre-registration पहिल्यै भइसकेको छ।'; ?>
                <?php else: ?>
                    <?php echo htmlspecialchars($preregError); ?>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($programs)): ?>
        <div class="cp-empty" data-aos="fade-up">
            <div class="cp-empty-icon"><i class="fas fa-calendar-times"></i></div>
            <h5 class="mb-2"><?php echo isEnglish() ? 'No Active Programs Available' : 'हाल सक्रिय कार्यक्रम उपलब्ध छैन'; ?></h5>
            <p class="text-muted small"><?php echo isEnglish() ? 'Please check back later for upcoming cooperative programs.' : 'कृपया आगामी सहकारी कार्यक्रमहरूको लागि पछि फेरि जाँच गर्नुहोस्।'; ?></p>
        </div>
        <?php else: ?>

        <div class="cp-section-sub">
            <h3><i class="fas fa-list-ul"></i> <?php echo isEnglish() ? 'All Programs' : 'सबै कार्यक्रमहरू'; ?> <span class="badge bg-success ms-1" style="font-size:.72rem;"><?php echo count($programs); ?></span></h3>
        </div>

        <div class="row g-4">
        <?php foreach ($programs as $pg):
            /* Date display */
            $evDate   = $pg['event_date'] ?? '';
            $dayNum   = $evDate ? date('d', strtotime($evDate)) : '';
            $monStr   = $evDate ? date('M', strtotime($evDate)) : '';
            $isPassed = $evDate && strtotime($evDate) < strtotime('today');
        ?>
            <div class="col-lg-6" data-aos="fade-up">
                <div class="cp-card">

                    <!-- Colored header -->
                    <div class="cp-card-head">
                        <?php if ($dayNum): ?>
                        <div class="cp-date-box">
                            <div class="dd"><?php echo $dayNum; ?></div>
                            <div class="mm"><?php echo $monStr; ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="cp-head-right">
                            <h5><?php echo htmlspecialchars($pg['title']); ?></h5>
                            <?php if (!empty($pg['pre_registration_open'])): ?>
                                <span class="cp-open-badge"><i class="fas fa-user-plus"></i><?php echo isEnglish() ? 'Pre-reg Open' : 'Pre-reg खुला'; ?></span>
                            <?php else: ?>
                                <span class="cp-closed-badge"><i class="fas fa-lock"></i><?php echo isEnglish() ? 'Pre-reg Closed' : 'Pre-reg बन्द'; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="cp-card-body">
                        <div class="cp-meta-row">
                            <?php if ($evDate): ?>
                            <span class="cp-pill cp-pill-date <?php echo $isPassed ? 'opacity-60' : ''; ?>">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('Y-m-d', strtotime($evDate)); ?>
                                <?php if ($isPassed): ?><em style="font-size:.7rem;">(<?php echo isEnglish()?'Past':'भइसक्यो'; ?>)</em><?php endif; ?>
                            </span>
                            <?php else: ?>
                            <span class="cp-pill cp-pill-tba"><i class="fas fa-clock"></i><?php echo isEnglish() ? 'Date TBA' : 'मिति घोषणा हुनेछ'; ?></span>
                            <?php endif; ?>
                            <?php if (!empty($pg['event_time'])): ?>
                            <span class="cp-pill cp-pill-time"><i class="fas fa-clock"></i><?php echo htmlspecialchars($pg['event_time']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($pg['location'])): ?>
                            <span class="cp-pill cp-pill-loc"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($pg['location']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="cp-desc-text">
                            <?php echo htmlspecialchars($pg['description'] ?: (isEnglish() ? 'Program details will be updated soon.' : 'कार्यक्रमको थप विवरण चाँडै अपडेट हुनेछ।')); ?>
                        </div>

                        <?php if (!empty($pg['pre_registration_open'])): ?>
                        <div class="cp-prereg-wrap">
                            <button type="button" class="cp-btn-prereg"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#preRegForm<?php echo (int)$pg['id']; ?>">
                                <i class="fas fa-user-check"></i>
                                <?php echo isEnglish() ? 'Pre-register Now' : 'Pre-register गर्नुहोस्'; ?>
                            </button>

                            <div class="collapse cp-prereg-collapse <?php echo ($preregProgramId === (int)$pg['id'] && ($preregSaved || $preregAlready || $preregError !== '')) ? 'show' : ''; ?>"
                                 id="preRegForm<?php echo (int)$pg['id']; ?>">
                                <div class="cp-prereg-inner">
                                    <div class="cp-prereg-title">
                                        <i class="fas fa-clipboard-list"></i>
                                        <?php echo isEnglish() ? 'Quick Pre-Registration' : 'छिटो Pre-Registration'; ?>
                                    </div>
                                    <?php if ($preregProgramId === (int)$pg['id'] && ($preregSaved || $preregAlready || $preregError !== '')): ?>
                                    <div class="alert py-2 px-3 mb-2 <?php echo $preregSaved ? 'alert-success' : ($preregAlready ? 'alert-warning' : 'alert-danger'); ?>" style="font-size:.84rem;">
                                        <?php if ($preregSaved): ?>
                                            <i class="fas fa-check-circle me-1"></i><?php echo isEnglish() ? 'Registration successful!' : 'Registration सफल भयो!'; ?>
                                        <?php elseif ($preregAlready): ?>
                                            <i class="fas fa-info-circle me-1"></i><?php echo isEnglish() ? 'Already registered.' : 'पहिल्यै registration भइसक्यो।'; ?>
                                        <?php else: ?>
                                            <i class="fas fa-exclamation-circle me-1"></i><?php echo htmlspecialchars($preregError); ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <form method="POST" class="needs-validation row g-2" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="program_preregister">
                                        <input type="hidden" name="program_id" value="<?php echo (int)$pg['id']; ?>">
                                        <div class="col-sm-6">
                                            <input type="text" name="member_id_input" class="form-control form-control-sm"
                                                   placeholder="<?php echo isEnglish() ? 'Member ID / Card No.' : 'सदस्यता नं. / कार्ड नं.'; ?>"
                                                   value="<?php echo htmlspecialchars($preregProgramId === (int)$pg['id'] ? $preregMemberInput : ''); ?>"
                                                   required>
                                        </div>
                                        <div class="col-sm-6">
                                            <input type="text" name="prereg_note" class="form-control form-control-sm"
                                                   placeholder="<?php echo isEnglish() ? 'Note (optional)' : 'टिप्पणी (वैकल्पिक)'; ?>"
                                                   value="<?php echo htmlspecialchars($preregProgramId === (int)$pg['id'] ? $preregNoteInput : ''); ?>">
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-sm btn-primary">
                                                <i class="fas fa-check-circle me-1"></i><?php echo isEnglish() ? 'Confirm Registration' : 'Registration Confirm'; ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div><!-- /.cp-card-body -->

                    <!-- Footer -->
                    <div class="cp-card-footer">
                        <a href="program-attendance-verify.php?program_id=<?php echo (int)$pg['id']; ?>" class="cp-att-btn">
                            <i class="fas fa-qrcode"></i>
                            <?php echo isEnglish() ? 'Verify Attendance' : 'उपस्थिति जाँच'; ?>
                        </a>
                    </div>

                </div><!-- /.cp-card -->
            </div>
        <?php endforeach; ?>
        </div><!-- /.row -->
        <?php endif; ?>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
