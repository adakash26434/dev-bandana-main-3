<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = isEnglish() ? 'Home' : 'गृहपृष्ठ';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/ensure-tables.php';
ensurePublicTables();

// Get sliders
try {
    $db = getDB();
    $sliders = $db->query("SELECT * FROM sliders WHERE is_active = 1 ORDER BY display_order, id")->fetchAll();
    /* Homepage मा सिर्फ ३ सेवाहरू देखाउने — बाँकी services.php मा */
    $services = $db->query("SELECT * FROM services WHERE is_active = 1 ORDER BY display_order LIMIT 3")->fetchAll();
    $totalServicesRow = $db->query("SELECT COUNT(*) as cnt FROM services WHERE is_active = 1")->fetch();
    $totalServices = $totalServicesRow ? (int)$totalServicesRow['cnt'] : count($services);
    $notices = $db->query("SELECT * FROM notices WHERE is_active = 1 ORDER BY id DESC LIMIT 5")->fetchAll();
    $savingRates = $db->query("SELECT * FROM interest_rates WHERE category = 'saving' AND is_active = 1 ORDER BY display_order LIMIT 5")->fetchAll();
    $loanRates = $db->query("SELECT * FROM interest_rates WHERE category = 'loan' AND is_active = 1 ORDER BY display_order LIMIT 5")->fetchAll();
    // Get latest 3 news
    $latestNews = $db->query("SELECT * FROM news WHERE is_active = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();
} catch (Throwable $e) {
    $sliders = $services = $notices = $savingRates = $loanRates = $latestNews = [];
    $totalServices = 0;
    $db = null;
}

/* Member of the Year — current year को active record ल्याउनुहोस्
   Admin: admin/member-of-year.php बाट manage गरिन्छ */
$memberSpotlight = null;
if ($db instanceof PDO) {
    try {
        $spotlightStmt = $db->prepare(
            "SELECT * FROM member_of_year
             WHERE spotlight_year = ? AND is_active = 1
             LIMIT 1"
        );
        $spotlightStmt->execute([date('Y')]); /* current year — YYYY */
        $memberSpotlight = $spotlightStmt->fetch() ?: null;
    } catch (Throwable $e) {
        /* Table छैन वा error — section hide हुन्छ */
        $memberSpotlight = null;
    }
}

$heroTitle = getSetting('hero_title', 'तपाईंको भविष्यको लागि बचत गर्नुहोस्');
$heroSubtitle = getSetting('hero_subtitle', 'हामीसँग बचत गर्नुहोस्, सुरक्षित भविष्य बनाउनुहोस्');
$L = getLangStrings();
?>

<!-- Hero Slider Section -->
<section class="hero-slider">
    <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <?php foreach ($sliders as $index => $slider): ?>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $index; ?>"
                    <?php echo $index === 0 ? 'class="active"' : ''; ?>></button>
            <?php endforeach; ?>
            <?php if (empty($sliders)): ?>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active"></button>
            <?php endif; ?>
        </div>

        <div class="carousel-inner">
            <?php if (!empty($sliders)): ?>
                <?php foreach ($sliders as $index => $slider): ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                    <div class="slider-bg" style="background-image: url('<?php echo e($slider['image']); ?>');">
                        <div class="slider-overlay"></div>
                        <div class="container">
                            <div class="slider-content">
                                <h1><?php echo e($slider['title']); ?></h1>
                                <p><?php echo e($slider['subtitle']); ?></p>
                                <?php if ($slider['button_text']): ?>
                                <a href="<?php echo e($slider['button_url']); ?>" class="btn btn-primary btn-lg">
                                    <?php echo e($slider['button_text']); ?> <i class="fas fa-arrow-right"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="carousel-item active">
                    <div class="slider-bg default-slider">
                        <div class="slider-overlay"></div>
                        <div class="container">
                            <div class="slider-content">
                                <h1><?php echo $heroTitle; ?></h1>
                                <p><?php echo $heroSubtitle; ?></p>
                                <a href="about.php" class="btn btn-primary btn-lg">
                                    थप जान्नुहोस् <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>
</section>

<!-- Institutional Profile / Reports Section -->
<?php
// Get latest reports and institutional profile - with safe table checks
$latestMonthlyReport = null;
$latestAnnualReport = null;
$institutionalProfile = null;
if ($db instanceof PDO) {
    try {
        // Check if reports table exists
        $reportsCheck = $db->query("SHOW TABLES LIKE 'reports'");
        if ($reportsCheck && $reportsCheck->fetch() !== false) {
            $monthlyStmt = $db->query("SELECT * FROM reports WHERE is_active = 1 AND report_type = 'monthly' ORDER BY report_year DESC, created_at DESC LIMIT 1");
            if ($monthlyStmt) {
                $latestMonthlyReport = $monthlyStmt->fetch();
            }

            $annualStmt = $db->query("SELECT * FROM reports WHERE is_active = 1 AND report_type = 'annual' ORDER BY report_year DESC, created_at DESC LIMIT 1");
            if ($annualStmt) {
                $latestAnnualReport = $annualStmt->fetch();
            }
        }

        // Check if institutional_profile table exists
        $profileCheck = $db->query("SHOW TABLES LIKE 'institutional_profile'");
        if ($profileCheck && $profileCheck->fetch() !== false) {
            // Try publish_date first, fallback to fiscal_year for backward compatibility
            $profileStmt = $db->query("SELECT * FROM institutional_profile WHERE is_active = 1 ORDER BY COALESCE(publish_date, created_at) DESC LIMIT 1");
            if ($profileStmt) {
                $institutionalProfile = $profileStmt->fetch();
            }
        }
    } catch (Throwable $e) {
        // Tables may not exist - use defaults
        $latestMonthlyReport = $latestAnnualReport = null;
        $institutionalProfile = null;
    }
}
?>

<?php if ($institutionalProfile): ?>
<!-- Institutional Profile Latest Update (Compact) -->
<section class="institutional-stats-section">
    <div class="container">
        <div class="card border-0 shadow-sm" data-aos="fade-up">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 py-3">
                <div>
                    <div class="fw-bold mb-1">
                        <i class="fas fa-building-columns text-primary me-2"></i>
                        <?php echo isEnglish() ? 'Institutional Financial Profile (Latest Update)' : 'संस्थाको आर्थिक प्रोफाइल (Latest Update)'; ?>
                    </div>
                    <div class="small text-muted">
                        <?php
                        $parts = [];
                        if (!empty($institutionalProfile['fiscal_year'])) {
                            $parts[] = (isEnglish() ? 'FY ' : 'आ.व. ') . htmlspecialchars((string)$institutionalProfile['fiscal_year']);
                        }
                        if (!empty($institutionalProfile['report_date_bs'])) {
                            $parts[] = htmlspecialchars((string)$institutionalProfile['report_date_bs']);
                        } elseif (!empty($institutionalProfile['publish_date'])) {
                            $parts[] = formatDate((string)$institutionalProfile['publish_date'], 'Y-m-d');
                        }
                        echo implode(' | ', $parts);
                        ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 small">
                    <span class="badge bg-light text-dark border">
                        <?php echo isEnglish() ? 'Members: ' : 'सदस्य: '; ?><?php echo formatNepaliNumber($institutionalProfile['total_members']); ?>
                    </span>
                    <span class="badge bg-light text-dark border">
                        <?php echo isEnglish() ? 'Deposit: ' : 'बचत: '; ?><?php echo formatNepaliCurrency($institutionalProfile['deposit'], false); ?>
                    </span>
                    <span class="badge bg-light text-dark border">
                        <?php echo isEnglish() ? 'Loan: ' : 'ऋण: '; ?><?php echo formatNepaliCurrency($institutionalProfile['loan'], false); ?>
                    </span>
                    <a href="institutional-profile.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-arrow-up-right-from-square me-1"></i><?php echo isEnglish() ? 'Full Details' : 'पूर्ण विवरण'; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="institutional-profile-section">
    <div class="container">
        <div class="institutional-profile-bar" data-aos="fade-up">
            <div class="profile-title">
                <i class="fas fa-university"></i>
                <span><?php echo isEnglish() ? 'Institutional Profile' : 'प्रतिवेदन तथा प्रकाशनहरू'; ?></span>
            </div>
            <div class="profile-reports">
                <a href="reports.php?type=monthly" class="report-quick-link monthly">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo isEnglish() ? 'Monthly Reports' : 'मासिक प्रतिवेदन'; ?></span>
                    <?php if ($latestMonthlyReport): ?>
                    <small class="latest-badge"><?php echo isEnglish() ? 'Latest' : 'नयाँ'; ?></small>
                    <?php endif; ?>
                </a>
                <a href="reports.php?type=annual" class="report-quick-link annual">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo isEnglish() ? 'Annual Reports' : 'वार्षिक प्रतिवेदन'; ?></span>
                    <?php if ($latestAnnualReport): ?>
                    <small class="latest-badge"><?php echo isEnglish() ? 'Latest' : 'नयाँ'; ?></small>
                    <?php endif; ?>
                </a>
                <a href="reports.php" class="report-quick-link all">
                    <i class="fas fa-folder-open"></i>
                    <span><?php echo isEnglish() ? 'All Reports' : 'सबै प्रतिवेदन'; ?></span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="services-section section-padding">
    <div class="container">
        <div class="section-header text-center" data-aos="fade-up">
            <h2><?php echo isEnglish() ? 'Our Services' : 'हामीले प्रदान गर्ने सेवाहरू'; ?></h2>
            <p><?php echo isEnglish() ? 'We provide various financial services' : 'हामी विभिन्न वित्तीय सेवाहरू प्रदान गर्दछौं'; ?></p>
        </div>

        <div class="row justify-content-center">
            <?php foreach ($services as $index => $service): ?>
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                <div class="service-card">
                    <?php if (!empty($service['show_new_badge'])): ?>
                    <span class="new-badge"><?php echo isEnglish() ? 'New' : 'नयाँ'; ?></span>
                    <?php endif; ?>
                    <div class="service-icon">
                        <i class="<?php echo $service['icon']; ?>"></i>
                    </div>
                    <h4><?php echo isEnglish() ? ($service['title'] ?: $service['title_np']) : ($service['title_np'] ?: $service['title']); ?></h4>
                    <p><?php echo isEnglish() ? ($service['description'] ?: $service['description_np']) : ($service['description_np'] ?: $service['description']); ?></p>
                    <a href="services.php" class="service-link"><?php echo isEnglish() ? 'Learn More' : 'थप जान्नुहोस्'; ?> <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($services)): ?>
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="0">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-piggy-bank"></i></div>
                    <h4>बचत खाता</h4>
                    <p>आकर्षक ब्याज दरमा बचत खाता खोल्नुहोस्।</p>
                    <a href="services.php" class="service-link">थप जान्नुहोस् <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <h4>ऋण सेवा</h4>
                    <p>विभिन्न आवश्यकताहरूको लागि सजिलो ऋण।</p>
                    <a href="services.php" class="service-link">थप जान्नुहोस् <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-lock"></i></div>
                    <h4>मुद्दती निक्षेप</h4>
                    <p>उच्च प्रतिफलको लागि मुद्दती निक्षेप।</p>
                    <a href="services.php" class="service-link">थप जान्नुहोस् <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalServices > count($services)): ?>
        <!-- सबै सेवाहरू हेर्नुहोस् बटन — जब database मा ३ भन्दा बढी सेवाहरू छन् -->
        <div class="text-center mt-4" data-aos="fade-up">
            <a href="services.php" class="btn btn-primary btn-lg view-all-btn shadow-sm">
                <i class="fas fa-th-large me-2"></i>
                <?php echo isEnglish()
                    ? 'View All Services (' . $totalServices . ')'
                    : 'अरु सबै सेवाहरू यहाँ हेर्नुहोस् (' . $totalServices . ')'; ?>
                <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Tools Widget Section -->
<section class="tools-widget-section">
    <div class="container">
        <div class="section-header text-center mb-4" data-aos="fade-up">
            <h2><?php echo isEnglish() ? 'Digital Services' : 'अन्य डिजिटल सेवाहरू'; ?></h2>
            <p><?php echo isEnglish() ? 'Quick access to our online services' : 'हाम्रा अनलाइन सेवाहरूमा द्रुत पहुँच'; ?></p>
        </div>
        <div class="row g-3">
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="0">
                <div class="tools-category-card tools-cat-forms">
                    <h5><i class="fas fa-file-signature me-2"></i><?php echo isEnglish() ? 'Online Forms' : 'अनलाइन फारमहरू'; ?></h5>
                    <div class="tools-links-grid">
                        <a href="online-kyc.php" class="tools-mini-link"><i class="fas fa-user-check"></i><span><?php echo isEnglish() ? 'Online KYC' : 'अनलाइन केवाइसी'; ?></span></a>
                        <a href="loan-apply.php" class="tools-mini-link"><i class="fas fa-hand-holding-usd"></i><span><?php echo isEnglish() ? 'Apply Loan' : 'ऋण आवेदन'; ?></span></a>
                        <a href="online-account.php" class="tools-mini-link"><i class="fas fa-user-plus"></i><span><?php echo isEnglish() ? 'Open Account' : 'खाता खोल्नुहोस्'; ?></span></a>
                        <a href="appointment.php" class="tools-mini-link"><i class="fas fa-calendar-check"></i><span><?php echo isEnglish() ? 'Book Appointment' : 'भेटघाट बुक'; ?></span></a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="80">
                <div class="tools-category-card tools-cat-tools">
                    <h5><i class="fas fa-calculator me-2"></i><?php echo isEnglish() ? 'Tools / Calculator' : 'टुल्स / क्याल्कुलेटर'; ?></h5>
                    <div class="tools-links-grid">
                        <a href="emi-calculator.php" class="tools-mini-link"><i class="fas fa-calculator"></i><span><?php echo $L['emi_calculator']; ?></span></a>
                        <a href="exchange-rate.php" class="tools-mini-link"><i class="fas fa-exchange-alt"></i><span><?php echo $L['exchange_rate']; ?></span></a>
                        <a href="date-converter.php" class="tools-mini-link"><i class="fas fa-calendar-alt"></i><span><?php echo $L['date_converter']; ?></span></a>
                        <a href="downloads.php" class="tools-mini-link"><i class="fas fa-download"></i><span><?php echo $L['downloads']; ?></span></a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="160">
                <div class="tools-category-card tools-cat-member">
                    <h5><i class="fas fa-hands-helping me-2"></i><?php echo isEnglish() ? 'Member Services' : 'सदस्य सेवा / सहायता'; ?></h5>
                    <div class="tools-links-grid">
                        <a href="digital-services.php" class="tools-mini-link"><i class="fas fa-mobile-screen-button"></i><span><?php echo isEnglish() ? 'Digital Service' : 'डिजिटल सेवा'; ?></span></a>
                        <a href="member-welfare.php" class="tools-mini-link"><i class="fas fa-hand-holding-heart"></i><span><?php echo isEnglish() ? 'Member Welfare' : 'सदस्य सुविधा'; ?></span></a>
                        <a href="grievance.php" class="tools-mini-link"><i class="fas fa-exclamation-circle"></i><span><?php echo isEnglish() ? 'Grievance' : 'गुनासो'; ?></span></a>
                        <a href="auction.php" class="tools-mini-link"><i class="fas fa-gavel"></i><span><?php echo isEnglish() ? 'Auction' : 'लिलामी'; ?></span></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Interest Rates & Notices Section -->
<section class="rates-notices-section section-padding bg-light">
    <div class="container">
        <div class="row">
            <!-- Interest Rates -->
            <div class="col-lg-8 mb-4" data-aos="fade-right">
                <div class="rates-box-enhanced">
                    <div class="rates-header">
                        <h3><i class="fas fa-chart-line"></i> <?php echo isEnglish() ? 'Interest Rates' : 'ब्याज दरहरू'; ?></h3>
                    </div>
                    <div class="rates-body">
                        <div class="row">
                            <!-- Saving Rates -->
                            <div class="col-md-6">
                                <div class="rate-card-enhanced">
                                    <h5><i class="fas fa-piggy-bank"></i> <?php echo isEnglish() ? 'Savings Interest' : 'बचत ब्याज दर'; ?></h5>
                                    <?php if (!empty($savingRates)): ?>
                                        <?php foreach (array_slice($savingRates, 0, 6) as $rate): ?>
                                        <div class="rate-item">
                                            <span class="rate-name"><?php echo $rate['name_np'] ?: $rate['name']; ?></span>
                                            <span class="rate-value"><?php echo number_format($rate['rate'], 2); ?>%</span>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (count($savingRates) > 6): ?>
                                        <div class="text-center mt-2">
                                            <small class="text-muted"><?php echo isEnglish() ? '+' . (count($savingRates) - 6) . ' more rates' : '+' . (count($savingRates) - 6) . ' थप दरहरू'; ?></small>
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <a href="interest-rates.php" class="text-primary"><?php echo isEnglish() ? 'View all rates' : 'सबै दरहरू हेर्नुहोस्'; ?> <i class="fas fa-arrow-right"></i></a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Loan Rates -->
                            <div class="col-md-6">
                                <div class="rate-card-enhanced">
                                    <h5><i class="fas fa-hand-holding-usd"></i> <?php echo isEnglish() ? 'Loan Interest' : 'ऋण ब्याज दर'; ?></h5>
                                    <?php if (!empty($loanRates)): ?>
                                        <?php foreach (array_slice($loanRates, 0, 6) as $rate): ?>
                                        <div class="rate-item">
                                            <span class="rate-name"><?php echo $rate['name_np'] ?: $rate['name']; ?></span>
                                            <span class="rate-value"><?php echo number_format($rate['rate'], 2); ?>%</span>
                                        </div>
                                        <?php endforeach; ?>
                                        <?php if (count($loanRates) > 6): ?>
                                        <div class="text-center mt-2">
                                            <small class="text-muted"><?php echo isEnglish() ? '+' . (count($loanRates) - 6) . ' more rates' : '+' . (count($loanRates) - 6) . ' थप दरहरू'; ?></small>
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                    <div class="text-center py-3">
                                        <a href="interest-rates.php" class="text-primary"><?php echo isEnglish() ? 'View all rates' : 'सबै दरहरू हेर्नुहोस्'; ?> <i class="fas fa-arrow-right"></i></a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-3">
                            <a href="interest-rates.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right me-2"></i><?php echo isEnglish() ? 'View All Rates' : 'सबै ब्याज दरहरू हेर्नुहोस्'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notices -->
            <div class="col-lg-4 mb-4" data-aos="fade-left">
                <div class="notices-box-enhanced">
                    <div class="notices-header">
                        <h3><i class="fas fa-bullhorn"></i> <?php echo isEnglish() ? 'Notices' : 'सूचनाहरू'; ?></h3>
                    </div>
                    <div class="notices-body">
                        <div class="notices-list">
                            <?php foreach ($notices as $notice):
                                $noticeDate = new DateTime($notice['notice_date']);
                                $day = $noticeDate->format('d');
                                $month = $noticeDate->format('M');
                            ?>
                            <div class="notice-item-enhanced">
                                <div class="notice-date-box">
                                    <span class="day"><?php echo $day; ?></span>
                                    <span class="month"><?php echo $month; ?></span>
                                </div>
                                <div class="notice-content">
                                    <h6><a href="notices.php?id=<?php echo (int)$notice['id']; ?>"><?php echo e($notice['title']); ?></a></h6>
                                    <span class="notice-meta"><i class="fas fa-clock"></i> <?php echo formatDate($notice['notice_date'], 'Y-m-d'); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if (empty($notices)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-2"><?php echo isEnglish() ? 'No notices available' : 'कुनै सूचना छैन'; ?></p>
                                <a href="notices.php" class="btn btn-sm btn-outline-primary">
                                    <?php echo isEnglish() ? 'View All Notices' : 'सबै सूचनाहरू हेर्नुहोस्'; ?>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="notices-footer">
                        <a href="notices.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-2"></i><?php echo isEnglish() ? 'View All Notices' : 'सबै सूचनाहरू हेर्नुहोस्'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us Section — DB-driven -->
<?php
$whyFeatures = [];
if ($db instanceof PDO) {
    try {
        $whyFeatures = $db->query("SELECT * FROM why_choose_features WHERE is_active=1 ORDER BY sort_order, id")->fetchAll();
    } catch (Throwable $e) {
        $whyFeatures = [];
    }
}

/* Fallback defaults if table missing or empty */
if (empty($whyFeatures)) {
    $whyFeatures = [
        ['icon'=>'fas fa-shield-alt','title_np'=>'सुरक्षित बचत',    'title_en'=>'Safe Savings',       'desc_np'=>'तपाईंको बचत हामीसँग पूर्ण रूपमा सुरक्षित छ।',   'desc_en'=>'Your savings are fully secure with us.'],
        ['icon'=>'fas fa-percentage', 'title_np'=>'आकर्षक ब्याज',   'title_en'=>'Attractive Interest', 'desc_np'=>'बजारमा प्रतिस्पर्धी ब्याज दरहरू।',               'desc_en'=>'Competitive interest rates in the market.'],
        ['icon'=>'fas fa-clock',      'title_np'=>'छिटो सेवा',       'title_en'=>'Quick Service',       'desc_np'=>'द्रुत र प्रभावकारी ग्राहक सेवा।',                 'desc_en'=>'Fast and effective customer service.'],
        ['icon'=>'fas fa-users',      'title_np'=>'समुदायमा आधारित','title_en'=>'Community Based',     'desc_np'=>'समुदायको विकासमा समर्पित।',                       'desc_en'=>'Dedicated to community development.'],
    ];
}
?>
<section class="why-us-section section-padding">
    <div class="container">
        <div class="section-header text-center" data-aos="fade-up">
            <h2><?php echo isEnglish() ? 'Why Choose Us?' : 'किन हामीलाई छान्ने?'; ?></h2>
            <p><?php echo isEnglish() ? 'Reasons to choose our cooperative' : 'हाम्रो संस्था छान्नुको कारणहरू'; ?></p>
        </div>
        <div class="row">
        <?php foreach ($whyFeatures as $wi => $wf): ?>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $wi * 100; ?>">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="<?php echo htmlspecialchars($wf['icon']); ?>"></i>
                    </div>
                    <h5><?php echo htmlspecialchars(isEnglish() ? ($wf['title_en'] ?: $wf['title_np']) : $wf['title_np']); ?></h5>
                    <p><?php echo htmlspecialchars(isEnglish() ? ($wf['desc_en'] ?: $wf['desc_np']) : ($wf['desc_np'] ?? '')); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Leadership Messages Section -->
<?php
$chairmanMessage = getSetting('chairman_message_np', '');
$chairmanName = getSetting('chairman_name', 'अध्यक्ष');
$chairmanPhoto = getSetting('chairman_photo', '');
$ceoMessage = getSetting('ceo_message_np', '');
$ceoName = getSetting('ceo_name', 'प्रमुख कार्यकारी अधिकृत');
$ceoPhoto = getSetting('ceo_photo', '');
$ceoDesignationNp = trim((string)getSetting('ceo_designation_np', 'प्रमुख कार्यकारी अधिकृत'));
$ceoDesignationEn = trim((string)getSetting('ceo_designation_en', 'Chief Executive Officer'));

// Get Information Officer and Grievance Officer
$informationOfficer = $grievanceOfficer = null;
if ($db instanceof PDO) {
    try {
        $informationOfficer = $db->query("SELECT * FROM team_members WHERE is_information_officer = 1 AND is_active = 1 LIMIT 1")->fetch();
        $grievanceOfficer = $db->query("SELECT * FROM team_members WHERE is_grievance_officer = 1 AND is_active = 1 LIMIT 1")->fetch();
    } catch (Throwable $e) {
        $informationOfficer = $grievanceOfficer = null;
    }
}
?>
<?php if ($chairmanMessage || $ceoMessage || $informationOfficer || $grievanceOfficer): ?>
<section class="leadership-messages-section section-padding bg-light">
    <div class="container">
        <div class="section-header text-center" data-aos="fade-up">
            <div class="section-badge-wrap">
                <span class="section-badge"><i class="fas fa-user-tie"></i> <?php echo isEnglish() ? 'Leadership' : 'नेतृत्व'; ?></span>
            </div>
            <h2><?php echo isEnglish() ? 'Messages from Leadership' : 'नेतृत्वबाट सन्देश'; ?></h2>
            <div class="section-divider"></div>
            <p><?php echo isEnglish() ? 'Meet our leadership team and key officers' : 'हाम्रो नेतृत्व टोली र प्रमुख अधिकारीहरूसँग भेट्नुहोस्'; ?></p>
        </div>

        <div class="row justify-content-center">
            <?php if ($chairmanMessage): ?>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="0">
                <div class="leadership-profile-card chairman-card">
                    <div class="profile-photo">
                        <?php if ($chairmanPhoto): ?>
                        <img src="<?php echo SITE_URL . $chairmanPhoto; ?>?v=<?php echo time(); ?>" loading="lazy" alt="<?php echo $chairmanName; ?>">
                        <?php else: ?>
                        <div class="photo-placeholder">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h4><?php echo $chairmanName; ?></h4>
                        <span class="profile-position"><?php echo isEnglish() ? 'Chairman' : 'अध्यक्ष'; ?></span>
                        <p class="profile-message"><?php echo truncateText(strip_tags($chairmanMessage), 120); ?></p>
                    </div>
                    <a href="about.php#chairman-message" class="profile-btn">
                        <?php echo isEnglish() ? 'Read More' : 'थप विवरण'; ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($ceoMessage): ?>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="leadership-profile-card ceo-card">
                    <div class="profile-photo">
                        <?php if ($ceoPhoto): ?>
                        <img src="<?php echo SITE_URL . $ceoPhoto; ?>?v=<?php echo time(); ?>" alt="<?php echo $ceoName; ?>">
                        <?php else: ?>
                        <div class="photo-placeholder">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h4><?php echo $ceoName; ?></h4>
                        <span class="profile-position"><?php echo isEnglish() ? $ceoDesignationEn : $ceoDesignationNp; ?></span>
                        <p class="profile-message"><?php echo truncateText(strip_tags($ceoMessage), 120); ?></p>
                    </div>
                    <a href="about.php#ceo-message" class="profile-btn">
                        <?php echo isEnglish() ? 'Read More' : 'थप विवरण'; ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($informationOfficer): ?>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="leadership-profile-card officer-card info-officer">
                    <div class="officer-badge"><i class="fas fa-info-circle"></i></div>
                    <div class="profile-photo">
                        <?php if ($informationOfficer['photo']): ?>
                        <img src="<?php echo $informationOfficer['photo']; ?>" loading="lazy"  alt="<?php echo $informationOfficer['name']; ?>">
                        <?php else: ?>
                        <div class="photo-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h4><?php echo isEnglish() && $informationOfficer['name_en'] ? $informationOfficer['name_en'] : $informationOfficer['name']; ?></h4>
                        <span class="profile-position"><?php echo isEnglish() ? 'Information Officer' : 'सूचना अधिकारी'; ?></span>
                        <div class="officer-contact-info">
                            <?php if ($informationOfficer['phone']): ?>
                            <a href="tel:<?php echo $informationOfficer['phone']; ?>"><i class="fas fa-phone"></i> <?php echo $informationOfficer['phone']; ?></a>
                            <?php endif; ?>
                            <?php if ($informationOfficer['email']): ?>
                            <a href="mailto:<?php echo $informationOfficer['email']; ?>"><i class="fas fa-envelope"></i> <?php echo $informationOfficer['email']; ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="team.php" class="profile-btn">
                        <?php echo isEnglish() ? 'View Details' : 'थप विवरण'; ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($grievanceOfficer): ?>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="leadership-profile-card officer-card grievance-officer">
                    <div class="officer-badge grievance"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="profile-photo">
                        <?php if ($grievanceOfficer['photo']): ?>
                        <img src="<?php echo $grievanceOfficer['photo']; ?>" loading="lazy"  alt="<?php echo $grievanceOfficer['name']; ?>">
                        <?php else: ?>
                        <div class="photo-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <h4><?php echo isEnglish() && $grievanceOfficer['name_en'] ? $grievanceOfficer['name_en'] : $grievanceOfficer['name']; ?></h4>
                        <span class="profile-position"><?php echo isEnglish() ? 'Grievance Officer' : 'गुनासो अधिकारी'; ?></span>
                        <div class="officer-contact-info">
                            <?php if ($grievanceOfficer['phone']): ?>
                            <a href="tel:<?php echo $grievanceOfficer['phone']; ?>"><i class="fas fa-phone"></i> <?php echo $grievanceOfficer['phone']; ?></a>
                            <?php endif; ?>
                            <?php if ($grievanceOfficer['email']): ?>
                            <a href="mailto:<?php echo $grievanceOfficer['email']; ?>"><i class="fas fa-envelope"></i> <?php echo $grievanceOfficer['email']; ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="grievance.php" class="profile-btn">
                        <?php echo isEnglish() ? 'File Grievance' : 'गुनासो दर्ता'; ?> <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Mobile Banking App Section -->
<section class="mobile-app-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0" data-aos="fade-right">
                <div class="app-content">
                    <h2><?php echo isEnglish() ? 'Manage your Digital Payments' : 'आफ्नो डिजिटल भुक्तानी व्यवस्थापन गर्नुहोस्'; ?></h2>
                    <h3><?php echo isEnglish() ? 'Anytime, Anywhere.' : 'जुनसुकै समय, जहाँबाट पनि।'; ?></h3>
                    <p class="app-tagline"><?php echo isEnglish() ? 'Download our Mobile Banking app!' : 'हाम्रो मोबाइल बैंकिङ एप डाउनलोड गर्नुहोस्!'; ?></p>
                    <p class="app-description"><?php echo isEnglish() ? 'Quick, Secure, and Convenient: Your all-in-one mobile banking app for seamless financial control.' : 'छिटो, सुरक्षित र सुविधाजनक: तपाईंको वित्तीय नियन्त्रणको लागि सबै-मा-एक मोबाइल बैंकिङ एप।'; ?></p>
                    <div class="app-buttons">
                        <a href="<?php echo getSetting('play_store_url', '#'); ?>" target="_blank" class="app-btn google-play">
                            <i class="fab fa-google-play"></i>
                            <span>
                                <small><?php echo isEnglish() ? 'GET IT ON' : 'यहाँबाट लिनुहोस्'; ?></small>
                                Google Play
                            </span>
                        </a>
                        <a href="<?php echo getSetting('app_store_url', '#'); ?>" target="_blank" class="app-btn app-store">
                            <i class="fab fa-apple"></i>
                            <span>
                                <small><?php echo isEnglish() ? 'Download on the' : 'यहाँबाट लिनुहोस्'; ?></small>
                                App Store
                            </span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="app-image text-center">
                    <?php
                    $mobileAppPhoto = getSetting('mobile_app_photo', '');
                    if ($mobileAppPhoto):
                    ?>
                    <img src="<?php echo SITE_URL . $mobileAppPhoto; ?>?v=<?php echo time(); ?>" alt="Mobile Banking App" class="app-phone-img">
                    <?php else: ?>
                    <div class="app-mockup-default">
                        <div class="phone-frame">
                            <div class="phone-screen">
                                <i class="fas fa-mobile-alt"></i>
                                <span><?php echo isEnglish() ? 'Mobile Banking' : 'मोबाइल बैंकिङ'; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mobile App Features Section -->
<?php
// Get app features from database or use defaults
$appFeatures = [];
if ($db instanceof PDO) {
    try {
        $appFeatures = $db->query("SELECT * FROM app_features WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 8")->fetchAll();
    } catch (Throwable $e) {
        $appFeatures = [];
    }
}
// Default features if database table doesn't exist
if (empty($appFeatures)) {
    $appFeatures = [
        ['icon' => 'fas fa-exchange-alt', 'title_np' => 'फण्ड ट्रान्सफर', 'title' => 'Fund Transfer', 'is_new' => 0],
        ['icon' => 'fas fa-file-invoice-dollar', 'title_np' => 'बिल भुक्तानी', 'title' => 'Bill Payment', 'is_new' => 0],
        ['icon' => 'fas fa-qrcode', 'title_np' => 'QR भुक्तानी', 'title' => 'QR Payment', 'is_new' => 1],
        ['icon' => 'fas fa-mobile-alt', 'title_np' => 'मोबाइल टपअप', 'title' => 'Mobile Topup', 'is_new' => 0],
        ['icon' => 'fas fa-wallet', 'title_np' => 'वालेट लोड', 'title' => 'Wallet Load', 'is_new' => 1],
        ['icon' => 'fas fa-chart-line', 'title_np' => 'स्टेटमेन्ट हेर्नुहोस्', 'title' => 'View Statement', 'is_new' => 0],
        ['icon' => 'fas fa-credit-card', 'title_np' => 'कार्ड व्यवस्थापन', 'title' => 'Card Management', 'is_new' => 1],
        ['icon' => 'fas fa-university', 'title_np' => 'शाखा पत्ता लगाउनुहोस्', 'title' => 'Locate Branch', 'is_new' => 0]
    ];
}
?>
<section class="app-features-section section-padding">
    <div class="container">
        <div class="section-header text-center" data-aos="fade-up">
            <div class="section-badge-wrap">
                <span class="section-badge"><?php echo isEnglish() ? 'App Features' : 'एप सुविधाहरू'; ?></span>
            </div>
            <h2><?php echo isEnglish() ? 'What You Can Do With Our App' : 'हाम्रो एपबाट तपाईं के गर्न सक्नुहुन्छ'; ?></h2>
            <div class="section-divider"></div>
            <p><?php echo isEnglish() ? 'Explore all the features available in our mobile banking app' : 'हाम्रो मोबाइल बैंकिङ एपमा उपलब्ध सबै सुविधाहरू अन्वेषण गर्नुहोस्'; ?></p>
        </div>

        <div class="app-features-grid" data-aos="fade-up" data-aos-delay="100" id="appFeaturesGrid">
            <?php
            $totalFeatures = count($appFeatures);
            $showInitially = 4;
            foreach ($appFeatures as $index => $feature):
            ?>
            <div class="app-feature-item <?php echo ($feature['is_new'] ?? 0) ? 'has-new-badge' : ''; ?> <?php echo ($index >= $showInitially) ? 'hidden-feature' : ''; ?>" data-feature-index="<?php echo $index; ?>">
                <?php if ($feature['is_new'] ?? 0): ?>
                <span class="new-badge"><?php echo isEnglish() ? 'NEW' : 'नयाँ'; ?></span>
                <?php endif; ?>
                <div class="feature-icon-wrap">
                    <i class="<?php echo $feature['icon']; ?>"></i>
                </div>
                <h5><?php echo isEnglish() ? ($feature['title'] ?? $feature['title_np']) : ($feature['title_np'] ?? $feature['title']); ?></h5>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalFeatures > $showInitially): ?>
        <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="150">
            <button type="button" class="btn btn-primary btn-lg" id="showMoreFeatures">
                <i class="fas fa-plus-circle me-2"></i>
                <?php echo isEnglish() ? 'Show More' : 'थप हेर्नुहोस्'; ?>
                <span class="feature-count">(<?php echo $totalFeatures - $showInitially; ?>)</span>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-lg d-none" id="showLessFeatures">
                <i class="fas fa-minus-circle me-2"></i>
                <?php echo isEnglish() ? 'Show Less' : 'कम देखाउनुहोस्'; ?>
            </button>
        </div>
        <?php endif; ?>

        <div class="text-center mt-4" data-aos="fade-up" data-aos-delay="200">
            <a href="services.php" class="btn btn-outline-primary btn-lg">
                <?php echo isEnglish() ? 'View All Services' : 'सबै सेवाहरू हेर्नुहोस्'; ?>
                <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- Latest News Section -->
<?php if (!empty($latestNews)): ?>
<section class="news-section section-padding bg-light">
    <div class="container">
        <div class="section-header text-center" data-aos="fade-up">
            <h2><?php echo isEnglish() ? 'Latest News' : 'ताजा समाचार'; ?></h2>
            <p><?php echo isEnglish() ? 'Stay updated with our latest news and activities' : 'हाम्रो ताजा समाचार र क्रियाकलापहरूसँग अद्यावधिक रहनुहोस्'; ?></p>
        </div>

        <div class="row">
            <?php foreach ($latestNews as $index => $news): ?>
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                <div class="news-card">
                    <div class="news-image">
                        <?php if (!empty($news['image'])): ?>
                        <img src="<?php echo $news['image']; ?>" loading="lazy"  alt="<?php echo getLangField($news, 'title'); ?>">
                        <?php else: ?>
                        <div class="news-placeholder">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <?php endif; ?>
                        <div class="news-date">
                            <span class="day"><?php echo date('d', strtotime($news['created_at'])); ?></span>
                            <span class="month"><?php echo date('M', strtotime($news['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="news-content">
                        <h4><?php echo getLangField($news, 'title'); ?></h4>
                        <p><?php echo truncateText(strip_tags(getLangField($news, 'content')), 100); ?></p>
                        <a href="news-detail.php?id=<?php echo $news['id']; ?>" class="read-more">
                            <?php echo isEnglish() ? 'Read More' : 'थप पढ्नुहोस्'; ?> <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4" data-aos="fade-up">
            <a href="news.php" class="btn btn-primary btn-lg">
                <i class="fas fa-newspaper"></i> <?php echo isEnglish() ? 'View All News' : 'सबै समाचार हेर्नुहोस्'; ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Awards Section -->
<?php
// Get awards from database (limit to 3 on homepage)
$awards = [];
$totalAwards = 0;
if ($db instanceof PDO) {
    try {
        $awardsStmt = $db->query("SELECT * FROM awards WHERE is_active = 1 ORDER BY display_order ASC, award_date DESC LIMIT 3");
        $awards = $awardsStmt->fetchAll();
        // Check total count for "View All" link
        $totalAwardsStmt = $db->query("SELECT COUNT(*) as total FROM awards WHERE is_active = 1");
        $totalAwards = $totalAwardsStmt->fetch()['total'] ?? 0;
    } catch (Throwable $e) {
        $awards = [];
        $totalAwards = 0;
    }
}
?>
<?php if (!empty($awards)): ?>
<section class="awards-section section-padding bg-light">
    <div class="container">
        <div class="section-header text-center" data-aos="fade-up">
            <h2><?php echo isEnglish() ? 'Awards & Recognition' : 'सहकारीले पाएको सम्मान तथा पुरस्कार'; ?></h2>
            <p><?php echo isEnglish() ? 'Our achievements and recognition over the years' : 'वर्षौंमा हाम्रो उपलब्धि र सम्मान'; ?></p>
        </div>

        <div class="row justify-content-center">
            <?php foreach ($awards as $index => $award): ?>
            <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                <div class="award-card">
                    <?php if (!empty($award['image'])): ?>
                    <div class="award-image">
                        <img src="<?php echo SITE_URL . $award['image']; ?>" loading="lazy"  alt="<?php echo isEnglish() ? ($award['title'] ?? $award['title_np']) : ($award['title_np'] ?? $award['title']); ?>">
                    </div>
                    <?php else: ?>
                    <div class="award-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <?php endif; ?>
                    <div class="award-content">
                        <h4><?php echo isEnglish() ? ($award['title'] ?? $award['title_np']) : ($award['title_np'] ?? $award['title']); ?></h4>
                        <p class="award-by">
                            <i class="fas fa-medal"></i>
                            <?php echo isEnglish() ? ($award['awarded_by'] ?? $award['awarded_by_np']) : ($award['awarded_by_np'] ?? $award['awarded_by']); ?>
                        </p>
                        <?php if ($award['award_date']): ?>
                        <span class="award-date">
                            <i class="fas fa-calendar-alt"></i> <?php echo date('Y', strtotime($award['award_date'])); ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($award['description']) || !empty($award['description_np'])): ?>
                        <p class="award-desc"><?php echo isEnglish() ? ($award['description'] ?? $award['description_np']) : ($award['description_np'] ?? $award['description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalAwards > 3): ?>
        <div class="text-center mt-4" data-aos="fade-up">
            <a href="awards.php" class="btn btn-primary btn-lg">
                <i class="fas fa-trophy"></i> <?php echo isEnglish() ? 'View All Awards' : 'सबै सम्मान हेर्नुहोस्'; ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================================================
     Member of the Year Spotlight Section
     Admin ले admin/member-of-year.php बाट yearly update गर्छ
     Current year को active record भएमा मात्र यो section देखिन्छ
     ============================================================ -->
<?php if ($memberSpotlight): ?>
<?php
/* Year display — Nepali/English */
$spotlightYearVal = $memberSpotlight['spotlight_year'] ?? date('Y');
/* Nepali year conversion (approximate: AD - 56 or 57) */
$nepaliYear = ($spotlightYearVal - 56) . '/' . ($spotlightYearVal - 57);
$spotlightYearShow = isEnglish()
    ? $spotlightYearVal
    : $spotlightYearVal . ' (वि.सं. लगभग ' . $nepaliYear . ')';

$spotlightName = isEnglish()
    ? ($memberSpotlight['member_name_en'] ?: $memberSpotlight['member_name'])
    : $memberSpotlight['member_name'];
$spotlightQuote = isEnglish()
    ? ($memberSpotlight['quote_en'] ?: $memberSpotlight['quote'])
    : ($memberSpotlight['quote'] ?: $memberSpotlight['quote_en']);
$spotlightAchievement = isEnglish()
    ? ($memberSpotlight['achievement_en'] ?: $memberSpotlight['achievement'])
    : ($memberSpotlight['achievement'] ?: $memberSpotlight['achievement_en']);
$hasPhoto = !empty($memberSpotlight['photo']) && file_exists(ROOT_PATH . $memberSpotlight['photo']);
?>
<section class="member-spotlight-section section-padding" id="member-spotlight">
    <div class="container">
        <div class="section-header text-center mb-5" data-aos="fade-up">
            <div class="section-badge-wrap">
                <!-- Gold star badge -->
                <span class="section-badge" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">
                    <i class="fas fa-trophy"></i>
                    <?php echo isEnglish() ? 'Annual Spotlight' : 'वार्षिक सम्मान'; ?>
                </span>
            </div>
            <h2>
                <?php echo isEnglish() ? 'Member of the Year' : 'वर्षको सदस्य'; ?>
            </h2>
            <div class="section-divider"></div>
            <p class="text-muted">
                <?php echo isEnglish()
                    ? 'Celebrating our outstanding member for the year ' . $spotlightYearVal
                    : $spotlightYearVal . ' को विशिष्ट सदस्यलाई सम्मान'; ?>
            </p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="spotlight-card" data-aos="zoom-in">

                    <!-- Decorative top bar -->
                    <div class="spotlight-top-bar"></div>

                    <div class="spotlight-inner">
                        <!-- Left: Photo area -->
                        <div class="spotlight-photo-col">
                            <div class="spotlight-photo-frame">
                                <?php if ($hasPhoto): ?>
                                <img src="<?php echo SITE_URL . $memberSpotlight['photo']; ?>"
                                     alt="<?php echo htmlspecialchars($spotlightName); ?>"
                                     class="spotlight-photo">
                                <?php else: ?>
                                <!-- Photo नभए decorative icon -->
                                <div class="spotlight-photo-placeholder">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <?php endif; ?>

                                <!-- Year badge over photo -->
                                <div class="spotlight-month-badge">
                                    <i class="fas fa-trophy me-1"></i>
                                    <?php echo htmlspecialchars($spotlightYearVal); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Info area -->
                        <div class="spotlight-info-col">
                            <!-- "Member of the Year" label -->
                            <div class="spotlight-tag">
                                <i class="fas fa-trophy me-1"></i>
                                <?php echo isEnglish() ? 'Member of the Year ' . $spotlightYearVal : $spotlightYearVal . ' को सदस्य'; ?>
                            </div>

                            <!-- Member Name -->
                            <h3 class="spotlight-name">
                                <?php echo htmlspecialchars($spotlightName); ?>
                            </h3>

                            <!-- Meta: Member since + ID -->
                            <div class="spotlight-meta">
                                <?php if ($memberSpotlight['member_since']): ?>
                                <span class="spotlight-meta-item">
                                    <i class="fas fa-calendar-check"></i>
                                    <?php echo isEnglish() ? 'Member since' : 'सदस्य बनेको'; ?>:
                                    <strong><?php echo htmlspecialchars($memberSpotlight['member_since']); ?></strong>
                                </span>
                                <?php endif; ?>
                                <?php if ($memberSpotlight['member_id']): ?>
                                <span class="spotlight-meta-item">
                                    <i class="fas fa-id-badge"></i>
                                    <?php echo isEnglish() ? 'Member ID' : 'सदस्य नं.'; ?>:
                                    <strong><?php echo htmlspecialchars($memberSpotlight['member_id']); ?></strong>
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- Achievement badge -->
                            <?php if ($spotlightAchievement): ?>
                            <div class="spotlight-achievement">
                                <i class="fas fa-trophy me-1"></i>
                                <?php echo htmlspecialchars($spotlightAchievement); ?>
                            </div>
                            <?php endif; ?>

                            <!-- Quote -->
                            <?php if ($spotlightQuote): ?>
                            <blockquote class="spotlight-quote">
                                <i class="fas fa-quote-left"></i>
                                <span><?php echo htmlspecialchars($spotlightQuote); ?></span>
                            </blockquote>
                            <?php endif; ?>
                        </div>
                    </div><!-- /spotlight-inner -->

                    <!-- Decorative corner stars -->
                    <div class="spotlight-stars">
                        <i class="fas fa-star star-1"></i>
                        <i class="fas fa-star star-2"></i>
                        <i class="fas fa-star star-3"></i>
                    </div>
                </div><!-- /spotlight-card -->
            </div>
        </div>

    </div>
</section>

<!-- Member of the Month CSS -->
<style>
/* =====================================================
   Member Spotlight Section Styles
   ===================================================== */

/* Section background — हल्का golden tint */
.member-spotlight-section {
    background: linear-gradient(135deg, #fffbf0 0%, #fff8e1 50%, #fffbf0 100%);
    position: relative;
    overflow: hidden;
}

/* Subtle background pattern */
.member-spotlight-section::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 30% 30%, rgba(245,158,11,0.04) 0%, transparent 60%),
                radial-gradient(circle at 70% 70%, rgba(26,95,42,0.03) 0%, transparent 60%);
    pointer-events: none;
}

/* Main spotlight card */
.spotlight-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 16px 48px rgba(245,158,11,0.14), 0 4px 16px rgba(0,0,0,0.06);
    overflow: hidden;
    position: relative;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.spotlight-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 24px 64px rgba(245,158,11,0.2), 0 8px 24px rgba(0,0,0,0.08);
}

/* Gold top accent bar */
.spotlight-top-bar {
    height: 6px;
    background: linear-gradient(90deg, #f59e0b, #fbbf24, #f59e0b, #d97706);
    background-size: 300% 100%;
    animation: shimmer-bar 3s linear infinite;
}
@keyframes shimmer-bar {
    0%   { background-position: 0% 50%; }
    100% { background-position: 300% 50%; }
}

/* Inner layout — flex */
.spotlight-inner {
    display: flex;
    align-items: center;
    gap: 36px;
    padding: 32px 36px;
}

/* Photo column */
.spotlight-photo-col {
    flex-shrink: 0;
}

/* Photo frame with golden ring */
.spotlight-photo-frame {
    position: relative;
    width: 140px;
}

.spotlight-photo {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid #f59e0b; /* golden border */
    box-shadow: 0 0 0 4px rgba(245,158,11,0.2), 0 8px 20px rgba(0,0,0,0.12);
    display: block;
}

.spotlight-photo-placeholder {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 5px solid #f59e0b;
    box-shadow: 0 0 0 4px rgba(245,158,11,0.2);
}
.spotlight-photo-placeholder i {
    font-size: 4rem;
    color: #d97706;
    opacity: 0.6;
}

/* Month badge below photo */
.spotlight-month-badge {
    position: absolute;
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg,var(--primary-color),var(--primary-light));
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 20px;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(26,95,42,0.3);
}

/* Info column */
.spotlight-info-col {
    flex: 1;
    min-width: 0;
}

/* "Member of the Month" tag */
.spotlight-tag {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    font-size: 0.78rem;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 20px;
    margin-bottom: 10px;
    border: 1px solid rgba(245,158,11,0.3);
}

/* Member name */
.spotlight-name {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1a202c;
    margin-bottom: 8px;
    line-height: 1.2;
}

/* Meta items row */
.spotlight-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}
.spotlight-meta-item {
    font-size: 0.82rem;
    color: #666;
    display: flex;
    align-items: center;
    gap: 5px;
}
.spotlight-meta-item i {
    color: var(--primary-color);
    font-size: 0.78rem;
}
.spotlight-meta-item strong {
    color: var(--primary-color);
}

/* Achievement badge */
.spotlight-achievement {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, #e8f5e9, #dcfce7);
    color: var(--primary-color);
    font-size: 0.82rem;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 20px;
    margin-bottom: 14px;
    border: 1px solid rgba(26,95,42,0.15);
}
.spotlight-achievement i { color: #f59e0b; margin-right: 5px; }

/* Quote */
.spotlight-quote {
    background: linear-gradient(135deg, #fffbf0, #fef3c7);
    border-left: 4px solid #f59e0b;
    border-radius: 0 10px 10px 0;
    padding: 12px 16px;
    margin: 0;
    position: relative;
    font-style: italic;
    color: #555;
    font-size: 0.9rem;
    line-height: 1.6;
}
.spotlight-quote .fa-quote-left {
    color: #f59e0b;
    font-size: 1.1rem;
    margin-right: 8px;
    opacity: 0.7;
}

/* Decorative stars */
.spotlight-stars {
    position: absolute;
    pointer-events: none;
}
.spotlight-stars i {
    position: absolute;
    color: #f59e0b;
    opacity: 0.12;
    animation: twinkle 3s ease-in-out infinite;
}
.star-1 { font-size: 2rem; top: 16px; right: 24px; animation-delay: 0s; }
.star-2 { font-size: 1rem; top: 48px; right: 56px; animation-delay: 0.8s; }
.star-3 { font-size: 1.5rem; bottom: 20px; right: 40px; animation-delay: 1.4s; }
@keyframes twinkle {
    0%, 100% { opacity: 0.12; transform: scale(1); }
    50%       { opacity: 0.25; transform: scale(1.2); }
}

/* Mobile responsive */
@media (max-width: 576px) {
    .spotlight-inner {
        flex-direction: column;
        text-align: center;
        padding: 24px 20px;
        gap: 24px;
    }
    .spotlight-meta { justify-content: center; }
    .spotlight-achievement { display: block; text-align: center; }
    .spotlight-name { font-size: 1.3rem; }
    .spotlight-photo { width: 120px; height: 120px; }
    .spotlight-photo-frame { width: 120px; }
}
</style>
<?php endif; /* end $memberSpotlight check */ ?>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-content" data-aos="zoom-in">
            <div class="row align-items-center">
                <div class="col-lg-7">
                    <h2><?php echo isEnglish() ? 'Become a Member Today!' : 'आज नै सदस्य बन्नुहोस्!'; ?></h2>
                    <p><?php echo isEnglish() ? 'Join our cooperative family and secure your financial future.' : 'हाम्रो सहकारी परिवारमा सामेल हुनुहोस् र आफ्नो वित्तीय भविष्य सुरक्षित गर्नुहोस्।'; ?></p>
                </div>
                <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
                    <div class="cta-buttons">
                        <a href="online-kyc.php" class="btn btn-light btn-lg me-2 mb-2">
                            <i class="fas fa-user-check"></i> <?php echo isEnglish() ? 'Fill KYC Form' : 'केवाइसी फारम भर्नुहोस्'; ?>
                        </a>
                        <a href="contact.php" class="btn btn-outline-light btn-lg mb-2">
                            <i class="fas fa-phone-alt"></i> <?php echo isEnglish() ? 'Contact Us' : 'सम्पर्क गर्नुहोस्'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
