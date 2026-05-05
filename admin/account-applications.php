<?php
/**
 * Admin: खाता आवेदन व्यवस्थापन — account-applications.php
 * ============================================================
 * feedbacks.php pattern: ?view=ID → full-page detail + edit form।
 * Modal पूर्ण रूपले हटाइयो।
 */
$pageTitle   = 'खाता आवेदन व्यवस्थापन';
$currentPage = 'account-apps';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';
require_once __DIR__ . '/../includes/auth-roles.php';
/* RBAC: staff hercha matra; mutate (approve/reject/delete) admin+ matra */
if ($_SERVER['REQUEST_METHOD'] === 'POST') { require_role('admin'); checkCSRF(); }

/* ── Auto-ALTER — MySQL 5.7+ compatible ── */
safeAddColumn($db, 'account_applications', 'admin_attachment', "VARCHAR(500) DEFAULT '' COMMENT 'Admin reply मा संलग्न file'");
safeAddColumn($db, 'account_applications', 'updated_at', "TIMESTAMP NULL DEFAULT NULL");

$accountListStatuses = ['pending', 'approved', 'rejected'];

/* ─── Status Update ─── */
if (isset($_POST['update_status'])) {
    $id      = intval($_POST['id']);
    $status  = clean_text($_POST['status']);
    $remarks = clean_text($_POST['remarks'] ?? '');
    $newFile = adminUploadFile('admin_attachment');

    try {
        if ($newFile) {
            $stmt = $db->prepare("UPDATE account_applications SET status=?, remarks=?, admin_attachment=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$status, $remarks, $newFile, $id]);
        } else {
            $stmt = $db->prepare("UPDATE account_applications SET status=?, remarks=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$status, $remarks, $id]);
        }
        /* Member लाई status notification — email/SMS */
        try {
            $nRow = $db->prepare("SELECT full_name, email, phone, tracking_id FROM account_applications WHERE id=?");
            $nRow->execute([$id]);
            $nData = $nRow->fetch();
            if ($nData) {
                sendMemberStatusUpdate('account',
                    $nData['email'] ?? '', $nData['phone'] ?? '', $nData['full_name'] ?? '',
                    $status, $remarks, $nData['tracking_id'] ?? '');
            }
        } catch (Throwable $e) { error_log("[account-applications.php] " . $e->getMessage()); }
        setFlash('success', 'स्थिति अपडेट भयो।');
    } catch (Exception $e) {
        setFlash('error', 'त्रुटि भयो।');
    }
    redirect('account-applications.php' . ($id ? '?view=' . $id : ''));
}

/* ─── Delete ─── */
if (isset($_POST['delete'])) {
    $id = (int)($_POST['delete_id'] ?? 0);
    try { $db->prepare("DELETE FROM account_applications WHERE id=?")->execute([$id]); setFlash('success', 'आवेदन मेटाइयो।'); } catch (Throwable $e) { error_log("[account-applications.php] " . $e->getMessage()); }
    redirect('account-applications.php');
}

/* ─── Quick Status ─── */
if (isset($_POST['quick_status'])) {
    $qid = (int)($_POST['quick_id'] ?? 0);
    $allowed = ['pending','approved','rejected'];
    $qst = in_array($_POST['quick_status_val'] ?? '', $allowed) ? $_POST['quick_status_val'] : 'pending';
    try {
        $db->prepare("UPDATE account_applications SET status=?, updated_at=NOW() WHERE id=?")->execute([$qst, $qid]);
        try {
            $nr = $db->prepare("SELECT full_name, email, mobile, tracking_id FROM account_applications WHERE id=?");
            $nr->execute([$qid]); $nd = $nr->fetch();
            if ($nd) sendMemberStatusUpdate('account', $nd['email']??'', $nd['mobile']??'', $nd['full_name']??'', $qst, '', $nd['tracking_id']??'');
        } catch (Throwable $e) { error_log("[account-applications.php] " . $e->getMessage()); }
        setFlash('success', 'खाता आवेदन स्थिति परिवर्तन गरियो।');
    } catch (Exception $e) { setFlash('error', 'त्रुटि भयो।'); }
    $redAccSt = $_GET['status'] ?? '';
    if ($redAccSt !== '' && !in_array($redAccSt, $accountListStatuses, true)) {
        $redAccSt = '';
    }
    $qs = http_build_query([
        'status' => $redAccSt,
        'search' => mb_substr(trim((string)($_GET['search'] ?? '')), 0, 200, 'UTF-8'),
        'page'   => max(1, (int)($_GET['page'] ?? 1)),
    ]);
    redirect('account-applications.php?' . $qs);
}

/* ─── Filter / Search / Pagination ─── */
$status_filter = $_GET['status'] ?? '';
if ($status_filter !== '' && !in_array($status_filter, $accountListStatuses, true)) {
    $status_filter = '';
}
$search        = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 200, 'UTF-8');
$page          = max(1, (int)($_GET['page'] ?? 1));
$limit         = 15; $offset = ($page-1)*$limit;
$where = '1=1'; $params = [];
if ($status_filter) { $where .= ' AND status = ?'; $params[] = $status_filter; }
if ($search !== '') {
    $where .= ' AND (full_name LIKE ? OR full_name_en LIKE ? OR mobile LIKE ? OR email LIKE ? OR tracking_id LIKE ? OR citizenship_no LIKE ?)';
    $t = "%$search%"; $params = array_merge($params, [$t,$t,$t,$t,$t,$t]);
}
try {
    $cntS = $db->prepare("SELECT COUNT(*) FROM account_applications WHERE $where"); $cntS->execute($params); $totalCount = (int)$cntS->fetchColumn();
    $totalPages = max(1, ceil($totalCount / $limit));
    $stmt = $db->prepare("SELECT * FROM account_applications WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params); $applications = $stmt->fetchAll();
} catch (Exception $e) { $applications = []; $totalCount = 0; $totalPages = 1; }

/* ─── Single view ─── */
$viewApp = null;
if (isset($_GET['view'])) {
    $s = $db->prepare("SELECT * FROM account_applications WHERE id=?");
    $s->execute([(int)$_GET['view']]);
    $viewApp = $s->fetch();
    if (!$viewApp) { setFlash('error', 'आवेदन फेला परेन।'); redirect('account-applications.php'); }
}

$statusClass = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'];
$statusLabel = ['pending'=>'पेन्डिङ','approved'=>'स्वीकृत','rejected'=>'अस्वीकृत'];
$accTypes    = ['saving'=>'बचत','current'=>'चल्ती','fixed'=>'मुद्दती','recurring'=>'आवधिक','child'=>'बाल बचत'];

/* ─── Counts ─── */
try {
    $pendingCount  = $db->query("SELECT COUNT(*) FROM account_applications WHERE status='pending'")->fetchColumn();
    $approvedCount = $db->query("SELECT COUNT(*) FROM account_applications WHERE status='approved'")->fetchColumn();
    $rejectedCount = $db->query("SELECT COUNT(*) FROM account_applications WHERE status='rejected'")->fetchColumn();
} catch (Exception $e) { $pendingCount=$approvedCount=$rejectedCount=0; }

/* ─── Page Header ─── */
if ($viewApp) {
    $trackId = $viewApp['tracking_id'] ?? 'ACC-' . str_pad($viewApp['id'], 6, '0', STR_PAD_LEFT);
    echo adminPageHeader('खाता आवेदन विवरण', 'fa-user-plus',
        'Tracking: ' . $trackId,
        adminBackBtn('account-applications.php', 'खाता आवेदन सूचीमा'));
} else {
    echo adminPageHeader('खाता आवेदन व्यवस्थापन', 'fa-user-plus',
        'सदस्यहरूको नयाँ खाता आवेदनहरू — समीक्षा र स्थिति अपडेट',
        adminStatLink('?status=pending', 'danger', 'पेन्डिङ', $pendingCount));
}

$flash = getFlash(); if ($flash) echo adminAlert($flash['type'] === 'success' ? 'success' : 'danger', $flash['message']);

/* ═══════════════════════════════════
   SINGLE DETAIL VIEW
   ═══════════════════════════════════ */
if ($viewApp):
    $sc = $statusClass[$viewApp['status']] ?? 'secondary';
    $sl = $statusLabel[$viewApp['status']] ?? $viewApp['status'];
    $trackId = $viewApp['tracking_id'] ?? 'ACC-' . str_pad($viewApp['id'], 6, '0', STR_PAD_LEFT);
    $accType = $accTypes[$viewApp['account_type']] ?? $viewApp['account_type'];
?>
<div class="card shadow-sm mb-4">
    <div class="card-header gradient-card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-user-plus me-2"></i>खाता आवेदन विवरण
            <code style="font-size:0.83rem;background:rgba(255,255,255,0.15);padding:2px 10px;border-radius:6px;margin-left:8px;">
                <?php echo htmlspecialchars($trackId); ?>
            </code>
        </h5>
        <span class="badge bg-<?php echo $sc; ?> fs-6"><?php echo $sl; ?></span>
    </div>

    <div class="card-body">
        <div class="row g-4">

            <!-- ── LEFT: आवेदकको विवरण ── -->
            <div class="col-lg-7">

                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-user"></i>व्यक्तिगत जानकारी</div>
                    <table class="table adm-detail-table">
                        <tr><th>पूरा नाम (नेपाली)</th>
                            <td><strong><?php echo htmlspecialchars($viewApp['full_name'] ?? '—'); ?></strong></td></tr>
                        <tr><th>Full Name (EN)</th>
                            <td><?php echo htmlspecialchars($viewApp['full_name_en'] ?: '—'); ?></td></tr>
                        <tr><th>जन्म मिति</th>
                            <td><?php echo htmlspecialchars($viewApp['dob_bs'] ?: '—'); ?></td></tr>
                        <tr><th>लिङ्ग</th>
                            <td><?php echo htmlspecialchars($viewApp['gender'] ?? '—'); ?></td></tr>
                        <tr><th>वैवाहिक अवस्था</th>
                            <td><?php echo htmlspecialchars($viewApp['marital_status'] ?: '—'); ?></td></tr>
                        <tr><th>पेशा</th>
                            <td><?php echo htmlspecialchars($viewApp['occupation'] ?: '—'); ?></td></tr>
                        <tr><th>Tracking ID</th>
                            <td><code class="text-success fw-bold"><?php echo htmlspecialchars($viewApp['tracking_id'] ?? '—'); ?></code></td></tr>
                    </table>
                </div>

                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-phone"></i>सम्पर्क जानकारी</div>
                    <table class="table adm-detail-table">
                        <tr><th>मोबाइल</th>
                            <td><a href="tel:<?php echo htmlspecialchars($viewApp['mobile'] ?? ''); ?>" class="text-decoration-none fw-semibold"><?php echo htmlspecialchars($viewApp['mobile'] ?? '—'); ?></a></td></tr>
                        <tr><th>इमेल</th>
                            <td><?php echo $viewApp['email'] ? '<a href="mailto:'.htmlspecialchars($viewApp['email']).'" class="text-decoration-none">'.htmlspecialchars($viewApp['email']).'</a>' : '—'; ?></td></tr>
                        <tr><th>स्थायी ठेगाना</th>
                            <td><?php echo htmlspecialchars($viewApp['permanent_address'] ?: '—'); ?></td></tr>
                        <tr><th>अस्थायी ठेगाना</th>
                            <td><?php echo htmlspecialchars($viewApp['temporary_address'] ?: '—'); ?></td></tr>
                        <tr><th>शाखा</th>
                            <td><?php echo htmlspecialchars(str_replace('_',' ',ucwords($viewApp['branch'] ?? '—'))); ?></td></tr>
                        <tr><th>खाता प्रकार</th>
                            <td><strong><?php echo htmlspecialchars($accType); ?></strong></td></tr>
                        <tr><th>दर्ता मिति</th>
                            <td><?php echo formatNepaliDate($viewApp['created_at'], true); ?></td></tr>
                    </table>
                </div>

                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-id-card"></i>नागरिकता विवरण</div>
                    <table class="table adm-detail-table">
                        <tr><th>नागरिकता नं.</th>
                            <td><code class="text-dark"><?php echo htmlspecialchars($viewApp['citizenship_no'] ?? '—'); ?></code></td></tr>
                        <tr><th>जारी मिति</th>
                            <td><?php echo htmlspecialchars($viewApp['citizenship_issued_date'] ?: '—'); ?></td></tr>
                        <tr><th>जारी स्थान</th>
                            <td><?php echo htmlspecialchars($viewApp['citizenship_issued_place'] ?: '—'); ?></td></tr>
                        <tr><th>बुबाको नाम</th>
                            <td><?php echo htmlspecialchars($viewApp['father_name'] ?: '—'); ?></td></tr>
                        <tr><th>आमाको नाम</th>
                            <td><?php echo htmlspecialchars($viewApp['mother_name'] ?: '—'); ?></td></tr>
                    </table>
                </div>

                <?php if (!empty($viewApp['nominee_name'])): ?>
                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-user-friends"></i>नामिनी विवरण</div>
                    <table class="table adm-detail-table">
                        <tr><th>नामिनीको नाम</th>
                            <td><strong><?php echo htmlspecialchars($viewApp['nominee_name']); ?></strong></td></tr>
                        <tr><th>सम्बन्ध</th>
                            <td><?php echo htmlspecialchars($viewApp['nominee_relation'] ?: '—'); ?></td></tr>
                        <tr><th>फोन</th>
                            <td><a href="tel:<?php echo htmlspecialchars($viewApp['nominee_phone'] ?? ''); ?>" class="text-decoration-none"><?php echo htmlspecialchars($viewApp['nominee_phone'] ?: '—'); ?></a></td></tr>
                    </table>
                </div>
                <?php endif; ?>

                <!-- कागजातहरू — photos & documents -->
                <?php
                $docs = [
                    'photo'             => 'फोटो',
                    'citizenship_front' => 'नागरिकता अगाडि',
                    'citizenship_back'  => 'नागरिकता पछाडि',
                    'signature'         => 'हस्ताक्षर',
                ];
                $hasDocs = false;
                foreach ($docs as $col => $_) { if (!empty($viewApp[$col])) { $hasDocs = true; break; } }
                if ($hasDocs):
                ?>
                <div class="adm-info-group">
                <div class="adm-info-group-header"><i class="fas fa-images"></i>पेश गरिएका कागजातहरू</div>
                <div class="p-3"><div class="row g-3">
                    <?php foreach ($docs as $col => $label): ?>
                    <?php if (!empty($viewApp[$col])): ?>
                    <div class="col-6 col-md-3 text-center">
                        <a href="<?php echo htmlspecialchars(SITE_URL . $viewApp[$col]); ?>" target="_blank">
                            <img src="<?php echo htmlspecialchars(SITE_URL . $viewApp[$col]); ?>"
                                 class="img-thumbnail mb-1" style="height:100px;object-fit:cover;width:100%;" alt="<?php echo $label; ?>">
                            <div class="small text-muted"><?php echo $label; ?></div>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div></div></div>
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
                        <i class="fas fa-edit me-2"></i>स्थिति अपडेट / कैफियत / Document
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
                                    <i class="fas fa-reply me-1 text-success"></i>Admin कैफियत
                                    <span class="text-muted fw-normal small">— Member ले Tracker मा देख्छ</span>
                                </label>
                                <textarea name="remarks" class="form-control" rows="4"
                                    placeholder="स्वीकृति वा अस्वीकृतिको कारण, आवश्यक कागजात..."
                                ><?php echo htmlspecialchars($viewApp['remarks'] ?? ''); ?></textarea>
                            </div>

                            <!-- Admin ले खाता खोलने पत्र वा rejection notice attach गर्न सक्छ -->
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
                                <a href="account-applications.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>सूचीमा
                                </a>
                            </div>
                        </form>

                        <hr class="my-3">
                        <form method="POST"
                              onsubmit="return confirm('के तपाईं यो खाता आवेदन स्थायी रूपले मेटाउन निश्चित हुनुहुन्छ?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="delete" value="1">
                            <input type="hidden" name="delete_id" value="<?php echo $viewApp['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-trash me-1"></i>यो आवेदन मेटाउनुहोस्
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Summary -->
                <div class="card mt-3 bg-light border-0">
                    <div class="card-body py-3 text-center">
                        <div class="fs-6 fw-bold"><?php echo htmlspecialchars($accType); ?></div>
                        <div class="small text-muted mb-2">खाता प्रकार</div>
                        <?php if ($viewApp['branch']): ?>
                        <div class="small"><i class="fas fa-building me-1 text-muted"></i><?php echo htmlspecialchars($viewApp['branch']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php else: /* ═══════════ LIST VIEW ═══════════ */ ?>

<!-- ── Stat Mini Row ── -->
<div class="stat-mini-row no-print">
    <a href="account-applications.php" class="stat-mini <?php echo $status_filter===''?'active-filter':''; ?>">
        <div class="sm-icon ic-total"><i class="fas fa-file-alt"></i></div>
        <div class="sm-val"><?php echo $totalCount; ?></div>
        <div class="sm-lbl">जम्मा आवेदन</div>
    </a>
    <a href="?status=pending" class="stat-mini <?php echo $status_filter==='pending'?'active-filter':''; ?>">
        <div class="sm-icon ic-pending"><i class="fas fa-clock"></i></div>
        <div class="sm-val"><?php echo $pendingCount; ?></div>
        <div class="sm-lbl">पेन्डिङ</div>
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
</div>

<!-- ── Filter Bar ── -->
<div class="adm-filter-bar no-print">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3 col-6">
            <label>स्थिति</label>
            <select name="status" id="qf_acc_status" class="form-select form-select-sm">
                <option value="">सबै स्थिति</option>
                <option value="pending"  <?php echo $status_filter==='pending'?'selected':''; ?>>⏳ पेन्डिङ</option>
                <option value="approved" <?php echo $status_filter==='approved'?'selected':''; ?>>✅ स्वीकृत</option>
                <option value="rejected" <?php echo $status_filter==='rejected'?'selected':''; ?>>❌ अस्वीकृत</option>
            </select>
        </div>
        <div class="col-md-7 col-12">
            <label>खोज्नुहोस्</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="नाम, मोबाइल, इमेल, नागरिकता नं., Tracking ID...">
                <?php if ($search): ?><a href="?status=<?php echo urlencode($status_filter); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>खोज</button>
        </div>
    </form>
    <script>document.getElementById('qf_acc_status').addEventListener('change',function(){this.closest('form').submit();});</script>
</div>

<!-- ── Account Table ── -->
<div class="card border-0 shadow-sm" style="border-radius:10px;overflow:hidden;">
    <div class="tbl-header-bar no-print">
        <h6><i class="fas fa-user-plus me-2 text-purple" style="color:#7c3aed;"></i>खाता आवेदन सूची</h6>
        <span class="result-count-badge"><?php echo $totalCount; ?> आवेदन</span>
    </div>
    <div class="table-responsive">
        <table class="table-hover table app-table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:200px;">आवेदक</th>
                    <th>खाता प्रकार</th>
                    <th>सम्पर्क</th>
                    <th>नागरिकता</th>
                    <th>Tracking ID</th>
                    <th>दर्ता मिति</th>
                    <th>स्थिति</th>
                    <th class="no-print">कार्यहरू</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($applications)): ?>
            <tr class="no-results-row"><td colspan="8"><i class="fas fa-inbox fa-2x d-block mb-2"></i>कुनै खाता आवेदन फेला परेन।</td></tr>
            <?php else: foreach ($applications as $app):
                $trackId = $app['tracking_id'] ?: 'ACC-' . str_pad($app['id'], 6, '0', STR_PAD_LEFT);
                $initLetter = mb_strtoupper(mb_substr($app['full_name'] ?? 'A', 0, 1));
                $accType = $accTypes[$app['account_type']] ?? $app['account_type'];
            ?>
            <tr data-status="<?php echo htmlspecialchars($app['status']); ?>">
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="av-letter av-acc"><?php echo $initLetter; ?></div>
                        <div>
                            <div class="cell-main"><?php echo htmlspecialchars($app['full_name']); ?></div>
                            <?php if ($app['full_name_en']): ?><div class="cell-sub"><?php echo htmlspecialchars($app['full_name_en']); ?></div><?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="cell-main"><?php echo htmlspecialchars($accType); ?></div>
                    <?php if ($app['branch']): ?><div class="cell-sub"><i class="fas fa-building fa-xs me-1"></i><?php echo htmlspecialchars($app['branch']); ?></div><?php endif; ?>
                </td>
                <td>
                    <div class="cell-main"><i class="fas fa-phone fa-xs text-muted me-1"></i><?php echo htmlspecialchars($app['mobile']); ?></div>
                    <?php if ($app['email']): ?><div class="cell-sub"><?php echo htmlspecialchars($app['email']); ?></div><?php endif; ?>
                </td>
                <td><div class="cell-sub"><?php echo htmlspecialchars($app['citizenship_no'] ?: '—'); ?></div></td>
                <td><span class="track-badge"><?php echo htmlspecialchars($trackId); ?></span></td>
                <td><div class="cell-sub"><?php echo formatNepaliDate($app['created_at']); ?></div></td>
                <td><span class="badge-status badge-<?php echo htmlspecialchars($app['status']); ?>"><?php echo $statusLabel[$app['status']] ?? $app['status']; ?></span></td>
                <td class="no-print">
                    <div class="d-flex gap-1 flex-wrap">
                        <a href="account-applications.php?view=<?php echo $app['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="विवरण"><i class="fas fa-eye"></i></a>
                        <?php if ($app['status'] === 'pending'): ?>
                        <form method="POST" class="qaction-form" onsubmit="return confirm('खाता आवेदन स्वीकृत गर्नुहुन्छ?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="quick_status" value="1">
                            <input type="hidden" name="quick_id" value="<?php echo $app['id']; ?>">
                            <input type="hidden" name="quick_status_val" value="approved">
                            <button type="submit" class="btn-qapprove"><i class="fas fa-check me-1"></i>स्वीकृत</button>
                        </form>
                        <form method="POST" class="qaction-form" onsubmit="return confirm('खाता आवेदन अस्वीकृत गर्नुहुन्छ?')">
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
            <?php $qs2 = ['status'=>$status_filter,'search'=>$search]; ?>
            <a href="?<?php echo http_build_query(array_merge($qs2,['page'=>1])); ?>" class="<?php echo $page==1?'disabled':''; ?>"><i class="fas fa-angle-double-left"></i></a>
            <a href="?<?php echo http_build_query(array_merge($qs2,['page'=>max(1,$page-1)])); ?>" class="<?php echo $page==1?'disabled':''; ?>"><i class="fas fa-angle-left"></i></a>
            <?php $s2=max(1,$page-2);$e2=min($totalPages,$page+2); for($i=$s2;$i<=$e2;$i++): ?>
            <?php echo $i==$page ? "<span class='active'>$i</span>" : "<a href='?".http_build_query(array_merge($qs2,['page'=>$i]))."'>$i</a>"; ?>
            <?php endfor; ?>
            <a href="?<?php echo http_build_query(array_merge($qs2,['page'=>min($totalPages,$page+1)])); ?>" class="<?php echo $page>=$totalPages?'disabled':''; ?>"><i class="fas fa-angle-right"></i></a>
            <a href="?<?php echo http_build_query(array_merge($qs2,['page'=>$totalPages])); ?>" class="<?php echo $page==$totalPages?'disabled':''; ?>"><i class="fas fa-angle-double-right"></i></a>
            <span style="font-size:0.78rem;color:#6b7280;margin-left:8px;"><?php echo $page; ?>/<?php echo $totalPages; ?> · <?php echo $totalCount; ?> रेकर्ड</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once 'includes/admin-footer.php'; ?>
