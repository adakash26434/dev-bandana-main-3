<?php
/**
 * Admin: गुनासो व्यवस्थापन — grievances.php
 * ================================================
 * feedbacks.php जस्तै full-page detail/edit view।
 * Modal हटाइयो — ?view=ID बाट detail page खुल्छ।
 */
$pageTitle   = 'गुनासो व्यवस्थापन';
$currentPage = 'grievances';
require_once '../includes/config.php';

if (!isAdminLoggedIn()) {
    redirect(ADMIN_URL . 'index.php');
}

$db = getDB();

/* CSRF सुरक्षा: POST अनुरोध प्रमाणित गर्नुहोस् */
checkCSRF();

/* ── auto-ALTER: ensure ALL grievance reply columns exist
   (Issue #4 — Member portal मा admin reply खाली देखिने bug का कारण
    पुरानो install मा admin_response / admin_note / resolved_at column नभएर
    UPDATE silent fail हुन्थ्यो। हरेक page load मा safe-add गरिन्छ — already-exists भए no-op।) */
safeAddColumn($db, 'grievances', 'admin_response',   "TEXT NULL DEFAULT NULL COMMENT 'Admin reply text shown to member'");
safeAddColumn($db, 'grievances', 'admin_note',       "TEXT NULL DEFAULT NULL COMMENT 'Internal admin note (member-invisible)'");
safeAddColumn($db, 'grievances', 'resolved_at',      "TIMESTAMP NULL DEFAULT NULL");
safeAddColumn($db, 'grievances', 'admin_attachment', "VARCHAR(500) DEFAULT '' COMMENT 'Admin reply संलग्न file'");
safeAddColumn($db, 'grievances', 'updated_at',       "TIMESTAMP NULL DEFAULT NULL");

require_once 'includes/admin-header.php';
require_once __DIR__ . '/../includes/auth-roles.php';
/* RBAC: staff hercha matra; mutate admin+ matra */
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_role('admin');
require_once 'includes/admin-ui.php';

/* ─── POST handlers ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $viewId = (int)($_POST['view_id'] ?? 0); /* form पछि detail page मा redirect */

    /* ── Status + Response Update ── */
    if (isset($_POST['update_grievance'])) {
        $id          = (int)$_POST['id'];
        $status      = clean_text($_POST['status']);
        $adminResp   = clean_text($_POST['admin_response'] ?? '');
        $adminNote   = trim($_POST['admin_note'] ?? '');
        $newFile     = adminUploadFile('admin_attachment');
        $resolvedAt  = ($status === 'resolved' || $status === 'closed') ? date('Y-m-d H:i:s') : null;

        try {
            if ($newFile) {
                $stmt = $db->prepare("UPDATE grievances SET status=?, admin_response=?, admin_note=?, resolved_at=?, admin_attachment=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$status, $adminResp, $adminNote, $resolvedAt, $newFile, $id]);
            } else {
                $stmt = $db->prepare("UPDATE grievances SET status=?, admin_response=?, admin_note=?, resolved_at=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$status, $adminResp, $adminNote, $resolvedAt, $id]);
            }

            /* Member लाई notification — fail भए पनि main काम रोकिँदैन */
            try {
                $nRow = $db->prepare("SELECT name, email, phone FROM grievances WHERE id=?");
                $nRow->execute([$id]);
                $nData = $nRow->fetch();
                if ($nData) {
                    sendMemberStatusUpdate('grievance',
                        $nData['email'] ?? '', $nData['phone'] ?? '', $nData['name'] ?? '',
                        $status, $adminResp, 'GRV-' . $id);
                }
            } catch (Exception $e) {}

            setFlash('success', 'गुनासो अपडेट भयो।');
        } catch (Exception $e) {
            setFlash('error', 'त्रुटि भयो: ' . $e->getMessage());
        }
        redirect('grievances.php' . ($id ? '?view=' . $id : ''));
    }

    /* ── Remove attachment ── */
    if (isset($_POST['remove_attachment'])) {
        $id = (int)$_POST['id'];
        try {
            $row = $db->prepare("SELECT admin_attachment FROM grievances WHERE id=?");
            $row->execute([$id]);
            $r = $row->fetch();
            if ($r && !empty($r['admin_attachment'])) {
                $fp = ROOT_PATH . $r['admin_attachment'];
                if (file_exists($fp)) @unlink($fp);
            }
            $db->prepare("UPDATE grievances SET admin_attachment='' WHERE id=?")->execute([$id]);
            setFlash('success', 'फाइल हटाइयो।');
        } catch (Exception $e) {}
        redirect('grievances.php?view=' . $id);
    }

    /* ── Delete ── */
    if (isset($_POST['delete'])) {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM grievances WHERE id=?")->execute([$id]);
        setFlash('success', 'गुनासो मेटाइयो।');
        redirect('grievances.php');
    }

    /* ── Quick Resolve (list view बाट) ── */
    if (isset($_POST['quick_resolve'])) {
        $qid = (int)($_POST['quick_id'] ?? 0);
        $allowed = ['pending','in_progress','resolved','closed'];
        $qst = in_array($_POST['quick_resolve_status'] ?? '', $allowed) ? $_POST['quick_resolve_status'] : 'resolved';
        try {
            $db->prepare("UPDATE grievances SET status=? WHERE id=?")->execute([$qst, $qid]);
            setFlash('success', 'गुनासो स्थिति परिवर्तन गरियो।');
        } catch (Exception $e) { setFlash('error', 'त्रुटि भयो।'); }
        $gStatuses = ['pending', 'in_progress', 'resolved', 'closed'];
        $redSt = $_GET['status'] ?? '';
        if ($redSt !== '' && !in_array($redSt, $gStatuses, true)) {
            $redSt = '';
        }
        $qs = http_build_query([
            'status' => $redSt,
            'search' => mb_substr(trim((string)($_GET['search'] ?? '')), 0, 200, 'UTF-8'),
        ]);
        redirect('grievances.php?' . $qs);
    }
}

/* ── Status / filter / search query ── */
$grievanceListStatuses = ['pending', 'in_progress', 'resolved', 'closed'];
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter !== '' && !in_array($statusFilter, $grievanceListStatuses, true)) {
    $statusFilter = '';
}
$search       = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 200, 'UTF-8');
$where        = '1=1';
$params       = [];
if ($statusFilter) { $where .= ' AND status = ?'; $params[] = $statusFilter; }
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR tracking_id LIKE ? OR subject LIKE ?)';
    $t = "%$search%";
    $params = array_merge($params, [$t,$t,$t,$t,$t]);
}
try {
    $stmt = $db->prepare("SELECT * FROM grievances WHERE $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $grievances = $stmt->fetchAll();
} catch (Exception $e) { $grievances = []; }

/* ── Counts ── */
$counts = ['pending'=>0,'in_progress'=>0,'resolved'=>0,'closed'=>0];
try {
    $cntStmt = $db->query("SELECT status, COUNT(*) c FROM grievances GROUP BY status");
    while ($r = $cntStmt->fetch()) if (isset($counts[$r['status']])) $counts[$r['status']] = (int)$r['c'];
} catch (Exception $e) {}
$total = array_sum($counts);

/* ── Single view ── */
$viewGrv = null;
if (isset($_GET['view'])) {
    $s = $db->prepare("SELECT * FROM grievances WHERE id=?");
    $s->execute([(int)$_GET['view']]);
    $viewGrv = $s->fetch();
}

/* ── Helpers ── */
$statusLabel = ['pending' => 'पेन्डिङ', 'in_progress' => 'प्रक्रियामा', 'resolved' => 'समाधान', 'closed' => 'बन्द'];
$statusClass = ['pending' => 'warning', 'in_progress' => 'info', 'resolved' => 'success', 'closed' => 'secondary'];
$catLabels   = ['service' => 'सेवा', 'staff' => 'कर्मचारी', 'loan' => 'ऋण', 'account' => 'खाता', 'branch' => 'शाखा', 'other' => 'अन्य'];

function grvBadge($status, $label, $class) {
    return '<span class="badge bg-' . $class . '">' . htmlspecialchars($label) . '</span>';
}
function grvAttachUrl($path) { return empty($path) ? '' : SITE_URL . ltrim($path, '/'); }
function grvAttachName($path) { return basename($path ?: ''); }
?>

<?php /* ════════════════════════════════════════════
         PAGE HEADER — single view বা list view
         ════════════════════════════════════════════ */
if ($viewGrv) {
    $trackId = $viewGrv['tracking_id'] ?? 'GRV-' . str_pad($viewGrv['id'], 6, '0', STR_PAD_LEFT);
    echo adminPageHeader(
        'गुनासो विवरण',
        'fa-exclamation-circle',
        'Tracking: ' . $trackId,
        adminBackBtn('grievances.php', 'गुनासो सूचीमा फर्किनुहोस्')
    );
} else {
    echo adminPageHeader(
        'गुनासो व्यवस्थापन',
        'fa-exclamation-circle',
        'सदस्यहरूद्वारा पेश गरिएका गुनासो — स्थिति अपडेट, Admin प्रतिक्रिया र Document।',
        adminStatLink('?status=pending',  'danger',  'पेन्डिङ', $counts['pending'])
        . ' '
        . adminStatLink('grievances.php', 'secondary', 'जम्मा', $total)
    );
} ?>

<?php $flash = getFlash(); if ($flash): ?>
<?php echo adminAlert($flash['type'] === 'success' ? 'success' : 'danger', $flash['message']); ?>
<?php endif; ?>

<?php /* ═══════════════════════════════════════════════
         SINGLE DETAIL VIEW
         ═══════════════════════════════════════════════ */
if ($viewGrv):
    $sc      = $statusClass[$viewGrv['status']] ?? 'secondary';
    $sl      = $statusLabel[$viewGrv['status']] ?? $viewGrv['status'];
    $trackId = $viewGrv['tracking_id'] ?? 'GRV-' . str_pad($viewGrv['id'], 6, '0', STR_PAD_LEFT);
    $catTxt  = $catLabels[$viewGrv['category'] ?? ''] ?? ($viewGrv['category'] ?? '—');
?>
<div class="card shadow-sm mb-4">
    <div class="card-header gradient-card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-exclamation-circle me-2"></i>गुनासो विवरण
            <code style="font-size:0.83rem;background:rgba(255,255,255,0.15);padding:2px 10px;border-radius:6px;margin-left:8px;letter-spacing:1px;">
                <?php echo htmlspecialchars($trackId); ?>
            </code>
        </h5>
        <?php echo grvBadge($viewGrv['status'], $sl, $sc); ?>
    </div>

    <div class="card-body">
        <div class="row g-4">

            <!-- ── LEFT: Member Info + Message + Previous Response ── -->
            <div class="col-lg-5">
                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-user"></i>गुनासोकर्ताको जानकारी</div>
                    <table class="table adm-detail-table">
                        <?php if (!$viewGrv['is_anonymous']): ?>
                        <tr><th>नाम</th>
                            <td><strong><?php echo htmlspecialchars($viewGrv['name'] ?? '—'); ?></strong></td></tr>
                        <tr><th>फोन</th>
                            <td><a href="tel:<?php echo htmlspecialchars($viewGrv['phone'] ?? ''); ?>" class="text-decoration-none fw-semibold"><?php echo htmlspecialchars($viewGrv['phone'] ?? '—'); ?></a></td></tr>
                        <tr><th>इमेल</th>
                            <td><?php echo $viewGrv['email'] ? '<a href="mailto:'.htmlspecialchars($viewGrv['email']).'" class="text-decoration-none">'.htmlspecialchars($viewGrv['email']).'</a>' : '—'; ?></td></tr>
                        <tr><th>सदस्य नं.</th>
                            <td><?php echo $viewGrv['member_id'] ? '<span class="badge bg-success-subtle text-success-emphasis fw-semibold px-2">'.htmlspecialchars($viewGrv['member_id']).'</span>' : '<span class="text-muted">—</span>'; ?></td></tr>
                        <?php else: ?>
                        <tr><td colspan="2" class="text-center text-muted fst-italic py-2">
                            <i class="fas fa-user-secret me-1"></i>गुप्त पहिचान (Anonymous)
                        </td></tr>
                        <?php endif; ?>
                        <tr><th>वर्ग</th>
                            <td><?php echo htmlspecialchars($catTxt); ?></td></tr>
                        <tr><th>Tracking ID</th>
                            <td><code class="text-success fw-bold"><?php echo htmlspecialchars($viewGrv['tracking_id'] ?? '—'); ?></code></td></tr>
                        <tr><th>दर्ता मिति</th>
                            <td><?php echo isset($viewGrv['created_at']) ? formatNepaliDate($viewGrv['created_at'], true) : '—'; ?></td></tr>
                        <tr><th>अवस्था</th>
                            <td><?php echo grvBadge($viewGrv['status'], $sl, $sc); ?></td></tr>
                        <?php if ($viewGrv['resolved_at']): ?>
                        <tr><th>समाधान मिति</th>
                            <td><?php echo formatNepaliDate($viewGrv['resolved_at'], true); ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>

                <?php if (!empty($viewGrv['subject'])): ?>
                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-tag"></i>विषय</div>
                    <div class="p-3 fw-semibold" style="font-size:0.9rem;color:#374151;">
                        <?php echo htmlspecialchars($viewGrv['subject']); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-comment-dots"></i>गुनासोको विस्तृत विवरण</div>
                    <div class="p-3" style="min-height:80px;white-space:pre-wrap;font-size:0.9rem;line-height:1.7;color:#374151;">
                        <?php echo nl2br(htmlspecialchars($viewGrv['description'] ?? '—')); ?>
                    </div>
                </div>

                <?php if (!empty($viewGrv['admin_response'])): ?>
                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-reply"></i>Admin प्रतिक्रिया <small class="fw-normal text-muted ms-1">(Member ले tracker मा देख्छ)</small></div>
                    <div class="p-3" style="white-space:pre-wrap;font-size:0.9rem;color:#374151;background:#f0fff4;">
                        <?php echo nl2br(htmlspecialchars($viewGrv['admin_response'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($viewGrv['admin_note'])): ?>
                <div class="adm-info-group">
                    <div class="adm-info-group-header" style="background:#fff9e6;border-color:#ffc107;"><i class="fas fa-sticky-note" style="color:#b45309;"></i>Admin आन्तरिक टिप्पणी <small class="fw-normal text-muted ms-1">(केवल admin)</small></div>
                    <div class="p-3" style="white-space:pre-wrap;font-size:0.9rem;color:#5c4400;background:#fffdf0;">
                        <?php echo nl2br(htmlspecialchars($viewGrv['admin_note'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($viewGrv['admin_attachment'])): ?>
                <div class="adm-info-group">
                    <div class="adm-info-group-header"><i class="fas fa-paperclip"></i>Admin संलग्न Document</div>
                    <div class="p-3 d-flex align-items-center gap-3">
                        <i class="fas fa-file-alt fa-2x text-primary opacity-75"></i>
                        <div class="flex-grow-1 small fw-semibold">
                            <?php echo htmlspecialchars(grvAttachName($viewGrv['admin_attachment'])); ?>
                        </div>
                        <a href="<?php echo htmlspecialchars(grvAttachUrl($viewGrv['admin_attachment'])); ?>"
                           class="btn btn-sm btn-outline-primary" target="_blank" download>
                            <i class="fas fa-download me-1"></i>Download
                        </a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('फाइल हटाउने?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="remove_attachment" value="1">
                            <input type="hidden" name="id" value="<?php echo $viewGrv['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── RIGHT: Edit Form ── -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header gradient-card-header py-2">
                        <i class="fas fa-edit me-2"></i>स्थिति अपडेट / प्रतिक्रिया / Note / Document
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="update_grievance" value="1">
                            <input type="hidden" name="id" value="<?php echo $viewGrv['id']; ?>">

                            <!-- Status -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-circle-dot me-1"></i>अवस्था (Status)
                                </label>
                                <select name="status" class="form-select">
                                    <?php foreach ($statusLabel as $v => $l): ?>
                                    <option value="<?php echo $v; ?>" <?php echo $viewGrv['status']===$v?'selected':''; ?>>
                                        <?php echo $l; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Admin Response — member ले tracker मा देख्छ -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-reply me-1 text-success"></i>Admin प्रतिक्रिया
                                    <span class="text-muted fw-normal small">— Member ले Application Tracker मा देख्छ</span>
                                </label>
                                <textarea name="admin_response" class="form-control" rows="3"
                                    placeholder="सदस्यलाई प्रतिक्रिया लेख्नुहोस्..."
                                ><?php echo htmlspecialchars($viewGrv['admin_response'] ?? ''); ?></textarea>
                            </div>

                            <!-- Admin Internal Note — member देख्दैन -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-sticky-note me-1" style="color:#d4900a;"></i>
                                    Admin आन्तरिक टिप्पणी (Note)
                                    <span class="text-muted fw-normal small">— Member ले देख्दैन</span>
                                </label>
                                <textarea name="admin_note" class="form-control" rows="3"
                                    placeholder="Admin को internal note — member देख्दैन..."
                                    style="border-color:#ffc107;background:#fffdf0;"
                                ><?php echo htmlspecialchars($viewGrv['admin_note'] ?? ''); ?></textarea>
                            </div>

                            <!-- Document Upload -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-paperclip me-1 text-primary"></i>Document संलग्न गर्नुहोस्
                                    <span class="text-muted fw-normal small">— PDF, Word, Image (max 5MB)</span>
                                </label>
                                <input type="file" name="admin_attachment" class="form-control"
                                       accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                                <?php if (!empty($viewGrv['admin_attachment'])): ?>
                                <div class="form-text text-primary">
                                    <i class="fas fa-info-circle me-1"></i>
                                    हाल संलग्न: <strong><?php echo htmlspecialchars(grvAttachName($viewGrv['admin_attachment'])); ?></strong>
                                    — नयाँ file upload गर्नुभयो भने पुरानो replace हुन्छ।
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Submit -->
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="fas fa-save me-1"></i>अपडेट गर्नुहोस्
                                </button>
                                <a href="grievances.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>सूचीमा फर्किनुहोस्
                                </a>
                            </div>
                        </form>

                        <!-- Delete button — separate form -->
                        <hr class="my-3">
                        <form method="POST" onsubmit="return confirm('के तपाईं यो गुनासो स्थायी रूपले मेटाउन निश्चित हुनुहुन्छ?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="delete" value="1">
                            <input type="hidden" name="id" value="<?php echo $viewGrv['id']; ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-trash me-1"></i>यो गुनासो मेटाउनुहोस्
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php else: /* ═══════════════════════════════ LIST VIEW ═══════════════════════════════ */ ?>

<!-- ── Stat Mini Row ── -->
<div class="stat-mini-row no-print">
    <a href="grievances.php" class="stat-mini <?php echo $statusFilter===''?'active-filter':''; ?>">
        <div class="sm-icon ic-total"><i class="fas fa-list"></i></div>
        <div class="sm-val"><?php echo $total; ?></div>
        <div class="sm-lbl">जम्मा गुनासो</div>
    </a>
    <a href="?status=pending" class="stat-mini <?php echo $statusFilter==='pending'?'active-filter':''; ?>">
        <div class="sm-icon ic-pending"><i class="fas fa-clock"></i></div>
        <div class="sm-val"><?php echo $counts['pending']; ?></div>
        <div class="sm-lbl">पेन्डिङ</div>
    </a>
    <a href="?status=in_progress" class="stat-mini <?php echo $statusFilter==='in_progress'?'active-filter':''; ?>">
        <div class="sm-icon ic-process"><i class="fas fa-spinner"></i></div>
        <div class="sm-val"><?php echo $counts['in_progress']; ?></div>
        <div class="sm-lbl">प्रक्रियामा</div>
    </a>
    <a href="?status=resolved" class="stat-mini <?php echo $statusFilter==='resolved'?'active-filter':''; ?>">
        <div class="sm-icon ic-resolved"><i class="fas fa-check-circle"></i></div>
        <div class="sm-val"><?php echo $counts['resolved']; ?></div>
        <div class="sm-lbl">समाधान</div>
    </a>
    <a href="?status=closed" class="stat-mini <?php echo $statusFilter==='closed'?'active-filter':''; ?>">
        <div class="sm-icon ic-anon"><i class="fas fa-lock"></i></div>
        <div class="sm-val"><?php echo $counts['closed']; ?></div>
        <div class="sm-lbl">बन्द</div>
    </a>
</div>

<!-- ── Filter Bar ── -->
<div class="adm-filter-bar no-print">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3 col-6">
            <label>स्थिति</label>
            <select name="status" id="qf_grv_status" class="form-select form-select-sm">
                <option value="">सबै स्थिति</option>
                <option value="pending"     <?php echo $statusFilter==='pending'?'selected':''; ?>>⏳ पेन्डिङ</option>
                <option value="in_progress" <?php echo $statusFilter==='in_progress'?'selected':''; ?>>🔄 प्रक्रियामा</option>
                <option value="resolved"    <?php echo $statusFilter==='resolved'?'selected':''; ?>>✅ समाधान</option>
                <option value="closed"      <?php echo $statusFilter==='closed'?'selected':''; ?>>🔒 बन्द</option>
            </select>
        </div>
        <div class="col-md-7 col-12">
            <label>खोज्नुहोस्</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="नाम, फोन, इमेल, Tracking ID, विषय...">
                <?php if ($search): ?><a href="?status=<?php echo urlencode($statusFilter); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a><?php endif; ?>
            </div>
        </div>
        <div class="col-md-2 col-6">
            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>खोज</button>
        </div>
    </form>
    <script>document.getElementById('qf_grv_status').addEventListener('change',function(){this.closest('form').submit();});</script>
</div>

<!-- ── Grievances Table ── -->
<div class="card border-0 shadow-sm" style="border-radius:10px;overflow:hidden;">
    <div class="tbl-header-bar no-print">
        <h6><i class="fas fa-exclamation-circle me-2" style="color:#b45309;"></i>गुनासो सूची</h6>
        <span class="result-count-badge"><?php echo count($grievances); ?> गुनासो</span>
    </div>
    <div class="table-responsive">
        <table class="table-hover table app-table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:200px;">व्यक्ति</th>
                    <th>विषय</th>
                    <th>वर्ग</th>
                    <th>Tracking ID</th>
                    <th>मिति</th>
                    <th>स्थिति</th>
                    <th class="no-print">कार्यहरू</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($grievances)): ?>
            <tr class="no-results-row"><td colspan="7"><i class="fas fa-inbox fa-2x d-block mb-2"></i>कुनै गुनासो फेला परेन।</td></tr>
            <?php else: foreach ($grievances as $grv):
                $tId = $grv['tracking_id'] ?? 'GRV-' . str_pad($grv['id'], 6, '0', STR_PAD_LEFT);
                $initLetter = $grv['is_anonymous'] ? '?' : mb_strtoupper(mb_substr($grv['name'] ?? 'G', 0, 1));
            ?>
            <tr data-status="<?php echo htmlspecialchars($grv['status']); ?>">
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="av-letter <?php echo $grv['is_anonymous'] ? 'av-anon' : 'av-grv'; ?>"><?php echo $initLetter; ?></div>
                        <div>
                            <?php if (!$grv['is_anonymous']): ?>
                            <div class="cell-main"><?php echo htmlspecialchars($grv['name'] ?? ''); ?></div>
                            <div class="cell-sub"><i class="fas fa-phone fa-xs me-1"></i><?php echo htmlspecialchars($grv['phone'] ?? ''); ?><?php if ($grv['member_id']): ?> · <?php echo htmlspecialchars($grv['member_id']); ?><?php endif; ?></div>
                            <?php else: ?>
                            <div class="cell-main fst-italic text-muted">गुप्त पहिचान</div>
                            <div class="cell-sub"><span class="badge bg-secondary" style="font-size:0.65rem;">Anonymous</span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="cell-main" title="<?php echo htmlspecialchars($grv['description'] ?? ''); ?>">
                        <?php echo htmlspecialchars(mb_substr($grv['subject'] ?? '', 0, 50)); ?><?php if (mb_strlen($grv['subject'] ?? '')>50):?>…<?php endif; ?>
                    </div>
                </td>
                <td><span class="badge bg-info-subtle text-info border border-info border-opacity-25 fw-normal"><?php echo $catLabels[$grv['category']] ?? ($grv['category'] ?? '—'); ?></span></td>
                <td><span class="track-badge"><?php echo htmlspecialchars($tId); ?></span></td>
                <td><div class="cell-sub"><?php echo isset($grv['created_at']) ? formatNepaliDate($grv['created_at'], true) : ''; ?></div></td>
                <td><span class="badge-status badge-<?php echo htmlspecialchars($grv['status']); ?>"><?php echo $statusLabel[$grv['status']] ?? $grv['status']; ?></span></td>
                <td class="no-print">
                    <div class="d-flex gap-1 flex-wrap">
                        <a href="grievances.php?view=<?php echo $grv['id']; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="विस्तृत / अपडेट"><i class="fas fa-eye"></i></a>
                        <?php if ($grv['status'] === 'pending' || $grv['status'] === 'in_progress'): ?>
                        <form method="POST" class="qaction-form" onsubmit="return confirm('यो गुनासो समाधान भएको मान्नुहुन्छ?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="quick_resolve" value="1">
                            <input type="hidden" name="quick_id" value="<?php echo $grv['id']; ?>">
                            <input type="hidden" name="quick_resolve_status" value="resolved">
                            <button type="submit" class="btn-qresolve"><i class="fas fa-check me-1"></i>समाधान</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php require_once 'includes/admin-footer.php'; ?>
