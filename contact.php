<?php
$pageTitle = 'सम्पर्क';
require_once 'includes/config.php';
require_once 'includes/ensure-tables.php';

$success = false;
$error   = '';

/* =============================================
   फारम submit भएमा process गर्नुहोस्
   ============================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken()) {
        $error = isEnglish() ? 'Security check failed. Please try again.' : 'सुरक्षा जाँच असफल। कृपया पुन: प्रयास गर्नुहोस्।';
    } elseif (!checkRateLimit('contact_form', 5, 60)) {
        $error = isEnglish() ? 'Too many requests. Please wait a moment.' : 'धेरै अनुरोधहरू। कृपया केही समय पर्खनुहोस्।';
    } else {
        $name    = clean_text($_POST['name']    ?? '', 200);
        $email   = strtolower(clean_text($_POST['email']   ?? '', 254));
        $phone   = preg_replace('/[^0-9]/', '', clean_text($_POST['phone']   ?? '', 20));
        $subject = clean_text($_POST['subject'] ?? '', 200);
        $message = clean_text($_POST['message'] ?? '', 8000);

        if (empty($name) || empty($message)) {
            $error = isEnglish() ? 'Please fill in name and message.' : 'कृपया नाम र सन्देश भर्नुहोस्।';
        } elseif (!empty($email) && !isValidEmail($email)) {
            $error = isEnglish() ? 'Please enter a valid email.' : 'कृपया सही इमेल ठेगाना राख्नुहोस्।';
        } else {
            try {
                $db   = getDB();
                $stmt = $db->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $phone, $subject, $message]);
                $success = true;
                logSecurityEvent('contact_form', 'Contact form submitted by: ' . $name);

                /* v3 connection-fix: contact form ले admin notification trigger गर्दैनथ्यो।
                 * अब अरू forms जस्तै email/SMS पठाउँछ + audit_log मा record गर्छ। */
                if (file_exists(__DIR__ . '/includes/notifications.php')) {
                    require_once __DIR__ . '/includes/notifications.php';
                    try {
                        sendAdminNotification('contact_message', [
                            'नाम'    => $name,
                            'इमेल'   => $email ?: '—',
                            'फोन'    => $phone ?: '—',
                            'विषय'   => $subject ?: '—',
                            'सन्देश' => mb_substr($message, 0, 500),
                        ]);
                    } catch (\Throwable $e) { error_log('contact notify: ' . $e->getMessage()); }
                }
                if (function_exists('auditLog')) auditLog('contact_submit', 'contact_messages', null, null, ['name'=>$name]);
            } catch (Exception $e) {
                $error = isEnglish() ? 'Failed to send message. Please try later.' : 'सन्देश पठाउन सकिएन। कृपया पछि प्रयास गर्नुहोस्।';
            }
        }
    }
}

require_once 'includes/header.php';
?>
<style>
.ct-success-wrap{background:linear-gradient(135deg,color-mix(in srgb,var(--primary-color) 14%,white),color-mix(in srgb,var(--primary-color) 8%,white));}
.ct-success-icon{font-size:3.5rem;color:var(--primary-light);}
.ct-success-title{color:var(--primary-dark);}
.ct-muted{color:var(--text-muted)!important;}
.ct-link-dark{color:var(--text-color);text-decoration:none;}
.ct-btn-primary{background:var(--primary-color);border-color:var(--primary-color);color:var(--text-on-primary);}
.ct-btn-primary:hover{background:var(--primary-dark);border-color:var(--primary-dark);color:var(--text-on-primary);}
.ct-btn-success{background:var(--primary-color);border-color:var(--primary-color);color:var(--text-on-primary);}
.ct-btn-success:hover{background:var(--primary-dark);border-color:var(--primary-dark);color:var(--text-on-primary);}
.ct-icon-lg{font-size:2.5rem;color:var(--primary-color);}
.ct-modal-head{background:linear-gradient(135deg,var(--primary-dark),var(--primary-color));color:var(--text-on-primary);}
.ct-success-block{display:none;}
.ct-success-bigicon{font-size:4rem;color:var(--primary-color);}
.ct-map-frame{border:0;}
.ct-office-bg{background:var(--surface-color);}
.ct-btn-light{background:color-mix(in srgb,var(--primary-color) 8%,white);border:1px solid color-mix(in srgb,var(--primary-color) 18%,white);color:var(--primary-dark);}

/* Modern Contact Page Styles */
.page-banner-modern {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    padding: 4rem 0;
    position: relative;
    overflow: hidden;
}

.page-banner-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    animation: bannerShimmer 4s infinite;
}

@keyframes bannerShimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

.banner-content-modern {
    position: relative;
    z-index: 2;
    text-align: center;
}

.page-title-modern {
    font-size: 2.5rem;
    font-weight: 900;
    color: white;
    margin-bottom: 1rem;
    text-shadow: 0 4px 16px rgba(0,0,0,0.3);
    animation: titleGlow 3s ease-in-out infinite alternate;
}

@keyframes titleGlow {
    from { text-shadow: 0 4px 16px rgba(0,0,0,0.3); }
    to { text-shadow: 0 4px 24px rgba(255,255,255,0.2); }
}

.breadcrumb-modern {
    background: rgba(255,255,255,0.1);
    border-radius: 25px;
    padding: 0.5rem 1rem;
    display: inline-flex;
    backdrop-filter: blur(10px);
}

.breadcrumb-modern .breadcrumb-item {
    color: rgba(255,255,255,0.8);
    font-weight: 600;
}

.breadcrumb-modern .breadcrumb-item a {
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
}

.breadcrumb-modern .breadcrumb-item a:hover {
    background: rgba(255,255,255,0.2);
    transform: translateY(-1px);
}

.breadcrumb-modern .breadcrumb-item.active {
    color: white;
    font-weight: 700;
}

.breadcrumb-link-modern {
    color: rgba(255,255,255,0.9);
    transition: all 0.3s ease;
}

.breadcrumb-link-modern:hover {
    color: white;
    transform: translateX(2px);
}

/* Contact Form Modernization */
.contact-form-modern {
    background: var(--surface-color);
    border: 2px solid color-mix(in srgb, var(--primary-color) 12%, white);
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 8px 32px rgba(var(--primary-rgb), .15);
    transition: all 0.3s cubic-bezier(.4,0,.2,1);
    position: relative;
    overflow: hidden;
}

.contact-form-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--primary-color) 8%, white), transparent);
    transition: left 0.6s ease;
}

.contact-form-modern:hover::before {
    left: 100%;
}

.contact-form-modern:hover {
    transform: translateY(-4px) scale(1.01);
    box-shadow: 0 16px 48px rgba(var(--primary-rgb), .25);
    border-color: var(--primary-color);
}

.contact-input-modern {
    background: var(--surface-color);
    border: 2px solid color-mix(in srgb, var(--primary-color) 8%, white);
    border-radius: 12px;
    padding: 0.8rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
    width: 100%;
}

.contact-input-modern:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), .1);
    outline: none;
    transform: translateY(-2px);
}

.contact-textarea-modern {
    background: var(--surface-color);
    border: 2px solid color-mix(in srgb, var(--primary-color) 8%, white);
    border-radius: 12px;
    padding: 0.8rem 1rem;
    font-size: 1rem;
    transition: all 0.3s ease;
    width: 100%;
    min-height: 120px;
    resize: vertical;
}

.contact-textarea-modern:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), .1);
    outline: none;
    transform: translateY(-2px);
}

.contact-btn-modern {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    color: var(--text-on-primary);
    padding: 0.8rem 2rem;
    border-radius: 25px;
    font-size: 1.1rem;
    font-weight: 700;
    border: 2px solid transparent;
    transition: all 0.3s cubic-bezier(.4,0,.2,1);
    position: relative;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(var(--primary-rgb), .3);
}

.contact-btn-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.contact-btn-modern:hover::before {
    left: 100%;
}

.contact-btn-modern:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 12px 32px rgba(var(--primary-rgb), .4);
    background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .page-banner-modern {
        padding: 3rem 0;
    }
    
    .page-title-modern {
        font-size: 2rem;
        margin-bottom: 0.8rem;
    }
    
    .contact-form-modern {
        padding: 2rem;
        margin-bottom: 1.5rem;
    }
    
    .breadcrumb-modern {
        padding: 0.4rem 0.8rem;
    }
    
    .contact-input-modern,
    .contact-textarea-modern {
        font-size: 0.95rem;
        padding: 0.7rem 0.8rem;
    }
    
    .contact-btn-modern {
        padding: 0.7rem 1.5rem;
        font-size: 1rem;
        border-radius: 20px;
    }
}

@media (max-width: 480px) {
    .page-banner-modern {
        padding: 2rem 0;
    }
    
    .page-title-modern {
        font-size: 1.8rem;
        margin-bottom: 0.6rem;
    }
    
    .contact-form-modern {
        padding: 1.5rem;
        margin-bottom: 1.2rem;
    }
    
    .breadcrumb-modern {
        padding: 0.3rem 0.6rem;
    }
    
    .contact-input-modern,
    .contact-textarea-modern {
        font-size: 0.9rem;
        padding: 0.6rem 0.7rem;
    }
    
    .contact-btn-modern {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
        border-radius: 18px;
    }
}
</style>

<!-- Page Banner -->
<section class="page-banner page-banner-modern">
    <div class="container">
        <div class="banner-content-modern">
            <h1 class="page-title-modern"><?php echo isEnglish() ? 'Contact Us' : 'सम्पर्क गर्नुहोस्'; ?></h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-modern">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="breadcrumb-link-modern"><?php echo $L['home']; ?></a></li>
                    <li class="breadcrumb-item active"><?php echo $L['contact'] ?? 'सम्पर्क'; ?></li>
                </ol>
            </nav>
        </div>
    </div>
</section>

<?php if ($success): ?>
<section class="py-5 ct-success-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7 text-center">
                <div class="ct-success-icon"><i class="fas fa-check-circle"></i></div>
                <h3 class="mt-3 fw-bold ct-success-title"><?php echo isEnglish() ? 'Message Sent Successfully!' : 'सन्देश सफलतापूर्वक पठाइयो!'; ?></h3>
                <p class="ct-muted mb-4"><?php echo isEnglish() ? 'Thank you for contacting us. We will respond shortly.' : 'सम्पर्क गर्नुभएकोमा धन्यवाद। हामी छिट्टै जवाफ दिनेछौं।'; ?></p>
                <a href="contact.php" class="btn ct-btn-success px-4 me-2"><i class="fas fa-envelope me-1"></i><?php echo isEnglish() ? 'Send Another' : 'फेरि पठाउनुहोस्'; ?></a>
                <a href="<?php echo SITE_URL; ?>" class="btn btn-outline-secondary px-4"><i class="fas fa-home me-1"></i><?php echo isEnglish() ? 'Home' : 'गृहपृष्ठ'; ?></a>
            </div>
        </div>
    </div>
</section>
<?php else: ?>
<!-- Contact Section -->
<section class="contact-section section-padding">
    <div class="container">
        <div class="row g-4">

            <!-- ===== सम्पर्क जानकारी ===== -->
            <div class="col-lg-4">
                <div class="contact-info-box h-100">
                    <h4><?php echo isEnglish() ? 'Contact Information' : 'सम्पर्क जानकारी'; ?></h4>
                    <p><?php echo isEnglish() ? 'Reach us through the following channels.' : 'हामीसँग सम्पर्क गर्न तलका माध्यमहरू प्रयोग गर्नुहोस्।'; ?></p>

                    <div class="contact-item">
                        <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="contact-details">
                            <h6><?php echo isEnglish() ? 'Address' : 'ठेगाना'; ?></h6>
                            <p><?php echo $address; ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                        <div class="contact-details">
                            <h6><?php echo isEnglish() ? 'Phone' : 'फोन'; ?></h6>
                            <?php if ($phone): ?>
                            <p><a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $phone); ?>" class="ct-link-dark"><?php echo e($phone); ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fas fa-mobile-alt"></i></div>
                        <div class="contact-details">
                            <h6><?php echo isEnglish() ? 'Mobile' : 'मोबाइल'; ?></h6>
                            <?php if ($mobile): ?>
                            <p><a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $mobile); ?>" class="ct-link-dark"><?php echo e($mobile); ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                        <div class="contact-details">
                            <h6><?php echo isEnglish() ? 'Email' : 'इमेल'; ?></h6>
                            <?php if ($email): ?>
                            <p><a href="mailto:<?php echo e($email); ?>" class="ct-link-dark"><?php echo e($email); ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="contact-social">
                        <h6><?php echo isEnglish() ? 'Social Media' : 'सामाजिक सञ्जाल'; ?></h6>
                        <a href="<?php echo $facebookUrl; ?>" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="<?php echo $youtubeUrl; ?>"  target="_blank"><i class="fab fa-youtube"></i></a>
                    </div>

                    <!-- सन्देश पठाउने बटन — modal खोल्छ -->
                    <div class="mt-4">
                        <button class="btn ct-btn-primary btn-lg w-100" data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="fas fa-envelope me-2"></i>
                            <?php echo isEnglish() ? 'Send Message' : 'सन्देश पठाउनुहोस्'; ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ===== Quick-access cards ===== -->
            <div class="col-lg-8">
                <div class="row g-3 h-100 align-content-start">

                    <!-- Online Banking -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100 text-center p-4">
                            <div class="mb-3 ct-icon-lg">
                                <i class="fas fa-university"></i>
                            </div>
                            <h5><?php echo isEnglish() ? 'Online Banking' : 'अनलाइन बैंकिङ'; ?></h5>
                            <p class="ct-muted small"><?php echo isEnglish() ? 'Access your account anytime, anywhere.' : 'जुनसुकै समय, जहाँबाट पनि खाता हेर्नुहोस्।'; ?></p>
                            <a href="<?php echo getSetting('internet_banking_url','#'); ?>" target="_blank" class="btn btn-outline-primary btn-sm mt-auto">
                                <?php echo isEnglish() ? 'Login' : 'लग-इन'; ?> <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Appointment -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100 text-center p-4">
                            <div class="mb-3 ct-icon-lg">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h5><?php echo isEnglish() ? 'Book Appointment' : 'अपोइन्टमेन्ट बुक'; ?></h5>
                            <p class="ct-muted small"><?php echo isEnglish() ? 'Schedule a meeting with our team.' : 'हाम्रो टोलीसँग भेटको समय तय गर्नुहोस्।'; ?></p>
                            <a href="<?php echo SITE_URL; ?>appointment.php" class="btn btn-outline-primary btn-sm mt-auto">
                                <?php echo isEnglish() ? 'Book Now' : 'बुक गर्नुहोस्'; ?> <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Loan Apply -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100 text-center p-4">
                            <div class="mb-3 ct-icon-lg">
                                <i class="fas fa-hand-holding-usd"></i>
                            </div>
                            <h5><?php echo isEnglish() ? 'Apply for Loan' : 'कर्जा आवेदन'; ?></h5>
                            <p class="ct-muted small"><?php echo isEnglish() ? 'Quick and easy loan application.' : 'सजिलो र छिटो कर्जा आवेदन गर्नुहोस्।'; ?></p>
                            <a href="<?php echo SITE_URL; ?>loan-apply.php" class="btn btn-outline-primary btn-sm mt-auto">
                                <?php echo isEnglish() ? 'Apply Now' : 'आवेदन गर्नुहोस्'; ?> <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Grievance -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100 text-center p-4">
                            <div class="mb-3 ct-icon-lg">
                                <i class="fas fa-comment-dots"></i>
                            </div>
                            <h5><?php echo isEnglish() ? 'Grievance' : 'गुनासो'; ?></h5>
                            <p class="ct-muted small"><?php echo isEnglish() ? 'Submit your complaint or feedback.' : 'तपाईंको गुनासो वा सुझाव पठाउनुहोस्।'; ?></p>
                            <a href="<?php echo SITE_URL; ?>grievance.php" class="btn btn-outline-primary btn-sm mt-auto">
                                <?php echo isEnglish() ? 'Submit' : 'पठाउनुहोस्'; ?> <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>

                </div><!-- /row -->
            </div><!-- /col-lg-8 -->

        </div><!-- /main row -->
    </div>
</section>

<!-- =============================================
     सम्पर्क Modal — Bootstrap 5
     ============================================= -->
<div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header ct-modal-head">
                <h5 class="modal-title" id="contactModalLabel">
                    <i class="fas fa-envelope-open-text me-2"></i>
                    <?php echo isEnglish() ? 'Send Message' : 'सन्देश पठाउनुहोस्'; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4">

                <!-- सफलता सन्देश -->
                <div id="contactSuccess" class="text-center py-4 ct-success-block">
                    <div class="ct-success-bigicon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h4 class="mt-3 fw-bold"><?php echo isEnglish() ? 'Message Sent!' : 'सन्देश पठाइयो!'; ?></h4>
                    <p class="ct-muted">
                        <?php echo isEnglish()
                            ? 'Thank you for contacting us. We will get back to you shortly.'
                            : 'धन्यवाद! तपाईंको सन्देश प्राप्त भयो। हामी चाँडै सम्पर्क गर्नेछौं।'; ?>
                    </p>
                    <button class="btn ct-btn-primary mt-2" data-bs-dismiss="modal">
                        <?php echo isEnglish() ? 'Close' : 'बन्द गर्नुहोस्'; ?>
                    </button>
                </div>

                <!-- Error alert -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Contact Form -->
                <form method="POST" action="contact.php" id="contactForm" class="needs-validation" novalidate>
                    <?php echo csrfField(); ?>

                    <div class="row g-3">
                        <!-- नाम -->
                        <div class="col-md-6">
                            <label class="form-label fw-500"><?php echo isEnglish() ? 'Full Name' : 'पूरा नाम'; ?> <span class="req">*</span></label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="<?php echo isEnglish() ? 'Your name' : 'तपाईंको नाम'; ?>">
                        </div>
                        <!-- इमेल -->
                        <div class="col-md-6">
                            <label class="form-label"><?php echo isEnglish() ? 'Email' : 'इमेल'; ?></label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="<?php echo isEnglish() ? 'Your email' : 'तपाईंको इमेल'; ?>">
                        </div>
                        <!-- फोन -->
                        <div class="col-md-6">
                            <label class="form-label"><?php echo isEnglish() ? 'Phone' : 'फोन नम्बर'; ?></label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="<?php echo isEnglish() ? 'Your phone' : 'तपाईंको फोन'; ?>">
                        </div>
                        <!-- विषय -->
                        <div class="col-md-6">
                            <label class="form-label"><?php echo isEnglish() ? 'Subject' : 'विषय'; ?></label>
                            <input type="text" name="subject" class="form-control"
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="<?php echo isEnglish() ? 'Message subject' : 'सन्देशको विषय'; ?>">
                        </div>
                        <!-- सन्देश -->
                        <div class="col-12">
                            <label class="form-label"><?php echo isEnglish() ? 'Message' : 'सन्देश'; ?> <span class="req">*</span></label>
                            <textarea name="message" class="form-control" rows="4" required
                                      placeholder="<?php echo isEnglish() ? 'Write your message here...' : 'तपाईंको सन्देश लेख्नुहोस्...'; ?>"><?php echo htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div><!-- /row -->

                    <div class="modal-footer px-0 pb-0 mt-4">
                        <button type="button" class="btn ct-btn-light" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i> <?php echo isEnglish() ? 'Cancel' : 'रद्द'; ?>
                        </button>
                        <button type="submit" class="btn ct-btn-primary">
                            <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span><i class="fas fa-paper-plane me-1"></i>
                            <?php echo isEnglish() ? 'Send Message' : 'सन्देश पठाउनुहोस्'; ?>
                        </button>
                    </div>
                </form>

            </div><!-- /modal-body -->
        </div>
    </div>
</div>

<!-- Map Section -->
<section class="map-section">
    <div class="container-fluid p-0">
        <div class="map-wrapper">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3532.456!2d85.3240!3d27.7172!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMjfCsDQzJzAyLjAiTiA4NcKwMTknMjYuNCJF!5e0!3m2!1sen!2snp!4v1234567890"
                width="100%" height="400" class="ct-map-frame" allowfullscreen="" loading="lazy">
            </iframe>
        </div>
    </div>
</section>

<!-- Office Hours -->
<section class="office-hours section-padding ct-office-bg">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="hours-box text-center">
                    <h3><i class="fas fa-clock"></i> <?php echo isEnglish() ? 'Office Hours' : 'कार्यालय समय'; ?></h3>
                    <?php
                    $weekdayHours  = getSetting('working_hours',  'बिहान १०:०० - साँझ ५:००');
                    $saturdayHours = getSetting('saturday_hours', 'बिहान १०:०० - दिउँसो १:००');
                    ?>
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="hour-item">
                                <h5><?php echo isEnglish() ? 'Sunday – Friday' : 'आइतबार - शुक्रबार'; ?></h5>
                                <p><?php echo htmlspecialchars($weekdayHours, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="hour-item">
                                <h5><?php echo isEnglish() ? 'Saturday' : 'शनिबार'; ?></h5>
                                <p><?php echo htmlspecialchars($saturdayHours, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =============================================
   JS — POST पछि modal auto-open गर्नुहोस्
   ============================================= -->
<script>
(function () {
    var wasPosted   = <?php echo ($_SERVER['REQUEST_METHOD'] === 'POST') ? 'true' : 'false'; ?>;
    var isSuccess   = <?php echo $success ? 'true' : 'false'; ?>;

    if (!wasPosted) return; // GET request — modal stays closed

    /* POST भएको छ → modal खोल्नुहोस् */
    var modalEl = document.getElementById('contactModal');
    if (!modalEl) return;
    var modal = new bootstrap.Modal(modalEl);

    if (isSuccess) {
        /* सफल भयो — form लुकाउनुहोस्, success message देखाउनुहोस् */
        var form    = document.getElementById('contactForm');
        var success = document.getElementById('contactSuccess');
        if (form)    form.style.display    = 'none';
        if (success) success.style.display = 'block';
    }

})();
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
