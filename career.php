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
    color: var(--text-on-primary);
    margin-bottom: 0;
}
.cr-hero-inner {
    display: flex; align-items: center;
    gap: 1.5rem; flex-wrap: wrap;
}
.cr-hero-text { flex: 1; min-width: 200px; }
.cr-hero-text h2 {
    font-size: 1.7rem; font-weight: 800;
    margin-bottom: .4rem; color: var(--text-on-primary);
}
.cr-hero-text p { color: color-mix(in srgb, var(--text-on-primary) 68%, transparent); margin: 0; font-size: .95rem; }

.cr-stats {
    display: flex; gap: 1rem; flex-wrap: wrap;
}
.cr-stat-box {
    background: color-mix(in srgb, var(--text-on-primary) 12%, transparent);
    border: 1px solid color-mix(in srgb, var(--text-on-primary) 20%, transparent);
    backdrop-filter: blur(10px);
    border-radius: 14px;
    padding: .85rem 1.3rem;
    text-align: center;
    min-width: 90px;
}
.cr-stat-num {
    font-size: 1.6rem; font-weight: 800;
    line-height: 1;
    background: linear-gradient(135deg, var(--accent-color), var(--primary-light));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.cr-stat-num.red {
    background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
    -webkit-background-clip: text; background-clip: text;
}
.cr-stat-lbl { font-size: .7rem; color: color-mix(in srgb, var(--text-on-primary) 62%, transparent); text-transform: uppercase; letter-spacing: .4px; margin-top: .2rem; }

/* ── Layout ── */
.cr-layout { padding: 2.5rem 0 3rem; }
.cr-main   { }
.cr-sidebar { }

/* ── Search & Filter Bar ── */
.cr-filterbar {
    background: var(--surface-color);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(var(--primary-rgb), .10);
    padding: 1.2rem 1.4rem;
    margin-bottom: 1.5rem;
    border: 1px solid color-mix(in srgb, var(--primary-color) 12%, white);
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
    color: var(--text-muted); font-size: .9rem;
}
.cr-search-field input {
    width: 100%; padding: .7rem 1rem .7rem 2.5rem;
    border: 1.5px solid color-mix(in srgb, var(--primary-color) 16%, white); border-radius: 10px;
    font-size: .92rem; transition: border-color .2s, box-shadow .2s;
    outline: none;
}
.cr-search-field input:focus {
    border-color: var(--primary-color); box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color) 20%, transparent);
}
.cr-filter-chips {
    display: flex; gap: .5rem; flex-wrap: wrap;
}
.cr-chip {
    padding: .38rem .95rem; border-radius: 20px;
    font-size: .8rem; font-weight: 600; cursor: pointer;
    border: 1.5px solid color-mix(in srgb, var(--primary-color) 16%, white); background: color-mix(in srgb, var(--primary-color) 6%, white); color: var(--text-light);
    transition: all .2s; display: flex; align-items: center; gap: .4rem;
    user-select: none;
}
.cr-chip:hover { border-color: var(--primary-color); color: var(--primary-color); background: color-mix(in srgb, var(--primary-color) 12%, white); }
.cr-chip.active { background: var(--primary-color); color: var(--text-on-primary); border-color: var(--primary-color); }
.cr-chip.active.green { background: var(--accent-dark); border-color: var(--accent-dark); color: var(--text-on-accent); }
.cr-chip.active.grey  { background: var(--text-muted); border-color: var(--text-muted); color: white; }
.cr-chip-count {
    background: color-mix(in srgb, var(--text-on-primary) 25%, transparent); color: inherit;
    border-radius: 10px; padding: 0 .45rem; font-size: .72rem;
    min-width: 18px; text-align: center;
}
.cr-chip:not(.active) .cr-chip-count { background: color-mix(in srgb, var(--primary-color) 14%, white); }

/* ── Result count ── */
.cr-result-count {
    font-size: .82rem; color: var(--text-light);
    margin-bottom: 1rem; display: flex; align-items: center; gap: .4rem;
}

/* ── Job Cards ── */
.cr-job-card {
    background: var(--surface-color);
    border-radius: 14px;
    box-shadow: 0 2px 14px rgba(var(--primary-rgb), .09);
    border: 1.5px solid color-mix(in srgb, var(--primary-color) 12%, white);
    border-left: 5px solid var(--primary-color);
    margin-bottom: 1.1rem;
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
    position: relative;
}
.cr-job-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 28px rgba(var(--primary-rgb), .16);
}
.cr-job-card.cr-closed {
    border-left-color: var(--text-muted);
    opacity: .75;
}
.cr-job-card.cr-urgent { border-left-color: var(--secondary-color); }

/* Urgent ribbon */
.cr-urgent-tag {
    position: absolute; top: 0; right: 0;
    background: linear-gradient(135deg, var(--secondary-color), var(--secondary-dark));
    color: var(--text-on-secondary); font-size: .68rem; font-weight: 800;
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
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 12%, white), color-mix(in srgb, var(--primary-color) 24%, white));
    display: flex; align-items: center; justify-content: center;
    color: var(--primary-color); font-size: 1.2rem;
}
.cr-job-card.cr-closed .cr-dept-avatar { background: color-mix(in srgb, var(--text-muted) 14%, white); color: var(--text-muted); }
.cr-job-card.cr-urgent .cr-dept-avatar { background: linear-gradient(135deg, color-mix(in srgb, var(--secondary-color) 14%, white), color-mix(in srgb, var(--secondary-color) 28%, white)); color: var(--secondary-dark); }

.cr-card-heading { flex: 1; }
.cr-job-title {
    font-size: 1.05rem; font-weight: 700;
    color: var(--primary-dark); margin-bottom: .3rem; line-height: 1.3;
}
.cr-job-card.cr-closed .cr-job-title { text-decoration: line-through; color: var(--text-muted); }

.cr-badge-row { display: flex; gap: .4rem; flex-wrap: wrap; }
.cr-tag {
    font-size: .7rem; font-weight: 700;
    padding: .18em .65em; border-radius: 20px;
    text-transform: uppercase; letter-spacing: .3px;
}
.cr-tag.type  { background: color-mix(in srgb, var(--primary-color) 12%, white); color: var(--primary-dark); }
.cr-tag.dept  { background: color-mix(in srgb, var(--accent-color) 18%, white); color: var(--accent-dark); }
.cr-tag.open  { background: color-mix(in srgb, var(--accent-color) 28%, white); color: var(--accent-dark); }
.cr-tag.closed-tag { background: color-mix(in srgb, var(--text-muted) 16%, white); color: var(--text-muted); }
.cr-tag.urgent-tag { background: color-mix(in srgb, var(--secondary-color) 16%, white); color: var(--secondary-dark); }

/* Meta info chips */
.cr-meta {
    display: flex; flex-wrap: wrap; gap: .5rem 1.2rem;
    margin-bottom: .85rem;
}
.cr-meta-item {
    display: flex; align-items: center; gap: .4rem;
    font-size: .82rem; color: var(--text-color);
}
.cr-meta-item i {
    width: 14px; text-align: center;
    color: var(--text-muted); font-size: .8rem; flex-shrink: 0;
}
.cr-meta-item.deadline-near { color: var(--secondary-dark); font-weight: 600; }
.cr-meta-item.deadline-gone { color: var(--text-muted); }
.cr-meta-deadline-strong{margin-left:.3rem;}

/* Description */
.cr-desc {
    font-size: .85rem; color: var(--text-light);
    line-height: 1.6; margin-bottom: 1rem;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Actions */
.cr-actions {
    display: flex; gap: .5rem; flex-wrap: wrap;
    padding-top: .85rem;
    border-top: 1px solid color-mix(in srgb, var(--primary-color) 10%, white);
}
.cr-btn-detail {
    padding: .5rem 1.1rem; border-radius: 8px;
    font-size: .82rem; font-weight: 600;
    border: 1.5px solid var(--primary-color); color: var(--primary-color);
    background: transparent; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
    transition: all .2s;
}
.cr-btn-detail:hover { background: var(--primary-color); color: var(--text-on-primary); }
.cr-btn-apply {
    padding: .5rem 1.2rem; border-radius: 8px;
    font-size: .82rem; font-weight: 700;
    background: linear-gradient(135deg, var(--accent-dark), var(--primary-dark));
    color: var(--text-on-primary); border: none; cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
    transition: all .2s; box-shadow: 0 2px 8px rgba(var(--primary-rgb),.28);
}
.cr-btn-apply:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(var(--primary-rgb),.36); color: var(--text-on-primary); }
.cr-btn-dl {
    padding: .5rem .9rem; border-radius: 8px;
    font-size: .82rem; font-weight: 600;
    border: 1.5px solid color-mix(in srgb, var(--primary-color) 14%, white); color: var(--text-light);
    background: color-mix(in srgb, var(--primary-color) 6%, white); cursor: pointer;
    text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
    transition: all .2s;
}
.cr-btn-dl:hover { background: color-mix(in srgb, var(--primary-color) 12%, white); color: var(--text-color); }

/* Deadline progress bar */
.cr-deadline-bar {
    height: 3px; background: color-mix(in srgb, var(--primary-color) 12%, white);
    border-radius: 2px; margin-bottom: .7rem; overflow: hidden;
}
.cr-deadline-fill {
    height: 100%; border-radius: 2px;
    transition: width .5s;
}
.cr-deadline-fill.ok{background:var(--accent-dark);}
.cr-deadline-fill.near{background:var(--secondary-color);}
.cr-deadline-fill.gone{background:var(--text-muted);}

/* ── Empty / No results ── */
.cr-empty {
    text-align: center; padding: 3rem 1.5rem;
    background: var(--surface-color); border-radius: 14px;
    box-shadow: 0 2px 12px rgba(var(--primary-rgb),.08);
}
.cr-empty-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: color-mix(in srgb, var(--primary-color) 8%, white); display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem; font-size: 1.8rem; color: var(--text-muted);
}

/* ── Sidebar ── */
.cr-sb-card {
    border-radius: 14px;
    margin-bottom: 1.2rem;
    overflow: hidden;
    box-shadow: 0 2px 14px rgba(var(--primary-rgb),.1);
}
.cr-sb-card .cr-sb-head {
    padding: 1.2rem 1.4rem 1rem;
    border-bottom: 1px solid color-mix(in srgb, var(--text-on-primary) 20%, transparent);
}
.cr-sb-card .cr-sb-body { padding: 1.2rem 1.4rem; background: var(--surface-color); }

/* ── Dept filter chips (below existing chips) ── */
.cr-dept-chips {
    display: flex; gap: .4rem; flex-wrap: wrap;
    padding-top: .75rem; border-top: 1px solid color-mix(in srgb, var(--primary-color) 10%, white); margin-top: .75rem;
}
.cr-dept-chip {
    padding: .28rem .8rem; border-radius: 20px;
    font-size: .76rem; font-weight: 600; cursor: pointer;
    border: 1.5px solid color-mix(in srgb, var(--primary-color) 16%, white); background: color-mix(in srgb, var(--primary-color) 6%, white); color: var(--text-light);
    transition: all .18s; display: inline-flex; align-items: center; gap: .3rem;
}
.cr-dept-chip:hover   { border-color: var(--accent-dark); color: var(--accent-dark); background: color-mix(in srgb, var(--accent-color) 16%, white); }
.cr-dept-chip.active  { background: var(--accent-dark); color: var(--text-on-accent); border-color: var(--accent-dark); }
.cr-dept-label{font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.3px;display:flex;align-items:center;gap:.3rem;margin-right:.3rem;}

/* ── Sidebar on mobile: collapsible ── */
.cr-sidebar-toggle {
    display: none; width: 100%; background: var(--surface-color);
    border: 1.5px solid color-mix(in srgb, var(--primary-color) 12%, white); border-radius: 12px;
    padding: .75rem 1.1rem; font-size: .88rem; font-weight: 700;
    color: var(--primary-dark); cursor: pointer; margin-bottom: .75rem;
    text-align: left; align-items: center; gap: .5rem;
    box-shadow: 0 2px 10px rgba(var(--primary-rgb),.08);
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
.cr-track-card .cr-sb-head h4 { color: var(--text-on-primary); margin: 0 0 .3rem; font-size: 1rem; font-weight: 700; }
.cr-track-card .cr-sb-head p  { color: color-mix(in srgb, var(--text-on-primary) 68%, transparent); font-size: .84rem; margin: 0; }
.cr-track-card .cr-sb-body { background: var(--primary-color); border-top: 1px solid color-mix(in srgb, var(--text-on-primary) 14%, transparent); }
.cr-btn-track {
    display: flex; align-items: center; gap: .5rem;
    background: white; color: var(--primary-color);
    padding: .65rem 1.2rem; border-radius: 10px;
    font-weight: 700; font-size: .88rem; text-decoration: none;
    transition: all .2s; width: 100%; justify-content: center;
}
.cr-btn-track:hover { background: color-mix(in srgb, var(--primary-color) 12%, white); color: var(--primary-color); }

/* CV card */
.cr-cv-card { background: var(--surface-color); border: 1.5px solid color-mix(in srgb, var(--primary-color) 12%, white); }
.cr-cv-card .cr-sb-body { padding: 1.4rem; }
.cr-cv-icon {
    width: 48px; height: 48px; border-radius: 12px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 14%, white), color-mix(in srgb, var(--accent-color) 26%, white));
    display: flex; align-items: center; justify-content: center;
    color: var(--accent-dark); font-size: 1.3rem; margin-bottom: .85rem;
}
.cr-cv-card h4 { font-size: 1rem; font-weight: 700; color: var(--primary-dark); margin-bottom: .4rem; }
.cr-cv-card p  { font-size: .84rem; color: var(--text-light); margin-bottom: 1rem; }
.cr-btn-cv {
    display: flex; align-items: center; gap: .5rem;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--text-on-primary); padding: .65rem 1.2rem; border-radius: 10px;
    font-weight: 700; font-size: .88rem; text-decoration: none;
    transition: all .2s; width: 100%; justify-content: center;
}
.cr-btn-cv:hover { opacity: .9; color: var(--text-on-primary); }
.cr-muted{color:var(--text-muted)!important;}
.cr-btn-primary{background:var(--primary-color);border-color:var(--primary-color);color:var(--text-on-primary);}
.cr-btn-primary:hover{background:var(--primary-dark);border-color:var(--primary-dark);color:var(--text-on-primary);}
.cr-ico-accent{color:var(--accent-color)!important;}

/* Why join */
.cr-why-card { background: var(--surface-color); border: 1.5px solid color-mix(in srgb, var(--primary-color) 12%, white); }
.cr-why-card .cr-sb-body { padding: 1.4rem; }
.cr-why-card h4 { font-size: 1rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 1rem; }
.cr-benefits {
    display: flex; flex-direction: column; gap: .65rem;
}
.cr-benefit-item {
    display: flex; align-items: center; gap: .75rem;
    font-size: .87rem; color: var(--text-color);
}
.cr-benefit-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--accent-color) 30%, white), color-mix(in srgb, var(--accent-color) 44%, white));
    display: flex; align-items: center; justify-content: center;
    color: var(--accent-dark); font-size: .8rem; flex-shrink: 0;
}

/* No vacancy state */
.cr-novacancy {
    background: var(--surface-color); border-radius: 14px;
    padding: 3rem 2rem; text-align: center;
    box-shadow: 0 2px 14px rgba(var(--primary-rgb),.09);
    border: 1.5px solid color-mix(in srgb, var(--primary-color) 12%, white);
}
.cr-novacancy-icon {
    width: 90px; height: 90px; border-radius: 50%;
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 8%, white), color-mix(in srgb, var(--primary-color) 14%, white));
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.2rem; font-size: 2.2rem; color: var(--text-muted);
}
.cr-inline-dot{font-size:.55em;}
.cr-no-results{display:none;}
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
            <span class="cr-dept-label">
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
        $progressClass = $deadlinePassed ? 'gone' : (($daysLeft <= 7) ? 'near' : 'ok');
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
                        <span class="cr-tag open"><i class="fas fa-circle me-1 cr-inline-dot"></i><?php echo isEnglish() ? 'Open' : 'खुला'; ?></span>
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
                    <strong class="cr-meta-deadline-strong">(<?php echo isEnglish() ? $daysLeft.'d left' : $daysLeft.' दिन'; ?>)</strong>
                    <?php elseif ($deadlinePassed): ?>
                    <strong class="cr-meta-deadline-strong">(<?php echo isEnglish() ? 'Expired' : 'म्याद सकियो'; ?>)</strong>
                    <?php endif; ?>
                </span>
                <?php endif; ?>
            </div>

            <!-- Deadline progress bar -->
            <?php if (!empty($job['deadline'])): ?>
            <div class="cr-deadline-bar">
                <div class="cr-deadline-fill <?php echo $progressClass; ?>" style="width:<?php echo $deadlinePassed ? 0 : $progressPct; ?>%;"></div>
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
    <div id="crNoResults" class="cr-no-results">
        <div class="cr-empty">
            <div class="cr-empty-icon"><i class="fas fa-search"></i></div>
            <h5><?php echo isEnglish() ? 'No Matching Positions Found' : 'कुनै पद फेला परेन'; ?></h5>
            <p class="cr-muted small"><?php echo isEnglish() ? 'Try different keywords.' : 'अर्को शब्दले खोज्नुहोस्।'; ?></p>
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
        <p class="cr-muted mb-3">
            <?php echo isEnglish()
                ? 'No job openings at the moment. Please check back later or send your CV to our email.'
                : 'हाल कुनै पद रिक्त छैन। कृपया पछि फेरि जाँच गर्नुहोस् वा हाम्रो इमेलमा CV पठाउनुहोस्।'; ?>
        </p>
        <a href="mailto:<?php echo getSetting('email','info@sahakari.org.np'); ?>?subject=CV Submission"
           class="btn cr-btn-primary">
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
            <h4><i class="fas fa-star me-2 cr-ico-accent"></i><?php echo isEnglish() ? 'Why Join Us?' : 'हामीलाई किन रोज्ने?'; ?></h4>
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
