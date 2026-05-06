<?php
require_once 'includes/config.php';
$pageTitle = isEnglish() ? 'About Us' : 'हाम्रो बारेमा';
require_once 'includes/header.php';

// Get about page content
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = 'about'");
    $stmt->execute();
    $page = $stmt->fetch();

    // Get team members (board)
    $boardMembers = $db->query("SELECT * FROM team_members WHERE category = 'board' AND is_active = 1 ORDER BY display_order")->fetchAll();
} catch (Exception $e) {
    $page = null;
    $boardMembers = [];
}

// Get about page image from settings (admin controlled) — missing file = no broken <img>
$aboutImageSetting = trim((string) getSetting('about_page_image', ''));
$aboutImageDefault = 'assets/images/about-image.jpg';
$aboutResolved = '';
foreach ([$aboutImageSetting, $aboutImageDefault] as $_abPath) {
    if ($_abPath === '') {
        continue;
    }
    $rel = ltrim($_abPath, '/');
    if (is_readable(ROOT_PATH . $rel)) {
        $aboutResolved = $rel;
        break;
    }
}
$hasAboutImage = $aboutResolved !== '';

// Static section titles (admin editable via pages-v2 static sections)
$visionTitleNp = getSetting('vision_content_title_np', 'हाम्रो दृष्टिकोण');
$visionTitleEn = getSetting('vision_content_title_en', 'Our Vision');
$missionTitleNp = getSetting('mission_content_title_np', 'हाम्रो लक्ष्य');
$missionTitleEn = getSetting('mission_content_title_en', 'Our Mission');
$valuesTitleNp = getSetting('values_content_title_np', 'हाम्रो मूल मान्यताहरू');
$valuesTitleEn = getSetting('values_content_title_en', 'Our Core Values');
?>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1><?php echo htmlspecialchars(is_array($page) ? ($page['title_np'] ?? 'हाम्रो बारेमा') : 'हाम्रो बारेमा', ENT_QUOTES, 'UTF-8'); ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>"><?php echo $L['home']; ?></a></li>
                <li class="breadcrumb-item active"><?php echo $L['about'] ?? 'हाम्रो बारेमा'; ?></li>
            </ol>
        </nav>
    </div>
</section>

<!-- About Content -->
<section class="about-section section-padding" id="about">
    <div class="container">
        <div class="row align-items-start justify-content-center">
            <?php if ($hasAboutImage): ?>
            <div class="col-lg-6 mb-4" data-aos="fade-right">
                <div class="about-image-box">
                    <img src="<?php echo SITE_URL . htmlspecialchars($aboutResolved, ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(ROOT_PATH . $aboutResolved); ?>"
                         alt="<?php echo isEnglish() ? 'About Us' : 'हाम्रो बारेमा'; ?>"
                         class="img-fluid rounded-4"
                         loading="lazy"
                         decoding="async">
                    <div class="about-year-badge">
                        <span class="year"><?php echo getSetting('established_year', '२०७५'); ?></span>
                        <span class="text"><?php echo isEnglish() ? 'Est.' : 'स्थापना'; ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo $hasAboutImage ? 'col-lg-6' : 'col-lg-10 col-xl-9'; ?> mb-4" data-aos="fade-left">
                <div class="about-content-box">
                    <div style="margin-bottom:4px;">
                        <span class="section-tag"><i class="fas fa-building"></i> <?php echo isEnglish() ? 'About Us' : 'हाम्रो बारेमा'; ?></span>
                    </div>
                    <h2><?php echo isEnglish() ? 'Our Introduction' : 'हाम्रो परिचय'; ?></h2>
                    <div class="about-divider"></div>
                    <?php
                    /* मुख्य परिचय: Admin → गतिशील पृष्ठ → slug <code>about</code> मात्र (about_content_* हटाइयो) */
                    $pageArr = is_array($page) ? $page : [];
                    $pageBodyNp = trim((string) ($pageArr['content_np'] ?? ''));
                    $pageBodyEn = trim((string) ($pageArr['content'] ?? ''));
                    $pageHtml = isEnglish()
                        ? ($pageBodyEn !== '' ? $pageBodyEn : $pageBodyNp)
                        : ($pageBodyNp !== '' ? $pageBodyNp : $pageBodyEn);

                    if ($pageHtml !== ''):
                        echo '<div class="intro-text coop-prose">' . $pageHtml . '</div>';
                    else:
                    ?>
                        <div class="intro-text">
                            <p><?php echo isEnglish() ? 'We are a leading community-based financial institution dedicated to serving our members with various financial services and promoting the spirit of cooperation.' : 'हामी समुदायमा आधारित एक अग्रणी वित्तीय संस्था हौं जसले आफ्ना सदस्यहरूलाई विभिन्न वित्तीय सेवाहरू प्रदान गर्दै सहकारिताको भावनालाई प्रवर्द्धन गर्दै आइरहेको छ।'; ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* About Section Enhanced */
.about-image-box {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(26, 95, 42, 0.15);
}

.about-image-box img {
    width: 100%;
    transition: transform 0.5s ease;
}

.about-image-box:hover img {
    transform: scale(1.03);
}

.about-year-badge {
    position: absolute;
    bottom: 20px;
    right: 20px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: #fff;
    padding: 15px 25px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(26, 95, 42, 0.3);
}

.about-year-badge .year {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}

.about-year-badge .text {
    font-size: 0.8rem;
    opacity: 0.9;
}

.about-content-box .section-tag {
    display: inline-flex; /* pill shape को लागि inline-flex राखिन्छ */
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: #fff;
    padding: 8px 20px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 15px;
    /* Fix: h2 सँग same line मा नआओस् भनेर block container बाट पठाइन्छ */
}

.about-content-box h2 {
    font-size: 2.2rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.about-divider {
    width: 80px;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    border-radius: 2px;
    margin-bottom: 25px;
}

.intro-text {
    color: #444;
    line-height: 1.9;
    font-size: 1.05rem;
}

.intro-text p {
    margin-bottom: 15px;
}

/* मुख्य विषयवस्तु: universal.css → .coop-prose */

@media (max-width: 991px) {
    .about-content-box h2 {
        font-size: 1.8rem;
    }
}
</style>

<!-- History Section - Eye Catching Design -->
<section class="history-section-v2 section-padding" id="history">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0" data-aos="fade-right">
                <?php
                /*
                 * Issue #14 FIX:
                 * - Static bank icon हटाइयो
                 * - Admin ले photo upload गर्न मिल्छ (admin/about-settings.php)
                 * - Photo भएमा photo देखिन्छ, नभए icon-only box देखिन्छ
                 */
                $historyPhoto = getSetting('history_photo', '');
                $hasHistoryPhoto = !empty($historyPhoto) && file_exists(ROOT_PATH . $historyPhoto);
                ?>

                <?php if ($hasHistoryPhoto): ?>
                <!-- History photo — admin ले upload गरेको photo -->
                <div class="history-image-box">
                    <img src="<?php echo SITE_URL . $historyPhoto; ?>"
                         alt="<?php echo isEnglish() ? 'Our History' : 'हाम्रो इतिहास'; ?>"
                         class="img-fluid rounded shadow"
                         style="width:100%;height:350px;object-fit:cover;border-radius:12px;">
                    <!-- Established year badge -->
                    <div class="history-year-badge">
                        <?php echo getSetting('established_year', '२०७५'); ?>
                    </div>
                    <div class="history-badge">
                        <i class="fas fa-history"></i>
                    </div>
                </div>

                <?php else: ?>
                <!-- Photo छैन भने modern decorative box देखाउनुहोस् — icon नहटाइएकोले icon-only -->
                <div class="history-image-box history-icon-only">
                    <div class="history-badge">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="history-year-badge">
                        <?php echo getSetting('established_year', '२०७५'); ?>
                    </div>
                    <!-- Static bank icon हटाइयो — empty decorative ring मात्र -->
                    <div class="history-icon-center">
                        <!-- Admin ले about-settings.php बाट photo upload गर्न सक्छ -->
                        <div class="history-icon-ring"></div>
                        <div style="text-align:center;color:#ccc;padding:20px;">
                            <i class="fas fa-camera fa-2x mb-2 d-block" style="opacity:0.4;"></i>
                            <small style="opacity:0.5;font-size:0.8rem;">फोटो उपलब्ध छैन — कृपया फोटो थप्नुहोस्</small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="history-content-v2">
                    <div style="margin-bottom:4px;">
                        <span class="section-tag"><i class="fas fa-history"></i> <?php echo isEnglish() ? 'Our Journey' : 'हाम्रो यात्रा'; ?></span>
                    </div>
                    <h2><?php echo isEnglish() ? 'Our History' : 'हाम्रो इतिहास'; ?></h2>
                    <div class="history-divider"></div>
                    <div class="history-text coop-prose">
                        <?php
                        $historyContent = isEnglish() ? getSetting('history_content_en', '') : getSetting('history_content_np', '');
                        if ($historyContent):
                            echo $historyContent;
                        else:
                        ?>
                        <p><?php echo isEnglish() ? 'Our cooperative has a rich history of serving the community. Established with the vision of financial inclusion, we have grown to become one of the most trusted financial institutions in our area.' : 'हाम्रो सहकारीको समुदायको सेवामा समृद्ध इतिहास छ। वित्तीय समावेशीताको दृष्टिकोणका साथ स्थापित, हामी हाम्रो क्षेत्रमा सबैभन्दा विश्वसनीय वित्तीय संस्थाहरू मध्ये एक बन्न विकसित भएका छौं।'; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Vision & Mission Section - Eye Catching Design -->
<section class="vision-section-v2 section-padding bg-light" id="vision">
    <div class="container">
        <div class="section-header text-center mb-5" data-aos="fade-up">
            <div class="section-badge-wrap">
                <span class="section-badge">
                    <i class="fas fa-eye"></i>
                    <?php echo isEnglish() ? 'Our Purpose' : 'हाम्रो उद्देश्य'; ?>
                </span>
            </div>
            <h2><?php echo isEnglish() ? 'Vision & Mission' : 'दृष्टि र लक्ष्य'; ?></h2>
            <div class="section-divider"></div>
        </div>
        <div class="row g-4">
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="vision-card-v2 vision">
                    <div class="vision-card-glow"></div>
                    <div class="vision-icon-v2">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="vision-card-content coop-prose">
                        <h4><?php echo htmlspecialchars(isEnglish() ? $visionTitleEn : $visionTitleNp, ENT_QUOTES, 'UTF-8'); ?></h4>
                        <?php
                        $visionContent = isEnglish() ? getSetting('vision_content_en', '') : getSetting('vision_content_np', '');
                        if ($visionContent):
                            echo '<p>' . $visionContent . '</p>';
                        else:
                        ?>
                        <p><?php echo isEnglish() ? 'To be the most trusted and preferred cooperative in our community.' : 'समुदायमा सबैभन्दा विश्वसनीय र रुचाइएको सहकारी संस्था बन्नु।'; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="vision-card-decoration"></div>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="vision-card-v2 mission">
                    <div class="vision-card-glow"></div>
                    <div class="vision-icon-v2">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <div class="vision-card-content coop-prose">
                        <h4><?php echo htmlspecialchars(isEnglish() ? $missionTitleEn : $missionTitleNp, ENT_QUOTES, 'UTF-8'); ?></h4>
                        <?php
                        $missionContent = isEnglish() ? getSetting('mission_content_en', '') : getSetting('mission_content_np', '');
                        if ($missionContent):
                            echo '<p>' . $missionContent . '</p>';
                        else:
                        ?>
                        <p><?php echo isEnglish() ? 'To provide quality financial services while promoting the spirit of cooperation and helping members achieve their financial goals.' : 'सहकारिताको भावनालाई प्रवर्द्धन गर्दै सदस्यहरूलाई उनीहरूको वित्तीय लक्ष्य हासिल गर्न मद्दत गर्ने गुणस्तरीय वित्तीय सेवा प्रदान गर्नु।'; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="vision-card-decoration"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* History Section V2 */
.history-section-v2 {
    background: linear-gradient(135deg, #f8fff9 0%, #e8f5e9 100%);
    position: relative;
    overflow: hidden;
}

.history-section-v2::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(26, 95, 42, 0.05), transparent 70%);
    border-radius: 50%;
}

.history-image-box {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(26, 95, 42, 0.2);
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    min-height: 350px;
}

.history-image-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.3;
}

.history-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.history-icon-float {
    width: 78px;
    height: 78px;
    background: rgba(255,255,255,0.15);
    backdrop-filter: blur(10px);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: floatIcon 3s ease-in-out infinite;
}

@keyframes floatIcon {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}

.history-icon-float i {
    font-size: 32px;
    color: #fff;
}

/* Icon-only history box: फोटो बिना आइकन मात्र देखाउँछ */
.history-image-box.history-icon-only {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 380px;
}

.history-icon-center {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100px;
    height: 100px;
    animation: floatIcon 3s ease-in-out infinite;
}

.history-icon-center i {
    font-size: 48px;
    color: rgba(255, 255, 255, 0.9);
    position: relative;
    z-index: 2;
    filter: drop-shadow(0 4px 20px rgba(255,255,255,0.3));
}

.history-icon-ring {
    position: absolute;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 3px solid rgba(255, 255, 255, 0.25);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation: pulseRing 2.5s ease-in-out infinite;
}

@keyframes pulseRing {
    0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
    50% { transform: translate(-50%, -50%) scale(1.15); opacity: 1; }
}

.history-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    width: 42px;
    height: 42px;
    background: var(--secondary-color);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 16px;
    z-index: 2;
    box-shadow: 0 6px 20px rgba(245, 190, 0, 0.35);
}

.history-year-badge {
    position: absolute;
    bottom: 20px;
    right: 20px;
    background: #fff;
    color: var(--primary-color);
    padding: 12px 25px;
    border-radius: 30px;
    font-size: 1.2rem;
    font-weight: 700;
    z-index: 2;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.history-content-v2 .section-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: #fff;
    padding: 8px 20px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 20px;
}

.history-content-v2 h2 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.history-divider {
    width: 80px;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    border-radius: 2px;
    margin-bottom: 25px;
}

.history-text {
    color: #555;
    line-height: 1.9;
    font-size: 1.05rem;
}

/* Vision Section V2 */
.vision-section-v2 {
    position: relative;
}
/* .section-tag र .section-divider-center — global style.css को
   .section-badge र .section-divider ले replace गरिसक्यो */

.vision-card-v2 {
    background: #fff;
    border-radius: 24px;
    padding: 40px 35px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 15px 60px rgba(0,0,0,0.08);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    height: 100%;
}

.vision-card-v2:hover {
    transform: translateY(-10px);
    box-shadow: 0 30px 80px rgba(26, 95, 42, 0.18);
}

.vision-card-v2::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
}

.vision-card-v2.mission::before {
    background: linear-gradient(90deg, var(--secondary-color), #e8a800);
}

.vision-card-glow {
    position: absolute;
    top: -100px;
    right: -100px;
    width: 250px;
    height: 250px;
    background: radial-gradient(circle, rgba(26, 95, 42, 0.08), transparent 70%);
    border-radius: 50%;
}

.vision-card-v2.mission .vision-card-glow {
    background: radial-gradient(circle, rgba(245, 190, 0, 0.1), transparent 70%);
}

.vision-icon-v2 {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 25px;
    box-shadow: 0 15px 40px rgba(26, 95, 42, 0.25);
    position: relative;
    z-index: 1;
}

.vision-card-v2.mission .vision-icon-v2 {
    background: linear-gradient(135deg, var(--secondary-color), #e8a800);
    box-shadow: 0 15px 40px rgba(245, 190, 0, 0.3);
}

.vision-icon-v2 i {
    font-size: 32px;
    color: #fff;
}

.vision-card-content {
    position: relative;
    z-index: 1;
}

.vision-card-content h4 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.vision-card-v2.mission .vision-card-content h4 {
    color: var(--secondary-color, #ffc107); /* secondary colour — CSS variable */
}

.vision-card-content p {
    color: #666;
    line-height: 1.8;
    font-size: 1rem;
    margin: 0;
}

/* coop-prose margins inside vision cards */
.vision-card-content.coop-prose p {
    margin-bottom: 0.9em;
}
.vision-card-content.coop-prose p:last-child {
    margin-bottom: 0;
}

.vision-card-decoration {
    position: absolute;
    bottom: -30px;
    left: -30px;
    width: 100px;
    height: 100px;
    border: 3px solid rgba(26, 95, 42, 0.1);
    border-radius: 50%;
}

.vision-card-v2.mission .vision-card-decoration {
    border-color: rgba(245, 190, 0, 0.15);
}

@media (max-width: 991px) {
    .history-content-v2 h2 {
        font-size: 2rem;
    }
    .history-image-box {
        min-height: 280px;
    }
}

@media (max-width: 767px) {
    .history-content-v2 h2 {
        font-size: 1.7rem;
    }
    .vision-card-v2 {
        padding: 30px 25px;
    }
}
</style>

<!-- Leadership Messages Section -->
<?php
$chairmanMessage = getSetting('chairman_message_np', '');
$chairmanName = getSetting('chairman_name', 'अध्यक्ष');
$chairmanPhoto = getSetting('chairman_photo', '');
$ceoMessage = getSetting('ceo_message_np', '');
$ceoName = getSetting('ceo_name', 'प्रमुख कार्यकारी अधिकृत');
$ceoPhoto = getSetting('ceo_photo', '');
?>
<?php if ($chairmanMessage || $ceoMessage): ?>
<section class="leadership-messages-about section-padding bg-light" id="chairman">
    <div class="container">
        <div class="section-header text-center mb-4" data-aos="fade-up">
            <div class="section-badge-wrap">
                <span class="section-badge"><i class="fas fa-quote-left"></i> <?php echo isEnglish() ? 'Leadership' : 'नेतृत्व'; ?></span>
            </div>
            <h2><?php echo isEnglish() ? 'Messages from Leadership' : 'नेतृत्वबाट सन्देश'; ?></h2>
            <div class="section-divider"></div>
            <p><?php echo isEnglish() ? 'Words from our Chairman and CEO' : 'हाम्रो अध्यक्ष र प्रमुख कार्यकारी अधिकृतका शब्दहरू'; ?></p>
        </div>

        <?php if ($chairmanMessage): ?>
        <div class="leadership-message-full mb-5" id="chairman-message">
            <div class="row align-items-center">
                <div class="col-lg-3 col-md-4 text-center mb-4 mb-md-0">
                    <div class="leader-photo-large">
                        <?php if ($chairmanPhoto): ?>
                        <img src="<?php echo SITE_URL . $chairmanPhoto; ?>?v=<?php echo time(); ?>" alt="<?php echo $chairmanName; ?>">
                        <?php else: ?>
                        <div class="photo-placeholder-large">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h4 class="mt-3"><?php echo $chairmanName; ?></h4>
                    <span class="leader-position"><?php echo isEnglish() ? 'Chairman' : 'अध्यक्ष'; ?></span>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="message-content-full">
                        <i class="fas fa-quote-left quote-icon-large"></i>
                        <div class="message-text-full coop-prose">
                            <?php echo $chairmanMessage; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($ceoMessage): ?>
        <div class="leadership-message-full" id="ceo-message">
            <div class="row align-items-center flex-md-row-reverse">
                <div class="col-lg-3 col-md-4 text-center mb-4 mb-md-0">
                    <div class="leader-photo-large">
                        <?php if ($ceoPhoto): ?>
                        <img src="<?php echo SITE_URL . $ceoPhoto; ?>?v=<?php echo time(); ?>" alt="<?php echo $ceoName; ?>">
                        <?php else: ?>
                        <div class="photo-placeholder-large">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h4 class="mt-3"><?php echo $ceoName; ?></h4>
                    <span class="leader-position"><?php echo isEnglish() ? 'Chief Executive Officer' : 'प्रमुख कार्यकारी अधिकृत'; ?></span>
                </div>
                <div class="col-lg-9 col-md-8">
                    <div class="message-content-full">
                        <i class="fas fa-quote-left quote-icon-large"></i>
                        <div class="message-text-full coop-prose">
                            <?php echo $ceoMessage; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- Core Values Section - Consolidated -->
<section class="values-section section-padding" id="values">
    <div class="container">
        <div class="section-header text-center mb-4" data-aos="fade-up">
            <div class="section-badge-wrap">
                <span class="section-badge"><i class="fas fa-heart"></i> <?php echo isEnglish() ? 'Values' : 'मूल्यहरू'; ?></span>
            </div>
            <h2><?php echo htmlspecialchars(isEnglish() ? $valuesTitleEn : $valuesTitleNp, ENT_QUOTES, 'UTF-8'); ?></h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="0">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h5><?php echo isEnglish() ? 'Integrity' : 'इमानदारिता'; ?></h5>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h5><?php echo isEnglish() ? 'Transparency' : 'पारदर्शिता'; ?></h5>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h5><?php echo isEnglish() ? 'Cooperation' : 'सहयोग'; ?></h5>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h5><?php echo isEnglish() ? 'Excellence' : 'उत्कृष्टता'; ?></h5>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.value-card {
    background: var(--white);
    padding: 30px 20px;
    border-radius: 15px;
    text-align: center;
    box-shadow: 0 5px 25px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.value-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 40px rgba(0,0,0,0.12);
}

.value-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
}

.value-icon i {
    font-size: 24px;
    color: var(--white);
}

.value-card h5 {
    margin: 0;
    font-weight: 600;
    color: var(--text-color);
}
</style>

<!-- Board Members - Same design as team.php -->
<?php if (!empty($boardMembers)): ?>
<section class="team-section section-padding bg-light" id="board">
    <div class="container">
        <div class="section-header text-center mb-4" data-aos="fade-up">
            <div class="section-badge-wrap">
                <span class="section-badge"><i class="fas fa-users-cog"></i> <?php echo isEnglish() ? 'Board' : 'समिति'; ?></span>
            </div>
            <h2><?php echo isEnglish() ? 'Board of Directors' : 'सञ्चालक समिति'; ?></h2>
            <div class="section-divider"></div>
            <p><?php echo isEnglish() ? 'Leadership team guiding our cooperative' : 'हाम्रो संस्थाको नेतृत्व गर्ने समिति'; ?></p>
        </div>

        <div class="row justify-content-center">
            <?php foreach ($boardMembers as $index => $member): ?>
            <div class="col-lg-3 col-md-4 col-sm-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 50; ?>">
                <div class="team-card-circular <?php echo $index === 0 ? 'featured' : ''; ?>">
                    <div class="team-photo-circular">
                        <?php if ($member['photo']): ?>
                            <img src="<?php echo $member['photo']; ?>" loading="lazy"  alt="<?php echo $member['name']; ?>">
                        <?php else: ?>
                            <div class="team-placeholder-circular"><i class="fas fa-user"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="team-info-circular">
                        <h5><?php echo $member['name']; ?></h5>
                        <?php if (!empty($member['name_en'])): ?>
                        <p class="team-name-en"><?php echo $member['name_en']; ?></p>
                        <?php endif; ?>
                        <span class="team-position-badge"><?php echo $member['position_np'] ?: $member['position']; ?></span>
                        <?php if (!empty($member['phone']) || !empty($member['email'])): ?>
                        <div class="team-contact-circular">
                            <?php if (!empty($member['phone'])): ?>
                                <a href="tel:<?php echo $member['phone']; ?>" title="<?php echo $member['phone']; ?>"><i class="fas fa-phone"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($member['email'])): ?>
                                <a href="mailto:<?php echo $member['email']; ?>" title="<?php echo $member['email']; ?>"><i class="fas fa-envelope"></i></a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4" data-aos="fade-up">
            <a href="team.php" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-users"></i> <?php echo isEnglish() ? 'View All Team Members' : 'सबै सदस्यहरू हेर्नुहोस्'; ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Statistics -->
<section class="stats-section" id="stats">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="0">
                <div class="stat-box">
                    <div class="stat-number"><?php echo getSetting('total_members', '५०००'); ?>+</div>
                    <div class="stat-label"><?php echo isEnglish() ? 'Members' : 'सदस्यहरू'; ?></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-box">
                    <div class="stat-number"><?php echo getSetting('years_experience', '२०'); ?>+</div>
                    <div class="stat-label"><?php echo isEnglish() ? 'Years Experience' : 'वर्षको अनुभव'; ?></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-box">
                    <div class="stat-number"><?php echo getSetting('total_services', '१०'); ?>+</div>
                    <div class="stat-label"><?php echo isEnglish() ? 'Services' : 'सेवाहरू'; ?></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-box">
                    <div class="stat-number"><?php echo getSetting('satisfaction_rate', '९९'); ?>%</div>
                    <div class="stat-label"><?php echo isEnglish() ? 'Satisfied Customers' : 'सन्तुष्ट ग्राहक'; ?></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
