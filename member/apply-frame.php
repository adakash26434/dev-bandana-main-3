<?php
/**
 * सार्वजनिक आवेदन फारमहरू — सदस्य पोर्टल भित्र (iframe, same-origin session)
 * Quick Apply लिंक यहीँबाट खुल्छन्; welfare जस्तो native पेज होइन तर लगिन र सत्र एउटै हुन्छ।
 */
require_once __DIR__ . '/_bootstrap.php';
requireMemberLogin();
memberSecurityHeaders();

$frames = [
    'appointment' => [
        'path' => 'appointment.php',
        'title' => 'भेटघाट बुक गर्नुहोस्',
        'hint' => 'तलको फारममा तपाईंको प्रोफाइल/KYC बाट विवरण auto-fill हुनेछ। मिति र उद्देश्य भर्नुहोस्।',
    ],
    'kyc' => [
        'path' => 'online-kyc.php',
        'title' => 'KYC दर्ता / अपडेट',
        'hint' => 'अनलाइन KYC फारम — लगिन सत्र प्रयोग भइरहेको छ।',
    ],
    'loan' => [
        'path' => 'loan-apply.php',
        'title' => 'ऋण आवेदन',
        'hint' => 'सदस्य प्रोफाइल अनुसार नाम, सम्पर्क आदि भरिनेछन्। ऋण विवरण मात्र थप्नुहोस्।',
    ],
    'account' => [
        'path' => 'online-account.php',
        'title' => 'खाता खोल्ने आवेदन',
        'hint' => 'व्यक्तिगत विवरण प्रोफाइलबाट लिइन्छ। खाता प्रकार र अन्य विवरण भर्नुहोस्।',
    ],
    'digital' => [
        'path' => 'digital-services.php',
        'title' => 'डिजिटल सेवा अनुरोध',
        'hint' => 'डिजिटल सेवा छानेर विवरण पठाउनुहोस्।',
    ],
    'grievance' => [
        'path' => 'grievance.php',
        'title' => 'गुनासो दर्ता',
        'hint' => 'सम्पर्क विवरण प्रोफाइलबाट भरिनेछ। गुनासो विवरण लेख्नुहोस्।',
    ],
    'career' => [
        'path' => 'career.php',
        'title' => 'रोजगार / जागिर',
        'hint' => 'खुला पदहरू र आवेदन यही फ्रेमभित्र।',
    ],
    'emi' => [
        'path' => 'emi-calculator.php',
        'title' => 'EMI गणना',
        'hint' => 'किस्ता क्यालकुलेटर — सार्वजनिक उपकरण।',
    ],
];

$p = $_GET['p'] ?? '';
if (!isset($frames[$p])) {
    header('Location: ' . SITE_URL . 'member/');
    exit;
}

$meta = $frames[$p];
$siteName = getSetting('site_name', 'सहकारी');
$pageTitle = $meta['title'] . ' — ' . $siteName;
/*
 * Path-only URL — iframe सधैं अहिलेको पृष्ठ जस्तै https/host प्रयोग गर्छ (mixed content ब्लक हुँदैन)।
 * embed=1 ले public header मा हेडर/लोडर लुकाउँछ — iframe भित्र फारम देखिन्छ।
 */
$pu = parse_url(rtrim(SITE_URL, '/') . '/');
$pathPrefix = isset($pu['path']) ? rtrim((string)$pu['path'], '/') : '';
$frameSrc = ($pathPrefix === '' ? '' : $pathPrefix) . '/' . ltrim($meta['path'], '/');
$frameSrc = preg_replace('#/{2,}#', '/', $frameSrc);
$qParts = [];
$extraQ = trim((string)($GLOBALS['member_frame_extra_query'] ?? ''));
if ($extraQ !== '') {
    parse_str($extraQ, $extraParsed);
    if (is_array($extraParsed)) {
        $qParts = $extraParsed;
    }
}
$qParts['embed'] = '1';
$frameSrc .= '?' . http_build_query($qParts);
unset($GLOBALS['member_frame_extra_query']);

require __DIR__ . '/includes/chrome.php';
?>

<div class="mem-alert mem-alert-info" style="margin-bottom:14px;font-size:0.86rem;line-height:1.5;">
    <i class="fas fa-shield-halved"></i>
    <?php echo htmlspecialchars($meta['hint']); ?>
    <span class="d-block mt-1" style="opacity:.9;">सम्पूर्ण आवेदन सुरक्षित रूपमा सहकारीमा पठाइन्छ।</span>
</div>

<div class="mem-card mem-apply-frame-card">
    <div class="mem-card-header" style="padding:12px 16px;">
        <div class="mem-card-title" style="font-size:0.92rem;"><i class="fas fa-file-signature"></i><?php echo htmlspecialchars($meta['title']); ?></div>
        <a href="<?php echo SITE_URL; ?>member/tracker.php" style="font-size:0.78rem;font-weight:700;color:var(--mem-primary);text-decoration:none;white-space:nowrap;">Tracker →</a>
    </div>
    <div class="mem-card-body" style="padding:0;">
        <iframe class="mem-public-form-frame" title="<?php echo htmlspecialchars($meta['title']); ?>"
                src="<?php echo htmlspecialchars($frameSrc); ?>"
                loading="eager" referrerpolicy="same-origin"></iframe>
    </div>
</div>

<style>
.mem-apply-frame-card { overflow: hidden; }
.mem-public-form-frame {
    display: block;
    width: 100%;
    min-height: min(78vh, 900px);
    height: 78vh;
    border: 0;
    background: #f8fafc;
}
@media (max-width: 768px) {
    .mem-public-form-frame {
        min-height: 70vh;
        height: 70vh;
    }
}
</style>

<?php require __DIR__ . '/includes/chrome-foot.php'; ?>
