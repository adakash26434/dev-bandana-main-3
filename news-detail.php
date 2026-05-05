<?php
require_once 'includes/config.php';

// Get news ID
$newsId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pageOgImage = '';

// Get news item
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM news WHERE id = ? AND is_active = 1");
    $stmt->execute([$newsId]);
    $news = $stmt->fetch();

    if (!$news) {
        redirect('news.php');
    }

    $pageTitle = getLangField($news, 'title');
    $bodyForDesc = getLangField($news, 'content');
    $pageDescription = function_exists('seo_meta_description_from_html')
        ? seo_meta_description_from_html($bodyForDesc)
        : '';
    if ($pageDescription === '') {
        $pageDescription = $pageTitle;
    }
    $imgRaw = trim((string) ($news['image'] ?? ''));
    if ($imgRaw !== '' && ($safe = safe_public_upload_path($imgRaw)) !== '') {
        $pageOgImage = $safe;
    }

    // Get related news (other news)
    $relatedStmt = $db->prepare("SELECT * FROM news WHERE id != ? AND is_active = 1 ORDER BY created_at DESC LIMIT 3");
    $relatedStmt->execute([$newsId]);
    $relatedNews = $relatedStmt->fetchAll();

} catch (Exception $e) {
    redirect('news.php');
}

require_once 'includes/header.php';
$L = getLangStrings();
?>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1><?php echo isEnglish() ? 'News Detail' : 'समाचार विवरण'; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>"><?php echo $L['home']; ?></a></li>
                <li class="breadcrumb-item"><a href="news.php"><?php echo isEnglish() ? 'News' : 'समाचार'; ?></a></li>
                <li class="breadcrumb-item active"><?php echo truncateText(getLangField($news, 'title'), 30); ?></li>
            </ol>
        </nav>
    </div>
</section>

<!-- News Detail Content -->
<section class="section-padding">
    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <article class="news-detail-article">
                    <div class="news-detail-header">
                        <h1><?php echo getLangField($news, 'title'); ?></h1>
                        <div class="news-meta">
                            <span class="news-date">
                                <i class="fas fa-calendar-alt"></i>
                                <?php echo date('Y-m-d', strtotime($news['created_at'])); ?>
                            </span>
                        </div>
                    </div>

                    <?php if (!empty($news['image'])): ?>
                    <div class="news-detail-image">
                        <img src="<?php echo $news['image']; ?>" loading="lazy"  alt="<?php echo getLangField($news, 'title'); ?>">
                    </div>
                    <?php endif; ?>

                    <div class="news-detail-content coop-prose">
                        <?php echo getLangField($news, 'content'); ?>
                    </div>

                    <div class="news-share">
                        <span><?php echo isEnglish() ? 'Share:' : 'सेयर गर्नुहोस्:'; ?></span>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . 'news-detail.php?id=' . $news['id']); ?>" target="_blank" class="share-btn facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . 'news-detail.php?id=' . $news['id']); ?>&text=<?php echo urlencode(getLangField($news, 'title')); ?>" target="_blank" class="share-btn twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://wa.me/?text=<?php echo urlencode(getLangField($news, 'title') . ' ' . SITE_URL . 'news-detail.php?id=' . $news['id']); ?>" target="_blank" class="share-btn whatsapp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>

                    <div class="news-navigation">
                        <a href="news.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> <?php echo isEnglish() ? 'Back to News' : 'समाचारमा फर्कनुहोस्'; ?>
                        </a>
                    </div>
                </article>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <?php if (!empty($relatedNews)): ?>
                <div class="sidebar-widget">
                    <h4 class="widget-title"><?php echo isEnglish() ? 'Related News' : 'सम्बन्धित समाचार'; ?></h4>
                    <div class="related-news-list">
                        <?php foreach ($relatedNews as $related): ?>
                        <div class="related-news-item">
                            <div class="related-news-image">
                                <?php if (!empty($related['image'])): ?>
                                <img src="<?php echo $related['image']; ?>" loading="lazy"  alt="<?php echo getLangField($related, 'title'); ?>">
                                <?php else: ?>
                                <div class="related-placeholder">
                                    <i class="fas fa-newspaper"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="related-news-info">
                                <h5><a href="news-detail.php?id=<?php echo $related['id']; ?>"><?php echo truncateText(getLangField($related, 'title'), 50); ?></a></h5>
                                <span class="date"><?php echo date('Y-m-d', strtotime($related['created_at'])); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="sidebar-widget">
                    <h4 class="widget-title"><?php echo isEnglish() ? 'Quick Links' : 'द्रुत लिंकहरू'; ?></h4>
                    <ul class="quick-links">
                        <li><a href="notices.php"><i class="fas fa-bullhorn"></i> <?php echo isEnglish() ? 'Notices' : 'सूचनाहरू'; ?></a></li>
                        <li><a href="downloads.php"><i class="fas fa-download"></i> <?php echo isEnglish() ? 'Downloads' : 'डाउनलोडहरू'; ?></a></li>
                        <li><a href="gallery.php"><i class="fas fa-images"></i> <?php echo isEnglish() ? 'Gallery' : 'ग्यालरी'; ?></a></li>
                        <li><a href="contact.php"><i class="fas fa-envelope"></i> <?php echo isEnglish() ? 'Contact Us' : 'सम्पर्क'; ?></a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.news-detail-article {
    background: var(--white);
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
}

.news-detail-header {
    margin-bottom: 25px;
}

.news-detail-header h1 {
    font-size: 1.8rem;
    color: var(--text-color);
    margin-bottom: 15px;
    line-height: 1.4;
}

.news-meta {
    display: flex;
    gap: 20px;
    color: var(--text-light);
    font-size: 0.9rem;
}

.news-meta i {
    margin-right: 5px;
    color: var(--primary-color);
}

.news-detail-image {
    margin-bottom: 25px;
    border-radius: 10px;
    overflow: hidden;
}

.news-detail-image img {
    width: 100%;
    height: auto;
}

.news-detail-content {
    color: var(--text-color);
    margin-bottom: 30px;
}

.news-share {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 0;
    border-top: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 20px;
}

.share-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    transition: all 0.3s;
}

.share-btn.facebook { background: #3b5998; }
.share-btn.twitter { background: #1da1f2; }
.share-btn.whatsapp { background: #25d366; }

.share-btn:hover {
    transform: scale(1.1);
    color: var(--white);
}

.news-navigation {
    text-align: center;
}

/* Sidebar */
.sidebar-widget {
    background: var(--white);
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

.widget-title {
    font-size: 1.2rem;
    color: var(--primary-color);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--primary-color);
}

.related-news-item {
    display: flex;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-color);
}

.related-news-item:last-child {
    border-bottom: none;
}

.related-news-image {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    overflow: hidden;
    flex-shrink: 0;
}

.related-news-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.related-placeholder {
    width: 100%;
    height: 100%;
    background: var(--light-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--border-color);
}

.related-news-info h5 {
    font-size: 0.9rem;
    margin-bottom: 5px;
    line-height: 1.4;
}

.related-news-info h5 a {
    color: var(--text-color);
}

.related-news-info h5 a:hover {
    color: var(--primary-color);
}

.related-news-info .date {
    font-size: 0.8rem;
    color: var(--text-light);
}

.quick-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

.quick-links li {
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.quick-links li:last-child {
    border-bottom: none;
}

.quick-links a {
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.quick-links a:hover {
    color: var(--primary-color);
}

.quick-links a i {
    color: var(--primary-color);
    width: 20px;
}

@media (max-width: 767px) {
    .news-detail-article {
        padding: 20px;
    }

    .news-detail-header h1 {
        font-size: 1.4rem;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
