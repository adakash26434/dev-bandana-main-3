<?php
/**
 * Admin Dashboard — v10.3
 * Restored 2-tab layout (Office Dashboard + Smart Credential Manager)
 * + Sadasya Anurodh (member request) badge.
 */
require_once __DIR__ . '/_bootstrap.php';
$__t = static function (string $np, string $en): string {
    return isEnglish() ? $en : $np;
};

$pageTitle   = $__t('ड्यासबोर्ड', 'Dashboard');
$currentPage = 'dashboard';

require_once 'includes/admin-header.php';

/* Optional helper includes — fail-soft if absent */
@require_once __DIR__ . '/../includes/auth-roles.php';
@require_once __DIR__ . '/../includes/credentials-crypto.php';

/* PDO handle */
if (!isset($pdo) && isset($db)) { $pdo = $db; }
if (!isset($pdo) && function_exists('getDB')) { $pdo = getDB(); }

/* ── Stats — one round-trip when possible; per-table fallback if schema partial ── */
$stats = [
    'members'   => 0,
    'pending'   => 0, // KYC pending
    'kycDue'    => 0, // KYC due for risk review
    'loans'     => 0,
    'notices'   => 0,
    'requests'  => 0, // सदस्य अनुरोध (member registration pending)
    'pwResets'  => 0, // password reset pending
    'programAttend' => 0, // program attendance total
    'programUnique' => 0, // unique members attended
];
$statsBatchOk = false;
try {
    $row = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM members WHERE approval_status='approved') AS members,
            (SELECT COUNT(*) FROM kyc_applications WHERE status IN ('pending','incomplete')) AS pending,
            (SELECT COUNT(*) FROM kyc_applications WHERE status='approved' AND risk_review_status='due_review') AS kycDue,
            (SELECT COUNT(*) FROM loan_applications WHERE status='pending') AS loans,
            (SELECT COUNT(*) FROM notices WHERE is_active = 1) AS notices,
            (SELECT COUNT(*) FROM members WHERE approval_status='pending') AS requests,
            (SELECT COUNT(*) FROM member_password_reset_requests WHERE status='pending') AS pwResets,
            (SELECT COUNT(*) FROM member_program_attendance) AS programAttend,
            (SELECT COUNT(DISTINCT member_id) FROM member_program_attendance) AS programUnique"
    )->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $stats['members']        = (int)($row['members'] ?? 0);
        $stats['pending']        = (int)($row['pending'] ?? 0);
        $stats['kycDue']         = (int)($row['kycDue'] ?? 0);
        $stats['loans']          = (int)($row['loans'] ?? 0);
        $stats['notices']        = (int)($row['notices'] ?? 0);
        $stats['requests']       = (int)($row['requests'] ?? 0);
        $stats['pwResets']       = (int)($row['pwResets'] ?? 0);
        $stats['programAttend']  = (int)($row['programAttend'] ?? 0);
        $stats['programUnique']  = (int)($row['programUnique'] ?? 0);
        $statsBatchOk = true;
    }
} catch (Throwable $e) {
    error_log('[dashboard stats batch] ' . $e->getMessage());
}
if (!$statsBatchOk) {
    try { $stats['members']  = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE approval_status='approved'")->fetchColumn(); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
    try { $stats['pending']  = (int)$pdo->query("SELECT COUNT(*) FROM kyc_applications WHERE status IN ('pending','incomplete')")->fetchColumn(); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
    try { $stats['kycDue']   = (int)$pdo->query("SELECT COUNT(*) FROM kyc_applications WHERE status='approved' AND risk_review_status='due_review'")->fetchColumn(); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
    try { $stats['loans']    = (int)$pdo->query("SELECT COUNT(*) FROM loan_applications WHERE status='pending'")->fetchColumn(); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
    try { $stats['notices']  = (int)$pdo->query("SELECT COUNT(*) FROM notices WHERE is_active = 1")->fetchColumn(); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
    try { $stats['requests'] = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE approval_status='pending'")->fetchColumn(); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
    try { $stats['pwResets'] = (int)$pdo->query("SELECT COUNT(*) FROM member_password_reset_requests WHERE status='pending'")->fetchColumn(); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
    try { $stats['programAttend'] = (int)$pdo->query("SELECT COUNT(*) FROM member_program_attendance")->fetchColumn(); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
    try { $stats['programUnique'] = (int)$pdo->query("SELECT COUNT(DISTINCT member_id) FROM member_program_attendance")->fetchColumn(); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
}

$dashPendingAttendanceReq = 0;
$dashAttendRecent = [];
$dashAttendTopPrograms = [];
try {
    $dashPendingAttendanceReq = (int)$pdo->query("SELECT COUNT(*) FROM member_program_attendance_requests WHERE status='pending'")->fetchColumn();
} catch (Throwable $e) {
    error_log("[dashboard attend-req] " . $e->getMessage());
}
try {
    $dashAttendRecent = $pdo->query(
        "SELECT a.program_title, a.member_card_no, a.attended_at, COALESCE(NULLIF(m.name,''), '') AS member_name
         FROM member_program_attendance a
         LEFT JOIN members m ON m.id = a.member_id
         ORDER BY a.attended_at DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("[dashboard attend-recent] " . $e->getMessage());
}
try {
    $dashAttendTopPrograms = $pdo->query(
        "SELECT program_title, COUNT(*) AS c FROM member_program_attendance GROUP BY program_title ORDER BY c DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("[dashboard attend-top] " . $e->getMessage());
}

$sadasyaBadge = $stats['requests'] + $stats['pwResets'];

  /* ── Welfare Claims Stats ── */
  $welfarePending=0;$welfareReview=0;$welfareApproved=0;$welfareByType=[];$welfareRecent=[];
  try {
      $welfareRow = $pdo->query(
          "SELECT
              COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END), 0) AS c_pending,
              COALESCE(SUM(CASE WHEN status='under_review' THEN 1 ELSE 0 END), 0) AS c_review,
              COALESCE(SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END), 0) AS c_approved
           FROM member_welfare_claims"
      )->fetch(PDO::FETCH_ASSOC);
      if ($welfareRow) {
          $welfarePending  = (int)($welfareRow['c_pending'] ?? 0);
          $welfareReview   = (int)($welfareRow['c_review'] ?? 0);
          $welfareApproved = (int)($welfareRow['c_approved'] ?? 0);
      }
      $welfareByType = $pdo->query("SELECT claim_type,COUNT(*) AS total,
          SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_count,
          SUM(CASE WHEN status='under_review' THEN 1 ELSE 0 END) AS review_count,
          SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved_count,
          SUM(COALESCE(claim_amount,0)) AS total_amount
          FROM member_welfare_claims GROUP BY claim_type ORDER BY pending_count DESC,total DESC"
      )->fetchAll(PDO::FETCH_ASSOC);
      $welfareRecent=$pdo->query("SELECT id, member_name AS claimant_name, claim_type, status, claim_amount, created_at FROM member_welfare_claims ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { error_log("[dashboard welfare] ".$e->getMessage()); }
  $welfareBadge=$welfarePending+$welfareReview;
  $welfareClaimTypes=['maternity'=>['np'=>'सुत्केरी सुविधा','icon'=>'fa-baby','color'=>'var(--secondary-color)','bg'=>'color-mix(in srgb, var(--secondary-color) 12%, white)'],'death'=>['np'=>'मृत्यु सुविधा','icon'=>'fa-heart-broken','color'=>'var(--primary-dark)','bg'=>'color-mix(in srgb, var(--primary-dark) 10%, white)'],'insurance'=>['np'=>'बीमा दाबी','icon'=>'fa-shield-halved','color'=>'var(--secondary-color)','bg'=>'color-mix(in srgb, var(--secondary-color) 12%, white)'],'medical'=>['np'=>'उपचार खर्च','icon'=>'fa-hospital','color'=>'var(--primary-light)','bg'=>'color-mix(in srgb, var(--primary-light) 12%, white)'],'accident'=>['np'=>'दुर्घटना सुविधा','icon'=>'fa-triangle-exclamation','color'=>'var(--accent-color,#17a2b8)','bg'=>'color-mix(in srgb, var(--accent-color,#17a2b8) 12%, white)'],'other'=>['np'=>'अन्य सुविधा','icon'=>'fa-gift','color'=>'var(--primary-color)','bg'=>'color-mix(in srgb, var(--primary-color) 10%, white)']];

/* Recent activity */
$log = [];
try { $log = $pdo->query("SELECT action, created_at FROM admin_activity_log ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }

/* Smart Credential Manager — fetch credentials (staff+ can view) */
$creds = [];
$credsError = '';
try {
    $creds = $pdo->query(
        "SELECT id, site_name, site_url, site_logo, username, category
           FROM office_credentials
          WHERE is_active = 1
          ORDER BY category, sort_order, site_name"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $credsError = $__t('Smart Credential table अहिले उपलब्ध छैन — DB Setup चलाउनुहोस्।', 'Smart Credential table is not available now — run DB Setup.');
}

/* Pending member requests preview (for Sadasya Anurodh badge area) */
$pendingMembers = [];
try {
    $pendingMembers = $pdo->query(
        "SELECT id, name, phone, email, created_at
           FROM members
          WHERE approval_status='pending'
          ORDER BY created_at DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log("[dashboard] " . $e->getMessage()); }
?>

<style>
  .ds-card{border:1px solid color-mix(in srgb, var(--primary-color) 14%, #e5e7eb);border-radius:14px;background:white;padding:18px;
           display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;
           box-shadow:0 2px 8px rgba(var(--primary-rgb,26,95,42),0.08);transition:all .2s;height:100%;}
  .ds-card:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(var(--primary-rgb,26,95,42),0.12);
                 border-color:var(--primary-color);color:inherit;text-decoration:none;}
  .ds-icon{width:52px;height:52px;border-radius:12px;display:grid;place-items:center;
           font-size:1.4rem;color:var(--text-on-primary,white);flex-shrink:0;
           background:linear-gradient(135deg,var(--primary-color),var(--primary-light));}
  .ds-icon.warn{background:linear-gradient(135deg,var(--secondary-color),var(--secondary-dark,var(--secondary-color)));}
  .ds-icon.info{background:linear-gradient(135deg,var(--secondary-dark,var(--secondary-color)),var(--secondary-color));}
  .ds-icon.alt{background:linear-gradient(135deg,var(--secondary-color),var(--primary-light));}
  .ds-icon.danger{background:linear-gradient(135deg,var(--secondary-dark,var(--secondary-color)),var(--secondary-color));}
  .ds-val{font-size:1.7rem;font-weight:700;line-height:1;color:var(--text-color,#111827);}
  .ds-lbl{font-size:.82rem;color:var(--text-light,#6b7280);margin-top:4px;}
  .ds-section{background:white;border:1px solid color-mix(in srgb, var(--primary-color) 14%, #e5e7eb);border-radius:14px;padding:18px;margin-top:18px;}
  .ds-section h2{font-size:1.05rem;margin:0 0 14px;display:flex;align-items:center;gap:8px;color:var(--primary-color);}

  /* Tabs */
  .ds-tabs{display:flex;gap:6px;border-bottom:2px solid color-mix(in srgb, var(--primary-color) 14%, #e5e7eb);margin-bottom:18px;flex-wrap:wrap;}
  .ds-tab{padding:10px 18px;background:transparent;border:none;border-bottom:3px solid transparent;
          font-weight:600;font-size:.95rem;color:var(--text-light,#6b7280);cursor:pointer;transition:all .15s;
          display:inline-flex;align-items:center;gap:8px;margin-bottom:-2px;}
  .ds-tab:hover{color:var(--primary-color);}
  .ds-tab.active{color:var(--primary-color);border-bottom-color:var(--primary-color);}
  .ds-tab .badge-pill{background:var(--secondary-color);color:var(--text-on-secondary,var(--text-on-primary,white));border-radius:999px;font-size:.7rem;
                      padding:2px 8px;font-weight:700;line-height:1.2;}
  .ds-pane{display:none;}
  .ds-pane.active{display:block;}

  /* Credential cards */
  .cred-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;}
  .cred-card{border:1px solid color-mix(in srgb, var(--primary-color) 14%, #e5e7eb);border-radius:12px;padding:14px;background:white;
             transition:all .15s;display:flex;flex-direction:column;gap:8px;}
  .cred-card:hover{border-color:var(--primary-color);box-shadow:0 4px 12px rgba(var(--primary-rgb,26,95,42),.1);}
  .cred-head{display:flex;align-items:center;gap:10px;}
  .cred-logo{width:36px;height:36px;border-radius:8px;background:color-mix(in srgb, var(--primary-color) 10%, white);display:grid;
             place-items:center;overflow:hidden;flex-shrink:0;color:var(--text-light,#6b7280);}
  .cred-logo img{width:100%;height:100%;object-fit:contain;}
  .cred-name{font-weight:700;font-size:.95rem;color:var(--text-color,#111827);}
  .cred-cat{font-size:.7rem;color:var(--text-light,#6b7280);text-transform:uppercase;letter-spacing:.05em;}
  .cred-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:auto;}
  .cred-btn{flex:1;min-width:80px;padding:6px 10px;border-radius:8px;border:1px solid color-mix(in srgb, var(--primary-color) 14%, #e5e7eb);
            background:color-mix(in srgb, var(--primary-color) 8%, white);font-size:.8rem;font-weight:600;cursor:pointer;color:var(--text-color,#374151);
            display:inline-flex;align-items:center;justify-content:center;gap:5px;text-decoration:none;}
  .cred-btn:hover{background:var(--primary-color);color:var(--text-on-primary,white);border-color:var(--primary-color);}

  .wf-type-row{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid color-mix(in srgb, var(--primary-color) 10%, #f3f4f6);}.wf-type-row:last-child{border-bottom:none;}
    .wf-type-icon{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;font-size:1rem;flex-shrink:0;}
    .wf-type-name{flex-grow:1;font-size:.88rem;font-weight:600;color:var(--text-color,#111827);}
    .wf-badge{border-radius:999px;font-size:.72rem;font-weight:700;padding:2px 8px;display:inline-block;text-decoration:none;}
    .wf-badge.pending{background:color-mix(in srgb, var(--secondary-color) 16%, white);color:var(--secondary-dark,var(--secondary-color));}.wf-badge.review{background:color-mix(in srgb, var(--secondary-color) 12%, white);color:var(--secondary-dark,var(--secondary-color));}.wf-badge.approved{background:color-mix(in srgb, var(--primary-color) 16%, white);color:var(--primary-dark,var(--primary-color));}
    .wf-summary-bar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;}
    .wf-stat-chip{flex:1;min-width:90px;background:color-mix(in srgb, var(--primary-color) 8%, white);border:1px solid color-mix(in srgb, var(--primary-color) 14%, #e5e7eb);border-radius:10px;padding:10px 12px;text-align:center;text-decoration:none;color:inherit;}
    .wf-stat-chip .val{font-size:1.4rem;font-weight:700;line-height:1;}.wf-stat-chip .lbl{font-size:.72rem;color:var(--text-light,#6b7280);margin-top:2px;}
    /* Pending member list */
  .pm-row{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid color-mix(in srgb, var(--primary-color) 10%, #f3f4f6);}
  .pm-row:last-child{border-bottom:none;}
  .pm-avatar{width:38px;height:38px;border-radius:50%;background:color-mix(in srgb, var(--secondary-color) 16%, white);color:var(--secondary-dark,var(--secondary-color));
             display:grid;place-items:center;font-weight:700;flex-shrink:0;}
  .pm-info{flex-grow:1;min-width:0;}
  .pm-name{font-weight:600;font-size:.92rem;color:var(--text-color,#111827);}
  .pm-meta{font-size:.78rem;color:var(--text-light,#6b7280);}
</style>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="dash-title-main">
    <i class="fas fa-gauge"></i> <?php echo $__t('कार्यालय ड्यासबोर्ड', 'Office Dashboard'); ?>
  </h1>
  <div class="d-flex gap-2 flex-wrap">
    <a href="member-online-portal.php?status=pending" class="btn btn-outline-warning btn-sm position-relative">
      <i class="fas fa-user-clock"></i> <?php echo $__t('सदस्य अनुरोध', 'Member Requests'); ?>
      <?php if ($sadasyaBadge > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
          <?= $sadasyaBadge ?>
        </span>
      <?php endif; ?>
    </a>
    <a href="settings.php" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-cog"></i> <?php echo $__t('सेटिङ', 'Settings'); ?>
    </a>
  </div>
</div>

<!-- ===== TABS ===== -->
<div class="ds-tabs" role="tablist">
  <button type="button" class="ds-tab active" data-tab="office" role="tab">
    <i class="fas fa-gauge-high"></i> <?php echo $__t('कार्यालय ड्यासबोर्ड', 'Office Dashboard'); ?>
  </button>
  <button type="button" class="ds-tab" data-tab="creds" role="tab">
    <i class="fas fa-key"></i> <?php echo $__t('स्मार्ट क्रेडेन्सियल म्यानेजर', 'Smart Credential Manager'); ?>
  </button>
  <button type="button" class="ds-tab" data-tab="requests" role="tab">
    <i class="fas fa-user-clock"></i> <?php echo $__t('सदस्य अनुरोध', 'Member Requests'); ?>
    <?php if ($sadasyaBadge > 0): ?><span class="badge-pill"><?= $sadasyaBadge ?></span><?php endif; ?>
  </button>
  <button type="button" class="ds-tab" data-tab="welfare" role="tab">
    <i class="fas fa-hand-holding-heart"></i> <?php echo $__t('कल्याण दाबी', 'Welfare Claims'); ?>
    <?php if ($welfareBadge > 0): ?><span class="badge-pill"><?= $welfareBadge ?></span><?php endif; ?>
  </button>
  <button type="button" class="ds-tab" data-tab="programs" role="tab">
    <i class="fas fa-clipboard-check"></i> <?php echo $__t('कार्यक्रम उपस्थिति', 'Program Attendance'); ?>
    <?php if ($dashPendingAttendanceReq > 0): ?><span class="badge-pill"><?= (int)$dashPendingAttendanceReq ?></span><?php endif; ?>
  </button>
</div>

<!-- ===== TAB 1: OFFICE DASHBOARD ===== -->
<div class="ds-pane active" id="pane-office">
  <div class="row g-3">
    <div class="col-12 col-sm-6 col-lg-3">
      <a href="members.php" class="ds-card">
        <div class="ds-icon"><i class="fas fa-users"></i></div>
        <div><div class="ds-val"><?= $stats['members'] ?></div><div class="ds-lbl"><?php echo $__t('सक्रिय सदस्य', 'Active Members'); ?></div></div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <a href="kyc-applications.php?status=pending" class="ds-card">
        <div class="ds-icon warn"><i class="fas fa-id-card-clip"></i></div>
        <div><div class="ds-val"><?= $stats['pending'] ?></div><div class="ds-lbl"><?php echo $__t('पेन्डिङ/अपूर्ण KYC', 'Pending/Incomplete KYC'); ?></div></div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <a href="kyc-risk-reviews.php?filter=due" class="ds-card">
        <div class="ds-icon danger"><i class="fas fa-shield-halved"></i></div>
        <div><div class="ds-val"><?= $stats['kycDue'] ?></div><div class="ds-lbl">KYC Due for Review</div></div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <a href="loan-applications.php" class="ds-card">
        <div class="ds-icon info"><i class="fas fa-coins"></i></div>
        <div><div class="ds-val"><?= $stats['loans'] ?></div><div class="ds-lbl"><?php echo $__t('पेन्डिङ ऋण', 'Pending Loans'); ?></div></div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <a href="notices.php" class="ds-card">
        <div class="ds-icon alt"><i class="fas fa-bullhorn"></i></div>
        <div><div class="ds-val"><?= $stats['notices'] ?></div><div class="ds-lbl"><?php echo $__t('प्रकाशित सूचना', 'Published Notices'); ?></div></div>
      </a>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
      <a href="program-attendance.php" class="ds-card">
        <div class="ds-icon dash-icon-purple"><i class="fas fa-clipboard-check"></i></div>
        <div>
          <div class="ds-val"><?= $stats['programAttend'] ?></div>
          <div class="ds-lbl">Program Attendance</div>
          <div class="small text-muted">Unique: <?= $stats['programUnique'] ?></div>
        </div>
      </a>
    </div>
  </div>

  <div class="ds-section">
    <h2><i class="fas fa-bolt"></i> <?php echo $__t('छिटो कार्यहरू', 'Quick Actions'); ?></h2>
    <div class="d-flex flex-wrap gap-2">
      <a href="kyc-applications.php" class="btn btn-success btn-sm"><i class="fas fa-id-card-clip"></i> <?php echo $__t('KYC आवेदन', 'KYC Applications'); ?></a>
      <a href="members.php" class="btn btn-outline-success btn-sm"><i class="fas fa-user-plus"></i> <?php echo $__t('सदस्य', 'Members'); ?></a>
      <a href="notices.php" class="btn btn-outline-success btn-sm"><i class="fas fa-bullhorn"></i> <?php echo $__t('सूचना', 'Notices'); ?></a>
      <a href="loan-applications.php" class="btn btn-outline-success btn-sm"><i class="fas fa-coins"></i> <?php echo $__t('ऋण', 'Loans'); ?></a>
      <a href="reports.php" class="btn btn-outline-success btn-sm"><i class="fas fa-chart-line"></i> <?php echo $__t('रिपोर्ट', 'Reports'); ?></a>
      <a href="programs.php" class="btn btn-outline-success btn-sm"><i class="fas fa-calendar-plus"></i> <?php echo $__t('कार्यक्रम', 'Programs'); ?></a>
      <a href="program-attendance.php" class="btn btn-outline-success btn-sm"><i class="fas fa-clipboard-check"></i> <?php echo $__t('उपस्थिति रिपोर्ट', 'Attendance Report'); ?></a>
      <a href="../verify.php" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-shield-halved"></i> <?php echo $__t('सदस्य प्रमाणीकरण', 'Member Verify'); ?></a>
    </div>
  </div>

  <div class="ds-section">
    <h2><i class="fas fa-clock-rotate-left"></i> <?php echo $__t('हालैको गतिविधि', 'Recent Activity'); ?></h2>
    <?php if (empty($log)): ?>
      <div class="text-center py-4 dash-muted-block">
        <div class="dash-empty-icon-lg"><i class="fas fa-inbox"></i></div>
        <div><?php echo $__t('हाल कुनै गतिविधि छैन।', 'No recent activity.'); ?></div>
      </div>
    <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($log as $l): ?>
          <div class="list-group-item d-flex align-items-center gap-3 px-0">
            <div class="dash-log-icon">
              <i class="fas fa-check"></i>
            </div>
            <div class="flex-grow-1">
              <div class="dash-log-title"><?= htmlspecialchars($l['action'] ?? '') ?></div>
              <div class="dash-log-time">
                <?= !empty($l['created_at']) ? date('Y-m-d H:i', strtotime($l['created_at'])) : '' ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===== TAB 2: SMART CREDENTIAL MANAGER ===== -->
<div class="ds-pane" id="pane-creds">
  <div class="ds-section ds-no-top-gap">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h2 class="dash-section-title"><i class="fas fa-key"></i> <?php echo $__t('स्मार्ट क्रेडेन्सियल म्यानेजर', 'Smart Credential Manager'); ?></h2>
      <a href="credentials.php" class="btn btn-success btn-sm">
        <i class="fas fa-arrow-up-right-from-square"></i> <?php echo $__t('पूरै पेज खोल्नुहोस्', 'Open Full Page'); ?>
      </a>
    </div>

    <?php if ($credsError): ?>
      <div class="alert alert-warning mb-3"><i class="fas fa-triangle-exclamation"></i> <?= $credsError ?></div>
    <?php elseif (empty($creds)): ?>
      <div class="text-center py-5 dash-muted-block">
        <div class="dash-empty-icon-xl"><i class="fas fa-key"></i></div>
        <div><?php echo $__t('अहिलेसम्म कुनै credential save गरिएको छैन।', 'No credentials saved yet.'); ?></div>
        <a href="credentials.php" class="btn btn-success btn-sm mt-3">
          <i class="fas fa-plus"></i> <?php echo $__t('नयाँ Credential थप्नुहोस्', 'Add New Credential'); ?>
        </a>
      </div>
    <?php else: ?>
      <div class="cred-grid">
        <?php foreach ($creds as $c): ?>
          <div class="cred-card">
            <div class="cred-head">
              <div class="cred-logo">
                <?php if (!empty($c['site_logo'])): ?>
                  <img src="<?= htmlspecialchars($c['site_logo']) ?>" alt="">
                <?php else: ?>
                  <i class="fas fa-globe"></i>
                <?php endif; ?>
              </div>
              <div class="dash-flex-grow">
                <div class="cred-name text-truncate" title="<?= htmlspecialchars($c['site_name']) ?>">
                  <?= htmlspecialchars($c['site_name']) ?>
                </div>
                <div class="cred-cat"><?= htmlspecialchars($c['category'] ?: 'general') ?></div>
              </div>
            </div>
            <div class="text-truncate dash-meta-sm">
              <i class="fas fa-user"></i> <?= htmlspecialchars($c['username']) ?>
            </div>
            <div class="cred-actions">
              <a href="<?= htmlspecialchars($c['site_url']) ?>" target="_blank" rel="noopener" class="cred-btn">
                <i class="fas fa-up-right-from-square"></i> <?php echo $__t('खोल्नुहोस्', 'Open'); ?>
              </a>
              <a href="credentials.php#cred-<?= (int)$c['id'] ?>" class="cred-btn">
                <i class="fas fa-eye"></i> <?php echo $__t('विवरण', 'Details'); ?>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===== TAB 3: सदस्य अनुरोध ===== -->
<div class="ds-pane" id="pane-requests">
  <div class="ds-section ds-no-top-gap">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h2 class="dash-section-title"><i class="fas fa-user-clock"></i> <?php echo $__t('सदस्य अनुरोध', 'Member Requests'); ?></h2>
      <a href="member-online-portal.php?status=pending" class="btn btn-warning btn-sm">
        <i class="fas fa-list"></i> <?php echo $__t('सबै हेर्नुहोस्', 'View All'); ?>
      </a>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-6 col-md-4">
        <a href="member-online-portal.php?status=pending" class="ds-card" title="<?php echo $__t('पेन्डिङ दर्ता अनुरोध हेर्नुहोस्', 'View pending registration requests'); ?>">
          <div class="ds-icon warn"><i class="fas fa-user-plus"></i></div>
          <div><div class="ds-val"><?= $stats['requests'] ?></div><div class="ds-lbl"><?php echo $__t('पेन्डिङ दर्ता', 'Pending Registrations'); ?></div></div>
        </a>
      </div>
      <div class="col-6 col-md-4">
        <a href="member-online-portal.php?tab=resets" class="ds-card" title="<?php echo $__t('Password reset अनुरोध हेर्नुहोस्', 'View password reset requests'); ?>">
          <div class="ds-icon danger"><i class="fas fa-key"></i></div>
          <div><div class="ds-val"><?= $stats['pwResets'] ?></div><div class="ds-lbl"><?php echo $__t('Password Reset', 'Password Reset'); ?></div></div>
        </a>
      </div>
    </div>

    <h2 class="dash-subtitle-row">
      <i class="fas fa-clock"></i> <?php echo $__t('हालैका दर्ता अनुरोधहरू', 'Recent Registration Requests'); ?>
    </h2>
    <?php if (empty($pendingMembers)): ?>
      <div class="text-center py-4 dash-muted-block">
        <div class="dash-empty-icon-lg"><i class="fas fa-circle-check"></i></div>
        <div><?php echo $__t('कुनै पेन्डिङ अनुरोध छैन।', 'No pending requests.'); ?></div>
      </div>
    <?php else: ?>
      <?php foreach ($pendingMembers as $m): ?>
        <div class="pm-row">
          <div class="pm-avatar">
            <?php
            $pmName = trim((string)($m['name'] ?? $m['full_name'] ?? $m['full_name_np'] ?? ''));
            $pmInitial = $pmName !== '' ? mb_substr($pmName, 0, 1, 'UTF-8') : '?';
            ?>
            <?= htmlspecialchars($pmInitial) ?>
          </div>
          <div class="pm-info">
            <div class="pm-name"><?= htmlspecialchars($pmName !== '' ? $pmName : '?') ?></div>
            <div class="pm-meta">
              <i class="fas fa-mobile-screen-button"></i> <?= htmlspecialchars($m['phone'] ?? '—') ?>
              &nbsp;·&nbsp;
              <?= !empty($m['created_at']) ? date('Y-m-d H:i', strtotime($m['created_at'])) : '' ?>
            </div>
          </div>
          <a href="member-online-portal.php?view=<?= (int)$m['id'] ?>" class="btn btn-sm btn-outline-success">
            <i class="fas fa-eye"></i> <?php echo $__t('हेर्नुहोस्', 'View'); ?>
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ===== TAB: कल्याण दाबी (pane-welfare — URL #welfare) ===== -->
<div class="ds-pane" id="pane-welfare">
  <div class="ds-section ds-no-top-gap">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h2 class="dash-section-title"><i class="fas fa-hand-holding-heart dash-heart-icon"></i> <?php echo $__t('कल्याण दाबी व्यवस्थापन', 'Welfare Claims Management'); ?></h2>
      <a href="welfare-claims.php" class="btn btn-sm btn-outline-success"><i class="fas fa-arrow-up-right-from-square"></i> <?php echo $__t('सबै दाबी', 'All Claims'); ?></a>
    </div>
    <div class="wf-summary-bar">
      <a href="welfare-claims.php?status=pending" class="wf-stat-chip"><div class="val dash-val-pending"><?= $welfarePending ?></div><div class="lbl"><?php echo $__t('पेन्डिङ', 'Pending'); ?></div></a>
      <a href="welfare-claims.php?status=under_review" class="wf-stat-chip"><div class="val dash-val-review"><?= $welfareReview ?></div><div class="lbl"><?php echo $__t('समीक्षाधीन', 'Under Review'); ?></div></a>
      <a href="welfare-claims.php?status=approved" class="wf-stat-chip"><div class="val dash-val-approved"><?= $welfareApproved ?></div><div class="lbl"><?php echo $__t('स्वीकृत', 'Approved'); ?></div></a>
    </div>
    <?php if (empty($welfareByType)): ?>
      <div class="text-center py-3 dash-empty-note"><?php echo $__t('कुनै दाबी छैन।', 'No claims found.'); ?></div>
    <?php else: ?>
      <?php foreach ($welfareByType as $wt): $tk = $wt['claim_type']; $tm = $welfareClaimTypes[$tk] ?? ['np' => $tk, 'icon' => 'fa-circle', 'color' => 'var(--text-light)', 'bg' => 'color-mix(in srgb, var(--primary-color) 8%, white)']; ?>
        <div class="wf-type-row">
          <div class="wf-type-icon dash-wf-type-icon" data-bg="<?= htmlspecialchars($tm['bg'] ?? 'color-mix(in srgb, var(--primary-color) 8%, white)', ENT_QUOTES, 'UTF-8') ?>" data-color="<?= htmlspecialchars($tm['color'] ?? 'var(--text-light)', ENT_QUOTES, 'UTF-8') ?>"><i class="fas <?= $tm['icon'] ?>"></i></div>
          <div class="wf-type-name"><?= htmlspecialchars($tm['np']) ?><div class="dash-type-total"><?= (int)($wt['total'] ?? 0) ?> <?php echo $__t('दाबी', 'claims'); ?></div></div>
          <div class="d-flex gap-1 flex-wrap">
            <?php if ((int)($wt['pending_count'] ?? 0) > 0): ?><a href="welfare-claims.php?type=<?= htmlspecialchars($tk, ENT_QUOTES, 'UTF-8') ?>&status=pending" class="wf-badge pending"><?= (int)$wt['pending_count'] ?> <?php echo $__t('पेन्डिङ', 'Pending'); ?></a><?php endif; ?>
            <?php if ((int)($wt['review_count'] ?? 0) > 0): ?><a href="welfare-claims.php?type=<?= htmlspecialchars($tk, ENT_QUOTES, 'UTF-8') ?>&status=under_review" class="wf-badge review"><?= (int)$wt['review_count'] ?> <?php echo $__t('समीक्षा', 'Review'); ?></a><?php endif; ?>
            <?php if ((int)($wt['approved_count'] ?? 0) > 0): ?><span class="wf-badge approved"><?= (int)$wt['approved_count'] ?> <?php echo $__t('स्वीकृत', 'Approved'); ?></span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <h3 class="dash-subtitle-row dash-subtitle-lg"><i class="fas fa-list"></i> <?php echo $__t('पछिल्ला दाबीहरू', 'Recent Claims'); ?></h3>
    <?php if (empty($welfareRecent)): ?>
      <div class="text-center py-3 dash-empty-note-lg"><?php echo $__t('अहिलेसम्म कुनै दाबी रेकर्ड छैन।', 'No claim records yet.'); ?></div>
    <?php else: ?>
      <div class="table-responsive border rounded">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light"><tr><th><?php echo $__t('दाबीकर्ता', 'Claimant'); ?></th><th><?php echo $__t('प्रकार', 'Type'); ?></th><th><?php echo $__t('स्थिति', 'Status'); ?></th><th><?php echo $__t('रकम', 'Amount'); ?></th><th><?php echo $__t('मिति', 'Date'); ?></th><th></th></tr></thead>
          <tbody>
            <?php foreach ($welfareRecent as $wr): $wtk = (string)($wr['claim_type'] ?? 'other'); $wtm = $welfareClaimTypes[$wtk] ?? ['np' => $wtk]; ?>
              <tr>
                <td><?= htmlspecialchars((string)($wr['claimant_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($wtm['np'] ?? $wtk, ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars((string)($wr['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><?= $wr['claim_amount'] !== null && $wr['claim_amount'] !== '' ? htmlspecialchars((string)$wr['claim_amount'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td class="small text-muted"><?= !empty($wr['created_at']) ? htmlspecialchars(date('Y-m-d H:i', strtotime((string)$wr['created_at'])), ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><a class="btn btn-sm btn-outline-success" href="welfare-claims.php?action=view&id=<?= (int)($wr['id'] ?? 0) ?>"><i class="fas fa-eye"></i></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ===== TAB: कार्यक्रम उपस्थिति ===== -->
<div class="ds-pane" id="pane-programs">
  <div class="ds-section ds-no-top-gap">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h2 class="dash-section-title"><i class="fas fa-clipboard-check"></i> <?php echo $__t('कार्यक्रम उपस्थिति', 'Program Attendance'); ?></h2>
      <div class="d-flex flex-wrap gap-2">
        <a href="program-attendance.php" class="btn btn-success btn-sm"><i class="fas fa-table"></i> <?php echo $__t('पूरै रिपोर्ट', 'Full Report'); ?></a>
        <a href="programs.php" class="btn btn-outline-success btn-sm"><i class="fas fa-calendar-plus"></i> <?php echo $__t('कार्यक्रम व्यवस्थापन', 'Program Management'); ?></a>
      </div>
    </div>
    <p class="small text-muted mb-3"><?php echo $__t('कुल रेकर्ड, खोज/मिति फिल्टर, पृष्ठ-पृष्ठ हेर्ने र Excel निर्यात उपस्थिति रिपोर्ट पृष्ठमा छन्।', 'Total records, search/date filters, pagination, and Excel export are available on the attendance report page.'); ?> <a href="program-attendance.php"><?php echo $__t('उपस्थिति रिपोर्ट', 'Attendance Report'); ?></a></p>
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4">
        <a href="program-attendance.php" class="ds-card">
          <div class="ds-icon dash-icon-purple"><i class="fas fa-user-check"></i></div>
          <div><div class="ds-val"><?= (int)$stats['programAttend'] ?></div><div class="ds-lbl"><?php echo $__t('कुल उपस्थिति रेकर्ड', 'Total Attendance Records'); ?></div></div>
        </a>
      </div>
      <div class="col-6 col-md-4">
        <a href="program-attendance.php" class="ds-card">
          <div class="ds-icon info"><i class="fas fa-users"></i></div>
          <div><div class="ds-val"><?= (int)$stats['programUnique'] ?></div><div class="ds-lbl"><?php echo $__t('भिन्न सदस्य (member_id)', 'Unique Members (member_id)'); ?></div></div>
        </a>
      </div>
      <div class="col-6 col-md-4">
        <a href="program-attendance.php" class="ds-card">
          <div class="ds-icon warn"><i class="fas fa-hourglass-half"></i></div>
          <div><div class="ds-val"><?= (int)$dashPendingAttendanceReq ?></div><div class="ds-lbl"><?php echo $__t('उपस्थिति अनुरोध पेन्डिङ', 'Pending Attendance Requests'); ?></div></div>
        </a>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-lg-6">
        <h3 class="dash-subtitle-row dash-subtitle-tight"><i class="fas fa-chart-bar"></i> <?php echo $__t('बढी उपस्थिति भएका कार्यक्रम', 'Top Programs by Attendance'); ?></h3>
        <?php if (empty($dashAttendTopPrograms)): ?>
          <div class="text-center py-4 dash-empty-note-md"><?php echo $__t('अहिलेसम्म कुनै उपस्थिति रेकर्ड छैन।', 'No attendance records yet.'); ?></div>
        <?php else: ?>
          <div class="list-group list-group-flush border rounded overflow-hidden">
            <?php foreach ($dashAttendTopPrograms as $tp): ?>
              <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                <span class="text-truncate me-2" title="<?= htmlspecialchars((string)($tp['program_title'] ?? '')) ?>"><?= htmlspecialchars((string)($tp['program_title'] ?? '')) ?></span>
                <span class="badge bg-primary rounded-pill"><?= (int)($tp['c'] ?? 0) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="col-lg-6">
        <h3 class="dash-subtitle-row dash-subtitle-tight"><i class="fas fa-clock"></i> <?php echo $__t('पछिल्लो उपस्थिति', 'Recent Attendance'); ?></h3>
        <?php if (empty($dashAttendRecent)): ?>
          <div class="text-center py-4 dash-empty-note-md"><?php echo $__t('डाटा छैन।', 'No data.'); ?></div>
        <?php else: ?>
          <div class="list-group list-group-flush border rounded overflow-hidden">
            <?php foreach ($dashAttendRecent as $ar): ?>
              <div class="list-group-item py-2 px-3">
                <div class="text-truncate dash-program-title"><?= htmlspecialchars((string)($ar['program_title'] ?? '')) ?></div>
                <div class="dash-program-meta">
                  <?= htmlspecialchars(trim((string)($ar['member_name'] ?? '') ?: ((string)($ar['member_card_no'] ?? '') ?: '—'))) ?>
                  &nbsp;·&nbsp;
                  <?= !empty($ar['attended_at']) ? htmlspecialchars(date('Y-m-d H:i', strtotime((string)$ar['attended_at']))) : '' ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  const tabs  = document.querySelectorAll('.ds-tab');
  const panes = document.querySelectorAll('.ds-pane');
  const tabIds = ['office','creds','requests','welfare','programs'];
  function activate(name){
    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
    panes.forEach(p => p.classList.toggle('active', p.id === 'pane-' + name));
    try { localStorage.setItem('aks_dash_tab', name); } catch(e) {}
    if (history.replaceState) history.replaceState(null, '', '#' + name);
  }
  tabs.forEach(t => t.addEventListener('click', () => activate(t.dataset.tab)));
  // Restore last tab from URL hash or localStorage
  const fromHash = (location.hash || '').replace('#','');
  const fromLS   = (function(){ try { return localStorage.getItem('aks_dash_tab') || ''; } catch(e){ return ''; } })();
  const initial  = tabIds.includes(fromHash) ? fromHash
                  : (tabIds.includes(fromLS) ? fromLS : 'office');
  if (initial !== 'office') activate(initial);
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.dash-wf-type-icon[data-bg][data-color]').forEach(function (el) {
    var bg = (el.getAttribute('data-bg') || '').trim();
    var color = (el.getAttribute('data-color') || '').trim();
    if (bg) el.style.backgroundColor = bg;
    if (color) el.style.color = color;
  });
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>
