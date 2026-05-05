<?php
/**
 * Admin: Member Portal Management
 * - Member list, details, direct notification send
 */
$pageTitle   = 'Member Portal व्यवस्थापन';
$currentPage = 'members';
require_once 'includes/admin-header.php';
require_once '../includes/member-auth.php';
require_once __DIR__ . '/../includes/auth-roles.php';
/* RBAC: staff hercha matra; mutate admin+ matra */
if ($_SERVER['REQUEST_METHOD'] === 'POST') require_role('admin');

/* ── Ensure tables ── */
ensureMemberTables();

/* ── Send Notification ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notif'])) {
    checkCSRF();
    $memberId = (int)($_POST['member_id'] ?? 0);
    $title    = clean_text($_POST['notif_title']   ?? '');
    $message  = clean_text($_POST['notif_message'] ?? '');
    $type     = in_array($_POST['notif_type'] ?? '', ['info','success','warning','error']) ? $_POST['notif_type'] : 'info';

    if ($memberId && $title) {
        createMemberNotification($memberId, $title, $message, $type, SITE_URL . 'member/notifications.php');
        setFlash('success', 'Notification सफलतापूर्वक पठाइयो!');
    } else {
        setFlash('error', 'Title राख्नुहोस्।');
    }
    redirect('members.php' . ($memberId ? '?view=' . $memberId : ''));
}

/* ── Toggle active/inactive ── */
if (isset($_POST['toggle_active'])) {
    checkCSRF();
    $mid = (int)$_POST['member_id'];
    $db->prepare("UPDATE members SET is_active = 1 - is_active WHERE id=?")->execute([$mid]);
    setFlash('success', 'Member status बदलियो।');
    redirect('members.php');
}

/* ── View single member ── */
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewMember = null;
$viewApps   = [];
$viewNotifs = [];
$viewCard   = null; /* Issue #3: card details (CVV / VCode / expiry) */
if ($viewId) {
    $st = $db->prepare("SELECT * FROM members WHERE id=?");
    $st->execute([$viewId]);
    $viewMember = $st->fetch(PDO::FETCH_ASSOC);
    if ($viewMember) {
        $viewApps   = getMemberApplications($viewMember['email'] ?? '', $viewMember['phone'] ?? '', 30);
        $nst = $db->prepare("SELECT * FROM member_notifications WHERE member_id=? ORDER BY created_at DESC LIMIT 20");
        $nst->execute([$viewId]);
        $viewNotifs = $nst->fetchAll(PDO::FETCH_ASSOC);

        /* Issue #3: load active ID card for CVV / verification code display */
        try {
            $cs = $db->prepare(
                "SELECT card_no, verification_code, cvv, issued_date, status
                   FROM member_id_cards
                  WHERE (member_id = :id OR member_id = :sid)
                  ORDER BY id DESC LIMIT 1"
            );
            $cs->execute([
                ':id'  => (string)$viewMember['id'],
                ':sid' => (string)($viewMember['sadasyata_number'] ?? ''),
            ]);
            $viewCard = $cs->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { /* table may not exist on legacy installs */ }
    }
}

/* ── Member list ── */
$search = mb_substr(trim((string)($_GET['search'] ?? '')), 0, 200, 'UTF-8');
$kycFilter = trim((string)($_GET['kyc'] ?? 'all'));
if (!in_array($kycFilter, ['all', 'linked', 'unlinked'], true)) {
    $kycFilter = 'all';
}
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where = '1=1'; $params = [];
if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR member_card_no LIKE ?)";
    $t = "%$search%"; $params = [$t,$t,$t,$t];
}
if ($kycFilter === 'linked') {
    $where .= " AND kyc_application_id IS NOT NULL";
} elseif ($kycFilter === 'unlinked') {
    $where .= " AND (kyc_application_id IS NULL OR kyc_application_id = 0)";
}

$total = $db->prepare("SELECT COUNT(*) FROM members WHERE $where");
$total->execute($params);
$totalCount = (int)$total->fetchColumn();

$members = $db->prepare("SELECT * FROM members WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$members->execute($params);
$members = $members->fetchAll(PDO::FETCH_ASSOC);

$totalPages = max(1, ceil($totalCount / $limit));

/* Stats */
$stats = [];
try {
    $stats['total']    = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
    $stats['active']   = $db->query("SELECT COUNT(*) FROM members WHERE is_active=1")->fetchColumn();
    $stats['pending']  = $db->query("SELECT COUNT(*) FROM members WHERE approval_status='pending'")->fetchColumn();
    $stats['renewal']  = (int)$db->query("SELECT COUNT(*) FROM members WHERE approval_status='renewal_pending'")->fetchColumn();
    $stats['kyc_linked'] = (int)$db->query("SELECT COUNT(*) FROM members WHERE kyc_application_id IS NOT NULL")->fetchColumn();
    $stats['google']   = $db->query("SELECT COUNT(*) FROM members WHERE google_id IS NOT NULL AND google_id!=''")->fetchColumn();
    $stats['facebook'] = $db->query("SELECT COUNT(*) FROM members WHERE facebook_id IS NOT NULL AND facebook_id!=''")->fetchColumn();
} catch (Exception $e) { $stats = ['total'=>0,'active'=>0,'pending'=>0,'renewal'=>0,'kyc_linked'=>0,'google'=>0,'facebook'=>0]; }
?>

<div class="container-fluid py-3">
<?php echo adminHelpTip('यो पृष्ठबाट संस्थाका सदस्यहरूको सूची र स्थिति देख्न सकिन्छ।', ['Pending सदस्य approve गर्न: "Approve" बटन थिच्नुहोस्।', 'सदस्य खोज्न: माथिको Search box प्रयोग गर्नुहोस्।', 'KYC status हेर्न: सदस्यको नाममा क्लिक गर्नुहोस्।']); ?>

<?php if ($flash = getFlash()): ?>
<div class="alert alert-<?php echo $flash['type']==='success'?'success':'danger'; ?> alert-dismissible fade show mb-3"><i class="fas fa-<?php echo $flash['type']==='success'?'check-circle':'exclamation-circle'; ?> me-2"></i><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>


<?php displayFlash(); ?>

<?php if ($viewMember): /* ── Single Member View ── */ ?>

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="members.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>फिर्ता</a>
    <h4 class="mb-0">Member विवरण</h4>
</div>

<div class="row g-3">
    <!-- Member Info Card -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <?php if ($viewMember['avatar_url']): ?>
                <img src="<?php echo htmlspecialchars($viewMember['avatar_url']); ?>" class="rounded-circle mb-3" style="width:80px;height:80px;object-fit:cover;border:3px solid var(--primary-color);">
                <?php else: ?>
                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width:80px;height:80px;font-size:2rem;font-weight:700;">
                    <?php echo mb_substr($viewMember['name'],0,1); ?>
                </div>
                <?php endif; ?>
                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($viewMember['name']); ?></h5>
                <div class="text-muted small"><?php echo htmlspecialchars($viewMember['member_card_no'] ?? ''); ?></div>
                <div class="mt-2">
                    <?php if ($viewMember['google_id']): ?><span class="badge" style="background:#ea4335;"><i class="fab fa-google me-1"></i>Google</span><?php endif; ?>
                    <?php if ($viewMember['facebook_id']): ?><span class="badge" style="background:#1877f2;"><i class="fab fa-facebook me-1"></i>Facebook</span><?php endif; ?>
                    <?php if ($viewMember['password_hash']): ?><span class="badge bg-success">Email</span><?php endif; ?>
                </div>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold">इमेल</span>
                    <span class="small"><?php echo htmlspecialchars($viewMember['email'] ?? '—'); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold">मोबाइल</span>
                    <span class="small"><?php echo htmlspecialchars($viewMember['phone'] ?? '—'); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold">ठेगाना</span>
                    <span class="small"><?php echo htmlspecialchars($viewMember['address'] ?? '—'); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold">दर्ता</span>
                    <span class="small"><?php echo formatNepaliDate($viewMember['created_at']); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold">अवस्था</span>
                    <span class="badge <?php echo $viewMember['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo $viewMember['is_active'] ? 'सक्रिय' : 'निष्क्रिय'; ?>
                    </span>
                </li>
                <?php
                /* Issue #3: Card validity / expiry display */
                $cExp = $viewMember['card_expires_at'] ?? '';
                if ($cExp):
                    $cExpTs = strtotime($cExp);
                    $isExp  = $cExpTs && $cExpTs < time();
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold">Card म्याद</span>
                    <span class="badge <?php echo $isExp ? 'bg-danger' : 'bg-info'; ?>">
                        <?php echo date('Y-m-d', $cExpTs); ?>
                        <?php echo $isExp ? ' (Expired)' : ''; ?>
                    </span>
                </li>
                <?php endif; ?>
            </ul>

            <?php /* ── Issue #3: CVV / Verification Code admin panel ── */ ?>
            <?php if ($viewCard && (!empty($viewCard['cvv']) || !empty($viewCard['verification_code']))): ?>
            <div class="card-body border-top" style="background:linear-gradient(135deg,#fefce8,#fef9c3);">
                <div class="fw-bold small text-warning-emphasis mb-2">
                    <i class="fas fa-shield-halved"></i> ID Card गोप्य विवरण
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small">Card No.</span>
                    <code class="small"><?php echo htmlspecialchars($viewCard['card_no'] ?? '—'); ?></code>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small">Verification Code</span>
                    <code class="small text-success fw-bold"><?php echo htmlspecialchars($viewCard['verification_code'] ?? '—'); ?></code>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">CVV</span>
                    <code class="small text-danger fw-bold" style="letter-spacing:.2em;"><?php echo htmlspecialchars($viewCard['cvv'] ?? '—'); ?></code>
                </div>
                <div class="small text-muted mt-2" style="font-size:.7rem;">
                    ⚠ यो जानकारी members लाई मात्र देखिनुपर्छ — admin reference मात्र हो।
                </div>
            </div>
            <?php endif; ?>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="toggle_active" value="1">
                    <input type="hidden" name="member_id" value="<?php echo $viewMember['id']; ?>">
                    <button type="submit" class="btn btn-sm w-100 <?php echo $viewMember['is_active'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                            onclick="return confirm('Member status बदल्ने?')">
                        <i class="fas fa-<?php echo $viewMember['is_active'] ? 'ban' : 'check'; ?> me-1"></i>
                        <?php echo $viewMember['is_active'] ? 'निष्क्रिय गर्नुहोस्' : 'सक्रिय गर्नुहोस्'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <!-- Send Notification -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold text-success">
                <i class="fas fa-bell me-2"></i>Member लाई Notification पठाउनुहोस्
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="send_notif" value="1">
                    <input type="hidden" name="member_id" value="<?php echo $viewMember['id']; ?>">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <input type="text" name="notif_title" class="form-control" required
                                   placeholder="Notification शीर्षक" maxlength="200">
                        </div>
                        <div class="col-md-4">
                            <select name="notif_type" class="form-select">
                                <option value="info">📘 सूचना</option>
                                <option value="success">✅ सफलता</option>
                                <option value="warning">⚠️ सतर्कता</option>
                                <option value="error">❌ अस्वीकृति</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <textarea name="notif_message" class="form-control" rows="2"
                                      placeholder="विस्तृत सन्देश (ऐच्छिक)"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fas fa-paper-plane me-1"></i>Notification पठाउनुहोस्
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabs: Applications | Notifications -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white p-0">
                <ul class="nav nav-tabs px-3 pt-2" id="memTabs">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabApps">
                        <i class="fas fa-file-alt me-1"></i>आवेदनहरू (<?php echo count($viewApps); ?>)
                    </a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabNotifs">
                        <i class="fas fa-bell me-1"></i>Notifications (<?php echo count($viewNotifs); ?>)
                    </a></li>
                </ul>
            </div>
            <div class="card-body tab-content p-0">
                <!-- Applications tab -->
                <div class="tab-pane fade show active p-3" id="tabApps">
                    <?php if (empty($viewApps)): ?>
                    <div class="text-center text-muted py-4"><i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>कुनै आवेदन छैन</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead><tr><th>सेवा</th><th>विवरण</th><th>अवस्था</th><th>मिति</th><th>Tracking</th></tr></thead>
                            <tbody>
                            <?php foreach ($viewApps as $app): ?>
                            <tr>
                                <td><span class="badge" style="background:<?php echo $app['service_color']; ?>;">
                                    <i class="fas <?php echo $app['service_icon']; ?> me-1"></i><?php echo $app['service_name']; ?>
                                </span></td>
                                <td class="small"><?php echo htmlspecialchars(mb_strimwidth($app['detail']??'', 0, 35, '…')); ?></td>
                                <td><?php echo memberStatusBadge($app['status']); ?></td>
                                <td class="small text-muted"><?php echo formatNepaliDate($app['created_at']); ?></td>
                                <td><code class="small"><?php echo htmlspecialchars($app['tracking_id'] ?? '—'); ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Notifications tab -->
                <div class="tab-pane fade p-3" id="tabNotifs">
                    <?php if (empty($viewNotifs)): ?>
                    <div class="text-center text-muted py-4"><i class="fas fa-bell-slash fa-2x mb-2 d-block opacity-25"></i>कुनै notification छैन</div>
                    <?php else: ?>
                    <?php
                    $icMap = ['success'=>'bg-success','error'=>'bg-danger','warning'=>'bg-warning','info'=>'bg-primary'];
                    foreach ($viewNotifs as $n): ?>
                    <div class="d-flex align-items-start gap-2 mb-3 pb-3 border-bottom">
                        <span class="badge rounded-pill <?php echo $icMap[$n['type']] ?? 'bg-secondary'; ?>" style="font-size:.7rem;padding:5px 7px;">
                            <i class="fas fa-bell"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="fw-bold small"><?php echo htmlspecialchars($n['title']); ?>
                                <?php if (!$n['is_read']): ?><span class="badge bg-warning text-dark ms-1" style="font-size:.55rem;">Unread</span><?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:.78rem;"><?php echo nl2br(htmlspecialchars($n['message'] ?? '')); ?></div>
                            <div class="text-muted" style="font-size:.68rem;"><?php echo formatNepaliDate($n['created_at'], true); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: /* ── Member List ── */ ?>

<!-- Pending approval banner -->
<?php if (!empty($stats['pending']) && $stats['pending'] > 0): ?>
<div class="alert alert-warning border-start border-warning border-4 d-flex align-items-center justify-content-between mb-3" role="alert">
    <div>
        <i class="fas fa-clock me-2"></i>
        <strong><?php echo $stats['pending']; ?> Member</strong> दर्ता अनुमोदन प्रतीक्षामा छ।
    </div>
    <a href="member-online-portal.php?status=pending" class="btn btn-warning btn-sm fw-bold">
        <i class="fas fa-check-circle me-1"></i>अनुमोदन गर्नुहोस् →
    </a>
</div>
<?php endif; ?>

<!-- Issue #3: Renewal-pending banner -->
<?php if (!empty($stats['renewal']) && $stats['renewal'] > 0): ?>
<div class="alert alert-info border-start border-info border-4 d-flex align-items-center justify-content-between mb-3" role="alert">
    <div>
        <i class="fas fa-rotate me-2"></i>
        <strong><?php echo $stats['renewal']; ?> Member</strong> को card म्याद सकिएको छ — renewal प्रतीक्षामा।
    </div>
    <a href="?search=" class="btn btn-info btn-sm fw-bold text-white">
        <i class="fas fa-list me-1"></i>हेर्नुहोस् →
    </a>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <?php $statItems = [
        ['icon'=>'fa-users','color'=>'success','val'=>$stats['total'],   'label'=>'कुल Members'],
        ['icon'=>'fa-user-check','color'=>'primary','val'=>$stats['active'],'label'=>'सक्रिय'],
        ['icon'=>'fa-clock','color'=>'warning','val'=>$stats['pending'] ?? 0,'label'=>'प्रतीक्षामा'],
        ['icon'=>'fa-rotate','color'=>'info','val'=>$stats['renewal'] ?? 0,'label'=>'Renewal Pending'],
        ['icon'=>'fa-link','color'=>'dark','val'=>$stats['kyc_linked'] ?? 0,'label'=>'KYC Linked'],
        ['icon'=>'fab fa-google','color'=>'danger','val'=>$stats['google'],'label'=>'Google Login'],
        ['icon'=>'fab fa-facebook','color'=>'primary','val'=>$stats['facebook'],'label'=>'Facebook Login'],
    ];
    foreach ($statItems as $s): ?>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3 stat-uniform-card">
            <i class="<?php echo $s['icon']; ?> fa-2x text-<?php echo $s['color']; ?> mb-2"></i>
            <div class="stat-value"><?php echo $s['val']; ?></div>
            <div class="stat-label"><?php echo $s['label']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Search + Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
        <h5 class="mb-0 fw-bold text-success"><i class="fas fa-users me-2"></i>Member Portal सदस्यहरू</h5>
        <form class="d-flex gap-2" method="GET">
            <select name="kyc" class="form-select form-select-sm" style="min-width:150px;">
                <option value="all" <?php echo $kycFilter==='all' ? 'selected' : ''; ?>>KYC: सबै</option>
                <option value="linked" <?php echo $kycFilter==='linked' ? 'selected' : ''; ?>>KYC Linked</option>
                <option value="unlinked" <?php echo $kycFilter==='unlinked' ? 'selected' : ''; ?>>KYC Unlinked</option>
            </select>
            <input type="text" name="search" class="form-control form-control-sm" placeholder="नाम / इमेल / फोन खोज्नुहोस्…"
                   value="<?php echo htmlspecialchars($search); ?>" style="min-width:220px;">
            <button class="btn btn-success btn-sm"><i class="fas fa-search"></i></button>
            <?php if ($search || $kycFilter !== 'all'): ?><a href="members.php" class="btn btn-outline-secondary btn-sm">Clear</a><?php endif; ?>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($members)): ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-user-slash fa-3x mb-3 opacity-25"></i>
            <div><?php echo $search ? "'{$search}' फेला परेन।" : 'अहिलेसम्म कुनै Member दर्ता भएको छैन।'; ?></div>
            <small class="text-muted mt-1 d-block">Member Portal मा Register गरेपछि यहाँ देखिन्छ।</small>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr>
                    <th>#</th><th>Member</th><th>सदस्यता नं</th><th>Contact</th><th>Card No.</th>
                    <th>Login विधि</th><th>दर्ता</th><th>अवस्था</th><th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($members as $i => $m): ?>
                <tr>
                    <td class="text-muted small"><?php echo $offset + $i + 1; ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($m['avatar_url']): ?>
                            <img src="<?php echo htmlspecialchars($m['avatar_url']); ?>" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;">
                            <?php else: ?>
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold" style="width:32px;height:32px;font-size:.9rem;">
                                <?php echo mb_substr($m['name'],0,1); ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-bold small"><?php echo htmlspecialchars($m['name']); ?></div>
                                <div class="text-muted" style="font-size:.7rem;"><?php echo htmlspecialchars($m['email'] ?? '—'); ?></div>
                                <div style="margin-top:2px;">
                                    <?php if (!empty($m['kyc_application_id'])): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.58rem;">KYC Linked</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border" style="font-size:.58rem;">KYC Unlinked</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="small"><code><?php echo htmlspecialchars($m['sadasyata_number'] ?? '—'); ?></code></td>
                    <td class="small"><?php echo htmlspecialchars($m['phone'] ?? '—'); ?></td>
                    <td><code class="small"><?php echo htmlspecialchars($m['member_card_no'] ?? ''); ?></code></td>
                    <td>
                        <?php if ($m['google_id']): ?><span class="badge" style="background:#ea4335;font-size:.65rem;"><i class="fab fa-google me-1"></i>G</span><?php endif; ?>
                        <?php if ($m['facebook_id']): ?><span class="badge" style="background:#1877f2;font-size:.65rem;"><i class="fab fa-facebook me-1"></i>FB</span><?php endif; ?>
                        <?php if ($m['password_hash']): ?><span class="badge bg-success" style="font-size:.65rem;">Email</span><?php endif; ?>
                    </td>
                    <td class="small text-muted"><?php echo formatNepaliDate($m['created_at']); ?></td>
                    <td>
                        <span class="badge <?php echo $m['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $m['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                        <?php
                        $as = $m['approval_status'] ?? 'pending';
                        $asBadge = ['pending'=>'bg-warning text-dark','approved'=>'bg-success','rejected'=>'bg-danger','renewal_pending'=>'bg-info text-dark'];
                        $asLabel = ['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','renewal_pending'=>'🔄 Renewal'];
                        $bClass  = $asBadge[$as] ?? 'bg-secondary';
                        $bLabel  = $asLabel[$as] ?? $as;
                        echo "<br><span class='badge $bClass' style='font-size:.6rem;margin-top:2px;'>$bLabel</span>";
                        ?>
                    </td>
                    <td>
                        <a href="member-online-portal.php?view=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-success" title="हेर्नुहोस्">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if ($as === 'pending' || $as === 'renewal_pending'): ?>
                        <a href="member-online-portal.php?status=<?php echo $as === 'renewal_pending' ? 'renewal_pending' : 'pending'; ?>" class="btn btn-sm btn-warning" title="अनुमोदन">
                            <i class="fas fa-check"></i>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <div class="text-muted small">जम्मा <?php echo $totalCount; ?> मध्ये <?php echo min($offset+$limit, $totalCount); ?> देखाइएको</div>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($pg = 1; $pg <= $totalPages; $pg++): ?>
                <li class="page-item <?php echo $pg === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $pg; ?>&search=<?php echo urlencode($search); ?>&kyc=<?php echo urlencode($kycFilter); ?>"><?php echo $pg; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
</div>

<?php require_once 'includes/admin-footer.php'; ?>
