<?php
/**
 * Superadmin मात्र: साइट म्याद + Pay Now / भुक्तानी ref + अन्तिम मिति सेभ (अरू admin लाई पहुँच छैन)
 */
$pageTitle  = 'साइट म्याद';
$currentPage = 'site-license';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';
require_once __DIR__ . '/../includes/site-license-renewal.php';

if (!$isSuperAdmin) {
    setFlash('error', 'यो पृष्ठ Superadmin मात्र खोल्न सकिन्छ।');
    redirect('dashboard.php');
}

$db = getDB();
ensureSiteLicenseRenewalNoticesTable($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        setFlash('error', 'सुरक्षा जाँच असफल।');
        redirect('site-license.php');
    }
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_renewal_settings') {
        updateSetting('site_license_renewal_amount', trim((string) ($_POST['renewal_amount'] ?? '')));
        updateSetting('site_license_khalti_id', trim((string) ($_POST['khalti_id'] ?? '')));
        updateSetting('site_license_esewa_id', trim((string) ($_POST['esewa_id'] ?? '')));
        $em = trim((string) ($_POST['vendor_email'] ?? ''));
        updateSetting('site_license_vendor_email', $em);
        setFlash('success', 'भुक्तानी सम्पर्क सेटिङ सेभ भयो।');
        redirect('site-license.php');
    }

    if ($action === 'cancel_renewal_pending') {
        site_license_renewal_cancel_pending($db);
        setFlash('success', 'पेन्डिङ भुक्तानी सूचना रद्द गरियो — आवश्यक भए फेरि पठाउन सक्नुहुन्छ।');
        redirect('site-license.php');
    }

    if ($action === 'submit_renewal_notice') {
        if (!site_license_expired()) {
            setFlash('error', 'म्याद नसकेको बेला यो फारम आवश्यक छैन।');
            redirect('site-license.php');
        }
        if (site_license_renewal_pending_count($db) > 0) {
            setFlash('warning', 'पहिले नै भुक्तानी सूचना पेन्डिङ छ। सक्रिय गर्न विक्रेतालाई सम्पर्क गर्नुहोस्। दोहोरो भुक्तानी नगर्नुहोस्।');
            redirect('site-license.php');
        }
        $gateway = trim((string) ($_POST['gateway'] ?? ''));
        $txn = trim((string) ($_POST['txn_reference'] ?? ''));
        $amt = trim((string) ($_POST['amount_reported'] ?? ''));
        $note = trim((string) ($_POST['renewal_note'] ?? ''));
        $allowedGw = ['khalti', 'esewa', 'other'];
        if (!in_array($gateway, $allowedGw, true)) {
            setFlash('error', 'गेटवेइ छान्नुहोस्।');
            redirect('site-license.php');
        }
        if (mb_strlen($txn) < 3) {
            setFlash('error', 'कारोबार नम्बर / Ref कम्तिमा ३ अक्षर हुनुपर्छ।');
            redirect('site-license.php');
        }
        $aid = (int) ($_SESSION['admin_id'] ?? 0);
        $auser = trim((string) ($_SESSION['admin_name'] ?? ($_SESSION['admin_username'] ?? '')));
        $st = $db->prepare('INSERT INTO site_license_renewal_notices (status, gateway, txn_reference, amount_reported, note, submitted_by_admin_id, submitted_by_username) VALUES (\'pending\',?,?,?,?,?,?)');
        $st->execute([$gateway, $txn, $amt, $note, $aid > 0 ? $aid : null, $auser !== '' ? $auser : null]);
        $newId = (int) $db->lastInsertId();
        site_license_renewal_notify_vendor($db, [
            'id' => $newId,
            'gateway' => $gateway,
            'txn_reference' => $txn,
            'amount_reported' => $amt,
            'note' => $note,
            'submitted_by_username' => $auser,
        ]);
        setFlash('success', 'भुक्तानी सूचना पठाइयो। अब यही Superadmin ले तलको क्यालेन्डरबाट नयाँ मिति सेभ गर्नुहोस् (विक्रेता सम्पर्क भए पनि अन्तिम मिति यहीँ राखिन्छ)।');
        redirect('site-license.php');
    }

    if ($action === 'save_license_date') {
        $raw = trim((string) ($_POST['valid_until_bs'] ?? ''));
        if ($raw === '') {
            updateSetting('site_license_valid_until_bs', '');
            site_license_sync_mirror_settings('');
            site_license_renewal_clear_pending($db);
            setFlash('success', 'साइट म्याद बन्द गरियो — अब म्याद जाँच हुँदैन।');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            setFlash('error', 'मिति ढाँचा मिलेन (YYYY-MM-DD)।');
        } else {
            $parts = nepali_parse_bs_ymd($raw);
            if ($parts === null || !nepali_bs_date_valid($parts)) {
                setFlash('error', 'अमान्य बि.सं. मिति वा क्यालेन्डर दायराभन्दा बाहिर।');
            } else {
                updateSetting('site_license_valid_until_bs', $raw);
                site_license_sync_mirror_settings($raw);
                site_license_renewal_clear_pending($db);
                setFlash('success', 'साइट म्याद सेभ भयो — बि.सं. (Latin + नेपाली अंक) र ई.सं. DB मा बच्यो।');
            }
        }
        redirect('site-license.php');
    }
}

$untilBs = site_license_until_bs();
$untilBsNp = site_license_until_bs_np();
$untilAd = site_license_until_ad();
$expired = site_license_expired();

$renewalAmount = trim((string) getSetting('site_license_renewal_amount', ''));
$khaltiId = site_license_pay_id_or_default((string) getSetting('site_license_khalti_id', ''));
$esewaId = site_license_pay_id_or_default((string) getSetting('site_license_esewa_id', ''));
$vendorEmail = trim((string) getSetting('site_license_vendor_email', ''));

$pendingCount = site_license_renewal_pending_count($db);
$pendingRow = null;
if ($pendingCount > 0) {
    try {
        $pendingRow = $db->query("SELECT * FROM site_license_renewal_notices WHERE status = 'pending' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $pendingRow = null;
    }
}

echo adminPageHeader('साइट म्याद (लाइसेन्स)', 'fa-calendar-check', 'Superadmin मात्र — भुक्तानी / Pay Now / मिति यहीँ', '');
if ($flash = getFlash()):
?>
<div class="alert alert-<?php echo $flash['type']==='success'?'success':($flash['type']==='warning'?'warning':'danger'); ?> alert-dismissible fade show mb-3"><i class="fas fa-<?php echo $flash['type']==='success'?'check-circle':($flash['type']==='warning'?'exclamation-triangle':'exclamation-circle'); ?> me-2"></i><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- भुक्तानी सम्पर्क सेटिङ -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h5 class="mb-3"><i class="fas fa-wallet me-2 text-success"></i>नवीकरण भुक्तानी (सरल)</h5>
                    <p class="text-muted small mb-3">
                        <strong>स्पष्ट:</strong> तल Khalti / eSewa मा राखिएको नम्बर <strong>भुक्तानी ग्रहण गर्ने खाता</strong> हो (लाइसेन्स प्रदायक / विक्रेता)।
                        <strong>ग्राहक (सहकारी) ले आफ्नो</strong> Khalti वा eSewa wallet बाट <em>त्यही नम्बरमा</em> रकम पठाउँछ — आफ्नो नम्बर होइन।
                        म्याद सकेपछि Superadmin ले तलको फारमबाट ref पठाउँछ; विक्रेता इमेलमा सूचना जान्छ।
                        यो <strong>API भुक्तानी होइन</strong> — Send गरेपछि wallet मा देखिएको ref मात्र राख्नुहोस्।
                    </p>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="action" value="save_renewal_settings">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">नवीकरण रकम (देखाउन मात्र)</label>
                            <input type="text" name="renewal_amount" class="form-control" placeholder="उदा. 5000" value="<?php echo htmlspecialchars($renewalAmount, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">विक्रेता सूचना इमेल</label>
                            <input type="email" name="vendor_email" class="form-control" placeholder="vendor@example.com" value="<?php echo htmlspecialchars($vendorEmail, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Khalti — पैसा पठाउने गन्तव्य ID</label>
                            <div class="form-text small mb-1">ग्राहकले आफ्नो Khalti बाट <strong>यही नम्बरमा</strong> पठाउँछन्।</div>
                            <input type="text" name="khalti_id" class="form-control" value="<?php echo htmlspecialchars($khaltiId, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">eSewa — पैसा पठाउने गन्तव्य ID</label>
                            <div class="form-text small mb-1">ग्राहकले आफ्नो eSewa बाट <strong>यही नम्बरमा</strong> पठाउँछन्।</div>
                            <input type="text" name="esewa_id" class="form-control" value="<?php echo htmlspecialchars($esewaId, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-success"><i class="fas fa-save me-1"></i>भुक्तानी सेटिङ सेभ</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- स्थिति -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">
                        <strong>सजिलो नियम:</strong> तल <strong>बि.सं.</strong> क्यालेन्डरबाट छान्नुहोस्। अन्तिम दिनसम्म साइट चल्छ; त्यसपछि <strong>Expired</strong> — सार्वजनिक र साधारण admin बन्द; Superadmin मात्र यहाँ।
                    </p>
                    <div class="alert alert-secondary border py-2 small mb-3 mb-md-4">
                        <strong class="d-block mb-1"><i class="fas fa-list-ol me-1"></i>नवीकरण क्रम (Superadmin मात्र)</strong>
                        <span class="text-muted">①</span> म्याद सकेपछि तल <strong>Pay Now</strong> अनुसार wallet बाट भुक्तानी →
                        <span class="text-muted">②</span> सोही पृष्ठको फारमबाट <strong>कारोबार ref</strong> पठाउनुहोस् →
                        <span class="text-muted">③</span> तलको <strong>म्याद सेभ</strong> बाट नयाँ अन्तिम दिन (बि.सं.) राख्नुहोस्। अरू Admin लाई यो पृष्ठ र Pay Now देखिँदैन।
                    </div>

                    <?php if ($untilBs === ''): ?>
                        <div class="alert alert-info py-2 small mb-3"><strong>अवस्था:</strong> म्याद बन्द — साइट सधैं चल्छ।</div>
                    <?php elseif ($expired): ?>
                        <div class="alert alert-danger py-3 mb-3">
                            <div class="fw-bold mb-1"><span class="badge bg-danger me-1">म्याद सकियो</span> <span class="badge bg-dark">Expired</span></div>
                            <div class="small">सार्वजनिक पृष्ठ र साधारण admin बन्द। Superadmin: तल भुक्तानी + ref, अनि मिति सेभ। विक्रेता सम्पर्क वैकल्पिक।</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success py-2 small mb-3"><strong>अवस्था:</strong> वैध (Valid)</div>
                    <?php endif; ?>

                    <?php if ($expired && $pendingRow): ?>
                        <div class="alert alert-warning border-warning">
                            <div class="fw-bold mb-2"><i class="fas fa-hourglass-half me-1"></i>भुक्तानी सूचना पेन्डिङ</div>
                            <div class="small mb-2">भुक्तानी पुष्टि भइसकेपछि <strong>यही Superadmin</strong> ले तल <strong>म्याद सेभ</strong> गरी नयाँ मिति राख्नुहोस्। विक्रेता इमेल सूचना मात्र सहायक हो। <strong>दोहोरो भुक्तानी नगर्नुहोस्।</strong></div>
                            <ul class="small mb-3 ps-3">
                                <li>गेटवेइ: <strong><?php echo htmlspecialchars((string)$pendingRow['gateway'], ENT_QUOTES, 'UTF-8'); ?></strong></li>
                                <li>Txn / Ref: <strong><?php echo htmlspecialchars((string)$pendingRow['txn_reference'], ENT_QUOTES, 'UTF-8'); ?></strong></li>
                                <?php if (trim((string)$pendingRow['amount_reported']) !== ''): ?>
                                    <li>रकम: <strong><?php echo htmlspecialchars((string)$pendingRow['amount_reported'], ENT_QUOTES, 'UTF-8'); ?></strong></li>
                                <?php endif; ?>
                                <li>पठाएको: <?php echo htmlspecialchars((string)$pendingRow['created_at'], ENT_QUOTES, 'UTF-8'); ?></li>
                            </ul>
                            <form method="post" class="d-inline" onsubmit="return confirm('पेन्डिङ सूचना रद्द गर्ने?');">
                                <input type="hidden" name="action" value="cancel_renewal_pending">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">गल्ती भयो — रद्द गर्नुहोस्</button>
                            </form>
                        </div>
                    <?php elseif ($expired && !$pendingRow): ?>
                        <div class="border rounded p-3 bg-light mb-3">
                            <h6 class="fw-bold mb-2"><i class="fas fa-mobile-screen-button me-1 text-success"></i>Pay Now <span class="badge bg-dark ms-1">Superadmin मात्र</span> — आफ्नो wallet बाट पठाउनुहोस्</h6>
                            <p class="small text-secondary mb-3 mb-md-2">तलका नम्बर <strong>विक्रेता/लाइसेन्स खाता</strong> हुन्। तपाईं <strong>आफ्नो</strong> Khalti वा eSewa खोलेर Send/Transfer गर्नुहोस् — ग्राहकको नम्बर यहाँ राख्नु पर्दैन।</p>
                            <?php if ($renewalAmount !== ''): ?>
                                <p class="mb-1"><strong>तिर्नुपर्ने रकम (सन्दर्भ):</strong> <?php echo htmlspecialchars($renewalAmount, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <?php if ($khaltiId !== ''): ?>
                                <p class="mb-1"><strong>Khalti मा पठाउने नम्बर:</strong> <code><?php echo htmlspecialchars($khaltiId, ENT_QUOTES, 'UTF-8'); ?></code></p>
                            <?php endif; ?>
                            <?php if ($esewaId !== ''): ?>
                                <p class="mb-1"><strong>eSewa मा पठाउने नम्बर:</strong> <code><?php echo htmlspecialchars($esewaId, ENT_QUOTES, 'UTF-8'); ?></code></p>
                            <?php endif; ?>
                            <?php if ($khaltiId === '' && $esewaId === ''): ?>
                                <p class="text-muted small mb-0">माथि Khalti/eSewa ID सेभ गर्नुहोस्।</p>
                            <?php endif; ?>
                        </div>
                        <form method="post" class="row g-3 mb-0">
                            <input type="hidden" name="action" value="submit_renewal_notice">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">गेटवेइ</label>
                                <select name="gateway" class="form-select" required>
                                    <option value="khalti">Khalti</option>
                                    <option value="esewa">eSewa</option>
                                    <option value="other">अन्य</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">कारोबार नम्बर / Ref <span class="text-danger">*</span></label>
                                <input type="text" name="txn_reference" class="form-control" required placeholder="wallet मा देखिएको ref">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">रकम (आफ्नो wallet बाट पठाएको)</label>
                                <input type="text" name="amount_reported" class="form-control" placeholder="ऐच्छिक" value="<?php echo htmlspecialchars($renewalAmount, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">टिप्पणी</label>
                                <textarea name="renewal_note" class="form-control" rows="2" placeholder="ऐच्छिक"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane me-1"></i>भुक्तानी सूचना पठाउनुहोस्</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($untilBs !== ''): ?>
                        <div class="border rounded p-3 small bg-light mb-4 mt-4">
                            <div class="fw-semibold text-secondary mb-2">DB मा बचेको म्याद</div>
                            <div><span class="text-muted">बि.सं. (Latin)</span> <code><?php echo htmlspecialchars($untilBs, ENT_QUOTES, 'UTF-8'); ?></code></div>
                            <div class="mt-1"><span class="text-muted">बि.सं. (नेपाली अंक)</span> <strong><?php echo htmlspecialchars($untilBsNp, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                            <div class="mt-1"><span class="text-muted">ई.सं. (AD)</span> <code><?php echo $untilAd !== '' ? htmlspecialchars($untilAd, ENT_QUOTES, 'UTF-8') : '—'; ?></code></div>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="mt-3">
                        <input type="hidden" name="action" value="save_license_date">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                        <label class="form-label fw-semibold">सेवा वैध रहने अन्तिम दिन (बि.सं.) — विक्रेता / Superadmin</label>
                        <input type="text" name="valid_until_bs" id="site_license_bs"
                               class="form-control form-control-lg mb-2 nepali-datepicker" autocomplete="off"
                               placeholder="YYYY-MM-DD"
                               value="<?php echo htmlspecialchars($untilBs, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-text mb-3">खाली छोडेर सेभ = म्याद जाँच बन्द। नयाँ मिति सेभ = साइट पुनः चालु; पेन्डिङ भुक्तानी सूचना स्वतः सफा हुन्छ। यो कदम <strong>Superadmin</strong> ले नै गर्छ।</div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>म्याद सेभ गर्नुहोस्</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initNepaliDatepickers === 'function') {
        initNepaliDatepickers(document.getElementById('site_license_bs') || document);
    }
});
</script>
<?php require_once 'includes/admin-footer.php'; ?>
