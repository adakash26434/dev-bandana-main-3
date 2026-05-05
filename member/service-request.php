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
    'appointment'       => ['label' => '📅 भेटघाट — शाखा भ्रमण / भेट माग्ने',      'table' => 'appointments',      'purpose' => 'Member Portal Service Request'],
    'loan_inquiry'      => ['label' => '💰 ऋण जानकारी — कर्जा सम्बन्धी सोधपुछ', 'table' => 'appointments',      'purpose' => 'ऋण जानकारी / Loan Inquiry'],
    'account_info'      => ['label' => '🏦 खाता जानकारी — बचत खाता सम्बन्धी',   'table' => 'appointments',      'purpose' => 'खाता जानकारी / Account Info'],
    'welfare_inquiry'   => ['label' => '❤️ कल्याण सोधपुछ — सुविधा जानकारी',    'table' => 'appointments',      'purpose' => 'कल्याण सोधपुछ / Welfare Inquiry'],
    'document_request'  => ['label' => '📄 कागजात माग — NOC, Statement आदि',   'table' => 'appointments',      'purpose' => 'कागजात माग / Document Request'],
    'grievance'         => ['label' => '📣 गुनासो — समस्या दर्ता गर्ने',         'table' => 'grievances',        'purpose' => ''],
    'general'           => ['label' => '💬 सामान्य सोधपुछ',                       'table' => 'appointments',      'purpose' => 'सामान्य सोधपुछ / General Inquiry'],
];

$successMsg = '';
$errorMsg   = '';
$submitted  = [];

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    if (!verifyCSRFToken()) {
        $errorMsg = 'सुरक्षा जाँच असफल।';
    } elseif (!checkRateLimit('svcreq_' . $memberId, 10, 3600)) {
        $errorMsg = 'धेरै अनुरोध भए। १ घण्टापछि पुनः प्रयास गर्नुहोस्।';
    } else {
        $svcType    = trim($_POST['service_type'] ?? '');
        $message    = trim(substr($_POST['message'] ?? '', 0, 2000));
        $prefDate   = trim($_POST['preferred_date'] ?? '') ?: null;
        $prefTime   = trim($_POST['preferred_time'] ?? '');
        $branch     = trim(substr($_POST['branch'] ?? '', 0, 80)) ?: $rBranch;

        if (!isset($serviceTypes[$svcType])) {
            $errorMsg = 'सेवा प्रकार छान्नुहोस्।';
        } elseif (!$message) {
            $errorMsg = 'सन्देश / विवरण अनिवार्य छ।';
        } else {
            $svc       = $serviceTypes[$svcType];
            $trackingId = 'REQ-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid($memberId,true)),0,6));
            $fullPurpose = $svc['purpose'] ?: ($serviceTypes[$svcType]['label'] ?? $svcType);

            try {
                if ($svc['table'] === 'grievances') {
                    $ins = $db->prepare("INSERT INTO grievances (tracking_id, name, phone, email, subject, message, status, created_at)
                                         VALUES (?,?,?,?,?,?,'pending',NOW())");
                    $ins->execute([$trackingId, $memName, $rPhone, $rEmail, $fullPurpose, $message]);
                } else {
                    /* Insert into appointments */
                    $ins = $db->prepare("INSERT INTO appointments
                        (tracking_id, name, phone, email, preferred_date, purpose, message, branch, status, created_at)
                        VALUES (?,?,?,?,?,?,?,?,'pending',NOW())");
                    $ins->execute([$trackingId, $memName, $rPhone, $rEmail, $prefDate, $fullPurpose . ($prefTime ? " ($prefTime)" : ''), $message, $branch]);
                }
                $successMsg = "अनुरोध दर्ता भयो! Tracking ID: <strong>$trackingId</strong> — Admin ले confirm गरेपछि सूचित गरिनेछ।";
            } catch (Throwable $e) {
                $errorMsg = 'दर्ता गर्न समस्या भयो। पुनः प्रयास गर्नुहोस्।';
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
$pageTitle = 'सेवा अनुरोध — ' . $siteName;
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
    <i class="fas fa-concierge-bell" style="margin-right:8px;"></i>सेवा अनुरोध
  </h1>

  <?php if ($successMsg): ?>
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 16px;color:#166534;font-size:.9rem;margin-bottom:16px;">
    <i class="fas fa-circle-check" style="margin-right:8px;"></i><?= $successMsg ?>
    <div style="margin-top:10px;"><a href="tracker.php" style="color:var(--primary-color,#1a8754);font-weight:700;">Tracker मा हेर्नुहोस् →</a></div>
  </div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;color:#dc2626;font-size:.9rem;margin-bottom:16px;">
    <i class="fas fa-circle-xmark" style="margin-right:8px;"></i><?= htmlspecialchars($errorMsg) ?>
  </div>
  <?php endif; ?>

  <div class="sr-info-bar">
    <i class="fas fa-magic-wand-sparkles" style="flex-shrink:0;"></i>
    <div>तपाईंको नाम, फोन, email — <strong>profile बाट auto-fill</strong> भएको छ। सेवा प्रकार र सन्देश मात्र भर्नुहोस्।</div>
  </div>

  <form method="POST">
    <?= $csrfField ?>
    <input type="hidden" name="action" value="submit">

    <!-- Pre-filled info -->
    <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px;margin-bottom:18px;">
      <div style="font-size:.75rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">तपाईंको जानकारी (Auto-filled)</div>
      <div class="form-row2">
        <div class="form-group" style="margin-bottom:8px;">
          <label>नाम</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($memName) ?>" readonly>
        </div>
        <div class="form-group" style="margin-bottom:8px;">
          <label>सदस्यता नम्बर</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($memSadasyata) ?>" readonly>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>फोन</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($rPhone) ?>" readonly>
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label>Email</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($rEmail) ?>" readonly>
        </div>
      </div>
    </div>

    <!-- Service type -->
    <div class="form-group">
      <label>सेवा प्रकार छान्नुहोस् <span style="color:#dc2626;">*</span></label>
      <select name="service_type" class="form-control" required>
        <option value="">— सेवा छान्नुहोस् —</option>
        <?php foreach ($serviceTypes as $key => $svc): ?>
        <option value="<?= $key ?>"><?= $svc['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row2">
      <div class="form-group">
        <label><i class="fas fa-calendar" style="margin-right:4px;color:var(--primary-color,#1a8754);"></i>मनपर्ने मिति (Optional)</label>
        <input type="date" name="preferred_date" class="form-control" min="<?= date('Y-m-d') ?>">
      </div>
      <div class="form-group">
        <label><i class="fas fa-clock" style="margin-right:4px;color:var(--primary-color,#1a8754);"></i>मनपर्ने समय</label>
        <select name="preferred_time" class="form-control">
          <option value="">— समय छान्नुहोस् —</option>
          <option>10:00 AM - 11:00 AM</option>
          <option>11:00 AM - 12:00 PM</option>
          <option>1:00 PM - 2:00 PM</option>
          <option>2:00 PM - 3:00 PM</option>
          <option>3:00 PM - 4:00 PM</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label><i class="fas fa-building" style="margin-right:4px;color:var(--primary-color,#1a8754);"></i>शाखा</label>
      <input type="text" name="branch" class="form-control" value="<?= htmlspecialchars($rBranch) ?>" placeholder="जस्तै: प्रधान कार्यालय">
    </div>

    <div class="form-group">
      <label>विस्तृत सन्देश <span style="color:#dc2626;">*</span></label>
      <textarea name="message" class="form-control" rows="4" required placeholder="तपाईंको अनुरोधको पूरा विवरण लेख्नुहोस्..."></textarea>
    </div>

    <button type="submit" style="width:100%;padding:12px;background:var(--primary-color,#1a8754);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
      <i class="fas fa-paper-plane"></i> अनुरोध पठाउनुहोस्
    </button>
  </form>

  <!-- Recent requests -->
  <?php if (!empty($recentReqs)): ?>
  <div style="margin-top:24px;">
    <div style="font-size:.8rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">
      <i class="fas fa-history" style="margin-right:4px;"></i>हालसालैका अनुरोधहरू
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
