<?php
/**
 * Member Login — आकाश बचत तथा ऋण सहकारी संस्था
 * v5 Phase 2 — Split-screen redesign (left visual + right form)
 * Original logic + features preserved exactly.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/totp-2fa.php';
$_t = static function (string $np, string $en): string {
    return isEnglish() ? $en : $np;
};

memberSecurityHeaders();

if (memberIsLoggedIn()) {
    memberSafeRedirect(SITE_URL . 'member/');
}

$tab = $_GET['tab'] ?? 'login';
if ($tab !== 'register') {
    $tab = 'login';
}
$error   = '';
$success = '';
$info    = '';

/* ── POST: Login ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['do_login'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = $_t('Security error. Page refresh गर्नुहोस्।', 'Security error. Please refresh the page.');
    } else {
        $loginId  = trim($_POST['login_id'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$loginId || !$password) {
            $error = $_t('इमेल/सदस्यता नम्बर र पासवर्ड आवश्यक छ।', 'Email/member number and password are required.');
        } elseif (function_exists('checkLoginAttempts') && !checkLoginAttempts($loginId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')) {
            $error = $_t('धेरै पटक गलत प्रयास भयो। कृपया १५ मिनेट पछि पुनः प्रयास गर्नुहोस्।', 'Too many failed attempts. Please try again after 15 minutes.');
        } elseif (function_exists('checkRateLimit') && !checkRateLimit('member_login_pw', 5, 900)) {
            $error = $_t('धेरै पटक प्रयास भयो। कृपया केही समय पछि पुनः प्रयास गर्नुहोस्।', 'Too many attempts. Please try again later.');
        } else {
            $res = memberLogin($loginId, $password, true);
            $ipLogin = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $credOkErrors = ['pending_approval', 'rejected', 'renewal_required'];
            if (isset($res['error'])) {
                if (!in_array($res['error'], $credOkErrors, true) && function_exists('recordLoginAttempt')) {
                    recordLoginAttempt($loginId, $ipLogin);
                } elseif (in_array($res['error'], $credOkErrors, true) && function_exists('resetLoginAttempts')) {
                    resetLoginAttempts($loginId, $ipLogin);
                }
            } elseif (function_exists('resetLoginAttempts')) {
                resetLoginAttempts($loginId, $ipLogin);
            }
            if (isset($res['error'])) {
                if ($res['error'] === 'pending_approval') {
                    $info = 'pending';
                } elseif ($res['error'] === 'rejected') {
                    $info  = 'rejected';
                    $error = $_t('❌ तपाईंको दर्ता अस्वीकृत भएको छ।', '❌ Your registration has been rejected.')
                           . (!empty($res['reason']) ? ($_t(' कारण: ', ' Reason: ') . htmlspecialchars($res['reason'])) : '')
                           . $_t(' थप जानकारीका लागि कार्यालयमा सम्पर्क गर्नुहोस्।', ' Please contact office for more details.');
                } elseif ($res['error'] === 'renewal_required') {
                    /* Issue #3: 5-year card म्याद सकियो */
                    $info  = 'renewal';
                    $error = $_t('🔄 तपाईंको Member Card को ५ बर्षे म्याद सकिएको छ। कार्यालयमा सम्पर्क गरी renew गर्नुहोस् — Admin ले approve गरेपछि feri active हुनेछ।', '🔄 Your member card has expired after 5 years. Please contact office for renewal — it will be active again after admin approval.');
                } else {
                    $error = htmlspecialchars($res['error']);
                }
            } else {
                $m = $res['member'] ?? null;
                $twoFaRequired = (getSetting('twofa_member_required', '0') === '1');
                if ($twoFaRequired && is_array($m)) {
                    $rawNext2fa  = (string)($_GET['next'] ?? '');
                    $siteHost2fa = parse_url(SITE_URL, PHP_URL_HOST);
                    $nextP2fa    = parse_url($rawNext2fa);
                    $safeNext2fa = ($rawNext2fa !== '' && (empty($nextP2fa['host']) || $nextP2fa['host'] === $siteHost2fa))
                        ? $rawNext2fa
                        : '';
                    $secret = trim((string)($m['twofa_secret'] ?? ''));
                    $enabled = (int)($m['twofa_enabled'] ?? 0) === 1 && $secret !== '';
                    if (!$enabled) {
                        if ($secret === '') $secret = twoFaGenerateSecret(32);
                        $_SESSION['member_2fa_pending'] = [
                            'id' => (int)$m['id'],
                            'mode' => 'setup',
                            'secret' => $secret,
                            'next' => $safeNext2fa,
                        ];
                        $info = 'twofa_setup_required';
                    } else {
                        $_SESSION['member_2fa_pending'] = [
                            'id' => (int)$m['id'],
                            'mode' => 'verify',
                            'next' => $safeNext2fa,
                        ];
                        $info = 'twofa_verify_required';
                    }
                } else {
                    if (is_array($m)) {
                        memberSetSession($m);
                        try { getDB()->prepare("UPDATE members SET last_login=NOW() WHERE id=?")->execute([(int)$m['id']]); } catch (Throwable $e) {}
                    }
                    $rawNext  = $_GET['next'] ?? '';
                    $siteHost = parse_url(SITE_URL, PHP_URL_HOST);
                    $nextP    = parse_url($rawNext);
                    $next     = ($rawNext && (empty($nextP['host']) || $nextP['host'] === $siteHost)) ? $rawNext : SITE_URL . 'member/';
                    memberSafeRedirect($next);
                }
            }
        }
    }
}

/* ── POST: Member 2FA verify/setup ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['do_member_2fa'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = $_t('Security error. Page refresh गर्नुहोस्।', 'Security error. Please refresh the page.');
    } else {
        $pending = $_SESSION['member_2fa_pending'] ?? null;
        if (!is_array($pending) || empty($pending['id'])) {
            $error = $_t('2FA session समाप्त भयो। पुन: login गर्नुहोस्।', '2FA session expired. Please login again.');
        } else {
            try {
                $db = getDB();
                $st = $db->prepare("SELECT * FROM members WHERE id=? AND is_active=1 LIMIT 1");
                $st->execute([(int)$pending['id']]);
                $m = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                if (!$m) {
                    $error = $_t('Member account भेटिएन।', 'Member account not found.');
                } else {
                    $code = trim((string)($_POST['twofa_code'] ?? ''));
                    $mode = (string)($pending['mode'] ?? 'verify');
                    $ok = false;
                    if ($mode === 'setup') {
                        $secret = trim((string)($pending['secret'] ?? ''));
                        if ($secret === '') {
                            $error = $_t('2FA setup secret भेटिएन।', '2FA setup secret not found.');
                        } elseif (!twoFaVerifyCode($secret, $code, 1)) {
                            $error = $_t('2FA code मिलेन। फेरि प्रयास गर्नुहोस्।', '2FA code did not match. Please try again.');
                        } else {
                            $bk = twoFaGenerateBackupCodes(8);
                            $db->prepare("UPDATE members SET twofa_enabled=1, twofa_secret=?, twofa_backup_codes=?, twofa_enabled_at=NOW() WHERE id=?")
                               ->execute([$secret, json_encode($bk['hashes']), (int)$m['id']]);
                            $_SESSION['member_2fa_backup_plain'] = $bk['plain'];
                            $ok = true;
                        }
                    } else {
                        $secret = trim((string)($m['twofa_secret'] ?? ''));
                        if ($secret !== '' && twoFaVerifyCode($secret, $code, 1)) {
                            $ok = true;
                        } else {
                            $hashes = json_decode((string)($m['twofa_backup_codes'] ?? '[]'), true);
                            if (!is_array($hashes)) $hashes = [];
                            $consumed = twoFaConsumeBackupCode($code, $hashes);
                            if (!empty($consumed['ok'])) {
                                $db->prepare("UPDATE members SET twofa_backup_codes=? WHERE id=?")
                                   ->execute([json_encode($consumed['hashes']), (int)$m['id']]);
                                $ok = true;
                            }
                        }
                        if (!$ok) $error = $_t('2FA code वा backup code मिलेन।', '2FA code or backup code did not match.');
                    }

                    if ($ok) {
                        unset($_SESSION['member_2fa_pending']);
                        memberSetSession($m);
                        try { $db->prepare("UPDATE members SET last_login=NOW() WHERE id=?")->execute([(int)$m['id']]); } catch (Throwable $e) {}
                        $rawNext  = (string)($pending['next'] ?? '');
                        $siteHost = parse_url(SITE_URL, PHP_URL_HOST);
                        $nextP    = parse_url($rawNext);
                        $next     = ($rawNext && (empty($nextP['host']) || $nextP['host'] === $siteHost)) ? $rawNext : SITE_URL . 'member/';
                        memberSafeRedirect($next);
                    }
                }
            } catch (Throwable $e) {
                $error = $_t('2FA verify गर्दा त्रुटि भयो।', 'An error occurred while verifying 2FA.');
            }
        }
    }
}

/* ── POST: Register ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['do_register'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = $_t('Security error. Page refresh गर्नुहोस्।', 'Security error. Please refresh the page.');
        $tab   = 'register';
    } else {
        $name      = '';
        $sadasyata = trim($_POST['sadasyata_number'] ?? '');
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $phone     = trim($_POST['phone']            ?? '');
        $password  = $_POST['password']              ?? '';
        $confirm   = $_POST['confirm']               ?? '';
        $tab       = 'register';

        if (!$sadasyata) {
            $error = $_t('सदस्यता नम्बर अनिवार्य छ।', 'Member number is required.');
        } elseif (!$email || !$phone) {
            $error = $_t('दर्ताका लागि इमेल र मोबाइल दुवै अनिवार्य छन्।', 'Both email and mobile are required for registration.');
        } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = $_t('मान्य इमेल ठेगाना राख्नुहोस्।', 'Please enter a valid email address.');
        } elseif ($phone && !preg_match('/^[0-9]{7,15}$/', $phone)) {
            $error = $_t('मान्य मोबाइल नम्बर राख्नुहोस्।', 'Please enter a valid mobile number.');
        } elseif (strlen($password) < 8) {
            $error = $_t('पासवर्ड कम्तिमा ८ अक्षरको हुनुपर्छ।', 'Password must be at least 8 characters.');
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $error = 'पासवर्डमा कम्तिमा एउटा Capital letter (A-Z) हुनुपर्छ।';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $error = 'पासवर्डमा कम्तिमा एउटा small letter (a-z) हुनुपर्छ।';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'पासवर्डमा कम्तिमा एउटा digit (0-9) हुनुपर्छ।';
        } elseif ($password !== $confirm) {
            $error = $_t('दुवै पासवर्ड मेल खाएनन्।', 'Passwords do not match.');
        } else {
            $db = getDB();
            $kycMatched = false;
            $kycRow = null;
            try {
                $memberIdCol = '';
                foreach (['member_id', 'sadasyata_number'] as $c) {
                    try {
                        $cc = $db->query("SHOW COLUMNS FROM kyc_applications LIKE " . $db->quote($c));
                        if ($cc && $cc->fetch(PDO::FETCH_ASSOC)) { $memberIdCol = $c; break; }
                    } catch (Throwable $ignored) {}
                }

                if ($memberIdCol === '') {
                    $error = $_t('KYC schema मा Member ID field छैन। Admin ले DB setup/migration चलाउनुहोस्।', 'KYC schema is missing member ID field. Please ask admin to run DB setup/migration.');
                }

                $kycSql = "SELECT id, full_name, email, mobile, status FROM kyc_applications
                           WHERE LOWER(email) = ? AND mobile = ?";
                $kycParams = [strtolower($email), preg_replace('/[^0-9]/', '', $phone)];
                if (!$error && $memberIdCol !== '') {
                    $kycSql .= " AND {$memberIdCol} = ?";
                    $kycParams[] = $sadasyata;
                }
                if (!$error) {
                    $kycSql .= " ORDER BY id DESC LIMIT 1";
                    $kycSt = $db->prepare($kycSql);
                    $kycSt->execute($kycParams);
                    $kycRow = $kycSt->fetch(PDO::FETCH_ASSOC) ?: null;
                    $kycMatched = (bool)$kycRow;
                }
            } catch (Throwable $e) {
                $kycMatched = false;
            }
            if (!$kycMatched) {
                $error = $_t('KYC रेकर्ड फेला परेन। सदस्यता नम्बर + इमेल + मोबाइल KYC विवरणसँग मिलेको छैन।', 'KYC record not found. Member number + email + mobile do not match KYC details.');
            } elseif (($kycRow['status'] ?? '') === 'rejected') {
                $error = $_t('तपाईंको KYC अस्वीकृत छ। कृपया सहकारीमा सम्पर्क गरी KYC अपडेट गर्नुहोस्।', 'Your KYC is rejected. Please contact cooperative and update KYC.');
            }
            if (!$error && $kycRow) {
                // Signup data KYC बाट नै लिने — duplicate typing हटाउने
                $name  = trim($kycRow['full_name'] ?? '');
                $email = strtolower(trim($kycRow['email'] ?? $email));
                $phone = preg_replace('/[^0-9]/', '', (string)($kycRow['mobile'] ?? $phone));
                if ($name === '') $error = $_t('KYC record मा नाम खाली छ। कृपया KYC update गर्नुहोस्।', 'Name is empty in KYC record. Please update KYC.');
            }
            if ($email) {
                $chk = $db->prepare("SELECT id FROM members WHERE email=? LIMIT 1");
                $chk->execute([$email]);
                if ($chk->fetch()) $error = $_t('यो इमेल पहिले नै दर्ता भएको छ।', 'This email is already registered.');
            }
            if (!$error && $phone) {
                $chk2 = $db->prepare("SELECT id FROM members WHERE phone=? LIMIT 1");
                $chk2->execute([$phone]);
                if ($chk2->fetch()) $error = $_t('यो मोबाइल नम्बर पहिले नै दर्ता भएको छ।', 'This mobile number is already registered.');
            }
            if (!$error) {
                $chkS = $db->prepare("SELECT id FROM members WHERE sadasyata_number=? AND sadasyata_number!='' LIMIT 1");
                $chkS->execute([$sadasyata]);
                if ($chkS->fetch()) $error = $_t('यो सदस्यता नम्बर पहिले नै दर्ता भएको छ।', 'This member number is already registered.');
            }
        }

        if (!$error) {
            $res = memberRegister($name, $email, $phone, $password, $sadasyata, null, null, '', (int)($kycRow['id'] ?? 0));
            if (isset($res['error'])) {
                $error = htmlspecialchars($res['error']);
            } else {
                $tab     = 'login';
                $success = $_t('✅ दर्ता सफल! KYC विवरणबाट प्रोफाइल स्वतः ल्याइयो। Admin अनुमोदनपछि लगिन गर्न सक्नुहुन्छ।', '✅ Registration successful! Profile was auto-filled from KYC. You can login after admin approval.');
            }
        }
    }
}

$siteName  = getSetting('site_name', $_t('आकाश बचत तथा ऋण सहकारी संस्था', 'Aakash Savings and Credit Cooperative'));
$siteUrl   = SITE_URL;
$logoPath  = function_exists('getLocalizedLogoPath')
    ? trim((string) getLocalizedLogoPath('assets/images/logo.png'))
    : trim((string) getSetting('site_logo', getSetting('logo', 'assets/images/logo.png')));
$googleUrl = function_exists('googleOAuthUrl')   ? googleOAuthUrl()   : '';
$fbUrl     = function_exists('facebookOAuthUrl') ? facebookOAuthUrl() : '';
$csrf      = generateCSRFToken();
$member2faPending = $_SESSION['member_2fa_pending'] ?? null;
$member2faSetupUri = '';
if (is_array($member2faPending) && (($member2faPending['mode'] ?? '') === 'setup')) {
    $issuer = getSetting('site_name', 'Aakash Cooperative');
    $label = 'member-' . (int)($member2faPending['id'] ?? 0);
    $secret = (string)($member2faPending['secret'] ?? '');
    if ($secret !== '') $member2faSetupUri = twoFaProvisioningUri($issuer, $label, $secret);
}

$logoSrc = '';
if ($logoPath) {
    $logoSrc = (strpos($logoPath, 'http') === 0) ? $logoPath : $siteUrl . ltrim($logoPath, '/');
}
?>
<!DOCTYPE html>
<html lang="<?php echo isEnglish() ? 'en' : 'ne'; ?>" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($_t('सदस्य लगिन', 'Member Login')); ?> — <?php echo htmlspecialchars($siteName); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php memberHeadAssets(); ?>
<?php @require_once __DIR__ . '/../assets/css/_color-vars.php'; ?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($siteUrl); ?>assets/css/auth-portals-unified.css?v=3">
<link rel="stylesheet" href="<?php echo htmlspecialchars($siteUrl); ?>member/assets/member.css?v=10">
<style>
*,*::before,*::after { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: var(--font-primary,'Mukta','Noto Sans Devanagari','Segoe UI',sans-serif);
    min-height: 100dvh;
    background:
        radial-gradient(ellipse 80% 50% at 20% -10%, rgba(26,135,84,.10) 0%, transparent 50%),
        radial-gradient(ellipse 65% 45% at 100% 0%, rgba(59,130,246,.08) 0%, transparent 48%),
        linear-gradient(165deg,#f0fdf4 0%,#ecfdf5 40%,#f0f9ff 100%);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 56px 14px 20px;
    color: #1f2937;
}
.page-back {
    position: fixed; top: 18px; right: 20px; z-index: 10;
    background: rgba(255,255,255,.9); backdrop-filter: blur(8px);
    border: 1px solid #e5e7eb;
    color: var(--primary-color,#1a8754);
    padding: 7px 15px; border-radius: 999px;
    text-decoration: none; font-size: .78rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 6px;
    box-shadow: 0 1px 6px rgba(0,0,0,.07);
    transition: all .15s;
}
.page-back:hover { background: #f0fdf4; transform: translateX(-2px); }

.auth-card {
    width: 100%; max-width: 372px;
    margin-top: 0;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,.04), 0 22px 50px rgba(13,92,46,.11);
    border: 1px solid rgba(226,232,240,.9);
    overflow: hidden;
}
.card-header {
    padding: 16px 18px 12px;
    text-align: center;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, rgba(240,253,244,.5) 0%, #fff 100%);
}
.card-logo-wrap {
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 8px;
}
.card-logo-wrap img {
    height: auto; width: auto;
    max-height: 52px; max-width: 180px;
    object-fit: contain; border-radius: 8px;
}
.card-logo-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));
    color: #fff; display: grid; place-items: center;
    font-size: 1.35rem; margin: 0 auto 8px;
    box-shadow: 0 6px 18px rgba(26,95,42,.28);
}
.card-logo-hide { display: none !important; }
.card-portal-label {
    display: inline-flex; align-items: center; gap: 5px;
    font-size:.68rem; font-weight:700; color:var(--primary-color,#1a8754);
    text-transform:uppercase; letter-spacing:.9px;
    background:#f0fdf4; border:1px solid #bbf7d0;
    padding:4px 12px; border-radius:999px;
}
.card-body { padding: 16px 18px 18px; }
.card-title {
    font-size: 1.02rem; font-weight: 800;
    color: var(--primary-dark,var(--primary-color,#1a8754));
    margin-bottom: 2px; line-height:1.2;
    letter-spacing: -.02em;
}
.card-sub { font-size:.76rem; color:#6b7280; margin-bottom:12px; line-height:1.45; }

/* Tabs */
.tabs { display:flex; gap:6px; margin-bottom:14px; background:#f1f5f9; border-radius:12px; padding:4px; }
.tab-btn {
    flex:1; padding:8px 8px;
    background:transparent; border:none; border-radius:9px;
    font-size:.83rem; font-weight:600; font-family:inherit;
    color:#6b7280; cursor:pointer; transition:all .15s;
    display:flex; align-items:center; justify-content:center; gap:6px;
}
.tab-btn.active { background:#fff; color:var(--primary-color,#1a8754); box-shadow:0 1px 4px rgba(0,0,0,.08); }
.tab-btn:hover:not(.active) { color:#374151; }

/* Fields */
.field { margin-bottom:11px; }
.field label { display:block; font-size:.74rem; font-weight:600; color:#374151; margin-bottom:5px; }
.field input, .field select {
    width:100%; padding:9px 11px;
    border:1.5px solid #e5e7eb; border-radius:10px;
    font-size:.88rem; font-family:inherit;
    background:#fafbfc; color:#111827;
    transition:border-color .15s,box-shadow .15s;
}
.field input::placeholder { color:#aab0ba; }
.field input:focus, .field select:focus {
    outline:none;
    border-color:var(--primary-color,#1a8754);
    background:#fff;
    box-shadow:0 0 0 3px rgba(26,135,84,.12);
}
.pw-wrap { position:relative; }
.pw-wrap input { padding-right:42px; }
.pw-toggle {
    position:absolute; right:12px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; color:#9ca3af; font-size:.9rem;
    padding:4px; transition:color .15s;
}
.pw-toggle:hover { color:var(--primary-color,#1a8754); }
.field-feedback { font-size:12px; margin-top:4px; }
.field-feedback.is-muted{color:var(--text-light,#6b7280);}
.field-feedback.is-ok{color:var(--primary-color);}
.field-feedback.is-bad{color:var(--secondary-color);}
.field-feedback.is-warn{color:var(--secondary-dark,var(--secondary-color));}

/* Button */
.submit-btn {
    width:100%; padding:10px 14px;
    background:linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));
    color:#fff; border:none; border-radius:10px;
    font-size:.9rem; font-weight:700; font-family:inherit;
    cursor:pointer; margin-top:8px;
    display:flex; align-items:center; justify-content:center; gap:8px;
    box-shadow:0 4px 14px rgba(26,135,84,.3);
    transition:all .18s;
}
.submit-btn:hover { filter:brightness(1.06); transform:translateY(-1px); box-shadow:0 8px 22px rgba(26,135,84,.35); }
.submit-btn:active { transform:none; filter:brightness(.97); }
.submit-btn:disabled { opacity:.65; cursor:not-allowed; transform:none; }

/* Alerts */
.alert { padding:9px 11px; border-radius:10px; margin-bottom:11px; font-size:.8rem; display:flex; align-items:flex-start; gap:8px; }
.alert-error  { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
.alert-success{ background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
.alert-warning{ background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
.alert-info   { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }

/* OAuth */
.oauth-divider {
    text-align:center; font-size:.72rem; color:#9ca3af;
    position:relative; margin:12px 0 10px;
}
.oauth-divider::before, .oauth-divider::after {
    content:''; position:absolute; top:50%; width:38%; height:1px; background:#e5e7eb;
}
.oauth-divider::before { left:0; }
.oauth-divider::after  { right:0; }
.oauth-row { display:flex; gap:8px; margin-bottom:8px; }
.oauth-btn {
    flex:1; padding:8px; border:1.5px solid #e5e7eb; border-radius:10px;
    background:#fafbfc; color:#374151; text-decoration:none;
    font-size:.82rem; font-weight:600; display:flex; align-items:center; justify-content:center; gap:7px;
    transition:all .15s;
}
.oauth-btn:hover { background:#fff; border-color:#d1d5db; }
.oauth-btn .google { color:#ea4335; }
.oauth-btn .fb     { color:#1877f2; }

.foot-link { text-align:center; margin-top:10px; font-size:.78rem; color:#6b7280; }
.foot-link a { color:var(--primary-color,#1a8754); font-weight:600; text-decoration:none; }
.foot-link a:hover { text-decoration:underline; }
.login-logo-fallback{display:none;margin-bottom:0;}
.twofa-qr-wrap{margin-bottom:12px;font-size:.78rem;}
.twofa-qr-link{color:var(--primary-color,#1a8754);font-weight:600;}
.forgot-wrap{text-align:right;margin-bottom:6px;}
.forgot-link{font-size:.78rem;color:var(--primary-color,#1a8754);font-weight:600;text-decoration:none;}
.req-star{color:var(--secondary-color);}
.kyc-note{font-size:.78rem;padding:9px 12px;margin-bottom:14px;}
.pw-strength{margin-top:5px;font-size:12px;}

/* Password strength */
.pw-rules { margin:6px 0 0; padding-left:0; font-size:11.5px; color:#6b7280; line-height:1.7; list-style:none; }
.pw-rules li.rule-ok{color:var(--primary-color);}
.pw-rules li.rule-muted{color:var(--text-muted,#9ca3af);}
.pw-strength.str-0{color:var(--text-muted,#9ca3af);}
.pw-strength.str-1{color:var(--secondary-color);}
.pw-strength.str-2{color:var(--secondary-dark,var(--secondary-color));}
.pw-strength.str-3{color:var(--accent-color,#ca8a04);}
.pw-strength.str-4{color:var(--primary-color);}

@media (max-width:480px) {
    body { padding: 48px 12px 16px; justify-content: flex-start; }
    .auth-card { border-radius: 18px; }
    .card-header { padding: 13px 14px 11px; }
    .card-body { padding: 13px 14px 14px; }
}
@media (min-height:700px) {
    body { justify-content: center; padding-top: 56px; }
}
</style>
</head>
<body class="auth-portal-page">

<a href="<?php echo $siteUrl; ?>" class="page-back">
    <i class="fas fa-arrow-left"></i> <?php echo $_t('गृहपृष्ठ', 'Homepage'); ?>
</a>

<div class="auth-card">

    <div class="card-header">
        <?php if ($logoSrc): ?>
            <div class="card-logo-wrap">
                <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="<?php echo htmlspecialchars($siteName); ?>"
                     onerror="this.classList.add('card-logo-hide');var f=document.getElementById('loginLogoFallback');if(f)f.style.display='grid';">
                <div id="loginLogoFallback" class="card-logo-icon login-logo-fallback" aria-hidden="true"><i class="fas fa-building-columns"></i></div>
            </div>
        <?php else: ?>
            <div class="card-logo-icon"><i class="fas fa-building-columns"></i></div>
        <?php endif; ?>
        <span class="card-portal-label"><i class="fas fa-user-circle"></i>&nbsp;<?php echo $_t('सदस्य पोर्टल', 'Member Portal'); ?></span>
    </div>

    <div class="card-body">
        <div class="card-title">
            <?php echo $tab === 'register' ? $_t('नयाँ खाता खोल्नुहोस्', 'Create New Account') : $_t('सदस्य लगिन', 'Member Login'); ?>
        </div>
        <div class="card-sub">
            <?php echo $tab === 'register'
                ? $_t('सदस्यता नम्बर सहित दर्ता गर्नुहोस् — Admin अनुमोदन पछि लगिन सक्नुहुन्छ।', 'Register with member number — you can login after admin approval.')
                : $_t('इमेल वा सदस्यता नम्बर बाट लगिन गर्नुहोस्।', 'Login with email or member number.'); ?>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($info === 'pending'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock"></i> <strong><?php echo $_t('अनुमोदन प्रतीक्षामा!', 'Pending Approval!'); ?></strong>&nbsp;
                <?php echo $_t('तपाईंको खाता Admin को समीक्षामा छ। स्वीकृत भएपछि सूचना पठाइनेछ।', 'Your account is under admin review. You will be notified after approval.'); ?>
            </div>
        <?php endif; ?>
        <?php if ($info === 'twofa_setup_required'): ?>
            <div class="alert alert-warning"><i class="fas fa-mobile-screen-button"></i> 2FA setup आवश्यक छ। Google Authenticator मा secret add गरेर code verify गर्नुहोस्।</div>
        <?php endif; ?>
        <?php if ($info === 'twofa_verify_required'): ?>
            <div class="alert alert-info"><i class="fas fa-shield-halved"></i> 2FA code verify गरेपछि मात्र login हुन्छ।</div>
        <?php endif; ?>

        <?php if (is_array($member2faPending)): ?>
        <form method="POST" id="formMember2FA">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="do_member_2fa" value="1">
            <?php if (($member2faPending['mode'] ?? '') === 'setup'): ?>
                <div class="alert alert-info"><i class="fas fa-qrcode"></i> Google Authenticator app मा यो setup गर्नुहोस्:</div>
                <div class="field">
                    <label>Manual Secret Key</label>
                    <input type="text" readonly value="<?php echo htmlspecialchars((string)($member2faPending['secret'] ?? '')); ?>">
                </div>
                <?php if ($member2faSetupUri !== ''): ?>
                <div class="twofa-qr-wrap">
                    <a href="https://chart.googleapis.com/chart?chs=220x220&cht=qr&chl=<?php echo urlencode($member2faSetupUri); ?>" target="_blank" rel="noopener" class="twofa-qr-link">QR खोल्नुहोस् (scan गर्न)</a>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-info"><i class="fas fa-lock"></i> Google Authenticator code वा backup code राख्नुहोस्।</div>
            <?php endif; ?>
            <div class="field">
                <label>2FA Code</label>
                <input type="text" name="twofa_code" placeholder="123456 वा BACKUPCODE" required autofocus>
            </div>
            <button type="submit" class="submit-btn"><i class="fas fa-shield-check"></i> Verify 2FA</button>
            <?php if (!empty($_SESSION['member_2fa_backup_plain']) && is_array($_SESSION['member_2fa_backup_plain'])): ?>
                <div class="alert alert-warning" style="margin-top:12px;">
                    <i class="fas fa-triangle-exclamation"></i> Backup codes (safe राख्नुहोस्):<br>
                    <code><?php echo htmlspecialchars(implode(' , ', $_SESSION['member_2fa_backup_plain'])); ?></code>
                </div>
                <?php unset($_SESSION['member_2fa_backup_plain']); ?>
            <?php endif; ?>
        </form>
        <?php else: ?>

        <div class="tabs">
                <button class="tab-btn <?php echo $tab==='login'?'active':''; ?>" onclick="switchTab('login')">
                <i class="fas fa-sign-in-alt"></i> <?php echo $_t('लगिन', 'Login'); ?>
            </button>
                <button class="tab-btn <?php echo $tab==='register'?'active':''; ?>" onclick="switchTab('register')">
                <i class="fas fa-user-plus"></i> <?php echo $_t('दर्ता', 'Register'); ?>
            </button>
        </div>

        <!-- Login Form -->
        <form method="POST" novalidate id="formLogin" style="display:<?php echo $tab==='login'?'block':'none'; ?>;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="do_login" value="1">
            <div class="field">
                <label><?php echo $_t('इमेल वा सदस्यता नम्बर', 'Email or Member Number'); ?></label>
                <input type="text" name="login_id" placeholder="<?php echo $_t('email@example.com वा १२३४५', 'email@example.com or 12345'); ?>" required autofocus>
            </div>
            <div class="field">
                <label><?php echo $_t('पासवर्ड', 'Password'); ?></label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="loginPw" placeholder="••••••••" required>
                    <button type="button" class="pw-toggle" onclick="togglePw('loginPw',this)"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <div class="forgot-wrap">
                <a href="<?php echo $siteUrl; ?>member/password-reset-request.php" class="forgot-link"><?php echo $_t('पासवर्ड बिर्सनुभयो?', 'Forgot password?'); ?></a>
            </div>
            <button type="submit" class="submit-btn">
                <i class="fas fa-sign-in-alt"></i> <?php echo $_t('लगिन गर्नुहोस्', 'Login'); ?>
            </button>
            <?php if ($googleUrl || $fbUrl): ?>
            <div class="oauth-divider"><?php echo $_t('वा यसबाट लगिन', 'Or login with'); ?></div>
            <div class="oauth-row">
                <?php if ($googleUrl): ?>
                <a href="<?php echo htmlspecialchars($googleUrl); ?>" class="oauth-btn"><i class="fab fa-google google"></i> Google</a>
                <?php endif; ?>
                <?php if ($fbUrl): ?>
                <a href="<?php echo htmlspecialchars($fbUrl); ?>" class="oauth-btn"><i class="fab fa-facebook fb"></i> Facebook</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="foot-link">
                <?php echo $_t('खाता छैन?', "Don't have an account?"); ?> <a href="#" onclick="switchTab('register');return false;"><?php echo $_t('दर्ता गर्नुहोस्', 'Register'); ?></a>
            </div>
        </form>

        <!-- Register Form -->
        <form method="POST" novalidate id="formRegister" style="display:<?php echo $tab==='register'?'block':'none'; ?>;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
            <input type="hidden" name="do_register" value="1">
            <div class="field">
                <label><?php echo $_t('सदस्यता नम्बर', 'Member Number'); ?> <span class="req-star">*</span></label>
                <input type="text" name="sadasyata_number" id="regSadasyata" placeholder="<?php echo $_t('जस्तै: १२३४', 'e.g. 1234'); ?>" required>
                <div class="field-feedback" id="fbSadasyata"></div>
            </div>
            <div class="field">
                <label><?php echo $_t('इमेल', 'Email'); ?> <span class="req-star">*</span></label>
                <input type="email" name="email" id="regEmail" placeholder="email@example.com" required>
                <div class="field-feedback" id="fbEmail"></div>
            </div>
            <div class="field">
                <label><?php echo $_t('मोबाइल नम्बर', 'Mobile Number'); ?> <span class="req-star">*</span></label>
                <input type="tel" name="phone" id="regPhone" placeholder="98XXXXXXXX" pattern="[0-9]{10}" maxlength="10" required>
                <div class="field-feedback" id="fbPhone"></div>
            </div>
            <div class="alert alert-info kyc-note">
                <i class="fas fa-circle-info"></i>
                नाम KYC बाट स्वतः लिइन्छ। सदस्यता नम्बर + इमेल + मोबाइल KYC सँग मिल्नुपर्छ।
            </div>
            <div class="field">
                <label><?php echo $_t('पासवर्ड', 'Password'); ?> <span class="req-star">*</span></label>
                <div class="pw-wrap">
                    <input type="password" name="password" id="regPw" placeholder="<?php echo $_t('८+ अक्षर, A-Z, a-z, 0-9 सहित', '8+ chars with A-Z, a-z, 0-9'); ?>" required minlength="8">
                    <button type="button" class="pw-toggle" onclick="togglePw('regPw',this)"><i class="fas fa-eye"></i></button>
                </div>
                <div class="pw-strength" id="pwStrength"></div>
                <ul class="pw-rules" id="pwRules">
                    <li data-rule="len">○ कम्तिमा ८ अक्षर</li>
                    <li data-rule="upper">○ १ Capital letter (A-Z)</li>
                    <li data-rule="lower">○ १ small letter (a-z)</li>
                    <li data-rule="digit">○ १ digit (0-9)</li>
                </ul>
            </div>
            <div class="field">
                <label><?php echo $_t('पासवर्ड पुनः', 'Confirm Password'); ?> <span class="req-star">*</span></label>
                <input type="password" name="confirm" id="regConfirm" placeholder="<?php echo $_t('माथिको जस्तै', 'Same as above'); ?>" required>
                <div class="field-feedback" id="fbConfirm"></div>
            </div>
            <button type="submit" class="submit-btn" id="regSubmitBtn">
                <i class="fas fa-user-plus"></i> <?php echo $_t('दर्ता गर्नुहोस्', 'Register'); ?>
            </button>
            <div class="foot-link">
                <?php echo $_t('पहिले नै दर्ता?', 'Already registered?'); ?> <a href="#" onclick="switchTab('login');return false;"><?php echo $_t('लगिन गर्नुहोस्', 'Login'); ?></a>
            </div>
        </form>

        <?php endif; ?>

    </div>
</div>

<script>
function switchTab(t) {
    document.getElementById('formLogin').style.display    = t==='login'    ? 'block' : 'none';
    document.getElementById('formRegister').style.display = t==='register' ? 'block' : 'none';
    document.querySelectorAll('.tab-btn').forEach(function(b,i){
        b.classList.toggle('active', (i===0 && t==='login') || (i===1 && t==='register'));
    });
    if (history && history.replaceState) {
        var u = new URL(location.href); u.searchParams.set('tab', t);
        history.replaceState(null, '', u.toString());
    }
    /* Update title text */
    var title = document.querySelector('.card-title');
    var sub   = document.querySelector('.card-sub');
    if (title) title.textContent = t === 'register' ? 'नयाँ खाता खोल्नुहोस्' : 'सदस्य लगिन';
    if (sub)   sub.textContent   = t === 'register'
        ? 'सदस्यता नम्बर सहित दर्ता गर्नुहोस् — Admin अनुमोदन पछि लगिन सक्नुहुन्छ।'
        : 'इमेल वा सदस्यता नम्बर बाट लगिन गर्नुहोस्।';
}
function togglePw(id, btn) {
    var inp = document.getElementById(id);
    var show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}
/* Loading state — double-submit रोक्ने */
document.querySelectorAll('form').forEach(function(form){
    form.addEventListener('submit', function(){
        var btn = form.querySelector('.submit-btn');
        if (!btn || btn.dataset.submitting === '1') return;
        btn.dataset.submitting = '1';
        btn.dataset.original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> प्रशोधन हुँदै…';
        setTimeout(function(){
            if (btn.dataset.submitting === '1') {
                btn.disabled = false;
                btn.innerHTML = btn.dataset.original;
                btn.dataset.submitting = '0';
            }
        }, 15000);
    });
});

/* ════════════════════════════════════════════════════════════
   Issue #4: Live registration validation
   • password strength meter + rule checklist
   • AJAX duplicate check (email / phone / sadasyata)
   • confirm-password match
   • disable submit until everything is OK
══════════════════════════════════════════════════════════════ */
(function(){
  var pw       = document.getElementById('regPw');
  var pwConf   = document.getElementById('regConfirm');
  var pwMeter  = document.getElementById('pwStrength');
  var pwRules  = document.getElementById('pwRules');
  var fbConf   = document.getElementById('fbConfirm');
  var btn      = document.getElementById('regSubmitBtn');
  if (!pw) return;

  var state = { pwOk:false, confirmOk:false, dupOk:true };
  function refreshBtn(){
    if (btn) btn.disabled = !(state.pwOk && state.confirmOk && state.dupOk);
  }
  function setRule(name, ok){
    var li = pwRules && pwRules.querySelector('[data-rule="'+name+'"]');
    if (!li) return;
    li.classList.remove('rule-ok', 'rule-muted');
    li.classList.add(ok ? 'rule-ok' : 'rule-muted');
    li.textContent = (ok ? '✓ ' : '○ ') + li.textContent.replace(/^[✓○]\s*/, '');
  }
  function checkPw(){
    var v = pw.value || '';
    var rLen = v.length >= 8, rUp = /[A-Z]/.test(v), rLo = /[a-z]/.test(v), rDi = /[0-9]/.test(v);
    setRule('len', rLen); setRule('upper', rUp); setRule('lower', rLo); setRule('digit', rDi);
    var score = rLen + rUp + rLo + rDi;
    var labels = ['', 'धेरै कमजोर', 'कमजोर', 'ठीकै', 'बलियो'];
    if (pwMeter) {
      pwMeter.textContent = v ? ('Password strength: ' + labels[score]) : '';
      pwMeter.classList.remove('str-0','str-1','str-2','str-3','str-4');
      pwMeter.classList.add('str-' + String(score));
    }
    state.pwOk = score === 4;
    refreshBtn();
    if (pwConf.value) checkConfirm();
  }
  function checkConfirm(){
    var match = pwConf.value && pwConf.value === pw.value;
    state.confirmOk = !!match;
    if (fbConf){
      fbConf.textContent = pwConf.value ? (match ? '✓ मेल खायो' : '✗ पासवर्ड मेल खाएन') : '';
      fbConf.classList.remove('is-ok','is-bad','is-muted','is-warn');
      if (!pwConf.value) fbConf.classList.add('is-muted');
      else fbConf.classList.add(match ? 'is-ok' : 'is-bad');
    }
    refreshBtn();
  }
  pw.addEventListener('input', checkPw);
  pwConf.addEventListener('input', checkConfirm);

  /* ── AJAX duplicate check ── */
  function debounce(fn, wait){
    var t; return function(){ clearTimeout(t); var a=arguments, c=this; t=setTimeout(function(){fn.apply(c,a);}, wait); };
  }
  function dupCheck(field, input, fb){
    var v = (input.value || '').trim();
    if (!v) { fb.textContent=''; state.dupOk = true; refreshBtn(); return; }
    if (field === 'phone' && !/^[0-9]{7,15}$/.test(v)) { return; }
    if (field === 'email' && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) { return; }
    fb.textContent = '⏳ जाँच हुँदै…';
    fb.classList.remove('is-ok','is-bad','is-muted','is-warn');
    fb.classList.add('is-muted');
    fetch('<?php echo SITE_URL; ?>member/check-availability.php?field='+encodeURIComponent(field)+'&value='+encodeURIComponent(v))
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (j && j.available) {
          fb.textContent = '✓ उपलब्ध छ';
          fb.classList.remove('is-muted','is-bad','is-warn');
          fb.classList.add('is-ok');
          state.dupOk = true;
        } else {
          fb.textContent = '✗ ' + (j && j.message ? j.message : 'पहिले नै दर्ता भएको छ');
          fb.classList.remove('is-muted','is-ok','is-warn');
          fb.classList.add('is-bad');
          state.dupOk = false;
        }
        refreshBtn();
      })
      .catch(function(){ fb.textContent=''; state.dupOk=true; refreshBtn(); });
  }
  ['Email','Phone','Sadasyata'].forEach(function(suffix){
    var input = document.getElementById('reg'+suffix);
    var fb    = document.getElementById('fb'+suffix);
    if (!input || !fb) return;
    var field = suffix === 'Sadasyata' ? 'sadasyata_number' : suffix.toLowerCase();
    input.addEventListener('input', debounce(function(){ dupCheck(field, input, fb); }, 450));
  });
})();
</script>
</body>
</html>
