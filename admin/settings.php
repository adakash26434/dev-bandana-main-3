<?php
$__t = static function (string $np, string $en): string {
    return isEnglish() ? $en : $np;
};
$pageTitle = $__t('साइट सेटिङ्स', 'Site Settings');
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
        $textSettings = ['site_name', 'site_name_en', 'site_slogan', 'site_slogan_en', 'meta_description', 'meta_description_en', 'meta_keywords', 'phone', 'mobile', 'email', 'address', 'facebook_url', 'youtube_url', 'twitter_url', 'instagram_url', 'whatsapp_number', 'about_short', 'hero_title', 'hero_subtitle', 'footer_text', 'internet_banking_url', 'play_store_url', 'app_store_url', 'developer_name', 'developer_url', 'supported_name', 'supported_url', 'google_map_url', 'working_hours', 'saturday_hours', 'primary_color', 'secondary_color', 'header_color', 'footer_color', 'topbar_color', 'chairman_name', 'ceo_name', 'ceo_designation_np', 'ceo_designation_en', 'site_version', 'site_launch_date', 'google_client_id', 'google_client_secret', 'facebook_app_id', 'facebook_app_secret', 'twofa_admin_required', 'twofa_member_required'];

        /* Color inputs सुरक्षित/valid hex मा मात्र save गर्ने:
           invalid value ले UI text invisible/unstyled बनाउने risk कम हुन्छ। */
        $sanitizeHexColor = function ($value, $fallback = '#1a5f2a') {
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
                if (in_array($key, ['footer_text', 'developer_name', 'developer_url', 'supported_name', 'supported_url'], true) && !$canEditFooterDev) {
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
                    $value = $sanitizeHexColor($value, '#1a5f2a');
                } elseif ($key === 'secondary_color') {
                    $value = $sanitizeHexColor($value, '#c0392b');
                } elseif ($key === 'header_color') {
                    $value = $sanitizeHexColor($value, '#c0392b');
                } elseif ($key === 'footer_color') {
                    $value = $sanitizeHexColor($value, '#1a5f2a');
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

        // Handle default logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['logo'], 'logo');
            if ($upload['success']) {
                $result = updateSetting('logo', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('Logo save गर्न सकिएन', 'Could not save logo');
                }
            } else {
                $uploadErrors[] = $__t('Logo upload', 'Logo upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // Nepali logo upload (lang-specific)
        if (isset($_FILES['logo_np']) && $_FILES['logo_np']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['logo_np'], 'logo');
            if ($upload['success']) {
                $result = updateSetting('logo_np', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('Nepali logo save गर्न सकिएन', 'Could not save Nepali logo');
                }
            } else {
                $uploadErrors[] = $__t('Nepali logo upload', 'Nepali logo upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // English logo upload (lang-specific)
        if (isset($_FILES['logo_en']) && $_FILES['logo_en']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['logo_en'], 'logo');
            if ($upload['success']) {
                $result = updateSetting('logo_en', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('English logo save गर्न सकिएन', 'Could not save English logo');
                }
            } else {
                $uploadErrors[] = $__t('English logo upload', 'English logo upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // Handle chairman photo upload
        if (isset($_FILES['chairman_photo']) && $_FILES['chairman_photo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['chairman_photo'], 'leadership');
            if ($upload['success']) {
                $result = updateSetting('chairman_photo', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('Chairman photo save गर्न सकिएन', 'Could not save chairman photo');
                }
            } else {
                $uploadErrors[] = $__t('Chairman photo upload', 'Chairman photo upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // Handle CEO photo upload
        if (isset($_FILES['ceo_photo']) && $_FILES['ceo_photo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['ceo_photo'], 'leadership');
            if ($upload['success']) {
                $result = updateSetting('ceo_photo', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('CEO photo save गर्न सकिएन', 'Could not save CEO photo');
                }
            } else {
                $uploadErrors[] = $__t('CEO photo upload', 'CEO photo upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // Handle mobile app photo upload
        if (isset($_FILES['mobile_app_photo']) && $_FILES['mobile_app_photo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['mobile_app_photo'], 'app');
            if ($upload['success']) {
                $result = updateSetting('mobile_app_photo', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('Mobile app photo save गर्न सकिएन', 'Could not save mobile app photo');
                }
            } else {
                $uploadErrors[] = $__t('Mobile app photo upload', 'Mobile app photo upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // Handle about page image upload
        if (isset($_FILES['about_page_image']) && $_FILES['about_page_image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['about_page_image'], 'pages');
            if ($upload['success']) {
                $result = updateSetting('about_page_image', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('About page image save गर्न सकिएन', 'Could not save About page image');
                }
            } else {
                $uploadErrors[] = $__t('About page image upload', 'About page image upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // Handle about intro right image upload
        if (isset($_FILES['about_intro_image']) && $_FILES['about_intro_image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['about_intro_image'], 'pages');
            if ($upload['success']) {
                $result = updateSetting('about_intro_image', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('About intro image save गर्न सकिएन', 'Could not save About intro image');
                }
            } else {
                $uploadErrors[] = $__t('About intro image upload', 'About intro image upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // Handle history photo upload (about history section)
        if (isset($_FILES['history_photo']) && $_FILES['history_photo']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['history_photo'], 'pages');
            if ($upload['success']) {
                $result = updateSetting('history_photo', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('History photo save गर्न सकिएन', 'Could not save history photo');
                }
            } else {
                $uploadErrors[] = $__t('History photo upload', 'History photo upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // Handle himal background photo upload
        if (isset($_FILES['himal_bg']) && $_FILES['himal_bg']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['himal_bg'], 'header');
            if ($upload['success']) {
                $result = updateSetting('himal_bg', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('Himal photo save गर्न सकिएन', 'Could not save himal photo');
                }
            } else {
                $uploadErrors[] = $__t('Himal photo upload', 'Himal photo upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
            }
        }

        // SEO — default Open Graph / Facebook share image (प्रति सहकारी फरक)
        if (isset($_FILES['seo_og_image']) && $_FILES['seo_og_image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadFile($_FILES['seo_og_image'], 'seo');
            if ($upload['success']) {
                $result = updateSetting('seo_og_image', $upload['path']);
                if (!$result) {
                    $uploadErrors[] = $__t('SEO share image save गर्न सकिएन', 'Could not save SEO share image');
                }
            } else {
                $uploadErrors[] = $__t('SEO share image upload', 'SEO share image upload') . ': ' . ($upload['message'] ?? $__t('अज्ञात त्रुटि', 'Unknown error'));
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
        setFlash('success', $__t('सेटिङ्स सफलतापूर्वक अपडेट भयो।', 'Settings updated successfully.'));

        // Use JavaScript redirect to ensure session is saved
        echo '<script>window.location.href = "settings.php";</script>';
        exit();

    } catch (Exception $e) {
        $updateError = $e->getMessage();
        setFlash('error', $__t('त्रुटि भयो। कृपया पछि प्रयास गर्नुहोस्।', 'An error occurred. Please try again later.'));
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
echo adminPageHeader($__t('साइट सेटिङ्स', 'Site Settings'), 'fa-cogs', $__t('आकाश सहकारी वेबसाइटको सामान्य सेटिङ्स', 'General settings for cooperative website'));
?>
<?php echo adminHelpTip($__t('यो पृष्ठबाट Website को नाम, Logo, रंग, र सम्पर्क विवरण परिवर्तन गर्न सकिन्छ।', 'You can change website name, logo, colors and contact details from this page.'), [$__t('Logo बदल्न: "Site Logo" section मा जानुहोस्।', 'To change logo: go to "Site Logo" section.'), $__t('रंग बदल्न: "Primary Color" section मा color picker प्रयोग गर्नुहोस्।', 'To change colors: use color picker in "Primary Color" section.'), $__t('परिवर्तन गरेपछि: "Save" बटन थिच्नुहोस्।', 'After changes: click "Save" button.')]); ?>
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
                <i class="fas fa-sliders me-1"></i> <?php echo $__t('सामान्य सेटिङ्स', 'General Settings'); ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $panel === 'branding' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#settings-branding-tab" type="button" role="tab">
                <i class="fas fa-image me-1"></i> <?php echo $__t('ब्रान्डिङ / मिडिया', 'Branding / Media'); ?>
            </button>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade <?php echo $panel === 'general' ? 'show active' : ''; ?>" id="settings-general-tab" role="tabpanel">
        <div class="alert alert-light border settings-tab-note mb-3">
            <i class="fas fa-circle-info me-2 text-primary"></i>
            <?php echo $__t('वेबसाइटको नाम, SEO, सम्पर्क, social links, banking links, नेतृत्व र footer सम्बन्धी मुख्य सेटिङ्स यही tab मा छन्।', 'Main settings for website name, SEO, contacts, social links, banking links, leadership and footer are in this tab.'); ?>
        </div>
        <div class="stg-subtabs mb-3" data-stg-panel="general">
            <button type="button" class="stg-subtab-btn active" data-stg-group="identity"><i class="fas fa-globe me-1"></i> <?php echo $__t('साइट / SEO', 'Site / SEO'); ?></button>
            <button type="button" class="stg-subtab-btn" data-stg-group="contact"><i class="fas fa-address-book me-1"></i> <?php echo $__t('सम्पर्क / Maps', 'Contact / Maps'); ?></button>
            <button type="button" class="stg-subtab-btn" data-stg-group="banking"><i class="fas fa-lock me-1"></i> <?php echo $__t('Banking / Security', 'Banking / Security'); ?></button>
            <button type="button" class="stg-subtab-btn" data-stg-group="leadership"><i class="fas fa-users me-1"></i> <?php echo $__t('नेतृत्व / Footer', 'Leadership / Footer'); ?></button>
            <button type="button" class="stg-subtab-btn" data-stg-group="all"><i class="fas fa-table-cells-large me-1"></i> <?php echo $__t('सबै देखाउनुहोस्', 'Show All'); ?></button>
        </div>
        <div class="row">
        <div class="col-lg-12">
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="identity" data-stg-order="1">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-globe"></i> <?php echo $__t('साइट जानकारी', 'Site Information'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $__t('साइट नाम (नेपाली)', 'Site Name (Nepali)'); ?></label>
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
                        <label class="form-label"><?php echo $__t('साइट स्लोगन', 'Site Slogan'); ?></label>
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
                    <h5 class="stg-section-title"><i class="fas fa-search"></i> <?php echo $__t('SEO (Google / सामाजिक साझेदारी)', 'SEO (Google / Social Sharing)'); ?></h5>
                </div>
                <div class="card-body">
                    <h6 class="text-success fw-bold mb-3"><i class="fas fa-bullseye me-2"></i>Search / Share Content</h6>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $__t('संक्षिप्त परिचय', 'Short Introduction'); ?></label>
                        <textarea name="about_short" class="form-control" rows="3"><?php echo $settings['about_short'] ?? ''; ?></textarea>
                    </div>

                    <hr>
                    <p class="text-muted small mb-3"><?php echo $__t('हरेक सहकारीको आफ्नै डोमेनमा यही थिम चलाउँदा यहाँ भएको विवरण प्रयोग हुन्छ। खाली छोड्नुभयो भने स्लोगन वा पृष्ठ–विशेष विवरण fallback हुन्छ।', 'When this theme runs on each cooperative domain, this metadata is used. If left empty, slogan or page-specific description is used as fallback.'); ?></p>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $__t('मेटा विवरण (नेपाली)', 'Meta Description (Nepali)'); ?> — &lt;meta name=&quot;description&quot;&gt;</label>
                        <textarea name="meta_description" class="form-control" rows="3" maxlength="400"
                                  placeholder="<?php echo $__t('छोटो, स्पष्ट वर्णन (अनुमानित १५०–१६० अक्षर)', 'Short and clear summary (about 150-160 characters)'); ?>"><?php echo htmlspecialchars($settings['meta_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta description (English)</label>
                        <textarea name="meta_description_en" class="form-control" rows="3" maxlength="400"
                                  placeholder="Short summary for English UI / search snippets"><?php echo htmlspecialchars($settings['meta_description_en'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $__t('मेटा कीवर्ड (अल्पविरामले छुट्याउनुहोस्)', 'Meta Keywords (comma separated)'); ?></label>
                        <textarea name="meta_keywords" class="form-control" rows="2" maxlength="500"
                                  placeholder="<?php echo $__t('सहकारी, बचत, ऋण, नेपाल', 'cooperative, savings, loan, nepal'); ?>"><?php echo htmlspecialchars($settings['meta_keywords'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Contact + Social Media -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="contact" data-stg-order="2">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-address-book"></i> <?php echo $__t('सम्पर्क / सामाजिक सञ्जाल', 'Contact / Social Media'); ?></h5>
                </div>
                <div class="card-body">
                    <h6 class="text-success fw-bold mb-3"><i class="fas fa-phone me-2"></i><?php echo $__t('सम्पर्क जानकारी', 'Contact Information'); ?></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $__t('फोन नम्बर', 'Phone Number'); ?></label>
                                <input type="text" name="phone" class="form-control"
                                       value="<?php echo $settings['phone'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $__t('मोबाइल नम्बर', 'Mobile Number'); ?></label>
                                <input type="text" name="mobile" class="form-control"
                                       value="<?php echo $settings['mobile'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $__t('इमेल', 'Email'); ?></label>
                        <input type="email" name="email" class="form-control"
                               value="<?php echo $settings['email'] ?? ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $__t('ठेगाना', 'Address'); ?></label>
                        <input type="text" name="address" class="form-control"
                               value="<?php echo $settings['address'] ?? ''; ?>">
                    </div>

                    <hr>
                    <h6 class="text-success fw-bold mb-3"><i class="fas fa-share-alt me-2"></i><?php echo $__t('सामाजिक सञ्जाल', 'Social Media'); ?></h6>
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
                        <small class="text-muted"><?php echo $__t('Country code सहित (जस्तै: 9779812345678)', 'Include country code (e.g., 9779812345678)'); ?></small>
                    </div>
                </div>
            </div>

            <!-- Digital Banking -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="banking" data-stg-order="1">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-laptop"></i> <?php echo $__t('डिजिटल बैंकिङ', 'Digital Banking'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Internet Banking URL</label>
                        <input type="url" name="internet_banking_url" class="form-control"
                               value="<?php echo $settings['internet_banking_url'] ?? ''; ?>"
                               placeholder="https://ibanking.yoursite.com">
                        <small class="text-muted"><?php echo $__t('इन्टरनेट बैंकिङ लगइन URL', 'Internet banking login URL'); ?></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Google Play Store URL</label>
                        <input type="url" name="play_store_url" class="form-control"
                               value="<?php echo $settings['play_store_url'] ?? ''; ?>"
                               placeholder="https://play.google.com/store/apps/details?id=...">
                        <small class="text-muted"><?php echo $__t('मोबाइल एप (Android)', 'Mobile app (Android)'); ?></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Apple App Store URL</label>
                        <input type="url" name="app_store_url" class="form-control"
                               value="<?php echo $settings['app_store_url'] ?? ''; ?>"
                               placeholder="https://apps.apple.com/app/...">
                        <small class="text-muted"><?php echo $__t('मोबाइल एप (iOS)', 'Mobile app (iOS)'); ?></small>
                    </div>

                    <!-- OAuth Settings -->
                    <hr><h6 class="text-success fw-bold mt-3"><i class="fas fa-key me-2"></i><?php echo $__t('Member Portal — Social Login (OAuth)', 'Member Portal — Social Login (OAuth)'); ?></h6>
                    <div class="alert alert-info py-2 px-3" style="font-size:.82rem;">
                        <i class="fas fa-info-circle me-1"></i>
                        <?php echo $__t('Google OAuth', 'Google OAuth'); ?>: <a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a> <?php echo $__t('बाट Client ID र Secret लिनुहोस्।', 'to get Client ID and Secret.'); ?><br>
                        Facebook: <a href="https://developers.facebook.com/apps" target="_blank">Meta Developers</a> <?php echo $__t('बाट App ID र Secret लिनुहोस्।', 'to get App ID and Secret.'); ?><br>
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
                    <h6 class="text-success fw-bold mt-3"><i class="fas fa-shield-halved me-2"></i><?php echo $__t('2FA नीति (Superadmin)', '2FA Policy (Superadmin)'); ?></h6>
                    <div class="alert alert-warning py-2 px-3" style="font-size:.82rem;">
                        <i class="fas fa-lock me-1"></i> <?php echo $__t('तलको toggle अनुसार Google Authenticator 2FA login मा लागू हुन्छ।', 'Google Authenticator 2FA is enforced on login based on toggles below.'); ?>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="twofa_admin_required" name="twofa_admin_required" value="1"
                               <?php echo (($settings['twofa_admin_required'] ?? '0') === '1') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="twofa_admin_required"><?php echo $__t('Admin Login मा 2FA अनिवार्य', 'Require 2FA for Admin Login'); ?></label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="twofa_member_required" name="twofa_member_required" value="1"
                               <?php echo (($settings['twofa_member_required'] ?? '0') === '1') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="twofa_member_required"><?php echo $__t('Member Login मा 2FA अनिवार्य', 'Require 2FA for Member Login'); ?></label>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <div class="row">
            <div class="col-xl-6">
            <!-- Leadership Section -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="leadership" data-stg-order="1">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-user-tie"></i> <?php echo $__t('नेतृत्व सन्देश', 'Leadership Message'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3"><i class="fas fa-user"></i> <?php echo $__t('अध्यक्ष', 'Chairperson'); ?></h6>
                            <div class="mb-3">
                                <label class="form-label"><?php echo $__t('अध्यक्षको नाम', 'Chairperson Name'); ?></label>
                                <input type="text" name="chairman_name" class="form-control"
                                       value="<?php echo $settings['chairman_name'] ?? ''; ?>"
                                       placeholder="<?php echo $__t('अध्यक्षको पूरा नाम', 'Full chairperson name'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success mb-3"><i class="fas fa-user"></i> <?php echo $__t('प्रमुख कार्यकारी अधिकृत', 'Chief Executive Officer'); ?></h6>
                            <div class="mb-3">
                                <label class="form-label"><?php echo $__t('CEO को नाम', 'CEO Name'); ?></label>
                                <input type="text" name="ceo_name" class="form-control"
                                       value="<?php echo $settings['ceo_name'] ?? ''; ?>"
                                       placeholder="<?php echo $__t('CEO को पूरा नाम', 'Full CEO name'); ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo $__t('कार्यकारी पदनाम (नेपाली)', 'Executive Designation (Nepali)'); ?></label>
                                        <input type="text" name="ceo_designation_np" class="form-control"
                                               value="<?php echo $settings['ceo_designation_np'] ?? 'प्रमुख कार्यकारी अधिकृत'; ?>"
                                               placeholder="<?php echo $__t('उदा: व्यवस्थापक / प्रमुख कार्यकारी अधिकृत', 'e.g. Manager / Chief Executive Officer'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Executive Designation (English)</label>
                                        <input type="text" name="ceo_designation_en" class="form-control"
                                               value="<?php echo $settings['ceo_designation_en'] ?? 'Chief Executive Officer'; ?>"
                                               placeholder="e.g. Manager / Chief Executive Officer">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-info-circle"></i>
                        <?php echo $__t('सन्देशहरू', 'Messages'); ?> <a href="pages-v2.php" class="alert-link"><?php echo $__t('पृष्ठ व्यवस्थापन', 'Page Management'); ?></a> <?php echo $__t('मा सम्पादन गर्नुहोस्। फोटोहरू "Branding / Media Manager" मा एकै ठाउँबाट अपलोड गर्न सकिन्छ।', 'can be edited there. Photos can be uploaded from "Branding / Media Manager".'); ?>
                    </div>
                </div>
            </div>
            </div>

            <div class="col-xl-6">
            <!-- Footer -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="leadership" data-stg-order="2">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-copyright"></i> <?php echo $__t('फुटर', 'Footer'); ?></h5>
                </div>
                <div class="card-body">
                    <?php if (!$canEditFooterDev): ?>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-lock me-1"></i> <?php echo $__t('Copyright/Developed By सेटिङ्स Super Admin ले मात्र परिवर्तन गर्न मिल्छ।', 'Only Super Admin can modify Copyright/Developed By settings.'); ?>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label"><?php echo $__t('Copyright Text', 'Copyright Text'); ?></label>
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $__t('Supported By (Name)', 'Supported By (Name)'); ?></label>
                                <input type="text" name="supported_name" class="form-control"
                                       value="<?php echo $settings['supported_name'] ?? ''; ?>"
                                       <?php echo $canEditFooterDev ? '' : 'readonly'; ?>>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $__t('Supported By URL', 'Supported By URL'); ?></label>
                                <input type="url" name="supported_url" class="form-control"
                                       value="<?php echo $settings['supported_url'] ?? ''; ?>"
                                       <?php echo $canEditFooterDev ? '' : 'readonly'; ?>>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            </div>

            <!-- Office Info -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="general" data-stg-group="contact" data-stg-order="3">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-building"></i> <?php echo $__t('Office Info (Map + कार्य समय)', 'Office Info (Map + Working Hours)'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Google Map Embed URL</label>
                        <input type="url" name="google_map_url" class="form-control"
                               value="<?php echo $settings['google_map_url'] ?? ''; ?>"
                               placeholder="https://www.google.com/maps/embed?pb=...">
                        <small class="text-muted"><?php echo $__t('Google Maps बाट Embed URL copy गर्नुहोस्', 'Copy embed URL from Google Maps'); ?></small>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $__t('आइत–शुक्रबार समय', 'Sunday–Friday Hours'); ?> <small class="text-muted">(Sunday–Friday)</small></label>
                                <input type="text" name="working_hours" class="form-control"
                                       placeholder="बिहान १०:०० - साँझ ५:००"
                                       value="<?php echo $settings['working_hours'] ?? 'बिहान १०:०० - साँझ ५:००'; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><?php echo $__t('शनिबार समय', 'Saturday Hours'); ?> <small class="text-muted">(Saturday)</small></label>
                                <input type="text" name="saturday_hours" class="form-control"
                                       placeholder="बिहान १०:०० - दिउँसो १:००"
                                       value="<?php echo $settings['saturday_hours'] ?? 'बिहान १०:०० - दिउँसो १:००'; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card stg-save-card">
                <div class="card-body py-3 admin-form-actions">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-1"></i> <?php echo $__t('सेटिङ्स सेभ गर्नुहोस्', 'Save Settings'); ?>
                    </button>
                </div>
            </div>
        </div>
        </div>
        </div>

        <div class="tab-pane fade <?php echo $panel === 'branding' ? 'show active' : ''; ?>" id="settings-branding-tab" role="tabpanel">
        <div class="alert alert-light border settings-tab-note mb-3">
            <i class="fas fa-circle-info me-2 text-primary"></i>
            <?php echo $__t('लोगो, header image, about image, theme colors र version जस्ता branding/media सम्बन्धी सेटिङ्स यही tab मा छन्।', 'Branding/media settings like logo, header image, about image, theme colors and version are in this tab.'); ?>
        </div>
        <div class="stg-subtabs mb-3" data-stg-panel="branding">
            <button type="button" class="stg-subtab-btn active" data-stg-group="media"><i class="fas fa-images me-1"></i> <?php echo $__t('मिडिया व्यवस्थापक', 'Media Manager'); ?></button>
            <button type="button" class="stg-subtab-btn" data-stg-group="colors"><i class="fas fa-palette me-1"></i> <?php echo $__t('थिम रङहरू', 'Theme Colors'); ?></button>
            <button type="button" class="stg-subtab-btn" data-stg-group="version"><i class="fas fa-code-branch me-1"></i> <?php echo $__t('संस्करण', 'Version'); ?></button>
            <button type="button" class="stg-subtab-btn" data-stg-group="all"><i class="fas fa-table-cells-large me-1"></i> <?php echo $__t('सबै देखाउनुहोस्', 'Show All'); ?></button>
        </div>
        <div class="row">
        <div class="col-lg-12">
            <!-- Sidebar -->
            <!-- Media Manager -->
            <div class="card mb-4 stg-section-card stg-accent-card stg-filter-card" data-stg-panel="branding" data-stg-group="media" data-stg-order="1">
                <div class="card-header stg-section-header stg-soft-green-header">
                    <h5 class="mb-0 stg-section-title"><i class="fas fa-images me-2"></i><?php echo $__t('मिडिया व्यवस्थापक (सबै fixed फोटो)', 'Media Manager (all fixed photos)'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold"><?php echo $__t('साइट लोगो (Default)', 'Site Logo (Default)'); ?></label>
                            <?php if (!empty($settings['logo'])): ?><img src="../<?php echo htmlspecialchars($settings['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="img-fluid mb-2 border rounded" style="max-height:100px;"><?php endif; ?>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                            <small class="text-muted"><?php echo $__t('Fallback लोगो', 'Fallback logo'); ?> · <?php echo $__t('अनुशंसित', 'Recommended'); ?>: 1200x460+</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold"><?php echo $__t('नेपाली लोगो (NE)', 'Nepali Logo (NE)'); ?></label>
                            <?php if (!empty($settings['logo_np'])): ?><img src="../<?php echo htmlspecialchars($settings['logo_np'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo NP" class="img-fluid mb-2 border rounded" style="max-height:100px;"><?php endif; ?>
                            <input type="file" name="logo_np" class="form-control" accept="image/*">
                            <small class="text-muted"><?php echo $__t('नेपाली भाषा हुँदा यो देखिन्छ', 'Shown when site language is Nepali'); ?></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold"><?php echo $__t('अंग्रेजी लोगो (EN)', 'English Logo (EN)'); ?></label>
                            <?php if (!empty($settings['logo_en'])): ?><img src="../<?php echo htmlspecialchars($settings['logo_en'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo EN" class="img-fluid mb-2 border rounded" style="max-height:100px;"><?php endif; ?>
                            <input type="file" name="logo_en" class="form-control" accept="image/*">
                            <small class="text-muted"><?php echo $__t('English भाषा हुँदा यो देखिन्छ', 'Shown when site language is English'); ?></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $__t('हेडर हिमाल फोटो', 'Header Himal Photo'); ?></label>
                            <?php if (!empty($settings['himal_bg'])): ?><img src="../<?php echo htmlspecialchars($settings['himal_bg'], ENT_QUOTES, 'UTF-8'); ?>" alt="Himal" class="img-fluid mb-2 border rounded" style="max-height:100px;"><?php endif; ?>
                            <input type="file" name="himal_bg" class="form-control" accept="image/*">
                            <small class="text-muted"><?php echo $__t('अनुशंसित', 'Recommended'); ?>: 1400x200</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $__t('About पेज फोटो', 'About Page Image'); ?></label>
                            <?php if (!empty($settings['about_page_image'])): ?><img src="../<?php echo htmlspecialchars($settings['about_page_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="About" class="img-fluid mb-2 border rounded" style="max-height:120px;"><?php endif; ?>
                            <input type="file" name="about_page_image" class="form-control" accept="image/*">
                            <small class="text-muted"><?php echo $__t('अनुशंसित', 'Recommended'); ?>: 600x400</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $__t('About Intro दायाँ फोटो', 'About Intro Right Image'); ?></label>
                            <?php if (!empty($settings['about_intro_image'])): ?><img src="../<?php echo htmlspecialchars($settings['about_intro_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="About Intro" class="img-fluid mb-2 border rounded" style="max-height:120px;"><?php endif; ?>
                            <input type="file" name="about_intro_image" class="form-control" accept="image/*">
                            <small class="text-muted"><?php echo $__t('अनुशंसित', 'Recommended'); ?>: 700x900</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $__t('इतिहास सेक्शन फोटो', 'History Section Photo'); ?></label>
                            <?php if (!empty($settings['history_photo'])): ?><img src="../<?php echo htmlspecialchars($settings['history_photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="History" class="img-fluid mb-2 border rounded" style="max-height:120px;"><?php endif; ?>
                            <input type="file" name="history_photo" class="form-control" accept="image/*">
                            <small class="text-muted"><?php echo $__t('"हाम्रो इतिहास" section फोटो', '"Our History" section photo'); ?></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $__t('मोबाइल एप फोटो', 'Mobile App Photo'); ?></label>
                            <?php if (!empty($settings['mobile_app_photo'])): ?><img src="../<?php echo htmlspecialchars($settings['mobile_app_photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="App" class="img-fluid mb-2 border rounded" style="max-height:120px;"><?php endif; ?>
                            <input type="file" name="mobile_app_photo" class="form-control" accept="image/*">
                            <small class="text-muted"><?php echo $__t('अनुशंसित', 'Recommended'); ?>: 400x600</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $__t('अध्यक्ष फोटो', 'Chairman Photo'); ?></label>
                            <?php if (!empty($settings['chairman_photo'])): ?><img src="../<?php echo htmlspecialchars($settings['chairman_photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Chairman" class="img-fluid mb-2 border rounded" style="max-height:90px;"><?php endif; ?>
                            <input type="file" name="chairman_photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold"><?php echo $__t('CEO / कार्यकारी फोटो', 'CEO / Executive Photo'); ?></label>
                            <?php if (!empty($settings['ceo_photo'])): ?><img src="../<?php echo htmlspecialchars($settings['ceo_photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="CEO" class="img-fluid mb-2 border rounded" style="max-height:90px;"><?php endif; ?>
                            <input type="file" name="ceo_photo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold"><?php echo $__t('Default Share Image (SEO OG)', 'Default Share Image (SEO OG)'); ?></label>
                            <?php if (!empty($settings['seo_og_image'])): ?><img src="../<?php echo htmlspecialchars($settings['seo_og_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="OG" class="img-fluid mb-2 border rounded" style="max-height:120px;"><?php endif; ?>
                            <input type="file" name="seo_og_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted d-block mt-1"><?php echo $__t('अनुशंसित', 'Recommended'); ?>: 1200x630</small>
                            <?php if (!empty($settings['seo_og_image'])): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="clear_seo_og_image" value="1" id="clear_seo_og_image">
                                <label class="form-check-label" for="clear_seo_og_image"><?php echo $__t('Share image हटाउनुहोस्', 'Remove share image'); ?></label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Theme Color -->
            <div class="card mb-4 stg-section-card stg-filter-card" data-stg-panel="branding" data-stg-group="colors" data-stg-order="1">
                <div class="card-header stg-section-header">
                    <h5 class="stg-section-title"><i class="fas fa-palette"></i> <?php echo $__t('थिम रंग', 'Theme Color'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label"><?php echo $__t('प्राथमिक रंग', 'Primary Color'); ?></label>
                            <input type="color" name="primary_color" class="form-control form-control-color w-100"
                                   value="<?php echo $settings['primary_color'] ?? '#1a5f2a'; ?>"
                                   style="height: 50px;">
                            <small class="text-muted"><?php echo $__t('मुख्य रंग', 'Main color'); ?></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo $__t('फुटर रंग', 'Footer Color'); ?></label>
                            <input type="color" name="footer_color" class="form-control form-control-color w-100"
                                   value="<?php echo $settings['footer_color'] ?? '#1a5f2a'; ?>"
                                   style="height: 50px;">
                            <small class="text-muted"><?php echo $__t('फुटर पृष्ठभूमि', 'Footer bg'); ?></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?php echo $__t('सेकेन्डरी रंग', 'Secondary Color'); ?></label>
                            <input type="color" name="secondary_color" class="form-control form-control-color w-100"
                                   value="<?php echo $settings['secondary_color'] ?? ($settings['topbar_color'] ?? '#c0392b'); ?>"
                                   style="height: 50px;">
                            <small class="text-muted"><?php echo $__t('एक्सेन्ट रंग', 'Accent color'); ?></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $__t('हेडर रंग', 'Header Color'); ?></label>
                            <input type="color" name="header_color" class="form-control form-control-color w-100"
                                   value="<?php echo $settings['header_color'] ?? ($settings['topbar_color'] ?? '#c0392b'); ?>"
                                   style="height: 50px;">
                            <small class="text-muted"><?php echo $__t('माथिल्लो utility/header strip', 'Top utility/header strip'); ?></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?php echo $__t('टप बार रंग', 'Top Bar Color'); ?> <span class="badge bg-danger ms-1"><?php echo $__t('रातो पट्टी', 'Red Strip'); ?></span></label>
                            <input type="color" name="topbar_color" class="form-control form-control-color w-100"
                                   value="<?php echo $settings['topbar_color'] ?? '#c0392b'; ?>"
                                   style="height: 50px;">
                            <small class="text-muted"><?php echo $__t('Header माथिको रातो पट्टी', 'Red strip above header'); ?></small>
                        </div>
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
                    <h5 class="mb-0 stg-section-title"><i class="fas fa-code-branch me-2"></i><?php echo $__t('वेबसाइट संस्करण', 'Website Version'); ?></h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $__t('संस्करण नम्बर', 'Version Number'); ?></label>
                        <!-- जस्तै: 1.0.0 वा 2.5.1 -->
                        <input type="text" name="site_version" class="form-control"
                               value="<?php echo htmlspecialchars($settings['site_version'] ?? '1.0.0'); ?>"
                               placeholder="e.g. 1.0.0">
                        <small class="text-muted"><?php echo $__t('Website को संस्करण नम्बर — जस्तै: 1.0.0, 2.1.0', 'Website version number — e.g., 1.0.0, 2.1.0'); ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold"><?php echo $__t('सुरु मिति (BS)', 'Launch Date (BS)'); ?></label>
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
                        <small class="text-muted"><?php echo $__t('Website सुरु भएको मिति (BS / बि.सं.)', 'Website launch date (BS)'); ?></small>
                    </div>
                    <!-- हालको version देखाउँछ -->
                    <div class="alert alert-success py-2 mb-0 d-flex align-items-center gap-2">
                        <i class="fas fa-info-circle"></i>
                        <span><?php echo $__t('हालको संस्करण', 'Current version'); ?>:
                            <strong><?php echo htmlspecialchars($settings['site_version'] ?? '1.0.0'); ?></strong>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="card stg-save-card">
                <div class="card-body py-3 admin-form-actions">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-1"></i> सेटिङ्स सेभ गर्नुहोस्
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
