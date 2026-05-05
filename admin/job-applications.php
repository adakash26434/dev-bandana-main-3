<?php
$pageTitle = 'आवेदनहरू व्यवस्थापन';
require_once 'includes/admin-header.php';
require_once __DIR__ . '/../includes/auth-roles.php';
/* RBAC: staff hercha matra; mutate admin+ matra */
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_role('admin');

$db = getDB();
$jobAppStatuses = ['pending', 'shortlisted', 'interviewed', 'selected', 'rejected'];

/* पुरानो DB compatibility: job_applications.is_read column नहुन सक्छ */
$hasIsRead = false;
try {
    $colChk = $db->query("SHOW COLUMNS FROM job_applications LIKE 'is_read'");
    $hasIsRead = $colChk && $colChk->fetch() !== false;
} catch (Exception $e) {}

/* CSRF सुरक्षा: POST अनुरोध प्रमाणित गर्नुहोस् */
checkCSRF();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_status') {
            $id = (int)$_POST['id'];
            $status = $_POST['status'] ?? 'pending';
            if (!in_array($status, $jobAppStatuses, true)) {
                $status = 'pending';
            }
            $notes = $_POST['admin_notes'] ?? '';

            if ($hasIsRead) {
                $stmt = $db->prepare("UPDATE job_applications SET status = ?, admin_notes = ?, is_read = 1 WHERE id = ?");
                $stmt->execute([$status, $notes, $id]);
            } else {
                $stmt = $db->prepare("UPDATE job_applications SET status = ?, admin_notes = ? WHERE id = ?");
                $stmt->execute([$status, $notes, $id]);
            }

            /* Member portal notification */
            try {
                $nr = $db->prepare("SELECT full_name, email, phone FROM job_applications WHERE id=?");
                $nr->execute([$id]); $nd = $nr->fetch();
                if ($nd && function_exists('sendMemberStatusUpdate')) {
                    sendMemberStatusUpdate('job', $nd['email']??'', $nd['phone']??'', $nd['full_name']??'', $status, $notes, '');
                }
            } catch (Exception $ex) {}

            setFlash('success', 'आवेदन स्थिति अपडेट भयो।');
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            $db->prepare("DELETE FROM job_applications WHERE id = ?")->execute([$id]);
            setFlash('success', 'आवेदन मेटाइयो।');
        } elseif ($action === 'mark_read' && $hasIsRead) {
            $id = (int)$_POST['id'];
            $db->prepare("UPDATE job_applications SET is_read = 1 WHERE id = ?")->execute([$id]);
        }
    } catch (Exception $e) {
        setFlash('error', 'त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।');
    }

    redirect('job-applications.php' . (isset($_GET['career_id']) ? '?career_id=' . (int) $_GET['career_id'] : ''));
}

// Filter by career/job
$careerId = isset($_GET['career_id']) ? (int)$_GET['career_id'] : 0;
$statusFilter = $_GET['status'] ?? '';
if ($statusFilter !== '' && !in_array($statusFilter, $jobAppStatuses, true)) {
    $statusFilter = '';
}
$jobSearch = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 200, 'UTF-8');

// Build query
$query = "SELECT ja.*, c.title as job_title, c.deadline
          FROM job_applications ja
          LEFT JOIN careers c ON ja.career_id = c.id
          WHERE 1=1";
$params = [];

if ($careerId) {
    $query .= " AND ja.career_id = ?";
    $params[] = $careerId;
}

if ($statusFilter) {
    $query .= " AND ja.status = ?";
    $params[] = $statusFilter;
}

if ($jobSearch !== '') {
    $query .= " AND (ja.full_name LIKE ? OR ja.email LIKE ? OR ja.phone LIKE ?)";
    $jt = "%$jobSearch%"; $params = array_merge($params, [$jt,$jt,$jt]);
}

$query .= " ORDER BY ja.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get all careers for filter dropdown
$careers = $db->query("SELECT id, title FROM careers ORDER BY created_at DESC")->fetchAll();

// Get statistics
if ($hasIsRead) {
    $stats = $db->query("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
        SUM(CASE WHEN status = 'interviewed' THEN 1 ELSE 0 END) as interviewed,
        SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) as selected,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread
        FROM job_applications")->fetch();
} else {
    $stats = $db->query("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
        SUM(CASE WHEN status = 'interviewed' THEN 1 ELSE 0 END) as interviewed,
        SUM(CASE WHEN status = 'selected' THEN 1 ELSE 0 END) as selected,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        0 as unread
        FROM job_applications")->fetch();
}

// View single application
$viewApplication = null;
if (isset($_GET['view'])) {
    $viewId = (int) $_GET['view'];
    $stmt = $db->prepare("SELECT ja.*, c.title as job_title, c.title_np as job_title_np, c.deadline, c.department
                          FROM job_applications ja
                          LEFT JOIN careers c ON ja.career_id = c.id
                          WHERE ja.id = ?");
    $stmt->execute([$viewId]);
    $viewApplication = $stmt->fetch();

    // Mark as read
    if ($hasIsRead && $viewApplication && !$viewApplication['is_read']) {
        $db->prepare("UPDATE job_applications SET is_read = 1 WHERE id = ?")->execute([$viewId]);
    }
}
?>

<?php echo adminPageHeader('जागिर आवेदन व्यवस्थापन', 'fa-file-alt',
    'आउनुभएका जागिर आवेदनहरू',
    adminStatLink('?status=pending',     'warning',   'पेन्डिङ',       $stats['pending']     ?? 0) . ' ' .
    adminStatLink('?status=shortlisted', 'info',      'छनोट',          $stats['shortlisted'] ?? 0) . ' ' .
    adminStatLink('?status=selected',    'success',   'चयन',           $stats['selected']    ?? 0) . ' ' .
    adminStatLink('?status=rejected',    'danger',    'अस्वीकृत',      $stats['rejected']    ?? 0)
); ?>
<?php if ($flash = getFlash()): ?>
<div class="alert alert-<?php echo $flash['type']==='success'?'success':'danger'; ?> alert-dismissible fade show mb-3"><i class="fas fa-<?php echo $flash['type']==='success'?'check-circle':'exclamation-circle'; ?> me-2"></i><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="container-fluid py-4">
    <!-- ── Stat Mini Row ── -->
    <div class="stat-mini-row no-print">
        <a href="job-applications.php" class="stat-mini <?php echo !$statusFilter&&!$careerId?'active-filter':''; ?>">
            <div class="sm-icon ic-total"><i class="fas fa-file-alt"></i></div>
            <div class="sm-val"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="sm-lbl">जम्मा</div>
        </a>
        <a href="?status=pending" class="stat-mini <?php echo $statusFilter==='pending'?'active-filter':''; ?>">
            <div class="sm-icon ic-pending"><i class="fas fa-clock"></i></div>
            <div class="sm-val"><?php echo $stats['pending'] ?? 0; ?></div>
            <div class="sm-lbl">पेन्डिङ</div>
        </a>
        <a href="?status=shortlisted" class="stat-mini <?php echo $statusFilter==='shortlisted'?'active-filter':''; ?>">
            <div class="sm-icon ic-process"><i class="fas fa-list-check"></i></div>
            <div class="sm-val"><?php echo $stats['shortlisted'] ?? 0; ?></div>
            <div class="sm-lbl">छनोट</div>
        </a>
        <a href="?status=interviewed" class="stat-mini <?php echo $statusFilter==='interviewed'?'active-filter':''; ?>">
            <div class="sm-icon" style="background:linear-gradient(135deg,#64748b20,#64748b10);"><i class="fas fa-comments" style="color:#64748b;"></i></div>
            <div class="sm-val"><?php echo $stats['interviewed'] ?? 0; ?></div>
            <div class="sm-lbl">अन्तर्वार्ता</div>
        </a>
        <a href="?status=selected" class="stat-mini <?php echo $statusFilter==='selected'?'active-filter':''; ?>">
            <div class="sm-icon ic-approved"><i class="fas fa-user-check"></i></div>
            <div class="sm-val"><?php echo $stats['selected'] ?? 0; ?></div>
            <div class="sm-lbl">चयन</div>
        </a>
        <a href="?status=rejected" class="stat-mini <?php echo $statusFilter==='rejected'?'active-filter':''; ?>">
            <div class="sm-icon ic-rejected"><i class="fas fa-times-circle"></i></div>
            <div class="sm-val"><?php echo $stats['rejected'] ?? 0; ?></div>
            <div class="sm-lbl">अस्वीकृत</div>
        </a>
    </div>

    <?php if ($viewApplication): ?>
    <!-- View Application Detail -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($viewApplication['full_name']); ?>
                        <span class="badge bg-<?php
                            echo match($viewApplication['status']) {
                                'pending' => 'warning',
                                'shortlisted' => 'info',
                                'interviewed' => 'secondary',
                                'selected' => 'success',
                                'rejected' => 'danger',
                                default => 'secondary'
                            };
                        ?>">
                            <?php echo ucfirst($viewApplication['status']); ?>
                        </span>
                    </h5>
                    <a href="job-applications.php<?php echo $careerId ? '?career_id=' . $careerId : ''; ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> फिर्ता
                    </a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Application Details -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-primary">व्यक्तिगत जानकारी</h6>
                                    <table class="table-hover table table-sm">
                                        <tr>
                                            <td><strong>पूरा नाम:</strong></td>
                                            <td><?php echo htmlspecialchars($viewApplication['full_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>इमेल:</strong></td>
                                            <td><a href="mailto:<?php echo $viewApplication['email']; ?>"><?php echo $viewApplication['email']; ?></a></td>
                                        </tr>
                                        <tr>
                                            <td><strong>फोन:</strong></td>
                                            <td><a href="tel:<?php echo $viewApplication['phone']; ?>"><?php echo $viewApplication['phone']; ?></a></td>
                                        </tr>
                                        <tr>
                                            <td><strong>ठेगाना:</strong></td>
                                            <td><?php echo htmlspecialchars($viewApplication['address'] ?? '-'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>जन्म मिति:</strong></td>
                                            <td><?php echo $viewApplication['date_of_birth'] ?? '-'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>लिङ्ग:</strong></td>
                                            <td><?php echo ucfirst($viewApplication['gender'] ?? '-'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-primary">शिक्षा र अनुभव</h6>
                                    <table class="table-hover table table-sm">
                                        <tr>
                                            <td><strong>शिक्षा:</strong></td>
                                            <td><?php echo htmlspecialchars($viewApplication['education'] ?? '-'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>अनुभव:</strong></td>
                                            <td><?php echo htmlspecialchars($viewApplication['experience'] ?? '-'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>हालको रोजगारदाता:</strong></td>
                                            <td><?php echo htmlspecialchars($viewApplication['current_employer'] ?? '-'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>अपेक्षित तलब:</strong></td>
                                            <td><?php echo htmlspecialchars($viewApplication['expected_salary'] ?? '-'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>आवेदन मिति:</strong></td>
                                            <td><?php echo formatNepaliDate($viewApplication['created_at'], true); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <!-- Cover Letter -->
                            <?php if (!empty($viewApplication['cover_letter'])): ?>
                            <div class="mb-4">
                                <h6 class="text-primary">आवेदन पत्र / Cover Letter</h6>
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($viewApplication['cover_letter'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Documents -->
                            <div class="mb-4">
                                <h6 class="text-primary">कागजातहरू</h6>
                                <div class="row">
                                    <?php if (!empty($viewApplication['resume_path'])): ?>
                                    <div class="col-md-3 col-6 mb-2">
                                        <a href="<?php echo SITE_URL . $viewApplication['resume_path']; ?>" class="btn btn-outline-primary btn-sm w-100" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Resume/CV
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($viewApplication['photo_path'])): ?>
                                    <div class="col-md-3 col-6 mb-2">
                                        <a href="<?php echo SITE_URL . $viewApplication['photo_path']; ?>" class="btn btn-outline-primary btn-sm w-100" target="_blank">
                                            <i class="fas fa-image"></i> फोटो
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($viewApplication['citizenship_path'])): ?>
                                    <div class="col-md-3 col-6 mb-2">
                                        <a href="<?php echo SITE_URL . $viewApplication['citizenship_path']; ?>" class="btn btn-outline-primary btn-sm w-100" target="_blank">
                                            <i class="fas fa-id-card"></i> नागरिकता
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($viewApplication['certificates_path'])): ?>
                                    <div class="col-md-3 col-6 mb-2">
                                        <a href="<?php echo SITE_URL . $viewApplication['certificates_path']; ?>" class="btn btn-outline-primary btn-sm w-100" target="_blank">
                                            <i class="fas fa-certificate"></i> प्रमाणपत्र
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Applied For -->
                            <div class="card bg-light mb-4">
                                <div class="card-body">
                                    <h6 class="text-primary mb-3">आवेदित पद</h6>
                                    <h5><?php echo htmlspecialchars($viewApplication['job_title']); ?></h5>
                                    <p class="mb-1"><i class="fas fa-building"></i> <?php echo $viewApplication['department'] ?? 'General'; ?></p>
                                    <p class="mb-0"><i class="fas fa-calendar"></i> म्याद: <?php echo $viewApplication['deadline']; ?></p>
                                </div>
                            </div>

                            <!-- Update Status Form -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">स्थिति अपडेट</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
    <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id" value="<?php echo $viewApplication['id']; ?>">

                                        <div class="mb-3">
                                            <label class="form-label">स्थिति</label>
                                            <select name="status" class="form-select">
                                                <option value="pending" <?php echo $viewApplication['status'] === 'pending' ? 'selected' : ''; ?>>Pending (पेन्डिङ)</option>
                                                <option value="shortlisted" <?php echo $viewApplication['status'] === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted (छनोट)</option>
                                                <option value="interviewed" <?php echo $viewApplication['status'] === 'interviewed' ? 'selected' : ''; ?>>Interviewed (अन्तर्वार्ता)</option>
                                                <option value="selected" <?php echo $viewApplication['status'] === 'selected' ? 'selected' : ''; ?>>Selected (चयन)</option>
                                                <option value="rejected" <?php echo $viewApplication['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected (अस्वीकृत)</option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">नोटहरू</label>
                                            <textarea name="admin_notes" class="form-control" rows="3"><?php echo htmlspecialchars($viewApplication['admin_notes'] ?? ''); ?></textarea>
                                        </div>

                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-save"></i> अपडेट गर्नुहोस्
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- ── Applications Filter Bar ── -->
    <div class="adm-filter-bar no-print">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3 col-6">
                <label>पद</label>
                <select name="career_id" class="form-select form-select-sm" onchange="this.closest('form').submit()">
                    <option value="">सबै पदहरू</option>
                    <?php foreach ($careers as $career): ?>
                    <option value="<?php echo $career['id']; ?>" <?php echo $careerId==$career['id']?'selected':''; ?>><?php echo htmlspecialchars($career['title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label>स्थिति</label>
                <select name="status" class="form-select form-select-sm" onchange="this.closest('form').submit()">
                    <option value="">सबै स्थिति</option>
                    <option value="pending" <?php echo $statusFilter==='pending'?'selected':''; ?>>⏳ पेन्डिङ</option>
                    <option value="shortlisted" <?php echo $statusFilter==='shortlisted'?'selected':''; ?>>📋 छनोट</option>
                    <option value="interviewed" <?php echo $statusFilter==='interviewed'?'selected':''; ?>>💬 अन्तर्वार्ता</option>
                    <option value="selected" <?php echo $statusFilter==='selected'?'selected':''; ?>>✅ चयन</option>
                    <option value="rejected" <?php echo $statusFilter==='rejected'?'selected':''; ?>>❌ अस्वीकृत</option>
                </select>
            </div>
            <div class="col-md-5 col-12">
                <label>खोज्नुहोस्</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($jobSearch); ?>" placeholder="नाम, फोन, इमेल...">
                </div>
            </div>
            <div class="col-md-2 col-6">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i>खोज</button>
                <?php if ($careerId||$statusFilter||$jobSearch !== ''): ?><a href="job-applications.php" class="btn btn-outline-secondary btn-sm w-100 mt-1"><i class="fas fa-times me-1"></i>हटाउनुहोस्</a><?php endif; ?>
            </div>
        </form>
    </div>
    <!-- ── Applications List ── -->
    <div class="card border-0 shadow-sm" style="border-radius:10px;overflow:hidden;">
        <div class="tbl-header-bar no-print">
            <h6><i class="fas fa-briefcase me-2 text-primary"></i>रोजगार आवेदन सूची</h6>
            <span class="result-count-badge"><?php echo count($applications); ?> आवेदन</span>
        </div>
        <div class="table-responsive">
            <table class="table-hover table app-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>आवेदक</th>
                                    <th>पद</th>
                                    <th>सम्पर्क</th>
                                    <th>आवेदन मिति</th>
                                    <th>स्थिति</th>
                                    <th>कार्य</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                <?php $isUnread = $hasIsRead ? ((int)($app['is_read'] ?? 0) === 0) : false; ?>
                                <tr class="<?php echo $isUnread ? 'table-warning' : ''; ?>">
                                    <td>
                                        <?php if ($isUnread): ?>
                                        <span class="badge bg-danger me-1">New</span>
                                        <?php endif; ?>
                                        <strong><?php echo htmlspecialchars($app['full_name']); ?></strong>
                                        <?php if ($app['education']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($app['education']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($app['job_title'] ?? 'N/A'); ?>
                                        <?php if ($app['deadline'] && strtotime($app['deadline']) < time()): ?>
                                        <br><small class="text-danger">म्याद समाप्त</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo $app['email']; ?>" title="Email">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                        <a href="tel:<?php echo $app['phone']; ?>" title="Phone" class="ms-2">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                        <br><small><?php echo $app['phone']; ?></small>
                                    </td>
                                    <td><?php echo formatNepaliDate($app['created_at']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo match($app['status']) {
                                                'pending' => 'warning',
                                                'shortlisted' => 'info',
                                                'interviewed' => 'secondary',
                                                'selected' => 'success',
                                                'rejected' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?view=<?php echo $app['id']; ?><?php echo $careerId ? '&career_id=' . $careerId : ''; ?>" class="btn btn-sm btn-info" title="हेर्नुहोस्">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form method="POST" style="display:inline" onsubmit="return confirm('के तपाईं पक्का हुनुहुन्छ?')">
    <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="मेट्नुहोस्">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                        कुनै आवेदन छैन
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
