<?php
/**
 * Admin Login — `admin/index.php` (एउटै URL, सबै admin/superadmin यहीँबाट)
 * =====================================================================
 * पहिले जस्तै फ्लो:
 *   १) Superadmin को username/password → `includes/superadmin-config.local.php` (cPanel, hardcode)।
 *   २) यही पृष्ठमा login — पहिलो पटक DB मा super_admin row seed/sync हुन्छ।
 *   ३) Dashboard खुल्छ → `manage-admins.php` मा अरू admin/editor बनाउन सकिन्छ।
 *   ४) Superadmin पासवर्ड बदल्न फेरि मात्र त्यो फाइल edit (अलग superadmin setup URL छैन)।
 *
 * `database/install.sql` को default `admin`/`password` = backup जब local फाइल हुँदैन;
 * production मा local फाइल राख्नु नै राम्रो।
 *
 * DB जडान नभए login चल्दैन — पहिलो पटक `admin/db-setup.php` (`database.local.php` भरे सिधै, नभए superadmin unlock)।
 */

require_once '../includes/config.php';
require_once '../includes/site-license-renewal.php';
require_once '../includes/superadmin-config.php';
require_once '../includes/totp-2fa.php';

if (!function_exists('coop_admin_ensure_twofa_columns')) {
    /** admin_users मा 2FA columns — प्रति request एक पटक मात्र */
    function coop_admin_ensure_twofa_columns(PDO $db): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach ([
            "ALTER TABLE admin_users ADD COLUMN twofa_enabled TINYINT DEFAULT 0",
            "ALTER TABLE admin_users ADD COLUMN twofa_secret VARCHAR(64) NULL",
            "ALTER TABLE admin_users ADD COLUMN twofa_backup_codes TEXT NULL",
            "ALTER TABLE admin_users ADD COLUMN twofa_enabled_at DATETIME NULL",
        ] as $sql2fa) {
            try {
                $db->exec($sql2fa);
            } catch (Throwable $ignored) {
            }
        }
    }
}

if (isAdminLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
/** साधारण admin — म्याद सकिएको बेला लग इन रोकिँदा (Superadmin बाहेक) */
$msgSiteLicenseExpiredLogin = 'साइट सेवा म्याद सकियो। सहकारीका साधारण Admin ले यो बेला लग इन गर्न मिल्दैन। नवीकरण/तिराई: तलको Pay Now वा विक्रेता सम्पर्क गर्नुहोस्।';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'submit_renewal_notice_login') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'सुरक्षा जाँच असफल। पृष्ठ refresh गरी पुन: प्रयास गर्नुहोस्।';
    } elseif (!function_exists('site_license_expired') || !site_license_expired()) {
        header('Location: ' . ADMIN_URL . 'index.php');
        exit;
    } elseif (!checkRateLimit('admin_login_renewal_notice', 15, 3600)) {
        $error = 'धेरै पटक भुक्तानी सूचना पठाइयो। केही समय पछि प्रयास गर्नुहोस्।';
    } else {
        try {
            $db = getDB();
            $gateway = trim((string) ($_POST['gateway'] ?? ''));
            $txn = trim((string) ($_POST['txn_reference'] ?? ''));
            $note = trim((string) ($_POST['renewal_note'] ?? ''));
            $submitter = function_exists('getSetting') ? trim((string) getSetting('site_name', 'सहकारी')) : '';
            $apply = site_license_renewal_apply_office_notice($db, $gateway, $txn, $note, $submitter, null);
            if (!$apply['ok']) {
                $error = $apply['error'] ?? 'त्रुटि।';
            } else {
                $newId = (int) ($apply['id'] ?? 0);
                $amtStored = trim((string) getSetting('site_license_renewal_amount', ''));
                site_license_renewal_notify_vendor($db, [
                    'id' => $newId,
                    'gateway' => $gateway,
                    'txn_reference' => $txn,
                    'amount_reported' => $amtStored,
                    'note' => $note,
                    'submitted_by_username' => $submitter,
                ]);
                header('Location: ' . ADMIN_URL . 'index.php?renewal_sent=1');
                exit;
            }
        } catch (Throwable $e) {
            error_log('[admin-login-renewal-notice] ' . $e->getMessage());
            $error = 'सर्भर त्रुटि। पछि प्रयास गर्नुहोस् वा विक्रेता सम्पर्क गर्नुहोस्।';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['do_admin_2fa'])) {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Security error.';
        } else {
            $pending = $_SESSION['admin_2fa_pending'] ?? null;
            if (!is_array($pending) || empty($pending['id'])) {
                $error = '2FA session सकियो। फेरि login गर्नुहोस्।';
            } else {
                try {
                    $db = getDB();
                    coop_admin_ensure_twofa_columns($db);
                    $st = $db->prepare("SELECT * FROM admin_users WHERE id=? AND is_active=1 LIMIT 1");
                    $st->execute([(int)$pending['id']]);
                    $user = $st->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (!$user) {
                        $error = 'User भेटिएन।';
                    } else {
                        $code = trim((string)($_POST['twofa_code'] ?? ''));
                        $mode = (string)($pending['mode'] ?? 'verify');
                        $ok = false;
                        if ($mode === 'setup') {
                            $secret = trim((string)($pending['secret'] ?? ''));
                            if ($secret === '') $error = '2FA secret नभेटियो।';
                            elseif (!twoFaVerifyCode($secret, $code, 1)) $error = '2FA code मिलेन।';
                            else {
                                $bk = twoFaGenerateBackupCodes(8);
                                $db->prepare("UPDATE admin_users SET twofa_enabled=1, twofa_secret=?, twofa_backup_codes=?, twofa_enabled_at=NOW() WHERE id=?")
                                   ->execute([$secret, json_encode($bk['hashes']), (int)$user['id']]);
                                $_SESSION['admin_2fa_backup_plain'] = $bk['plain'];
                                $ok = true;
                            }
                        } else {
                            $secret = trim((string)($user['twofa_secret'] ?? ''));
                            if ($secret !== '' && twoFaVerifyCode($secret, $code, 1)) {
                                $ok = true;
                            } else {
                                $hashes = json_decode((string)($user['twofa_backup_codes'] ?? '[]'), true);
                                if (!is_array($hashes)) $hashes = [];
                                $resBk = twoFaConsumeBackupCode($code, $hashes);
                                if (!empty($resBk['ok'])) {
                                    $db->prepare("UPDATE admin_users SET twofa_backup_codes=? WHERE id=?")->execute([json_encode($resBk['hashes']), (int)$user['id']]);
                                    $ok = true;
                                }
                            }
                            if (!$ok) $error = '2FA code / backup code मिलेन।';
                        }

                        if ($ok) {
                            if (function_exists('site_license_login_blocked_for_user') && site_license_login_blocked_for_user($user)) {
                                unset($_SESSION['admin_2fa_pending']);
                                $error = $msgSiteLicenseExpiredLogin;
                                $_SESSION['admin_license_renewal_prompt'] = true;
                            } else {
                                unset($_SESSION['admin_2fa_pending']);
                                unset($_SESSION['admin_license_renewal_prompt']);
                                session_regenerate_id(true);
                                $_SESSION['admin_id']        = $user['id'];
                                $_SESSION['admin_username']  = $user['username'];
                                $_SESSION['admin_name']      = $user['full_name'] ?: $user['username'];
                                $_SESSION['is_superadmin']   = admin_db_role_is_superadmin($user['role'] ?? '');
                                $_SESSION['admin_last_activity'] = time();
                                $_SESSION['admin_agent_hash'] = substr(md5($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 16);
                                $ip2 = $_SERVER['REMOTE_ADDR'] ?? '';
                                $_SESSION['admin_ip_partial'] = implode('.', array_slice(explode('.', $ip2), 0, 3));
                                if (!empty($_SESSION['is_superadmin']) && defined('SUPER_ADMIN_INITIAL_PASSWORD') && SUPER_ADMIN_INITIAL_PASSWORD !== ''
                                    && function_exists('safeColumnExists') && safeColumnExists('admin_users', 'must_change_password')) {
                                    try {
                                        $db->prepare('UPDATE admin_users SET must_change_password = 0 WHERE id = ?')->execute([(int) $user['id']]);
                                    } catch (Throwable $eMc2fa) { /* ignore */ }
                                }
                                try {
                                    $db->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([(int) $user['id']]);
                                } catch (Throwable $e2faLog) {
                                    error_log('[admin-login-2fa-lastlogin] ' . $e2faLog->getMessage());
                                }
                                redirect('dashboard.php');
                            }
                        }
                    }
                } catch (Throwable $e) {
                    $error = '2FA verify गर्दा त्रुटि भयो।';
                }
            }
        }
    } else {
    $username = clean_text($_POST['username'] ?? '', 100);
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (function_exists('checkLoginAttempts') && !checkLoginAttempts($username, $ip)) {
        $error = 'धेरै पटक गलत प्रयास भयो। कृपया १५ मिनेट पछि पुन: प्रयास गर्नुहोस्।';
    } elseif (!checkRateLimit('admin_login', 5, 900)) {
        $error = 'धेरै पटक गलत प्रयास भयो। कृपया १५ मिनेट पछि पुन: प्रयास गर्नुहोस्।';
    } elseif (empty($username) || empty($password)) {
        $error = 'कृपया युजरनेम र पासवर्ड भर्नुहोस्।';
    } else {
        try {
            $db = getDB();
            coop_admin_ensure_twofa_columns($db);

            /* SEED superadmin एकपटक */
            if (defined('SUPER_ADMIN_INITIAL_PASSWORD') && SUPER_ADMIN_INITIAL_PASSWORD !== '') {
                $check = $db->prepare("SELECT id FROM admin_users WHERE username = ? LIMIT 1");
                $check->execute([SUPER_ADMIN_USERNAME]);
                if (!$check->fetch()) {
                    $hash = password_hash(SUPER_ADMIN_INITIAL_PASSWORD, PASSWORD_BCRYPT);
                    try {
                        /* Superadmin पासवर्ड फाइलबाट — admin change-password जरुरी छैन */
                        $ins = $db->prepare("INSERT INTO admin_users (username, password, full_name, email, role, is_active, must_change_password) VALUES (?, ?, ?, '', 'super_admin', 1, 0)");
                        $ins->execute([SUPER_ADMIN_USERNAME, $hash, SUPER_ADMIN_DISPLAY_NAME]);
                    } catch (Throwable $e) {
                        $ins = $db->prepare("INSERT INTO admin_users (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, '', 'super_admin', 1)");
                        $ins->execute([SUPER_ADMIN_USERNAME, $hash, SUPER_ADMIN_DISPLAY_NAME]);
                    }
                    logSecurityEvent('superadmin_seeded', 'Superadmin account seeded into DB from initial config.');
                }
            }

            $stmt = $db->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            /**
             * superadmin-config.local.php edit गर्दा DB को hash अपडेट हुँदैन (seed मात्र नयाँ row)।
             * यहाँ: local SUPER_ADMIN_USERNAME + SUPER_ADMIN_INITIAL_PASSWORD मिलेमा DB sync गर्छ —
             * (१) username DB मा फरक छ तर super_admin एउटै मात्र → username/password अपडेट
             * (२) user मिल्यो तर hash पुरानो → पासवर्ड hash मात्र अपडेट
             */
            $__localSeed = defined('SUPER_ADMIN_INITIAL_PASSWORD') && SUPER_ADMIN_INITIAL_PASSWORD !== ''
                && defined('SUPER_ADMIN_USERNAME')
                && hash_equals((string) SUPER_ADMIN_USERNAME, (string) $username)
                && hash_equals((string) SUPER_ADMIN_INITIAL_PASSWORD, (string) $password);
            if ($__localSeed) {
                if (!$user) {
                    $rows = $db->query("SELECT id FROM admin_users WHERE (role = 'super_admin' OR role = 'superadmin') AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
                    if (is_array($rows) && count($rows) === 1) {
                        $rid = (int) $rows[0]['id'];
                        $newHash = password_hash((string) SUPER_ADMIN_INITIAL_PASSWORD, PASSWORD_BCRYPT);
                        try {
                            $db->prepare('UPDATE admin_users SET username = ?, password = ?, must_change_password = 0 WHERE id = ?')
                                ->execute([SUPER_ADMIN_USERNAME, $newHash, $rid]);
                        } catch (Throwable $eSync) {
                            $db->prepare('UPDATE admin_users SET username = ?, password = ? WHERE id = ?')
                                ->execute([SUPER_ADMIN_USERNAME, $newHash, $rid]);
                        }
                        $stmt->execute([$username]);
                        $user = $stmt->fetch();
                        logSecurityEvent('superadmin_local_sync', 'Superadmin username/password synced from superadmin-config.local.php (single super row).');
                    }
                } elseif (!password_verify($password, $user['password']) && function_exists('admin_db_role_is_superadmin') && admin_db_role_is_superadmin($user['role'] ?? '')) {
                    $newHash = password_hash((string) SUPER_ADMIN_INITIAL_PASSWORD, PASSWORD_BCRYPT);
                    try {
                        $db->prepare('UPDATE admin_users SET password = ?, must_change_password = 0 WHERE id = ?')->execute([$newHash, (int) $user['id']]);
                    } catch (Throwable $ePw) {
                        $db->prepare('UPDATE admin_users SET password = ? WHERE id = ?')->execute([$newHash, (int) $user['id']]);
                    }
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                    logSecurityEvent('superadmin_local_sync', 'Superadmin password hash synced from superadmin-config.local.php.');
                }
            }

            if ($user && password_verify($password, $user['password'])) {
                unset($_SESSION['rate_admin_login_' . $ip]);
                if (function_exists('resetLoginAttempts')) {
                    resetLoginAttempts($username, $ip);
                }

                /* Superadmin + local फाइलमा पासवर्ड: DB मा must_change झुण्डिने अवस्था सफा गर्ने */
                if (function_exists('admin_db_role_is_superadmin') && admin_db_role_is_superadmin($user['role'] ?? '')
                    && defined('SUPER_ADMIN_INITIAL_PASSWORD') && SUPER_ADMIN_INITIAL_PASSWORD !== ''
                    && function_exists('safeColumnExists') && safeColumnExists('admin_users', 'must_change_password')) {
                    try {
                        $db->prepare('UPDATE admin_users SET must_change_password = 0 WHERE id = ?')->execute([(int) $user['id']]);
                    } catch (Throwable $eMc0) { /* ignore */ }
                }

                if (password_needs_rehash($user['password'], PASSWORD_BCRYPT)) {
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    $rh = $db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                    $rh->execute([$newHash, $user['id']]);
                }

                $twoFaRequired = (getSetting('twofa_admin_required', '0') === '1');
                $secret = trim((string)($user['twofa_secret'] ?? ''));
                $enabled = ((int)($user['twofa_enabled'] ?? 0) === 1) && $secret !== '';
                if ($twoFaRequired) {
                    if (!$enabled) {
                        if ($secret === '') $secret = twoFaGenerateSecret(32);
                        $_SESSION['admin_2fa_pending'] = ['id' => (int)$user['id'], 'mode' => 'setup', 'secret' => $secret];
                    } else {
                        $_SESSION['admin_2fa_pending'] = ['id' => (int)$user['id'], 'mode' => 'verify'];
                    }
                } else {
                    if (function_exists('site_license_login_blocked_for_user') && site_license_login_blocked_for_user($user)) {
                        $error = $msgSiteLicenseExpiredLogin;
                        $_SESSION['admin_license_renewal_prompt'] = true;
                        if (function_exists('recordLoginAttempt')) {
                            recordLoginAttempt($username, $ip);
                        }
                    } else {
                        unset($_SESSION['admin_license_renewal_prompt']);
                        session_regenerate_id(true);
                        $_SESSION['admin_id']        = $user['id'];
                        $_SESSION['admin_username']  = $user['username'];
                        $_SESSION['admin_name']      = $user['full_name'] ?: $user['username'];
                        $_SESSION['is_superadmin']   = admin_db_role_is_superadmin($user['role'] ?? '');
                        $_SESSION['admin_last_activity'] = time();
                        $_SESSION['admin_agent_hash'] = substr(md5($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 16);
                        $_SESSION['admin_ip_partial'] = implode('.', array_slice(explode('.', $ip), 0, 3));

                        /* last_login / activity_log असफल भए पनि login पूरा गर्ने (पुरानो DB मा activity_log नभएमा) */
                        try {
                            $updateStmt = $db->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?');
                            $updateStmt->execute([$user['id']]);
                            $logStmt = $db->prepare('INSERT INTO activity_log (admin_id, action, description, ip_address) VALUES (?, ?, ?, ?)');
                            $logStmt->execute([$user['id'], 'login', 'Admin logged in', $ip]);
                        } catch (Throwable $postLoginEx) {
                            error_log('[admin-login-post-actions] ' . $postLoginEx->getMessage());
                        }

                        redirect('dashboard.php');
                    }
                }
            } else {
                $error = 'गलत युजरनेम वा पासवर्ड।';
                if (function_exists('recordLoginAttempt')) {
                    recordLoginAttempt($username, $ip);
                }
                logSecurityEvent('failed_login', 'Failed login for: ' . $username);
            }
        } catch (Throwable $e) {
            error_log('Admin login error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $em = $e->getMessage();
            if (str_contains($em, 'Database connection not available')) {
                $dsu = defined('ADMIN_URL') ? ADMIN_URL . 'db-setup.php' : 'admin/db-setup.php';
                $error = 'डेटाबेस जडान उपलब्ध छैन। `public_html/includes/database.local.php` मा DB host/name/user/pass जाँच गर्नुहोस्। पहिलो install: ' . $dsu;
            } elseif (str_contains($em, '2002') || str_contains($em, '2006') || str_contains($em, 'Connection refused')) {
                $error = 'MySQL server मा जडान हुन सकेन (host/socket)। hosting मा DB host जाँच गर्नुहोस्।';
            } elseif (str_contains($em, '1045') || str_contains($em, 'Access denied')) {
                $error = 'डेटाबेस user वा password गलत छ।';
            } elseif (str_contains($em, '1049') || str_contains($em, "Unknown database")) {
                $error = 'डेटाबेस नाम (DB name) भेटिएन।';
            } elseif (str_contains($em, '1146') || str_contains($em, "doesn't exist")) {
                $error = 'डेटाबेस तालिका छैन। `install.sql` import वा admin मा run-migration चलाउनुहोस्।';
            } else {
                $error = 'त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।';
            }
        }
    }
    }
}

$admin2faPending = $_SESSION['admin_2fa_pending'] ?? null;
$admin2faSetupUri = '';
if (is_array($admin2faPending) && (($admin2faPending['mode'] ?? '') === 'setup')) {
    $issuer = getSetting('site_name', 'Aakash Cooperative');
    $label = 'admin-' . (int)($admin2faPending['id'] ?? 0);
    $secret = (string)($admin2faPending['secret'] ?? '');
    if ($secret !== '') $admin2faSetupUri = twoFaProvisioningUri($issuer, $label, $secret);
}

$siteName = function_exists('getSetting') ? getSetting('site_name', 'आकाश सहकारी') : 'आकाश सहकारी';
$logoPath = function_exists('getSetting')
    ? trim((string) getSetting('site_logo', getSetting('logo', 'assets/images/logo.png')))
    : 'assets/images/logo.png';
$logoSrc  = $logoPath ? (strpos($logoPath,'http')===0 ? $logoPath : SITE_URL . ltrim($logoPath,'/')) : '';

$licExpiredLogin = function_exists('site_license_expired') && site_license_expired();
$renewalNoticeSent = $licExpiredLogin && isset($_GET['renewal_sent']) && (string) $_GET['renewal_sent'] === '1';
$loginRenewKhalti = '';
$loginRenewEsewa = '';
$loginRenewAmount = '';
if ($licExpiredLogin && function_exists('site_license_pay_id_or_default') && function_exists('getSetting')) {
    $loginRenewKhalti = site_license_pay_id_or_default((string) getSetting('site_license_khalti_id', ''));
    $loginRenewEsewa = site_license_pay_id_or_default((string) getSetting('site_license_esewa_id', ''));
    $loginRenewAmount = trim((string) getSetting('site_license_renewal_amount', ''));
}
$renewalSubmitterCoop = function_exists('getSetting') ? trim((string) getSetting('site_name', '')) : '';
$renewalSubmitterCoopReadonly = ($renewalSubmitterCoop !== '');

if (!$licExpiredLogin && isset($_SESSION['admin_license_renewal_prompt'])) {
    unset($_SESSION['admin_license_renewal_prompt']);
}

$showLicenseRenewalOnLogin = false;
if ($licExpiredLogin && !is_array($admin2faPending)) {
    $showLicenseRenewalOnLogin = !empty($_SESSION['admin_license_renewal_prompt'])
        || (string) ($_GET['renewal'] ?? '') === '1'
        || $renewalNoticeSent
        || ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'submit_renewal_notice_login');
}
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@300;400;500;600;700;800&family=Noto+Sans+Devanagari:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/design-tokens.css?v=9.7">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>assets/css/auth-portals-unified.css?v=3">
    <link rel="stylesheet" href="assets/admin-modern.css?v=4.9">
    <?php if (file_exists(__DIR__ . '/../assets/css/_color-vars.php')) require __DIR__ . '/../assets/css/_color-vars.php'; ?>
    <style>
        *,*::before,*::after { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: var(--font-primary,'Mukta','Noto Sans Devanagari','Segoe UI',sans-serif);
            min-height: 100dvh;
            background: linear-gradient(150deg,#f0fdf4 0%,#e8f5e9 45%,#eef2ff 100%);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 24px 16px;
            color: var(--text-primary,#1f2937);
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
            width: 100%; max-width: 420px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,.04), 0 24px 56px rgba(0,0,0,.09);
            border-top: 4px solid var(--primary-color,#1a8754);
            overflow: hidden;
        }
        .card-header {
            padding: 30px 28px 22px;
            text-align: center;
            border-bottom: 1px solid #f3f4f6;
        }
        .card-logo-wrap {
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .card-logo-wrap img {
            height: auto; width: auto;
            max-height: 72px; max-width: 210px;
            object-fit: contain; border-radius: 8px;
        }
        .card-logo-icon {
            width: 66px; height: 66px;
            border-radius: 16px;
            background: linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));
            color: #fff; display: grid; place-items: center;
            font-size: 1.7rem; margin: 0 auto 14px;
            box-shadow: 0 6px 18px rgba(26,95,42,.28);
        }
        .card-portal-label {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: .72rem; font-weight: 700;
            color: var(--primary-color,#1a8754);
            text-transform: uppercase; letter-spacing: .9px;
            background: #f0fdf4; border: 1px solid #bbf7d0;
            padding: 3px 11px; border-radius: 999px;
        }
        .card-body { padding: 26px 28px 30px; }
        .card-title {
            font-size: 1.32rem; font-weight: 800;
            color: var(--primary-dark,var(--primary-color,#1a8754));
            margin-bottom: 4px; line-height: 1.2;
        }
        .card-sub {
            font-size: .84rem; color: #6b7280;
            margin-bottom: 20px; line-height: 1.5;
        }
        .field { margin-bottom: 14px; }
        .field label {
            display: block; font-size: .78rem; font-weight: 600;
            color: #374151; margin-bottom: 6px;
        }
        .input-icon { position: relative; }
        .input-icon i {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%); color: #9ca3af; font-size: .88rem;
            pointer-events: none;
        }
        .field input {
            width: 100%; padding: 11px 13px 11px 38px;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            font-size: .93rem; font-family: inherit;
            background: #fafbfc; color: #111827;
            transition: border-color .15s, box-shadow .15s;
        }
        .field input::placeholder { color: #aab0ba; }
        .field input:focus {
            outline: none;
            border-color: var(--primary-color,#1a8754);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(26,135,84,.12);
        }
        .submit-btn {
            width: 100%; padding: 12px 16px;
            background: linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));
            color: #fff; border: none; border-radius: 10px;
            font-size: .95rem; font-weight: 700; font-family: inherit;
            cursor: pointer; margin-top: 8px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 14px rgba(26,135,84,.3);
            transition: all .18s; letter-spacing: .1px;
        }
        .submit-btn:hover { filter: brightness(1.06); transform: translateY(-1px); box-shadow: 0 8px 22px rgba(26,135,84,.35); }
        .submit-btn:active { transform: none; filter: brightness(.97); }
        .alert-error {
            background: #fef2f2; color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 10px 13px; border-radius: 9px;
            margin-bottom: 14px; font-size: .82rem;
            display: flex; align-items: center; gap: 8px;
        }
        .security-note {
            margin-top: 18px; padding: 11px 13px;
            background: #f0fdf4; border: 1px solid #bbf7d0;
            border-radius: 9px; font-size: .76rem; color: #15803d;
            display: flex; align-items: center; gap: 8px;
        }
        .alert-info-soft {
            background: #ecfeff;
            border-color: #a5f3fc;
            color: #155e75;
        }
        .field-compact {
            font-size: .82rem;
        }
        .link-primary-strong {
            color: var(--primary-color,#1a8754);
            font-weight: 600;
        }
        .security-note-warning {
            background: #fff7ed;
            border-color: #fed7aa;
            color: #9a3412;
            margin-top: 14px;
        }
        .license-renew-on-login {
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 1px solid #86efac;
            border-radius: 12px;
            padding: 14px 14px 12px;
            margin-bottom: 16px;
            font-size: .8rem;
            line-height: 1.55;
            color: #14532d;
        }
        .license-renew-on-login h4 {
            margin: 0 0 8px;
            font-size: .88rem;
            font-weight: 800;
            color: #166534;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .license-renew-on-login .pay-row {
            margin: 4px 0;
            word-break: break-all;
        }
        .license-renew-on-login code {
            background: rgba(255,255,255,.85);
            padding: 2px 6px;
            border-radius: 6px;
            font-size: .85em;
        }
        .license-renew-on-login .sub {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed rgba(22,101,52,.25);
            font-size: .76rem;
            color: #15803d;
        }
        .license-renew-vendor {
            font-size: .76rem;
            line-height: 1.55;
            color: #334155;
            background: rgba(255,255,255,.75);
            border: 1px solid rgba(22,101,52,.2);
            border-radius: 10px;
            padding: 10px 11px;
            margin-bottom: 12px;
        }
        .license-renew-success {
            background: #ecfdf5;
            border: 1px solid #6ee7b7;
            color: #065f46;
            border-radius: 10px;
            padding: 11px 12px;
            margin-bottom: 12px;
            font-size: .78rem;
            line-height: 1.5;
        }
        .license-renew-form .lr-fld { margin-bottom: 10px; }
        .license-renew-form label {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 4px;
        }
        .license-renew-form input,
        .license-renew-form select,
        .license-renew-form textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: .85rem;
            font-family: inherit;
            background: #fff;
        }
        .license-renew-form textarea { min-height: 56px; resize: vertical; }
        .license-renew-form .lr-submit {
            margin-top: 6px;
            width: 100%;
            padding: 10px 14px;
            border: none;
            border-radius: 10px;
            font-size: .88rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            background: linear-gradient(135deg,var(--primary-dark,#0f4f20),var(--primary-color,#1a8754));
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .license-renew-form .lr-submit:hover { filter: brightness(1.05); }
        .license-renew-form .lr-amt-display {
            padding: 9px 11px;
            background: #f3f4f6;
            border: 1.5px dashed #9ca3af;
            border-radius: 8px;
            font-size: .88rem;
            font-weight: 700;
            color: #111827;
        }
        .license-renew-form .lr-amt-hint { font-size: .68rem; color: #6b7280; margin-top: 4px; font-weight: 500; }
        .license-expired-login-first {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: .78rem;
            line-height: 1.55;
            color: #78350f;
            text-align: left;
        }
        .license-expired-login-first a { color: var(--primary-color,#1a8754); font-weight: 700; }
        @media (max-width:480px) {
            .auth-card { border-radius: 16px; }
            .card-header { padding: 24px 20px 18px; }
            .card-body { padding: 20px 20px 24px; }
        }
    </style>
</head>
<body class="auth-portal-page admin-auth-page">

<a href="../index.php" class="page-back">
    <i class="fas fa-arrow-left"></i> वेबसाइट
</a>

<div class="auth-card">

    <div class="card-header">
        <?php if ($logoSrc): ?>
            <div class="card-logo-wrap">
                <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="<?php echo htmlspecialchars($siteName); ?>">
            </div>
        <?php else: ?>
            <div class="card-logo-icon"><i class="fas fa-shield-halved"></i></div>
        <?php endif; ?>
        <span class="card-portal-label"><i class="fas fa-lock"></i>&nbsp;Admin Portal</span>
    </div>

    <div class="card-body">
        <div class="card-title"><?php echo is_array($admin2faPending) ? '2FA Verify' : 'लग इन'; ?></div>
        <div class="card-sub"><?php echo is_array($admin2faPending) ? 'Google Authenticator code verify गर्नुहोस्।' : 'आफ्नो username र password प्रविष्ट गर्नुहोस्।'; ?></div>

        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($showLicenseRenewalOnLogin): ?>
        <div class="license-renew-on-login" style="margin: 0 0 18px;">
            <h4><i class="fas fa-building"></i> लाइसेन्स नवीकरण — कार्यालय Admin</h4>
            <div class="license-renew-vendor">
                <strong>सूचना:</strong> SSL certificates तथा domain active शुल्क कृपया तुरुन्तै अनलाइनमार्फत भुक्तानी गर्नुहोला, अन्यथा domain स्वतः suspend हुन सक्नेछ।
                अन्य cloud, maintenance, support तथा license सम्बन्धी लागतको विस्तृत जानकारी तथा भुक्तानी प्रक्रियाका लागि कृपया सम्बन्धित vendor सँग सम्पर्क गर्नुहुन अनुरोध गरिन्छ।
            </div>
            <p class="mb-2" style="font-size:.78rem;opacity:.95;">
                <strong>साइट सेवा म्याद सकियो।</strong> सहकारीका <strong>कार्यालय/साधारण Admin</strong> ले यो बेला लग इन गर्न मिल्दैन।
                आफ्नो Khalti वा eSewa बाट <strong>तलको नम्बरमा</strong> (विक्रेता खाता) रकम पठाउनुहोस्, अनि तलको फारमबाट ref सहित <strong>भुक्तानी सूचना पठाउनुहोस्</strong>।
            </p>
            <?php if ($loginRenewAmount !== ''): ?>
                <div class="pay-row"><strong>रकम (सन्दर्भ):</strong> <?php echo htmlspecialchars($loginRenewAmount, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($loginRenewKhalti !== ''): ?>
                <div class="pay-row"><strong>Khalti मा पठाउने:</strong> <code><?php echo htmlspecialchars($loginRenewKhalti, ENT_QUOTES, 'UTF-8'); ?></code></div>
            <?php endif; ?>
            <?php if ($loginRenewEsewa !== ''): ?>
                <div class="pay-row"><strong>eSewa मा पठाउने:</strong> <code><?php echo htmlspecialchars($loginRenewEsewa, ENT_QUOTES, 'UTF-8'); ?></code></div>
            <?php endif; ?>
            <?php if ($renewalNoticeSent): ?>
            <div class="license-renew-success">
                <strong><i class="fas fa-check-circle me-1"></i>भुक्तानी सूचना पठाइयो।</strong>
                भुक्तानी सूचना प्राप्त भएको छ। कृपया पुष्टि/सक्रिय हुन केही समय प्रतीक्षा गर्नुहोस् वा विक्रेता सम्पर्क गर्नुहोस्।
            </div>
            <?php else: ?>
            <form method="post" class="license-renew-form" action="">
                <input type="hidden" name="action" value="submit_renewal_notice_login">
                <?php echo csrfField(); ?>
                <div class="lr-fld">
                    <span class="lr-amt-hint" style="display:block;margin-bottom:4px;">संस्थाको नाम (साइट सेटिङबाट स्वतः)</span>
                    <div class="lr-amt-display"><?php echo htmlspecialchars($renewalSubmitterCoop !== '' ? $renewalSubmitterCoop : 'सहकारी', ENT_QUOTES, 'UTF-8'); ?></div>
                    <input type="hidden" name="submitter_name" value="<?php echo htmlspecialchars($renewalSubmitterCoop !== '' ? $renewalSubmitterCoop : 'सहकारी', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="lr-fld">
                    <label for="renew_gw">गेटवेइ <span class="text-danger">*</span></label>
                    <select name="gateway" id="renew_gw" required>
                        <option value="khalti">Khalti</option>
                        <option value="esewa" selected>eSewa</option>
                        <option value="other">अन्य</option>
                    </select>
                </div>
                <div class="lr-fld">
                    <label for="renew_txn">कारोबार नम्बर / Ref <span class="text-danger">*</span></label>
                    <input type="text" name="txn_reference" id="renew_txn" required minlength="3" maxlength="180" placeholder="wallet मा देखिएको ref" autocomplete="off">
                </div>
                <div class="lr-fld">
                    <span class="lr-amt-hint" style="display:block;margin-bottom:4px;">रकम (नवीकरण सेटिङमा तोकेको — पठाउँदा बदल्न मिल्दैन)</span>
                    <div class="lr-amt-display" id="renew_amt_display"><?php echo $loginRenewAmount !== '' ? htmlspecialchars($loginRenewAmount, ENT_QUOTES, 'UTF-8') : '— (अझै सेट भएको छैन — व्यवस्थापक सम्पर्क)'; ?></div>
                </div>
                <div class="lr-fld">
                    <label for="renew_note">टिप्पणी</label>
                    <textarea name="renewal_note" id="renew_note" maxlength="2000" placeholder="ऐच्छिक"></textarea>
                </div>
                <button type="submit" class="lr-submit"><i class="fas fa-paper-plane"></i> Pay SSL certificates तथा domain active Charge now</button>
            </form>
            <?php endif; ?>
            <div class="sub">
                <i class="fas fa-user-shield me-1"></i>यो फारम कार्यालय प्रतिनिधिका लागि हो; व्यवस्थापन पहुँच भएको खाताले माथिको लग इन प्रयोग गर्नुहोस्।
            </div>
        </div>
        <?php endif; ?>

        <?php if (is_array($admin2faPending)): ?>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="do_admin_2fa" value="1">
            <?php if (($admin2faPending['mode'] ?? '') === 'setup'): ?>
                <div class="alert-error alert-info-soft">
                    <i class="fas fa-qrcode"></i> Google Authenticator setup आवश्यक छ।
                </div>
                <div class="field">
                    <label>Manual Secret</label>
                    <div class="input-icon">
                        <i class="fas fa-key"></i>
                        <input type="text" readonly value="<?php echo htmlspecialchars((string)($admin2faPending['secret'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <?php if ($admin2faSetupUri !== ''): ?>
                <div class="field field-compact">
                    <a href="https://chart.googleapis.com/chart?chs=220x220&cht=qr&chl=<?php echo urlencode($admin2faSetupUri); ?>" target="_blank" rel="noopener" class="link-primary-strong">QR खोल्नुहोस् (scan गर्न)</a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="field">
                <label>2FA Code / Backup Code</label>
                <div class="input-icon">
                    <i class="fas fa-shield-halved"></i>
                    <input type="text" name="twofa_code" placeholder="123456 वा BACKUPCODE" required autofocus>
                </div>
            </div>
            <button type="submit" class="submit-btn">
                <i class="fas fa-shield-check"></i> 2FA Verify
            </button>
            <?php if (!empty($_SESSION['admin_2fa_backup_plain']) && is_array($_SESSION['admin_2fa_backup_plain'])): ?>
                <div class="security-note security-note-warning">
                    <i class="fas fa-triangle-exclamation"></i>
                    Backup codes: <code><?php echo htmlspecialchars(implode(' , ', $_SESSION['admin_2fa_backup_plain']), ENT_QUOTES, 'UTF-8'); ?></code>
                </div>
                <?php unset($_SESSION['admin_2fa_backup_plain']); ?>
            <?php endif; ?>
        </form>
        <?php elseif (!$showLicenseRenewalOnLogin): ?>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <div class="field">
                <label>युजरनेम</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" placeholder="युजरनेम राख्नुहोस्" required autofocus>
                </div>
            </div>
            <div class="field">
                <label>पासवर्ड</label>
                <div class="input-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                </div>
            </div>
            <button type="submit" class="submit-btn">
                <i class="fas fa-sign-in-alt"></i> लग इन गर्नुहोस्
            </button>
        </form>
        <?php else: ?>
            <div class="field-compact" style="margin-top:6px;">
                <a href="?renewal=1" class="link-primary-strong">भुक्तानी सूचना फारम खुल्लै छ (लग इन चाहिँदैन)</a>
                <div class="small text-secondary" style="margin-top:6px;line-height:1.55;">
                    व्यवस्थापन पहुँच भएको खाताबाट लग इन गर्नुपर्छ भने page माथि स्क्रोल गरी लग इन भाग प्रयोग गर्नुहोस्।
                </div>
            </div>
        <?php endif; ?>

        <div class="security-note">
            <i class="fas fa-shield-halved"></i>
            यो सुरक्षित Admin क्षेत्र हो। सबै गतिविधि audit log मा record हुन्छ।
        </div>
    </div>

</div>

</body>
</html>
