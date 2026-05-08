<?php
/**
 * Member Portal — सेवा अनुरोध (Pre-filled Service Request)
 * Member profile data auto-fills form — zero re-entry
 */
require_once __DIR__ . '/_bootstrap.php';
requireMemberLogin();
memberSecurityHeaders();

$db  = getDB();
$mem = currentMember();
if (!$mem) { header('Location: login.php?msg=session_expired'); exit; }
$_t = static function (string $np, string $en): string {
    return isEnglish() ? $en : $np;
};

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

$memName    = trim((string)($kycRow['full_name']    ?? $mem['name']            ?? ''));
$memSadasyata = trim((string)($kycRow['member_id']  ?? $mem['sadasyata_number']?? ''));
$rPhone     = $memPhone ?: preg_replace('/[^0-9]/', '', (string)($kycRow['mobile'] ?? ''));
$rEmail     = $memEmail ?: strtolower(trim((string)($kycRow['email'] ?? '')));
$rAddress   = trim((string)($kycRow['temporary_address'] ?? $kycRow['permanent_address'] ?? ''));
$rBranch    = trim((string)($kycRow['branch'] ?? ''));

/* Service type options with target table/purpose */
$serviceTypes = [
    'appointment'       => ['label' => $_t('📅 भेटघाट — शाखा भ्रमण / भेट माग्ने','📅 Appointment — Branch visit request'),      'table' => 'appointments', 'purpose' => 'other'],
    'loan_inquiry'      => ['label' => $_t('💰 ऋण जानकारी — कर्जा सम्बन्धी सोधपुछ','💰 Loan Inquiry — Ask about loans'), 'table' => 'appointments', 'purpose' => 'loan_inquiry'],
    'account_info'      => ['label' => $_t('🏦 खाता जानकारी — बचत खाता सम्बन्धी','🏦 Account Info — Savings account related'),   'table' => 'appointments', 'purpose' => 'account_inquiry'],
    'welfare_inquiry'   => ['label' => $_t('❤️ कल्याण सोधपुछ — सुविधा जानकारी','❤️ Welfare Inquiry — Benefit information'),    'table' => 'appointments', 'purpose' => 'other'],
    'document_request'  => ['label' => $_t('📄 कागजात माग — NOC, Statement आदि','📄 Document Request — NOC, Statement etc.'),   'table' => 'appointments', 'purpose' => 'other'],
    'grievance'         => ['label' => $_t('📣 गुनासो — समस्या दर्ता गर्ने','📣 Grievance — Register a problem'),         'table' => 'grievances',   'purpose' => 'other'],
    'general'           => ['label' => $_t('💬 सामान्य सोधपुछ','💬 General Inquiry'),                       'table' => 'appointments', 'purpose' => 'other'],
];

$successMsg = '';
$errorMsg   = '';
$submitted  = [];

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    if (!verifyCSRFToken()) {
        $errorMsg = $_t('सुरक्षा जाँच असफल।', 'Security check failed.');
    } elseif (!checkRateLimit('svcreq_' . $memberId, 10, 3600)) {
        $errorMsg = $_t('धेरै अनुरोध भए। १ घण्टापछि पुनः प्रयास गर्नुहोस्।', 'Too many requests. Please try again after 1 hour.');
    } else {
        $svcType    = trim($_POST['service_type'] ?? '');
        $message    = trim(substr($_POST['message'] ?? '', 0, 2000));
        $prefDate   = trim($_POST['preferred_date'] ?? '') ?: null;
        $prefTime   = trim($_POST['preferred_time'] ?? '');
        $branch     = trim(substr($_POST['branch'] ?? '', 0, 80)) ?: $rBranch;

        if (!isset($serviceTypes[$svcType])) {
            $errorMsg = $_t('सेवा प्रकार छान्नुहोस्।', 'Please select service type.');
        } elseif (!$message) {
            $errorMsg = $_t('सन्देश / विवरण अनिवार्य छ।', 'Message/description is required.');
        } else {
            $svc       = $serviceTypes[$svcType];
            $trackingId = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid($memberId,true)),0,6));
            $svcLabel = $serviceTypes[$svcType]['label'] ?? $svcType;
            $corePurpose = $svc['purpose'] ?: 'other';
            $detailPrefix = preg_replace('/^[^\s]+\s*/u', '', $svcLabel);
            $detailText = trim($detailPrefix . ($message ? ("\n\n" . $message) : ''));

            try {
                if ($svc['table'] === 'grievances') {
                    $ins = $db->prepare("INSERT INTO grievances
                        (tracking_id, name, member_id, phone, email, category, subject, description, is_anonymous, status, created_at)
                        VALUES (?,?,?,?,?,'service',?,?,0,'pending',NOW())");
                    $ins->execute([$trackingId, $memName, $memSadasyata, $rPhone, $rEmail, $detailPrefix, $detailText]);
                } else {
                    /* Canonical appointments insert (purpose enum + purpose_detail text). */
                    $effectiveDate = $prefDate ?: date('Y-m-d');
                    $effectiveTime = $prefTime ?: '10:00 AM';
                    $ins = $db->prepare("INSERT INTO appointments
                        (tracking_id, name, phone, email, member_id, preferred_date, preferred_time, purpose, purpose_detail, branch, status, created_at)
                        VALUES (?,?,?,?,?,?,?,?,?,?,'pending',NOW())");
                    $ins->execute([$trackingId, $memName, $rPhone, $rEmail, $memSadasyata, $effectiveDate, $effectiveTime, $corePurpose, $detailText, $branch]);
                }
                $successMsg = isEnglish()
                    ? "Request submitted! Tracking ID: <strong>$trackingId</strong> — You will be notified after admin confirmation."
                    : "अनुरोध दर्ता भयो! Tracking ID: <strong>$trackingId</strong> — Admin ले confirm गरेपछि सूचित गरिनेछ।";
            } catch (Throwable $e) {
                $errorMsg = $_t('दर्ता गर्न समस्या भयो। पुनः प्रयास गर्नुहोस्।', 'Failed to submit. Please try again.');
                error_log('[service-request] ' . $e->getMessage());
            }
        }
    }
}

/* Recent requests */
$recentReqs = [];
try {
    $rConds = []; $rParams = [];
    if ($rEmail) { $rConds[] = 'LOWER(email)=?'; $rParams[] = strtolower($rEmail); }
    if ($rPhone) { $rConds[] = 'phone=?'; $rParams[] = $rPhone; }
    if (!empty($rConds)) {
        $st = $db->prepare("SELECT tracking_id, name, purpose, status, preferred_date, created_at FROM appointments
                            WHERE " . implode(' OR ', $rConds) . " ORDER BY created_at DESC LIMIT 8");
        $st->execute($rParams);
        $recentReqs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) { $recentReqs = []; }

$siteName  = getSetting('site_name', 'सहकारी');
$pageTitle = $_t('सेवा अनुरोध', 'Service Request') . ' — ' . $siteName;
$csrfField = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken()) . '">';

$statusColors = [
    'pending' => 'var(--secondary-color,#c0392b)',
    'confirmed' => 'var(--secondary-color,#c0392b)',
    'completed' => 'var(--primary-color,#1a8754)',
    'cancelled' => '#dc2626',
    'processing' => 'var(--secondary-dark,#922b21)'
];

$extraHead = <<<HTML
<style>
.sr-info-bar { background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:12px 14px;font-size:.83rem;color:#166534;margin-bottom:18px;display:flex;gap:8px;align-items:center; }
.form-group { margin-bottom:14px; }
.form-group label { display:block;font-size:.82rem;font-weight:600;color:#374151;margin-bottom:5px; }
.form-control { width:100%;padding:10px 14px;min-height:44px;border:1.5px solid #d1d5db;border-radius:10px;font-family:inherit;font-size:.9rem;background:#f9fafb;transition:border-color .2s;line-height:1.4; }
.form-control:focus { outline:none;border-color:var(--primary-color,#1a8754);background:#fff;box-shadow:0 0 0 3px rgba(26,95,42,.12); }
.form-control[readonly] { background:#f0f0f0;color:#6b7280; }
.form-row2 { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
@media(max-width:540px){.form-row2{grid-template-columns:1fr;}}
.svc-card { background:#fff;border:2px solid #e5e7eb;border-radius:10px;padding:14px;cursor:pointer;transition:all .2s;margin-bottom:10px; }
.svc-card:hover,.svc-card.sel { border-color:var(--primary-color,#1a8754);background:#f0fdf4; }
.svc-card.sel { box-shadow:0 0 0 3px rgba(26,95,42,.12); }
.svc-label { font-size:.9rem;font-weight:600;color:#1f2937; }
.recent-card { background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px; }
.status-dot { width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:5px; }
</style>
HTML;
?>
<?php require __DIR__ . '/includes/chrome.php'; ?>

<main class="mp-main">
<div class="mp-container">

  <h1 style="font-size:1.25rem;font-weight:700;color:var(--primary-color,#1a8754);margin:0 0 16px;">
    <i class="fas fa-concierge-bell" style="margin-right:8px;"></i><?php echo $_t('सेवा अनुरोध', 'Service Request'); ?>
  </h1>

  <?php if ($successMsg): ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 16px;color:#166534;font-size:.9rem;margin-bottom:16px;">
    <i class="fas fa-circle-check" style="margin-right:8px;"></i><?= $successMsg ?>
    <div style="margin-top:10px;"><a href="tracker.php" style="color:var(--primary-color,#1a8754);font-weight:700;"><?php echo $_t('Tracker मा हेर्नुहोस्', 'Open in Tracker'); ?> →</a></div>
  </div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;color:#dc2626;font-size:.9rem;margin-bottom:16px;">
    <i class="fas fa-circle-xmark" style="margin-right:8px;"></i><?= htmlspecialchars($errorMsg) ?>
  </div>
  <?php endif; ?>

  <div class="sr-info-bar">
    <i class="fas fa-magic-wand-sparkles" style="flex-shrink:0;"></i>
    <div><?php echo $_t('तपाईंको नाम, फोन, email — <strong>profile बाट auto-fill</strong> भएको छ। सेवा प्रकार र सन्देश मात्र भर्नुहोस्।', 'Your name, phone and email are <strong>auto-filled from profile</strong>. Only select service type and message.'); ?></div>
  </div>

  <form method="POST">
    <?= $csrfField ?>
    <input type="hidden" name="action" value="submit">

    <!-- Pre-filled info -->
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:18px;">
      <div style="font-size:.75rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;"><?php echo $_t('तपाईंको जानकारी (Auto-filled)', 'Your Information (Auto-filled)'); ?></div>
      <div class="form-row2">
        <div class="form-group" style="margin-bottom:8px;">
          <label><?php echo $_t('नाम', 'Name'); ?></label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($memName) ?>" readonly>
        </div>
        <div class="form-group" style="margin-bottom:8px;">
          <label><?php echo $_t('सदस्यता नम्बर', 'Membership Number'); ?></label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($memSadasyata) ?>" readonly>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label><?php echo $_t('फोन', 'Phone'); ?></label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($rPhone) ?>" readonly>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label><?php echo $_t('इमेल', 'Email'); ?></label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($rEmail) ?>" readonly>
        </div>
      </div>
    </div>

    <!-- Service type -->
    <div class="form-group">
      <label><?php echo $_t('सेवा प्रकार छान्नुहोस्', 'Select Service Type'); ?> <span style="color:#dc2626;">*</span></label>
      <select name="service_type" class="form-control" required>
        <option value="">— <?php echo $_t('सेवा छान्नुहोस्', 'Select service'); ?> —</option>
        <?php foreach ($serviceTypes as $key => $svc): ?>
        <option value="<?= $key ?>"><?= $svc['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row2">
      <div class="form-group">
        <label><i class="fas fa-calendar" style="margin-right:4px;color:var(--primary-color,#1a8754);"></i><?php echo $_t('मनपर्ने मिति (Optional)', 'Preferred Date (Optional)'); ?></label>
        <input type="date" name="preferred_date" class="form-control" min="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label><i class="fas fa-clock" style="margin-right:4px;color:var(--primary-color,#1a8754);"></i><?php echo $_t('मनपर्ने समय', 'Preferred Time'); ?></label>
        <select name="preferred_time" class="form-control">
          <option value="">— <?php echo $_t('समय छान्नुहोस्', 'Select time'); ?> —</option>
          <option>10:00 AM - 11:00 AM</option>
          <option>11:00 AM - 12:00 PM</option>
          <option>1:00 PM - 2:00 PM</option>
          <option>2:00 PM - 3:00 PM</option>
          <option>3:00 PM - 4:00 PM</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label><i class="fas fa-building" style="margin-right:4px;color:var(--primary-color,#1a8754);"></i><?php echo $_t('शाखा', 'Branch'); ?></label>
      <input type="text" name="branch" class="form-control" value="<?= htmlspecialchars($rBranch) ?>" placeholder="<?php echo $_t('जस्तै: प्रधान कार्यालय', 'e.g., Head Office'); ?>">
    </div>

    <div class="form-group">
      <label><?php echo $_t('विस्तृत सन्देश', 'Detailed Message'); ?> <span style="color:#dc2626;">*</span></label>
      <textarea name="message" class="form-control" rows="4" required placeholder="<?php echo $_t('तपाईंको अनुरोधको पूरा विवरण लेख्नुहोस्...', 'Write full details of your request...'); ?>"></textarea>
    </div>

    <button type="submit" style="width:100%;padding:12px;background:var(--primary-color,#1a8754);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
      <i class="fas fa-paper-plane"></i> <?php echo $_t('अनुरोध पठाउनुहोस्', 'Submit Request'); ?>
    </button>
  </form>

  <!-- Recent requests -->
  <?php if (!empty($recentReqs)): ?>
  <div style="margin-top:24px;">
    <div style="font-size:.8rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">
      <i class="fas fa-history" style="margin-right:4px;"></i><?php echo $_t('हालसालैका अनुरोधहरू', 'Recent Requests'); ?>
    </div>
    <?php foreach ($recentReqs as $rq):
        $stColor = $statusColors[$rq['status']] ?? '#9ca3af';
    ?>
    <div class="recent-card">
      <div>
        <span style="font-size:.82rem;font-weight:700;color:#1f2937;"><?= htmlspecialchars(mb_substr($rq['purpose'],0,50)) ?></span>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:2px;"><?= date('Y-m-d', strtotime($rq['created_at'])) ?></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <span class="track-id" style="font-size:.72rem;font-family:monospace;background:#f3f4f6;padding:2px 7px;border-radius:5px;"><?= htmlspecialchars($rq['tracking_id']) ?></span>
        <span style="font-size:.75rem;font-weight:700;color:<?= $stColor ?>;">
          <span class="status-dot" style="background:<?= $stColor ?>;"></span><?= htmlspecialchars($rq['status']) ?>
        </span>
      </div>
    </div>
    <?php endforeach; ?>
    <a href="tracker.php?filter=appointment" style="font-size:.82rem;color:var(--primary-color,#1a8754);text-decoration:none;">
      सबै Tracker मा हेर्नुहोस् →
    </a>
  </div>
  <?php endif; ?>

</div>
</main>
<?php require __DIR__ . '/includes/chrome-foot.php'; ?>
