<?php
/**
 * Member Portal — Password Reset (OTP Self-Service + Admin Fallback)
 * Steps:
 *   1. Enter email or sadasyata number → pick channel (SMS/email)
 *   2. Enter 6-digit OTP
 *   3. Set new password
 */
define('MEMBER_PORTAL', true);
/* v2: bootstrap ले config + member-auth + global error guard load गर्छ */
require_once __DIR__ . '/_bootstrap.php';
if (file_exists(__DIR__ . '/../includes/notifications.php')) require_once __DIR__ . '/../includes/notifications.php';

memberSecurityHeaders();

if (isset($_SESSION['member_id'])) {
    header('Location: index.php'); exit;
}

$step     = intval($_SESSION['pr_step'] ?? 1);
$error    = '';
$success  = '';
$siteName = getSetting('site_name', 'आकाश सहकारी');
$smsEnabled = getSetting('notify_sms_enabled','0')==='1' && getSetting('notify_sms_token','')!=='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* CSRF guard — token provided by csrfField() in every form */
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) { $error = 'सुरक्षा जाँच असफल। पेज refresh गरेर पुनः प्रयास गर्नुहोस्।'; }
    $action = empty($error) ? ($_POST['action'] ?? '') : '';

    /* ── Step 1: Find member & send OTP ── */
    if ($action === 'send_otp') {
        $identifier = trim($_POST['identifier'] ?? '');
        $channel    = in_array($_POST['channel'] ?? '', ['sms','email']) ? $_POST['channel'] : 'auto';
        if (!$identifier) {
            $error = 'Email वा Sadasyata Number लेख्नुहोस्।';
        } else {
            $member = findMemberForReset($identifier);
            if (!$member) {
                $error = 'यो email वा sadasyata number मा कुनै खाता भेटिएन।';
            } else {
                $result = dispatchOTP($member, $channel);
                if ($result['sent']) {
                    $_SESSION['pr_member_id'] = $member['id'];
                    $_SESSION['pr_channel']   = $result['channel'];
                    $_SESSION['pr_sent_to']   = $result['sent_to'];
                    $_SESSION['pr_step']      = 2;
                    $step    = 2;
                    $success = "OTP पठाइयो — " . htmlspecialchars($result['sent_to']) . " मा।";
                } elseif ($result['channel'] === 'none') {
                    $error = 'SMS/Email gateway configure भएको छैन। Admin लाई request पठाउनुहोस्।';
                } else {
                    $error = 'OTP पठाउन सकिएन। केही समय पछि पुनः प्रयास गर्नुहोस्।';
                }
            }
        }
    }

    /* ── Step 2: Verify OTP ── */
    elseif ($action === 'verify_otp') {
        $memberId = intval($_SESSION['pr_member_id'] ?? 0);
        $otp      = trim($_POST['otp'] ?? '');
        if (!$memberId) { $error = 'Session expired. फेरि सुरु गर्नुहोस्।'; $step = 1; }
        elseif (!preg_match('/^\d{6}$/', $otp)) { $error = 'OTP 6 अंकको हुनुपर्छ।'; }
        else {
            if (verifyOTP($memberId, $otp)) {
                $_SESSION['pr_otp_ok'] = true;
                $_SESSION['pr_step']   = 3;
                $step    = 3;
                $success = 'OTP verified! अब नयाँ पासवर्ड राख्नुहोस्।';
            } else {
                $error = 'OTP गलत वा expire भयो।';
            }
        }
    }

    /* ── Step 3: Set new password ── */
    elseif ($action === 'set_password') {
        $memberId = intval($_SESSION['pr_member_id'] ?? 0);
        $otpOk    = !empty($_SESSION['pr_otp_ok']);
        $pass1    = $_POST['password'] ?? '';
        $pass2    = $_POST['password_confirm'] ?? '';
        if (!$memberId || !$otpOk) {
            $error = 'Session expired.'; $step = 1;
            unset($_SESSION['pr_member_id'],$_SESSION['pr_otp_ok'],$_SESSION['pr_step']);
        } elseif (strlen($pass1) < 6) {
            $error = 'पासवर्ड कम्तिमा 6 अक्षरको हुनुपर्छ।'; $step = 3;
        } elseif ($pass1 !== $pass2) {
            $error = 'दुवै पासवर्ड मेल खाएनन्।'; $step = 3;
        } else {
            $db   = getDB();
            $hash = password_hash($pass1, PASSWORD_DEFAULT);
            $db->prepare("UPDATE members SET password_hash=? WHERE id=?")->execute([$hash, $memberId]);
            unset($_SESSION['pr_member_id'],$_SESSION['pr_channel'],$_SESSION['pr_sent_to'],
                  $_SESSION['pr_otp_ok'],$_SESSION['pr_step']);
            $step = 'done';
        }
    }

    /* ── Resend OTP ── */
    elseif ($action === 'resend_otp') {
        $memberId = intval($_SESSION['pr_member_id'] ?? 0);
        $channel  = $_SESSION['pr_channel'] ?? 'auto';
        if ($memberId) {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id,name,email,phone FROM members WHERE id=?");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($member) {
                $result = dispatchOTP($member, $channel);
                $step   = 2; $_SESSION['pr_step'] = 2;
                if ($result['sent']) $success = 'OTP फेरि पठाइयो।';
                else $error = 'OTP पठाउन सकिएन।';
            }
        }
    }

    /* ── Admin Fallback: SMS/Email नभएमा admin लाई manual reset request पठाउने ── */
    elseif ($action === 'admin_fallback') {
        $identifier = trim($_POST['identifier'] ?? '');
        if (!$identifier) { $error = 'Email वा Sadasyata Number लेख्नुहोस्।'; }
        else {
            $member = findMemberForReset($identifier);
            if (!$member) { $error = 'यो email वा sadasyata number मा कुनै खाता भेटिएन।'; }
            else {
                $r = memberRequestPasswordReset($member['id']);
                if (!empty($r['success'])) {
                    /* Try to notify admin (email/in-app — best-effort, silent on failure) */
                    if (function_exists('notifyAdminOfPasswordResetRequest')) {
                        try { notifyAdminOfPasswordResetRequest($member); } catch (\Throwable $e) {}
                    }
                    $success = 'तपाईंको पासवर्ड Reset अनुरोध Admin लाई पठाइयो। Admin ले प्रक्रिया गरेपछि कार्यालयबाट सम्पर्क हुनेछ।';
                    $step = 'done';
                } elseif (!empty($r['error'])) { $error = $r['error']; }
                else { $error = 'अनुरोध पठाउन सकिएन। पछि पुनः प्रयास गर्नुहोस्।'; }
            }
        }
    }

    /* ── Cancel ── */
    elseif ($action === 'cancel') {
        unset($_SESSION['pr_member_id'],$_SESSION['pr_channel'],$_SESSION['pr_sent_to'],
              $_SESSION['pr_otp_ok'],$_SESSION['pr_step']);
        $step = 1;
    }
}

if (!$step) $step = intval($_SESSION['pr_step'] ?? 1);
?>
<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>पासवर्ड रिसेट — <?php echo htmlspecialchars($siteName); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php memberHeadAssets(); ?>
<style>
body{background:linear-gradient(135deg,var(--bg-muted,#e8f5e9),var(--bg-soft,#f0fdf4) 60%,#e3f2fd);min-height:100vh;font-family:var(--font-primary,'Mukta','Noto Sans Devanagari','Segoe UI',sans-serif);color:var(--text-primary,#1a2e1f);}
.card{max-width:440px;margin:56px auto 40px;border-radius:18px;box-shadow:0 8px 40px rgba(0,0,0,.10);border:none;}
.card-header{background:var(--primary-color);color:#fff;padding:26px 28px 18px;text-align:center;border-radius:18px 18px 0 0;}
.card-header h4{margin:0;font-weight:700;}
.step-bar{display:flex;gap:6px;margin-bottom:22px;}
.step-dot{flex:1;height:4px;border-radius:4px;background:var(--border-color,#e5e7eb);transition:.3s;}
.step-dot.done{background:var(--primary-color);}
.step-dot.active{background:var(--primary-light,#4ade80);}
.otp-input{letter-spacing:12px;font-size:2rem;font-weight:700;text-align:center;padding:12px;}
.channel-opt input{display:none;}
.channel-opt label{border:2px solid var(--border-color,#e5e7eb);border-radius:10px;padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:8px;transition:.2s;font-size:.9rem;}
.channel-opt input:checked+label{border-color:var(--primary-color);background:var(--bg-soft,#f0fdf4);}
.btn-g{background:var(--primary-color);color:#fff;border:none;}
.btn-g:hover{background:var(--primary-dark,#155222);color:#fff;}
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div style="font-size:2rem;margin-bottom:6px;"><i class="fas fa-shield-alt"></i></div>
    <h4>पासवर्ड रिसेट</h4>
    <p class="mb-0 mt-1 opacity-75 small"><?php echo htmlspecialchars($siteName); ?> — Member Portal</p>
  </div>
  <div class="card-body p-4">

    <?php if ($error): ?>
    <div class="alert alert-danger border-0 small py-2"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success border-0 small py-2"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($step !== 'done'): ?>
    <div class="step-bar">
      <div class="step-dot <?php echo $step>=1 ? ($step>1?'done':'active') : ''; ?>"></div>
      <div class="step-dot <?php echo $step>=2 ? ($step>2?'done':'active') : ''; ?>"></div>
      <div class="step-dot <?php echo $step>=3 ? ($step>3?'done':'active') : ''; ?>"></div>
    </div>
    <p class="text-muted small mb-3">
      <?php echo $step==1
        ? '<i class="fas fa-search me-1"></i>चरण १: खाता पहिचान'
        : ($step==2
          ? '<i class="fas fa-key me-1"></i>चरण २: OTP Verify'
          : '<i class="fas fa-lock me-1"></i>चरण ३: नयाँ पासवर्ड'); ?>
    </p>
    <?php endif; ?>

    <?php if ($step == 1): /* ═══ STEP 1 ═══ */ ?>
    <form method="POST" novalidate class="needs-validation" autocomplete="off">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="send_otp">
      <div class="mb-3">
        <label class="form-label fw-semibold small">Email वा Sadasyata Number <span class="text-danger">*</span></label>
        <input type="text" name="identifier" class="form-control"
               placeholder="example@email.com वा MEM-2081-001"
               value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>" required autofocus>
        <div class="form-text">Register गर्दा प्रयोग गरेको email वा सदस्यता नम्बर।</div>
      </div>
      <?php if ($smsEnabled): ?>
      <div class="mb-4">
        <label class="form-label fw-semibold small">OTP कहाँ पठाउने?</label>
        <div class="d-flex gap-2">
          <div class="flex-1 channel-opt" style="flex:1">
            <input type="radio" name="channel" id="ch_sms" value="sms" checked>
            <label for="ch_sms" class="w-100 justify-content-center">
              <i class="fas fa-mobile-alt text-success fs-5"></i>
              <div><strong class="d-block">SMS</strong><small class="text-muted">Mobile मा</small></div>
            </label>
          </div>
          <div class="flex-1 channel-opt" style="flex:1">
            <input type="radio" name="channel" id="ch_email" value="email">
            <label for="ch_email" class="w-100 justify-content-center">
              <i class="fas fa-envelope text-primary fs-5"></i>
              <div><strong class="d-block">Email</strong><small class="text-muted">Email मा</small></div>
            </label>
          </div>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="channel" value="email">
      <?php endif; ?>
      <button class="btn btn-g w-100 py-2 fw-semibold">
        <i class="fas fa-paper-plane me-2"></i>OTP पठाउनुहोस्
      </button>
    </form>

    <!-- Admin Fallback: SMS/Email नभएमा वा OTP नआएमा -->
    <div class="text-center mt-3 pt-3 border-top">
      <p class="text-muted small mb-2">OTP प्राप्त भएन वा SMS/Email सेवा छैन?</p>
      <form method="POST" novalidate class="needs-validation" onsubmit="return confirm('Admin लाई पासवर्ड Reset अनुरोध पठाउने? (कार्यालयबाट सम्पर्क हुनेछ)');" style="display:inline;">
      <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="admin_fallback">
        <input type="hidden" name="identifier" value="<?php echo htmlspecialchars($_POST['identifier'] ?? ''); ?>">
        <button type="submit" class="btn btn-outline-secondary btn-sm">
          <i class="fas fa-user-shield me-1"></i>Admin लाई अनुरोध पठाउनुहोस्
        </button>
      </form>
      <div class="form-text mt-2">माथि email/sadasyata नम्बर भर्नुहोस्, अनि यो button थिच्नुहोस्।</div>
    </div>

    <?php elseif ($step === 'done'): ?>
    <div class="text-center py-3">
      <div style="font-size:3rem;color:#16a34a;"><i class="fas fa-check-circle"></i></div>
      <h5 class="mt-2">अनुरोध सफलतापूर्वक पठाइयो!</h5>
      <p class="text-muted small">Admin ले तपाईंको अनुरोध समीक्षा गर्नेछन्। नयाँ पासवर्द कार्यालयबाट प्राप्त गर्न सकिन्छ।</p>
      <a href="login.php" class="btn btn-g mt-2"><i class="fas fa-sign-in-alt me-1"></i>Login मा फर्कनुहोस्</a>
    </div>

    <?php elseif ($step == 2): /* ═══ STEP 2 ═══ */ ?>
    <?php $sentTo = $_SESSION['pr_sent_to'] ?? ''; $ch = $_SESSION['pr_channel'] ?? 'sms'; ?>
    <p class="text-center text-muted small mb-3">
      <i class="fas fa-<?php echo $ch==='sms'?'mobile-alt':'envelope'; ?> me-1 text-success"></i>
      <?php echo $ch==='sms'?'Mobile':'Email'; ?> <strong><?php echo htmlspecialchars($sentTo); ?></strong> मा
      6-digit OTP पठाइएको छ। 10 मिनेटभित्र enter गर्नुहोस्।
    </p>
    <form method="POST" novalidate class="needs-validation" autocomplete="off" id="otpForm">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="verify_otp">
      <div class="mb-4">
        <input type="text" name="otp" id="otpInput" class="form-control otp-input"
               placeholder="000000" maxlength="6" pattern="\d{6}"
               inputmode="numeric" autofocus required>
      </div>
      <button class="btn btn-g w-100 py-2 fw-semibold mb-2">
        <i class="fas fa-check-circle me-2"></i>OTP Verify गर्नुहोस्
      </button>
    </form>
    <div class="d-flex gap-2">
      <form method="POST" novalidate class="needs-validation" class="flex-1">
      <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="resend_otp">
        <button class="btn btn-outline-secondary btn-sm w-100">
          <i class="fas fa-redo me-1"></i>फेरि पठाउनुहोस्
        </button>
      </form>
      <form method="POST" novalidate class="needs-validation" class="flex-1">
      <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="cancel">
        <button class="btn btn-outline-danger btn-sm w-100">रद्द गर्नुहोस्</button>
      </form>
    </div>

    <?php elseif ($step == 3): /* ═══ STEP 3 ═══ */ ?>
    <div class="alert alert-success border-0 small py-2 text-center">
      <i class="fas fa-check-circle me-1"></i>OTP verified! अब नयाँ पासवर्ड राख्नुहोस्।
    </div>
    <form method="POST" novalidate class="needs-validation" autocomplete="off">
      <?php echo csrfField(); ?>
      <input type="hidden" name="action" value="set_password">
      <div class="mb-3">
        <label class="form-label fw-semibold small">नयाँ पासवर्ड <span class="text-danger">*</span></label>
        <div class="input-group">
          <input type="password" name="password" id="pw1" class="form-control"
                 placeholder="कम्तिमा 6 अक्षर" minlength="6" required autofocus>
          <button type="button" class="btn btn-outline-secondary" onclick="tpw('pw1')">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label fw-semibold small">पासवर्ड पुष्टि गर्नुहोस् <span class="text-danger">*</span></label>
        <div class="input-group">
          <input type="password" name="password_confirm" id="pw2" class="form-control"
                 placeholder="फेरि लेख्नुहोस्" minlength="6" required>
          <button type="button" class="btn btn-outline-secondary" onclick="tpw('pw2')">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>
      <button class="btn btn-g w-100 py-2 fw-semibold">
        <i class="fas fa-save me-2"></i>पासवर्ड परिवर्तन गर्नुहोस्
      </button>
    </form>

    <?php elseif ($step === 'done'): /* ═══ DONE ═══ */ ?>
    <div class="text-center py-4">
      <div style="font-size:3.5rem;color:#22c55e"><i class="fas fa-check-circle"></i></div>
      <h5 class="mt-3 fw-bold">पासवर्ड सफलतापूर्वक परिवर्तन!</h5>
      <p class="text-muted small mt-2">तपाईं अब नयाँ पासवर्डले login गर्न सक्नुहुन्छ।</p>
      <a href="login.php" class="btn btn-g px-4 py-2 fw-semibold mt-2">
        <i class="fas fa-sign-in-alt me-2"></i>Login गर्नुहोस्
      </a>
    </div>
    <?php endif; ?>

    <?php if ($step == 1 || $step == 2): ?>
    <div class="text-center mt-4 pt-3 border-top">
      <a href="login.php" class="text-success text-decoration-none small">
        <i class="fas fa-arrow-left me-1"></i>Login पेजमा फर्कनुहोस्
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>
<script>
function tpw(id){var e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}
var oi=document.getElementById('otpInput');
if(oi){oi.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,6);if(this.value.length===6)document.getElementById('otpForm').submit();});}
</script>
</body>
</html>
