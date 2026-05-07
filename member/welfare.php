<?php
/**
 * Member Portal — कल्याण दाबी (Welfare Claims)
 * Submit new claims + track all existing claims with timeline
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/welfare-claims-tables.php';
require_once __DIR__ . '/../includes/welfare-claims-submit-helper.php';
requireMemberLogin();
memberSecurityHeaders();

$db  = getDB();
$mem = currentMember();
if (!$mem) { header('Location: login.php?msg=session_expired'); exit; }

$memberId = (int)$mem['id'];
$memEmail = trim((string)($mem['email'] ?? ''));
$memPhone = preg_replace('/[^0-9]/', '', (string)($mem['phone'] ?? ''));

/* KYC-linked profile priority */
$kycRow = null;
try {
    $kycLinkId = (int)($mem['kyc_application_id'] ?? 0);
    if ($kycLinkId > 0) {
        $ks = $db->prepare("SELECT * FROM kyc_applications WHERE id=? LIMIT 1");
        $ks->execute([$kycLinkId]);
        $kycRow = $ks->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$kycRow) {
        $kw = []; $kp = [];
        if ($memEmail) { $kw[] = 'LOWER(email)=?'; $kp[] = strtolower($memEmail); }
        if ($memPhone) { $kw[] = 'mobile=?'; $kp[] = $memPhone; }
        if (!empty($kw)) {
            $ks = $db->prepare("SELECT * FROM kyc_applications WHERE (" . implode(' OR ', $kw) . ") ORDER BY id DESC LIMIT 1");
            $ks->execute($kp);
            $kycRow = $ks->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }
} catch (Throwable $e) { $kycRow = null; }

/* Resolved identity */
$memName    = trim((string)($kycRow['full_name'] ?? $mem['name']    ?? ''));
$memSadasyata = trim((string)($kycRow['member_id'] ?? $mem['sadasyata_number'] ?? ''));
$resolvedPhone = $memPhone ?: preg_replace('/[^0-9]/', '', (string)($kycRow['mobile'] ?? ''));
$resolvedEmail = $memEmail ?: strtolower(trim((string)($kycRow['email'] ?? '')));
$resolvedAddress = trim((string)($kycRow['temporary_address'] ?? $kycRow['permanent_address'] ?? ''));

ensureWelfareClaimsTables($db);

/* ── Handle POST: new claim ── */
$successMsg = '';
$errorMsg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_claim') {
    if (!verifyCSRFToken()) {
        $errorMsg = 'सुरक्षा जाँच असफल। पुनः प्रयास गर्नुहोस्।';
    } elseif (!checkRateLimit('welfare_portal_' . $memberId, 5, 3600)) {
        $errorMsg = 'धेरै अनुरोधहरू भए। १ घण्टापछि पुनः प्रयास गर्नुहोस्।';
    } else {
        $claimType  = trim($_POST['claim_type'] ?? '');
        $desc       = trim(substr($_POST['description'] ?? '', 0, 4000));
        $beneName   = trim(substr($_POST['beneficiary_name'] ?? '', 0, 120));
        $beneRel    = trim(substr($_POST['beneficiary_relation'] ?? '', 0, 80));
        $claimAmt   = max(0, (float)($_POST['claim_amount'] ?? 0));
        $dcName     = trim(substr($_POST['deceased_name'] ?? '', 0, 120));
        $dcRel      = trim(substr($_POST['deceased_relation'] ?? '', 0, 80));
        $deathDate  = trim($_POST['death_date'] ?? '') ?: null;
        $delivDate  = trim($_POST['delivery_date'] ?? '') ?: null;
        $hospName   = trim(substr($_POST['hospital_name'] ?? '', 0, 200));
        $disease    = trim(substr($_POST['disease_illness'] ?? '', 0, 500));
        $treatDate  = trim($_POST['treatment_date'] ?? '') ?: null;
        $hospClinic = trim(substr($_POST['hospital_clinic'] ?? '', 0, 200));
        $policyNo   = trim(substr($_POST['policy_number'] ?? '', 0, 80));
        $insurerNm  = trim(substr($_POST['insurer_name'] ?? '', 0, 150));

        $validTypes = ['maternity','death','insurance','medical','accident','other'];
        if (!in_array($claimType, $validTypes, true)) {
            $errorMsg = 'दाबी प्रकार छान्नुहोस्।';
        } else {
            try {
                $submit = submitWelfareClaimUnified($db, [
                    'member_name' => $memName,
                    'member_id' => $memSadasyata,
                    'member_portal_id' => $memberId,
                    'phone' => $resolvedPhone,
                    'email' => $resolvedEmail,
                    'address' => $resolvedAddress,
                    'claim_type' => $claimType,
                    'beneficiary_name' => $beneName,
                    'beneficiary_relation' => $beneRel,
                    'claim_amount' => $claimAmt,
                    'description' => $desc,
                    'deceased_name' => $dcName,
                    'deceased_relation' => $dcRel,
                    'death_date' => $deathDate,
                    'delivery_date' => $delivDate,
                    'hospital_name' => $hospName,
                    'disease_illness' => $disease,
                    'treatment_date' => $treatDate,
                    'hospital_clinic' => $hospClinic,
                    'policy_number' => $policyNo ?: null,
                    'insurer_name' => $insurerNm ?: null,
                ], $_FILES);
                $trackingId = $submit['tracking_id'];
                $successMsg = "दाबी सफलतापूर्वक दर्ता भयो! Tracking ID: <strong>$trackingId</strong> — Admin ले समीक्षा गरेपछि सूचित गरिनेछ।";
            } catch (Throwable $e) {
                $errorMsg = 'दाबी दर्ता गर्न समस्या भयो। पुनः प्रयास गर्नुहोस्।';
                error_log('[welfare portal] ' . $e->getMessage());
            }
        }
    }
}

/* ── Fetch this member's existing claims ── */
$myClaims = [];
try {
    $conds = ['member_portal_id=?'];
    $params = [$memberId];
    if ($resolvedPhone) { $conds[] = 'phone=?'; $params[] = $resolvedPhone; }
    if ($resolvedEmail) { $conds[] = 'LOWER(email)=?'; $params[] = strtolower($resolvedEmail); }
    $st = $db->prepare("SELECT * FROM member_welfare_claims WHERE " . implode(' OR ', $conds) . " ORDER BY created_at DESC LIMIT 50");
    $st->execute($params);
    $myClaims = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $myClaims = []; }

$siteName  = getSetting('site_name', 'सहकारी');
$pageTitle = 'कल्याण दाबी — ' . $siteName;
$activeTab = empty($myClaims) ? 'new' : ((!empty($_GET['new']) || !empty($successMsg)) ? 'new' : 'history');
$csrfField = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';

$statusLabels = [
    'pending'      => ['label' => 'पर्खाइमा',   'color' => '#d97706', 'bg' => '#fffbeb', 'icon' => 'fa-clock'],
    'under_review' => ['label' => 'समीक्षामा',  'color' => 'var(--secondary-color,#c0392b)', 'bg' => '#fef2f2', 'icon' => 'fa-magnifying-glass'],
    'approved'     => ['label' => 'स्वीकृत',    'color' => '#16a34a', 'bg' => '#f0fdf4', 'icon' => 'fa-circle-check'],
    'rejected'     => ['label' => 'अस्वीकृत',   'color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => 'fa-circle-xmark'],
    'paid'         => ['label' => 'भुक्तानी भयो','color' => 'var(--secondary-dark,#922b21)','bg' => '#fef2f2', 'icon' => 'fa-money-bill'],
    'completed'    => ['label' => 'सम्पन्न',     'color' => '#0f766e', 'bg' => '#f0fdfa', 'icon' => 'fa-flag-checkered'],
];
$extraHead = <<<HTML
<style>
.wf-tabs { display:flex; gap:0; border-bottom:2px solid var(--gray-100,#f3f4f6); margin-bottom:20px; }
.wf-tab  { padding:10px 20px; font-size:.9rem; font-weight:600; cursor:pointer; border:none; background:none;
           color:#6b7280; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .2s; }
.wf-tab.active { color:var(--primary-color,#1a8754); border-bottom-color:var(--primary-color,#1a8754); }
.wf-pane { display:none; }
.wf-pane.active { display:block; }
.claim-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:14px; }
.claim-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.08); }
.status-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:20px; font-size:.78rem; font-weight:700; }
.wf-timeline { display:flex; gap:0; align-items:center; margin:14px 0 4px; flex-wrap:wrap; gap:4px; }
.wf-tstep { display:flex; flex-direction:column; align-items:center; gap:3px; flex:1; min-width:60px; }
.wf-tdot  { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center;
            font-size:.7rem; border:2px solid #e5e7eb; background:#f9fafb; color:#9ca3af; }
.wf-tdot.done   { background:var(--primary-color,#1a8754); border-color:var(--primary-color,#1a8754); color:#fff; }
.wf-tdot.active { background:var(--secondary-color,#c0392b); border-color:var(--secondary-color,#c0392b); color:#fff; }
.wf-tdot.reject { background:#dc2626; border-color:#dc2626; color:#fff; }
.wf-tline { flex:1; height:2px; background:#e5e7eb; min-width:16px; }
.wf-tline.done { background:var(--primary-color,#1a8754); }
.wf-tlabel { font-size:.65rem; color:#9ca3af; text-align:center; }
.wf-tlabel.done   { color:var(--primary-color,#1a8754); font-weight:600; }
.wf-tlabel.active { color:var(--secondary-color,#c0392b); font-weight:700; }
.form-group { margin-bottom:14px; }
.form-group label { display:block; font-size:.82rem; font-weight:600; color:#374151; margin-bottom:5px; }
.form-control { width:100%; padding:10px 14px; min-height:44px; border:1.5px solid #d1d5db; border-radius:10px;
               font-family:inherit; font-size:.9rem; background:#f9fafb; transition:border-color .2s; line-height:1.4; }
.form-control:focus { outline:none; border-color:var(--primary-color,#1a8754); background:#fff; box-shadow:0 0 0 3px rgba(26,95,42,.12); }
.form-row { display:grid; gap:12px; }
.form-row.cols2 { grid-template-columns:1fr 1fr; }
@media(max-width:540px){ .form-row.cols2 { grid-template-columns:1fr; } }
.type-fields { display:none; }
.type-fields.show { display:block; }
.alert-success { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px 16px; color:#166534; font-size:.9rem; margin-bottom:16px; }
.alert-error   { background:#fef2f2; border:1px solid #fecaca; border-radius:10px; padding:14px 16px; color:#dc2626; font-size:.9rem; margin-bottom:16px; }
.track-id { font-family:monospace; font-weight:700; font-size:.9rem; background:#f3f4f6; padding:2px 8px; border-radius:6px; }
.empty-state { text-align:center; padding:40px 20px; color:#9ca3af; }
.empty-state i { font-size:3rem; display:block; margin-bottom:12px; }
.doc-upload { border:2px dashed #e5e7eb; border-radius:10px; padding:16px; text-align:center; cursor:pointer; transition:border .2s; }
.doc-upload:hover { border-color:var(--primary-color,#1a8754); }
</style>
HTML;
?>
<?php require __DIR__ . '/includes/chrome.php'; ?>

<main class="mp-main">
<div class="mp-container">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <h1 style="font-size:1.25rem;font-weight:700;color:var(--primary-color,#1a8754);margin:0;">
      <i class="fas fa-heart-pulse" style="margin-right:8px;"></i>कल्याण दाबी
    </h1>
    <div style="display:flex;gap:8px;">
      <a href="tracker.php?filter=welfare" style="font-size:.8rem;color:var(--primary-color,#1a8754);text-decoration:none;">
        <i class="fas fa-magnifying-glass-chart"></i> Tracker मा हेर्नुहोस्
      </a>
    </div>
  </div>

  <?php if ($successMsg): ?>
  <div class="alert-success"><i class="fas fa-circle-check" style="margin-right:8px;"></i><?= $successMsg ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
  <div class="alert-error"><i class="fas fa-circle-xmark" style="margin-right:8px;"></i><?= htmlspecialchars($errorMsg) ?></div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="wf-tabs">
    <button class="wf-tab <?= $activeTab==='history'?'active':'' ?>" onclick="showTab(this,'history')">
      <i class="fas fa-list" style="margin-right:6px;"></i>मेरा दाबीहरू (<?= count($myClaims) ?>)
    </button>
    <button class="wf-tab <?= $activeTab==='new'?'active':'' ?>" onclick="showTab(this,'new')" id="tabNew">
      <i class="fas fa-plus-circle" style="margin-right:6px;"></i>नयाँ दाबी
    </button>
  </div>

  <!-- Tab: History -->
  <div class="wf-pane <?= $activeTab==='history'?'active':'' ?>" id="pane-history">
    <?php if (empty($myClaims)): ?>
    <div class="empty-state">
      <i class="fas fa-heart"></i>
      <div style="font-size:1rem;font-weight:600;color:#6b7280;margin-bottom:6px;">कुनै दाबी छैन</div>
      <div style="font-size:.85rem;">नयाँ कल्याण दाबी दर्ता गर्न "नयाँ दाबी" tab खोल्नुहोस्।</div>
    </div>
    <?php else: ?>
    <?php foreach ($myClaims as $cl):
        $st = $cl['status'] ?? 'pending';
        $info = $statusLabels[$st] ?? $statusLabels['pending'];
        $tSteps = [
            ['key'=>'pending',       'label'=>'दर्ता'],
            ['key'=>'under_review',  'label'=>'समीक्षा'],
            ['key'=>'approved',      'label'=>'स्वीकृत'],
            ['key'=>'completed',     'label'=>'सम्पन्न'],
        ];
        $tOrder = ['pending'=>0,'under_review'=>1,'approved'=>2,'paid'=>3,'completed'=>4,'rejected'=>1];
        $curIdx = $tOrder[$st] ?? 0;
        $isRej  = ($st === 'rejected');
    ?>
    <div class="claim-card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div>
          <div style="font-size:1rem;font-weight:700;color:#1f2937;margin-bottom:4px;">
            <?= htmlspecialchars($cl['claim_type_np'] ?: $cl['claim_type']) ?>
          </div>
          <div class="track-id"><?= htmlspecialchars($cl['tracking_id'] ?? 'N/A') ?></div>
        </div>
        <span class="status-pill" style="background:<?= $info['bg'] ?>;color:<?= $info['color'] ?>;">
          <i class="fas <?= $info['icon'] ?>"></i> <?= $info['label'] ?>
        </span>
      </div>

      <!-- Timeline -->
      <div class="wf-timeline">
        <?php foreach ($tSteps as $ti => $ts):
            $tdone   = !$isRej && $curIdx > $ti;
            $tactive = !$isRej && $curIdx === $ti;
            $treject = $isRej && $ti === 1;
        ?>
        <?php if ($ti > 0): ?>
        <div class="wf-tline <?= $tdone?'done':'' ?>"></div>
        <?php endif; ?>
        <div class="wf-tstep">
          <div class="wf-tdot <?= $treject?'reject':($tdone?'done':($tactive?'active':'')) ?>">
            <?php if ($treject): ?><i class="fas fa-xmark"></i>
            <?php elseif($tdone): ?><i class="fas fa-check"></i>
            <?php elseif($tactive): ?><i class="fas fa-circle-dot"></i>
            <?php else: ?><?= $ti+1 ?><?php endif; ?>
          </div>
          <div class="wf-tlabel <?= $treject?'reject':($tdone?'done':($tactive?'active':'')) ?>"><?= $ts['label'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px;font-size:.82rem;color:#6b7280;">
        <?php if ($cl['claim_amount'] > 0): ?>
        <div><i class="fas fa-coins" style="margin-right:4px;color:#d97706;"></i>माग रकम: रू <?= number_format((float)$cl['claim_amount'],2) ?></div>
        <?php endif; ?>
        <?php if ($cl['approved_amount'] > 0): ?>
        <div><i class="fas fa-check-circle" style="margin-right:4px;color:#16a34a;"></i>स्वीकृत रकम: रू <?= number_format((float)$cl['approved_amount'],2) ?></div>
        <?php endif; ?>
        <div><i class="fas fa-calendar" style="margin-right:4px;"></i><?= date('Y-m-d', strtotime($cl['created_at'])) ?></div>
        <?php if ($cl['beneficiary_name']): ?>
        <div><i class="fas fa-user" style="margin-right:4px;"></i><?= htmlspecialchars($cl['beneficiary_name']) ?></div>
        <?php endif; ?>
      </div>
      <?php if ($cl['admin_remarks']): ?>
      <div style="margin-top:10px;padding:9px 12px;background:#f9fafb;border-radius:8px;font-size:.82rem;color:#374151;">
        <strong><i class="fas fa-comment" style="margin-right:5px;color:#6b7280;"></i>Admin टिप्पणी:</strong>
        <?= htmlspecialchars($cl['admin_remarks']) ?>
      </div>
      <?php endif; ?>
      <?php if ($cl['description']): ?>
      <div style="margin-top:8px;font-size:.82rem;color:#6b7280;"><?= nl2br(htmlspecialchars(mb_substr($cl['description'],0,200))) ?><?= mb_strlen($cl['description'])>200?'…':'' ?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Tab: New Claim Form -->
  <div class="wf-pane <?= $activeTab==='new'?'active':'' ?>" id="pane-new">
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 14px;font-size:.83rem;color:var(--secondary-dark,#922b21);margin-bottom:18px;display:flex;gap:8px;align-items:center;">
      <i class="fas fa-circle-info" style="flex-shrink:0;"></i>
      <div>तपाईंको नाम, फोन र ठेगाना <strong>profile बाट auto-fill</strong> भएको छ। केवल दाबीको विवरण भर्नुहोस्।</div>
    </div>

    <form method="POST" enctype="multipart/form-data">
      <?= $csrfField ?>
      <input type="hidden" name="action" value="submit_claim">

      <!-- Pre-filled member info (readonly) -->
      <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:18px;">
        <div style="font-size:.75rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">सदस्य जानकारी (Auto-filled)</div>
        <div class="form-row cols2">
          <div class="form-group" style="margin-bottom:8px;">
            <label>नाम</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($memName) ?>" readonly style="background:#f0f0f0;color:#6b7280;">
          </div>
          <div class="form-group" style="margin-bottom:8px;">
            <label>सदस्यता नम्बर</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($memSadasyata) ?>" readonly style="background:#f0f0f0;color:#6b7280;">
          </div>
          <div class="form-group" style="margin-bottom:8px;">
            <label>फोन</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($resolvedPhone) ?>" readonly style="background:#f0f0f0;color:#6b7280;">
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label>Email</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($resolvedEmail) ?>" readonly style="background:#f0f0f0;color:#6b7280;">
          </div>
        </div>
      </div>

      <!-- Claim Type -->
      <div class="form-group">
        <label>दाबी प्रकार <span style="color:#dc2626;">*</span></label>
        <select name="claim_type" class="form-control" required onchange="showTypeFields(this.value)">
          <option value="">— प्रकार छान्नुहोस् —</option>
          <option value="death">⚫ मृत्यु सुविधा</option>
          <option value="maternity">🟢 सुत्केरी सुविधा</option>
          <option value="medical">🔵 उपचार खर्च</option>
          <option value="accident">🟠 दुर्घटना सुविधा</option>
          <option value="insurance">🟣 बीमा दाबी</option>
          <option value="other">⚪ अन्य सुविधा</option>
        </select>
      </div>

      <!-- Death-specific fields -->
      <div class="type-fields" id="tf-death">
        <div style="background:#fff5f5;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
          <div style="font-size:.8rem;font-weight:700;color:#dc2626;margin-bottom:10px;"><i class="fas fa-cross" style="margin-right:5px;"></i>मृत्यु विवरण</div>
          <div class="form-row cols2">
            <div class="form-group" style="margin-bottom:0;"><label>मृत्यु हुने व्यक्तिको नाम</label><input name="deceased_name" type="text" class="form-control" placeholder="पूरा नाम"></div>
            <div class="form-group" style="margin-bottom:0;"><label>नाता</label><input name="deceased_relation" type="text" class="form-control" placeholder="जस्तै: आफ्नो, श्रीमती"></div>
            <div class="form-group" style="margin-bottom:0;"><label>मृत्यु मिति</label><input name="death_date" type="text" class="form-control" placeholder="YYYY-MM-DD"></div>
          </div>
        </div>
      </div>

      <!-- Maternity-specific fields -->
      <div class="type-fields" id="tf-maternity">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px;margin-bottom:14px;">
          <div style="font-size:.8rem;font-weight:700;color:#16a34a;margin-bottom:10px;"><i class="fas fa-baby" style="margin-right:5px;"></i>सुत्केरी विवरण</div>
          <div class="form-row cols2">
            <div class="form-group" style="margin-bottom:0;"><label>सुत्केरी मिति</label><input name="delivery_date" type="text" class="form-control" placeholder="YYYY-MM-DD"></div>
            <div class="form-group" style="margin-bottom:0;"><label>अस्पताल / क्लिनिकको नाम</label><input name="hospital_name" type="text" class="form-control" placeholder="अस्पतालको नाम"></div>
          </div>
        </div>
      </div>

      <!-- Medical/Accident-specific fields -->
      <div class="type-fields" id="tf-medical">
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
          <div style="font-size:.8rem;font-weight:700;color:var(--secondary-color,#c0392b);margin-bottom:10px;"><i class="fas fa-stethoscope" style="margin-right:5px;"></i>उपचार विवरण</div>
          <div class="form-row cols2">
            <div class="form-group" style="margin-bottom:0;"><label>रोग / चोट विवरण</label><input name="disease_illness" type="text" class="form-control" placeholder="संक्षिप्त विवरण"></div>
            <div class="form-group" style="margin-bottom:0;"><label>उपचार मिति</label><input name="treatment_date" type="text" class="form-control" placeholder="YYYY-MM-DD"></div>
            <div class="form-group" style="margin-bottom:0;"><label>अस्पताल / क्लिनिक</label><input name="hospital_clinic" type="text" class="form-control" placeholder="अस्पतालको नाम"></div>
          </div>
        </div>
      </div>

      <!-- Insurance-specific fields -->
      <div class="type-fields" id="tf-insurance">
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;margin-bottom:14px;">
          <div style="font-size:.8rem;font-weight:700;color:var(--secondary-dark,#922b21);margin-bottom:10px;"><i class="fas fa-shield-halved" style="margin-right:5px;"></i>बीमा विवरण</div>
          <div class="form-row cols2">
            <div class="form-group" style="margin-bottom:0;">
              <label>बीमा पोलिसी नम्बर</label>
              <input name="policy_number" type="text" class="form-control" placeholder="जस्तै: NL-2023-XXXXXX">
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label>बीमा कम्पनीको नाम</label>
              <input name="insurer_name" type="text" class="form-control" placeholder="बीमा कम्पनी">
            </div>
          </div>
        </div>
      </div>

      <!-- Other-specific fields -->
      <div class="type-fields" id="tf-other">
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin-bottom:14px;">
          <div style="font-size:.8rem;font-weight:700;color:#374151;margin-bottom:6px;"><i class="fas fa-circle-info" style="margin-right:5px;"></i>अन्य सुविधा दाबी</div>
          <div style="font-size:.82rem;color:#6b7280;">तलको <strong>विस्तृत विवरण</strong> section मा आफ्नो दाबीको पूरा जानकारी लेख्नुहोस् — कुन सुविधा माग गरिरहनुभएको छ, किन चाहिएको छ, आदि।</div>
        </div>
      </div>

      <!-- Common fields -->
      <div class="form-row cols2">
        <div class="form-group">
          <label>लाभग्राही नाम (Beneficiary)</label>
          <input name="beneficiary_name" type="text" class="form-control" placeholder="भुक्तानी पाउने व्यक्ति">
        </div>
        <div class="form-group">
          <label>लाभग्राहीसँग नाता</label>
          <input name="beneficiary_relation" type="text" class="form-control" placeholder="जस्तै: आमा, श्रीमान्">
        </div>
      </div>

      <div class="form-group">
        <label>माग रकम (रूपैयाँमा)</label>
        <input name="claim_amount" type="number" class="form-control" min="0" step="0.01" placeholder="0.00">
      </div>

      <div class="form-group">
        <label>विस्तृत विवरण <span style="color:#dc2626;">*</span></label>
        <textarea name="description" class="form-control" rows="4" required placeholder="दाबीको पूरा विवरण लेख्नुहोस्..."></textarea>
      </div>

      <!-- Document upload -->
      <div class="form-group">
        <label><i class="fas fa-paperclip" style="margin-right:5px;"></i>सम्बन्धित कागजपत्र (Optional)</label>
        <label class="doc-upload" for="docUpload">
          <i class="fas fa-cloud-upload-alt" style="font-size:1.8rem;color:#9ca3af;display:block;margin-bottom:6px;"></i>
          <div style="font-size:.85rem;color:#6b7280;">Click गरी files छान्नुहोस् वा यहाँ drag गर्नुहोस्</div>
          <div style="font-size:.75rem;color:#9ca3af;margin-top:3px;">PDF, JPG, PNG — अधिकतम 10MB प्रति file</div>
          <input type="file" id="docUpload" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="showFiles(this)">
        </label>
        <div id="fileList" style="margin-top:8px;font-size:.82rem;color:#16a34a;"></div>
      </div>

      <button type="submit" style="width:100%;padding:12px;background:var(--primary-color,#1a8754);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
        <i class="fas fa-paper-plane"></i> दाबी दर्ता गर्नुहोस्
      </button>
    </form>
  </div>

</div>
</main>

<script>
function showTab(btn, tab) {
    document.querySelectorAll('.wf-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.wf-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('pane-' + tab).classList.add('active');
    /* btn could be the <i> icon child — walk up to the button */
    var el = btn;
    while (el && el.tagName !== 'BUTTON') el = el.parentElement;
    if (el) el.classList.add('active');
}
function showTypeFields(type) {
    document.querySelectorAll('.type-fields').forEach(f => f.classList.remove('show'));
    var map = {
        'death'     : 'tf-death',
        'maternity' : 'tf-maternity',
        'medical'   : 'tf-medical',
        'accident'  : 'tf-medical',
        'insurance' : 'tf-insurance',
        'other'     : 'tf-other'
    };
    if (map[type]) document.getElementById(map[type]).classList.add('show');
}
function showFiles(input) {
    var list = document.getElementById('fileList');
    list.innerHTML = '';
    Array.from(input.files).forEach(function(f){
        list.innerHTML += '<div><i class="fas fa-file" style="margin-right:4px;"></i>'+f.name+'</div>';
    });
}
</script>
<?php require __DIR__ . '/includes/chrome-foot.php'; ?>
