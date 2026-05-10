<?php
/**
 * Member Portal — कार्यक्रम QR स्क्यान (क्यामेरा)
 * सफल scan पछि attend.php?qr_token=... मा जान्छ — त्यहाँ Check-in (OK) थिच्नुपर्छ।
 */
require_once __DIR__ . '/_bootstrap.php';
requireMemberLogin();
memberSecurityHeaders();

$mem = currentMember();
if (!$mem) {
    header('Location: login.php?msg=session_expired');
    exit;
}
$_t = static function (string $np, string $en): string {
    return isEnglish() ? $en : $np;
};

$siteName  = function_exists('getSetting') ? getSetting('site_name', 'सहकारी') : 'सहकारी';
$pageTitle = $_t('कार्यक्रम QR स्क्यान', 'Program QR Scan') . ' — ' . $siteName;
$base      = rtrim(SITE_URL, '/') . '/';

$extraHead = <<<'HTML'
<style>
.scan-wrap { margin: 0 auto; }
.scan-hero {
  background: linear-gradient(135deg,#ecfdf5,#d1fae5);
  border: 1px solid #6ee7b7;
  border-radius: 14px;
  padding: 14px 16px;
  margin-bottom: 14px;
  font-size: .82rem;
  color: #065f46;
  line-height: 1.5;
}
#scan-reader {
    border-radius: 14px;
    overflow: hidden;
    border: 2px solid var(--primary-color);
    background: linear-gradient(135deg, #0f172a, #1e293b);
    min-height: 300px;
    position: relative;
    box-shadow: 0 8px 32px rgba(var(--primary-rgb), .2);
}
#scan-reader::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
    animation: scan-shimmer 3s infinite;
}
@keyframes scan-shimmer {
    0% { transform: translateX(-100%) translateY(-100%); }
    100% { transform: translateX(100%) translateY(100%); }
}
#scan-reader video { 
    border-radius: 12px; 
    box-shadow: 0 4px 16px rgba(0,0,0,0.3);
}
.scan-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
.scan-actions button {
    flex: 1;
    min-width: 140px;
    padding: 14px 18px;
    border-radius: 12px;
    font-family: inherit;
    font-weight: 700;
    font-size: .95rem;
    border: 2px solid transparent;
    transition: all .3s cubic-bezier(.4,0,.2,1);
    position: relative;
    overflow: hidden;
}
.scan-actions button::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left .5s;
}
.scan-actions button:hover::before {
    left: 100%;
}
.scan-actions button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}
.scan-btn-start { background: var(--primary-color,#1a8754); color: #fff; }
.scan-btn-stop { background: #e5e7eb; color: #374151; }
.scan-err {
  display: none;
  margin-top: 12px;
  padding: 12px 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  color: #b91c1c;
  font-size: .85rem;
}
.scan-links { margin-top: 18px; text-align: center; font-size: .82rem; }
.scan-links a { color: var(--primary-color,#1a8754); font-weight: 600; text-decoration: none; }
.scan-links a:hover { text-decoration: underline; }
</style>
HTML;

require __DIR__ . '/includes/chrome.php';
?>

<main class="mp-main">
<div class="mp-container mp-container-narrow scan-wrap">
  <h1 style="font-size:1.2rem;font-weight:800;color:var(--primary-color,#1a8754);margin:0 0 10px;">
    <i class="fas fa-qrcode" style="margin-right:8px;"></i><?php echo $_t('कार्यक्रम QR स्क्यान', 'Program QR Scan'); ?>
  </h1>

  <div class="scan-hero">
    <strong><?php echo $_t('कसरी?', 'How?'); ?></strong> <?php echo $_t('स्थलमा राखिएको', 'Scan the'); ?> <strong><?php echo $_t('कार्यक्रम QR', 'program QR'); ?></strong> <?php echo $_t('क्यामेराले स्क्यान गर्नुहोस्। पछि खुल्ने पृष्ठमा', 'at the venue with camera. On the next page, press'); ?>
    <strong><?php echo $_t('Check-in / OK', 'Check-in / OK'); ?></strong> <?php echo $_t('थिच्नुहोस् — उपस्थिति Admin र तपाईंको', '— attendance goes to admin and your'); ?> <strong><?php echo $_t('उपस्थिति इतिहास', 'attendance history'); ?></strong><?php echo $_t('मा जान्छ। (Pre-registration मात्र उपस्थिति होइन।)', '. (Pre-registration alone is not attendance.)'); ?>
  </div>

  <div id="scan-reader"></div>
  <div id="scan-err" class="scan-err" role="alert"></div>

  <div class="scan-actions">
    <button type="button" class="scan-btn-start" id="scanStart"><i class="fas fa-camera me-2"></i><?php echo $_t('क्यामेरा सुरु', 'Start Camera'); ?></button>
    <button type="button" class="scan-btn-stop" id="scanStop" style="display:none;"><i class="fas fa-stop me-2"></i><?php echo $_t('रोक्नुहोस्', 'Stop'); ?></button>
  </div>

  <div class="scan-links">
    <a href="<?= htmlspecialchars($base) ?>member/attend.php"><i class="fas fa-calendar-check me-1"></i><?php echo $_t('उपस्थिति र इतिहास', 'Attendance & History'); ?></a>
  </div>
</div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function(){
  var base = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
  var readerEl = document.getElementById('scan-reader');
  var errEl = document.getElementById('scan-err');
  var btnStart = document.getElementById('scanStart');
  var btnStop = document.getElementById('scanStop');
  var html5Qr = null;
  var busy = false;
  var msgInvalid = <?= json_encode($_t('यो QR कार्यक्रम check-in को लागि मान्य देखिँदैन। Admin को कार्यक्रम QR प्रयोग गर्नुहोस्।', 'This QR is not valid for program check-in. Please use admin-generated program QR.')) ?>;
  var msgLib = <?= json_encode($_t('स्क्यान लाइब्रेरी लोड हुन सकेन। इन्टरनेट जाँच गरी पुनः प्रयास गर्नुहोस्।', 'Scanner library failed to load. Check internet and try again.')) ?>;
  var msgCam = <?= json_encode($_t('क्यामेरा खोल्न सकिएन। अनुमति दिनुहोस् वा HTTPS प्रयोग गर्नुहोस्।', 'Unable to open camera. Allow permission or use HTTPS.')) ?>;

  function showErr(msg) {
    errEl.textContent = msg;
    errEl.style.display = 'block';
  }
  function hideErr() {
    errEl.style.display = 'none';
    errEl.textContent = '';
  }

  function extractToken(text) {
    var t = (text || '').trim();
    if (!t) return '';
    // 1. Try as URL — extract qr_token/token query param
    try {
      var u = new URL(t);
      var q = u.searchParams.get('qr_token') || u.searchParams.get('token');
      if (q) {
        var clean = String(q).replace(/[^a-zA-Z0-9_-]/g, '');
        return /^[a-zA-Z0-9_-]{8,80}$/.test(clean) ? clean : '';
      }
      // It's a URL but has no token param — invalid QR
      return '';
    } catch (e) {}
    // 2. Plain token string (not a URL)
    if (/^[a-zA-Z0-9_-]{8,80}$/.test(t)) return t;
    // 3. Colon-separated "PROG:TOKEN" format
    var parts = t.split(':');
    if (parts.length === 2) {
      var tok = parts[1].trim().replace(/[^a-zA-Z0-9_-]/g, '');
      return /^[a-zA-Z0-9_-]{8,80}$/.test(tok) ? tok : '';
    }
    return '';
  }

  function validateAndAutoAttend(decodedText) {
    var token = extractToken(decodedText);
    if (!token) return false;
    
    // Redirect to attend.php with QR token for auto-processing
    window.location.href = base + 'member/attend.php?qr_token=' + encodeURIComponent(token) + '&auto=1';
    return true;
  }

  function onScanSuccess(decodedText, result) {
    if (busy) return;
    busy = true;
    hideErr();
    
    // Validate and auto-attend QR code
    if (validateAndAutoAttend(decodedText)) {
        return;
    }
    
    // If validation fails, show error and reset busy state
    showErr(msgInvalid);
    busy = false;
  }

  btnStart.addEventListener('click', function() {
    hideErr();
    if (typeof Html5Qrcode === 'undefined') {
      showErr(msgLib);
      return;
    }
    btnStart.disabled = true;
    html5Qr = new Html5Qrcode('scan-reader');
    var cfg = { fps: 10, qrbox: { width: 260, height: 260 } };
    html5Qr.start(
      { facingMode: 'environment' },
      cfg,
      onScanSuccess,
      function() {}
    ).then(function() {
      btnStart.style.display = 'none';
      btnStop.style.display = 'inline-block';
      btnStart.disabled = false;
    }).catch(function(e) {
      btnStart.disabled = false;
      showErr(msgCam + ' (' + (e && e.message ? e.message : 'unknown') + ')');
    });
  });

  btnStop.addEventListener('click', function() {
    if (!html5Qr) return;
    html5Qr.stop().then(function() {
      try { if (typeof html5Qr.clear === 'function') html5Qr.clear(); } catch (e) {}
      html5Qr = null;
      btnStop.style.display = 'none';
      btnStart.style.display = 'inline-block';
    }).catch(function() {
      html5Qr = null;
      btnStop.style.display = 'none';
      btnStart.style.display = 'inline-block';
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/chrome-foot.php'; ?>
