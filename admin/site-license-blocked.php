<?php
/**
 * म्याद सकिएपछि साधारण admin — सन्देश + logout (admin-header छैन)।
 */
require_once __DIR__ . '/../includes/config.php';

if (!isAdminLoggedIn()) {
    header('Location: ' . ADMIN_URL . 'index.php');
    exit;
}
if (!empty($_SESSION['is_superadmin'])) {
    header('Location: ' . ADMIN_URL . 'site-license.php');
    exit;
}

$site = function_exists('getSetting') ? getSetting('site_name', 'सहकारी') : 'सहकारी';
$siteH = htmlspecialchars($site, ENT_QUOTES, 'UTF-8');
$untilBs = function_exists('site_license_until_bs') ? site_license_until_bs() : '';
$untilBsNp = function_exists('site_license_until_bs_np') ? site_license_until_bs_np() : '';
$untilAd = function_exists('site_license_until_ad') ? site_license_until_ad() : '';
$untilBsH = htmlspecialchars($untilBs, ENT_QUOTES, 'UTF-8');
$untilBsNpH = htmlspecialchars($untilBsNp, ENT_QUOTES, 'UTF-8');
$untilAdH = htmlspecialchars($untilAd, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ne">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>म्याद सकियो — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --coop-900: #14532d;
            --coop-700: #15803d;
            --coop-600: #16a34a;
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --amber-bg: #fffbeb;
            --amber-br: #fcd34d;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Noto Sans Devanagari", "Mukta", sans-serif;
            background: linear-gradient(165deg, #ecfdf5 0%, #f0fdf4 28%, #f8fafc 72%);
            color: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(16px, 4vw, 32px);
        }
        .shell { width: 100%; max-width: 520px; }
        .card-main {
            border-radius: 20px;
            border: 1px solid var(--line);
            box-shadow: 0 4px 6px -1px rgba(15, 23, 42, .06), 0 20px 50px -12px rgba(22, 101, 52, .12);
            overflow: hidden;
            background: #fff;
        }
        .card-head {
            background: linear-gradient(135deg, var(--coop-900) 0%, var(--coop-700) 100%);
            color: #fff;
            padding: 1.35rem 1.5rem 1.25rem;
            text-align: center;
        }
        .card-head h1 {
            margin: 0;
            font-size: clamp(1.1rem, 3.2vw, 1.3rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            line-height: 1.35;
        }
        .card-head .sub {
            margin: 0.65rem 0 0;
            opacity: 0.92;
            font-size: 0.9rem;
            line-height: 1.55;
        }
        .ico-wrap {
            width: 72px;
            height: 72px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: var(--amber-bg);
            border: 1px solid var(--amber-br);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(217, 119, 6, .15);
        }
        .ico-wrap i { font-size: 1.85rem; color: #d97706; }
        .card-body-inner { padding: 1.4rem 1.5rem 1.5rem; }
        .pill-row { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; margin-bottom: 1rem; }
        .pill {
            font-size: 0.78rem;
            font-weight: 600;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .dates-box {
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 1rem 1.1rem;
            margin-bottom: 1.15rem;
            text-align: left;
            font-size: 0.9rem;
        }
        .dates-box .lbl { font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); margin-bottom: 0.2rem; }
        .steps {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 14px;
            padding: 1rem 1.1rem;
            margin-bottom: 1.25rem;
            text-align: left;
        }
        .steps ol { margin: 0; padding-left: 1.2rem; font-size: 0.88rem; line-height: 1.65; color: #334155; }
        .btn-logout {
            border-radius: 12px;
            font-weight: 600;
            padding: 0.5rem 1.25rem;
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="card-main">
        <div class="card-head">
            <div class="ico-wrap"><i class="fas fa-calendar-xmark" aria-hidden="true"></i></div>
            <h1>साइट सेवा म्याद सकियो</h1>
            <p class="sub"><strong><?php echo $siteH; ?></strong> — Admin panel साधारण प्रयोगकर्ताका लागि बन्द छ।</p>
        </div>
        <div class="card-body-inner text-center">
            <div class="pill-row">
                <span class="pill"><i class="fas fa-circle-exclamation me-1"></i>म्याद सकियो · Expired</span>
            </div>

            <?php if ($untilBs !== ''): ?>
            <div class="dates-box">
                <div class="lbl">अन्तिम वैध दिन / Last valid day</div>
                <div><strong>बि.सं.</strong> <?php echo $untilBsNpH; ?> <span class="text-muted">(<code><?php echo $untilBsH; ?></code>)</span></div>
                <?php if ($untilAd !== ''): ?>
                <div class="mt-2"><strong>ई.सं. / AD</strong> <code><?php echo $untilAdH; ?></code></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="steps">
                <div class="fw-semibold text-success mb-2 small"><i class="fas fa-list-check me-1"></i>के गर्ने?</div>
                <ol>
                    <li><strong>भुक्तानी सूचना:</strong> कार्यालय Admin ले <strong><code>/admin/</code></strong> लग इन URL खोलेर (लग इन नगरीकन) Pay Now + फारमबाट पठाउन सक्छन्।</li>
                    <li><strong>Superadmin</strong> ले मात्र Admin → <strong>साइट म्याद</strong> खोल्छन् — त्यहाँ पेन्डिङ सूचना र <strong>नयाँ मिति सेभ</strong> हुन्छ।</li>
                    <li>वैकल्पिक: लाइसेन्स वा प्राविधिक मद्दतका लागि <strong>विक्रेता / सहयोग टोली</strong> सम्पर्क।</li>
                </ol>
            </div>

            <a href="logout.php" class="btn btn-outline-secondary btn-logout">
                <i class="fas fa-right-from-bracket me-2"></i>लगआउट
            </a>
        </div>
    </div>
</div>
</body>
</html>
