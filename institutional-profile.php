<?php
/**
 * Public Page: संस्थागत प्रोफाइल
 * File: institutional-profile.php
 *
 * Admin मा थपिएको financial data यहाँ public page मा देखाइन्छ।
 * Admin: admin/institutional-profile.php बाट manage गर्नुहोस्।
 */
require_once 'includes/config.php';
$pageTitle = isEnglish() ? 'Institutional Profile' : 'संस्थागत प्रोफाइल';
require_once 'includes/header.php';
$L = getLangStrings();

/* ─── Fetch active institutional profiles ─── */
$profiles = [];
$tableExists = false;
try {
    $db = getDB();
    $r = $db->query("SHOW TABLES LIKE 'institutional_profile'");
    $tableExists = ($r->rowCount() > 0);
    if ($tableExists) {
        $profiles = $db->query(
            "SELECT * FROM institutional_profile WHERE is_active = 1 ORDER BY fiscal_year DESC"
        )->fetchAll();
    }
} catch (Exception $e) {
    $profiles = [];
}

/* Helper: short amount display */
function ipShortAmt(float $v): string {
    if ($v >= 1e7) return 'रू. ' . number_format($v / 1e7, 2) . ' करोड';
    if ($v >= 1e5) return 'रू. ' . number_format($v / 1e5, 1) . ' लाख';
    if ($v > 0)    return 'रू. ' . number_format($v);
    return '—';
}
?>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1><?php echo isEnglish() ? 'Institutional Profile' : 'संस्थागत प्रोफाइल'; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>"><?php echo $L['home'] ?? 'गृहपृष्ठ'; ?></a></li>
                <li class="breadcrumb-item active"><?php echo isEnglish() ? 'Institutional Profile' : 'संस्थागत प्रोफाइल'; ?></li>
            </ol>
        </nav>
    </div>
</section>

<!-- Main Content -->
<section class="section-padding">
<div class="container">

<?php if (empty($profiles)): ?>
<!-- No data yet -->
<div class="text-center py-5">
    <div class="ip-empty-icon-wrap">
        <i class="fas fa-building-columns fa-2x" style="color:var(--primary-color);"></i>
    </div>
    <h4 style="color:var(--primary-color);">संस्थागत प्रोफाइल उपलब्ध छैन</h4>
    <p class="text-muted">छिट्टै उपलब्ध हुनेछ।</p>
</div>

<?php else: ?>

<!-- Section intro -->
<div class="text-center mb-5">
    <h2 style="color:var(--primary-color);font-weight:700;">संस्थाको आर्थिक प्रोफाइल</h2>
    <p class="text-muted" style="max-width:650px;margin:0 auto;">
        वार्षिक आर्थिक तथ्याङ्क — सदस्य संख्या, शेयर, बचत, ऋण र कुल सम्पत्तिको विवरण
    </p>
    <div style="width:60px;height:4px;background:linear-gradient(90deg,var(--primary-color),var(--primary-light));border-radius:2px;margin:16px auto 0;"></div>
</div>

<?php foreach ($profiles as $p): ?>
<!-- ── Profile Card for each fiscal year ── -->
<div class="ip-profile-card mb-5">

    <!-- Card Header -->
    <div class="ip-card-header">
        <div class="ip-fy-badge">
            <i class="fas fa-calendar-days me-2"></i>
            आ.व. <?php echo htmlspecialchars($p['fiscal_year']); ?>
        </div>
        <?php if (!empty($p['report_date_bs'])): ?>
        <div class="ip-date-info">
            <i class="fas fa-clock me-1"></i>
            <span style="opacity:0.82;font-size:0.88em;margin-right:4px;">प्रकाशित:</span>
            <?php echo htmlspecialchars($p['report_date_bs']); ?>
            <?php if (!empty($p['report_date_ad'])): ?>
            &nbsp;/&nbsp; <?php echo date('d M Y', strtotime($p['report_date_ad'])); ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Stats Grid -->
    <div class="ip-stats-grid">

        <!-- Members -->
        <div class="ip-stat-item ip-stat-primary">
            <div class="ip-stat-icon"><i class="fas fa-users"></i></div>
            <div class="ip-stat-body">
                <div class="ip-stat-value"><?php echo number_format((int)$p['total_members']); ?></div>
                <div class="ip-stat-label">कुल सदस्य</div>
                <?php if (!empty($p['total_balance_member'])): ?>
                <div class="ip-stat-sub"><?php echo number_format((int)$p['total_balance_member']); ?> शेष</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Total Assets -->
        <div class="ip-stat-item ip-stat-success">
            <div class="ip-stat-icon"><i class="fas fa-landmark"></i></div>
            <div class="ip-stat-body">
                <div class="ip-stat-value"><?php echo ipShortAmt((float)$p['total_assets']); ?></div>
                <div class="ip-stat-label">कुल सम्पत्ति</div>
            </div>
        </div>

        <!-- Share Capital -->
        <div class="ip-stat-item ip-stat-info">
            <div class="ip-stat-icon"><i class="fas fa-coins"></i></div>
            <div class="ip-stat-body">
                <div class="ip-stat-value"><?php echo ipShortAmt((float)$p['share_capital']); ?></div>
                <div class="ip-stat-label">शेयर पूँजी</div>
                <?php if (!empty($p['share_capital_percent'])): ?>
                <div class="ip-stat-sub"><?php echo $p['share_capital_percent']; ?>% वृद्धि</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Deposit/Savings -->
        <div class="ip-stat-item ip-stat-teal">
            <div class="ip-stat-icon"><i class="fas fa-piggy-bank"></i></div>
            <div class="ip-stat-body">
                <div class="ip-stat-value"><?php echo ipShortAmt((float)$p['deposit']); ?></div>
                <div class="ip-stat-label">कुल बचत</div>
                <?php if (!empty($p['deposit_percent'])): ?>
                <div class="ip-stat-sub"><?php echo $p['deposit_percent']; ?>% वृद्धि</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loan -->
        <div class="ip-stat-item ip-stat-warning">
            <div class="ip-stat-icon"><i class="fas fa-hand-holding-dollar"></i></div>
            <div class="ip-stat-body">
                <div class="ip-stat-value"><?php echo ipShortAmt((float)$p['loan']); ?></div>
                <div class="ip-stat-label">ऋण लगानी</div>
                <?php if (!empty($p['loan_percent'])): ?>
                <div class="ip-stat-sub"><?php echo $p['loan_percent']; ?>% वृद्धि</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reserved Fund -->
        <?php if (!empty($p['reserved_fund'])): ?>
        <div class="ip-stat-item ip-stat-purple">
            <div class="ip-stat-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="ip-stat-body">
                <div class="ip-stat-value"><?php echo ipShortAmt((float)$p['reserved_fund']); ?></div>
                <div class="ip-stat-label">जगेडा कोष</div>
                <?php if (!empty($p['reserved_fund_percent'])): ?>
                <div class="ip-stat-sub"><?php echo $p['reserved_fund_percent']; ?>% वृद्धि</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- .ip-stats-grid -->

    <!-- Indicators Row (NPA, NPL, Liquidity) -->
    <?php
    $hasIndicators = !empty($p['npa_percent']) || !empty($p['npl_percent']) || !empty($p['liquidity_percent']);
    if ($hasIndicators):
    ?>
    <div class="ip-indicators-row">
        <?php if (!empty($p['npa_percent'])): ?>
        <div class="ip-indicator">
            <div class="ip-ind-label">NPA (खराब ऋण)</div>
            <div class="ip-ind-bar-wrap">
                <?php
                $npa = (float)$p['npa_percent'];
                $npaClass = $npa < 3 ? 'bar-good' : ($npa < 5 ? 'bar-warning' : 'bar-danger');
                $barWidth = min($npa * 10, 100);
                ?>
                <div class="ip-ind-bar <?php echo $npaClass; ?>" style="width:<?php echo $barWidth; ?>%"></div>
            </div>
            <div class="ip-ind-value <?php echo $npaClass; ?>"><?php echo $npa; ?>%</div>
        </div>
        <?php endif; ?>

        <?php if (!empty($p['npl_percent'])): ?>
        <div class="ip-indicator">
            <div class="ip-ind-label">NPL</div>
            <div class="ip-ind-bar-wrap">
                <?php $npl = (float)$p['npl_percent']; $bw = min($npl * 10, 100); ?>
                <div class="ip-ind-bar bar-info" style="width:<?php echo $bw; ?>%"></div>
            </div>
            <div class="ip-ind-value ip-ind-value-info"><?php echo $npl; ?>%</div>
        </div>
        <?php endif; ?>

        <?php if (!empty($p['liquidity_percent'])): ?>
        <div class="ip-indicator">
            <div class="ip-ind-label">तरलता (Liquidity)</div>
            <div class="ip-ind-bar-wrap">
                <?php $liq = (float)$p['liquidity_percent']; $bw2 = min($liq, 100); ?>
                <div class="ip-ind-bar bar-teal" style="width:<?php echo $bw2; ?>%"></div>
            </div>
            <div class="ip-ind-value ip-ind-value-teal"><?php echo $liq; ?>%</div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Loan Reserve Fund -->
    <?php if (!empty($p['total_loan_reserve_fund'])): ?>
    <div class="ip-reserve-row">
        <i class="fas fa-vault me-2 text-success"></i>
        <strong>ऋण सुरक्षण कोष:</strong>
        <?php echo ipShortAmt((float)$p['total_loan_reserve_fund']); ?>
        <?php if (!empty($p['total_loan_reserve_percent'])): ?>
        <span class="ip-reserve-pct">(<?php echo $p['total_loan_reserve_percent']; ?>%)</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Note -->
    <?php if (!empty($p['report_note'])): ?>
    <div class="ip-note">
        <i class="fas fa-info-circle me-2 text-muted"></i>
        <?php echo nl2br(htmlspecialchars($p['report_note'])); ?>
    </div>
    <?php endif; ?>

</div><!-- .ip-profile-card -->
<?php endforeach; ?>

<?php endif; /* end profiles check */ ?>

</div><!-- .container -->
</section>

<style>
/* ── Institutional Profile Public Page Styles ── */

.ip-profile-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 26px rgba(var(--primary-rgb,26,95,42),0.10);
    overflow: hidden;
    border: 1px solid rgba(var(--primary-rgb,26,95,42),0.08);
}

.ip-card-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: var(--text-on-primary, #fff);
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.ip-fy-badge {
    font-size: 1.02rem;
    font-weight: 700;
    letter-spacing: 0.3px;
}

.ip-date-info {
    font-size: 0.82rem;
    opacity: 0.88;
}

/* Stats Grid */
.ip-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(168px, 1fr));
    gap: 0;
    border-bottom: 1px solid color-mix(in srgb, var(--primary-color) 10%, #f0f0f0);
}

.ip-stat-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 14px;
    border-right: 1px solid color-mix(in srgb, var(--primary-color) 10%, #f2f4f3);
    transition: background 0.2s;
}
.ip-stat-item:last-child { border-right: none; }
.ip-stat-item:hover { background: color-mix(in srgb, var(--primary-color) 4%, white); }

.ip-stat-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.ip-stat-primary .ip-stat-icon  { background:color-mix(in srgb, var(--primary-color) 16%, white); color:var(--primary-color); }
.ip-stat-success .ip-stat-icon  { background:color-mix(in srgb, var(--secondary-color) 12%, white); color:var(--secondary-color); }
.ip-stat-info .ip-stat-icon     { background:color-mix(in srgb, var(--primary-light) 14%, white); color:var(--primary-light); }
.ip-stat-teal .ip-stat-icon     { background:color-mix(in srgb, var(--primary-color) 10%, white); color:var(--primary-dark); }
.ip-stat-warning .ip-stat-icon  { background:color-mix(in srgb, var(--secondary-color) 14%, white); color:var(--secondary-dark, var(--secondary-color)); }
.ip-stat-purple .ip-stat-icon   { background:color-mix(in srgb, var(--primary-dark) 12%, white); color:var(--primary-dark); }

.ip-stat-value {
    font-size: 1.02rem;
    font-weight: 800;
    color: var(--text-color,#1a1a1a);
    line-height: 1.2;
}
.ip-stat-label {
    font-size: 0.75rem;
    color: var(--text-light,#777);
    margin-top: 3px;
    font-weight: 500;
}
.ip-stat-sub {
    font-size: 0.68rem;
    color: var(--primary-light);
    font-weight: 600;
    margin-top: 2px;
}

/* Indicators */
.ip-indicators-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0;
    background: color-mix(in srgb, var(--primary-color) 4%, white);
    border-top: 1px solid color-mix(in srgb, var(--primary-color) 14%, white);
    border-bottom: 1px solid color-mix(in srgb, var(--primary-color) 14%, white);
    padding: 12px 16px;
    gap: 18px;
}
.ip-indicator { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 180px; }
.ip-ind-label { font-size: 0.76rem; color: var(--text-light,#555); white-space: nowrap; min-width: 98px; }
.ip-ind-bar-wrap {
    flex: 1;
    height: 8px;
    background: color-mix(in srgb, var(--primary-color) 16%, #e0e0e0);
    border-radius: 4px;
    overflow: hidden;
    min-width: 80px;
}
.ip-ind-bar { height: 100%; border-radius: 4px; transition: width 0.6s ease; }
.bar-good    { background: linear-gradient(90deg,var(--primary-color),var(--primary-light)); }
.bar-warning { background: linear-gradient(90deg,var(--secondary-color),var(--accent-color)); }
.bar-danger  { background: linear-gradient(90deg,var(--secondary-color),var(--secondary-dark,var(--secondary-color))); }
.bar-info    { background: linear-gradient(90deg,var(--primary-light),var(--secondary-color)); }
.bar-teal    { background: linear-gradient(90deg,var(--primary-dark),var(--primary-color)); }
.ip-ind-value { font-size: 0.88rem; font-weight: 700; min-width: 42px; text-align: right; }
.bar-good ~ .ip-ind-value, .ip-ind-value.bar-good { color: var(--primary-dark,var(--primary-color)); }
.bar-danger ~ .ip-ind-value { color: var(--secondary-dark,var(--secondary-color)); }
.ip-ind-value-info { color: var(--accent-color,#1565c0); }
.ip-ind-value-teal { color: var(--primary-dark,#00796b); }

/* Loan reserve */
.ip-reserve-row {
    padding: 12px 16px;
    font-size: 0.84rem;
    color: var(--text-color,#444);
    border-bottom: 1px solid color-mix(in srgb, var(--primary-color) 10%, #f0f0f0);
    background: color-mix(in srgb, var(--primary-color) 5%, white);
}
.ip-reserve-pct {
    background:color-mix(in srgb, var(--primary-color) 16%, white);
    color:var(--primary-color);
    padding:2px 8px;
    border-radius:8px;
    font-size:0.8rem;
    font-weight:600;
    margin-left:6px;
}

/* Note */
.ip-note {
    padding: 12px 16px;
    font-size: 0.82rem;
    color: var(--text-light,#666);
    background: color-mix(in srgb, var(--primary-color) 6%, #fafafa);
}
.ip-empty-icon-wrap{width:80px;height:80px;background:color-mix(in srgb, var(--primary-color) 10%, white);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;}
    font-style: italic;
}

/* Responsive */
@media (max-width: 768px) {
    .ip-stats-grid { grid-template-columns: repeat(2, 1fr); }
    .ip-stat-item { border-right: 1px solid color-mix(in srgb, var(--primary-color) 10%, #f2f4f3); }
    .ip-stat-item:nth-child(2n) { border-right: none; }
    .ip-indicators-row { flex-direction: column; gap: 12px; }
    .ip-card-header { padding: 12px 14px; }
}
@media (max-width: 480px) {
    .ip-stats-grid { grid-template-columns: 1fr; }
    .ip-stat-item { border-right: none; border-bottom: 1px solid color-mix(in srgb, var(--primary-color) 10%, #f2f4f3); }
}
</style>

<?php require_once 'includes/footer.php'; ?>
