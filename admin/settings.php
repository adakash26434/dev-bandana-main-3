<?php
$pageTitle = 'साइट सेटिङ्स';
require_once 'includes/admin-header.php';
require_once 'includes/admin-ui.php';

$updateSuccess = false;
$updateError = '';
$canEditFooterDev = !empty($_SESSION['is_superadmin']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();

/* CSRF सुरक्षा: POST अनुरोध प्रमाणित गर्नुहोस् */
checkCSRF();

        // Update text settings
        /* site_version थपियो — admin ले version number अपडेट गर्न सक्छ */
        $textSettings = ['site_name', 'site_name_en', 'site_slogan', 'site_slogan_en', 'meta_description', 'meta_description_en', 'meta_keywords', 'phone', 'mobile', 'email', 'address', 'facebook_url', 'youtube_url', 'twitter_url', 'instagram_url', 'whatsapp_number', 'about_short', 'hero_title', 'hero_subtitle', 'footer_text', 'internet_banking_url', 'play_store_url', 'app_store_url', 'developer_name', 'developer_url', 'google_map_url', 'working_hours', 'saturday_hours', 'primary_color', 'secondary_color', 'header_color', 'footer_color', 'topbar_color', 'chairman_name', 'ceo_name', 'site_version', 'site_launch_date', 'google_client_id', 'google_client_secret', 'facebook_app_id', 'facebook_app_secret', 'twofa_admin_required', 'twofa_member_required'];

        /* Color inputs सुरक्षित/valid hex मा मात्र save गर्ने:
           invalid value ले UI text invisible/unstyled बनाउने risk कम हुन्छ। */
        $sanitizeHexColor = function ($value, $fallback = 'var(--primary-color)') {
            $value = trim((string)$value);
            if (!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value)) return $fallback;
            // 3-digit hex लाई 6-digit मा normalize
            if (strlen($value) === 4) {
                $value = '#' . $value[1] . $value[1] . $value[2] . $value[2] . $value[3] . $value[3];
            }
            return strtolower($value);
        };

        foreach ($textSettings as $key) {
            if (isset($_POST[$key])) {
                if (in_array($key, ['twofa_admin_required','twofa_member_required'], true) && empty($_SESSION['is_superadmin'])) {
                    continue;
                }
                if (in_array($key, ['footer_text', 'developer_name', 'developer_url'], true) && !$canEditFooterDev) {
                    continue;
                }
                $value = $_POST[$key];
                if (in_array($key, ['meta_description', 'meta_description_en'], true)) {
                    $value = function_exists('clean_text') ? clean_text((string) $value, 400) : trim((string) $value);
                } elseif ($key === 'meta_keywords') {
                    $value = function_exists('clean_text') ? clean_text((string) $value, 500) : trim((string) $value);
                } elseif ($key === 'site_slogan_en') {
                    $value = function_exists('clean_text') ? clean_text((string) $value, 300) : trim((string) $value);
                }
                if ($key === 'primary_color') {
                    $value = $sanitizeHexColor($value, 'var(--primary-color)');
                } elseif ($key === 'secondary_color') {
                    $value = $sanitizeHexColor($value, '#c0392b');
                } elseif ($key === 'header_color') {
                    $value = $sanitizeHexColor($value, '#c0392b');
                } elseif ($key === 'footer_color') {
                    $value = $sanitizeHexColor($value, 'var(--primary-color)');
                } elseif ($key === 'topbar_color') {
                    $value = $sanitizeHexColor($value, '#c0392b');
                }
                updateSetting($key, $value);
            }
        }
        // checkbox fallback (unchecked हुँदा key नआउने)
        if (!empty($_SESSION['is_superadmin'])) {
            if (!isset($_POST['twofa_admin_required'])) updateSetting('twofa_admin_required', '0');
            if (!isset($_POST['twofa_member_required'])) updateSetting('twofa_member_required', '0');
        }

        $uploadErrors = [];

        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['logo'], 'logo');
            if ($upload['success']) {
                $result = updateSetting('logo', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = 'Logo save गर्न सकिएन';
                }
            } else {
                $uploadErrors[] = 'Logo upload: ' . ($upload['message'] ?? 'Unknown error');
            }
        }

        // Handle chairman photo upload
        if (isset($_FILES['chairman_photo']) && $_FILES['chairman_photo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['chairman_photo'], 'leadership');
            if ($upload['success']) {
                $result = updateSetting('chairman_photo', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = 'Chairman photo save गर्न सकिएन';
                }
            } else {
                $uploadErrors[] = 'Chairman photo upload: ' . ($upload['message'] ?? 'Unknown error');
            }
        }

        // Handle CEO photo upload
        if (isset($_FILES['ceo_photo']) && $_FILES['ceo_photo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['ceo_photo'], 'leadership');
            if ($upload['success']) {
                $result = updateSetting('ceo_photo', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = 'CEO photo save गर्न सकिएन';
                }
            } else {
                $uploadErrors[] = 'CEO photo upload: ' . ($upload['message'] ?? 'Unknown error');
            }
        }

        // Handle mobile app photo upload
        if (isset($_FILES['mobile_app_photo']) && $_FILES['mobile_app_photo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['mobile_app_photo'], 'app');
            if ($upload['success']) {
                $result = updateSetting('mobile_app_photo', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = 'Mobile app photo save गर्न सकिएन';
                }
            } else {
                $uploadErrors[] = 'Mobile app photo upload: ' . ($upload['message'] ?? 'Unknown error');
            }
        }

        // Handle about page image upload
        if (isset($_FILES['about_page_image']) && $_FILES['about_page_image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['about_page_image'], 'pages');
            if ($upload['success']) {
                $result = updateSetting('about_page_image', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = 'About page image save गर्न सकिएन';
                }
            } else {
                $uploadErrors[] = 'About page image upload: ' . ($upload['message'] ?? 'Unknown error');
            }
        }

        // Handle himal background photo upload
        if (isset($_FILES['himal_bg']) && $_FILES['himal_bg']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['himal_bg'], 'header');
            if ($upload['success']) {
                $result = updateSetting('himal_bg', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = 'Himal photo save गर्न सकिएन';
                }
            } else {
                $uploadErrors[] = 'Himal photo upload: ' . ($upload['message'] ?? 'Unknown error');
            }
        }

        // SEO — default Open Graph / Facebook share image (प्रति सहकारी फरक)
        if (isset($_FILES['seo_og_image']) && $_FILES['seo_og_image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['seo_og_image'], 'seo');
            if ($upload['success']) {
                $result = updateSetting('seo_og_image', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = 'SEO share image save गर्न सकिएन';
                }
            } else {
                $uploadErrors[] = 'SEO share image upload: ' . ($upload['message'] ?? 'Unknown error');
            }
        }
        if (!empty($_POST['clear_seo_og_image'])) {
            updateSetting('seo_og_image', '');
        }

        // Log any upload errors
        if (!empty($uploadErrors)) {
            error_log('Settings upload errors: ' . implode(', ', $uploadErrors));
        }

        $updateSuccess = true;
        setFlash('success', 'सेटिङ्स सफलतापूर्वक अपडेट भयो।');

        // Use JavaScript redirect to ensure session is saved
        echo '<script>window.location.href = "settings.php";</script>';
        exit();

    } catch (Exception $e) {
        $updateError = $e->getMessage();
        setFlash('error', 'त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।');
    }
}

// Get current settings
try {
    $db = getDB();
    $settingsStmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
    $settings = [];
    while ($row = $settingsStmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $settings = [];
}
?>

<?php
echo adminPageHeader('साइट सेटिङ्स', 'fa-cogs', 'आकाश सहकारी वेबसाइटको सामान्य सेटिङ्स');
?>
<?php echo adminHelpTip('यो पृष्ठबाट Website को नाम, Logo, रंग, र सम्पर्क विवरण परिवर्तन गर्न सकिन्छ।', ['Logo बदल्न: "Site Logo" section मा जानुहोस्।', 'रंग बदल्न: "Primary Color" section मा color picker प्रयोग गर्नुहोस्।', 'परिवर्तन गरेपछि: "Save" बटन थिच्नुहोस्।']); ?>
<?php $_flash = getFlash(); if ($_flash) echo adminAlert($_flash['type'], $_flash['message']); ?>
<?php
$panel = (string)($_GET['panel'] ?? 'general');
if (!in_array($panel, ['general', 'branding'], true)) {
    $panel = 'general';
}
?>
<form method="POST" action="settings.php" enctype="multipart/form-data" class="settings-page-compact">
    <?php echo csrfField(); ?>
    <ul class="nav admin-nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $panel === 'general' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#settings-general-tab" type="button" role="tab">
                <i class="fas fa-sliders me-1"></i> सामान्य सेटिङ्स
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $panel === 'branding' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#settings-branding-tab" type="button" role="tab">
                <i class="fas fa-image me-1"></i> ब्रान्डिङ / मिडिया
            </button>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade <?php echo $panel === 'general' ? 'show active' : ''; ?>" id="settings-general-tab" role="tabpanel">
        <div class="alert alert-light border settings-tab-note mb-3">
            <i class="fas fa-circle-info me-2 text-primary"></i>
            वेबसाइटको नाम, SEO, सम्पर्क, social links, banking links, नेतृत्व र footer सम्बन्धी मुख्य सेटिङ्स यही tab मा छन्।
        </div>
        <div class="stg-subtabs mb-3" data-stg-panel="general">
            <button type="button" class="stg-subtab-btn active" data-stg-group="identity"><i class="fas fa-globe me-1"></i> साइट / SEO</button>
            <button type="button" class="stg-subtab-btn" data-stg-group="contact"><i class="fas fa-address-book me-1"></i> सम्पर्क / Maps</button>
            <button type="button" class="stg-subtab-btn" data-stg-group="banking"><i class="fas fa-lock me-1"></i> Banking / Security</button>
            <button type="button" class="stg-subtab-btn" data-stg-group="leadership"><i class="fas fa-users me-1"></i> नेतृत्व / Footer</button>
            <button type="button" class="stg-subtab-btn" data-stg-group="all"><i class="fas fa-table-cells-large me-1"></i> सबै देखाउनुहोस्</button>
        </div>
        <div class="row">
        <div class="col-lg-12">
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="identity" data-stg-order="1">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-globe"></i> साइट जानकारी</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">साइट नाम (नेपाली)</label>
                                <input type="text" name="site_name" class="form-control"
                                       value="<?php echo $settings['site_name'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Site Name (English)</label>
                                <input type="text" name="site_name_en" class="form-control"
                                       value="<?php echo $settings['site_name_en'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">साइट स्लोगन</label>
                        <input type="text" name="site_slogan" class="form-control"
                               value="<?php echo htmlspecialchars($settings['site_slogan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Site slogan (English)</label>
                        <input type="text" name="site_slogan_en" class="form-control"
                               value="<?php echo htmlspecialchars($settings['site_slogan_en'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="Short tagline for English UI / SEO fallback">
                    </div>

                </div>
            </div>

            <!-- SEO — प्रति डोमेन/सहकारी (Google, Facebook share) -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="identity" data-stg-order="2">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-search"></i> SEO (Google / सामाजिक साझेदारी)</h5>
                </div>
                <div class="card-body">
                    <h6 class="text-success fw-bold mb-3"><i class="fas fa-globe me-2"></i>साइट जानकारी</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Website Name</label>
                                <input type="text" name="site_name" class="form-control"
                                       value="<?php echo $settings['site_name'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Website Name (EN)</label>
                                <input type="text" name="site_name_en" class="form-control"
                                       value="<?php echo $settings['site_name_en'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slogan</label>
                        <input type="text" name="site_slogan" class="form-control"
                               value="<?php echo htmlspecialchars($settings['site_slogan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slogan (EN)</label>
                        <input type="text" name="site_slogan_en" class="form-control"
                               value="<?php echo htmlspecialchars($settings['site_slogan_en'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">संक्षिप्त परिचय</label>
                        <textarea name="about_short" class="form-control" rows="3"><?php echo $settings['about_short'] ?? ''; ?></textarea>
                    </div>

                    <hr>
                    <p class="text-muted small mb-3">हरेक सहकारीको आफ्नै डोमेनमा यही थिम चलाउँदा यहाँ भएको विवरण प्रयोग हुन्छ। खाली छोड्नुभयो भने स्लोगन वा पृष्ठ–विशेष विवरण fallback हुन्छ।</p>
                    <div class="mb-3">
                        <label class="form-label">मेटा विवरण (नेपाली) — &lt;meta name=&quot;description&quot;&gt;</label>
                        <textarea name="meta_description" class="form-control" rows="3" maxlength="400"
                                  placeholder="छोटो, स्पष्ट वर्णन (अनुमानित १५०–१६० अक्षर)"><?php echo htmlspecialchars($settings['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta description (English)</label>
                        <textarea name="meta_description_en" class="form-control" rows="3" maxlength="400"
                                  placeholder="Short summary for English UI / search snippets"><?php echo htmlspecialchars($settings['meta_description_en'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">मेटा कीवर्ड (अल्पविरामले छुट्याउनुहोस्)</label>
                        <textarea name="meta_keywords" class="form-control" rows="2" maxlength="500"
                                  placeholder="सहकारी, बचत, ऋण, नेपाल"><?php echo htmlspecialchars($settings['meta_keywords'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">डिफल्ट share छवि (Open Graph / Facebook)</label>
                        <?php if (!empty($settings['seo_og_image'])): ?>
                        <div class="mb-2">
                            <img src="../<?php echo htmlspecialchars($settings['seo_og_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="OG" style="max-width:100%;max-height:120px;border-radius:8px;border:1px solid #dee2e6;">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="seo_og_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <small class="text-muted d-block mt-1">सिफारिस ~1200×630 px। खाली भए लोगो प्रयोग हुन्छ।</small>
                        <?php if (!empty($settings['seo_og_image'])): ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="clear_seo_og_image" value="1" id="clear_seo_og_image">
                            <label class="form-check-label" for="clear_seo_og_image">Share छवि हटाउनुहोस् (लोगोमा फर्कनु)</label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Contact + Social Media -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="contact" data-stg-order="2">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-address-book"></i> सम्पर्क / सामाजिक सञ्जाल</h5>
                </div>
                <div class="card-body">
                    <h6 class="text-success fw-bold mb-3"><i class="fas fa-phone me-2"></i>सम्पर्क जानकारी</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">फोन नम्बर</label>
                                <input type="text" name="phone" class="form-control"
                                       value="<?php echo $settings['phone'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">मोबाइल नम्बर</label>
                                <input type="text" name="mobile" class="form-control"
                                       value="<?php echo $settings['mobile'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">इमेल</label>
                        <input type="email" name="email" class="form-control"
                               value="<?php echo $settings['email'] ?? ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ठेगाना</label>
                        <input type="text" name="address" class="form-control"
                               value="<?php echo $settings['address'] ?? ''; ?>">
                    </div>

                    <hr>
                    <h6 class="text-success fw-bold mb-3"><i class="fas fa-share-alt me-2"></i>सामाजिक सञ्जाल</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fab fa-facebook text-primary"></i> Facebook URL</label>
                                <input type="url" name="facebook_url" class="form-control"
                                       value="<?php echo $settings['facebook_url'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fab fa-youtube text-danger"></i> YouTube URL</label>
                                <input type="url" name="youtube_url" class="form-control"
                                       value="<?php echo $settings['youtube_url'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fab fa-twitter text-info"></i> Twitter URL</label>
                                <input type="url" name="twitter_url" class="form-control"
                                       value="<?php echo $settings['twitter_url'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><i class="fab fa-instagram text-danger"></i> Instagram URL</label>
                                <input type="url" name="instagram_url" class="form-control"
                                       value="<?php echo $settings['instagram_url'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><i class="fab fa-whatsapp text-success"></i> WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" class="form-control"
                               value="<?php echo $settings['whatsapp_number'] ?? ''; ?>"
                               placeholder="9779812345678">
                        <small class="text-muted">Country code सहित (जस्तै: 9779812345678)</small>
                    </div>
                </div>
            </div>

            <!-- Digital Banking -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="banking" data-stg-order="1">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-laptop"></i> डिजिटल बैंकिङ</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Internet Banking URL</label>
                        <input type="url" name="internet_banking_url" class="form-control"
                               value="<?php echo $settings['internet_banking_url'] ?? ''; ?>"
                               placeholder="https://ibanking.yoursite.com">
                        <small class="text-muted">इन्टरनेट बैंकिङ लगइन URL</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Google Play Store URL</label>
                        <input type="url" name="play_store_url" class="form-control"
                               value="<?php echo $settings['play_store_url'] ?? ''; ?>"
                               placeholder="https://play.google.com/store/apps/details?id=...">
                        <small class="text-muted">मोबाइल एप (Android)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Apple App Store URL</label>
                        <input type="url" name="app_store_url" class="form-control"
                               value="<?php echo $settings['app_store_url'] ?? ''; ?>"
                               placeholder="https://apps.apple.com/app/...">
                        <small class="text-muted">मोबाइल एप (iOS)</small>
                    </div>

                    <!-- OAuth Settings -->
                    <hr><h6 class="text-success fw-bold mt-3"><i class="fas fa-key me-2"></i>Member Portal — Social Login (OAuth)</h6>
                    <div class="alert alert-info py-2 px-3" style="font-size:.82rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        Google OAuth: <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> बाट Client ID र Secret लिनुहोस्।<br>
                        Facebook: <a href="https://developers.facebook.com/apps" target="_blank">Meta Developers</a> बाट App ID र Secret लिनुहोस्।<br>
                        <strong>Redirect URI:</strong> <code><?php echo SITE_URL; ?>member/oauth.php?provider=google</code>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><i class="fab fa-google text-danger me-1"></i>Google Client ID</label>
                            <input type="text" name="google_client_id" class="form-control font-monospace"
                                   value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>"
                                   placeholder="xxxx.apps.googleusercontent.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fab fa-google text-danger me-1"></i>Google Client Secret</label>
                            <input type="password" name="google_client_secret" class="form-control font-monospace"
                                   value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>"
                                   placeholder="GOCSPX-...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fab fa-facebook text-primary me-1"></i>Facebook App ID</label>
                            <input type="text" name="facebook_app_id" class="form-control font-monospace"
                                   value="<?php echo htmlspecialchars($settings['facebook_app_id'] ?? ''); ?>"
                                   placeholder="1234567890">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><i class="fab fa-facebook text-primary me-1"></i>Facebook App Secret</label>
                            <input type="password" name="facebook_app_secret" class="form-control font-monospace"
                                   value="<?php echo htmlspecialchars($settings['facebook_app_secret'] ?? ''); ?>"
                                   placeholder="abcdef1234...">
                        </div>
                    </div>
                    <?php if (!empty($_SESSION['is_superadmin'])): ?>
                    <hr>
                    <h6 class="text-success fw-bold mt-3"><i class="fas fa-shield-halved me-2"></i>2FA Policy (Superadmin)</h6>
                    <div class="alert alert-warning py-2 px-3" style="font-size:.82rem;">
                        <i class="fas fa-lock me-1"></i> तलको toggle अनुसार Google Authenticator 2FA login मा लागू हुन्छ।
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="twofa_admin_required" name="twofa_admin_required" value="1"
                               <?php echo (($settings['twofa_admin_required'] ?? '0') === '1') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="twofa_admin_required">Admin Login मा 2FA अनिवार्य</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="twofa_member_required" name="twofa_member_required" value="1"
                               <?php echo (($settings['twofa_member_required'] ?? '0') === '1') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="twofa_member_required">Member Login मा 2FA अनिवार्य</label>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Mobile App Photo</label>
                        <?php if (isset($settings['mobile_app_photo']) && $settings['mobile_app_photo']): ?>
                        <div class="mb-2">
                            <img src="../<?php echo $settings['mobile_app_photo']; ?>" alt="Mobile App" class="img-thumbnail" style="max-height: 120px;">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="mobile_app_photo" class="form-control" accept="image/*">
                        <small class="text-muted">मोबाइल एप सेक्सनमा देखिने फोटो (अनुशंसित: 400x600px)</small>
                    </div>
                </div>
            </div>

            <!-- Leadership Section -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="leadership" data-stg-order="1">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-user-tie"></i> नेतृत्व सन्देश</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="fas fa-user"></i> अध्यक्ष</h6>
                            <div class="mb-3">
                                <label class="form-label">अध्यक्षको नाम</label>
                                <input type="text" name="chairman_name" class="form-control"
                                       value="<?php echo $settings['chairman_name'] ?? ''; ?>"
                                       placeholder="अध्यक्षको पूरा नाम">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">अध्यक्षको फोटो</label>
                                <?php if (isset($settings['chairman_photo']) && $settings['chairman_photo']): ?>
                                <div class="mb-2">
                                    <img src="../<?php echo $settings['chairman_photo']; ?>" alt="Chairman" class="img-thumbnail" style="max-height: 80px;">
                                </div>
                                <?php endif; ?>
                                <input type="file" name="chairman_photo" class="form-control" accept="image/*">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success mb-3"><i class="fas fa-user"></i> प्रमुख कार्यकारी अधिकृत</h6>
                            <div class="mb-3">
                                <label class="form-label">CEO को नाम</label>
                                <input type="text" name="ceo_name" class="form-control"
                                       value="<?php echo $settings['ceo_name'] ?? ''; ?>"
                                       placeholder="CEO को पूरा नाम">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">CEO को फोटो</label>
                                <?php if (isset($settings['ceo_photo']) && $settings['ceo_photo']): ?>
                                <div class="mb-2">
                                    <img src="../<?php echo $settings['ceo_photo']; ?>" alt="CEO" class="img-thumbnail" style="max-height: 80px;">
                                </div>
                                <?php endif; ?>
                                <input type="file" name="ceo_photo" class="form-control" accept="image/*">
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle"></i>
                        सन्देशहरू <a href="pages.php" class="alert-link">पृष्ठ व्यवस्थापन</a> मा "अध्यक्षको सन्देश" र "प्रमुख कार्यकारी अधिकृतको सन्देश" मा सम्पादन गर्नुहोस्।
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="leadership" data-stg-order="2">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-copyright"></i> फुटर</h5>
                </div>
                <div class="card-body">
                    <?php if (!$canEditFooterDev): ?>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-lock me-1"></i> Copyright/Developed By सेटिङ्स Super Admin ले मात्र परिवर्तन गर्न मिल्छ।
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Copyright Text</label>
                        <input type="text" name="footer_text" class="form-control"
                               value="<?php echo $settings['footer_text'] ?? ''; ?>"
                               <?php echo $canEditFooterDev ? '' : 'readonly'; ?>>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Developed By (Name)</label>
                                <input type="text" name="developer_name" class="form-control"
                                       value="<?php echo $settings['developer_name'] ?? 'Tanka Adhikari'; ?>"
                                       <?php echo $canEditFooterDev ? '' : 'readonly'; ?>>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Developed By URL</label>
                                <input type="url" name="developer_url" class="form-control"
                                       value="<?php echo $settings['developer_url'] ?? 'https://www.tankaadhikari.com.np/'; ?>"
                                       <?php echo $canEditFooterDev ? '' : 'readonly'; ?>>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Google Map -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="contact" data-stg-order="4">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-map-marker-alt"></i> Google Map</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Google Map Embed URL</label>
                        <input type="url" name="google_map_url" class="form-control"
                               value="<?php echo $settings['google_map_url'] ?? ''; ?>"
                               placeholder="https://www.google.com/maps/embed?pb=...">
                        <small class="text-muted">Google Maps बाट Embed URL copy गर्नुहोस्</small>
                    </div>
                </div>
            </div>

            <!-- Working Hours -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="contact" data-stg-order="3">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-clock"></i> कार्य समय</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">आइत–शुक्रबार समय <small class="text-muted">(Sunday–Friday)</small></label>
                        <input type="text" name="working_hours" class="form-control"
                               placeholder="बिहान १०:०० - साँझ ५:००"
                               value="<?php echo $settings['working_hours'] ?? 'बिहान १०:०० - साँझ ५:००'; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">शनिबार समय <small class="text-muted">(Saturday)</small></label>
                        <input type="text" name="saturday_hours" class="form-control"
                               placeholder="बिहान १०:०० - दिउँसो १:००"
                               value="<?php echo $settings['saturday_hours'] ?? 'बिहान १०:०० - दिउँसो १:००'; ?>">
                    </div>
                </div>
            </div>
            <div class="card stg-save-card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> सेटिङ्स सेभ गर्नुहोस्
                    </button>
                </div>
            </div>
        </div>
        </div>
        </div>

        <div class="tab-pane fade <?php echo $panel === 'branding' ? 'show active' : ''; ?>" id="settings-branding-tab" role="tabpanel">
        <div class="alert alert-light border settings-tab-note mb-3">
            <i class="fas fa-circle-info me-2 text-primary"></i>
            लोगो, header image, about image, theme colors र version जस्ता branding/media सम्बन्धी सेटिङ्स यही tab मा छन्।
        </div>
        <div class="stg-subtabs mb-3" data-stg-panel="branding">
            <button type="button" class="stg-subtab-btn active" data-stg-group="media"><i class="fas fa-images me-1"></i> Logo / Media</button>
            <button type="button" class="stg-subtab-btn" data-stg-group="colors"><i class="fas fa-palette me-1"></i> Theme Colors</button>
            <button type="button" class="stg-subtab-btn" data-stg-group="version"><i class="fas fa-code-branch me-1"></i> Version</button>
            <button type="button" class="stg-subtab-btn" data-stg-group="all"><i class="fas fa-table-cells-large me-1"></i> सबै देखाउनुहोस्</button>
        </div>
        <div class="row">
        <div class="col-lg-12">
            <!-- Sidebar -->
            <!-- Logo -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="branding" data-stg-group="media" data-stg-order="1">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-image"></i> लोगो</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (isset($settings['logo']) && $settings['logo']): ?>
                        <img src="../<?php echo $settings['logo']; ?>" alt="Logo" class="img-fluid mb-3" style="max-height: 100px;">
                    <?php endif; ?>
                    <div class="mb-3">
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <small class="text-muted">PNG/JPG/SVG (सिफारिस: 1200x460 वा बढी, Max 10MB)</small>
                    </div>
                </div>
            </div>

            <!-- Himal Background Photo -->
            <div class="card mb-4 stg-section-card stg-accent-card stg-filter-card" data-stg-panel="branding" data-stg-group="media" data-stg-order="2">
                <div class="card-header stg-section-header stg-soft-green-header">
                    <h5 class="mb-0 stg-section-title"><i class="fas fa-mountain me-2"></i>हेडर हिमाल फोटो</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($settings['himal_bg'])): ?>
                        <img src="../<?php echo htmlspecialchars($settings['himal_bg']); ?>"
                             alt="Himal Background" class="img-fluid mb-2 rounded"
                             style="max-height:150px; width:100%; object-fit:cover;">
                        <div class="mb-2">
                            <small class="text-success"><i class="fas fa-check-circle"></i> फोटो अपलोड भएको छ</small>
                        </div>
                    <?php else: ?>
                        <div class="mb-2 p-3 rounded" style="background:linear-gradient(135deg,var(--primary-color),var(--primary-light)); min-height:110px; display:flex;align-items:center;justify-content:center;">
                            <small class="text-white opacity-75"><i class="fas fa-mountain me-1"></i> हिमाल फोटो छैन — gradient देखिन्छ</small>
                        </div>
                    <?php endif; ?>
                    <div class="mb-0">
                        <input type="file" name="himal_bg" class="form-control" accept="image/*">
                        <small class="text-muted mt-1 d-block">
                            <i class="fas fa-info-circle"></i>
                            Header को हरियो background मा देखिने हिमाल/पहाड फोटो।<br>
                            <strong>अनुशंसित:</strong> 1400×200px landscape (JPG/PNG, Max 10MB)
                        </small>
                    </div>
                </div>
            </div>

            <!-- About Page Image -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="branding" data-stg-group="media" data-stg-order="3">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-info-circle"></i> हाम्रो बारेमा पृष्ठको फोटो</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (isset($settings['about_page_image']) && $settings['about_page_image']): ?>
                        <img src="../<?php echo $settings['about_page_image']; ?>" alt="About Page" class="img-fluid mb-3" style="max-height: 120px;">
                    <?php endif; ?>
                    <div class="mb-3">
                        <input type="file" name="about_page_image" class="form-control" accept="image/*">
                        <small class="text-muted">"हाम्रो बारेमा" पृष्ठमा देखिने फोटो (अनुशंसित: 600x400px)</small>
                    </div>
                </div>
            </div>

            <!-- Theme Color -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="branding" data-stg-group="colors" data-stg-order="1">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-palette"></i> Theme Color</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Primary Color</label>
                        <input type="color" name="primary_color" class="form-control form-control-color w-100"
                               value="<?php echo $settings['primary_color'] ?? 'var(--primary-color)'; ?>"
                               style="height: 50px;">
                        <small class="text-muted">Website को main color चयन गर्नुहोस्</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Footer Color</label>
                        <input type="color" name="footer_color" class="form-control form-control-color w-100"
                               value="<?php echo $settings['footer_color'] ?? 'var(--primary-color)'; ?>"
                               style="height: 50px;">
                        <small class="text-muted">Footer को background color चयन गर्नुहोस्</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Secondary Color</label>
                        <input type="color" name="secondary_color" class="form-control form-control-color w-100"
                               value="<?php echo $settings['secondary_color'] ?? ($settings['topbar_color'] ?? '#c0392b'); ?>"
                               style="height: 50px;">
                        <small class="text-muted">Accent / secondary theme color चयन गर्नुहोस्</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Header Color</label>
                        <input type="color" name="header_color" class="form-control form-control-color w-100"
                               value="<?php echo $settings['header_color'] ?? ($settings['topbar_color'] ?? '#c0392b'); ?>"
                               style="height: 50px;">
                        <small class="text-muted">Top utility/header strip को रंग चयन गर्नुहोस्</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Top Bar Color <span class="badge bg-danger ms-1">Red Strip</span></label>
                        <input type="color" name="topbar_color" class="form-control form-control-color w-100"
                               value="<?php echo $settings['topbar_color'] ?? '#c0392b'; ?>"
                               style="height: 50px;">
                        <small class="text-muted">Header माथिको रातो पट्टीको रंग चयन गर्नुहोस् (Auction Portal, Downloads आदि भएको bar)</small>
                    </div>
                </div>
            </div>

            <!-- ===================================================
                 Website Version Management
                 Admin ले website को version number अपडेट गर्न सक्छ।
                 यो version footer मा / system info मा देखाउन सकिन्छ।
                 =================================================== -->
            <div class="card mb-4 stg-section-card stg-accent-card stg-filter-card" id="version" data-stg-panel="branding" data-stg-group="version" data-stg-order="1">
                <div class="card-header stg-section-header stg-soft-green-header">
                    <h5 class="mb-0 stg-section-title"><i class="fas fa-code-branch me-2"></i>Website Version</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Version Number</label>
                        <!-- जस्तै: 1.0.0 वा 2.5.1 -->
                        <input type="text" name="site_version" class="form-control"
                               value="<?php echo htmlspecialchars($settings['site_version'] ?? '1.0.0'); ?>"
                               placeholder="e.g. 1.0.0">
                        <small class="text-muted">Website को संस्करण नम्बर — जस्तै: 1.0.0, 2.1.0</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Launch Date (BS मिति)</label>
                        <!-- Website सुरु भएको मिति — BS (बि.सं.) format मा -->
                        <div class="input-group">
                            <input type="text" name="site_launch_date"
                                   class="form-control nepali-datepicker"
                                   value="<?php echo htmlspecialchars($settings['site_launch_date'] ?? ''); ?>"
                                   placeholder="YYYY-MM-DD" autocomplete="off">
                            <span class="input-group-text bg-success text-white border-success">
                                <i class="fas fa-calendar-alt"></i>
                            </span>
                        </div>
                        <small class="text-muted">Website सुरु भएको मिति (BS / बि.सं.)</small>
                    </div>
                    <!-- हालको version देखाउँछ -->
                    <div class="alert alert-success py-2 mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-info-circle"></i>
                        <span>हालको संस्करण:
                            <strong><?php echo htmlspecialchars($settings['site_version'] ?? '1.0.0'); ?></strong>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="card stg-save-card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> सेटिङ्स सेभ गर्नुहोस्
                    </button>
                </div>
            </div>
        </div>
        </div>
        </div>
    </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.stg-subtabs[data-stg-panel]').forEach(function (bar) {
        var panel = bar.getAttribute('data-stg-panel') || '';
        var buttons = Array.prototype.slice.call(bar.querySelectorAll('.stg-subtab-btn[data-stg-group]'));
        var cards = Array.prototype.slice.call(document.querySelectorAll('.stg-filter-card[data-stg-panel="' + panel + '"]'));
        if (!panel || !buttons.length || !cards.length) return;

        function setGroup(group) {
            buttons.forEach(function (btn) {
                btn.classList.toggle('active', btn.getAttribute('data-stg-group') === group);
            });
            cards.forEach(function (card) {
                var cardGroup = card.getAttribute('data-stg-group') || 'all';
                var show = (group === 'all' || cardGroup === group);
                card.classList.toggle('d-none', !show);
            });
        }

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setGroup(btn.getAttribute('data-stg-group') || 'all');
            });
        });

        var defaultBtn = bar.querySelector('.stg-subtab-btn.active[data-stg-group]');
        setGroup(defaultBtn ? defaultBtn.getAttribute('data-stg-group') : 'all');
    });
});
</script>

<?php require_once 'includes/admin-footer.php'; ?>
