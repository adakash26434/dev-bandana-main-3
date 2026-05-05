<?php
/**
 * सूचना व्यवस्थापन — Notices Management
 * Tab UI: List tab + Add/Edit form tab (modal popup हटाइएको)
 * सबै मिति नेपाली (बि.सं.) मात्र
 */
$pageTitle = 'सूचना व्यवस्थापन';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';

$rawAction = $_POST['action'] ?? $_GET['action'] ?? 'list';
$action    = in_array($rawAction, ['list', 'delete', 'bulk_status'], true) ? $rawAction : 'list';
$id        = intval($_POST['id'] ?? 0) ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notice'])) {
    $title      = clean_text($_POST['title']      ?? '');
    $content    = $_POST['content']             ?? '';
    $noticeDate = !empty(trim($_POST['notice_date'] ?? '')) ? clean_text($_POST['notice_date']) : null;
    $isActive   = isset($_POST['is_active'])  ? 1 : 0;
    $isPopup    = isset($_POST['is_popup'])   ? 1 : 0;
    $attachment = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $upload = uploadFile($_FILES['attachment'], 'notices');
        if ($upload['success']) $attachment = $upload['path'];
    }

    try {
        $db = getDB();

/* CSRF सुरक्षा: POST अनुरोध प्रमाणित गर्नुहोस् */
checkCSRF();
        if (!empty($_POST['notice_id'])) {
            $noticeId = (int)$_POST['notice_id'];
            if ($attachment) {
                $db->prepare("UPDATE notices SET title=?, content=?, notice_date=?, attachment=?, is_active=?, is_popup=? WHERE id=?")
                   ->execute([$title, $content, $noticeDate, $attachment, $isActive, $isPopup, $noticeId]);
            } else {
                $db->prepare("UPDATE notices SET title=?, content=?, notice_date=?, is_active=?, is_popup=? WHERE id=?")
                   ->execute([$title, $content, $noticeDate, $isActive, $isPopup, $noticeId]);
            }
            setFlash('success', 'सूचना सफलतापूर्वक अपडेट भयो।');
        } else {
            $db->prepare("INSERT INTO notices (title, content, notice_date, attachment, is_active, is_popup) VALUES (?,?,?,?,?,?)")
               ->execute([$title, $content, $noticeDate, $attachment, $isActive, $isPopup]);
            setFlash('success', 'नयाँ सूचना सफलतापूर्वक थपियो।');
        }
        redirect('notices.php');
    } catch (Exception $e) {
        setFlash('error', 'त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।');
        redirect('notices.php');
    }
}

if ($action === 'bulk_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        checkCSRF();
        $bulk = clean_text($_POST['bulk'] ?? '');
        $selected = $_POST['selected_ids'] ?? [];
        $ids = array_values(array_filter(array_map('intval', (array)$selected), fn($v) => $v > 0));
        if (empty($ids) || !in_array($bulk, ['active','inactive'], true)) {
            setFlash('error', 'Bulk update का लागि notice छान्नुहोस्।');
            redirect('notices.php');
        }
        $target = $bulk === 'active' ? 1 : 0;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $db->prepare("UPDATE notices SET is_active = ? WHERE id IN ($ph)");
        $st->execute(array_merge([$target], $ids));
        setFlash('success', 'Bulk status update सफल भयो।');
    } catch (Exception $e) {
        setFlash('error', 'Bulk status update असफल भयो।');
    }
    redirect('notices.php');
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST' && $id) {
    try {
        $db = getDB();
        checkCSRF();
        $db->prepare("DELETE FROM notices WHERE id=?")->execute([$id]);
        setFlash('success', 'सूचना मेटाइयो।');
    } catch (Exception $e) {
        setFlash('error', 'मेटाउन सकिएन।');
    }
    redirect('notices.php');
}

$notices = [];
try {
    $db      = getDB();
    $notices = $db->query("SELECT * FROM notices ORDER BY id DESC")->fetchAll();
} catch (Exception $e) { $notices = []; }

$flash = getFlash();
?>

<?php echo adminPageHeader('सूचना व्यवस्थापन', 'fa-bullhorn', 'संस्थाका सूचनाहरू — थप्नुहोस्, सम्पादन गर्नुहोस्।',
    '<span class="badge admin-stat-badge bg-success-subtle text-success border border-success border-opacity-25 me-2"><i class="fas fa-layer-group me-1"></i>जम्मा: ' . count($notices) . '</span>'
);
?>
<?php echo adminHelpTip('यो पृष्ठबाट संस्थाका सूचनाहरू थप्न, सम्पादन गर्न र हटाउन सकिन्छ।', ['नयाँ सूचना थप्न: माथिको "+" बटन थिच्नुहोस्।', 'सूचना publish/unpublish गर्न: Active/Inactive बटन थिच्नुहोस्।', 'सूचना हटाउन: रातो Delete बटन थिच्नुहोस् (यो कार्य पूर्ववत हुन सक्दैन)।']); ?>

<?php if (!empty($flash)) { echo adminAlert($flash['type'] === 'success' ? 'success' : 'danger', $flash['message']); } ?>

<!-- Tab Navigation -->
<ul class="nav nav-tabs admin-nav-tabs mb-0" id="noticeTabs">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-list" id="tab-list-btn">
            <i class="fas fa-list me-2"></i>सूचना सूची
            <span class="badge bg-success ms-1"><?php echo count($notices); ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-form" id="tab-form-btn">
            <i class="fas fa-plus-circle me-2"></i><span id="noticeFormTabLabel">नयाँ थप्नुहोस्</span>
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ══ TAB 1: सूची ══ -->
    <div class="tab-pane fade show active" id="tab-list">
        <div class="card admin-table-card svc-flat-top-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="bulk_status">
                        <div class="px-3 py-2 border-bottom bg-light d-flex gap-2 justify-content-end">
                            <button type="submit" name="bulk" value="active" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-check-circle me-1"></i>Bulk Active
                            </button>
                            <button type="submit" name="bulk" value="inactive" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-ban me-1"></i>Bulk Inactive
                            </button>
                        </div>
                    <table class="table table-hover align-middle mb-0" id="noticesTable">
                        <thead>
                            <tr>
                                <th width="40" class="text-center"><input type="checkbox" onclick="document.querySelectorAll('.nt-select').forEach(c=>c.checked=this.checked)"></th>
                                <th class="ps-3" width="50">#</th>
                                <th>शीर्षक</th>
                                <th width="140">मिति (बि.सं.)</th>
                                <th width="90" class="text-center">पप-अप</th>
                                <th width="100" class="text-center">स्थिति</th>
                                <th width="130" class="text-center">कार्य</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notices)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="admin-empty-state">
                                        <i class="fas fa-bullhorn"></i>
                                        <p>कुनै सूचना छैन। माथिको "नयाँ सूचना" बटन थिच्नुहोस्।</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($notices as $idx => $item): ?>
                            <tr>
                                <td class="text-center"><input type="checkbox" class="nt-select" name="selected_ids[]" value="<?php echo (int)$item['id']; ?>"></td>
                                <td class="ps-3 text-muted"><?php echo $idx + 1; ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($item['title']); ?></div>
                                    <?php if ($item['attachment']): ?>
                                        <small class="text-muted"><i class="fas fa-paperclip me-1 text-success"></i>फाइल संलग्न</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-secondary">
                                        <i class="far fa-calendar-alt me-1 text-success"></i>
                                        <?php echo htmlspecialchars($item['notice_date'] ?? '—'); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($item['is_popup']): ?>
                                        <span class="badge bg-info"><i class="fas fa-bell me-1"></i>पप-अप</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted">होइन</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($item['is_active']): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>सक्रिय</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>निष्क्रिय</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button"
                                        class="btn btn-sm btn-primary me-1 btn-edit-notice"
                                        title="सम्पादन"
                                        data-id="<?php echo $item['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($item['title'], ENT_QUOTES); ?>"
                                        data-date="<?php echo htmlspecialchars($item['notice_date'] ?? '', ENT_QUOTES); ?>"
                                        data-active="<?php echo $item['is_active']; ?>"
                                        data-popup="<?php echo $item['is_popup']; ?>"
                                        data-attachment="<?php echo htmlspecialchars($item['attachment'] ?? '', ENT_QUOTES); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="svc-inline-form" onsubmit="return confirm('यो सूचना मेटाउने हो?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                        <?php echo csrfField(); ?>
                                        <button type="submit" class="btn btn-sm btn-danger" title="मेटाउनुहोस्">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ TAB 2: Add / Edit Form ══ -->
    <div class="tab-pane fade" id="tab-form">
        <div class="card svc-flat-top-card">
            <div class="card-header d-flex justify-content-between align-items-center svc-form-header-grad">
                <h5 class="mb-0 fw-bold" id="noticeFormTitle">
                    <i class="fas fa-plus-circle me-2"></i>नयाँ सूचना थप्नुहोस्
                </h5>
                <button type="button" class="btn btn-light btn-sm" id="btnCancelNotice">
                    <i class="fas fa-arrow-left me-1"></i>सूचीमा फर्कनुहोस्
                </button>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="notices.php" enctype="multipart/form-data" id="noticeForm" class="needs-validation" novalidate>
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="save_notice" value="1">
                    <input type="hidden" name="notice_id" id="ntf_id" value="">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-success">
                                    <i class="fas fa-heading me-1"></i>शीर्षक <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="title" id="ntf_title" class="form-control admin-fancy-input" required placeholder="सूचनाको शीर्षक">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-success">
                                    <i class="fas fa-align-left me-1"></i>विवरण
                                </label>
                                <textarea name="content" id="ntf_content" class="form-control admin-fancy-input" rows="6" placeholder="सूचनाको विवरण..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-success">
                                    <i class="fas fa-calendar-alt me-1"></i>मिति (बि.सं.)
                                </label>
                                <div class="input-group">
                                    <input type="text" name="notice_date" id="ntf_date"
                                           class="form-control admin-fancy-input nepali-datepicker"
                                           placeholder="YYYY-MM-DD" autocomplete="off">
                                    <span class="input-group-text bg-success text-white ndp-trigger ntf-cursor-pointer">
                                        <i class="fas fa-calendar-alt"></i>
                                    </span>
                                </div>
                                <small class="text-muted">बि.सं. मिति (नेपाली क्यालेन्डर)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-success">
                                    <i class="fas fa-paperclip me-1"></i>फाइल (वैकल्पिक)
                                    <small class="text-muted fw-normal" id="ntf_att_note"></small>
                                </label>
                                <input type="file" name="attachment" class="form-control admin-fancy-input" accept=".pdf,.jpg,.jpeg,.png">
                                <div id="ntf_att_link" class="mt-1 d-none">
                                    <small class="text-muted">हालको फाइल:
                                        <a id="ntf_att_href" href="#" target="_blank" class="text-success fw-semibold">
                                            <i class="fas fa-external-link-alt me-1"></i>हेर्नुहोस्
                                        </a>
                                    </small>
                                </div>
                            </div>
                            <div class="mb-2 d-flex align-items-center gap-2">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="ntf_active">
                                </div>
                                <label class="form-label mb-0 fw-semibold" for="ntf_active">सक्रिय</label>
                            </div>
                            <div class="mb-1 d-flex align-items-center gap-2">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_popup" id="ntf_popup">
                                </div>
                                <label class="form-label mb-0 fw-semibold d-flex align-items-center gap-1" for="ntf_popup">
                                    <i class="fas fa-bell text-warning"></i>
                                    पप-अप देखाउनुहोस्
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex gap-3">
                        <button type="submit" id="ntf_submit" class="btn btn-success px-5 fw-semibold">
                            <i class="fas fa-plus-circle me-2"></i>थप्नुहोस्
                        </button>
                        <button type="button" id="ntf_cancel2" class="btn btn-outline-secondary px-4">
                            <i class="fas fa-times me-1"></i>रद्द
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div><!-- end tab-content -->

<script>
document.addEventListener('DOMContentLoaded', function () {

    var tabListBtn = document.getElementById('tab-list-btn');
    var tabFormBtn = document.getElementById('tab-form-btn');

    function switchToList() { adminSwitchTab(tabListBtn, tabFormBtn); }
    function switchToForm() { adminSwitchTab(tabFormBtn, tabListBtn); }

    function clearForm() {
        document.getElementById('ntf_id').value      = '';
        document.getElementById('ntf_title').value   = '';
        document.getElementById('ntf_content').value = '';
        document.getElementById('ntf_date').value    = '';
        document.getElementById('ntf_active').checked= true;
        document.getElementById('ntf_popup').checked = false;
        document.getElementById('ntf_att_link').classList.add('d-none');
        document.getElementById('ntf_att_note').textContent   = '';
        document.getElementById('ntf_submit').innerHTML = '<i class="fas fa-plus-circle me-2"></i>थप्नुहोस्';
        document.getElementById('noticeFormTitle').innerHTML  = '<i class="fas fa-plus-circle me-2"></i>नयाँ सूचना थप्नुहोस्';
        document.getElementById('noticeFormTabLabel').textContent = 'नयाँ थप्नुहोस्';
    }

    /* Edit mode flag — edit गर्दा tab switch हुँदा form clear नहोस् */
    var _isEditMode = false;
    /* Add New tab direct-click गर्दा मात्र form clear हुन्छ */
    if (tabFormBtn) tabFormBtn.addEventListener('show.bs.tab', function() {
        if (!_isEditMode) clearForm();
        _isEditMode = false;
    });

    /* Cancel बटनहरू */
    ['btnCancelNotice','ntf_cancel2'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', function() { clearForm(); switchToList(); });
    });

    /* Edit बटनहरू */
    document.querySelectorAll('.btn-edit-notice').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var d = this.dataset;
            document.getElementById('ntf_id').value      = d.id;
            document.getElementById('ntf_title').value   = d.title;
            document.getElementById('ntf_date').value    = d.date;
            document.getElementById('ntf_active').checked= d.active === '1';
            document.getElementById('ntf_popup').checked = d.popup  === '1';

            if (d.attachment) {
                document.getElementById('ntf_att_link').classList.remove('d-none');
                document.getElementById('ntf_att_href').href = '../' + d.attachment;
                document.getElementById('ntf_att_note').textContent = ' — नयाँ फाइल नचुने भने पुरानै रहन्छ';
            } else {
                document.getElementById('ntf_att_link').classList.add('d-none');
                document.getElementById('ntf_att_note').textContent   = '';
            }
            document.getElementById('ntf_submit').innerHTML = '<i class="fas fa-save me-2"></i>अपडेट गर्नुहोस्';
            document.getElementById('noticeFormTitle').innerHTML  = '<i class="fas fa-edit me-2"></i>सूचना सम्पादन';
            document.getElementById('noticeFormTabLabel').textContent = 'सम्पादन';
            _isEditMode = true;
            switchToForm();
        });
    });

    /* DataTable */
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        try {
            $('#noticesTable').DataTable({
                language: {
                    search    : 'खोज्नुहोस्:',
                    lengthMenu: '_MENU_ पङ्क्ति',
                    info      : '_START_–_END_ / _TOTAL_ सूचना',
                    paginate  : { previous: '‹', next: '›' },
                    emptyTable: 'कुनै सूचना छैन'
                },
                order     : [[0, 'desc']],
                pageLength: 15,
                columnDefs: [{ orderable: false, targets: [0,6] }]
            });
        } catch(e) {}
    }
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>
