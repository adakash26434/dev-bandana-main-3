<?php
/**
 * Member Portal — Dashboard
 * v6: Programs/Charts हटाइयो, Partner history UI सुधारियो
 */
require_once __DIR__ . '/_bootstrap.php';
requireMemberLogin();
memberSecurityHeaders();

if (!isset($db) || !$db) {
    $db = function_exists('getDB') ? getDB() : null;
}
if (!$db) {
    header('Location: login.php?msg=db_unavailable'); exit;
}

$mem      = currentMember();
if (!$mem) { header('Location: login.php?msg=session_expired'); exit; }
$memberId = $mem['id'];
$memName  = trim((string)($mem['name'] ?? ''));
$memEmail = trim((string)($mem['email'] ?? ''));
$memPhone = trim((string)($mem['phone'] ?? ''));
$memAvatar = $mem['avatar_url'] ?? '';

/* KYC-linked profile priority */
$kycRow = null;
try {
    $kycMemberLinkId = (int)($mem['kyc_application_id'] ?? 0);
    if ($kycMemberLinkId > 0) {
        $ks = $db->prepare("SELECT id, full_name, email, mobile, photo FROM kyc_applications WHERE id=? LIMIT 1");
        $ks->execute([$kycMemberLinkId]);
        $kycRow = $ks->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    if (!$kycRow) {
        $kw = []; $kp = [];
        if ($memEmail !== '') { $kw[] = 'LOWER(email)=?'; $kp[] = strtolower($memEmail); }
        if ($memPhone !== '') { $kw[] = 'mobile=?'; $kp[] = preg_replace('/[^0-9]/', '', $memPhone); }
        if (!empty($kw)) {
            $ks = $db->prepare("SELECT id, full_name, email, mobile, photo FROM kyc_applications WHERE (" . implode(' OR ', $kw) . ") ORDER BY id DESC LIMIT 1");
            $ks->execute($kp);
            $kycRow = $ks->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($kycRow && empty($mem['kyc_application_id'])) {
                $db->prepare("UPDATE members SET kyc_application_id=? WHERE id=?")->execute([(int)$kycRow['id'], $memberId]);
                $mem['kyc_application_id'] = (int)$kycRow['id'];
            }
        }
    }
} catch (Throwable $e) { $kycRow = null; }
if ($kycRow) {
    $memName  = trim((string)($kycRow['full_name'] ?? '')) !== '' ? trim((string)$kycRow['full_name']) : $memName;
    $memEmail = trim((string)($kycRow['email']     ?? '')) !== '' ? trim((string)$kycRow['email'])     : $memEmail;
    $memPhone = trim((string)($kycRow['mobile']    ?? '')) !== '' ? trim((string)$kycRow['mobile'])    : $memPhone;
    if (!empty($kycRow['photo'])) $memAvatar = trim((string)$kycRow['photo']);
}

/* Applications */
$apps      = getMemberApplications($memEmail, $memPhone, 200);
$totalApps = count($apps);
$pending   = count(array_filter($apps, fn($a) => $a['status'] === 'pending'));
$approved  = count(array_filter($apps, fn($a) => in_array($a['status'], ['approved','completed','resolved'])));
$raStatus  = $_GET['ra_status'] ?? 'all';
if (!in_array($raStatus, ['all','pending','approved','rejected'], true)) $raStatus = 'all';
$raQ = mb_substr(trim((string)($_GET['ra_q'] ?? '')), 0, 120);
$recentFiltered = $apps;
if ($raStatus !== 'all') {
    $recentFiltered = array_values(array_filter($recentFiltered, function($a) use ($raStatus) {
        $st = (string)($a['status'] ?? '');
        if ($raStatus === 'pending')  return in_array($st, ['pending','under_review','processing'], true);
        if ($raStatus === 'approved') return in_array($st, ['approved','completed','resolved'], true);
        if ($raStatus === 'rejected') return $st === 'rejected';
        return true;
    }));
}
if ($raQ !== '') {
    $qLower = mb_strtolower($raQ);
    $recentFiltered = array_values(array_filter($recentFiltered, function($a) use ($qLower) {
        $hay = mb_strtolower(($a['service_name']??'').' '.($a['detail']??'').' '.($a['tracking_id']??'').' '.($a['status']??''));
        return strpos($hay, $qLower) !== false;
    }));
}
$recentApps = array_slice($recentFiltered, 0, 5);

/* Notifications */
$unread  = getMemberUnreadCount($memberId);
$notifSt = $db->prepare("SELECT * FROM member_notifications WHERE member_id=? ORDER BY created_at DESC LIMIT 5");
$notifSt->execute([$memberId]);
$notifs  = $notifSt->fetchAll(PDO::FETCH_ASSOC);

/* ── Partner service history ── */
$partnerHistory = [];
try {
    $ph = $db->prepare("SELECT partner_name, service_name, service_taken, service_note, created_at
                        FROM member_partner_services
                        WHERE member_id = ?
                        ORDER BY created_at DESC
                        LIMIT 50");
    $ph->execute([$memberId]);
    $partnerHistory = $ph->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $partnerHistory = []; }

/* Per-partner summary (name => count taken) */
$partnerSummary = [];
foreach ($partnerHistory as $h) {
    $pn = trim((string)($h['partner_name'] ?? ''));
    if ($pn === '') continue;
    if (!isset($partnerSummary[$pn])) $partnerSummary[$pn] = ['total' => 0, 'taken' => 0];
    $partnerSummary[$pn]['total']++;
    if (!empty($h['service_taken'])) $partnerSummary[$pn]['taken']++;
}
arsort($partnerSummary); // most-used first

$welcome  = $_GET['welcome'] ?? '';
if (!in_array($welcome, ['google','facebook'], true)) $welcome = '';

$siteName = getSetting('site_name', 'आकाश सहकारी');
$logoPath = function_exists('getLocalizedLogoPath')
    ? trim((string) getLocalizedLogoPath('assets/images/logo.png'))
    : trim((string) getSetting('site_logo', getSetting('logo', 'assets/images/logo.png')));
$siteUrl  = SITE_URL;
$_t = static function (string $np, string $en): string {
    return isEnglish() ? $en : $np;
};

$hour = (int)date('H');
$greeting = $hour < 12 ? $_t('शुभ बिहान', 'Good Morning') : ($hour < 17 ? $_t('शुभ दिन', 'Good Afternoon') : $_t('शुभ सन्ध्या', 'Good Evening'));

/* सबै Quick Apply → /member/ भित्र (कल्याण = native welfare.php, बाँकी = apply-frame) */
$quickActions = [
    ['href' => $siteUrl.'member/apply-frame.php?p=appointment', 'icon' => 'fa-calendar-check',     'color' => 'var(--primary-color)', 'label' => $_t('भेटघाट', 'Appointment')],
    ['href' => $siteUrl.'member/kyc.php',                        'icon' => 'fa-id-card',             'color' => 'var(--secondary-color,#c0392b)', 'label' => $_t('KYC दर्ता', 'KYC Registration')],
    ['href' => $siteUrl.'member/apply-frame.php?p=loan',        'icon' => 'fa-hand-holding-usd',    'color' => 'var(--secondary-dark,#922b21)', 'label' => $_t('ऋण आवेदन', 'Loan Apply')],
    ['href' => $siteUrl.'member/apply-frame.php?p=account',     'icon' => 'fa-university',          'color' => '#00695c', 'label' => $_t('खाता खोल्ने', 'Open Account')],
    ['href' => $siteUrl.'member/apply-frame.php?p=digital',     'icon' => 'fa-laptop',              'color' => 'var(--secondary-color,#c0392b)', 'label' => $_t('डिजिटल सेवा', 'Digital Service')],
    ['href' => $siteUrl.'member/apply-frame.php?p=grievance',   'icon' => 'fa-comment-exclamation', 'color' => '#c62828', 'label' => $_t('गुनासो', 'Grievance')],
    ['href' => $siteUrl.'member/welfare.php',                   'icon' => 'fa-heart',               'color' => '#e65100', 'label' => $_t('कल्याण', 'Welfare')],
    ['href' => $siteUrl.'member/apply-frame.php?p=career',      'icon' => 'fa-briefcase',           'color' => '#37474f', 'label' => $_t('जागिर', 'Career')],
    ['href' => $siteUrl.'member/apply-frame.php?p=emi',         'icon' => 'fa-calculator',          'color' => '#0277bd', 'label' => 'EMI Calculator'],
];
$pageTitle = $_t('सदस्य ड्यासबोर्ड', 'Member Dashboard') . ' — ' . $siteName;
require __DIR__ . '/includes/chrome.php';
?>
<?php if ($welcome): ?>
<div class="mem-alert mem-alert-success" style="margin-bottom:16px;">
    <i class="fas fa-party-horn"></i>
    <?php echo $welcome === 'google' ? 'Google बाट ' : ($welcome === 'facebook' ? 'Facebook बाट ' : ''); ?>
    <?php echo $_t('स्वागत छ,', 'Welcome,'); ?> <strong><?php echo htmlspecialchars($memName); ?></strong>!
</div>
<?php endif; ?>

    <!-- Greeting -->
    <div class="mem-greeting" style="margin-bottom:16px;">
        <h2 style="margin:0;color:var(--primary-color);"><?php echo $greeting; ?>, <?php echo htmlspecialchars($memName); ?>! 👋</h2>
        <p style="margin:4px 0 0;color:#6b7280;font-size:0.88rem;"><?php echo $_t('आजको मिति', 'Today'); ?>: <?php echo formatNepaliDate(date('Y-m-d')); ?></p>
    </div>

    <!-- Stat cards (Programs हटाइयो) -->
    <div class="mem-stats">
        <div class="mem-stat">
            <div class="mem-stat-icon" style="color:var(--primary-color);">📋</div>
            <div class="mem-stat-num"  style="color:var(--primary-color);"><?php echo $totalApps; ?></div>
            <div class="mem-stat-label"><?php echo $_t('कुल आवेदन', 'Total Applications'); ?></div>
        </div>
        <div class="mem-stat">
            <div class="mem-stat-icon" style="color:#d97706;">⏳</div>
            <div class="mem-stat-num"  style="color:#d97706;"><?php echo $pending; ?></div>
            <div class="mem-stat-label"><?php echo $_t('पेन्डिङ', 'Pending'); ?></div>
        </div>
        <div class="mem-stat">
            <div class="mem-stat-icon" style="color:#16a34a;">✅</div>
            <div class="mem-stat-num"  style="color:#16a34a;"><?php echo $approved; ?></div>
            <div class="mem-stat-label"><?php echo $_t('स्वीकृत', 'Approved'); ?></div>
        </div>
        <div class="mem-stat">
            <div class="mem-stat-icon" style="color:var(--secondary-color,#c0392b);">🔔</div>
            <div class="mem-stat-num"  style="color:var(--secondary-color,#c0392b);"><?php echo $unread; ?></div>
            <div class="mem-stat-label"><?php echo $_t('नयाँ सूचना', 'New Notifications'); ?></div>
        </div>
        <div class="mem-stat">
            <div class="mem-stat-icon" style="color:#0d9488;">🏥</div>
            <div class="mem-stat-num"  style="color:#0d9488;"><?php echo count($partnerHistory); ?></div>
            <div class="mem-stat-label"><?php echo $_t('साझेदार सेवा', 'Partner Services'); ?></div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mem-card">
        <div class="mem-card-header">
            <div class="mem-card-title"><i class="fas fa-bolt"></i><?php echo $_t('छिटो आवेदन — सेवाहरू', 'Quick Apply — Services'); ?></div>
        </div>
        <div class="mem-card-body">
            <div class="mem-actions">
                <?php foreach ($quickActions as $qa): ?>
                <a href="<?php echo htmlspecialchars($qa['href']); ?>" class="mem-action-btn">
                    <div class="mem-action-icon" style="background:<?php echo $qa['color']; ?>;"><i class="fas <?php echo $qa['icon']; ?>"></i></div>
                    <?php echo $qa['label']; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Digital Services -->
    <div class="mem-card" style="margin-bottom:18px;">
        <div class="mem-card-header">
            <div class="mem-card-title"><i class="fas fa-laptop-code"></i><?php echo $_t('डिजिटल सेवाहरू', 'Digital Services'); ?></div>
        </div>
        <div class="mem-card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px;">
                <?php
                $ibUrl  = getSetting('internet_banking_url', '');
                $iosUrl = getSetting('app_store_url', '');
                $andUrl = getSetting('play_store_url', '');
                $digitalServices = [];
                if ($ibUrl)  $digitalServices[] = ['icon'=>'fa-laptop',      'color'=>'#0e7490','bg'=>'#ecfeff','label'=>'Internet Banking','href'=>$ibUrl, 'desc'=>$_t('Online खाता व्यवस्थापन','Online account management'),'target'=>'_blank'];
                if ($iosUrl) $digitalServices[] = ['icon'=>'fa-apple','iconLib'=>'fab','color'=>'#111827','bg'=>'#f3f4f6','label'=>'iOS App','href'=>$iosUrl,'desc'=>$_t('App Store बाट डाउनलोड','Download from App Store'),'target'=>'_blank'];
                if ($andUrl) $digitalServices[] = ['icon'=>'fa-google-play','iconLib'=>'fab','color'=>'#16a34a','bg'=>'#f0fdf4','label'=>'Android App','href'=>$andUrl,'desc'=>$_t('Play Store बाट डाउनलोड','Download from Play Store'),'target'=>'_blank'];
                $digitalServices = array_merge($digitalServices, [
                    ['icon'=>'fa-mobile-screen-button','color'=>'var(--secondary-color,#c0392b)','bg'=>'#fef2f2','label'=>'Mobile Banking',   'href'=>$siteUrl.'digital-services.php#mobile-banking','desc'=>$_t('कुनै पनि समय बैंकिङ','Anytime banking')],
                    ['icon'=>'fa-qrcode',              'color'=>'var(--secondary-dark,#922b21)','bg'=>'#fef2f2','label'=>'QR Payment',       'href'=>$siteUrl.'digital-services.php#qr-payment',   'desc'=>$_t('छिटो भुक्तानी','Quick payment')],
                    ['icon'=>'fa-file-invoice-dollar', 'color'=>'#059669','bg'=>'#ecfdf5','label'=>'Online Loan',       'href'=>$siteUrl.'member/apply-frame.php?p=loan',     'desc'=>$_t('घरबाटै ऋण आवेदन','Apply loan from home')],
                    ['icon'=>'fa-piggy-bank',          'color'=>'#d97706','bg'=>'#fffbeb','label'=>'Online Bachat',     'href'=>$siteUrl.'digital-services.php#bachat',       'desc'=>$_t('बचत खाता Online','Online savings account')],
                    ['icon'=>'fa-headset',             'color'=>'#c0392b','bg'=>'#fef2f2','label'=>'24/7 Support',      'href'=>$siteUrl.'digital-services.php#support',      'desc'=>$_t('सहायता केन्द्र','Support center')],
                    ['icon'=>'fa-id-card',             'color'=>'var(--primary-color)','bg'=>'#f0fdf4','label'=>'Digital ID Card',   'href'=>$siteUrl.'member/id-card.php',
                     'desc'=>$mem['id_card_generated'] ? $_t('ID Card हेर्नुहोस्','View ID card') : $_t('Admin Generate गर्दैछन्','Pending admin generation')],
                    ['icon'=>'fa-calculator',          'color'=>'#0277bd','bg'=>'#e1f5fe','label'=>'EMI Calculator',    'href'=>$siteUrl.'member/apply-frame.php?p=emi',     'desc'=>$_t('किस्ता गणना','Installment calculation')],
                ]);
                foreach ($digitalServices as $ds): ?>
                <a href="<?php echo htmlspecialchars($ds['href']); ?>"
                   <?php if (!empty($ds['target'])): ?>target="<?php echo $ds['target']; ?>" rel="noopener"<?php endif; ?>
                   style="background:<?php echo $ds['bg']; ?>;border-radius:12px;padding:14px 12px;text-decoration:none;display:flex;flex-direction:column;align-items:center;text-align:center;gap:8px;transition:transform .15s,box-shadow .15s;"
                   onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 14px rgba(0,0,0,.1)';"
                   onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div style="width:44px;height:44px;border-radius:50%;background:<?php echo $ds['color']; ?>;display:flex;align-items:center;justify-content:center;">
                        <i class="<?php echo $ds['iconLib'] ?? 'fas'; ?> <?php echo $ds['icon']; ?>" style="color:#fff;font-size:1.1rem;"></i>
                    </div>
                    <div style="font-size:.8rem;font-weight:700;color:#1f2937;"><?php echo $ds['label']; ?></div>
                    <div style="font-size:.68rem;color:#6b7280;"><?php echo $ds['desc']; ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Two-column: Recent apps + Notifications -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;" class="mem-grid-2">

        <div class="mem-card">
            <div class="mem-card-header">
                <div class="mem-card-title"><i class="fas fa-clock-rotate-left"></i><?php echo $_t('हालका आवेदनहरू', 'Recent Applications'); ?></div>
                <a href="<?php echo $siteUrl; ?>member/tracker.php" style="font-size:0.78rem;color:var(--mem-primary);font-weight:700;text-decoration:none;"><?php echo $_t('सबै हेर्नुस्', 'View all'); ?> →</a>
            </div>
            <div class="mem-card-body" style="padding-top:6px;">
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
                    <?php $raFilters = ['all'=>$_t('सबै','All'),'pending'=>$_t('पेन्डिङ','Pending'),'approved'=>$_t('स्वीकृत','Approved'),'rejected'=>$_t('अस्वीकृत','Rejected')]; ?>
                    <?php foreach ($raFilters as $rk => $rl): ?>
                    <a href="?ra_status=<?php echo urlencode($rk); ?>&ra_q=<?php echo urlencode($raQ); ?>"
                       style="padding:4px 10px;border-radius:18px;font-size:.7rem;font-weight:700;text-decoration:none;border:1.5px solid <?php echo $raStatus===$rk ? 'var(--mem-primary)' : '#e5e7eb'; ?>;background:<?php echo $raStatus===$rk ? 'var(--mem-primary)' : '#fff'; ?>;color:<?php echo $raStatus===$rk ? '#fff' : '#6b7280'; ?>;">
                        <?php echo $rl; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <form method="GET" style="display:flex;gap:6px;align-items:center;margin-bottom:10px;">
                    <input type="hidden" name="ra_status" value="<?php echo htmlspecialchars($raStatus); ?>">
                    <input type="text"  name="ra_q"      value="<?php echo htmlspecialchars($raQ); ?>"
                           placeholder="<?php echo $_t('सेवा वा Tracking ID खोज्नुहोस्...', 'Search service or tracking ID...'); ?>"
                           style="flex:1;min-width:0;border:1px solid #d1d5db;border-radius:8px;padding:6px 9px;font-size:.76rem;">
                    <button type="submit" style="padding:6px 10px;border:none;border-radius:8px;background:var(--mem-primary);color:#fff;font-size:.74rem;font-weight:700;"><i class="fas fa-search"></i></button>
                    <?php if ($raQ !== '' || $raStatus !== 'all'): ?>
                    <a href="<?php echo $siteUrl; ?>member/" style="padding:6px 10px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#6b7280;font-size:.74rem;font-weight:700;text-decoration:none;"><?php echo $_t('रिसेट','Reset'); ?></a>
                    <?php endif; ?>
                </form>
                <?php if (empty($recentApps)): ?>
                <div class="mem-empty">
                    <span class="mem-empty-icon">📭</span>
                    <div><?php echo $_t('अहिलेसम्म कुनै आवेदन छैन।', 'No applications yet.'); ?></div>
                    <div style="margin-top:8px;font-size:0.78rem;"><?php echo $_t('माथि Quick Apply बाट सेवा लिनुहोस्।', 'Use Quick Apply above to request services.'); ?></div>
                </div>
                <?php else: foreach ($recentApps as $app): ?>
                <div class="mem-app-item">
                    <div class="mem-app-icon" style="background:<?php echo $app['service_color']; ?>;"><i class="fas <?php echo $app['service_icon']; ?>"></i></div>
                    <div class="mem-app-info">
                        <div class="mem-app-service" style="color:<?php echo $app['service_color']; ?>;"><?php echo htmlspecialchars($app['service_name']); ?></div>
                        <div class="mem-app-detail"><?php echo htmlspecialchars($app['detail'] ?: '—'); ?></div>
                        <div class="mem-app-date"><?php echo formatNepaliDate($app['created_at'], true); ?></div>
                    </div>
                    <div class="mem-app-right">
                        <?php echo memberStatusBadge($app['status']); ?>
                        <?php if ($app['tracking_id']): ?><span style="font-size:0.68rem;color:#6b7280;font-family:monospace;"><?php echo htmlspecialchars($app['tracking_id']); ?></span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="mem-card">
            <div class="mem-card-header">
                <div class="mem-card-title"><i class="fas fa-bell"></i><?php echo $_t('सूचनाहरू', 'Notifications'); ?>
                    <?php if ($unread > 0): ?><span class="mem-notif-dot" style="position:static;"><?php echo $unread; ?></span><?php endif; ?>
                </div>
                <a href="<?php echo $siteUrl; ?>member/notifications.php" style="font-size:0.78rem;color:var(--mem-primary);font-weight:700;text-decoration:none;"><?php echo $_t('सबै', 'All'); ?> →</a>
            </div>
            <div class="mem-card-body" style="padding-top:6px;">
                <?php if (empty($notifs)): ?>
                <div class="mem-empty"><span class="mem-empty-icon">🔔</span><div><?php echo $_t('कुनै सूचना छैन।', 'No notifications.'); ?></div></div>
                <?php else:
                    $iconMap = ['success'=>['fas fa-circle-check','#16a34a','#f0fdf4'],'error'=>['fas fa-circle-xmark','#dc2626','#fef2f2'],'warning'=>['fas fa-triangle-exclamation','#d97706','#fffbeb'],'info'=>['fas fa-circle-info','var(--secondary-color,#c0392b)','#fef2f2']];
                    foreach ($notifs as $n): $ic = $iconMap[$n['type']] ?? $iconMap['info'];
                ?>
                <div class="mem-notif-item <?php echo !$n['is_read'] ? 'unread' : ''; ?>" onclick="markRead(<?php echo $n['id']; ?>, this)" style="border-radius:8px;">
                    <div class="mem-notif-dot-icon" style="background:<?php echo $ic[2]; ?>;color:<?php echo $ic[1]; ?>;"><i class="<?php echo $ic[0]; ?>"></i></div>
                    <div style="flex:1;min-width:0;">
                        <div class="mem-notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                        <div class="mem-notif-msg"><?php echo htmlspecialchars(mb_strimwidth($n['message'] ?? '', 0, 80, '…')); ?></div>
                        <div class="mem-notif-time"><?php echo formatNepaliDate($n['created_at'], true); ?></div>
                    </div>
                    <?php if (!$n['is_read']): ?><span style="width:8px;height:8px;border-radius:50%;background:var(--mem-accent);flex-shrink:0;margin-top:6px;"></span><?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

    </div>

    <!-- ══════════════════════════════════════════════════════
         साझेदार संस्था सेवा इतिहास — v6 redesigned
    ═══════════════════════════════════════════════════════════ -->
    <div class="mem-card" style="margin-top:18px;">
        <div class="mem-card-header">
            <div class="mem-card-title"><i class="fas fa-hospital"></i><?php echo $_t('साझेदार संस्था सेवा इतिहास', 'Partner Service History'); ?></div>
            <?php if (!empty($partnerHistory)): ?>
            <span style="font-size:.75rem;color:#6b7280;font-weight:600;"><?php echo count($partnerHistory); ?> <?php echo $_t('रेकर्ड', 'records'); ?></span>
            <?php endif; ?>
        </div>
        <div class="mem-card-body" style="padding-top:8px;">
            <?php if (empty($partnerHistory)): ?>
            <div class="mem-empty">
                <span class="mem-empty-icon">🏥</span>
                <div><?php echo $_t('अहिलेसम्म कुनै साझेदार संस्थामा सेवा लिइएको छैन।', 'No partner services taken yet.'); ?></div>
                <div style="margin-top:6px;font-size:.78rem;color:#9ca3af;">
                    <?php echo $_t('साझेदार संस्थामा Member Card देखाएपछि यहाँ history देखिन्छ।', 'History appears here after showing your Member Card at partner organizations.'); ?>
                </div>
            </div>
            <?php else:
                $totalTaken   = count(array_filter($partnerHistory, fn($h) => !empty($h['service_taken'])));
                $totalOrgs    = count($partnerSummary);
            ?>
            <!-- Summary bar -->
            <div class="ph-total-bar">
                <div class="ph-total-stat">
                    <div class="ph-total-num"><?php echo count($partnerHistory); ?></div>
                    <div class="ph-total-lbl"><?php echo $_t('कुल भेट', 'Total Visits'); ?></div>
                </div>
                <div class="ph-divider"></div>
                <div class="ph-total-stat">
                    <div class="ph-total-num" style="color:#16a34a;"><?php echo $totalTaken; ?></div>
                    <div class="ph-total-lbl"><?php echo $_t('सेवा लिइयो', 'Services Taken'); ?></div>
                </div>
                <div class="ph-divider"></div>
                <div class="ph-total-stat">
                    <div class="ph-total-num" style="color:#0d9488;"><?php echo $totalOrgs; ?></div>
                    <div class="ph-total-lbl"><?php echo $_t('संस्थाहरू', 'Organizations'); ?></div>
                </div>
            </div>

            <!-- Per-partner filter pills -->
            <div class="ph-summary-row">
                <span class="ph-summary-pill active" data-filter="all" onclick="phFilter('all', this)">
                    <i class="fas fa-th-large" style="font-size:.75rem;"></i>
                    <?php echo $_t('सबै', 'All'); ?>
                    <span class="ph-pill-count"><?php echo count($partnerHistory); ?></span>
                </span>
                <?php foreach ($partnerSummary as $pname => $pdata): ?>
                <span class="ph-summary-pill" data-filter="<?php echo htmlspecialchars($pname, ENT_QUOTES); ?>" onclick="phFilter(<?php echo json_encode($pname); ?>, this)">
                    <i class="fas fa-building" style="font-size:.72rem;"></i>
                    <?php echo htmlspecialchars($pname); ?>
                    <span class="ph-pill-count"><?php echo $pdata['total']; ?> <?php echo $_t('पटक', 'times'); ?></span>
                </span>
                <?php endforeach; ?>
            </div>

            <!-- History rows -->
            <div id="phList">
                <?php foreach ($partnerHistory as $h):
                    $pnAttr = htmlspecialchars(trim((string)($h['partner_name'] ?? '')), ENT_QUOTES);
                    $taken  = !empty($h['service_taken']);
                ?>
                <div class="ph-history-item" data-org="<?php echo $pnAttr; ?>">
                    <div class="ph-org-icon">
                        <i class="fas fa-<?php echo ($h['facility_type'] ?? '') === 'अस्पताल' ? 'hospital' : 'building-columns'; ?>"></i>
                    </div>
                    <div class="ph-info">
                        <div class="ph-org-name"><?php echo htmlspecialchars($h['partner_name'] ?? '—'); ?></div>
                        <div class="ph-svc-name">
                            <i class="fas fa-stethoscope" style="color:#0d9488;font-size:.72rem;margin-right:4px;"></i>
                            <?php echo htmlspecialchars($h['service_name'] ?: $_t('सेवा उल्लेख छैन', 'Service not specified')); ?>
                        </div>
                        <?php if (!empty($h['service_note'])): ?>
                        <div class="ph-svc-note"><i class="fas fa-note-sticky" style="font-size:.65rem;margin-right:3px;"></i><?php echo htmlspecialchars($h['service_note']); ?></div>
                        <?php endif; ?>
                        <div class="ph-date"><i class="fas fa-clock" style="font-size:.63rem;margin-right:3px;"></i><?php echo formatNepaliDate($h['created_at'], true); ?></div>
                    </div>
                    <div class="ph-taken-badge <?php echo $taken ? 'ph-taken-yes' : 'ph-taken-no'; ?>">
                        <?php if ($taken): ?><i class="fas fa-circle-check" style="font-size:.72rem;"></i><?php else: ?><i class="fas fa-circle-xmark" style="font-size:.72rem;"></i><?php endif; ?>
                        <?php echo $taken ? $_t('सेवा लिइयो', 'Taken') : $_t('नलिइएको', 'Not taken'); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

<script>
/* Partner history filter */
function phFilter(val, pill) {
    document.querySelectorAll('.ph-summary-pill').forEach(function(p){ p.classList.remove('active'); });
    if (pill) pill.classList.add('active');
    document.querySelectorAll('#phList .ph-history-item').forEach(function(row){
        var match = val === 'all' || row.getAttribute('data-org') === val;
        row.classList.toggle('ph-hidden', !match);
    });
}
/* Mark notification read */
function markRead(id, el) {
    if (el.classList.contains('mem-notif-item-read')) return;
    fetch('<?php echo $siteUrl; ?>member/ajax.php?action=mark_notif_read&id=' + id, {credentials:'same-origin'})
        .then(function(){ el.classList.remove('unread'); el.classList.add('mem-notif-item-read'); });
}
</script>
<?php require __DIR__ . '/includes/chrome-foot.php'; ?>
