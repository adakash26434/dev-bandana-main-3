<?php
/**
 * Member Portal — सदस्यता प्रमाणपत्र (Membership Certificate)
 * Printable + browser PDF download via print dialog
 */
require_once __DIR__ . '/_bootstrap.php';
requireMemberLogin();
memberSecurityHeaders();

$db  = getDB();
$mem = currentMember();
if (!$mem) { header('Location: login.php?msg=session_expired'); exit; }

$memberId = (int)$mem['id'];

/* KYC data */
$kycRow = null;
try {
    $kycLinkId = (int)($mem['kyc_application_id'] ?? 0);
    if ($kycLinkId > 0) {
        $ks = $db->prepare("SELECT * FROM kyc_applications WHERE id=? LIMIT 1");
        $ks->execute([$kycLinkId]);
        $kycRow = $ks->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$kycRow) {
        $memEmail = trim((string)($mem['email'] ?? ''));
        $memPhone = preg_replace('/[^0-9]/', '', (string)($mem['phone'] ?? ''));
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

$fullName    = trim((string)($kycRow['full_name']       ?? $mem['name']             ?? ''));
$sadasyata   = trim((string)($kycRow['member_id']       ?? $mem['sadasyata_number'] ?? ''));
$phone       = trim((string)($kycRow['mobile']          ?? $mem['phone']            ?? ''));
$email       = trim((string)($kycRow['email']           ?? $mem['email']            ?? ''));
$address     = trim((string)($kycRow['permanent_address']?? ''));
$fatherName  = trim((string)($kycRow['father_name']     ?? ''));
$dob         = trim((string)($kycRow['date_of_birth_ad'] ?? $kycRow['date_of_birth'] ?? ''));
$citizenship = trim((string)($kycRow['citizenship_no']  ?? ''));
$photoPath   = trim((string)($kycRow['photo']           ?? $mem['avatar_url']       ?? ''));
$approvedDate= trim((string)($kycRow['approved_at']     ?? $mem['created_at']       ?? ''));
$accountType = trim((string)($kycRow['account_type']    ?? ''));

/* Site settings */
$siteName    = getSetting('site_name', 'सहकारी');
$siteNameEn  = getSetting('site_name_en', 'Cooperative');
$sitePhone   = getSetting('phone', '');
$siteEmail   = getSetting('email', '');
$siteAddress = getSetting('address', '');
$siteLogo    = getSetting('site_logo', getSetting('logo', 'assets/images/logo.png'));
$primaryColor= getSetting('primary_color', '#1a5f2a');

/* Photo URL */
$photoUrl = '';
if ($photoPath) {
    $photoUrl = preg_match('#^https?://#', $photoPath) ? $photoPath : SITE_URL . ltrim($photoPath, '/');
}

/* Verify QR URL */
$verifyUrl = SITE_URL . 'verify.php?id=' . urlencode($sadasyata);
$qrUrl     = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($verifyUrl) . '&size=120x120&margin=4';

/* Issue / expiry */
$issueDate  = $approvedDate ? date('Y F d', strtotime($approvedDate)) : date('Y F d');
$issueDateNp= $approvedDate ? date('Y-m-d', strtotime($approvedDate)) : date('Y-m-d');
$logoUrl    = SITE_URL . ltrim($siteLogo, '/');

$pageTitle = 'सदस्यता प्रमाणपत्र — ' . $siteName;
$extraHead = <<<HTML
<style>
@media print {
  .cert-noprint { display:none !important; }
  body { padding-bottom:0 !important; }
  .mp-topbar, .mem-nav, .mp-bottom-nav { display:none !important; }
  .mp-container { padding:0 !important; }
  .cert-page { box-shadow:none !important; border:2px solid #ccc !important; }
  @page { size: A4; margin: 10mm; }
}
.cert-page {
  background:#fff; max-width:680px; margin:0 auto;
  border:3px double {$primaryColor};
  border-radius:8px;
  box-shadow:0 8px 32px rgba(0,0,0,.12);
  position:relative; overflow:hidden;
}
.cert-top-band { background:{$primaryColor}; color:#fff; padding:14px 24px; display:flex; align-items:center; gap:16px; }
.cert-logo { width:52px; height:52px; border-radius:10px; background:#fff; padding:4px; object-fit:contain; }
.cert-logo-placeholder { width:52px;height:52px;border-radius:10px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.4rem; }
.cert-site-name { font-size:1.1rem; font-weight:800; line-height:1.2; }
.cert-site-sub  { font-size:.78rem; opacity:.85; margin-top:2px; }
.cert-body { padding:22px 28px; }
.cert-title { text-align:center; margin-bottom:20px; }
.cert-title h2 { font-size:1.25rem; font-weight:800; color:{$primaryColor}; letter-spacing:.05em; border-bottom:2px solid {$primaryColor}; display:inline-block; padding-bottom:4px; }
.cert-title p { font-size:.78rem; color:#6b7280; margin-top:4px; }
.cert-member-row { display:flex; gap:20px; margin-bottom:20px; align-items:flex-start; }
.cert-photo { width:90px; height:110px; border:2px solid #e5e7eb; border-radius:6px; object-fit:cover; flex-shrink:0; background:#f9fafb; display:flex; align-items:center; justify-content:center; }
.cert-photo img { width:86px; height:106px; object-fit:cover; border-radius:4px; }
.cert-details { flex:1; }
.cert-field { margin-bottom:8px; display:flex; gap:4px; font-size:.88rem; }
.cert-field-label { min-width:140px; color:#6b7280; font-weight:600; flex-shrink:0; }
.cert-field-value { color:#1f2937; font-weight:700; border-bottom:1px solid #e5e7eb; flex:1; padding-bottom:2px; }
.cert-footer { background:#f9fafb; border-top:2px solid {$primaryColor}; padding:14px 28px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
.cert-seal { width:80px; height:80px; border-radius:50%; border:3px solid {$primaryColor}; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; font-size:.55rem; font-weight:700; color:{$primaryColor}; line-height:1.2; }
.cert-qr img { display:block; }
.cert-sign-line { border-top:1.5px solid #374151; margin-top:40px; padding-top:5px; font-size:.75rem; color:#6b7280; text-align:center; min-width:100px; }
.cert-watermark { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%) rotate(-30deg); font-size:5rem; font-weight:900; color:rgba(26,95,42,.04); pointer-events:none; white-space:nowrap; z-index:0; }
.cert-ribbon { position:absolute; top:16px; right:-20px; background:var(--secondary-color,#c0392b); color:var(--text-on-secondary,#fff); font-size:.65rem; font-weight:700; padding:4px 28px; transform:rotate(35deg); letter-spacing:.08em; }
</style>
HTML;
?>
<?php require __DIR__ . '/includes/chrome.php'; ?>

<main class="mp-main">
<div class="mp-container">

  <!-- Action buttons (hidden on print) -->
  <div class="cert-noprint" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <h1 style="font-size:1.15rem;font-weight:700;color:var(--primary-color,#1a8754);margin:0;">
      <i class="fas fa-certificate" style="margin-right:8px;"></i>सदस्यता प्रमाणपत्र
    </h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button onclick="window.print()" style="padding:9px 20px;background:var(--primary-color,#1a8754);color:#fff;border:none;border-radius:8px;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-print"></i> Print / PDF
      </button>
      <a href="id-card.php" style="padding:9px 20px;background:#fff;color:var(--primary-color,#1a8754);border:2px solid var(--primary-color,#1a8754);border-radius:8px;font-size:.88rem;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-id-card"></i> ID Card
      </a>
    </div>
  </div>

  <?php if (!$fullName || !$sadasyata): ?>
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;font-size:.88rem;color:var(--secondary-dark,#922b21);margin-bottom:16px;display:flex;gap:8px;align-items:center;" class="cert-noprint">
    <i class="fas fa-triangle-exclamation"></i>
    <div>तपाईंको KYC अनुमोदन भएको छैन। KYC approve भएपछि मात्र पूर्ण प्रमाणपत्र उपलब्ध हुनेछ।</div>
  </div>
  <?php endif; ?>

  <!-- Certificate -->
  <div class="cert-page" id="certificate">
    <div class="cert-watermark"><?= htmlspecialchars($siteName) ?></div>
    <?php if ($kycRow && ($kycRow['status'] ?? '') === 'approved'): ?>
    <div class="cert-ribbon">VERIFIED</div>
    <?php endif; ?>

    <!-- Top band -->
    <div class="cert-top-band">
      <?php if ($siteLogo && file_exists(__DIR__ . '/../' . ltrim($siteLogo,'/'))): ?>
      <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="cert-logo">
      <?php else: ?>
      <div class="cert-logo-placeholder"><i class="fas fa-seedling"></i></div>
      <?php endif; ?>
      <div>
        <div class="cert-site-name"><?= htmlspecialchars($siteName) ?></div>
        <div class="cert-site-sub"><?= htmlspecialchars($siteNameEn) ?></div>
        <?php if ($siteAddress): ?>
        <div class="cert-site-sub"><i class="fas fa-location-dot" style="margin-right:3px;"></i><?= htmlspecialchars($siteAddress) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Body -->
    <div class="cert-body" style="position:relative;z-index:1;">
      <div class="cert-title">
        <h2>सदस्यता प्रमाणपत्र</h2>
        <p>MEMBERSHIP CERTIFICATE</p>
      </div>

      <div style="text-align:center;font-size:.88rem;color:#4b5563;margin-bottom:18px;line-height:1.6;">
        यसद्वारा प्रमाणित गरिन्छ कि तल उल्लिखित व्यक्ति <strong><?= htmlspecialchars($siteName) ?></strong> को
        <?= $accountType ? htmlspecialchars($accountType) . ' ' : '' ?>सदस्य हुनुहुन्छ।
      </div>

      <div class="cert-member-row">
        <div class="cert-photo">
          <?php if ($photoUrl): ?>
          <img src="<?= htmlspecialchars($photoUrl) ?>" alt="Photo">
          <?php else: ?>
          <i class="fas fa-user" style="font-size:2.5rem;color:#d1d5db;"></i>
          <?php endif; ?>
        </div>
        <div class="cert-details">
          <div class="cert-field">
            <span class="cert-field-label">पूरा नाम</span>
            <span class="cert-field-value"><?= htmlspecialchars($fullName ?: '—') ?></span>
          </div>
          <div class="cert-field">
            <span class="cert-field-label">सदस्यता नम्बर</span>
            <span class="cert-field-value" style="font-family:monospace;color:var(--primary-color,#1a5f2a);"><?= htmlspecialchars($sadasyata ?: '—') ?></span>
          </div>
          <?php if ($fatherName): ?>
          <div class="cert-field">
            <span class="cert-field-label">बुबाको नाम</span>
            <span class="cert-field-value"><?= htmlspecialchars($fatherName) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($dob): ?>
          <div class="cert-field">
            <span class="cert-field-label">जन्म मिति</span>
            <span class="cert-field-value"><?= htmlspecialchars($dob) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($address): ?>
          <div class="cert-field">
            <span class="cert-field-label">स्थायी ठेगाना</span>
            <span class="cert-field-value"><?= htmlspecialchars($address) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($citizenship): ?>
          <div class="cert-field">
            <span class="cert-field-label">नागरिकता नम्बर</span>
            <span class="cert-field-value"><?= htmlspecialchars($citizenship) ?></span>
          </div>
          <?php endif; ?>
          <div class="cert-field">
            <span class="cert-field-label">सदस्य भएको मिति</span>
            <span class="cert-field-value"><?= htmlspecialchars($issueDateNp) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="cert-footer">
      <div>
        <div class="cert-seal"><?= htmlspecialchars(mb_substr($siteName,0,12)) ?><br>सहकारी<br>छाप</div>
      </div>
      <div style="flex:1;text-align:center;">
        <div style="font-size:.75rem;color:#9ca3af;margin-bottom:4px;">जारी मिति: <?= htmlspecialchars($issueDateNp) ?></div>
        <?php if ($sitePhone): ?>
        <div style="font-size:.72rem;color:#9ca3af;"><i class="fas fa-phone" style="margin-right:3px;"></i><?= htmlspecialchars($sitePhone) ?></div>
        <?php endif; ?>
        <div style="margin-top:8px;">
          <div class="cert-sign-line">अध्यक्ष / Chairman</div>
        </div>
      </div>
      <div style="text-align:center;">
        <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR" width="80" height="80" style="display:block;border:1px solid #e5e7eb;border-radius:4px;">
        <div style="font-size:.65rem;color:#9ca3af;margin-top:3px;">Scan to Verify</div>
      </div>
    </div>
  </div>

  <div class="cert-noprint" style="text-align:center;margin-top:16px;font-size:.8rem;color:#9ca3af;">
    <i class="fas fa-info-circle" style="margin-right:4px;"></i>
    Print / PDF download को लागि माथिको "Print / PDF" button थिच्नुहोस्। Browser मा "Save as PDF" option छान्नुहोस्।
  </div>

</div>
</main>
<?php require __DIR__ . '/includes/chrome-foot.php'; ?>
