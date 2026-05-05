<?php
/**
 * Public Digital ID Card Preview — via Tracker verification
 * v9.9 NEW
 *
 * URL: tracker-id-card.php?mid=<member_id>&exp=<unix_ts>&sig=<hmac_sha256>
 * Signed token application-tracker.php मा issue हुन्छ phone+email verify पछि।
 * - Token expiry: 15 minutes
 * - HMAC-SHA256 with AUTH_SECRET / SECRET_KEY (config.php)
 * - Read-only preview: print/download disabled, watermark added
 * - कुनै login आवश्यक छैन
 */
require_once __DIR__ . '/includes/config.php';

/* ── Token validation ── */
$mid = (int)($_GET['mid'] ?? 0);
$exp = (int)($_GET['exp'] ?? 0);
$sig = (string)($_GET['sig'] ?? '');

$valid = false;
$errMsg = '';

if (!$mid || !$exp || !$sig) {
    $errMsg = 'अमान्य लिङ्क — Tracker verification बाट मात्र आउनु पर्छ।';
} elseif ($exp < time()) {
    $errMsg = 'यो लिङ्कको म्याद सकिएको छ (15 मिनेट)। कृपया फेरि Tracker बाट verify गर्नुहोस्।';
} else {
    $secret  = defined('AUTH_SECRET') ? AUTH_SECRET : (defined('SECRET_KEY') ? SECRET_KEY : 'aakash-fallback-secret-2026');
    $payload = $mid . '.' . $exp;
    $expected= hash_hmac('sha256', $payload, $secret);
    if (hash_equals($expected, $sig)) {
        $valid = true;
    } else {
        $errMsg = 'सुरक्षा हस्ताक्षर मेल खाएन। यो लिङ्क सम्भवतः छेडछाड भएको छ।';
    }
}

/* ── Fetch member ── */
$mem = null;
if ($valid) {
    try {
        $db = getDB();
        $st = $db->prepare("SELECT id, name, member_card_no, phone, email, address, dob, gender,
                                   approval_status, id_card_generated, id_card_generated_at,
                                   profile_image, sadasyata_number, created_at
                            FROM members
                            WHERE id = ? AND is_active = 1
                              AND approval_status = 'approved'
                              AND id_card_generated = 1
                            LIMIT 1");
        $st->execute([$mid]);
        $mem = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$mem) {
            $valid = false;
            $errMsg = 'सदस्य अहिले उपलब्ध छैन वा ID Card अझै Generate भएको छैन।';
        }
    } catch (\Throwable $e) {
        $valid = false;
        $errMsg = 'डेटा लोड गर्दा त्रुटि भयो। पुनः प्रयास गर्नुहोस्।';
        error_log('tracker-id-card fetch: ' . $e->getMessage());
    }
}

/* ── Site settings ── */
$siteName = function_exists('getSetting') ? getSetting('site_name', 'आकाश सहकारी') : 'आकाश सहकारी';
$siteUrl  = SITE_URL;
$logoPath = function_exists('getSetting') ? getSetting('logo', 'assets/images/logo.png') : 'assets/images/logo.png';
$address  = function_exists('getSetting') ? getSetting('address', 'काठमाडौं, नेपाल') : 'काठमाडौं, नेपाल';

/* No-cache + no-index */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
?>
<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<meta name="robots" content="noindex,nofollow">
<title>Digital ID Card Preview — <?php echo htmlspecialchars($siteName); ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; }
body {
    font-family: 'Mukta','Noto Sans Devanagari',-apple-system,sans-serif;
    background: linear-gradient(135deg,#0f3d1f 0%,var(--primary-color) 50%,var(--primary-light) 100%);
    min-height: 100vh;
    margin: 0;
    padding: 24px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    /* Anti-screenshot deterrent (works in some browsers) */
    -webkit-touch-callout: none;
    user-select: none;
}
.preview-wrap {
    max-width: 480px;
    width: 100%;
    background: #fff;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,.35);
    position: relative;
}
.preview-header {
    background: linear-gradient(135deg,var(--primary-color),var(--primary-light));
    color: #fff;
    padding: 14px 20px;
    text-align: center;
    font-size: .82rem;
    font-weight: 600;
    letter-spacing: .3px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.id-card {
    position: relative;
    background: linear-gradient(135deg,#0f3d1f 0%,var(--primary-color) 60%,var(--primary-light) 100%);
    color: #fff;
    padding: 22px 22px 26px;
    margin: 18px;
    border-radius: 16px;
    box-shadow: inset 0 0 0 2px rgba(255,255,255,.12), 0 8px 24px rgba(0,0,0,.18);
    overflow: hidden;
}
.id-card::before {
    content: 'PREVIEW';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%) rotate(-30deg);
    font-size: 4rem;
    font-weight: 900;
    color: rgba(255,255,255,.07);
    letter-spacing: 8px;
    pointer-events: none;
    z-index: 0;
}
.id-top {
    display: flex; align-items: center; gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,.18);
    padding-bottom: 12px; margin-bottom: 14px;
    position: relative; z-index: 1;
}
.id-top img { width: 44px; height: 44px; border-radius: 10px; background:#fff; padding: 4px; object-fit: contain; }
.id-top .org { font-weight: 700; font-size: 1rem; line-height: 1.2; }
.id-top .org-sub { font-size: .7rem; opacity: .85; letter-spacing: 1px; }
.id-body { display: flex; gap: 14px; align-items: flex-start; position: relative; z-index: 1; }
.id-photo {
    width: 92px; height: 110px; border-radius: 10px;
    background: rgba(255,255,255,.12);
    border: 2px solid rgba(255,255,255,.4);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
    font-size: 2rem; color: rgba(255,255,255,.4);
}
.id-photo img { width:100%; height:100%; object-fit: cover; }
.id-fields { flex: 1; min-width: 0; }
.id-name { font-size: 1.05rem; font-weight: 700; margin-bottom: 4px; line-height: 1.2; }
.id-num { font-size: .82rem; opacity: .92; margin-bottom: 10px; font-family: 'Courier New',monospace; letter-spacing: 1px; }
.id-row { display: flex; font-size: .76rem; margin-bottom: 4px; gap: 6px; }
.id-row .lbl { opacity: .75; min-width: 56px; }
.id-row .val { flex: 1; word-break: break-all; }
.id-footer {
    margin-top: 14px; padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,.18);
    font-size: .68rem; opacity: .8; text-align: center;
    position: relative; z-index: 1;
}
.preview-meta {
    padding: 14px 20px 18px;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    font-size: .78rem;
    color: #6b7280;
    text-align: center;
    line-height: 1.6;
}
.preview-meta strong { color: var(--primary-color); }
.btn-back {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 10px;
    background: var(--primary-color); color: #fff !important; text-decoration: none;
    padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: .82rem;
}
.error-card {
    max-width: 440px; background: #fff; border-radius: 16px; padding: 32px 24px;
    text-align: center; box-shadow: 0 12px 40px rgba(0,0,0,.2);
}
.error-icon { width: 64px; height: 64px; background: #fef2f2; color: #b91c1c;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 1.6rem; }
.error-card h3 { color: #b91c1c; font-size: 1.1rem; margin: 0 0 8px; }
.error-card p { color: #6b7280; font-size: .9rem; margin: 0 0 18px; line-height: 1.65; }
@media print { body { display: none !important; } } /* prevent print */
</style>
<script>
/* Disable right-click + key shortcuts to discourage trivial copying.
   यो absolute security होइन — सिर्फ casual abuse रोक्ने। */
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && ['s','p','u'].includes(e.key.toLowerCase())) e.preventDefault();
    if (e.key === 'PrintScreen') e.preventDefault();
});
</script>
</head>
<body>

<?php if (!$valid || !$mem): ?>
    <div class="error-card">
        <div class="error-icon"><i class="fas fa-shield-exclamation"></i></div>
        <h3>लिङ्क पहुँच असफल</h3>
        <p><?php echo htmlspecialchars($errMsg ?: 'यो लिङ्क मान्य छैन।'); ?></p>
        <a href="<?php echo $siteUrl; ?>application-tracker.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Tracker मा फर्किनुहोस्
        </a>
    </div>
<?php else: ?>
    <?php
        $cardNo   = htmlspecialchars($mem['member_card_no'] ?: ('MEM-'.str_pad($mem['id'],6,'0',STR_PAD_LEFT)));
        $sadNo    = htmlspecialchars($mem['sadasyata_number'] ?? '—');
        $name     = htmlspecialchars($mem['name']);
        $phone    = htmlspecialchars($mem['phone'] ?? '—');
        $emailMa  = $mem['email'] ?? '';
        /* mask middle of email for privacy: r***@gmail.com */
        if ($emailMa && strpos($emailMa,'@') !== false) {
            [$u,$d] = explode('@',$emailMa,2);
            $u = strlen($u)>2 ? substr($u,0,1).str_repeat('*',max(1,strlen($u)-2)).substr($u,-1) : $u[0].'*';
            $emailMa = $u . '@' . $d;
        }
        $emailMa  = htmlspecialchars($emailMa ?: '—');
        $genTs    = $mem['id_card_generated_at'] ? date('d/m/Y', strtotime($mem['id_card_generated_at'])) : '—';
        $photo    = $mem['profile_image'] ?? '';
    ?>
    <div class="preview-wrap">
        <div class="preview-header">
            <i class="fas fa-shield-halved"></i>
            Verified Preview · <?php echo date('d M Y, H:i'); ?>
        </div>
        <div class="id-card">
            <div class="id-top">
                <?php if ($logoPath): ?>
                    <img src="<?php echo $siteUrl . htmlspecialchars($logoPath); ?>" alt="" onerror="this.style.display='none'">
                <?php endif; ?>
                <div>
                    <div class="org"><?php echo htmlspecialchars($siteName); ?></div>
                    <div class="org-sub">DIGITAL MEMBER ID</div>
                </div>
            </div>
            <div class="id-body">
                <div class="id-photo">
                    <?php if ($photo): ?>
                        <img src="<?php echo $siteUrl . htmlspecialchars($photo); ?>" alt="" onerror="this.parentNode.innerHTML='<i class=\'fas fa-user\'></i>'">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="id-fields">
                    <div class="id-name"><?php echo $name; ?></div>
                    <div class="id-num"><?php echo $cardNo; ?></div>
                    <div class="id-row"><span class="lbl">सदस्यता:</span><span class="val"><?php echo $sadNo; ?></span></div>
                    <div class="id-row"><span class="lbl">फोन:</span><span class="val"><?php echo $phone; ?></span></div>
                    <div class="id-row"><span class="lbl">इमेल:</span><span class="val"><?php echo $emailMa; ?></span></div>
                    <div class="id-row"><span class="lbl">जारी मिति:</span><span class="val"><?php echo $genTs; ?></span></div>
                </div>
            </div>
            <div class="id-footer">
                <?php echo htmlspecialchars($address); ?> · आधिकारिक डिजिटल परिचयपत्र
            </div>
        </div>
        <div class="preview-meta">
            <strong>🔐 Verified Public Preview</strong><br>
            यो preview <?php echo date('H:i'); ?> मा generate भयो र <strong>१५ मिनेटमा expire</strong> हुन्छ।<br>
            पूर्ण उपयोग र Download को लागि <a href="<?php echo $siteUrl; ?>member/login.php" style="color:var(--primary-color);font-weight:600;">Member Portal</a> मा Login गर्नुहोस्।
            <br>
            <a href="<?php echo $siteUrl; ?>application-tracker.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Tracker मा फर्किनुहोस्
            </a>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
