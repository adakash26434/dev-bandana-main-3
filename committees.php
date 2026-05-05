<?php
require_once 'includes/config.php';
$pageTitle = isEnglish() ? 'Committees' : 'समिति/उपसमिति';
require_once 'includes/header.php';
$L = getLangStrings();

// Get selected committee type
/* ?type=N (पुरानो) र ?id=N (नयाँ navbar बाट) — दुवै support */
$selectedType  = isset($_GET['type']) ? (int)$_GET['type'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);
$selectedName  = isset($_GET['name']) ? trim($_GET['name']) : null;
$showPast = isset($_GET['past']) && $_GET['past'] == '1';

// Get data from database
try {
    $db = getDB();

    // Get committee types
    $committeeTypes = $db->query("SELECT * FROM committee_types WHERE is_active = 1 ORDER BY display_order, id")->fetchAll();

    // Resolve name → id (nav links use ?name=sanchalak etc.)
    if (!$selectedType && $selectedName) {
        $nameMap = [
            'sanchalak'   => ['सञ्चालक','sanchalak','board'],
            'byabasthapan'=> ['व्यवस्थापन','byabasthapan','management'],
            'lekha'       => ['लेखा','lekha','audit'],
            'sallahakar'  => ['सल्लाह','sallahakar','advisory'],
            'anya'        => ['अन्य','anya','sub','other'],
        ];
        $keywords = $nameMap[$selectedName] ?? [$selectedName];
        foreach ($committeeTypes as $t) {
            $haystack = strtolower($t['name'] . ' ' . $t['name_np']);
            foreach ($keywords as $kw) {
                if (mb_strpos($haystack, mb_strtolower($kw)) !== false) {
                    $selectedType = $t['id'];
                    break 2;
                }
            }
        }
    }

    // Also get board members from team_members table (for showing in filters)
    $boardMembers = $db->query("SELECT * FROM team_members WHERE category = 'board' AND is_active = 1 ORDER BY display_order")->fetchAll();

    // Get current tenures with members
    $currentCommittees = [];
    $pastCommittees = [];

    // First check if we should show board members (from team page)
    $showBoardFromTeam = isset($_GET['type']) && $_GET['type'] == 'board';

    foreach ($committeeTypes as $type) {
        // Skip if specific type is selected and this isn't it
        if ($selectedType && $selectedType != $type['id']) continue;

        // Get current tenure
        $stmt = $db->prepare("SELECT * FROM committee_tenures WHERE committee_type_id = ? AND is_current = 1 AND is_active = 1");
        $stmt->execute([$type['id']]);
        $currentTenure = $stmt->fetch();

        if ($currentTenure) {
            // Get members for current tenure
            $memberStmt = $db->prepare("SELECT * FROM committee_members WHERE tenure_id = ? AND is_active = 1 ORDER BY display_order, id");
            $memberStmt->execute([$currentTenure['id']]);
            $members = $memberStmt->fetchAll();

            // If no members in committee_members, try to get from team_members (for board)
            if (empty($members) && stripos($type['name'], 'संचालक') !== false || stripos($type['name'], 'board') !== false) {
                $members = $boardMembers;
            }

            $currentCommittees[] = [
                'type' => $type,
                'tenure' => $currentTenure,
                'members' => $members
            ];
        } else {
            // Even if no tenure, still show the type if it's a board type and we have board members
            if ((stripos($type['name'], 'संचालक') !== false || stripos($type['name'], 'board') !== false) && !empty($boardMembers)) {
                $currentCommittees[] = [
                    'type' => $type,
                    'tenure' => ['tenure_name' => isEnglish() ? 'Current' : 'हालको'],
                    'members' => $boardMembers
                ];
            }
        }

        // Get past tenures
        if ($showPast || $selectedType) {
            $pastStmt = $db->prepare("SELECT * FROM committee_tenures WHERE committee_type_id = ? AND is_current = 0 AND is_active = 1 ORDER BY start_date DESC");
            $pastStmt->execute([$type['id']]);
            $pastTenures = $pastStmt->fetchAll();

            foreach ($pastTenures as $tenure) {
                $memberStmt = $db->prepare("SELECT * FROM committee_members WHERE tenure_id = ? AND is_active = 1 ORDER BY display_order, id");
                $memberStmt->execute([$tenure['id']]);
                $members = $memberStmt->fetchAll();

                $pastCommittees[] = [
                    'type' => $type,
                    'tenure' => $tenure,
                    'members' => $members
                ];
            }
        }
    }

    // If no committees found at all but we have board members, show them as default
    if (empty($currentCommittees) && !empty($boardMembers) && !$showPast) {
        $currentCommittees[] = [
            'type' => ['id' => 0, 'name' => 'Board of Directors', 'name_np' => 'सञ्चालक समिति'],
            'tenure' => ['tenure_name' => isEnglish() ? 'Current' : 'हालको'],
            'members' => $boardMembers
        ];
    }
} catch (Exception $e) {
    $committeeTypes = [];
    $currentCommittees = [];
    $pastCommittees = [];
    $boardMembers = [];
}
?>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1><?php echo isEnglish() ? 'Committees & Sub-committees' : 'समिति/उपसमिति'; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>"><?php echo $L['home']; ?></a></li>
                <li class="breadcrumb-item active"><?php echo isEnglish() ? 'Committees' : 'समिति'; ?></li>
            </ol>
        </nav>
    </div>
</section>

<!-- Committee Filter -->
<section class="committee-filter py-4">
    <div class="container">
        <div class="filter-wrapper">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="filter-tabs">
                        <a href="committees.php" class="filter-tab <?php echo !$selectedType && !$showPast ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> <?php echo isEnglish() ? 'All Current' : 'सबै हालका'; ?>
                        </a>
                        <?php foreach ($committeeTypes as $type): ?>
                        <a href="?type=<?php echo $type['id']; ?>" class="filter-tab <?php echo $selectedType == $type['id'] ? 'active' : ''; ?>">
                            <?php echo isEnglish() ? $type['name'] : $type['name_np']; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <?php if (!$showPast): ?>
                    <a href="?past=1<?php echo $selectedType ? '&type='.$selectedType : ''; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-history"></i> <?php echo isEnglish() ? 'View Past Committees' : 'विगतका समितिहरू हेर्नुहोस्'; ?>
                    </a>
                    <?php else: ?>
                    <a href="committees.php<?php echo $selectedType ? '?type='.$selectedType : ''; ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-users"></i> <?php echo isEnglish() ? 'View Current Committees' : 'हालका समितिहरू हेर्नुहोस्'; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Current Committees -->
<?php if (!$showPast && !empty($currentCommittees)): ?>
<section class="committees-section section-padding">
    <div class="container">
<div class="section-header text-center mb-4" data-aos="fade-up">
<div class="section-badge-wrap">
<span class="section-badge"><i class="fas fa-users"></i> <?php echo isEnglish() ? 'Current Committees' : 'हालका समितिहरू'; ?></span>
</div>
<h2><?php echo isEnglish() ? 'Current Committee Members' : 'हालका समिति सदस्यहरू'; ?></h2>
<div class="section-divider"></div>
<p><?php echo isEnglish() ? 'Our current committee members serving the cooperative' : 'हाम्रो सहकारीलाई सेवा गरिरहेका हालका समिति सदस्यहरू'; ?></p>
</div>

        <?php foreach ($currentCommittees as $committee): ?>
        <div class="committee-block mb-5" data-aos="fade-up">
            <div class="committee-header">
                <h3>
                    <i class="fas fa-users-cog"></i>
                    <?php echo isEnglish() ? $committee['type']['name'] : $committee['type']['name_np']; ?>
                </h3>
                <span class="tenure-badge">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo isEnglish() ? 'Tenure: ' : 'कार्यकाल: '; ?>
                    <?php echo $committee['tenure']['tenure_name']; ?>
                </span>
            </div>

            <?php if (!empty($committee['members'])): ?>
            <div class="row justify-content-center">
                <?php foreach ($committee['members'] as $index => $member): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo ($index % 4) * 50; ?>">
                    <div class="team-card-circular <?php echo $index === 0 ? 'featured' : ''; ?>">
                        <div class="team-photo-circular">
                            <?php if ($member['photo']): ?>
                                <img src="<?php echo $member['photo']; ?>" loading="lazy"  alt="<?php echo $member['name']; ?>">
                            <?php else: ?>
                                <div class="team-placeholder-circular">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="team-info-circular">
                            <h5><?php echo $member['name']; ?></h5>
                            <?php if ($member['name_en']): ?>
                            <p class="team-name-en"><?php echo $member['name_en']; ?></p>
                            <?php endif; ?>
                            <span class="team-position-badge">
                                <?php echo isEnglish() ? ($member['position_en'] ?: $member['position']) : $member['position']; ?>
                            </span>
                            <?php if ($member['phone'] || $member['email']): ?>
                            <div class="team-contact-circular">
                                <?php if ($member['phone']): ?>
                                <a href="tel:<?php echo $member['phone']; ?>" title="<?php echo $member['phone']; ?>">
                                    <i class="fas fa-phone"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($member['email']): ?>
                                <a href="mailto:<?php echo $member['email']; ?>" title="<?php echo $member['email']; ?>">
                                    <i class="fas fa-envelope"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-committee text-center py-4">
                <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                <p class="text-muted"><?php echo isEnglish() ? 'No members available' : 'सदस्यहरू उपलब्ध छैनन्'; ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Past Committees -->
<?php if ($showPast && !empty($pastCommittees)): ?>
<section class="committees-section past-committees section-padding">
    <div class="container">
        <div class="section-header text-center" data-aos="fade-up">
            <h2><?php echo isEnglish() ? 'Past Committees' : 'विगतका समितिहरू'; ?></h2>
            <p><?php echo isEnglish() ? 'Our former committee members who served the cooperative' : 'हाम्रो सहकारीलाई सेवा गरेका पूर्व समिति सदस्यहरू'; ?></p>
        </div>

        <?php foreach ($pastCommittees as $committee): ?>
        <div class="committee-block past mb-5" data-aos="fade-up">
            <div class="committee-header">
                <h3>
                    <i class="fas fa-history"></i>
                    <?php echo isEnglish() ? $committee['type']['name'] : $committee['type']['name_np']; ?>
                </h3>
                <span class="tenure-badge past">
                    <i class="fas fa-calendar-alt"></i>
                    <?php echo isEnglish() ? 'Tenure: ' : 'कार्यकाल: '; ?>
                    <?php echo $committee['tenure']['tenure_name']; ?>
                    (<?php echo date('Y', strtotime($committee['tenure']['start_date'])); ?> - <?php echo date('Y', strtotime($committee['tenure']['end_date'])); ?>)
                </span>
            </div>

            <?php if (!empty($committee['members'])): ?>
            <div class="row justify-content-center">
                <?php foreach ($committee['members'] as $index => $member): ?>
                <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-3" data-aos="fade-up" data-aos-delay="<?php echo ($index % 6) * 50; ?>">
                    <div class="team-card-circular small">
                        <div class="team-photo-circular small">
                            <?php if ($member['photo']): ?>
                                <img src="<?php echo $member['photo']; ?>" loading="lazy"  alt="<?php echo $member['name']; ?>">
                            <?php else: ?>
                                <div class="team-placeholder-circular">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="team-info-circular">
                            <h6><?php echo $member['name']; ?></h6>
                            <span class="team-position-badge small">
                                <?php echo isEnglish() ? ($member['position_en'] ?: $member['position']) : $member['position']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Empty State -->
<?php if (empty($currentCommittees) && empty($pastCommittees)): ?>
<section class="section-padding">
    <div class="container">
        <div class="empty-state text-center py-5">
            <i class="fas fa-users-cog fa-4x text-muted mb-3"></i>
            <h4><?php echo isEnglish() ? 'No Committee Information Available' : 'समिति जानकारी उपलब्ध छैन'; ?></h4>
            <p class="text-muted"><?php echo isEnglish() ? 'Committee information will be available soon.' : 'समिति जानकारी चाँडै उपलब्ध हुनेछ।'; ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<style>
/* Committee Filter Styles */
.filter-wrapper {
    background: var(--light-bg);
    padding: 15px 20px;
    border-radius: 10px;
}

.filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.filter-tab {
    padding: 8px 16px;
    background: var(--white);
    border: 1px solid var(--border-color);
    border-radius: 25px;
    color: var(--text-color);
    font-size: 14px;
    transition: all 0.3s;
    text-decoration: none;
}

.filter-tab:hover,
.filter-tab.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: var(--white);
}

/* Committee Block */
.committee-block {
    background: var(--white);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.committee-block.past {
    opacity: 0.9;
}

.committee-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--primary-color);
}

.committee-header h3 {
    color: var(--primary-color);
    margin: 0;
    font-size: 1.5rem;
}

.committee-header h3 i {
    margin-right: 10px;
}

.tenure-badge {
    background: var(--primary-color);
    color: var(--white);
    padding: 8px 15px;
    border-radius: 25px;
    font-size: 13px;
}

.tenure-badge.past {
    background: var(--text-light);
}

.tenure-badge i {
    margin-right: 5px;
}

/* Member Card */
.member-card {
    background: var(--white);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 3px 15px rgba(0,0,0,0.1);
    text-align: center;
    transition: all 0.3s;
    height: 100%;
}

.member-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.member-card.featured {
    border: 2px solid var(--primary-color);
}

.member-card.featured .member-position {
    background: var(--primary-color);
    color: var(--white);
}

.member-photo {
    position: relative;
    padding-top: 100%;
    overflow: hidden;
    background: var(--light-bg);
}

.member-photo img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.member-placeholder {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: var(--border-color);
}

.member-info {
    padding: 15px;
}

.member-info h5 {
    margin-bottom: 5px;
    color: var(--text-color);
    font-size: 1rem;
}

.member-info h6 {
    margin-bottom: 3px;
    color: var(--text-color);
    font-size: 0.85rem;
}

.member-name-en {
    font-size: 0.8rem;
    color: var(--text-light);
    margin-bottom: 8px;
}

.member-position {
    display: inline-block;
    background: var(--light-bg);
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 0.75rem;
    color: var(--primary-color);
}

.member-position.small {
    font-size: 0.7rem;
    padding: 3px 8px;
}

.member-contact {
    margin-top: 12px;
    display: flex;
    justify-content: center;
    gap: 10px;
}

.member-contact a {
    width: 32px;
    height: 32px;
    background: var(--light-bg);
    color: var(--primary-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.member-contact a:hover {
    background: var(--primary-color);
    color: var(--white);
}

/* Small Member Card */
.member-card.small .member-photo {
    padding-top: 80%;
}

.member-card.small .member-placeholder {
    font-size: 32px;
}

.member-card.small .member-info {
    padding: 10px;
}

@media (max-width: 767px) {
    .committee-header {
        flex-direction: column;
        text-align: center;
    }

    .filter-tabs {
        justify-content: center;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
