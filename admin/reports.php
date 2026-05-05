<?php
$pageTitle = 'प्रतिवेदन व्यवस्थापन';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';

/* ── getBSFiscalYears: BS आर्थिक वर्ष <option> list ── */
if (!function_exists('getBSFiscalYears')) {
    function getBSFiscalYears(string $selected = ''): string {
        $html = '';
        for ($y = 2070; $y <= 2086; $y++) {
            $next  = $y + 1 - 2000;          // e.g. 2080+1-2000 = 81
            $label = $y . '/' . str_pad($next, 2, '0', STR_PAD_LEFT); // 2080/81
            $sel   = ($selected === $label) ? ' selected' : '';
            $html .= "<option value=\"{$label}\"{$sel}>{$label}</option>\n";
        }
        return $html;
    }
}

// Nepali months array
$nepaliMonths = [
    'baisakh' => 'बैशाख',
    'jestha' => 'जेठ',
    'ashadh' => 'असार',
    'shrawan' => 'श्रावण',
    'bhadra' => 'भदौ',
    'ashwin' => 'असोज',
    'kartik' => 'कात्तिक',
    'mangsir' => 'मंसिर',
    'poush' => 'पुष',
    'magh' => 'माघ',
    'falgun' => 'फागुन',
    'chaitra' => 'चैत्र'
];

$quarters = [
    'Q1' => 'पहिलो त्रैमासिक (बैशाख-असार)',
    'Q2' => 'दोस्रो त्रैमासिक (श्रावण-असोज)',
    'Q3' => 'तेस्रो त्रैमासिक (कात्तिक-पुष)',
    'Q4' => 'चौथो त्रैमासिक (माघ-चैत्र)'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = getDB();

/* CSRF सुरक्षा: POST अनुरोध प्रमाणित गर्नुहोस् */
checkCSRF();

    try {
        if ($action === 'add' || $action === 'edit') {
            $id = $_POST['id'] ?? null;
            $title = clean_text($_POST['title']);
            $title_np = clean_text($_POST['title_np']);
            $report_type = clean_text($_POST['report_type']);
            $report_year = clean_text($_POST['report_year']);
            $report_month = $report_type === 'monthly' ? clean_text($_POST['report_month'] ?? '') : null;
            $report_quarter = $report_type === 'quarterly' ? clean_text($_POST['report_quarter'] ?? '') : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $display_order = (int)($_POST['display_order'] ?? 0);

            // Handle file upload
            $file_path = $_POST['existing_file'] ?? '';
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $upload = uploadFile($_FILES['file'], 'reports');
                if ($upload['success']) {
                    $file_path = $upload['path'];
                }
            }

            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO reports (title, title_np, report_type, report_year, report_month, report_quarter, file_path, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $title_np, $report_type, $report_year, $report_month, $report_quarter, $file_path, $is_active, $display_order]);
                setFlash('success', 'प्रतिवेदन थपियो।');
            } else {
                $stmt = $db->prepare("UPDATE reports SET title=?, title_np=?, report_type=?, report_year=?, report_month=?, report_quarter=?, file_path=?, is_active=?, display_order=? WHERE id=?");
                $stmt->execute([$title, $title_np, $report_type, $report_year, $report_month, $report_quarter, $file_path, $is_active, $display_order, $id]);
                setFlash('success', 'प्रतिवेदन अपडेट भयो।');
            }
        } elseif ($action === 'delete') {
            $id = $_POST['id'];
            $db->prepare("DELETE FROM reports WHERE id = ?")->execute([$id]);
            setFlash('success', 'प्रतिवेदन मेटाइयो।');
        }
    } catch (Exception $e) {
        setFlash('error', 'त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।');
    }

    redirect('reports.php');
}

// Get database connection
$db = getDB();

// Get filter
$allowedReportTypes = ['all', 'monthly', 'quarterly', 'progress', 'annual', 'financial', 'audit', 'agm', 'other'];
$filterType = $_GET['type'] ?? 'all';
if (!in_array($filterType, $allowedReportTypes, true)) {
    $filterType = 'all';
}

// Get all reports
try {
    if ($filterType !== 'all') {
        $stmt = $db->prepare("SELECT * FROM reports WHERE report_type = ? ORDER BY report_year DESC, display_order ASC, created_at DESC");
        $stmt->execute([$filterType]);
        $reports = $stmt->fetchAll();
    } else {
        $reports = $db->query("SELECT * FROM reports ORDER BY report_year DESC, display_order ASC, created_at DESC")->fetchAll();
    }
} catch (Exception $e) {
    $reports = [];
}

// Get single report for editing
$editReport = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->execute([(int) $_GET['edit']]);
    $editReport = $stmt->fetch();
}

// Get report type labels
function getReportTypeLabel($type) {
    $labels = [
        'monthly' => 'मासिक',
        'quarterly' => 'त्रैमासिक',
        'progress' => 'प्रगति',
        'annual' => 'वार्षिक',
        'financial' => 'वित्तीय',
        'audit' => 'लेखापरीक्षण',
        'agm' => 'साधारण सभा',
        'other' => 'अन्य'
    ];
    return $labels[$type] ?? $type;
}
?>

<?php
echo adminPageHeader('प्रतिवेदन व्यवस्थापन', 'fa-file-alt', 'वार्षिक प्रतिवेदन र दस्तावेजहरू व्यवस्थापन गर्नुहोस्');
$_flash = getFlash(); if ($_flash) echo adminAlert($_flash['type'], $_flash['message']);
?>
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12"></div>
    </div>

    <div class="row">
        <!-- Form Section -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5><?php echo $editReport ? 'प्रतिवेदन सम्पादन' : 'नयाँ प्रतिवेदन थप्नुहोस्'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="reportForm" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                      <input type="hidden" name="action" value="<?php echo $editReport ? 'edit' : 'add'; ?>">
                        <?php if ($editReport): ?>
                        <input type="hidden" name="id" value="<?php echo $editReport['id']; ?>">
                        <input type="hidden" name="existing_file" value="<?php echo $editReport['file_path']; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">प्रतिवेदन प्रकार *</label>
                            <select name="report_type" id="reportType" class="form-select" required>
                                <option value="">-- छान्नुहोस् --</option>
                                <option value="monthly" <?php echo ($editReport['report_type'] ?? '') === 'monthly' ? 'selected' : ''; ?>>मासिक प्रतिवेदन</option>
                                <option value="quarterly" <?php echo ($editReport['report_type'] ?? '') === 'quarterly' ? 'selected' : ''; ?>>त्रैमासिक प्रतिवेदन</option>
                                <option value="progress" <?php echo ($editReport['report_type'] ?? '') === 'progress' ? 'selected' : ''; ?>>प्रगति प्रतिवेदन</option>
                                <option value="annual" <?php echo ($editReport['report_type'] ?? '') === 'annual' ? 'selected' : ''; ?>>वार्षिक प्रतिवेदन</option>
                                <option value="financial" <?php echo ($editReport['report_type'] ?? '') === 'financial' ? 'selected' : ''; ?>>वित्तीय विवरण</option>
                                <option value="audit" <?php echo ($editReport['report_type'] ?? '') === 'audit' ? 'selected' : ''; ?>>लेखापरीक्षण प्रतिवेदन</option>
                                <option value="agm" <?php echo ($editReport['report_type'] ?? '') === 'agm' ? 'selected' : ''; ?>>साधारण सभा प्रतिवेदन</option>
                                <option value="other" <?php echo ($editReport['report_type'] ?? '') === 'other' ? 'selected' : ''; ?>>अन्य</option>
                            </select>
                        </div>

                        <!-- Month selection (for monthly reports) -->
                        <div class="mb-3" id="monthField" style="display: <?php echo ($editReport['report_type'] ?? '') === 'monthly' ? 'block' : 'none'; ?>;">
                            <label class="form-label">महिना *</label>
                            <select name="report_month" class="form-select">
                                <option value="">-- महिना छान्नुहोस् --</option>
                                <?php foreach ($nepaliMonths as $key => $month): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($editReport['report_month'] ?? '') === $key ? 'selected' : ''; ?>>
                                    <?php echo $month; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Quarter selection (for quarterly reports) -->
                        <div class="mb-3" id="quarterField" style="display: <?php echo ($editReport['report_type'] ?? '') === 'quarterly' ? 'block' : 'none'; ?>;">
                            <label class="form-label">त्रैमास *</label>
                            <select name="report_quarter" class="form-select">
                                <option value="">-- त्रैमास छान्नुहोस् --</option>
                                <?php foreach ($quarters as $key => $quarter): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($editReport['report_quarter'] ?? '') === $key ? 'selected' : ''; ?>>
                                    <?php echo $quarter; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title (English)</label>
                            <input type="text" name="title" class="form-control" required value="<?php echo $editReport['title'] ?? ''; ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">शीर्षक (नेपाली)</label>
                            <input type="text" name="title_np" class="form-control" value="<?php echo $editReport['title_np'] ?? ''; ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">आर्थिक वर्ष *</label>
                                <select name="report_year" class="form-select" required>
                          <option value="">-- आर्थिक वर्ष छान्नुहोस् --</option>
                          <?php echo getBSFiscalYears($editReport['report_year'] ?? ''); ?>
                      </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">क्रम</label>
                                <input type="number" name="display_order" class="form-control" value="<?php echo $editReport['display_order'] ?? 0; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">फाइल (PDF)</label>
                            <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx">
                            <?php if (!empty($editReport['file_path'])): ?>
                            <small class="text-muted">हालको: <?php echo basename($editReport['file_path']); ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive"
                                   <?php echo ($editReport['is_active'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isActive">सक्रिय</label>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $editReport ? 'अपडेट गर्नुहोस्' : 'थप्नुहोस्'; ?>
                        </button>
                        <?php if ($editReport): ?>
                        <a href="reports.php" class="btn btn-secondary">रद्द गर्नुहोस्</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- List Section -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">प्रतिवेदन सूची</h5>
                    <div class="filter-buttons">
                        <a href="?type=all" class="btn btn-sm <?php echo $filterType === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">सबै</a>
                        <a href="?type=monthly" class="btn btn-sm <?php echo $filterType === 'monthly' ? 'btn-primary' : 'btn-outline-secondary'; ?>">मासिक</a>
                        <a href="?type=quarterly" class="btn btn-sm <?php echo $filterType === 'quarterly' ? 'btn-primary' : 'btn-outline-secondary'; ?>">त्रैमासिक</a>
                        <a href="?type=progress" class="btn btn-sm <?php echo $filterType === 'progress' ? 'btn-primary' : 'btn-outline-secondary'; ?>">प्रगति</a>
                        <a href="?type=annual" class="btn btn-sm <?php echo $filterType === 'annual' ? 'btn-primary' : 'btn-outline-secondary'; ?>">वार्षिक</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped table-responsive">
                            <thead>
                                <tr>
                                    <th>शीर्षक</th>
                                    <th>प्रकार</th>
                                    <th>अवधि</th>
                                    <th>स्थिति</th>
                                    <th>कार्य</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td>
                                        <?php echo truncateText($report['title_np'] ?: $report['title'], 35); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $report['report_type'] === 'monthly' ? 'info' :
                                                ($report['report_type'] === 'quarterly' ? 'warning' :
                                                ($report['report_type'] === 'annual' ? 'success' : 'secondary'));
                                        ?>">
                                            <?php echo getReportTypeLabel($report['report_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        echo $report['report_year'];
                                        if ($report['report_month']) {
                                            echo ' / ' . ($nepaliMonths[$report['report_month']] ?? $report['report_month']);
                                        }
                                        if ($report['report_quarter']) {
                                            echo ' / ' . $report['report_quarter'];
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $report['is_active'] ? 'success' : 'secondary'; ?>">
                                            <?php echo $report['is_active'] ? 'सक्रिय' : 'निष्क्रिय'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($report['file_path']): ?>
                                        <a href="../<?php echo $report['file_path']; ?>" class="btn btn-sm btn-success" target="_blank" title="हेर्नुहोस्">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php endif; ?>
                                        <a href="?edit=<?php echo $report['id']; ?>" class="btn btn-sm btn-primary" title="सम्पादन">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('के तपाईं निश्चित हुनुहुन्छ?')">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
    <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $report['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="मेटाउनुहोस्">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($reports)): ?>
                                <tr><td colspan="5" class="text-center">कुनै प्रतिवेदन छैन</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportType = document.getElementById('reportType');
    const monthField = document.getElementById('monthField');
    const quarterField = document.getElementById('quarterField');

    function toggleFields() {
        const type = reportType.value;
        monthField.style.display = type === 'monthly' ? 'block' : 'none';
        quarterField.style.display = type === 'quarterly' ? 'block' : 'none';
    }

    reportType.addEventListener('change', toggleFields);
    toggleFields();
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>
