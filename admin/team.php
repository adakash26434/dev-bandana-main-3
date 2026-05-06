<?php
if (!defined('TEAM_ADMIN_SECTION')) {
    define('TEAM_ADMIN_SECTION', 'governance');
}
$teamListSection = TEAM_ADMIN_SECTION;
if (!in_array($teamListSection, ['governance', 'karmachari'], true)) {
    $teamListSection = 'governance';
}
require_once __DIR__ . '/../includes/election-tables.php';
/**
 * टिम सदस्य व्यवस्थापन — Team Members Management
 * Tab UI: सूची + Add/Edit form (modal popup हटाइएको)
 * Special roles: सूचना अधिकारी / गुनासो अधिकारी
 */
/* admin-header भित्र:
   - login/auth check
   - CSRF सुरक्षा
   - ensure-admin-tables
   - global exception handler
   सबै loaded हुन्छ, त्यसैले यो file लाई stable बनाउँछ। */
$pageTitle = $teamListSection === 'karmachari' ? 'कर्मचारी / व्यवस्थापन' : 'सञ्चालक / समिति';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';
if (empty($csrfToken)) $csrfToken = generateCSRFToken();

$success = '';
$error   = '';

/* ── v9.8 Auto-migration: convert team_members.category ENUM→VARCHAR(50) ──
   Without this, saving a "cmt_<id>" committee slug silently fails on strict MySQL
   (ENUM allows only board/management/staff). One-time, idempotent. */
try {
    $_db_chk = getDB();
    $_col = $_db_chk->query("SHOW COLUMNS FROM team_members LIKE 'category'")->fetch(PDO::FETCH_ASSOC);
    if ($_col && stripos($_col['Type'] ?? '', 'enum') !== false) {
        $_db_chk->exec("ALTER TABLE team_members MODIFY COLUMN category VARCHAR(50) NOT NULL DEFAULT 'staff'");
    }
    /* Ensure committee_types table exists (silently) */
    $_db_chk->exec("CREATE TABLE IF NOT EXISTS committee_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        name_np VARCHAR(100) DEFAULT NULL,
        slug VARCHAR(80) DEFAULT NULL,
        description TEXT,
        display_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        show_in_navbar TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $e) { /* best-effort */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    try {
        switch ($_POST['action'] ?? '') {
            case 'add':
            case 'edit':
                $id        = isset($_POST['id']) ? (int)$_POST['id'] : null;
                $name      = clean_text($_POST['name']        ?? '');
                $name_en   = clean_text($_POST['name_en']     ?? '');
                $pos       = clean_text($_POST['position']    ?? '');
                $pos_np    = clean_text($_POST['position_np'] ?? '');
                $pos_en    = clean_text($_POST['position_en'] ?? '');
                $phone     = preg_replace('/[^0-9]/', '', clean_text($_POST['phone']       ?? '', 20));
                $email     = strtolower(clean_text($_POST['email']       ?? '', 254));
                $cat       = clean_text($_POST['category']    ?? 'staff');
                $order     = (int)($_POST['display_order']  ?? 0);
                $isInfo    = isset($_POST['is_information_officer']) ? 1 : 0;
                $isGriev   = isset($_POST['is_grievance_officer'])   ? 1 : 0;
                $isActive  = isset($_POST['is_active']) ? 1 : 0;

                if ($isInfo) {
                    if ($id) {
                        $db->prepare('UPDATE team_members SET is_information_officer = 0 WHERE id != ?')->execute([$id]);
                    } else {
                        $db->exec('UPDATE team_members SET is_information_officer = 0');
                    }
                }
                if ($isGriev) {
                    if ($id) {
                        $db->prepare('UPDATE team_members SET is_grievance_officer = 0 WHERE id != ?')->execute([$id]);
                    } else {
                        $db->exec('UPDATE team_members SET is_grievance_officer = 0');
                    }
                }

                $photo = '';
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
                    $pf = uploadImage($_FILES['photo'], UPLOAD_PATH . 'team/', 400, 400, true);
                    if ($pf) $photo = 'assets/uploads/team/' . $pf;
                }

                if ($_POST['action'] === 'add') {
                    $db->prepare("INSERT INTO team_members (name, name_en, position, position_np, position_en, phone, email, photo, category, display_order, is_information_officer, is_grievance_officer, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
                       ->execute([$name, $name_en, $pos, $pos_np, $pos_en, $phone, $email, $photo, $cat, $order, $isInfo, $isGriev, $isActive]);
                    $success = 'टिम सदस्य सफलतापूर्वक थपियो।';
                } else {
                    if ($photo) {
                        $db->prepare("UPDATE team_members SET name=?, name_en=?, position=?, position_np=?, position_en=?, phone=?, email=?, photo=?, category=?, display_order=?, is_information_officer=?, is_grievance_officer=?, is_active=? WHERE id=?")
                           ->execute([$name, $name_en, $pos, $pos_np, $pos_en, $phone, $email, $photo, $cat, $order, $isInfo, $isGriev, $isActive, $id]);
                    } else {
                        $db->prepare("UPDATE team_members SET name=?, name_en=?, position=?, position_np=?, position_en=?, phone=?, email=?, category=?, display_order=?, is_information_officer=?, is_grievance_officer=?, is_active=? WHERE id=?")
                           ->execute([$name, $name_en, $pos, $pos_np, $pos_en, $phone, $email, $cat, $order, $isInfo, $isGriev, $isActive, $id]);
                    }
                    $success = 'टिम सदस्य सफलतापूर्वक अपडेट भयो।';
                }
                break;

            case 'delete':
                $db->prepare("DELETE FROM team_members WHERE id=?")->execute([(int)$_POST['id']]);
                $success = 'टिम सदस्य हटाइयो।';
                break;

            case 'toggle':
                $db->prepare('UPDATE team_members SET is_active = NOT is_active WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
                $success = 'स्थिति परिवर्तन भयो।';
                break;
        }
    } catch (Exception $e) {
        $error = 'त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।';
    }
}

$db   = getDB();

/* Default 3 categories (backward-compatible) */
$cats = ['board' => 'सञ्चालक समिति', 'management' => 'व्यवस्थापन', 'staff' => 'कर्मचारी'];
$catColors = ['board' => 'var(--primary-color)', 'management' => '#0c7dbf', 'staff' => '#6c757d'];

$extraTypes = [];
try {
    $extraTypes = $db->query("SELECT id, name_np, name FROM committee_types WHERE is_active = 1 ORDER BY display_order, id")->fetchAll();
    foreach ($extraTypes as $ct) {
        $slug = 'cmt_' . (int)$ct['id'];
        if (!isset($cats[$slug])) {
            $cats[$slug] = $ct['name_np'] ?: $ct['name'];
            $catColors[$slug] = '#7e57c2';
        }
    }
} catch (\Throwable $e) { /* committee_types छैन */ }

/* सूची: governance = board + समिति (cmt_*), karmachari = व्यवस्थापन + कर्मचारी */
if ($teamListSection === 'governance') {
    $govCategoryList = ['board'];
    foreach ($extraTypes as $ct) {
        $govCategoryList[] = 'cmt_' . (int)$ct['id'];
    }
    $ph = implode(',', array_fill(0, count($govCategoryList), '?'));
    $stTeam = $db->prepare("SELECT * FROM team_members WHERE category IN ($ph) ORDER BY category, display_order, id DESC");
    $stTeam->execute($govCategoryList);
    $team = $stTeam->fetchAll();
} else {
    $stTeam = $db->prepare("SELECT * FROM team_members WHERE category IN ('management','staff') ORDER BY category, display_order, id DESC");
    $stTeam->execute();
    $team = $stTeam->fetchAll();
}

/* फारम dropdown: यो पृष्ठ अनुसार मात्र वर्ग देखाउने */
$catsForm = [];
if ($teamListSection === 'governance') {
    $catsForm['board'] = $cats['board'];
    foreach ($extraTypes as $ct) {
        $slug = 'cmt_' . (int)$ct['id'];
        if (isset($cats[$slug])) {
            $catsForm[$slug] = $cats[$slug];
        }
    }
} else {
    $catsForm['management'] = $cats['management'];
    $catsForm['staff'] = $cats['staff'];
}

?>

<?php
$teamHeaderTitle = $teamListSection === 'karmachari'
    ? 'कर्मचारी / व्यवस्थापन'
    : 'सञ्चालक र समिति';
$teamHeaderIcon = $teamListSection === 'karmachari' ? 'fa-user-tie' : 'fa-building-columns';
$teamHeaderSub = $teamListSection === 'karmachari'
    ? 'व्यवस्थापन र कर्मचारी मात्र यहाँ सूचीबद्ध। सञ्चालक समिति वा अन्य समिति: मेनु «सञ्चालक / समिति»। RTI/गुनासो अधिकारी स्विच यहीँ वा «तोकाइ» पृष्ठ।'
    : 'सञ्चालक समिति (board) र समिति/उपसमिति (समिति प्रकार) मात्र। कर्मचारी/व्यवस्थापन: मेनु «कर्मचारी / व्यवस्थापन»। RTI/गुनासो अधिकारी यहीँका स्विच वा «तोकाइ» पृष्ठ।';
$teamHeaderActions = '<span class="badge admin-stat-badge bg-success-subtle text-success border border-success border-opacity-25 me-2"><i class="fas fa-layer-group me-1"></i>जम्मा: ' . count($team) . ' सदस्यहरू</span>';
if ($teamListSection === 'karmachari') {
    $teamHeaderActions .= '<a href="team.php" class="btn btn-sm btn-outline-secondary ms-1 mb-1"><i class="fas fa-building-columns me-1"></i>सञ्चालक / समिति</a>';
} else {
    $teamHeaderActions .= '<a href="team-karmachari.php" class="btn btn-sm btn-outline-secondary ms-1 mb-1"><i class="fas fa-user-tie me-1"></i>कर्मचारी / व्यवस्थापन</a>';
}
$teamHeaderActions .= '<a href="info-officer.php" class="btn btn-sm btn-outline-primary ms-1 mb-1"><i class="fas fa-user-shield me-1"></i>RTI तोकाइ</a>'
    . '<a href="grievance-officer.php" class="btn btn-sm btn-outline-secondary ms-1 mb-1"><i class="fas fa-user-tie me-1"></i>गुनासो तोकाइ</a>';
echo adminPageHeader($teamHeaderTitle, $teamHeaderIcon, $teamHeaderSub, $teamHeaderActions);
?>

<?php echo adminAlert('success', $success) . adminAlert('danger', $error); ?>

<ul class="nav nav-tabs admin-nav-tabs mb-0" data-team-section="<?php echo htmlspecialchars($teamListSection, ENT_QUOTES, 'UTF-8'); ?>">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#team-list" id="team-list-btn">
            <i class="fas fa-list me-2"></i>सदस्य सूची
            <span class="badge bg-success ms-1"><?php echo count($team); ?></span>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#team-form" id="team-form-btn">
            <i class="fas fa-plus-circle me-2"></i><span id="teamFormTabLabel">नयाँ थप्नुहोस्</span>
        </button>
    </li>
</ul>

<div class="tab-content">

    <!-- ══ TAB 1: सूची ══ -->
    <div class="tab-pane fade show active" id="team-list">
        <div class="card admin-table-card svc-flat-top-card">

            <!-- खोज बक्स — client-side filter -->
            <div class="admin-search-wrap px-3 py-2 border-bottom bg-light d-flex align-items-center gap-3 svc-search-wrap">
                <div class="input-group input-group-sm svc-search-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 admin-table-search" placeholder="नाम, विवरण अनुसार खोज्नुहोस्..." autocomplete="off">
                </div>
                <small class="text-muted search-count"></small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3" width="70">फोटो</th>
                                <th>नाम</th>
                                <th>पद</th>
                                <th width="110">सम्पर्क</th>
                                <th width="110" class="text-center">वर्ग</th>
                                <th width="120" class="text-center">विशेष भूमिका</th>
                                <th width="90" class="text-center">स्थिति</th>
                                <th width="140" class="text-center">कार्य</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($team)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-users fa-3x mb-2 d-block opacity-25"></i>
                                कुनै सदस्य छैन।
                            </td></tr>
                            <?php endif; ?>
                            <?php foreach ($team as $m): ?>
                            <tr>
                                <td class="ps-3">
                                    <?php if (!empty($m['photo'])): ?>
                                    <img src="<?php echo SITE_URL . htmlspecialchars($m['photo']); ?>" class="tm-avatar-photo">
                                    <?php else: ?>
                                    <div class="tm-avatar-fallback"><i class="fas fa-user text-success"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($m['name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($m['name_en'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($m['position_np'] ?: $m['position']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($m['position_en'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <?php if (!empty($m['phone'])): ?><small><i class="fas fa-phone me-1 text-success"></i><?php echo htmlspecialchars($m['phone']); ?></small><br><?php endif; ?>
                                    <?php if (!empty($m['email'])): ?><small><i class="fas fa-envelope me-1 text-success"></i><?php echo htmlspecialchars($m['email']); ?></small><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge tm-cat-badge"
                                          data-badge-color="<?php echo htmlspecialchars($catColors[$m['category']] ?? '#6c757d', ENT_QUOTES); ?>">
                                        <?php echo $cats[$m['category']] ?? $m['category']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['is_information_officer']): ?><span class="badge bg-info-subtle text-info border mb-1 d-block">सूचना अधिकारी</span><?php endif; ?>
                                    <?php if ($m['is_grievance_officer']): ?><span class="badge bg-danger-subtle text-danger border d-block">गुनासो अधिकारी</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <form method="POST" class="svc-inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                        <button class="badge bg-<?php echo $m['is_active'] ? 'success' : 'secondary'; ?> border-0 tm-status-toggle-btn">
                                            <?php echo $m['is_active'] ? 'सक्रिय' : 'निष्क्रिय'; ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary me-1 btn-edit-member"
                                            data-member='<?php echo htmlspecialchars(json_encode($m, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>'
                                            title="सम्पादन">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="svc-inline-form" onsubmit="return confirm('के तपाईं यो सदस्य मेटाउन निश्चित हुनुहुन्छ?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="मेटाउनुहोस्"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ TAB 2: Add / Edit Form ══ -->
    <div class="tab-pane fade" id="team-form">
        <div class="card svc-flat-top-card">
            <div class="card-header d-flex justify-content-between align-items-center svc-form-header-grad">
                <h5 class="mb-0 fw-bold" id="teamFormTitle">
                    <i class="fas fa-plus-circle me-2"></i>नयाँ सदस्य थप्नुहोस्
                </h5>
                <button type="button" class="btn btn-light btn-sm" id="btnCancelTeam">
                    <i class="fas fa-arrow-left me-1"></i>सूचीमा फर्कनुहोस्
                </button>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data" id="teamForm" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" id="tmf_action" value="add">
                    <input type="hidden" name="id" id="tmf_id" value="">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-success">नाम (नेपाली) <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="tmf_name" class="form-control admin-fancy-input" required placeholder="पूरा नाम नेपालीमा">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-success">Name (English)</label>
                            <input type="text" name="name_en" id="tmf_name_en" class="form-control admin-fancy-input" placeholder="Full name in English">
                        </div>
                        <?php ensureDesignationsTable(getDB()); $__teamDesigs = fetchDesignations(getDB(), ['staff','committee']); ?>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-success">पद (मास्टरबाट)</label>
                            <select name="__pos_pick" id="tmf_pos_pick" class="form-select admin-fancy-input" onchange="(function(sel){var o=sel.options[sel.selectedIndex];document.getElementById('tmf_pos_np').value=o.dataset.np||'';document.getElementById('tmf_pos_en').value=o.dataset.en||'';document.getElementById('tmf_pos').value=o.dataset.np||'';})(this)">
                                <option value="">— पद छान्नुहोस् —</option>
                                <?php foreach ($__teamDesigs as $__d): ?>
                                    <option value="<?php echo (int)$__d['id']; ?>" data-np="<?php echo htmlspecialchars($__d['title_np']); ?>" data-en="<?php echo htmlspecialchars($__d['title_en']); ?>">
                                        <?php echo htmlspecialchars($__d['title_np']); ?> <?php if ($__d['title_en']): ?>— <?php echo htmlspecialchars($__d['title_en']); ?><?php endif; ?> <small>[<?php echo htmlspecialchars($__d['category']); ?>]</small>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="small text-muted mt-1">नयाँ पद <a href="designations.php" target="_blank">पद मास्टर</a> मा थप्नुहोस्।</div>
                        </div>
                        <input type="hidden" name="position_np" id="tmf_pos_np">
                        <input type="hidden" name="position_en" id="tmf_pos_en">
                        <input type="hidden" name="position" id="tmf_pos">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-success">फोन</label>
                            <input type="text" name="phone" id="tmf_phone" class="form-control admin-fancy-input" placeholder="98XXXXXXXX">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-success">इमेल</label>
                            <input type="email" name="email" id="tmf_email" class="form-control admin-fancy-input" placeholder="email@example.com">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold text-success">वर्ग</label>
                            <select name="category" id="tmf_cat" class="form-select admin-fancy-input">
                                <?php
                                $_defCat = $teamListSection === 'karmachari' ? 'management' : 'board';
                                foreach ($catsForm as $_slug => $_lbl): ?>
                                    <option value="<?php echo htmlspecialchars($_slug); ?>" <?php echo $_slug === $_defCat ? 'selected' : ''; ?>><?php echo htmlspecialchars($_lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold text-success">क्रम</label>
                            <input type="number" name="display_order" id="tmf_order" class="form-control admin-fancy-input" value="0" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-success">फोटो
                                <small class="text-muted fw-normal" id="tmf_photo_note"></small>
                            </label>
                            <input type="file" name="photo" class="form-control admin-fancy-input" accept="image/*">
                            <div id="tmf_photo_prev" class="mt-2"></div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch fs-5 mb-2">
                                <input class="form-check-input" type="checkbox" name="is_information_officer" id="tmf_is_info" value="1">
                                <label class="form-check-label fw-semibold" for="tmf_is_info">सूचना अधिकारी (RTI)</label>
                            </div>
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" name="is_grievance_officer" id="tmf_is_griev" value="1">
                                <label class="form-check-label fw-semibold" for="tmf_is_griev">गुनासो अधिकारी</label>
                            </div>
                            <p class="small text-muted mb-0 mt-2 tm-note-xs">
                                <i class="fas fa-link me-1 opacity-75"></i>छुट्टै पृष्ठबाट पनि तोक्न मिल्छ —
                                <a href="info-officer.php" class="link-primary">RTI</a>,
                                <a href="grievance-officer.php" class="link-primary">गुनासो</a>।
                            </p>
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check form-switch fs-5">
                                <input class="form-check-input" type="checkbox" name="is_active" id="tmf_active" value="1" checked>
                                <label class="form-check-label fw-semibold" for="tmf_active">सक्रिय</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex gap-3">
                        <button type="submit" id="tmf_submit" class="btn btn-success px-5 fw-semibold">
                            <i class="fas fa-plus-circle me-2"></i>थप्नुहोस्
                        </button>
                        <button type="button" id="tmf_cancel2" class="btn btn-outline-secondary px-4">
                            <i class="fas fa-times me-1"></i>रद्द
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    var tabsNav = document.querySelector('.admin-nav-tabs[data-team-section]');
    var teamSection = (tabsNav && tabsNav.getAttribute('data-team-section')) ? tabsNav.getAttribute('data-team-section') : 'governance';
    var defaultCategory = teamSection === 'karmachari' ? 'management' : 'board';

    var listBtn = document.getElementById('team-list-btn');
    var formBtn = document.getElementById('team-form-btn');

    function switchToList() { adminSwitchTab(listBtn, formBtn); }
    function switchToForm() { adminSwitchTab(formBtn, listBtn); }

    function clearForm() {
        document.getElementById('tmf_action').value   = 'add';
        document.getElementById('tmf_id').value       = '';
        document.getElementById('tmf_name').value     = '';
        document.getElementById('tmf_name_en').value  = '';
        document.getElementById('tmf_pos').value      = '';
        document.getElementById('tmf_pos_np').value   = '';
        document.getElementById('tmf_pos_en').value   = '';
        document.getElementById('tmf_phone').value    = '';
        document.getElementById('tmf_email').value    = '';
        document.getElementById('tmf_order').value    = '0';
        document.getElementById('tmf_is_info').checked  = false;
        document.getElementById('tmf_is_griev').checked = false;
        document.getElementById('tmf_active').checked   = true;
        var catSel = document.getElementById('tmf_cat');
        if (catSel) {
            var found = false;
            for (var i=0; i<catSel.options.length; i++) {
                if (catSel.options[i].value === defaultCategory) { catSel.selectedIndex = i; found = true; break; }
            }
            if (!found) catSel.selectedIndex = 0;
        }
        document.getElementById('tmf_photo_prev').innerHTML = '';
        document.getElementById('tmf_photo_note').textContent = '';
        document.getElementById('tmf_submit').innerHTML = '<i class="fas fa-plus-circle me-2"></i>थप्नुहोस्';
        document.getElementById('teamFormTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>नयाँ सदस्य थप्नुहोस्';
        document.getElementById('teamFormTabLabel').textContent = 'नयाँ थप्नुहोस्';
    }

    var addBtn = document.getElementById('btnAddTeam');
    if (addBtn) {
        addBtn.addEventListener('click', function() { clearForm(); switchToForm(); });
    }

    ['btnCancelTeam','tmf_cancel2'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('click', function() { clearForm(); switchToList(); });
    });

    document.querySelectorAll('.btn-edit-member').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var m;
            try { m = JSON.parse(this.dataset.member); } catch(e) { return; }

            document.getElementById('tmf_action').value   = 'edit';
            document.getElementById('tmf_id').value       = m.id;
            document.getElementById('tmf_name').value     = m.name || '';
            document.getElementById('tmf_name_en').value  = m.name_en || '';
            document.getElementById('tmf_pos').value      = m.position || '';
            (function(){
                var sel=document.getElementById('tmf_pos_pick'); if(!sel) return;
                var want=(m.position_np||m.position||'').trim();
                for(var i=0;i<sel.options.length;i++){ if((sel.options[i].dataset.np||'').trim()===want){ sel.selectedIndex=i; break; } }
            })();
            document.getElementById('tmf_pos_np').value   = m.position_np || '';
            document.getElementById('tmf_pos_en').value   = m.position_en || '';
            document.getElementById('tmf_phone').value    = m.phone || '';
            document.getElementById('tmf_email').value    = m.email || '';
            document.getElementById('tmf_order').value    = m.display_order || 0;
            document.getElementById('tmf_is_info').checked  = m.is_information_officer == 1;
            document.getElementById('tmf_is_griev').checked = m.is_grievance_officer == 1;
            document.getElementById('tmf_active').checked   = m.is_active == 1;
            var sel = document.getElementById('tmf_cat');
            for (var i=0; i<sel.options.length; i++) {
                if (sel.options[i].value === m.category) { sel.selectedIndex = i; break; }
            }
            var prev = document.getElementById('tmf_photo_prev');
            prev.innerHTML = m.photo
                ? '<img src="<?php echo SITE_URL; ?>' + m.photo + '" class="tm-photo-preview">'
                : '';
            document.getElementById('tmf_photo_note').textContent = m.photo ? ' — नयाँ फोटो नचुने भने पुरानै रहन्छ' : '';
            document.getElementById('tmf_submit').innerHTML = '<i class="fas fa-save me-2"></i>अपडेट गर्नुहोस्';
            document.getElementById('teamFormTitle').innerHTML = '<i class="fas fa-user-edit me-2"></i>सदस्य सम्पादन';
            document.getElementById('teamFormTabLabel').textContent = 'सम्पादन';
            switchToForm();
        });
    });

    document.querySelectorAll('.tm-cat-badge[data-badge-color]').forEach(function (el) {
        var c = (el.getAttribute('data-badge-color') || '').trim();
        if (!c) return;
        el.style.background = c + '20';
        el.style.color = c;
        el.style.border = '1px solid currentColor';
    });
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>
