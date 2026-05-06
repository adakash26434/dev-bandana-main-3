<?php
/**
 * About (हाम्रो बारेमा) — एकै ठाउँबाट व्यवस्थापन shortcut
 * सार्वजनिक `about.php` ले content तान्ने स्रोतहरू:
 * - परिचय: pages.slug = about  (Dynamic pages)
 * - इतिहास: About Settings (history photo + history text)
 * - दृष्टि/लक्ष्य/मूल मान्यता/अध्यक्ष/CEO सन्देश: Pages (About Sections)
 * - नेतृत्व फोटो/नाम: Settings → नेतृत्व सन्देश
 * - संस्थागत प्रोफाइल: Institutional Profile page
 */
$pageTitle = 'हाम्रो बारेमा';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';

// Find dynamic about page id (slug=about)
$aboutId = 0;
try {
    $db = getDB();
    $st = $db->prepare("SELECT id FROM pages WHERE slug = 'about' LIMIT 1");
    $st->execute();
    $aboutId = (int) ($st->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $aboutId = 0;
}

echo adminPageHeader('हाम्रो बारेमा', 'fa-circle-info', 'About पेजका सबै sections एकै ठाउँबाट खोल्नुहोस्', '');
$flash = getFlash(); if ($flash) echo adminAlert($flash['type'], $flash['message']);
?>

<div class="container-fluid py-3">
    <div class="row justify-content-center">
        <div class="col-12 col-xxl-10">

            <div class="alert alert-info border mb-3">
                <div class="fw-semibold mb-1"><i class="fas fa-link me-1"></i>सार्वजनिक पेजसँग जडान</div>
                <div class="small mb-0">
                    यहाँका button हरूले सार्वजनिक <code>about.php</code> मा देखिने सामग्री नै edit गर्ने ठाउँ खोल्छन्।
                    दोहोरिएको (double) होइन — हरेक section को स्रोत छुट्टै छ।
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="mb-2"><i class="fas fa-file-pen me-2 text-success"></i>हाम्रो परिचय (Dynamic)</h5>
                            <div class="text-muted small mb-3">सार्वजनिक “हाम्रो परिचय” (about page header paragraph) यहीँबाट बदलिन्छ।</div>
                            <a class="btn btn-success" href="pages.php?action=edit<?php echo $aboutId > 0 ? '&id=' . $aboutId : ''; ?>&tab=dynamic">
                                <i class="fas fa-pen-to-square me-1"></i>परिचय सम्पादन खोल्नुहोस्
                            </a>
                            <?php if ($aboutId <= 0): ?>
                                <div class="small text-warning mt-2">slug <code>about</code> भएको पृष्ठ भेटिएन — “नयाँ गतिशील पृष्ठ” बाट बनाउनुहोस्।</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="mb-2"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>हाम्रो इतिहास (Photo + Text)</h5>
                            <div class="text-muted small mb-3">सार्वजनिक “हाम्रो इतिहास” section (photo + लेख) यहीँबाट बदलिन्छ।</div>
                            <a class="btn btn-primary" href="about-settings.php?panel=content">
                                <i class="fas fa-gear me-1"></i>इतिहास सेटिङ खोल्नुहोस्
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="mb-2"><i class="fas fa-list-check me-2 text-warning"></i>About Sections</h5>
                            <div class="text-muted small mb-3">
                                दृष्टि, लक्ष्य, मूल मान्यता, अध्यक्ष/CEO सन्देश (text)।
                            </div>
                            <a class="btn btn-warning" href="pages.php?tab=static">
                                <i class="fas fa-layer-group me-1"></i>Sections सम्पादन खोल्नुहोस्
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="mb-2"><i class="fas fa-user-tie me-2 text-danger"></i>नेतृत्व (नाम + फोटो)</h5>
                            <div class="text-muted small mb-3">अध्यक्ष/CEO को नाम र फोटो (text होइन) Settings बाट हुन्छ।</div>
                            <a class="btn btn-outline-danger" href="settings.php">
                                <i class="fas fa-sliders me-1"></i>Settings खोल्नुहोस्
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4 d-flex flex-wrap gap-2 align-items-center justify-content-between">
                            <div>
                                <div class="fw-semibold"><i class="fas fa-building-columns me-2 text-success"></i>संस्थागत प्रोफाइल</div>
                                <div class="text-muted small">“संस्थागत प्रोफाइल” menu item को data अलग पृष्ठबाट manage हुन्छ।</div>
                            </div>
                            <a class="btn btn-outline-success" href="institutional-profile.php">
                                <i class="fas fa-arrow-right me-1"></i>खोल्नुहोस्
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once 'includes/admin-footer.php'; ?>

