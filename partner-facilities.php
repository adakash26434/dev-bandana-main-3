<?php
/**
 * Public: साझेदार सुविधाहरू — Partner Facilities
 * Table view with filter by सुविधा प्रकार
 */
require_once 'includes/config.php';
require_once 'includes/ensure-tables.php';
require_once 'includes/partner-facilities-tables.php';
$pageTitle = isEnglish() ? 'Partner Facilities' : 'साझेदार सुविधाहरू';
require_once 'includes/header.php';
$L = getLangStrings();

/* ── Load facilities ── */
$facilities = [];
$types      = [];
try {
    $db = getDB();
    ensurePartnerFacilitiesTables($db);

    $facilities = $db->query("SELECT * FROM partner_facilities WHERE is_active=1 ORDER BY display_order ASC, partner_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $types      = array_unique(array_filter(array_column($facilities, 'facility_type')));
    sort($types);
} catch (\Throwable $e) { $facilities = []; $types = []; }

$activeType = trim($_GET['type'] ?? '');
$filtered = $activeType
    ? array_filter($facilities, fn($f) => $f['facility_type'] === $activeType)
    : $facilities;
?>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1><?php echo isEnglish() ? 'Partner Facilities & Discounts' : 'साझेदार सुविधाहरू'; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>"><?php echo $L['home']; ?></a></li>
                <li class="breadcrumb-item active"><?php echo isEnglish() ? 'Partner Facilities' : 'साझेदार सुविधाहरू'; ?></li>
            </ol>
        </nav>
    </div>
</section>

<section class="section-padding">
<div class="container">

    <!-- Intro -->
    <div class="text-center mb-5">
        <div style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--primary-color),var(--primary-light));margin-bottom:16px;">
            <i class="fas fa-handshake" style="font-size:1.6rem;color:#fff;"></i>
        </div>
        <h2 style="font-size:1.7rem;font-weight:700;color:var(--primary-color);margin-bottom:8px;">
            <?php echo isEnglish() ? 'Member Benefits at Partner Organizations' : 'साझेदार संस्थामा सदस्यले पाउने सुविधाहरू'; ?>
        </h2>
        <p class="text-muted" style="max-width:600px;margin:0 auto;">
            <?php echo isEnglish()
                ? 'As a member of Aakash Cooperative, enjoy exclusive discounts and benefits at our partner organizations.'
                : 'आकाश सहकारीको सदस्यको रूपमा हाम्रा साझेदार संस्थाहरूमा विशेष छुट तथा सुविधाहरू प्राप्त गर्नुहोस्।'; ?>
        </p>
    </div>

    <?php if (empty($facilities)): ?>
    <div class="text-center py-5">
        <div style="font-size:4rem;color:#e9ecef;margin-bottom:16px;"><i class="fas fa-handshake"></i></div>
        <h4 style="color:#6c757d;"><?php echo isEnglish() ? 'Coming Soon' : 'छिट्टै आउँदैछ'; ?></h4>
        <p class="text-muted"><?php echo isEnglish() ? 'Partner facility details will be published soon.' : 'साझेदार सुविधाको विवरण छिट्टै प्रकाशित गरिनेछ।'; ?></p>
    </div>
    <?php else: ?>

    <!-- Type Filter Pills -->
    <?php if (!empty($types)): ?>
    <div class="pf-filter-wrap">
        <a href="partner-facilities.php" class="pf-filter-pill <?php echo !$activeType ? 'active' : ''; ?>">
            <i class="fas fa-th-large me-1"></i><?php echo isEnglish() ? 'All' : 'सबै'; ?>
            <span class="pf-pill-count"><?php echo count($facilities); ?></span>
        </a>
        <?php foreach ($types as $t):
            $cnt = count(array_filter($facilities, fn($f) => $f['facility_type'] === $t));
        ?>
        <a href="?type=<?php echo urlencode($t); ?>"
           class="pf-filter-pill <?php echo $activeType===$t ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($t); ?>
            <span class="pf-pill-count"><?php echo $cnt; ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Search bar -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div style="position:relative;max-width:320px;width:100%;">
            <i class="fas fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.85rem;"></i>
            <input type="text" id="pfSearch" placeholder="<?php echo isEnglish() ? 'Search...' : 'संस्था, स्थान, विवरण खोज्नुहोस्...'; ?>"
                   style="width:100%;padding:9px 12px 9px 34px;border:1px solid #d1fae5;border-radius:24px;font-size:.88rem;outline:none;background:#f0fdf4;"
                   oninput="pfSearchFn()">
        </div>
        <div id="pfCount" class="text-muted" style="font-size:.85rem;">
            <?php echo count($filtered); ?> <?php echo isEnglish() ? 'records' : 'रेकर्ड'; ?>
        </div>
    </div>

    <!-- Table -->
    <div class="pf-table-wrap">
        <table class="pf-table" id="pfTable">
            <thead>
                <tr>
                    <th style="width:50px;">क्र.स.</th>
                    <th><?php echo isEnglish() ? 'Partner Organization' : 'साझेदार संस्था'; ?></th>
                    <th><?php echo isEnglish() ? 'Location' : 'स्थान'; ?></th>
                    <th><?php echo isEnglish() ? 'Facility Type' : 'सुविधा प्रकार'; ?></th>
                    <th style="text-align:center;"><?php echo isEnglish() ? 'Discount' : 'छुट (%)'; ?></th>
                    <th><?php echo isEnglish() ? 'Details' : 'विवरण'; ?></th>
                </tr>
            </thead>
            <tbody id="pfTbody">
                <?php $sn = 1; foreach ($filtered as $f): ?>
                <tr>
                    <td style="color:#6b7280;font-weight:600;"><?php echo $sn++; ?></td>
                    <td>
                        <div class="pf-org-name"><?php echo htmlspecialchars($f['partner_name']); ?></div>
                    </td>
                    <td>
                        <span class="pf-location">
                            <i class="fas fa-location-dot"></i>
                            <?php echo htmlspecialchars($f['location'] ?: '—'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($f['facility_type']): ?>
                        <span class="pf-type-badge"><?php echo htmlspecialchars($f['facility_type']); ?></span>
                        <?php else: echo '<span style="color:#9ca3af;">—</span>'; endif; ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ($f['discount_percent'] > 0): ?>
                        <span class="pf-discount-badge">
                            <?php echo number_format($f['discount_percent'], 0); ?>% <small>छुट</small>
                        </span>
                        <?php else: ?>
                        <span style="color:#9ca3af;font-size:.82rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:#374151;font-size:.88rem;">
                        <?php echo nl2br(htmlspecialchars($f['description'] ?? '—')); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($filtered)): ?>
                <tr id="pfNoResult">
                    <td colspan="6" style="text-align:center;padding:40px;color:#6b7280;">
                        <i class="fas fa-search" style="font-size:2rem;opacity:.3;display:block;margin-bottom:8px;"></i>
                        कुनै रेकर्ड फेला परेन।
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Info note -->
    <div class="pf-note">
        <i class="fas fa-circle-info"></i>
        <?php echo isEnglish()
            ? 'To avail these discounts, please present your Aakash Cooperative member card at the partner organization.'
            : 'यी सुविधाहरू प्राप्त गर्न साझेदार संस्थामा आकाश सहकारीको सदस्य कार्ड देखाउनुहोस्।'; ?>
    </div>

    <?php endif; ?>

</div>
</section>

<style>
/* ── Partner Facilities Page Styles ── */
.pf-filter-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
}
.pf-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border-radius: 20px;
    background: #f0fdf4;
    color: #374151;
    text-decoration: none;
    font-size: .83rem;
    font-weight: 600;
    border: 1.5px solid #d1fae5;
    transition: all .15s;
}
.pf-filter-pill:hover, .pf-filter-pill.active {
    background: var(--primary-color);
    color: #fff;
    border-color: var(--primary-color);
}
.pf-pill-count {
    background: rgba(255,255,255,.25);
    border-radius: 10px;
    padding: 1px 7px;
    font-size: .75rem;
}
.pf-filter-pill.active .pf-pill-count { background: rgba(255,255,255,.3); }
.pf-filter-pill:not(.active) .pf-pill-count { background: #e5e7eb; color: #6b7280; }

.pf-table-wrap {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
    margin-bottom: 20px;
}
.pf-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
}
.pf-table thead tr {
    background: linear-gradient(135deg,var(--primary-color),var(--primary-light));
    color: #fff;
}
.pf-table th {
    padding: 13px 16px;
    font-size: .82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    white-space: nowrap;
}
.pf-table tbody tr {
    border-bottom: 1px solid #f3f4f6;
    transition: background .12s;
}
.pf-table tbody tr:last-child { border-bottom: none; }
.pf-table tbody tr:hover { background: #f0fdf4; }
.pf-table td { padding: 13px 16px; vertical-align: middle; font-size: .88rem; }

.pf-org-name {
    font-weight: 700;
    color: var(--primary-color);
    font-size: .92rem;
}
.pf-location {
    color: #6b7280;
    font-size: .84rem;
}
.pf-location i {
    color: var(--primary-color);
    margin-right: 4px;
    font-size: .78rem;
}
.pf-type-badge {
    background: #f0fdf4;
    color: var(--primary-color);
    border: 1px solid #bbf7d0;
    border-radius: 20px;
    padding: 3px 10px;
    font-size: .78rem;
    font-weight: 700;
}
.pf-discount-badge {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #fff;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: .85rem;
    font-weight: 800;
    display: inline-block;
}
.pf-discount-badge small { font-size: .72rem; font-weight: 600; opacity: .9; }

.pf-note {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
    padding: 12px 18px;
    font-size: .85rem;
    color: #1e40af;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.pf-note i { margin-top: 2px; flex-shrink: 0; }

@media (max-width: 768px) {
    .pf-table-wrap { overflow-x: auto; }
    .pf-table { min-width: 620px; }
}
</style>

<script>
function pfSearchFn() {
    const q   = document.getElementById('pfSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#pfTbody tr');
    let vis = 0;
    rows.forEach(r => {
        if (!r.id) {
            const show = !q || r.textContent.toLowerCase().includes(q);
            r.style.display = show ? '' : 'none';
            if (show) vis++;
        }
    });
    const noRes = document.getElementById('pfNoResult');
    if (noRes) noRes.style.display = vis ? 'none' : '';
    document.getElementById('pfCount').textContent = vis + ' रेकर्ड';
}
</script>

<?php require_once 'includes/footer.php'; ?>
