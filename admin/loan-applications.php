<?php
/**
 * Admin: ऋण आवेदन व्यवस्थापन — loan-applications.php
 * =====================================================
 * feedbacks.php pattern: ?view=ID → full-page detail + edit form।
 * Modal पूर्ण रूपले हटाइयो।
 */
$pageTitle   = 'ऋण आवेदन व्यवस्थापन';
$currentPage = 'loans';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';
require_once __DIR__ . '/../includes/auth-roles.php';
/* RBAC: staff hercha matra; mutate (approve/reject/delete) admin+ matra */
if ($_SERVER['REQUEST_METHOD'] === 'POST') { require_role('admin'); checkCSRF(); }

/* ── Auto-ALTER: admin_attachment column थप्ने — MySQL 5.7+ compatible ── */
safeAddColumn($db, 'loan_applications', 'admin_attachment', "VARCHAR(500) DEFAULT '' COMMENT 'Admin reply मा संलग्न file'");
safeAddColumn($db, 'loan_applications', 'updated_at', "TIMESTAMP NULL DEFAULT NULL");

$loanListStatuses = ['pending', 'processing', 'approved', 'rejected', 'disbursed'];

/* ─── Status Update ─── */
if (isset($_POST['update_status'])) {
    $id      = (int)$_POST['id'];
    $status  = clean_text($_POST['status']);
    $remarks = clean_text($_POST['remarks'] ?? '');
    $newFile = adminUploadFile('admin_attachment');

    try {
        if ($newFile) {
            $stmt = $db->prepare("UPDATE loan_applications SET status=?, remarks=?, admin_attachment=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$status, $remarks, $newFile, $id]);
        } else {
            $stmt = $db->prepare("UPDATE loan_applications SET status=?, remarks=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$status, $remarks, $id]);
        }
        /* Member लाई status notification — email/SMS */
        try {
            $nRow = $db->prepare("SELECT full_name, email, phone, tracking_id FROM loan_applications WHERE id=?");
            $nRow->execute([$id]);
            $nData = $nRow->fetch();
            if ($nData) {
                sendMemberStatusUpdate('loan',
                    $nData['email'] ?? '', $nData['phone'] ?? '', $nData['full_name'] ?? '',
                    $status, $remarks, $nData['tracking_id'] ?? '');
            }
        } catch (Exception $e) {}
        setFlash('success', 'स्थिति अपडेट गरियो।');
    } catch (Exception $e) {
        setFlash('error', 'त्रुटि भयो।');
    }
    redirect('loan-applications.php' . ($id ? '?view=' . $id : ''));
}

/* ─── Delete ─── */
if (isset($_POST['delete'])) {
    $id = (int)($_POST['delete_id'] ?? 0);
    try { $db->prepare("DELETE FROM loan_applications WHERE id=?")->execute([$id]); setFlash('success', 'आवेदन मेटाइयो।'); } catch (Exception $e) {}
    redirect('loan-applications.php');
}

/* ─── Quick Status (list view बाट सिधै approve/reject) ─── */
if (isset($_POST['quick_status'])) {
    $qid = (int)($_POST['quick_id'] ?? 0);
    $allowed = ['pending','processing','approved','rejected','disbursed'];
    $qst = in_array($_POST['quick_status_val'] ?? '', $allowed) ? $_POST['quick_status_val'] : 'pending';
    try {
        $db->prepare("UPDATE loan_applications SET status=?, updated_at=NOW() WHERE id=?")->execute([$qst, $qid]);
        try {
            $nr = $db->prepare("SELECT full_name, email, mobile, tracking_id FROM loan_applications WHERE id=?");
            $nr->execute([$qid]); $nd = $nr->fetch();
            if ($nd) sendMemberStatusUpdate('loan', $nd['email']??'', $nd['mobile']??'', $nd['full_name']??'', $qst, '', $nd['tracking_id']??'');
        } catch (Exception $e) {}
        setFlash('success', 'स्थिति परिवर्तन गरियो।');
    } catch (Exception $e) { setFlash('error', 'त्रुटि भयो।'); }
    $redLoanSt = $_GET['status'] ?? '';
    if ($redLoanSt !== '' && !in_array($redLoanSt, $loanListStatuses, true)) {
        $redLoanSt = '';
    }
    $qs = http_build_query([
        'status' => $redLoanSt,
        'search' => mb_substr(trim((string)($_GET['search'] ?? '')), 0, 200, 'UTF-8'),
        'page'   => max(1, (int)($_GET['page'] ?? 1)),
    ]);
    redirect('loan-applications.php?' . $qs);
}

/* ─── Filters & Counts ─── */
$status_filter = $_GET['status'] ?? '';
if ($status_filter !== '' && !in_array($status_filter, $loanListStatuses, true)) {
    $status_filter = '';
}
$search  = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 200, 'UTF-8');
$where   = "1=1"; $lparams = [];
if ($status_filter) { $where .= " AND status = ?"; $lparams[] = $status_filter; }
if ($search !== '') {
    $where .= " AND (full_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR citizenship_no LIKE ? OR tracking_id LIKE ? OR loan_type LIKE ?)";
    $t = "%$search%"; $lparams = array_merge($lparams, [$t,$t,$t,$t,$t,$t]);
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

try {
    $lcnt = $db->prepare("SELECT COUNT(*) FROM loan_applications WHERE $where"); $lcnt->execute($lparams); $total = $lcnt->fetchColumn();
    $totalPages = ceil($total / $limit);
    $lstmt = $db->prepare("SELECT * FROM loan_applications WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset"); $lstmt->execute($lparams); $applications = $lstmt->fetchAll();
    $totalAmount = $db->query("SELECT SUM(loan_amount) FROM loan_applications WHERE status='pending'")->fetchColumn();
} catch (Exception $e) { $applications = []; $total = 0; $totalPages = 0; $totalAmount = 0; }

try {
    $pendingCount    = $db->query("SELECT COUNT(*) FROM loan_applications WHERE status='pending'")->fetchColumn();
    $approvedCount   = $db->query("SELECT COUNT(*) FROM loan_applications WHERE status='approved'")->fetchColumn();
    $rejectedCount   = $db->query("SELECT COUNT(*) FROM loan_applications WHERE status='rejected'")->fetchColumn();
    $processingCount = $db->query("SELECT COUNT(*) FROM loan_applications WHERE status='processing'")->fetchColumn();
} catch (Exception $e) { $pendingCount=$approvedCount=$rejectedCount=$processingCount=0; }

/* ─── Single view ─── */
$viewApp = null;
if (isset($_GET['view'])) {
    $s = $db->prepare("SELECT * FROM loan_applications WHERE id=?");
    $s->execute([(int)$_GET['view']]);
    $viewApp = $s->fetch();
    if (!$viewApp) { setFlash('error', 'आवेदन फेला परेन।'); redirect('loan-applications.php'); }
}

$statusLabel = ['pending'=>'पेन्डिङ','processing'=>'प्रक्रियामा','approved'=>'स्वीकृत','rejected'=>'अस्वीकृत','disbursed'=>'वितरित'];
$statusClass = ['pending'=>'warning','processing'=>'info','approved'=>'success','rejected'=>'danger','disbursed'=>'primary'];

/* ─── Page Header ─── */
if ($viewApp) {
    $trackId = $viewApp['tracking_id'] ?? 'LOAN-' . str_pad($viewApp['id'], 6, '0', STR_PAD_LEFT);
    echo adminPageHeader('ऋण आवेदन विवरण', 'fa-hand-holding-usd',
        'Tracking: ' . $trackId,
        adminBackBtn('loan-applications.php', 'ऋण सूचीमा फर्किनुहोस्'));
} else {
    echo adminPageHeader('ऋण आवेदन व्यवस्थापन', 'fa-hand-holding-usd',
        'सदस्यहरूको ऋण आवेदनहरू — समीक्षा, स्थिति अपडेट र Approval',
        adminStatLink('?status=pending', 'danger', 'पेन्डिङ', $pendingCount));
}

$flash = getFlash(); if ($flash) echo adminAlert($flash['type'] === 'success' ? 'success' : 'danger', $flash['message']);

/* ═══════════════════════════════════════════
   SINGLE DETAIL VIEW
   ═══════════════════════════════════════════ */
if ($viewApp):
    $sc = $statusClass[$viewApp['status']] ?? 'secondary';
    $sl = $statusLabel[$viewApp['status']] ?? $viewApp['status'];
    $trackId = $viewApp['tracking_id'] ?? 'LOAN-' . str_pad($viewApp['id'], 6, '0', STR_PAD_LEFT);
?>
<div class="card shadow-sm mb-4">
    <div class="card-header gradient-card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-hand-holding-usd me-2"></i>ऋण आवेदन विवरण
            <code style="font-size:0.83rem;background:rgba(255,255,255,0.15);padding:2px 10px;border-radius:6px;margin-left:8px;">
                <?php echo htmlspecialchars($trackId); ?>
            </code>
        </h5>
        <span class="badge bg-<?php echo $sc; ?> fs-6"><?php echo $sl; ?></span>
    </div>
    <div class="card-body">
        <div class="row g-4">

            <!-- ── LEFT: Application Details ── -->
            <div class="col-lg-7">

                <!-- आवेदकको जानकारी -->
                <div class="adm-info-group">
                <div class="adm-info-group-header"><i class="fas fa-user"></i>आवेदकको जानकारी</div>
                <table class="table adm-detail-table">
                    <tr><th>पूरा नाम</th><td><strong><?php echo htmlspecialchars($viewApp['full_name'] ?? '—'); ?></strong></td></tr>
                    <tr><th>सदस्य नं.</th><td><?php echo htmlspecialchars($viewApp['member_id'] ?: '—'); ?></td></tr>
                    <tr><th>मोबाइल</th><td><?php echo htmlspecialchars($viewApp['mobile'] ?? '—'); ?></td></tr>
                    <tr><th>इमेल</th><td><?php echo htmlspecialchars($viewApp['email'] ?: '—'); ?></td></tr>
                    <tr><th>नागरिकता नं.</th><td><?php echo htmlspecialchars($viewApp['citizenship_no'] ?: '—'); ?></td></tr>
                    <tr><th>ठेगाना</th><td><?php echo htmlspecialchars($viewApp['address'] ?: '—'); ?></td></tr>
                    <tr><th>दर्ता मिति</th><td><?php echo formatNepaliDate($viewApp['created_at'], true); ?></td></tr>
                </table>

                </div>

                <!-- ऋण जानकारी -->
                <div class="adm-info-group">
                <div class="adm-info-group-header"><i class="fas fa-money-bill-wave"></i>ऋण जानकारी</div>
                <table class="table adm-detail-table">
                    <tr><th>ऋणको प्रकार</th><td><?php echo htmlspecialchars($viewApp['loan_type'] ?? '—'); ?></td></tr>
                    <tr><th>रकम</th>
                        <td><strong class="text-primary fs-5">रू. <?php echo number_format((float)($viewApp['loan_amount'] ?? 0)); ?></strong></td></tr>
                    <tr><th>अवधि</th><td><?php echo $viewApp['loan_tenure'] ? htmlspecialchars($viewApp['loan_tenure']) . ' महिना' : '—'; ?></td></tr>
                    <tr><th>भुक्तानी विधि</th><td><?php echo htmlspecialchars($viewApp['repayment_method'] ?: '—'); ?></td></tr>
                    <tr><th>उद्देश्य</th><td><?php echo htmlspecialchars($viewApp['loan_purpose'] ?: '—'); ?></td></tr>
                </table>

                </div>

                <!-- आय जानकारी -->
                <div class="adm-info-group">
                <div class="adm-info-group-header"><i class="fas fa-briefcase"></i>आय / पेशा जानकारी</div>
                <table class="table adm-detail-table">
                    <tr><th>पेशा</th><td><?php echo htmlspecialchars($viewApp['occupation'] ?: '—'); ?></td></tr>
                    <tr><th>संस्था/व्यवसाय</th><td><?php echo htmlspecialchars($viewApp['organization_name'] ?: '—'); ?></td></tr>
                    <tr><th>मासिक आय</th>
                        <td><?php echo !empty($viewApp['monthly_income']) ? 'रू. ' . number_format((float)$viewApp['monthly_income']) : '—'; ?></td></tr>
                </table>

                </div>

                <!-- धितो जानकारी -->
                <div class="adm-info-group">
                <div class="adm-info-group-header"><i class="fas fa-home"></i>धितो (Collateral) जानकारी</div>
                <table class="table adm-detail-table">
                    <tr><th>धितो प्रकार</th><td><?php echo htmlspecialchars($viewApp['collateral_type'] ?: '—'); ?></td></tr>
                    <tr><th>धितो मूल्य</th>
                        <td><?php echo !empty($viewApp['collateral_value']) ? 'रू. ' . number_format((float)$viewApp['collateral_value']) : '—'; ?></td></tr>
                    <tr><th>विवरण</th><td><?php echo htmlspecialchars($viewApp['collateral_description'] ?: '—'); ?></td></tr>
                </table>

                </div>

                <?php if (!empty($viewApp['guarantor_name'])): ?>
                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-user-shield"></i>जमानी (Guarantor)</div>
                    <table class="table adm-detail-table">
                        <tr><th>नाम</th><td><strong><?php echo htmlspecialchars($viewApp['guarantor_name']); ?></strong></td></tr>
                        <tr><th>सम्बन्ध</th><td><?php echo htmlspecialchars($viewApp['guarantor_relation'] ?: '—'); ?></td></tr>
                        <tr><th>फोन</th><td><a href="tel:<?php echo htmlspecialchars($viewApp['guarantor_phone'] ?? ''); ?>" class="text-decoration-none"><?php echo htmlspecialchars($viewApp['guarantor_phone'] ?: '—'); ?></a></td></tr>
                        <tr><th>ठेगाना</th><td><?php echo htmlspecialchars($viewApp['guarantor_address'] ?: '—'); ?></td></tr>
                    </table>
                </div>
                <?php endif; ?>

                <?php if (!empty($viewApp['documents'])): ?>
                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-file-alt"></i>पेश गरिएका कागजातहरू</div>
                    <div class="p-3 d-flex flex-wrap gap-2">
                        <?php foreach (explode(',', $viewApp['documents']) as $doc): $doc = trim($doc); if (!$doc) continue; ?>
                        <a href="../<?php echo htmlspecialchars($doc); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-pdf me-1"></i><?php echo htmlspecialchars(basename($doc)); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($viewApp['admin_attachment'])): ?>
                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-paperclip"></i>Admin संलग्न Document</div>
                    <div class="p-3 d-flex align-items-center gap-3">
                        <i class="fas fa-file-alt fa-2x text-primary opacity-75"></i>
                        <div class="flex-grow-1 fw-semibold small"><?php echo htmlspecialchars(basename($viewApp['admin_attachment'])); ?></div>
                        <a href="<?php echo htmlspecialchars(SITE_URL . ltrim($viewApp['admin_attachment'], '/')); ?>"
                           class="btn btn-sm btn-outline-primary" target="_blank" download>
                            <i class="fas fa-download me-1"></i>Download
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($viewApp['remarks'])): ?>
                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-sticky-note"></i>Admin टिप्पणी (Member ले Tracker मा देख्छ)</div>
                    <div class="p-3" style="white-space:pre-wrap;font-size:0.9rem;color:#374151;background:#f0fff4;">
                        <?php echo nl2br(htmlspecialchars($viewApp['remarks'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── RIGHT: Status Update Form ── -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header gradient-card-header py-2">
                        <i class="fas fa-edit me-2"></i>स्थिति अपडेट / Admin टिप्पणी / Document
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="id" value="<?php echo $viewApp['id']; ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold"><i class="fas fa-circle-dot me-1"></i>अवस्था</label>
                                <select name="status" class="form-select">
                                    <?php foreach ($statusLabel as $v => $l): ?>
                                    <option value="<?php echo $v; ?>" <?php echo $viewApp['status']===$v?'selected':''; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-reply me-1 text-success"></i>Admin टिप्पणी/कैफियत
                                </label>
                                <textarea name="remarks" class="form-control" rows="4"
                                    placeholder="स्वीकृति/अस्वीकृतिको कारण, सर्तहरू..."
                                ><?php echo htmlspecialchars($viewApp['remarks'] ?? ''); ?></textarea>
                            </div>

                            <!-- Admin ले approval letter वा rejection notice attach गर्न सक्छ -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-paperclip me-1 text-primary"></i>Document संलग्न गर्नुहोस्
                                    <span class="text-muted fw-normal small">— PDF, Word, Image (max 5MB)</span>
                                </label>
                                <input type="file" name="admin_attachment" class="form-control"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <?php if (!empty($viewApp['admin_attachment'])): ?>
                                <div class="form-text text-primary mt-1">
                                    <i class="fas fa-info-circle me-1"></i>हाल: <strong><?php echo htmlspecialchars(basename($viewApp['admin_attachment'])); ?></strong>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-1"></i>अपडेट गर्नुहोस्
                                </button>
                                <a href="loan-applications.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>सूचीमा
                                </a>
                            </div>
                        </form>

                        <hr class="my-3">
                        <form method="POST"
                              onsubmit="return confirm('के तपाईं यो ऋण आवेदन स्थायी रूपले मेटाउन निश्चित हुनुहुन्छ?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="delete" value="1">
                            <input type="hidden" name="delete_id" value="<?php echo $viewApp['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-trash me-1"></i>यो आवेदन मेटाउनुहोस्
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Summary box -->
                <div class="card mt-3 bg-light border-0">
                    <div class="card-body py-3 text-center">
                        <div class="fs-5 fw-bold text-primary">रू. <?php echo number_format((float)($viewApp['loan_amount'] ?? 0)); ?></div>
                        <div class="small text-muted">माग गरिएको ऋण रकम</div>
                        <hr class="my-2">
                        <div class="small text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo $viewApp['loan_tenure'] ? htmlspecialchars($viewApp['loan_tenure']) . ' महिना' : '—'; ?>
                            &nbsp;|&nbsp;
                            <?php echo htmlspecialchars($viewApp['loan_type'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php else: /* ═══════════ LIST VIEW ═══════════ */ ?>

<!-- ── Stat Mini Row ── -->
<div class="stat-mini-row no-print">
    <a href="loan-applications.php" class="stat-mini <?php echo $status_filter===''?'active-filter':''; ?>">
        <div class="sm-icon ic-total"><i class="fas fa-file-alt"></i></div>
        <div class="sm-val"><?php echo $total; ?></div>
        <div class="sm-lbl">जम्मा आवेदन</div>
    </a>
    <a href="?status=pending" class="stat-mini <?php echo $status_filter==='pending'?'active-filter':''; ?>">
        <div class="sm-icon ic-pending"><i class="fas fa-clock"></i></div>
        <div class="sm-val"><?php echo $pendingCount; ?></div>
        <div class="sm-lbl">पेन्डिङ</div>
    </a>
    <a href="?status=processing" class="stat-mini <?php echo $status_filter==='processing'?'active-filter':''; ?>">
        <div class="sm-icon ic-process"><i class="fas fa-spinner"></i></div>
        <div class="sm-val"><?php echo $processingCount; ?></div>
        <div class="sm-lbl">प्रक्रियामा</div>
    </a>
    <a href="?status=approved" class="stat-mini <?php echo $status_filter==='approved'?'active-filter':''; ?>">
        <div class="sm-icon ic-approved"><i class="fas fa-check-circle"></i></div>
        <div class="sm-val"><?php echo $approvedCount; ?></div>
        <div class="sm-lbl">स्वीकृत</div>
    </a>
    <a href="?status=rejected" class="stat-mini <?php echo $status_filter==='rejected'?'active-filter':''; ?>">
        <div class="sm-icon ic-rejected"><i class="fas fa-times-circle"></i></div>
        <div class="sm-val"><?php echo $rejectedCount; ?></div>
        <div class="sm-lbl">अस्वीकृत</div>
    </a>
    <div class="stat-mini" style="cursor:default;">
        <div class="sm-icon ic-amount"><i class="fas fa-rupee-sign"></i></div>
        <div class="sm-val" style="font-size:1.1rem;"><?php echo number_format((float)$totalAmount/100000,2); ?>L</div>
        <div class="sm-lbl">पेन्डिङ रकम</div>
    </div>
</div>

<!-- ── Filter Bar ── -->
<div class="adm-filter-bar no-print">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3 col-6">
            <label>स्थिति</label>
            <select name="status" id="qf_status" class="form-select form-select-sm">
                <option value="">सबै स्थिति</option>
                <option value="pending"    <?php echo $status_filter==='pending'?'selected':''; ?>>⏳ पेन्डिङ</option>
                <option value="processing" <?php echo $status_filter==='processing'?'selected':''; ?>>🔄 प्रक्रियामा</option>
                <option value="approved"   <?php echo $status_filter==='approved'?'selected':''; ?>>✅ स्वीकृत</option>
                <option value="rejected"   <?php echo $status_filter==='rejected'?'selected':''; ?>>❌ अस्वीकृत</option>
                <option value="disbursed"  <?php echo $status_filter==='disbursed'?'selected':''; ?>>💰 वितरित</option>
            </select>
        </div>
        <div class="col-md-6 col-12">
            <label>खोज्नुहोस्</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="नाम, मोबाइल, Tracking ID, नागरिकता नं., ऋण प्रकार...">
                <?php if ($search): ?>
                <a href="?status=<?php echo urlencode($status_filter); ?>" class="btn btn-outline-secondary btn-sm" title="खोज हटाउनुहोस्"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>खोज</button>
        </div>
        <div class="col-md-1 col-6 text-end">
            <a href="?<?php echo http_build_query(['status'=>$status_filter,'search'=>$search,'export'=>'csv']); ?>" class="btn-csv no-print" title="CSV Export"><i class="fas fa-file-csv"></i> CSV</a>
        </div>
    </form>
    <script>document.getElementById('qf_status').addEventListener('change',function(){this.closest('form').submit();});</script>
</div>

<!-- ── Application Table ── -->
<div class="card border-0 shadow-sm" style="border-radius:10px;overflow:hidden;">
    <div class="tbl-header-bar no-print">
        <h6><i class="fas fa-hand-holding-usd me-2 text-success"></i>ऋण आवेदन सूची</h6>
        <span class="result-count-badge"><?php echo $total; ?> आवेदन<?php echo $search ? ' — "'.htmlspecialchars($search).'" खोज' : ''; ?></span>
    </div>
    <div class="table-responsive">
        <table class="table-hover table app-table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:200px;">आवेदक</th>
                    <th>सम्पर्क</th>
                    <th>ऋण विवरण</th>
                    <th>रकम</th>
                    <th>Tracking ID</th>
                    <th>दर्ता मिति</th>
                    <th>स्थिति</th>
                    <th class="no-print">कार्यहरू</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($applications)): ?>
            <tr class="no-results-row"><td colspan="8"><i class="fas fa-inbox fa-2x d-block mb-2"></i>कुनै आवेदन फेला परेन।</td></tr>
            <?php else: foreach ($applications as $app):
                $trackId = $app['tracking_id'] ?: 'LNP-' . str_pad($app['id'], 6, '0', STR_PAD_LEFT);
                $initLetter = mb_strtoupper(mb_substr($app['full_name'] ?? 'A', 0, 1));
            ?>
            <tr data-status="<?php echo htmlspecialchars($app['status']); ?>">
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="av-letter"><?php echo $initLetter; ?></div>
                        <div>
                            <div class="cell-main"><?php echo htmlspecialchars($app['full_name']); ?></div>
                            <?php if ($app['member_id']): ?>
                            <div class="cell-sub">सदस्य: <?php echo htmlspecialchars($app['member_id']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="cell-main"><i class="fas fa-phone fa-xs text-muted me-1"></i><?php echo htmlspecialchars($app['mobile']); ?></div>
                    <?php if ($app['email']): ?><div class="cell-sub"><?php echo htmlspecialchars($app['email']); ?></div><?php endif; ?>
                </td>
                <td>
                    <div class="cell-main"><?php echo htmlspecialchars($app['loan_type']); ?></div>
                    <div class="cell-sub"><?php echo $app['loan_tenure'] ? htmlspecialchars($app['loan_tenure']).' महिना' : ''; ?><?php echo $app['collateral_type'] ? ' · '.htmlspecialchars($app['collateral_type']) : ''; ?></div>
                </td>
                <td class="amount-cell">रू.<?php echo number_format((float)$app['loan_amount']); ?></td>
                <td><span class="track-badge"><?php echo htmlspecialchars($trackId); ?></span></td>
                <td><div class="cell-sub"><?php echo formatNepaliDate($app['created_at']); ?></div></td>
                <td>
                    <span class="badge-status badge-<?php echo htmlspecialchars($app['status']); ?>">
                        <?php echo $statusLabel[$app['status']] ?? $app['status']; ?>
                    </span>
                </td>
                <td class="no-print">
                    <div class="d-flex gap-1 flex-wrap align-items-center">
                        <a href="loan-applications.php?view=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="विस्तृत हेर्नुहोस्">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if ($app['status'] === 'pending' || $app['status'] === 'processing'): ?>
                        <form method="POST" class="qaction-form" onsubmit="return confirm('यो आवेदन स्वीकृत गर्नुहुन्छ?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="quick_status" value="1">
                            <input type="hidden" name="quick_id" value="<?php echo $app['id']; ?>">
                            <input type="hidden" name="quick_status_val" value="approved">
                            <button type="submit" class="btn-qapprove"><i class="fas fa-check me-1"></i>स्वीकृत</button>
                        </form>
                        <form method="POST" class="qaction-form" onsubmit="return confirm('यो आवेदन अस्वीकृत गर्नुहुन्छ?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="quick_status" value="1">
                            <input type="hidden" name="quick_id" value="<?php echo $app['id']; ?>">
                            <input type="hidden" name="quick_status_val" value="rejected">
                            <button type="submit" class="btn-qreject"><i class="fas fa-times me-1"></i>अस्वीकृत</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="p-3 border-top no-print">
        <div class="adm-pagination">
            <?php
            $qs = ['status'=>$status_filter,'search'=>$search];
            $prevPage = $page > 1 ? $page - 1 : null;
            $nextPage = $page < $totalPages ? $page + 1 : null;
            ?>
            <a href="?<?php echo http_build_query(array_merge($qs,['page'=>1])); ?>" class="<?php echo $page==1?'disabled':''; ?>" title="पहिलो"><i class="fas fa-angle-double-left"></i></a>
            <a href="<?php echo $prevPage ? '?'.http_build_query(array_merge($qs,['page'=>$prevPage])) : '#'; ?>" class="<?php echo !$prevPage?'disabled':''; ?>"><i class="fas fa-angle-left"></i></a>
            <?php
            $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <?php if ($i == $page): ?>
            <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
            <a href="?<?php echo http_build_query(array_merge($qs,['page'=>$i])); ?>"><?php echo $i; ?></a>
            <?php endif; endfor; ?>
            <a href="<?php echo $nextPage ? '?'.http_build_query(array_merge($qs,['page'=>$nextPage])) : '#'; ?>" class="<?php echo !$nextPage?'disabled':''; ?>"><i class="fas fa-angle-right"></i></a>
            <a href="?<?php echo http_build_query(array_merge($qs,['page'=>$totalPages])); ?>" class="<?php echo $page==$totalPages?'disabled':''; ?>" title="अन्तिम"><i class="fas fa-angle-double-right"></i></a>
            <span style="font-size:0.78rem;color:#6b7280;margin-left:8px;"><?php echo $page; ?>/<?php echo $totalPages; ?> पेज · <?php echo $total; ?> रेकर्ड</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once 'includes/admin-footer.php'; ?>
