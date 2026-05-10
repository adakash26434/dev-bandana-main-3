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
  padding: 12px 14px;
  margin-bottom: 14px;
  font-size: .82rem;
  color: #065f46;
  line-height: 1.5;
}
#scan-reader {
    border-radius: 14px;
    overflow: hidden;
    border: 2px solid var(--primary-color);
    background: #0f172a;
    min-height: 280px;
    position: relative;
    box-shadow: 0 8px 32px rgba(0,0,0,.25);
    width: 100%;
}
#scan-reader video {
    width: 100% !important;
    border-radius: 12px;
    display: block;
}
/* Scanning laser line animation */
#scan-reader.scanning::after {
    content: '';
    position: absolute;
    left: 10%; right: 10%;
    top: 30%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #22c55e, transparent);
    box-shadow: 0 0 8px #22c55e;
    animation: scan-laser 1.8s ease-in-out infinite;
    z-index: 10;
    pointer-events: none;
}
@keyframes scan-laser {
    0%   { top: 25%; opacity: 0; }
    10%  { opacity: 1; }
    90%  { opacity: 1; }
    100% { top: 75%; opacity: 0; }
}
/* Camera loading state */
#scan-loading {
    display: none;
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
    font-size: .88rem;
}
#scan-loading i { font-size: 2rem; display: block; margin-bottom: 10px; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.scan-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
.scan-actions button {
    flex: 1;
    min-width: 130px;
    padding: 14px 16px;
    border-radius: 12px;
    font-family: inherit;
    font-weight: 700;
    font-size: .9rem;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all .25s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.scan-btn-start { background: var(--primary-color,#1a8754); color: #fff; }
.scan-btn-start:disabled { opacity: .6; cursor: not-allowed; }
.scan-btn-stop  { background: #f1f5f9; color: #374151; border-color: #cbd5e1; }
.scan-err {
  display: none;
  margin-top: 12px;
  padding: 12px 14px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 10px;
  color: #b91c1c;
  font-size: .85rem;
  line-height: 1.5;
}
.scan-err-retry {
  display: inline-block;
  margin-top: 8px;
  background: var(--primary-color,#1a8754);
  color: #fff;
  border: none;
  border-radius: 8px;
  padding: 7px 16px;
  font-size: .82rem;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}
.scan-success {
  display: none;
  margin-top: 12px;
  padding: 12px 14px;
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 10px;
  color: #15803d;
  font-size: .88rem;
  font-weight: 600;
}
.scan-links { margin-top: 18px; text-align: center; font-size: .82rem; }
.scan-links a { color: var(--primary-color,#1a8754); font-weight: 600; text-decoration: none; }
.scan-links a:hover { text-decoration: underline; }
/* ios safari fix */
video { object-fit: cover; }
</style>
HTML;

require __DIR__ . '/includes/chrome.php';
?>

<main class="mp-main">
<div class="mp-container mp-container-narrow scan-wrap">
  <h1 class="mem-page-title">
    <i class="fas fa-qrcode"></i><?php echo $_t('कार्यक्रम QR स्क्यान', 'Program QR Scan'); ?>
  </h1>

  <div class="scan-hero">
    <strong><?php echo $_t('कसरी?', 'How?'); ?></strong> <?php echo $_t('स्थलमा राखिएको', 'Scan the'); ?> <strong><?php echo $_t('कार्यक्रम QR', 'program QR'); ?></strong> <?php echo $_t('क्यामेराले स्क्यान गर्नुहोस्। पछि खुल्ने पृष्ठमा', 'at the venue with camera. On the next page, press'); ?>
    <strong><?php echo $_t('Check-in / OK', 'Check-in / OK'); ?></strong> <?php echo $_t('थिच्नुहोस् — उपस्थिति Admin र तपाईंको', '— attendance goes to admin and your'); ?> <strong><?php echo $_t('उपस्थिति इतिहास', 'attendance history'); ?></strong><?php echo $_t('मा जान्छ। (Pre-registration मात्र उपस्थिति होइन।)', '. (Pre-registration alone is not attendance.)'); ?>
  </div>

  <div id="scan-loading" style="display:none;text-align:center;padding:20px;color:var(--text-muted,#6b7280);font-size:.88rem;"><i class="fas fa-spinner" style="animation:spin 1s linear infinite;display:block;font-size:1.8rem;margin-bottom:8px;"></i><?php echo $_t('क्यामेरा खुल्दैछ…', 'Opening camera…'); ?></div>
  <div id="scan-reader"></div>
  <div id="scan-err" class="scan-err" role="alert"></div>
  <div id="scan-success" class="scan-success"></div>

  <div class="scan-actions">
    <button type="button" class="mem-submit-btn" id="scanStart" style="font-size:1rem;"><i class="fas fa-camera"></i><?php echo $_t('क्यामेरा सुरु गर्नुहोस् — ट्याप गर्नुहोस्', 'Tap to Start Camera'); ?></button>
    <button type="button" class="scan-btn-stop" id="scanStop" style="display:none;"><i class="fas fa-stop"></i><?php echo $_t('रोक्नुहोस्', 'Stop'); ?></button>
  </div>

  <div class="scan-links">
    <a href="<?= htmlspecialchars($base) ?>member/attend.php"><i class="fas fa-calendar-check me-1"></i><?php echo $_t('उपस्थिति र इतिहास', 'Attendance & History'); ?></a>
  </div>
</div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function(){
  var base      = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
  var readerEl  = document.getElementById('scan-reader');
  var errEl     = document.getElementById('scan-err');
  var successEl = document.getElementById('scan-success');
  var loadEl    = document.getElementById('scan-loading');
  var btnStart  = document.getElementById('scanStart');
  var btnStop   = document.getElementById('scanStop');
  var html5Qr   = null;
  var busy      = false;
  var camRunning = false;

  var msgInvalid = <?= json_encode($_t('यो QR मान्य छैन। Admin ले बनाएको कार्यक्रम QR प्रयोग गर्नुहोस्।', 'This QR is not valid. Use admin-generated program QR.')) ?>;
  var msgLib     = <?= json_encode($_t('स्क्यान लाइब्रेरी लोड हुन सकेन। Internet जाँच गरी पुनः प्रयास गर्नुहोस्।', 'Scanner library failed to load. Check internet and retry.')) ?>;
  var msgCam     = <?= json_encode($_t('क्यामेरा खोल्न सकिएन।', 'Unable to open camera.')) ?>;
  var msgPerm    = <?= json_encode($_t('क्यामेरा अनुमति अस्वीकार भयो। Browser Settings मा गई Camera अनुमति दिनुहोस् र पुनः प्रयास गर्नुहोस्।', 'Camera permission denied. Go to Browser Settings, allow Camera, then retry.')) ?>;
  var msgHttps   = <?= json_encode($_t('क्यामेरा HTTPS मा मात्र काम गर्छ। Secure connection प्रयोग गर्नुहोस्।', 'Camera only works over HTTPS. Use a secure connection.')) ?>;
  var msgRetry   = <?= json_encode($_t('पुनः प्रयास', 'Retry')) ?>;
  var msgScanning = <?= json_encode($_t('QR खोज्दैछ… कार्यक्रम QR सामु राख्नुहोस्।', 'Scanning… Hold program QR in front of camera.')) ?>;
  var msgFound   = <?= json_encode($_t('QR फेला पर्यो! जाँदैछ…', 'QR found! Redirecting…')) ?>;

  function showErr(msg) {
    errEl.innerHTML = msg +
      '<br><button class="scan-err-retry" onclick="retryCamera()">' + msgRetry + '</button>';
    errEl.style.display = 'block';
    successEl.style.display = 'none';
    if (loadEl) loadEl.style.display = 'none';
    readerEl.classList.remove('scanning');
  }
  function hideErr() {
    errEl.style.display = 'none';
    errEl.innerHTML = '';
  }
  function showSuccess(msg) {
    successEl.textContent = msg;
    successEl.style.display = 'block';
  }

  /* Responsive qrbox — 70% of the reader element width, min 200, max 300 */
  function qrboxSize() {
    var w = Math.min(readerEl.clientWidth || 280, 400);
    var s = Math.round(w * 0.72);
    return { width: Math.max(180, Math.min(s, 300)), height: Math.max(180, Math.min(s, 300)) };
  }

  function extractToken(text) {
    var t = (text || '').trim();
    if (!t) return '';
    try {
      var u = new URL(t);
      var q = u.searchParams.get('qr_token') || u.searchParams.get('token');
      if (q) {
        var clean = String(q).replace(/[^a-zA-Z0-9_-]/g, '');
        return /^[a-zA-Z0-9_-]{8,80}$/.test(clean) ? clean : '';
      }
      return '';
    } catch (e) {}
    if (/^[a-zA-Z0-9_-]{8,80}$/.test(t)) return t;
    var parts = t.split(':');
    if (parts.length === 2) {
      var tok = parts[1].trim().replace(/[^a-zA-Z0-9_-]/g, '');
      return /^[a-zA-Z0-9_-]{8,80}$/.test(tok) ? tok : '';
    }
    return '';
  }

  function onScanSuccess(decodedText) {
    if (busy) return;
    busy = true;
    hideErr();
    var token = extractToken(decodedText);
    if (!token) {
      showErr(msgInvalid);
      busy = false;
      return;
    }
    showSuccess(msgFound);
    readerEl.classList.remove('scanning');
    /* Stop camera then redirect */
    stopCamera(function() {
      window.location.href = base + 'member/attend.php?qr_token=' + encodeURIComponent(token) + '&auto=1';
    });
  }

  function stopCamera(cb) {
    if (!html5Qr) { camRunning = false; if (cb) cb(); return; }
    html5Qr.stop().then(function() {
      try { if (typeof html5Qr.clear === 'function') html5Qr.clear(); } catch(e) {}
      html5Qr = null; camRunning = false;
      btnStop.style.display = 'none';
      btnStart.style.display = 'flex';
      if (cb) cb();
    }).catch(function() {
      html5Qr = null; camRunning = false;
      btnStop.style.display = 'none';
      btnStart.style.display = 'flex';
      if (cb) cb();
    });
  }

  function startCamera() {
    if (camRunning) return;
    hideErr();
    if (typeof Html5Qrcode === 'undefined') { showErr(msgLib); return; }
    /* HTTPS check */
    if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
      showErr(msgHttps); return;
    }
    btnStart.disabled = true;
    btnStart.style.display = 'none';
    if (loadEl) loadEl.style.display = 'block';
    readerEl.innerHTML = '';
    html5Qr = new Html5Qrcode('scan-reader');
    var cfg = { fps: 12, qrbox: qrboxSize(), aspectRatio: 1.0 };
    html5Qr.start(
      { facingMode: { ideal: 'environment' } },
      cfg,
      onScanSuccess,
      function() {} /* frame error — ignore */
    ).then(function() {
      camRunning = true;
      busy = false;
      btnStart.disabled = false;
      btnStop.style.display = 'flex';
      if (loadEl) loadEl.style.display = 'none';
      readerEl.classList.add('scanning');
    }).catch(function(e) {
      html5Qr = null;
      btnStart.disabled = false;
      btnStart.style.display = 'flex';
      if (loadEl) loadEl.style.display = 'none';
      var msg = (e && e.message ? e.message : '');
      if (/denied|not allowed|permission/i.test(msg)) {
        showErr(msgPerm);
      } else if (/https/i.test(msg)) {
        showErr(msgHttps);
      } else {
        showErr(msgCam + (msg ? ' — ' + msg : ''));
      }
    });
  }

  window.retryCamera = function() {
    hideErr();
    busy = false;
    stopCamera(function() { startCamera(); });
  };

  btnStart.addEventListener('click', startCamera);
  btnStop.addEventListener('click', function() { stopCamera(); });

  /* Stop camera when user leaves page (back button / tab switch) */
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) stopCamera();
  });
  window.addEventListener('pagehide', function() { stopCamera(); });
})();
</script>

<?php require __DIR__ . '/includes/chrome-foot.php'; ?>
