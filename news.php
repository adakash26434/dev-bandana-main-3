<?php
require_once 'includes/config.php';
$pageTitle = isEnglish() ? 'News & Activities' : 'समाचार तथा क्रियाकलापहरू';
require_once 'includes/header.php';
$L = getLangStrings();

// Get page number
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 9;
$offset = ($page - 1) * $perPage;

// Get news from database
try {
    $db = getDB();

    // Get total count
    $countStmt = $db->query("SELECT COUNT(*) FROM news WHERE is_active = 1");
    $totalNews = $countStmt->fetchColumn();
    $totalPages = ceil($totalNews / $perPage);

    // Get news with pagination
    $stmt = $db->prepare("SELECT * FROM news WHERE is_active = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $news = $stmt->fetchAll();
} catch (Exception $e) {
    $news = [];
    $totalPages = 1;
}
?>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1><?php echo isEnglish() ? 'News & Activities' : 'समाचार तथा क्रियाकलापहरू'; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>"><?php echo $L['home']; ?></a></li>
                <li class="breadcrumb-item active"><?php echo isEnglish() ? 'News' : 'समाचार'; ?></li>
            </ol>
        </nav>
    </div>
</section>

<!-- News Content -->
<section class="section-padding">
    <div class="container">
        <?php if (!empty($news)): ?>
        <div class="row">
            <?php foreach ($news as $item): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="news-card">
                    <div class="news-image">
                        <?php if (!empty($item['image'])): ?>
                        <img src="<?php echo $item['image']; ?>" loading="lazy"  alt="<?php echo getLangField($item, 'title'); ?>">
                        <?php else: ?>
                        <div class="news-placeholder">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <?php endif; ?>
                        <div class="news-date">
                            <span class="day"><?php echo date('d', strtotime($item['created_at'])); ?></span>
                            <span class="month"><?php echo date('M', strtotime($item['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="news-content">
                        <h4><?php echo getLangField($item, 'title'); ?></h4>
                        <p><?php echo truncateText(getLangField($item, 'content'), 120); ?></p>
                        <a href="news-detail.php?id=<?php echo $item['id']; ?>" class="read-more">
                            <?php echo isEnglish() ? 'Read More' : 'थप पढ्नुहोस्'; ?> <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav mt-5">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <!-- Empty State with Sample News -->
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="news-card">
                    <div class="news-image">
                        <div class="news-placeholder">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div class="news-date">
                            <span class="day">15</span>
                            <span class="month">Mar</span>
                        </div>
                    </div>
                    <div class="news-content">
                        <h4><?php echo isEnglish() ? 'Annual General Meeting Successfully Completed' : 'वार्षिक साधारण सभा सफलतापूर्वक सम्पन्न'; ?></h4>
                        <p><?php echo isEnglish() ? 'Our annual general meeting was held successfully with participation of all members...' : 'हाम्रो वार्षिक साधारण सभा सबै सदस्यहरूको सहभागितामा सफलतापूर्वक सम्पन्न भयो...'; ?></p>
                        <span class="read-more text-muted" style="cursor:default;opacity:.55;">
                            <?php echo isEnglish() ? 'Read More' : 'थप पढ्नुहोस्'; ?> <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="news-card">
                    <div class="news-image">
                        <div class="news-placeholder">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div class="news-date">
                            <span class="day">10</span>
                            <span class="month">Feb</span>
                        </div>
                    </div>
                    <div class="news-content">
                        <h4><?php echo isEnglish() ? 'New Branch Inauguration' : 'नयाँ शाखा उद्घाटन'; ?></h4>
                        <p><?php echo isEnglish() ? 'We are delighted to announce the opening of our new branch...' : 'हामी हाम्रो नयाँ शाखा उद्घाटन भएको जानकारी गराउँछौं...'; ?></p>
                        <span class="read-more text-muted" style="cursor:default;opacity:.55;">
                            <?php echo isEnglish() ? 'Read More' : 'थप पढ्नुहोस्'; ?> <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="news-card">
                    <div class="news-image">
                        <div class="news-placeholder">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div class="news-date">
                            <span class="day">05</span>
                            <span class="month">Jan</span>
                        </div>
                    </div>
                    <div class="news-content">
                        <h4><?php echo isEnglish() ? 'Community Health Camp Organized' : 'सामुदायिक स्वास्थ्य शिविर आयोजना'; ?></h4>
                        <p><?php echo isEnglish() ? 'Free health check-up camp was organized for our members and community...' : 'हाम्रा सदस्य र समुदायका लागि निःशुल्क स्वास्थ्य जाँच शिविर आयोजना गरियो...'; ?></p>
                        <span class="read-more text-muted" style="cursor:default;opacity:.55;">
                            <?php echo isEnglish() ? 'Read More' : 'थप पढ्नुहोस्'; ?> <i class="fas fa-arrow-right"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
