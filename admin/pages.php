<?php
/**
 * Admin Pages Management
 * स्थिर पृष्ठ व्यवस्थापन (About, FAQ etc.) + Dynamic Pages
 */
$pageTitle = 'पृष्ठ व्यवस्थापन';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';

$db = getDB();

/* CSRF सुरक्षा: POST अनुरोध प्रमाणित गर्नुहोस् */
checkCSRF();
$tab = $_POST['tab'] ?? $_GET['tab'] ?? 'dynamic';
if (!in_array($tab, ['dynamic', 'static'], true)) {
    $tab = 'dynamic';
}
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
if (!in_array($action, ['list', 'edit', 'edit_static', 'delete', 'add'], true)) {
    $action = 'list';
}
if ($action === 'add') {
    $action = 'edit'; /* नयाँ page पनि एउटै edit form बाट */
}

function renderTinyEditorScript(): void {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    echo '<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>';
    echo "<script>
        tinymce.init({
            selector: '.editor',
            height: 400,
            plugins: 'lists link image table code preview fullscreen',
            toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | link image table | code preview fullscreen',
            menubar: false,
            branding: false,
            promotion: false,
            license_key: 'gpl'
        });
    </script>";
}

/* v10.3: Ensure footer policy pages exist so admin can edit them.
   (Only inserts if missing; never overwrites existing content.) */
try {
    $seedPolicies = [
        ['privacy-policy',   'गोपनीयता नीति', 'Privacy Policy'],
        ['terms-of-service', 'सेवाका सर्तहरू', 'Terms of Service'],
        ['cookie-policy',    'कुकी नीति', 'Cookie Policy'],
    ];
    $chk = $db->prepare("SELECT id FROM pages WHERE slug = ? LIMIT 1");
    $ins = $db->prepare("INSERT INTO pages (slug, title, title_np, title_en, content, content_np, show_in_menu, menu_position, menu_order, is_active)
                         VALUES (?, ?, ?, ?, ?, ?, 0, 'footer', 99, 1)");
    foreach ($seedPolicies as $p) {
        [$slug, $np, $en] = $p;
        $chk->execute([$slug]);
        if (!$chk->fetch()) {
            $bodyNp = '<p>यो ' . htmlspecialchars($np, ENT_QUOTES, 'UTF-8') . ' पृष्ठ हो। यहाँ आफ्नो नीतिहरू लेख्नुहोस्।</p>';
            $bodyEn = '<p>This is the ' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . ' page. Please update the policy content here.</p>';
            $ins->execute([$slug, $np, $np, $en, $bodyEn, $bodyNp]);
        }
    }
} catch (Throwable $e) { /* ignore if pages table missing */ }

// Define static pages — परिचय मुख्य विषयवस्तु `pages` (slug: about) मा मात्र (दोहोरो हटाइयो)
$staticPages = [
    /* हाम्रो इतिहास — यो about-settings.php बाट मात्र सम्पादन गर्नुहोस्।
       (History photo र text दुवै त्यहाँ राखिएको छ) */
    'vision_content' => [
        'title' => 'हाम्रो दृष्टिकोण',
        'title_en' => 'Our Vision',
    ],
    'mission_content' => [
        'title' => 'हाम्रो लक्ष्य',
        'title_en' => 'Our Mission',
    ],
    'values_content' => [
        'title' => 'मूल मान्यताहरू',
        'title_en' => 'Core Values',
    ],
    'chairman_message' => [
        'title' => 'अध्यक्षको सन्देश',
        'title_en' => "Chairman's Message",
    ],
    'ceo_message' => [
        'title' => 'प्रमुख कार्यकारी अधिकृतको सन्देश',
        'title_en' => "CEO's Message",
    ]
];

// Handle Static Page form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['page_key'])) {
    $pageKey = clean_text($_POST['page_key']);
    if ($pageKey === 'about_content') {
        setFlash('info', 'हाम्रो बारेमाको मुख्य विषयवस्तु अब गतिशील पृष्ठ (slug: about) मा मात्र सम्पादन हुन्छ।');
        redirect('pages.php?tab=dynamic');
    }
    $contentNp = $_POST['content_np'] ?? '';
    $contentEn = $_POST['content_en'] ?? '';

    try {
        // Check if setting exists
        $checkStmt = $db->prepare("SELECT id FROM site_settings WHERE setting_key = ?");
        $checkStmt->execute([$pageKey . '_np']);

        if ($checkStmt->fetch()) {
            // Update
            $stmt = $db->prepare("UPDATE site_settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$contentNp, $pageKey . '_np']);
            $stmt->execute([$contentEn, $pageKey . '_en']);
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$pageKey . '_np', $contentNp]);
            $stmt->execute([$pageKey . '_en', $contentEn]);
        }

        setFlash('success', 'पृष्ठ सफलतापूर्वक अपडेट भयो।');
    } catch (Exception $e) {
        setFlash('error', 'त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।');
    }

    redirect('pages.php?action=edit_static&page=' . $pageKey);
}

// Handle Dynamic Page form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dynamic_page'])) {
    $pageId = intval($_POST['page_id'] ?? 0);
    $slug = clean_text($_POST['slug'] ?? '');
    $titleNp = clean_text($_POST['title_np'] ?? '');
    $titleEn = clean_text($_POST['title_en'] ?? '');
    $contentNp = $_POST['content_np'] ?? '';
    $contentEn = $_POST['content_en'] ?? '';
    $showInMenu = isset($_POST['show_in_menu']) ? 1 : 0;
    $menuPosition = clean_text($_POST['menu_position'] ?? 'about');
    $menuOrder = intval($_POST['menu_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $allowedMenuPositions = ['about','services','more','footer'];
    if (!in_array($menuPosition, $allowedMenuPositions, true)) $menuPosition = 'about';

    // Generate slug if empty
    if (empty($slug) && (!empty($titleEn) || !empty($titleNp))) {
        $slugSource = !empty($titleEn) ? $titleEn : $titleNp;
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $slugSource));
        $slug = trim($slug, '-');
    }
    if (empty($slug)) $slug = 'page-' . time();

    if (empty($titleNp)) {
        setFlash('error', 'शीर्षक (नेपाली) अनिवार्य छ।');
        redirect('pages.php?action=edit' . ($pageId > 0 ? '&id=' . $pageId : '') . '&tab=dynamic');
    }

    try {
        // Slug uniqueness check
        if ($pageId > 0) {
            $slugChk = $db->prepare("SELECT id FROM pages WHERE slug = ? AND id <> ? LIMIT 1");
            $slugChk->execute([$slug, $pageId]);
        } else {
            $slugChk = $db->prepare("SELECT id FROM pages WHERE slug = ? LIMIT 1");
            $slugChk->execute([$slug]);
        }
        if ($slugChk->fetch()) {
            setFlash('error', 'यो slug पहिले नै प्रयोग भएको छ। कृपया अर्को slug राख्नुहोस्।');
            redirect('pages.php?action=edit' . ($pageId > 0 ? '&id=' . $pageId : '') . '&tab=dynamic');
        }

        if ($pageId > 0) {
            // Update
            $stmt = $db->prepare("UPDATE pages SET slug = ?, title = ?, title_np = ?, title_en = ?, content = ?, content_np = ?, show_in_menu = ?, menu_position = ?, menu_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$slug, $titleNp, $titleNp, $titleEn, $contentEn, $contentNp, $showInMenu, $menuPosition, $menuOrder, $isActive, $pageId]);
            setFlash('success', 'पृष्ठ सफलतापूर्वक अपडेट भयो।');
        } else {
            // Insert
            $stmt = $db->prepare("INSERT INTO pages (slug, title, title_np, title_en, content, content_np, show_in_menu, menu_position, menu_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$slug, $titleNp, $titleNp, $titleEn, $contentEn, $contentNp, $showInMenu, $menuPosition, $menuOrder, $isActive]);
            setFlash('success', 'नयाँ पृष्ठ सफलतापूर्वक थपियो।');
        }
    } catch (Exception $e) {
        setFlash('error', 'त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।');
    }

    redirect('pages.php?tab=dynamic');
}

// Handle bulk status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulkAction = clean_text($_POST['bulk_action'] ?? '');
    $selectedIds = $_POST['selected_ids'] ?? [];
    $safeIds = array_values(array_filter(array_map('intval', (array)$selectedIds), fn($v) => $v > 0));
    if (empty($safeIds) || !in_array($bulkAction, ['activate','deactivate'], true)) {
        setFlash('error', 'Bulk action का लागि valid rows छान्नुहोस्।');
        redirect('pages.php?tab=dynamic');
    }
    $target = $bulkAction === 'activate' ? 1 : 0;
    $placeholders = implode(',', array_fill(0, count($safeIds), '?'));
    try {
        $sql = "UPDATE pages SET is_active = ? WHERE id IN ($placeholders)";
        $params = array_merge([$target], $safeIds);
        $st = $db->prepare($sql);
        $st->execute($params);
        setFlash('success', 'Bulk status update सफल भयो।');
    } catch (Throwable $e) {
        setFlash('error', 'Bulk update गर्दा त्रुटि भयो।');
    }
    redirect('pages.php?tab=dynamic');
}

// Handle Delete Dynamic Page
if ($action === 'delete' && isset($_POST['id'])) {
    $deleteId = intval($_POST['id'] ?? 0);
    try {
        $protectedSlugs = ['privacy-policy', 'terms-of-service', 'cookie-policy'];
        $chk = $db->prepare("SELECT slug FROM pages WHERE id = ? LIMIT 1");
        $chk->execute([$deleteId]);
        $deleteRow = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$deleteRow) {
            setFlash('error', 'पृष्ठ फेला परेन।');
        } elseif (in_array($deleteRow['slug'] ?? '', $protectedSlugs, true)) {
            setFlash('error', 'यो system policy page हो। हटाउन मिल्दैन, सम्पादन मात्र गर्न सकिन्छ।');
        } else {
            $stmt = $db->prepare("DELETE FROM pages WHERE id = ?");
            $stmt->execute([$deleteId]);
            setFlash('success', 'पृष्ठ सफलतापूर्वक हटाइयो।');
        }
    } catch (Exception $e) {
        setFlash('error', 'त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।');
    }
    redirect('pages.php?tab=dynamic');
}

// Edit Static Page
if ($action === 'edit_static' && isset($_GET['page'])) {
    $pageKey = trim((string)($_GET['page'] ?? ''));
    if ($pageKey === 'about_content') {
        try {
            $st = $db->prepare("SELECT id FROM pages WHERE slug = 'about' LIMIT 1");
            $st->execute();
            $aboutRow = $st->fetch(PDO::FETCH_ASSOC);
            if ($aboutRow && (int)($aboutRow['id'] ?? 0) > 0) {
                redirect('pages.php?action=edit&id=' . (int)$aboutRow['id'] . '&tab=dynamic');
            }
        } catch (Throwable $e) { /* ignore */ }
        setFlash('info', 'गतिशील पृष्ठहरूमा slug <code>about</code> भएको पृष्ठ सम्पादन गर्नुहोस्।');
        redirect('pages.php?tab=dynamic');
    }
    if ($pageKey === '' || !array_key_exists($pageKey, $staticPages)) {
        setFlash('error', 'पृष्ठ फेला परेन।');
        redirect('pages.php');
    }

    $pageInfo = $staticPages[$pageKey];

    // Get current content
    $contentNp = getSetting($pageKey . '_np', '');
    $contentEn = getSetting($pageKey . '_en', '');
    ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-edit"></i> <?php echo $pageInfo['title']; ?> सम्पादन</h5>
            <a href="pages.php?tab=static" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> फिर्ता
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="">
    <?php echo csrfField(); ?>
                <input type="hidden" name="page_key" value="<?php echo $pageKey; ?>">

                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#nepali" type="button">
                            <i class="fas fa-language"></i> नेपाली
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#english" type="button">
                            <i class="fas fa-globe"></i> English
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="nepali">
                        <div class="mb-3">
                            <label class="form-label">विषयवस्तु (नेपाली)</label>
                            <textarea name="content_np" class="form-control editor" rows="15"><?php echo htmlspecialchars($contentNp); ?></textarea>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="english">
                        <div class="mb-3">
                            <label class="form-label">Content (English)</label>
                            <textarea name="content_en" class="form-control editor" rows="15"><?php echo htmlspecialchars($contentEn); ?></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> सेभ गर्नुहोस्
                </button>
            </form>
        </div>
    </div>

    <?php renderTinyEditorScript(); ?>

    <?php
}
// Edit Dynamic Page — ID GET बाट लिन मिल्छ (navigation link)
elseif ($action === 'edit' && (isset($_GET['id']) || isset($_POST['id']))) {
    $pageId = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    $page = null;

    if ($pageId > 0) {
        $stmt = $db->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$pageId]);
        $page = $stmt->fetch();
    }

    if (!$page && $pageId > 0) {
        setFlash('error', 'पृष्ठ फेला परेन।');
        redirect('pages.php');
    }
    ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-edit"></i> <?php echo $page ? 'पृष्ठ सम्पादन' : 'नयाँ पृष्ठ थप्नुहोस्'; ?></h5>
            <a href="pages.php?tab=dynamic" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> फिर्ता
            </a>
        </div>
        <div class="card-body">
            <form method="POST" action="">
    <?php echo csrfField(); ?>
                <input type="hidden" name="save_dynamic_page" value="1">
                <input type="hidden" name="page_id" value="<?php echo $page['id'] ?? 0; ?>">

                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label class="form-label">Slug (URL) <span class="text-danger">*</span></label>
                            <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($page['slug'] ?? ''); ?>" placeholder="e.g. membership, privacy-policy" required>
                            <small class="text-muted">URL: <?php echo SITE_URL; ?>page.php?slug=<strong>[slug]</strong></small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">स्थिति</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php echo ($page['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isActive">सक्रिय</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">शीर्षक (नेपाली) <span class="text-danger">*</span></label>
                            <input type="text" name="title_np" class="form-control" value="<?php echo htmlspecialchars($page['title_np'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Title (English)</label>
                            <input type="text" name="title_en" class="form-control" value="<?php echo htmlspecialchars($page['title_en'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_in_menu" id="showInMenu" <?php echo ($page['show_in_menu'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="showInMenu">मेनुमा देखाउनुहोस्</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="menu_position" class="form-select form-select-sm">
                            <option value="about" <?php echo ($page['menu_position'] ?? '') === 'about' ? 'selected' : ''; ?>>हाम्रो बारेमा</option>
                            <option value="services" <?php echo ($page['menu_position'] ?? '') === 'services' ? 'selected' : ''; ?>>सेवाहरू</option>
                            <option value="more" <?php echo ($page['menu_position'] ?? '') === 'more' ? 'selected' : ''; ?>>थप</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="number" name="menu_order" class="form-control form-control-sm" value="<?php echo $page['menu_order'] ?? 0; ?>" placeholder="क्रम">
                    </div>
                </div>

                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#nepali" type="button">
                            <i class="fas fa-language"></i> नेपाली
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#english" type="button">
                            <i class="fas fa-globe"></i> English
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="nepali">
                        <div class="mb-3">
                            <label class="form-label">विषयवस्तु (नेपाली)</label>
                            <textarea name="content_np" class="form-control editor" rows="15"><?php echo htmlspecialchars($page['content_np'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="english">
                        <div class="mb-3">
                            <label class="form-label">Content (English)</label>
                            <textarea name="content_en" class="form-control editor" rows="15"><?php echo htmlspecialchars($page['content'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> सेभ गर्नुहोस्
                </button>
            </form>
        </div>
    </div>

    <?php renderTinyEditorScript(); ?>

    <?php
} else {
    // List pages with tabs

    // Get dynamic pages with optional filters
    $fStatus = $_GET['f_status'] ?? '';
    if ($fStatus !== '' && !in_array($fStatus, ['active', 'inactive'], true)) {
        $fStatus = '';
    }
    $fMenu = $_GET['f_menu'] ?? '';
    if ($fMenu !== '' && !in_array($fMenu, ['about', 'services', 'more', 'footer', 'none'], true)) {
        $fMenu = '';
    }
    $dynamicPages = [];
    try {
        $where = ['1=1'];
        $params = [];
        if ($fStatus === 'active') { $where[] = 'is_active = 1'; }
        if ($fStatus === 'inactive') { $where[] = 'is_active = 0'; }
        if (in_array($fMenu, ['about','services','more','footer'], true)) {
            $where[] = 'show_in_menu = 1 AND menu_position = ?';
            $params[] = $fMenu;
        } elseif ($fMenu === 'none') {
            $where[] = 'show_in_menu = 0';
        }
        $sql = "SELECT * FROM pages WHERE " . implode(' AND ', $where) . " ORDER BY menu_position, menu_order, id";
        $st = $db->prepare($sql);
        $st->execute($params);
        $dynamicPages = $st->fetchAll();
    } catch (Exception $e) {
        $dynamicPages = [];
    }
    echo adminPageHeader(
        'पृष्ठ व्यवस्थापन', 'fa-file-alt',
        'गतिशील र स्थिर पृष्ठहरू (About/FAQ सेक्सनहरू) व्यवस्थापन',
        '<a href="pages.php?action=edit" class="btn btn-outline-light btn-sm"><i class="fas fa-plus me-1"></i>नयाँ पृष्ठ</a>'
    );
    $_flash = getFlash(); if ($_flash) echo adminAlert($_flash['type'], $_flash['message']);
    ?>

    <!-- Tab Navigation -->
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'dynamic' ? 'active' : ''; ?>" href="pages.php?tab=dynamic">
                <i class="fas fa-file-alt"></i> गतिशील पृष्ठहरू (Dynamic)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'static' ? 'active' : ''; ?>" href="pages.php?tab=static">
                <i class="fas fa-cog"></i> स्थिर विषयवस्तु (About Sections)
            </a>
        </li>
    </ul>

    <?php if ($tab === 'dynamic'): ?>
    <!-- Dynamic Pages -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-file-alt"></i> गतिशील पृष्ठहरू</h5>
            <div class="d-flex gap-2">
                <!-- Policy pages मा सीधा पुग्ने shortcut -->
                <a href="pages.php?tab=dynamic&f_menu=footer" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-shield-halved me-1"></i>नीति पृष्ठहरू
                </a>
                <a href="pages.php?action=edit" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> नयाँ पृष्ठ
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="alert alert-info border-0 rounded-0 mb-0 small">
                <i class="fas fa-circle-info me-1"></i>
                <strong>गोपनीयता नीति / सेवाका सर्तहरू / कुकी नीति</strong> सम्पादन गर्न
                <a href="pages.php?tab=dynamic&f_menu=footer" class="fw-semibold">Footer filter</a> प्रयोग गर्नुहोस्।
            </div>
            <form method="GET" class="px-3 py-2 border-bottom bg-light d-flex gap-2 align-items-end flex-wrap">
                <input type="hidden" name="tab" value="dynamic">
                <div>
                    <label class="form-label form-label-sm mb-1 small">स्थिति</label>
                    <select name="f_status" class="form-select form-select-sm">
                        <option value="">सबै</option>
                        <option value="active" <?php echo $fStatus==='active'?'selected':''; ?>>सक्रिय</option>
                        <option value="inactive" <?php echo $fStatus==='inactive'?'selected':''; ?>>निष्क्रिय</option>
                    </select>
                </div>
                <div>
                    <label class="form-label form-label-sm mb-1 small">मेनु</label>
                    <select name="f_menu" class="form-select form-select-sm">
                        <option value="">सबै</option>
                        <option value="about" <?php echo $fMenu==='about'?'selected':''; ?>>हाम्रो बारेमा</option>
                        <option value="services" <?php echo $fMenu==='services'?'selected':''; ?>>सेवाहरू</option>
                        <option value="more" <?php echo $fMenu==='more'?'selected':''; ?>>थप</option>
                        <option value="footer" <?php echo $fMenu==='footer'?'selected':''; ?>>फुटर</option>
                        <option value="none" <?php echo $fMenu==='none'?'selected':''; ?>>मेनुमा छैन</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                    <a href="pages.php?tab=dynamic" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
            <?php if (empty($dynamicPages)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-file-alt fa-3x mb-3"></i>
                <p>कुनै पृष्ठ छैन।</p>
                <a href="pages.php?action=edit" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> पहिलो पृष्ठ थप्नुहोस्
                </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
            <form method="POST">
            <?php echo csrfField(); ?>

            <!-- खोज बक्स -->
            <div class="admin-search-wrap px-3 py-2 border-bottom bg-light d-flex align-items-center gap-3">
                <div class="input-group input-group-sm" style="max-width:300px">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 admin-table-search" placeholder="पृष्ठ खोज्नुहोस्..." autocomplete="off">
                </div>
                <div class="ms-auto d-flex gap-2">
                    <button type="submit" name="bulk_action" value="activate" class="btn btn-sm btn-outline-success">Bulk Active</button>
                    <button type="submit" name="bulk_action" value="deactivate" class="btn btn-sm btn-outline-secondary">Bulk Inactive</button>
                </div>
            </div>
            <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="4%"><input type="checkbox" onclick="document.querySelectorAll('.pg-select').forEach(c=>c.checked=this.checked)"></th>
                            <th width="5%">#</th>
                            <th width="15%">Slug</th>
                            <th width="25%">शीर्षक (नेपाली)</th>
                            <th width="20%">Title (English)</th>
                            <th width="10%">मेनु</th>
                            <th width="10%">स्थिति</th>
                            <th width="15%">कार्य</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dynamicPages as $i => $pg): ?>
                        <tr>
                            <td><input type="checkbox" class="pg-select" name="selected_ids[]" value="<?php echo (int)$pg['id']; ?>"></td>
                            <td><?php echo $i + 1; ?></td>
                            <td>
                                <a href="<?php echo SITE_URL; ?>page.php?slug=<?php echo $pg['slug']; ?>" target="_blank" class="text-decoration-none">
                                    <code><?php echo $pg['slug']; ?></code>
                                    <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($pg['title_np'] ?: $pg['title']); ?></td>
                            <td><?php echo htmlspecialchars($pg['title_en'] ?: '-'); ?></td>
                            <td>
                                <?php if ($pg['show_in_menu']): ?>
                                <?php
                                $menuPos = (string)($pg['menu_position'] ?? '');
                                $menuLabelMap = [
                                    'about' => 'हाम्रो बारेमा',
                                    'services' => 'सेवाहरू',
                                    'more' => 'थप',
                                    'footer' => 'फुटर',
                                ];
                                $menuLabel = $menuLabelMap[$menuPos] ?? $menuPos;
                                ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($menuLabel); ?></span>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($pg['is_active']): ?>
                                <span class="badge bg-success">सक्रिय</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">निष्क्रिय</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="pages.php?action=edit&id=<?php echo $pg['id']; ?>" class="btn btn-sm btn-primary" title="सम्पादन">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php $isProtectedPolicy = in_array(($pg['slug'] ?? ''), ['privacy-policy','terms-of-service','cookie-policy'], true); ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('यो पृष्ठ मेटाउने हो?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$pg['id']; ?>">
                                    <?php echo csrfField(); ?>
                                    <button type="submit" class="btn btn-danger btn-sm" title="<?php echo $isProtectedPolicy ? 'यो policy page हटाउन मिल्दैन' : 'मेटाउनुहोस्'; ?>" <?php echo $isProtectedPolicy ? 'disabled' : ''; ?>>
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
            <?php endif; ?>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5><i class="fas fa-info-circle"></i> जानकारी</h5>
        </div>
        <div class="card-body">
            <p><strong>गतिशील पृष्ठहरू</strong> - यी पृष्ठहरू <code>/page.php?slug=[slug]</code> URL मा देखिन्छन्।</p>
            <ul>
                <li><strong>Slug:</strong> URL मा प्रयोग हुने unique identifier (जस्तै: membership, privacy-policy)</li>
                <li><strong>मेनुमा देखाउनुहोस्:</strong> यो पृष्ठलाई navigation menu मा देखाउन चाहनुहुन्छ भने check गर्नुहोस्</li>
                <li><strong>मेनु स्थिति:</strong> कुन dropdown मा देखाउने (हाम्रो बारेमा, सेवाहरू, थप)</li>
            </ul>
        </div>
    </div>

    <?php else: ?>
    <!-- Static Pages (About Sections) -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-cog"></i> About Us पेज सेक्सनहरू</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">#</th>
                            <th width="35%">सेक्सन नाम (नेपाली)</th>
                            <th width="35%">Section Name (English)</th>
                            <th width="25%">कार्य</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($staticPages as $key => $page): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><i class="fas fa-file-alt text-muted me-2"></i><?php echo $page['title']; ?></td>
                            <td><?php echo $page['title_en']; ?></td>
                            <td>
                                <a href="pages.php?action=edit_static&page=<?php echo $key; ?>" class="btn btn-sm btn-primary" title="सम्पादन">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5><i class="fas fa-info-circle"></i> जानकारी</h5>
        </div>
        <div class="card-body">
            <p><strong>स्थिर विषयवस्तु</strong> - यी सेक्सनहरूको content <code>/about.php</code> पेजमा देखिन्छन्:</p>
            <ul>
                <li><strong>हाम्रो बारेमा / परिचय (मुख्य लेख):</strong> <a href="pages.php?tab=dynamic">गतिशील पृष्ठहरू</a> → slug <code>about</code> (दोहोरो स्थिर सम्पादन हटाइयो)</li>
                <li><strong>हाम्रो इतिहास:</strong> <a href="about-settings.php" class="text-primary">About Page (इतिहास) Settings</a> बाट सम्पादन गर्नुहोस् — History photo र content त्यहाँ छ।</li>
                <li><strong>हाम्रो दृष्टिकोण:</strong> Vision card (#vision)</li>
                <li><strong>हाम्रो लक्ष्य:</strong> Mission card (#vision)</li>
                <li><strong>मूल मान्यताहरू:</strong> Values section (#values)</li>
                <li><strong>अध्यक्षको सन्देश:</strong> गृहपृष्ठ र About Us (#chairman)</li>
                <li><strong>प्रमुख कार्यकारी अधिकृतको सन्देश:</strong> गृहपृष्ठ र About Us (#ceo)</li>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php
}

require_once 'includes/admin-footer.php';
?>
