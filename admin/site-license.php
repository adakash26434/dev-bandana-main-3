<?php
/**
 * Superadmin: साइट सेवा म्याद (एक मिति) — साधारण लाइसेन्स।
 */
$pageTitle  = 'साइट म्याद';
$currentPage = 'site-license';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';

if (!$isSuperAdmin) {
    setFlash('error', 'यो पृष्ठ Superadmin मात्र खोल्न सकिन्छ।');
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_license_date') {
    $raw = trim((string) ($_POST['valid_until_bs'] ?? ''));
    if ($raw === '') {
        updateSetting('site_license_valid_until_bs', '');
        site_license_sync_mirror_settings('');
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
            setFlash('success', 'साइट म्याद सेभ भयो — बि.सं. (Latin + नेपाली अंक) र ई.सं. DB मा बच्यो।');
        }
    }
    redirect('site-license.php');
}

$untilBs = site_license_until_bs();
$untilBsNp = site_license_until_bs_np();
$untilAd = site_license_until_ad();
$expired = site_license_expired();

echo adminPageHeader('साइट म्याद (लाइसेन्स)', 'fa-calendar-check', 'Superadmin मात्र — अन्तिम वैध दिन सेट गर्नुहोस्', '');
if ($flash = getFlash()):
?>
<div class="alert alert-<?php echo $flash['type']==='success'?'success':'danger'; ?> alert-dismissible fade show mb-3"><i class="fas fa-<?php echo $flash['type']==='success'?'check-circle':'exclamation-circle'; ?> me-2"></i><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <p class="text-muted small mb-3">
                        <strong>सजिलो नियम:</strong> तल <strong>बि.सं.</strong> क्यालेन्डरबाट छान्नुहोस्। सेभ गर्दा DB मा <strong>बि.सं. (Latin)</strong>, <strong>बि.सं. (नेपाली अंक)</strong>, र <strong>ई.सं. (AD)</strong> तीनै अपडेट हुन्छन्।
                        अन्तिम दिनसम्म साइट चल्छ; त्यसपछिको दिनदेखि <strong>म्याद सकियो (Expired)</strong> — सार्वजनिक पृष्ठ र साधारण admin बन्द; <strong>Superadmin</strong> मात्र यहाँ नयाँ मिति राख्न सक्छ।
                    </p>

                    <?php if ($untilBs === ''): ?>
                        <div class="alert alert-info py-2 small mb-3"><strong>अवस्था:</strong> म्याद बन्द — साइट सधैं चल्छ।</div>
                    <?php elseif ($expired): ?>
                        <div class="alert alert-danger py-3 mb-3">
                            <div class="fw-bold mb-1"><span class="badge bg-danger me-1">म्याद सकियो</span> <span class="badge bg-dark">Expired</span></div>
                            <div class="small">आजको बि.सं. अन्तिम दिनभन्दा ठूलो भइसकेको छ। अरू admin login गर्न सक्दैनन्; सार्वजनिक पृष्ठमा पनि सन्देश देखिन्छ।</div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success py-2 small mb-3"><strong>अवस्था:</strong> वैध (Valid) — अन्तिम दिनसम्म सेवा चल्छ।</div>
                    <?php endif; ?>

                    <?php if ($untilBs !== ''): ?>
                        <div class="border rounded p-3 small bg-light mb-4">
                            <div class="fw-semibold text-secondary mb-2">DB मा बचेको (छानेपछि सेभ)</div>
                            <div><span class="text-muted">बि.सं. (Latin)</span> <code><?php echo htmlspecialchars($untilBs, ENT_QUOTES, 'UTF-8'); ?></code></div>
                            <div class="mt-1"><span class="text-muted">बि.सं. (नेपाली अंक)</span> <strong><?php echo htmlspecialchars($untilBsNp, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                            <div class="mt-1"><span class="text-muted">ई.सं. (AD)</span> <code><?php echo $untilAd !== '' ? htmlspecialchars($untilAd, ENT_QUOTES, 'UTF-8') : '—'; ?></code></div>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="mt-3">
                        <input type="hidden" name="action" value="save_license_date">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                        <label class="form-label fw-semibold">सेवा वैध रहने अन्तिम दिन (बि.सं.)</label>
                        <input type="text" name="valid_until_bs" id="site_license_bs"
                               class="form-control form-control-lg mb-2 nepali-datepicker" autocomplete="off"
                               placeholder="YYYY-MM-DD"
                               value="<?php echo htmlspecialchars($untilBs, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-text mb-3">खाली छोडेर सेभ गर्नुभयो भने म्याद जाँच पूर्ण बन्द हुन्छ। क्यालेन्डरबाट छान्नुहोस्।</div>

                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>सेभ गर्नुहोस्</button>
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
