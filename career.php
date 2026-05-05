<?php
require_once 'includes/config.php';
require_once 'includes/ensure-tables.php';
ensurePublicTables();
$pageTitle = isEnglish() ? 'Career Opportunities' : 'रोजगारीका अवसरहरू';
require_once 'includes/header.php';
$L = getLangStrings();

try {
    $db   = getDB();
    $jobs = $db->query("SELECT * FROM careers WHERE is_active = 1 ORDER BY created_at DESC")->fetchAll();
} catch (Exception $e) {
    $jobs = [];
}

$openCount   = 0;
$closedCount = 0;
$deptSet     = [];
foreach ($jobs as $j) {
    $passed = !empty($j['deadline']) && strtotime($j['deadline']) < strtotime('today');
    if ($passed) $closedCount++; else $openCount++;
    if (!empty($j['department'])) $deptSet[$j['department']] = true;
}
$totalDepts = count($deptSet);
?>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1><?php echo isEnglish() ? 'Career Opportunities' : 'रोजगारीका अवसरहरू'; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>"><?php echo $L['home']; ?></a></li>
                <li class="breadcrumb-item active"><?php echo isEnglish() ? 'Career' : 'क्यारियर'; ?></li>
            </ol>
        </nav>
    </div>
</section>

<style>
/* ════════════════════════════════════════════════
   Career Page v2 — Modern Job Board Design
════════════════════════════════════════════════ */

/* ── Hero Stats Strip ── */
.cr-hero {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
    padding: 2.5rem 0 2rem;
    color: #fff;
    margin-bottom: 0;
}
.cr-hero-inner {
    display: flex; align-items: center;
    gap: 1.5rem; flex-wrap: wrap;
}
.cr-hero-text { flex: 1; min-width: 200px; }
.cr-hero-text h2 {
    font-size: 1.7rem; font-weight: 800;
    margin-bottom: .4rem; color: #fff;
}
.cr-hero-text p { color: rgba(255,255,255,.65); margin: 0; font-size: .95rem; }

.cr-stats {
    display: flex; gap: 1rem; flex-wrap: wrap;
}
.cr-stat-box {
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    backdrop-filter: blur(10px);
    border-radius: 14px;
    padding: .85rem 1.3rem;
    text-align: center;
    min-width: 90px;
}
.cr-stat-num {
    font-size: 1.6rem; font-weight: 800;
    line-height: 1;
    background: linear-gradient(135deg, #4ecdc4, #44cf6c);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.cr-stat-num.red {
    background: linear-gradient(135deg, #ff6b6b, #ffd93d);
    -webkit-background-clip: text; background-clip: text;
}
.cr-stat-lbl { font-size: .7rem; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: .4px; margin-top: .2rem; }

/* ── Layout ── */
.cr-layout { padding: 2.5rem 0 3rem; }
.cr-main   { }
.cr-sidebar { }

/* ── Search & Filter Bar ── */
.cr-filterbar {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,.08);
    padding: 1.2rem 1.4rem;
    margin-bottom: 1.5rem;
    border: 1px solid #eef1f6;
}
.cr-search-row {
    display: flex; gap: .75rem; align-items: center;
    margin-bottom: 1rem;
}
.cr-search-field {
    flex: 1; position: relative;
}
.cr-search-field i {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #adb5bd; font-size: .9rem;
}
.cr-search-field input {
    width: 100%; padding: .7rem 1rem .7rem 2.5rem;
    border: 1.5px solid #e9ecef; border-radius: 10px;
    font-size: .92rem; transition: border-color .2s, box-shadow .2s;
    outline: none;
}
.cr-search-field input:focus {
    border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0,0,0,.1);
}
.cr-filter-chips {
    display: flex; gap: .5rem; flex-wrap: wrap;
}
.cr-chip {
    padding: .38rem .95rem; border-radius: 20px;
    font-size: .8rem; font-weight: 600; cursor: pointer;
    border: 1.5px solid #e9ecef; background: #f8f9fa; color: #6c757d;
    transition: all .2s; display: flex; align-items: center; gap: .4rem;
    user-select: none;
}
.cr-chip:hover { border-color: var(--primary-color); color: var(--primary-color); background: #e8f5e9; }
.cr-chip.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
.cr-chip.active.green { background: #198754; border-color: #198754; }
.cr-chip.active.grey  { background: #6c757d; border-color: #6c757d; }
.cr-chip-count {
    background: rgba(255,255,255,.25); color: inherit;
    border-radius: 10px; padding: 0 .45rem; font-size: .72rem;
    min-width: 18px; text-align: center;
}
.cr-chip:not(.active) .cr-chip-count { background: #e9ecef; }

/* ── Result count ── */
.cr-result-count {
    font-size: .82rem; color: #6c757d;
    margin-bottom: 1rem; display: flex; align-items: center; gap: .4rem;
}

/* ── Job Cards ── */
.cr-job-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 14px rgba(0,0,0,.07);
    border: 1.5px solid #eef1f6;
    border-left: 5px solid var(--primary-color);
    margin-bottom: 1.1rem;
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
    position: relative;
}
.cr-job-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(0,0,0,.12);
}
.cr-job-card.cr-closed {
    border-left-color: #adb5bd;
    opacity: .75;
}
.cr-job-card.cr-urgent { border-left-color: #fd7e14; }

/* Urgent ribbon */
.cr-urgent-tag {
    position: absolute; top: 0; right: 0;
    background: linear-gradient(135deg, #fd7e14, #dc3545);
    color: #fff; font-size: .68rem; font-weight: 800;
    padding: .25rem .75rem; border-radius: 0 0 0 10px;
    text-transform: uppercase; letter-spacing: .4px;
    display: flex; align-items: center; gap: .3rem;
}

/* Card inner */
.cr-card-inner { padding: 1.2rem 1.4rem; }

/* Top row */
.cr-card-top {
    display: flex; align-items: flex-start;
    gap: 1rem; margin-bottom: .9rem;
}
.cr-dept-avatar {
    width: 50px; height: 50px; flex-shrink: 0;
    border-radius: 12px;
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    display: flex; align-items: center; justify-content: center;
    color: var(--primary-color); font-size: 1.2rem;
}
.cr-job-card.cr-closed .cr-dept-avatar { background: #f1f3f5; color: #adb5bd; }
.cr-job-card.cr-urgent .cr-dept-avatar { background: linear-gradient(135deg, #fff3e0, #ffe0b2); color: #e65100; }

.cr-card-heading { flex: 1; }
.cr-job-title {
    font-size: 1.05rem; font-weight: 700;
    color: var(--primary-dark); margin-bottom: .3rem; line-height: 1.3;
}
.cr-job-card.cr-closed .cr-job-title { text-decoration: line-through; color: #868e96; }

.cr-badge-row { display: flex; gap: .4rem; flex-wrap: wrap; }
.cr-tag {
    font-size: .7rem; font-weight: 700;
    padding: .18em .65em; border-radius: 20px;
    text-transform: uppercase; letter-spacing: .3px;
}
.cr-tag.type  { background: #e8f5e9; color: #1971c2; }
.cr-tag.dept  { background: #f3f0ff; color: #6741d9; }
.cr-tag.open  { background: #d3f9d8; color: #2f9e44; }
.cr-tag.closed-tag { background: #e9ecef; color: #868e96; }
.cr-tag.urgent-tag { background: #fff4e6; color: #e8590c; }

/* Meta info chips */
.cr-meta {
    display: flex; flex-wrap: wrap; gap: .5rem 1.2rem;
    margin-bottom: .85rem;
}
.cr-meta-item {
    display: flex; align-items: center; gap: .4rem;
    font-size: .82rem; color: #495057;
}
.cr-meta-item i {
    width: 14px; text-align: center;
    color: #868e96; font-size: .8rem; flex-shrink: 0;
}
.cr-meta-item.deadline-near { color: #e8590c; font-weight: 600; }
.cr-meta-item.deadline-gone { color: #868e96; }

/* Description */
.cr-desc {
    font-size: .85rem; color: #6c757d;
    line-height: 1.6; margin-bottom: 1rem;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Actions */
.cr-actions {
    display: flex; gap: .5rem; flex-wrap: wrap;
    padding-top: .85rem;
    border-top: 1px solid #f1f3f5;
}
.cr-btn-detail {
    padding: .5rem 1.1rem; border-radius: 8px;
    font-size: .82rem; font-weight: 600;
    border: 1.5px solid var(--primary-color); color: var(--primary-color);
    background: transparent; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
    transition: all .2s;
}
.cr-btn-detail:hover { background: var(--primary-color); color: #fff; }
.cr-btn-apply {
    padding: .5rem 1.2rem; border-radius: 8px;
    font-size: .82rem; font-weight: 700;
    background: linear-gradient(135deg, #198754, #12694a);
    color: #fff; border: none; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
    transition: all .2s; box-shadow: 0 2px 8px rgba(25,135,84,.3);
}
.cr-btn-apply:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(25,135,84,.4); color: #fff; }
.cr-btn-dl {
    padding: .5rem .9rem; border-radius: 8px;
    font-size: .82rem; font-weight: 600;
    border: 1.5px solid #dee2e6; color: #6c757d;
    background: #f8f9fa; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
    transition: all .2s;
}
.cr-btn-dl:hover { background: #e9ecef; color: #212529; }

/* Deadline progress bar */
.cr-deadline-bar {
    height: 3px; background: #e9ecef;
    border-radius: 2px; margin-bottom: .7rem; overflow: hidden;
}
.cr-deadline-fill {
    height: 100%; border-radius: 2px;
    transition: width .5s;
}

/* ── Empty / No results ── */
.cr-empty {
    text-align: center; padding: 3rem 1.5rem;
    background: #fff; border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.cr-empty-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: #f1f3f5; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem; font-size: 1.8rem; color: #adb5bd;
}

/* ── Sidebar ── */
.cr-sb-card {
    border-radius: 14px;
    margin-bottom: 1.2rem;
    overflow: hidden;
    box-shadow: 0 2px 14px rgba(0,0,0,.08);
}
.cr-sb-card .cr-sb-head {
    padding: 1.2rem 1.4rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,.15);
}
.cr-sb-card .cr-sb-body { padding: 1.2rem 1.4rem; background: #fff; }

/* ── Dept filter chips (below existing chips) ── */
.cr-dept-chips {
    display: flex; gap: .4rem; flex-wrap: wrap;
    padding-top: .75rem; border-top: 1px solid #f1f3f5; margin-top: .75rem;
}
.cr-dept-chip {
    padding: .28rem .8rem; border-radius: 20px;
    font-size: .76rem; font-weight: 600; cursor: pointer;
    border: 1.5px solid #e9ecef; background: #f8f9fa; color: #6c757d;
    transition: all .18s; display: inline-flex; align-items: center; gap: .3rem;
}
.cr-dept-chip:hover   { border-color: #7950f2; color: #7950f2; background: #f3f0ff; }
.cr-dept-chip.active  { background: #7950f2; color: #fff; border-color: #7950f2; }

/* ── Sidebar on mobile: collapsible ── */
.cr-sidebar-toggle {
    display: none; width: 100%; background: #fff;
    border: 1.5px solid #eef1f6; border-radius: 12px;
    padding: .75rem 1.1rem; font-size: .88rem; font-weight: 700;
    color: var(--primary-dark); cursor: pointer; margin-bottom: .75rem;
    text-align: left; align-items: center; gap: .5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}
@media (max-width: 991px) {
    .cr-sidebar-toggle { display: flex; }
    .cr-sidebar-content { display: none; }
    .cr-sidebar-content.open { display: block; }
}
@media (min-width: 992px) {
    .cr-sidebar-content { display: block !important; }
}

/* Track card */
.cr-track-card { background: linear-gradient(135deg, var(--primary-dark), var(--primary-color)); }
.cr-track-card .cr-sb-head h4 { color: #fff; margin: 0 0 .3rem; font-size: 1rem; font-weight: 700; }
.cr-track-card .cr-sb-head p  { color: rgba(255,255,255,.65); font-size: .84rem; margin: 0; }
.cr-track-card .cr-sb-body { background: var(--primary-color); border-top: 1px solid rgba(255,255,255,.1); }
.cr-btn-track {
    display: flex; align-items: center; gap: .5rem;
    background: #fff; color: var(--primary-color);
    padding: .65rem 1.2rem; border-radius: 10px;
    font-weight: 700; font-size: .88rem; text-decoration: none;
    transition: all .2s; width: 100%; justify-content: center;
}
.cr-btn-track:hover { background: #e8f5e9; color: var(--primary-color); }

/* CV card */
.cr-cv-card { background: #fff; border: 1.5px solid #eef1f6; }
.cr-cv-card .cr-sb-body { padding: 1.4rem; }
.cr-cv-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: linear-gradient(135deg, #e8f5e9, #74c0fc);
    display: flex; align-items: center; justify-content: center;
    color: #1971c2; font-size: 1.3rem; margin-bottom: .85rem;
}
.cr-cv-card h4 { font-size: 1rem; font-weight: 700; color: var(--primary-dark); margin-bottom: .4rem; }
.cr-cv-card p  { font-size: .84rem; color: #6c757d; margin-bottom: 1rem; }
.cr-btn-cv {
    display: flex; align-items: center; gap: .5rem;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: #fff; padding: .65rem 1.2rem; border-radius: 10px;
    font-weight: 700; font-size: .88rem; text-decoration: none;
    transition: all .2s; width: 100%; justify-content: center;
}
.cr-btn-cv:hover { opacity: .9; color: #fff; }

/* Why join */
.cr-why-card { background: #fff; border: 1.5px solid #eef1f6; }
.cr-why-card .cr-sb-body { padding: 1.4rem; }
.cr-why-card h4 { font-size: 1rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 1rem; }
.cr-benefits {
    display: flex; flex-direction: column; gap: .65rem;
}
.cr-benefit-item {
    display: flex; align-items: center; gap: .75rem;
    font-size: .87rem; color: #495057;
}
.cr-benefit-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, #d3f9d8, #b2f2bb);
    display: flex; align-items: center; justify-content: center;
    color: #2f9e44; font-size: .8rem; flex-shrink: 0;
}

/* No vacancy state */
.cr-novacancy {
    background: #fff; border-radius: 14px;
    padding: 3rem 2rem; text-align: center;
    box-shadow: 0 2px 14px rgba(0,0,0,.07);
    border: 1.5px solid #eef1f6;
}
.cr-novacancy-icon {
    width: 90px; height: 90px; border-radius: 50%;
    background: linear-gradient(135deg, #f1f3f5, #e9ecef);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.2rem; font-size: 2.2rem; color: #adb5bd;
}
</style>

<!-- Hero Stats -->
<div class="cr-hero">
    <div class="container">
        <div class="cr-hero-inner">
            <div class="cr-hero-text">
                <h2><?php echo isEnglish() ? 'Join Our Team' : 'हाम्रो टोलीमा सामेल हुनुहोस्'; ?></h2>
                <p><?php echo isEnglish()
                    ? 'Build your career with आकाश सहकारी — a trusted cooperative family.'
                    : 'आकाश सहकारीसँग आफ्नो क्यारियर निर्माण गर्नुहोस् — एक विश्वसनीय सहकारी परिवार।'; ?>
                </p>
            </div>
            <div class="cr-stats">
                <div class="cr-stat-box">
                    <div class="cr-stat-num"><?php echo $openCount; ?></div>
                    <div class="cr-stat-lbl"><?php echo isEnglish() ? 'Open' : 'खुला पद'; ?></div>
                </div>
                <?php if ($closedCount > 0): ?>
                <div class="cr-stat-box">
                    <div class="cr-stat-num red"><?php echo $closedCount; ?></div>
                    <div class="cr-stat-lbl"><?php echo isEnglish() ? 'Closed' : 'बन्द भएका'; ?></div>
                </div>
                <?php endif; ?>
                <?php if ($totalDepts > 0): ?>
                <div class="cr-stat-box">
                    <div class="cr-stat-num"><?php echo $totalDepts; ?></div>
                    <div class="cr-stat-lbl"><?php echo isEnglish() ? 'Depts' : 'विभागहरू'; ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<section class="cr-layout">
<div class="container">
<div class="row g-4">

<!-- ── Main Column ── -->
<div class="col-lg-8 cr-main">

    <?php if (!empty($jobs)): ?>

    <!-- Filter Bar -->
    <div class="cr-filterbar" data-aos="fade-up">
        <div class="cr-search-row">
            <div class="cr-search-field">
                <i class="fas fa-search"></i>
                <input type="text" id="crSearch"
                       placeholder="<?php echo isEnglish() ? 'Search by title, department...' : 'पद, विभाग अनुसार खोज्नुहोस्...'; ?>"
                       oninput="crFilter()">
            </div>
        </div>
        <div class="cr-filter-chips">
            <div class="cr-chip active" data-filter="all" onclick="crChip(this)">
                <i class="fas fa-th-large"></i>
                <?php echo isEnglish() ? 'All' : 'सबै'; ?>
                <span class="cr-chip-count"><?php echo count($jobs); ?></span>
            </div>
            <div class="cr-chip" data-filter="open" onclick="crChip(this)">
                <i class="fas fa-door-open"></i>
                <?php echo isEnglish() ? 'Open' : 'खुला'; ?>
                <span class="cr-chip-count"><?php echo $openCount; ?></span>
            </div>
            <?php if ($closedCount > 0): ?>
            <div class="cr-chip" data-filter="closed" onclick="crChip(this)">
                <i class="fas fa-lock"></i>
                <?php echo isEnglish() ? 'Closed' : 'बन्द'; ?>
                <span class="cr-chip-count"><?php echo $closedCount; ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($totalDepts > 0): ?>
        <div class="cr-dept-chips">
            <span style="font-size:.72rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.3px;display:flex;align-items:center;gap:.3rem;margin-right:.3rem;">
                <i class="fas fa-building"></i><?php echo isEnglish() ? 'Dept:' : 'विभाग:'; ?>
            </span>
            <?php foreach (array_keys($deptSet) as $dname): ?>
            <div class="cr-dept-chip"
                 data-dept-filter="<?php echo strtolower(htmlspecialchars($dname)); ?>"
                 onclick="crDeptChip(this)">
                <?php echo htmlspecialchars($dname); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Result count -->
    <div class="cr-result-count" id="crCount">
        <i class="fas fa-list-ul"></i>
        <span id="crCountText"><?php echo count($jobs); ?> <?php echo isEnglish() ? 'positions found' : 'पदहरू भेटिए'; ?></span>
    </div>

    <!-- Jobs List -->
    <div id="crGrid">
    <?php
    $deptIcons = [
        'IT' => 'fa-laptop-code', 'लेखा' => 'fa-calculator', 'Accounts' => 'fa-calculator',
        'HR' => 'fa-users', 'Operations' => 'fa-cogs', 'Marketing' => 'fa-bullhorn',
        'Finance' => 'fa-coins', 'Admin' => 'fa-building', 'Loan' => 'fa-hand-holding-usd',
        'Credit' => 'fa-credit-card', 'Audit' => 'fa-clipboard-check',
    ];

    foreach ($jobs as $idx => $job):
        $deadlinePassed = !empty($job['deadline']) && strtotime($job['deadline']) < strtotime('today');
        $daysLeft = 0;
        $totalDays = 0;
        if (!empty($job['deadline'])) {
            $daysLeft = (int)ceil((strtotime($job['deadline']) - time()) / 86400);
            if (!empty($job['created_at'])) {
                $totalDays = (int)ceil((strtotime($job['deadline']) - strtotime($job['created_at'])) / 86400);
            }
        }
        $isUrgent = (!$deadlinePassed && $daysLeft > 0 && $daysLeft <= 7);
        $dept = $job['department'] ?? '';
        $deptIcon = 'fa-briefcase';
        foreach ($deptIcons as $k => $v) {
            if (stripos($dept, $k) !== false) { $deptIcon = $v; break; }
        }
        $cardClass = $deadlinePassed ? 'cr-closed' : ($isUrgent ? 'cr-urgent' : '');

        // Progress bar: how much of deadline window remains
        $progressPct = 100;
        if (!$deadlinePassed && $totalDays > 0 && $daysLeft >= 0) {
            $progressPct = min(100, max(3, ($daysLeft / $totalDays) * 100));
        }
        $progressColor = $deadlinePassed ? '#adb5bd' : ($daysLeft <= 3 ? '#e03131' : ($daysLeft <= 7 ? '#f76707' : '#2f9e44'));
    ?>
    <div class="cr-job-card <?php echo $cardClass; ?>"
         data-status="<?php echo $deadlinePassed ? 'closed' : 'open'; ?>"
         data-title="<?php echo strtolower(htmlspecialchars(getLangField($job, 'title'))); ?>"
         data-dept="<?php echo strtolower(htmlspecialchars($dept)); ?>"
         data-aos="fade-up" data-aos-delay="<?php echo ($idx % 4) * 60; ?>">

        <?php if ($isUrgent): ?>
        <div class="cr-urgent-tag">
            <i class="fas fa-fire"></i>
            <?php echo isEnglish() ? 'Closes in '.$daysLeft.'d' : $daysLeft.' दिनमा बन्द'; ?>
        </div>
        <?php endif; ?>

        <div class="cr-card-inner">
            <!-- Top row -->
            <div class="cr-card-top">
                <div class="cr-dept-avatar">
                    <i class="fas <?php echo $deptIcon; ?>"></i>
                </div>
                <div class="cr-card-heading">
                    <div class="cr-job-title"><?php echo htmlspecialchars(getLangField($job, 'title')); ?></div>
                    <div class="cr-badge-row">
                        <?php if (!empty($job['job_type'])): ?>
                        <span class="cr-tag type"><?php echo htmlspecialchars($job['job_type']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($dept)): ?>
                        <span class="cr-tag dept"><?php echo htmlspecialchars($dept); ?></span>
                        <?php endif; ?>
                        <?php if ($deadlinePassed): ?>
                        <span class="cr-tag closed-tag"><i class="fas fa-lock me-1"></i><?php echo isEnglish() ? 'Closed' : 'बन्द'; ?></span>
                        <?php elseif ($isUrgent): ?>
                        <span class="cr-tag urgent-tag"><i class="fas fa-fire me-1"></i><?php echo isEnglish() ? 'Urgent' : 'अर्जेन्ट'; ?></span>
                        <?php else: ?>
                        <span class="cr-tag open"><i class="fas fa-circle me-1" style="font-size:.55em;"></i><?php echo isEnglish() ? 'Open' : 'खुला'; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Meta info -->
            <div class="cr-meta">
                <?php if (!empty($job['location'])): ?>
                <span class="cr-meta-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($job['location']); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($job['vacancy_count'])): ?>
                <span class="cr-meta-item">
                    <i class="fas fa-users"></i>
                    <?php echo isEnglish() ? 'Vacancy: '.$job['vacancy_count'] : 'रिक्त: '.$job['vacancy_count']; ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($job['salary_range'])): ?>
                <span class="cr-meta-item">
                    <i class="fas fa-coins"></i>
                    <?php echo htmlspecialchars($job['salary_range']); ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($job['deadline'])): ?>
                <span class="cr-meta-item <?php echo $deadlinePassed ? 'deadline-gone' : ($daysLeft <= 7 ? 'deadline-near' : ''); ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo isEnglish() ? 'Deadline:' : 'म्याद:'; ?>
                    <?php echo date('Y M d', strtotime($job['deadline'])); ?>
                    <?php if (!$deadlinePassed && $daysLeft > 0): ?>
                    <strong style="margin-left:.3rem;">(<?php echo isEnglish() ? $daysLeft.'d left' : $daysLeft.' दिन'; ?>)</strong>
                    <?php elseif ($deadlinePassed): ?>
                    <strong style="margin-left:.3rem;">(<?php echo isEnglish() ? 'Expired' : 'म्याद सकियो'; ?>)</strong>
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </div>

            <!-- Deadline progress bar -->
            <?php if (!empty($job['deadline'])): ?>
            <div class="cr-deadline-bar">
                <div class="cr-deadline-fill" style="width:<?php echo $deadlinePassed ? 0 : $progressPct; ?>%; background:<?php echo $progressColor; ?>;"></div>
            </div>
            <?php endif; ?>

            <!-- Description -->
            <?php $desc = getLangField($job, 'description'); if (!empty($desc)): ?>
            <div class="cr-desc"><?php echo htmlspecialchars($desc); ?></div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="cr-actions">
                <a href="career-detail.php?id=<?php echo $job['id']; ?>" class="cr-btn-detail">
                    <i class="fas fa-info-circle"></i>
                    <?php echo isEnglish() ? 'Details' : 'विवरण'; ?>
                </a>
                <?php if (!$deadlinePassed && ($job['allow_online_apply'] ?? 1)): ?>
                <a href="career-detail.php?id=<?php echo $job['id']; ?>#apply-form" class="cr-btn-apply">
                    <i class="fas fa-paper-plane"></i>
                    <?php echo isEnglish() ? 'Apply Now' : 'अहिले आवेदन'; ?>
                </a>
                <?php endif; ?>
                <?php if (!empty($job['attachment'])): ?>
                <a href="<?php echo htmlspecialchars($job['attachment']); ?>" class="cr-btn-dl" download
                   title="<?php echo isEnglish() ? 'Download Notice' : 'सूचना डाउनलोड'; ?>">
                    <i class="fas fa-download"></i>
                    <?php echo isEnglish() ? 'Notice' : 'सूचना'; ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <!-- No results -->
    <div id="crNoResults" style="display:none;">
        <div class="cr-empty">
            <div class="cr-empty-icon"><i class="fas fa-search"></i></div>
            <h5><?php echo isEnglish() ? 'No Matching Positions Found' : 'कुनै पद फेला परेन'; ?></h5>
            <p class="text-muted small"><?php echo isEnglish() ? 'Try different keywords.' : 'अर्को शब्दले खोज्नुहोस्।'; ?></p>
            <button class="btn btn-outline-secondary btn-sm mt-2" onclick="crReset()">
                <i class="fas fa-redo me-1"></i><?php echo isEnglish() ? 'Reset' : 'रिसेट'; ?>
            </button>
        </div>
    </div>

    <?php else: ?>
    <!-- No vacancies at all -->
    <div class="cr-novacancy" data-aos="fade-up">
        <div class="cr-novacancy-icon"><i class="fas fa-briefcase"></i></div>
        <h4 class="mb-2"><?php echo isEnglish() ? 'No Current Openings' : 'हाल कुनै रिक्त पद छैन'; ?></h4>
        <p class="text-muted mb-3">
            <?php echo isEnglish()
                ? 'No job openings at the moment. Please check back later or send your CV to our email.'
                : 'हाल कुनै पद रिक्त छैन। कृपया पछि फेरि जाँच गर्नुहोस् वा हाम्रो इमेलमा CV पठाउनुहोस्।'; ?>
        </p>
        <a href="mailto:<?php echo getSetting('email','info@sahakari.org.np'); ?>?subject=CV Submission"
           class="btn btn-primary">
            <i class="fas fa-envelope me-2"></i><?php echo isEnglish() ? 'Send Your CV' : 'CV इमेल गर्नुहोस्'; ?>
        </a>
    </div>
    <?php endif; ?>

</div><!-- /.col main -->

<!-- ── Sidebar ── -->
<div class="col-lg-4 cr-sidebar">

    <button class="cr-sidebar-toggle" onclick="this.nextElementSibling.classList.toggle('open')">
        <i class="fas fa-info-circle"></i>
        <?php echo isEnglish() ? 'Career Resources & Info' : 'क्यारियर सहायता र जानकारी'; ?>
        <i class="fas fa-chevron-down ms-auto"></i>
    </button>

    <div class="cr-sidebar-content">

    <!-- Track Application -->
    <div class="cr-sb-card cr-track-card" data-aos="fade-up">
        <div class="cr-sb-head">
            <h4><i class="fas fa-search me-2"></i><?php echo isEnglish() ? 'Track Application' : 'आवेदन ट्र्याक गर्नुहोस्'; ?></h4>
            <p><?php echo isEnglish()
                ? 'Already applied? Check your status with Tracking ID or email.'
                : 'पहिले नै आवेदन दिनुभयो? Tracking ID वा इमेलले स्थिति हेर्नुहोस्।'; ?>
            </p>
        </div>
        <div class="cr-sb-body">
            <a href="application-tracker.php" class="cr-btn-track">
                <i class="fas fa-route"></i>
                <?php echo isEnglish() ? 'Track Now' : 'अहिले ट्र्याक'; ?>
            </a>
        </div>
    </div>

    <!-- Submit CV -->
    <div class="cr-sb-card cr-cv-card" data-aos="fade-up" data-aos-delay="80">
        <div class="cr-sb-body">
            <div class="cr-cv-icon"><i class="fas fa-file-user"></i></div>
            <h4><?php echo isEnglish() ? 'Submit Your CV' : 'आफ्नो CV पठाउनुहोस्'; ?></h4>
            <p><?php echo isEnglish()
                ? 'Interested in joining us? Send your CV to our HR department even if no vacancy is posted.'
                : 'हामीसँग सामेल हुन इच्छुक? रिक्त पद नभए पनि HR विभागमा CV पठाउन सक्नुहुन्छ।'; ?>
            </p>
            <a href="mailto:<?php echo getSetting('email','info@sahakari.org.np'); ?>?subject=CV Submission - Job Application" class="cr-btn-cv">
                <i class="fas fa-paper-plane"></i>
                <?php echo isEnglish() ? 'Send CV' : 'CV पठाउनुहोस्'; ?>
            </a>
        </div>
    </div>

    <!-- Why Join Us -->
    <div class="cr-sb-card cr-why-card" data-aos="fade-up" data-aos-delay="140">
        <div class="cr-sb-body">
            <h4><i class="fas fa-star me-2 text-warning"></i><?php echo isEnglish() ? 'Why Join Us?' : 'हामीलाई किन रोज्ने?'; ?></h4>
            <div class="cr-benefits">
                <?php
                $benefits = [
                    ['fa-money-bill-wave', isEnglish() ? 'Competitive Salary'   : 'प्रतिस्पर्धी तलब'],
                    ['fa-chart-line',      isEnglish() ? 'Professional Growth'  : 'व्यावसायिक वृद्धि'],
                    ['fa-smile',           isEnglish() ? 'Friendly Environment' : 'मैत्रीपूर्ण वातावरण'],
                    ['fa-heartbeat',       isEnglish() ? 'Health Benefits'      : 'स्वास्थ्य सुविधाहरू'],
                    ['fa-gift',            isEnglish() ? 'Festival Bonus'       : 'चाडपर्व बोनस'],
                    ['fa-graduation-cap',  isEnglish() ? 'Training Support'     : 'तालिम सहयोग'],
                ];
                foreach ($benefits as $b): ?>
                <div class="cr-benefit-item">
                    <div class="cr-benefit-icon"><i class="fas <?php echo $b[0]; ?>"></i></div>
                    <span><?php echo $b[1]; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    </div><!-- /.cr-sidebar-content -->
</div><!-- /.sidebar -->
</div><!-- /.row -->
</div><!-- /.container -->
</section>

<script>
/* ─── Career v2 Filter Logic ─── */
var crActiveFilter = 'all';

function crFilter() {
    var q   = (document.getElementById('crSearch').value || '').toLowerCase().trim();
    var cards = document.querySelectorAll('#crGrid .cr-job-card');
    var visible = 0;

    cards.forEach(function(c) {
        var title = (c.getAttribute('data-title') || '');
        var dept  = (c.getAttribute('data-dept')  || '');
        var status= (c.getAttribute('data-status') || '');

        var matchSearch = !q || title.includes(q) || dept.includes(q);
        var matchFilter = crActiveFilter === 'all' || status === crActiveFilter;

        if (matchSearch && matchFilter) {
            c.style.display = ''; visible++;
        } else {
            c.style.display = 'none';
        }
    });

    var countEl = document.getElementById('crCountText');
    if (countEl) {
        countEl.textContent = visible + ' <?php echo isEnglish() ? "positions found" : "पदहरू भेटिए"; ?>';
    }
    var noRes = document.getElementById('crNoResults');
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}

function crChip(el) {
    document.querySelectorAll('.cr-chip').forEach(function(c) {
        c.classList.remove('active','green','grey');
    });
    el.classList.add('active');
    var f = el.getAttribute('data-filter');
    if (f === 'open')   el.classList.add('green');
    if (f === 'closed') el.classList.add('grey');
    crActiveFilter = f;
    crFilter();
}

function crReset() {
    document.getElementById('crSearch').value = '';
    crActiveFilter = 'all';
    crActiveDept   = '';
    document.querySelectorAll('.cr-chip').forEach(function(c, i) {
        c.classList.remove('active','green','grey');
        if (i === 0) c.classList.add('active');
    });
    document.querySelectorAll('.cr-dept-chip').forEach(function(c) {
        c.classList.remove('active');
    });
    crFilter();
}

/* Department chip filter */
var crActiveDept = '';
function crDeptChip(el) {
    var d = el.getAttribute('data-dept-filter') || '';
    if (crActiveDept === d) {
        crActiveDept = '';
        el.classList.remove('active');
    } else {
        document.querySelectorAll('.cr-dept-chip').forEach(function(c) { c.classList.remove('active'); });
        crActiveDept = d;
        el.classList.add('active');
    }
    crFilter();
}

/* Override crFilter to also handle dept */
var _crFilterOrig = crFilter;
crFilter = function() {
    var q      = (document.getElementById('crSearch').value || '').toLowerCase().trim();
    var cards  = document.querySelectorAll('#crGrid .cr-job-card');
    var visible = 0;
    cards.forEach(function(c) {
        var title  = (c.getAttribute('data-title') || '');
        var dept   = (c.getAttribute('data-dept')  || '').toLowerCase();
        var status = (c.getAttribute('data-status') || '');
        var matchSearch = !q || title.includes(q) || dept.includes(q);
        var matchFilter = crActiveFilter === 'all' || status === crActiveFilter;
        var matchDept   = !crActiveDept  || dept.includes(crActiveDept);
        if (matchSearch && matchFilter && matchDept) {
            c.style.display = ''; visible++;
        } else {
            c.style.display = 'none';
        }
    });
    var countEl = document.getElementById('crCountText');
    if (countEl) countEl.textContent = visible + ' <?php echo isEnglish() ? "positions found" : "पदहरू भेटिए"; ?>';
    var noRes = document.getElementById('crNoResults');
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
};

/* Search on keyup */
document.getElementById('crSearch')?.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { this.value = ''; crFilter(); }
});
</script>

<?php require_once 'includes/footer.php'; ?>
