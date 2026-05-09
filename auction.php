<?php
require_once 'includes/config.php';
require_once 'includes/ensure-tables.php';
$pageTitle = isEnglish() ? 'Auction Notices' : 'लिलामी सूचना';
require_once 'includes/header.php';
$L = getLangStrings();

// Get auctions
try {
    $db = getDB();
    $auctions = $db->query("SELECT * FROM auction_notices WHERE is_active = 1 ORDER BY auction_date DESC LIMIT 10")->fetchAll();
} catch (Exception $e) {
    $auctions = [];
}

// Handle bid submission
$bidSuccess = false;
$bidError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bid'])) {
    if (!verifyCSRFToken()) {
        $bidError = isEnglish() ? 'Security check failed.' : 'सुरक्षा जाँच असफल।';
    } elseif (!checkRateLimit('auction_bid', 5, 60)) {
        $bidError = isEnglish() ? 'Too many requests.' : 'धेरै अनुरोधहरू।';
    } elseif (!empty($_POST['bid_form_token']) && isset($_SESSION['last_bid_form_token']) && $_SESSION['last_bid_form_token'] === $_POST['bid_form_token']) {
        $bidError = isEnglish() ? 'This bid was already submitted. Please refresh before submitting again.' : 'यो बोलपत्र पहिले नै पेश भइसकेको छ। फेरि पेश गर्न पेज refresh गर्नुहोस्।';
    } else {
        $auction_id = intval($_POST['auction_id'] ?? 0);
        $bidder_name = clean_text($_POST['bidder_name'] ?? '', 200);
        $bidder_phone = preg_replace('/[^0-9]/', '', clean_text($_POST['bidder_phone'] ?? '', 20));
        $bidder_email = strtolower(clean_text($_POST['bidder_email'] ?? '', 254));
        $bidder_address = clean_text($_POST['bidder_address'] ?? '', 500);
        $bid_amount = floatval($_POST['bid_amount'] ?? 0);
        $message = clean_text($_POST['message'] ?? '', 2000);

        if (empty($bidder_name) || empty($bidder_phone) || $bid_amount <= 0) {
            $bidError = isEnglish() ? 'Please fill all required fields.' : 'कृपया सबै आवश्यक फिल्डहरू भर्नुहोस्।';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("INSERT INTO auction_bids (auction_id, bidder_name, bidder_phone, bidder_email, bidder_address, bid_amount, message) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$auction_id, $bidder_name, $bidder_phone, $bidder_email, $bidder_address, $bid_amount, $message]);
                $_SESSION['last_bid_form_token'] = $_POST['bid_form_token'] ?? '';
                $bidSuccess = true;
            } catch (Exception $e) {
                $bidError = isEnglish() ? 'Failed to submit bid.' : 'बोलपत्र पेश गर्न सकिएन।';
            }
        }
    }
}
?>

<!-- Page Banner -->
<section class="page-banner">
    <div class="container">
        <h1><?php echo $pageTitle; ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>"><?php echo $L['home']; ?></a></li>
                <li class="breadcrumb-item active"><?php echo $pageTitle; ?></li>
            </ol>
        </nav>
    </div>
</section>

<!-- ===== Auction Section ===== -->
<style>
/* ─── Auction v2 — Full-width card with tabbed details ─── */
.auc2-wrap { margin-bottom: 2.5rem; }

/* Card */
.auc2-card {
    background: var(--surface-color);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(var(--primary-rgb), .15);
    border: 2px solid color-mix(in srgb, var(--primary-color) 18%, white);
    transition: all .4s cubic-bezier(.4,0,.2,1);
    transform: translateY(0);
}
.auc2-card:hover {
    transform: translateY(-6px) scale(1.01);
    box-shadow: 0 16px 48px rgba(var(--primary-rgb), .25);
    border-color: var(--primary-color);
}

/* ── Hero row: image left, summary right ── */
.auc2-hero { display: flex; flex-wrap: wrap; }

/* Gallery pane */
.auc2-gallery-pane {
    flex: 0 0 100%;
    max-width: 100%;
    background: #1a1a2e;
    position: relative;
}
@media (min-width:768px) {
    .auc2-gallery-pane { flex: 0 0 48%; max-width: 48%; }
}

.auc2-main-img {
    width: 100%; height: 300px;
    object-fit: cover; display: block;
    cursor: zoom-in; transition: transform .3s;
}
@media (min-width:768px) { .auc2-main-img { height: 340px; } }
@media (min-width:992px) { .auc2-main-img { height: 380px; } }

.auc2-main-img:hover { transform: scale(1.02); }

/* Thumbnail strip */
.auc2-thumbs {
    display: flex; gap: 6px; padding: 8px 10px;
    background: #111;
    overflow-x: auto;
    scrollbar-width: thin; scrollbar-color: #444 #111;
}
.auc2-thumbs img {
    width: 60px; height: 45px;
    object-fit: cover; border-radius: 5px;
    cursor: pointer; opacity: .6; transition: opacity .2s, outline .2s;
    flex-shrink: 0;
}
.auc2-thumbs img.active, .auc2-thumbs img:hover { opacity: 1; outline: 2px solid #e74c3c; }

/* No-image placeholder */
.auc2-no-img {
    width: 100%; height: 280px;
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 8%, white), color-mix(in srgb, var(--primary-color) 15%, white));
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; color: var(--primary-color);
    position: relative;
    overflow: hidden;
}
.auc2-no-img::before {
    content: '';
    position: absolute;
    top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(45deg, transparent, color-mix(in srgb, var(--primary-color) 5%, white), transparent);
    animation: shimmer 3s infinite;
}
@keyframes shimmer {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}
.auc2-no-img i {
    font-size: 3.5rem;
    margin-bottom: 1rem;
    animation: float 3s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}
.auc2-no-img span {
    font-size: .95rem;
    font-weight: 600;
    background: color-mix(in srgb, var(--primary-color) 15%, white);
    padding: .5rem 1rem;
    border-radius: 20px;
    border: 1px solid color-mix(in srgb, var(--primary-color) 25%, white);
}

/* ── Summary pane (right) ── */
.auc2-summary-pane {
    flex: 1; padding: 2rem 2.2rem;
    display: flex; flex-direction: column;
    border-left: 2px solid color-mix(in srgb, var(--primary-color) 15%, white);
    background: linear-gradient(135deg, var(--surface-color), color-mix(in srgb, var(--primary-color) 5%, white));
}

.auc2-status-row {
    display: flex; align-items: center; gap: .6rem;
    margin-bottom: 1rem; flex-wrap: wrap;
}
.auc2-badge-status {
    font-size: .78rem; font-weight: 800; letter-spacing: .6px;
    padding: .4em 1em; border-radius: 25px; text-transform: uppercase;
    border: 2px solid transparent;
    transition: all .3s;
    position: relative;
    overflow: hidden;
}
.auc2-badge-status::before {
    content: '';
    position: absolute; top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.3), transparent);
    transition: left .6s;
}
.auc2-badge-status:hover::before {
    left: 100%;
}
.auc2-badge-status.s-upcoming  { 
    background: linear-gradient(135deg, color-mix(in srgb, var(--accent-color) 25%, white), color-mix(in srgb, var(--accent-color) 35%, white)); 
    color: var(--accent-dark); 
    border-color: color-mix(in srgb, var(--accent-color) 30%, white);
}
.auc2-badge-status.s-ongoing   { 
    background: linear-gradient(135deg, color-mix(in srgb, var(--accent-color) 35%, white), color-mix(in srgb, var(--accent-color) 45%, white)); 
    color: var(--accent-dark); 
    border-color: color-mix(in srgb, var(--accent-color) 40%, white);
    animation: auc2-pulse 1.6s infinite;
}
.auc2-badge-status.s-completed { 
    background: linear-gradient(135deg, color-mix(in srgb, var(--text-muted) 25%, white), color-mix(in srgb, var(--text-muted) 35%, white)); 
    color: var(--text-muted); 
    border-color: color-mix(in srgb, var(--text-muted) 30%, white);
}
.auc2-badge-status.s-cancelled { 
    background: linear-gradient(135deg, color-mix(in srgb, var(--secondary-color) 25%, white), color-mix(in srgb, var(--secondary-color) 35%, white)); 
    color: var(--secondary-dark); 
    border-color: color-mix(in srgb, var(--secondary-color) 30%, white);
}
@keyframes auc2-pulse {
    0%,100% { box-shadow: 0 0 0 0 rgba(25,135,84,.35); }
    50%      { box-shadow: 0 0 0 6px rgba(25,135,84,0); }
}
.auc2-serial { font-size:.78rem; color:#6c757d; }

.auc2-title {
    font-size: 1.4rem; font-weight: 800;
    color: var(--primary-dark); margin-bottom: 1.2rem;
    line-height: 1.3;
    transition: color .3s;
}
.auc2-card:hover .auc2-title {
    color: var(--primary-color);
}

/* Info grid */
.auc2-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .8rem; margin-bottom: 1.5rem;
}
.auc2-info-item {
    background: linear-gradient(135deg, color-mix(in srgb, var(--primary-color) 8%, white), color-mix(in srgb, var(--primary-color) 12%, white));
    border-radius: 14px;
    padding: .8rem 1rem;
    border-left: 4px solid var(--primary-color);
    transition: all .3s;
    position: relative;
    overflow: hidden;
}
.auc2-info-item::before {
    content: '';
    position: absolute; top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--primary-color) 10%, white), transparent);
    transition: left .5s;
}
.auc2-info-item:hover::before {
    left: 100%;
}
.auc2-info-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(var(--primary-rgb), .15);
    border-left-color: var(--primary-dark);
}
.auc2-info-item.highlight { 
    background: linear-gradient(135deg, color-mix(in srgb, var(--accent-color) 15%, white), color-mix(in srgb, var(--accent-color) 20%, white)); 
    border-left-color: var(--accent-color);
}
.auc2-info-item.highlight:hover {
    border-left-color: var(--accent-dark);
}
.auc2-info-item.price-item { 
    background: linear-gradient(135deg, color-mix(in srgb, var(--secondary-color) 15%, white), color-mix(in srgb, var(--secondary-color) 20%, white)); 
    border-left-color: var(--secondary-color);
}
.auc2-info-item.price-item:hover {
    border-left-color: var(--secondary-dark);
}
.auc2-info-label {
    font-size: .72rem; color: var(--text-muted);
    text-transform: uppercase; letter-spacing: .5px;
    margin-bottom: .2rem; display: flex; align-items: center; gap: .4rem;
    font-weight: 700;
}
.auc2-info-value {
    font-size: .95rem; font-weight: 800; color: var(--primary-dark);
    transition: color .3s;
}
.auc2-info-item:hover .auc2-info-value {
    color: var(--primary-color);
}
.auc2-price-value { 
    font-size: 1.3rem; 
    color: var(--secondary-dark); 
    font-weight: 900;
    text-shadow: 0 1px 2px rgba(var(--secondary-rgb), .1);
    animation: price-glow 2s ease-in-out infinite;
}
@keyframes price-glow {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}

/* Countdown */
.auc2-countdown {
    display: flex; gap: .6rem; margin-bottom: 1.2rem; flex-wrap: wrap;
}
.auc2-cd-box {
    background: linear-gradient(135deg, var(--primary-dark), var(--primary-color)); color: var(--text-on-primary);
    border-radius: 12px; padding: .5rem .8rem;
    text-align: center; min-width: 56px;
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), .3);
    transition: all .3s;
    position: relative;
    overflow: hidden;
}
.auc2-cd-box::before {
    content: '';
    position: absolute; top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(45deg, transparent, rgba(255,255,255,.1), transparent);
    animation: countdown-shimmer 3s infinite;
}
@keyframes countdown-shimmer {
    0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
    100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
}
.auc2-cd-box:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 6px 16px rgba(var(--primary-rgb), .4);
}
.auc2-cd-num { 
    font-size: 1.2rem; 
    font-weight: 900; 
    line-height: 1; 
    text-shadow: 0 1px 2px rgba(0,0,0,.2);
    position: relative; z-index: 1;
}
.auc2-cd-lbl { 
    font-size: .65rem; 
    opacity: .9; 
    text-transform: uppercase; 
    letter-spacing: .5px;
    font-weight: 700;
    position: relative; z-index: 1;
}

/* CTA */
.auc2-cta { margin-top: auto; padding-top: 1.5rem; }
.auc2-bid-btn {
    background: linear-gradient(135deg, var(--secondary-color), var(--secondary-dark));
    color: var(--text-on-secondary); border: none; border-radius: 14px;
    padding: .85rem 1.8rem; font-weight: 800; font-size: 1rem;
    width: 100%; cursor: pointer;
    transition: all .3s cubic-bezier(.4,0,.2,1);
    box-shadow: 0 6px 20px rgba(var(--secondary-rgb), .4);
    position: relative;
    overflow: hidden;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.auc2-bid-btn::before {
    content: '';
    position: absolute; top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(135deg, var(--secondary-dark), var(--secondary-color));
    transition: left .3s cubic-bezier(.4,0,.2,1);
    z-index: 0;
}
.auc2-bid-btn:hover::before {
    left: 0;
}
.auc2-bid-btn:hover { 
    transform: translateY(-4px) scale(1.02);
    box-shadow: 0 10px 30px rgba(var(--secondary-rgb), .5);
    color: var(--text-on-secondary);
}
.auc2-bid-btn i { 
    margin-right: .5rem;
    position: relative; z-index: 1;
    transition: transform .3s;
}
.auc2-bid-btn:hover i {
    transform: translateX(3px) rotate(5deg);
}
.auc2-bid-btn span {
    position: relative; z-index: 1;
}

/* ── Tabs ── */
.auc2-tabs-wrap { border-top: 1px solid #e9ecef; }
.auc2-nav {
    display: flex; background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    padding: 0 1.2rem;
}
.auc2-tab-btn {
    padding: .85rem 1.4rem;
    font-size: .88rem; font-weight: 600; color: #6c757d;
    background: none; border: none; border-bottom: 3px solid transparent;
    margin-bottom: -2px; cursor: pointer;
    transition: color .2s, border-color .2s;
    display: flex; align-items: center; gap: .4rem;
}
.auc2-tab-btn:hover { color: #212529; }
.auc2-tab-btn.active { color: #e74c3c; border-bottom-color: #e74c3c; }
.auc2-tab-pane { display: none; padding: 1.5rem 1.8rem; }
.auc2-tab-pane.active { display: block; }

/* Overview tab */
.auc2-desc-block { color: #495057; line-height: 1.75; margin-bottom: 1.2rem; }
.auc2-contact-row {
    display: flex; gap: 1rem; flex-wrap: wrap;
    background: #f0f7ff; border-radius: 10px; padding: 1rem 1.2rem;
    align-items: center;
}
.auc2-contact-item { display: flex; align-items: center; gap: .5rem; font-size: .9rem; color: #212529; }
.auc2-contact-item i { color: #0d6efd; width: 16px; text-align: center; }
.auc2-contact-item a { color: #0d6efd; text-decoration: none; font-weight: 600; }
.auc2-contact-item a:hover { text-decoration: underline; }

/* Photos tab */
.auc2-photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
}
.auc2-photo-grid img {
    width: 100%; aspect-ratio: 4/3;
    object-fit: cover; border-radius: 8px;
    cursor: zoom-in; transition: transform .2s, box-shadow .2s;
}
.auc2-photo-grid img:hover { transform: scale(1.04); box-shadow: 0 4px 16px rgba(0,0,0,.2); }

/* Documents tab */
.auc2-doc-list { display: flex; flex-direction: column; gap: .75rem; }
.auc2-doc-item {
    display: flex; align-items: center; gap: 1rem;
    background: #fff8f8; border: 1px solid #fdd; border-radius: 10px;
    padding: 1rem 1.2rem;
}
.auc2-doc-icon { font-size: 2rem; color: #e74c3c; flex-shrink: 0; }
.auc2-doc-info { flex: 1; }
.auc2-doc-name { font-weight: 600; color: #212529; font-size: .92rem; }
.auc2-doc-desc { font-size: .8rem; color: #6c757d; margin-top: .1rem; }

/* Map tab */
.auc2-map-container { border-radius: 10px; overflow: hidden; }
.auc2-map-container iframe { width: 100%; height: 350px; border: 0; display: block; }
.auc2-map-link-wrap { margin-top: 1rem; }

/* No-content placeholder */
.auc2-empty-tab { text-align: center; padding: 2.5rem 1rem; color: #adb5bd; }
.auc2-empty-tab i { font-size: 2.5rem; margin-bottom: .75rem; display: block; }

/* Lightbox overlay */
#auc2-lightbox {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.92); z-index: 9999;
    align-items: center; justify-content: center;
    padding: 1rem;
}
#auc2-lightbox.open { display: flex; }
#auc2-lightbox img {
    max-width: 92vw; max-height: 88vh;
    border-radius: 8px; box-shadow: 0 8px 40px rgba(0,0,0,.6);
    object-fit: contain;
}
#auc2-lightbox-close {
    position: fixed; top: 1rem; right: 1.2rem;
    color:#fff; font-size: 2rem; cursor: pointer;
    background: none; border: none; line-height: 1;
    z-index: 10000;
}

/* ── Bid Modal (uniform/global form layer) ── */
.auc2-bid-modal-head{
    background:linear-gradient(135deg,var(--primary-dark),var(--primary-color));
    color:var(--text-on-primary);
}
.auc2-bid-modal-title{font-weight:700;letter-spacing:.2px;}
.auc2-bid-form{padding:.15rem 0;}
.auc2-bid-note{
    background:color-mix(in srgb,var(--secondary-color) 12%,white);
    border:1px solid color-mix(in srgb,var(--secondary-color) 22%,white);
    color:var(--secondary-dark);
    border-radius:10px;
    font-weight:600;
}
.auc2-bid-min-amt{color:var(--secondary-dark);font-weight:800;}
.auc2-bid-label{font-size:.88rem;font-weight:700;color:var(--primary-dark);margin-bottom:.4rem;}
.auc2-req{color:var(--secondary-color);}
.auc2-bid-input,.auc2-bid-textarea{
    border:1.5px solid color-mix(in srgb,var(--primary-color) 16%,var(--gray-300));
    border-radius:10px;
    min-height:44px;
    font-size:.92rem;
}
.auc2-bid-input:focus,.auc2-bid-textarea:focus{
    border-color:var(--primary-color);
    box-shadow:0 0 0 3px rgba(var(--primary-rgb),.14);
}
.auc2-bid-addon{
    background:color-mix(in srgb,var(--primary-color) 8%,white);
    border:1.5px solid color-mix(in srgb,var(--primary-color) 16%,var(--gray-300));
    border-right:none;
    color:var(--primary-dark);
    font-weight:700;
}
.auc2-bid-help{font-size:.78rem;color:var(--text-muted);}
.auc2-bid-help-warn{font-size:.78rem;color:var(--secondary-dark);font-weight:600;}
.auc2-bid-footer{padding-top:.4rem;}
.auc2-bid-cancel{
    border-color:color-mix(in srgb,var(--primary-color) 18%,var(--gray-300));
    color:var(--text-color);
}
.auc2-bid-cancel:hover{
    background:color-mix(in srgb,var(--primary-color) 8%,white);
    border-color:var(--primary-color);
    color:var(--primary-dark);
}
.auc2-bid-submit{
    background:linear-gradient(135deg,var(--secondary-color),var(--secondary-dark));
    border-color:var(--secondary-color);
    color:var(--text-on-secondary);
    font-weight:700;
}
.auc2-bid-submit:hover{
    background:linear-gradient(135deg,var(--secondary-dark),var(--secondary-color));
    border-color:var(--secondary-dark);
    color:var(--text-on-secondary);
}
</style>

<style>
/* ── Auction Filter Bar ── */
.auc2-page-hero {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
    padding: 2rem 0 1.8rem; color: #fff; margin-bottom: 0;
}
.auc2-page-hero-inner { display: flex; align-items: center; gap: 1.2rem; flex-wrap: wrap; }
.auc2-page-icon {
    width: 64px; height: 64px; border-radius: 16px;
    background: rgba(231,76,60,.2); border: 1px solid rgba(231,76,60,.35);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; color: #e74c3c; flex-shrink: 0;
}
.auc2-page-text { flex: 1; min-width: 160px; }
.auc2-page-text h2 { font-size: 1.55rem; font-weight: 800; color: #fff; margin-bottom: .25rem; }
.auc2-page-text p  { color: rgba(255,255,255,.6); margin: 0; font-size: .9rem; }
.auc2-hero-stats  { display: flex; gap: .65rem; flex-wrap: wrap; }
.auc2-hstat {
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
    border-radius: 10px; padding: .6rem 1rem; text-align: center; min-width: 72px;
}
.auc2-hstat-num { font-size: 1.4rem; font-weight: 800; color: #fff; line-height: 1; }
.auc2-hstat-num.green { color: #4ecdc4; }
.auc2-hstat-num.yellow { color: #ffd93d; }
.auc2-hstat-lbl { font-size: .62rem; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: .3px; margin-top: .18rem; }

/* Filter bar */
.auc2-filterbar {
    background: var(--surface-color); border-bottom: 3px solid color-mix(in srgb, var(--primary-color) 15%, white);
    padding: 1rem 0; position: sticky; top: 0; z-index: 100;
    box-shadow: 0 4px 20px rgba(var(--primary-rgb), .12);
    backdrop-filter: blur(10px);
}
.auc2-filterbar .container { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.auc2-fbar-label { font-size: .82rem; font-weight: 800; color: var(--primary-dark); display: flex; align-items: center; gap: .4rem; text-transform: uppercase; letter-spacing: .4px; }
.auc2-fchips { display: flex; gap: .6rem; flex-wrap: wrap; }
.auc2-fchip {
    padding: .45rem 1.1rem; border-radius: 25px;
    font-size: .85rem; font-weight: 700; cursor: pointer;
    border: 2px solid color-mix(in srgb, var(--primary-color) 18%, white); background: color-mix(in srgb, var(--primary-color) 8%, white); color: var(--primary-dark);
    transition: all .3s cubic-bezier(.4,0,.2,1); display: inline-flex; align-items: center; gap: .4rem;
    position: relative; overflow: hidden;
}
.auc2-fchip::before {
    content: '';
    position: absolute; top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    transition: left .3s cubic-bezier(.4,0,.2,1);
    z-index: 0;
}
.auc2-fchip:hover::before {
    left: 0;
}
.auc2-fchip:hover  { 
    border-color: var(--primary-color); 
    color: var(--text-on-primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), .3);
}
.auc2-fchip.active { 
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); 
    color: var(--text-on-primary); 
    border-color: var(--primary-color);
    transform: scale(1.05);
    box-shadow: 0 4px 16px rgba(var(--primary-rgb), .4);
}
.auc2-fchip.active.green  { 
    background: linear-gradient(135deg, var(--accent-color), var(--accent-dark)); 
    border-color: var(--accent-color);
    box-shadow: 0 4px 16px rgba(var(--accent-rgb), .4);
}
.auc2-fchip.active.yellow { 
    background: linear-gradient(135deg, color-mix(in srgb, var(--accent-color) 80%, var(--accent-color)), var(--accent-color)); 
    border-color: var(--accent-color);
    box-shadow: 0 4px 16px rgba(var(--accent-rgb), .4);
}
.auc2-fchip.active.grey   { 
    background: linear-gradient(135deg, var(--text-muted), color-mix(in srgb, var(--text-muted) 80%, black)); 
    border-color: var(--text-muted);
    box-shadow: 0 4px 16px rgba(var(--text-muted-rgb), .4);
}
.auc2-fchip i, .auc2-fchip span {
    position: relative; z-index: 1;
}
.auc2-fcount {
    background: rgba(255,255,255,.25); color: inherit;
    border-radius: 12px; padding: 0 .45rem; font-size: .72rem; min-width: 18px; text-align: center;
    font-weight: 800;
}
.auc2-fchip:not(.active) .auc2-fcount { background: color-mix(in srgb, var(--primary-color) 20%, white); color: var(--primary-dark); }
.auc2-no-filter {
    text-align: center; padding: 3rem 1.5rem;
    background: #fff; border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06); display: none;
}

/* Mobile Responsive */
@media (max-width: 767px) {
    .auc2-card {
        border-radius: 16px;
        margin-bottom: 2rem;
    }
    .auc2-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 36px rgba(var(--primary-rgb), .22);
    }
    
    .auc2-summary-pane {
        padding: 1.5rem 1.8rem;
        border-left: none;
        border-top: 2px solid color-mix(in srgb, var(--primary-color) 15%, white);
    }
    
    .auc2-title {
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }
    
    .auc2-info-grid {
        grid-template-columns: 1fr;
        gap: .6rem;
        margin-bottom: 1.2rem;
    }
    
    .auc2-info-item {
        padding: .7rem .9rem;
    }
    
    .auc2-price-value {
        font-size: 1.15rem;
    }
    
    .auc2-countdown {
        gap: .4rem;
        margin-bottom: 1rem;
    }
    
    .auc2-cd-box {
        min-width: 48px;
        padding: .4rem .6rem;
    }
    
    .auc2-cd-num {
        font-size: 1rem;
    }
    
    .auc2-cd-lbl {
        font-size: .6rem;
    }
    
    .auc2-bid-btn {
        padding: .75rem 1.5rem;
        font-size: .9rem;
    }
    
    .auc2-filterbar {
        padding: .8rem 0;
    }
    
    .auc2-filterbar .container {
        gap: .8rem;
    }
    
    .auc2-fchips {
        gap: .4rem;
        flex-wrap: wrap;
    }
    
    .auc2-fchip {
        padding: .35rem .9rem;
        font-size: .8rem;
    }
    
    .auc2-fchip.active {
        transform: scale(1.02);
    }
    
    .auc2-page-hero-inner {
        gap: 1rem;
    }
    
    .auc2-page-icon {
        width: 56px;
        height: 56px;
        font-size: 1.8rem;
    }
    
    .auc2-page-text h2 {
        font-size: 1.4rem;
    }
    
    .auc2-hero-stats {
        gap: .5rem;
    }
    
    .auc2-hstat {
        padding: .5rem .8rem;
        min-width: 64px;
    }
    
    .auc2-hstat-num {
        font-size: 1.2rem;
    }
    
    .auc2-hstat-lbl {
        font-size: .58rem;
    }
}
</style>

<?php
$statusCounts = ['all'=>0,'upcoming'=>0,'ongoing'=>0,'completed'=>0,'cancelled'=>0];
$statusCounts['all'] = count($auctions);
foreach ($auctions as $a) {
    $s = $a['status'] ?? 'upcoming';
    if (isset($statusCounts[$s])) $statusCounts[$s]++;
}
?>

<!-- Auction Page Hero -->
<div class="auc2-page-hero">
    <div class="container">
        <div class="auc2-page-hero-inner">
            <div class="auc2-page-icon"><i class="fas fa-gavel"></i></div>
            <div class="auc2-page-text">
                <h2><?php echo isEnglish() ? 'Auction Notices' : 'लिलामी सूचना'; ?></h2>
                <p><?php echo isEnglish()
                    ? 'Browse active property auctions — view details, photos, and submit your bid.'
                    : 'सक्रिय लिलामी सम्पत्तिहरू हेर्नुहोस् — विवरण, तस्बिर हेर्नुहोस् र बोलपत्र पेश गर्नुहोस्।'; ?>
                </p>
            </div>
            <?php if (!empty($auctions)): ?>
            <div class="auc2-hero-stats">
                <div class="auc2-hstat">
                    <div class="auc2-hstat-num"><?php echo $statusCounts['all']; ?></div>
                    <div class="auc2-hstat-lbl"><?php echo isEnglish() ? 'Total' : 'जम्मा'; ?></div>
                </div>
                <?php if ($statusCounts['ongoing'] > 0): ?>
                <div class="auc2-hstat">
                    <div class="auc2-hstat-num green"><?php echo $statusCounts['ongoing']; ?></div>
                    <div class="auc2-hstat-lbl"><?php echo isEnglish() ? 'Live' : 'जारी'; ?></div>
                </div>
                <?php endif; ?>
                <?php if ($statusCounts['upcoming'] > 0): ?>
                <div class="auc2-hstat">
                    <div class="auc2-hstat-num yellow"><?php echo $statusCounts['upcoming']; ?></div>
                    <div class="auc2-hstat-lbl"><?php echo isEnglish() ? 'Upcoming' : 'आगामी'; ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filter Bar -->
<?php if (!empty($auctions)): ?>
<div class="auc2-filterbar">
    <div class="container">
        <span class="auc2-fbar-label"><i class="fas fa-filter"></i> <?php echo isEnglish() ? 'Filter:' : 'छान्नुहोस्:'; ?></span>
        <div class="auc2-fchips">
            <button class="auc2-fchip active" data-auc-filter="all" onclick="aucFilter(this,'all')">
                <i class="fas fa-th-large"></i> <?php echo isEnglish() ? 'All' : 'सबै'; ?>
                <span class="auc2-fcount"><?php echo $statusCounts['all']; ?></span>
            </button>
            <?php if ($statusCounts['ongoing'] > 0): ?>
            <button class="auc2-fchip" data-auc-filter="ongoing" onclick="aucFilter(this,'ongoing')">
                <i class="fas fa-circle" style="font-size:.55em;color:#198754;"></i> <?php echo isEnglish() ? 'Ongoing' : 'जारी'; ?>
                <span class="auc2-fcount"><?php echo $statusCounts['ongoing']; ?></span>
            </button>
            <?php endif; ?>
            <?php if ($statusCounts['upcoming'] > 0): ?>
            <button class="auc2-fchip" data-auc-filter="upcoming" onclick="aucFilter(this,'upcoming')">
                <i class="fas fa-clock"></i> <?php echo isEnglish() ? 'Upcoming' : 'आगामी'; ?>
                <span class="auc2-fcount"><?php echo $statusCounts['upcoming']; ?></span>
            </button>
            <?php endif; ?>
            <?php if ($statusCounts['completed'] > 0): ?>
            <button class="auc2-fchip" data-auc-filter="completed" onclick="aucFilter(this,'completed')">
                <i class="fas fa-check-circle"></i> <?php echo isEnglish() ? 'Completed' : 'सम्पन्न'; ?>
                <span class="auc2-fcount"><?php echo $statusCounts['completed']; ?></span>
            </button>
            <?php endif; ?>
            <?php if ($statusCounts['cancelled'] > 0): ?>
            <button class="auc2-fchip" data-auc-filter="cancelled" onclick="aucFilter(this,'cancelled')">
                <i class="fas fa-ban"></i> <?php echo isEnglish() ? 'Cancelled' : 'रद्द'; ?>
                <span class="auc2-fcount"><?php echo $statusCounts['cancelled']; ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="section-padding">
<div class="container">

    <?php if ($bidSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo isEnglish() ? 'Your bid has been submitted successfully!' : 'तपाईंको बोलपत्र सफलतापूर्वक पेश भयो!'; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($bidError): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $bidError; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (empty($auctions)): ?>
    <div class="text-center py-5">
        <i class="fas fa-gavel fa-4x text-muted mb-3 d-block"></i>
        <h4 class="text-muted"><?php echo isEnglish() ? 'No Auction Notices Available' : 'कुनै लिलामी सूचना उपलब्ध छैन'; ?></h4>
        <p class="text-muted"><?php echo isEnglish() ? 'Please check back later.' : 'कृपया पछि पुनः हेर्नुहोस्।'; ?></p>
    </div>
    <?php else: ?>

    <?php
    $statusLabels = [
        'upcoming'  => isEnglish() ? 'Upcoming'  : 'आगामी',
        'ongoing'   => isEnglish() ? 'Ongoing'   : 'जारी',
        'completed' => isEnglish() ? 'Completed' : 'सम्पन्न',
        'cancelled' => isEnglish() ? 'Cancelled' : 'रद्द',
    ];
    foreach ($auctions as $aIdx => $auction):
        /* ── Images ── */
        $auctionImages = [];
        if (!empty($auction['image']))  $auctionImages[] = $auction['image'];
        if (!empty($auction['images'])) {
            $extra = json_decode($auction['images'], true);
            if (is_array($extra)) $auctionImages = array_merge($auctionImages, $extra);
        }

        /* ── Area display ── */
        $aAreaParts = [];
        if (!empty($auction['area_bigha'])  && $auction['area_bigha']  > 0) $aAreaParts[] = number_format((float)$auction['area_bigha'],2,'.','').' '.(isEnglish()?'Bigha':'बिगाहा');
        if (!empty($auction['area_ropani']) && $auction['area_ropani'] > 0) $aAreaParts[] = (int)$auction['area_ropani'].' '.(isEnglish()?'Ropani':'रोपनी');
        if (!empty($auction['area_aana'])   && $auction['area_aana']   > 0) $aAreaParts[] = (int)$auction['area_aana'].' '.(isEnglish()?'Aana':'आना');
        if (!empty($auction['area_paisa'])  && $auction['area_paisa']  > 0) $aAreaParts[] = (int)$auction['area_paisa'].' '.(isEnglish()?'Paisa':'पैसा');
        $aAreaDisplay = !empty($aAreaParts) ? implode(' ', $aAreaParts) : htmlspecialchars($auction['area'] ?? '');

        $aId    = $auction['id'];
        $status = $auction['status'] ?? 'upcoming';
        $hasBid = ($status === 'upcoming' || $status === 'ongoing');
        $hasDoc = !empty($auction['document']);
        $hasMap = !empty($auction['google_map_embed']) || !empty($auction['google_map_link']);
        $hasPhotos = count($auctionImages) > 1;

        /* Auction date countdown */
        $auctionTs   = strtotime($auction['auction_date']);
        $nowTs       = time();
        $diffSec     = $auctionTs - $nowTs;
        $showCountdown = ($status === 'upcoming' && $diffSec > 0 && $diffSec < 30*24*3600);
    ?>

    <div class="auc2-wrap" id="auction-<?php echo $aId; ?>" data-auc-status="<?php echo htmlspecialchars($status); ?>">
    <div class="auc2-card">

        <!-- ── Hero ── -->
        <div class="auc2-hero">

            <!-- Gallery pane -->
            <div class="auc2-gallery-pane">
                <?php if (!empty($auctionImages)): ?>
                <img
                    src="<?php echo SITE_URL . $auctionImages[0]; ?>"
                    class="auc2-main-img"
                    id="auc2-main-<?php echo $aId; ?>"
                    alt="<?php echo htmlspecialchars(getLangField($auction,'title')); ?>"
                    loading="lazy"
                    onclick="auc2Lightbox(this.src)">

                <?php if (count($auctionImages) > 1): ?>
                <div class="auc2-thumbs" id="auc2-thumbs-<?php echo $aId; ?>">
                    <?php foreach ($auctionImages as $tIdx => $tImg): ?>
                    <img src="<?php echo SITE_URL . $tImg; ?>"
                         class="<?php echo $tIdx===0?'active':''; ?>"
                         loading="lazy"
                         alt="Photo <?php echo $tIdx+1; ?>"
                         onclick="auc2SetMain(<?php echo $aId; ?>, this, '<?php echo SITE_URL.$tImg; ?>')">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="auc2-no-img">
                    <i class="fas fa-image fa-3x mb-2"></i>
                    <span style="font-size:.85rem;"><?php echo isEnglish()?'No Photos':'तस्बिर उपलब्ध छैन'; ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Summary pane -->
            <div class="auc2-summary-pane">

                <div class="auc2-status-row">
                    <span class="auc2-badge-status s-<?php echo htmlspecialchars($status); ?>">
                        <?php if ($status==='ongoing'): ?><i class="fas fa-circle fa-xs me-1"></i><?php endif; ?>
                        <?php echo $statusLabels[$status] ?? $status; ?>
                    </span>
                    <span class="auc2-serial text-muted">
                        <i class="fas fa-hashtag fa-xs"></i> <?php echo isEnglish()?'Auction No.':'लिलामी नं.'; ?> <?php echo str_pad($aIdx+1, 3, '0', STR_PAD_LEFT); ?>
                    </span>
                </div>

                <h2 class="auc2-title"><?php echo htmlspecialchars(getLangField($auction,'title')); ?></h2>

                <!-- Info grid -->
                <div class="auc2-info-grid">
                    <?php if (!empty($auction['minimum_price'])): ?>
                    <div class="auc2-info-item price-item" style="grid-column:1/-1">
                        <div class="auc2-info-label"><i class="fas fa-tag"></i> <?php echo isEnglish()?'Minimum Price':'न्यूनतम मूल्य'; ?></div>
                        <div class="auc2-price-value">रु. <?php echo number_format((float)$auction['minimum_price']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($auction['auction_date'])): ?>
                    <div class="auc2-info-item highlight">
                        <div class="auc2-info-label"><i class="fas fa-calendar-alt"></i> <?php echo isEnglish()?'Auction Date':'लिलामी मिति'; ?></div>
                        <div class="auc2-info-value"><?php echo date('Y-m-d', strtotime($auction['auction_date'])); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($auction['auction_time'])): ?>
                    <div class="auc2-info-item">
                        <div class="auc2-info-label"><i class="fas fa-clock"></i> <?php echo isEnglish()?'Time':'समय'; ?></div>
                        <div class="auc2-info-value"><?php echo htmlspecialchars($auction['auction_time']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($auction['property_type'])): ?>
                    <div class="auc2-info-item">
                        <div class="auc2-info-label"><i class="fas fa-home"></i> <?php echo isEnglish()?'Type':'प्रकार'; ?></div>
                        <div class="auc2-info-value"><?php echo htmlspecialchars($auction['property_type']); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($aAreaDisplay)): ?>
                    <div class="auc2-info-item">
                        <div class="auc2-info-label"><i class="fas fa-ruler-combined"></i> <?php echo isEnglish()?'Area':'क्षेत्रफल'; ?></div>
                        <div class="auc2-info-value"><?php echo $aAreaDisplay; ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($auction['location'])): ?>
                    <div class="auc2-info-item" style="grid-column:1/-1">
                        <div class="auc2-info-label"><i class="fas fa-map-marker-alt"></i> <?php echo isEnglish()?'Location':'स्थान'; ?></div>
                        <div class="auc2-info-value">
                            <?php echo htmlspecialchars($auction['location']); ?>
                            <?php if (!empty($auction['google_map_link'])): ?>
                            <a href="<?php echo htmlspecialchars($auction['google_map_link']); ?>"
                               target="_blank" rel="noopener"
                               class="badge bg-danger text-decoration-none ms-2" style="font-size:.68rem;vertical-align:middle;">
                                <i class="fas fa-map me-1"></i><?php echo isEnglish()?'Map':'नक्सा'; ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($showCountdown): ?>
                <!-- Countdown -->
                <div class="mb-2" style="font-size:.78rem;color:#6c757d;margin-bottom:.3rem;">
                    <i class="fas fa-hourglass-half me-1 text-warning"></i>
                    <?php echo isEnglish()?'Time remaining:':'बाँकी समय:'; ?>
                </div>
                <div class="auc2-countdown"
                     data-auc2-countdown="<?php echo $auctionTs; ?>"
                     id="auc2-cd-<?php echo $aId; ?>">
                    <div class="auc2-cd-box"><div class="auc2-cd-num" id="auc2-cd-d-<?php echo $aId; ?>">--</div><div class="auc2-cd-lbl"><?php echo isEnglish()?'Days':'दिन'; ?></div></div>
                    <div class="auc2-cd-box"><div class="auc2-cd-num" id="auc2-cd-h-<?php echo $aId; ?>">--</div><div class="auc2-cd-lbl"><?php echo isEnglish()?'Hrs':'घन्टा'; ?></div></div>
                    <div class="auc2-cd-box"><div class="auc2-cd-num" id="auc2-cd-m-<?php echo $aId; ?>">--</div><div class="auc2-cd-lbl"><?php echo isEnglish()?'Min':'मिन'; ?></div></div>
                    <div class="auc2-cd-box"><div class="auc2-cd-num" id="auc2-cd-s-<?php echo $aId; ?>">--</div><div class="auc2-cd-lbl"><?php echo isEnglish()?'Sec':'सेक'; ?></div></div>
                </div>
                <?php endif; ?>

                <!-- CTA -->
                <div class="auc2-cta">
                    <?php if ($hasBid): ?>
                    <button class="auc2-bid-btn" data-bs-toggle="modal" data-bs-target="#bidModal<?php echo $aId; ?>">
                        <i class="fas fa-gavel"></i>
                        <span><?php echo isEnglish() ? 'Place Bid / Inquiry' : 'बोलपत्र / जिज्ञासा राख्नुहोस्'; ?></span>
                    </button>
                    <?php else: ?>
                    <div class="alert alert-secondary mb-0 py-2 text-center" style="border-radius:10px;font-size:.9rem;">
                        <i class="fas fa-lock me-2"></i>
                        <?php echo $status==='completed'
                            ? (isEnglish()?'This auction has been completed.':'यो लिलामी सम्पन्न भइसकेको छ।')
                            : (isEnglish()?'This auction was cancelled.':'यो लिलामी रद्द भएको छ।'); ?>
                    </div>
                    <?php endif; ?>
                </div>

            </div><!-- /.auc2-summary-pane -->
        </div><!-- /.auc2-hero -->

        <!-- ── Tabs ── -->
        <div class="auc2-tabs-wrap">
            <div class="auc2-nav" role="tablist">
                <button class="auc2-tab-btn active"
                        data-auc2-tab="overview-<?php echo $aId; ?>"
                        onclick="auc2Tab(this,'overview-<?php echo $aId; ?>')">
                    <i class="fas fa-info-circle"></i> <?php echo isEnglish()?'Overview':'सारांश'; ?>
                </button>
                <?php if ($hasPhotos): ?>
                <button class="auc2-tab-btn"
                        data-auc2-tab="photos-<?php echo $aId; ?>"
                        onclick="auc2Tab(this,'photos-<?php echo $aId; ?>')">
                    <i class="fas fa-images"></i> <?php echo isEnglish()?'Photos':'तस्बिरहरू'; ?>
                    <span class="badge bg-secondary" style="font-size:.65rem;"><?php echo count($auctionImages); ?></span>
                </button>
                <?php endif; ?>
                <?php if ($hasDoc): ?>
                <button class="auc2-tab-btn"
                        data-auc2-tab="docs-<?php echo $aId; ?>"
                        onclick="auc2Tab(this,'docs-<?php echo $aId; ?>')">
                    <i class="fas fa-file-alt"></i> <?php echo isEnglish()?'Document':'कागजपत्र'; ?>
                </button>
                <?php endif; ?>
                <?php if ($hasMap): ?>
                <button class="auc2-tab-btn"
                        data-auc2-tab="map-<?php echo $aId; ?>"
                        onclick="auc2Tab(this,'map-<?php echo $aId; ?>')">
                    <i class="fas fa-map-marked-alt"></i> <?php echo isEnglish()?'Map':'नक्सा'; ?>
                </button>
                <?php endif; ?>
            </div>

            <!-- Overview tab -->
            <div class="auc2-tab-pane active" id="overview-<?php echo $aId; ?>">
                <?php $desc = getLangField($auction,'description'); ?>
                <?php if (!empty($desc)): ?>
                <div class="auc2-desc-block">
                    <?php echo nl2br(htmlspecialchars($desc)); ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($auction['contact_person']) || !empty($auction['contact_phone'])): ?>
                <div class="auc2-contact-row">
                    <span style="font-size:.8rem;font-weight:700;color:#6c757d;text-transform:uppercase;letter-spacing:.4px;margin-right:.5rem;">
                        <i class="fas fa-headset me-1"></i><?php echo isEnglish()?'Contact':'सम्पर्क'; ?>:
                    </span>
                    <?php if (!empty($auction['contact_person'])): ?>
                    <span class="auc2-contact-item"><i class="fas fa-user"></i><?php echo htmlspecialchars($auction['contact_person']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($auction['contact_phone'])): ?>
                    <span class="auc2-contact-item">
                        <i class="fas fa-phone"></i>
                        <a href="tel:<?php echo htmlspecialchars($auction['contact_phone']); ?>">
                            <?php echo htmlspecialchars($auction['contact_phone']); ?>
                        </a>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (empty($desc) && empty($auction['contact_person'])): ?>
                <div class="auc2-empty-tab">
                    <i class="fas fa-info-circle"></i>
                    <?php echo isEnglish()?'No additional details available.':'थप विवरण उपलब्ध छैन।'; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Photos tab -->
            <?php if ($hasPhotos): ?>
            <div class="auc2-tab-pane" id="photos-<?php echo $aId; ?>">
                <div class="auc2-photo-grid">
                    <?php foreach ($auctionImages as $pImg): ?>
                    <img src="<?php echo SITE_URL.$pImg; ?>"
                         loading="lazy"
                         alt="<?php echo htmlspecialchars(getLangField($auction,'title')); ?>"
                         onclick="auc2Lightbox(this.src)">
                    <?php endforeach; ?>
                </div>
                <p class="text-muted mt-3 mb-0" style="font-size:.8rem;">
                    <i class="fas fa-hand-pointer me-1"></i><?php echo isEnglish()?'Click any photo to enlarge':'ठूलो हेर्न तस्बिरमा क्लिक गर्नुहोस्'; ?>
                </p>
            </div>
            <?php endif; ?>

            <!-- Documents tab -->
            <?php if ($hasDoc): ?>
            <div class="auc2-tab-pane" id="docs-<?php echo $aId; ?>">
                <div class="auc2-doc-list">
                    <div class="auc2-doc-item">
                        <div class="auc2-doc-icon"><i class="fas fa-file-pdf"></i></div>
                        <div class="auc2-doc-info">
                            <div class="auc2-doc-name"><?php echo isEnglish()?'Official Auction Notice':'आधिकारिक लिलामी सूचना'; ?></div>
                            <div class="auc2-doc-desc"><?php echo isEnglish()?'Click to download or view the official document.':'सरकारी कागजात डाउनलोड गर्न वा हेर्न क्लिक गर्नुहोस्।'; ?></div>
                        </div>
                        <a href="<?php echo SITE_URL.htmlspecialchars($auction['document']); ?>"
                           target="_blank" rel="noopener"
                           class="btn btn-danger btn-sm" style="white-space:nowrap;">
                            <i class="fas fa-download me-1"></i><?php echo isEnglish()?'Download':'डाउनलोड'; ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Map tab -->
            <?php if ($hasMap): ?>
            <div class="auc2-tab-pane" id="map-<?php echo $aId; ?>">
                <?php if (!empty($auction['google_map_embed'])): ?>
                <div class="auc2-map-container">
                    <?php echo $auction['google_map_embed']; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($auction['google_map_link'])): ?>
                <div class="auc2-map-link-wrap">
                    <a href="<?php echo htmlspecialchars($auction['google_map_link']); ?>"
                       target="_blank" rel="noopener"
                       class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i>
                        <?php echo isEnglish()?'Open in Google Maps':'Google Maps मा खोल्नुहोस्'; ?>
                    </a>
                </div>
                <?php endif; ?>
                <?php if (empty($auction['google_map_embed']) && empty($auction['google_map_link'])): ?>
                <div class="auc2-empty-tab">
                    <i class="fas fa-map-marked-alt"></i>
                    <?php echo isEnglish()?'No map available.':'नक्सा उपलब्ध छैन।'; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div><!-- /.auc2-tabs-wrap -->
    </div><!-- /.auc2-card -->
    </div><!-- /.auc2-wrap -->

    <?php if ($hasBid): ?>
    <!-- Bid Modal -->
    <div class="modal fade" id="bidModal<?php echo $aId; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header auc2-bid-modal-head">
                    <h5 class="modal-title auc2-bid-modal-title">
                        <i class="fas fa-gavel me-2"></i>
                        <?php echo isEnglish() ? 'Submit Bid / Inquiry' : 'बोलपत्र / जिज्ञासा पेश'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" novalidate class="bid-modal-form auc2-bid-form needs-validation">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="bid_form_token" value="<?php echo bin2hex(random_bytes(12)); ?>">
                    <input type="hidden" name="auction_id" value="<?php echo $aId; ?>">
                    <input type="hidden" name="submit_bid" value="1">
                    <div class="modal-body">
                        <div class="auc2-bid-note py-2 px-3 mb-3 small">
                            <i class="fas fa-gavel me-1"></i>
                            <?php echo isEnglish() ? 'Minimum bid amount:' : 'न्यूनतम बोलपत्र रकम:'; ?>
                            <strong class="auc2-bid-min-amt"> रु. <?php echo number_format($auction['minimum_price']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <label class="form-label auc2-bid-label"><?php echo isEnglish()?'Full Name':'पूरा नाम'; ?> <span class="auc2-req">*</span></label>
                            <input type="text" name="bidder_name" class="form-control auc2-bid-input"
                                   placeholder="<?php echo isEnglish()?'Enter your full name':'आफ्नो पूरा नाम लेख्नुहोस्'; ?>"
                                   required minlength="2" maxlength="120">
                        </div>
                        <div class="mb-3">
                            <label class="form-label auc2-bid-label"><?php echo isEnglish()?'Mobile Number':'मोबाइल नम्बर'; ?> <span class="auc2-req">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text auc2-bid-addon"><i class="fas fa-phone"></i></span>
                                <input type="tel" name="bidder_phone" class="form-control auc2-bid-input"
                                       placeholder="98XXXXXXXX" pattern="[9][0-9]{9}"
                                       maxlength="10" minlength="10" inputmode="numeric" required
                                       title="<?php echo isEnglish()?'10-digit Nepal mobile':'९ बाट शुरु हुने १० अंकको नम्बर'; ?>">
                            </div>
                            <div class="auc2-bid-help"><i class="fas fa-info-circle"></i> <?php echo isEnglish()?'10-digit Nepal mobile starting with 9':'९ बाट शुरु हुने १० अंकको नम्बर'; ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label auc2-bid-label"><?php echo isEnglish()?'Email':'इमेल'; ?></label>
                            <div class="input-group">
                                <span class="input-group-text auc2-bid-addon"><i class="fas fa-envelope"></i></span>
                                <input type="email" name="bidder_email" class="form-control auc2-bid-input" placeholder="name@email.com" maxlength="150">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label auc2-bid-label"><?php echo isEnglish()?'Address':'ठेगाना'; ?></label>
                            <input type="text" name="bidder_address" class="form-control auc2-bid-input"
                                   placeholder="<?php echo isEnglish()?'District, Municipality':'जिल्ला, गाउँपालिका/नगरपालिका'; ?>" maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label class="form-label auc2-bid-label"><?php echo isEnglish()?'Bid Amount (Rs.)':'बोलपत्र रकम (रु.)'; ?> <span class="auc2-req">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text auc2-bid-addon">रु.</span>
                                <input type="number" name="bid_amount" class="form-control auc2-bid-input"
                                       min="<?php echo $auction['minimum_price']; ?>"
                                       step="1" inputmode="numeric"
                                       placeholder="<?php echo number_format($auction['minimum_price']); ?>" required>
                            </div>
                            <div class="auc2-bid-help-warn"><i class="fas fa-exclamation-circle"></i> <?php echo isEnglish()?'Minimum bid: Rs.':'न्यूनतम रकम: रु.'; ?> <?php echo number_format($auction['minimum_price']); ?></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label auc2-bid-label"><?php echo isEnglish()?'Message / Query':'सन्देश / जिज्ञासा'; ?></label>
                            <textarea name="message" class="form-control auc2-bid-textarea" rows="3" maxlength="500"
                                      placeholder="<?php echo isEnglish()?'Optional message...':'थप जानकारी वा जिज्ञासा...'; ?>"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer auc2-bid-footer">
                        <button type="button" class="btn auc2-bid-cancel" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i><?php echo isEnglish()?'Cancel':'रद्द'; ?>
                        </button>
                        <button type="submit" class="btn auc2-bid-submit bid-submit-btn">
                            <i class="fas fa-gavel me-1"></i><?php echo isEnglish()?'Submit Bid':'बोलपत्र पेश गर्नुहोस्'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endforeach; ?>

    <!-- No-filter results state -->
    <div class="auc2-no-filter" id="aucNoResults">
        <i class="fas fa-filter fa-2x text-muted mb-3 d-block"></i>
        <h5><?php echo isEnglish() ? 'No auctions match this filter.' : 'यो filter मा कुनै लिलामी भेटिएन।'; ?></h5>
        <button class="btn btn-outline-secondary btn-sm mt-2" onclick="aucResetFilter()">
            <i class="fas fa-redo me-1"></i><?php echo isEnglish() ? 'Show All' : 'सबै देखाउनुहोस्'; ?>
        </button>
    </div>

    <?php endif; /* end !empty($auctions) */ ?>

</div><!-- /.container -->
</section>

<script>
/* ─── Auction Status Filter ─── */
function aucFilter(btn, filter) {
    document.querySelectorAll('.auc2-fchip').forEach(function(c) {
        c.classList.remove('active','green','yellow','grey');
    });
    btn.classList.add('active');
    if (filter === 'ongoing')   btn.classList.add('green');
    if (filter === 'upcoming')  btn.classList.add('yellow');
    if (filter === 'completed' || filter === 'cancelled') btn.classList.add('grey');

    var wraps   = document.querySelectorAll('.auc2-wrap[data-auc-status]');
    var visible = 0;
    wraps.forEach(function(w) {
        if (filter === 'all' || w.getAttribute('data-auc-status') === filter) {
            w.style.display = ''; visible++;
        } else {
            w.style.display = 'none';
        }
    });
    var noRes = document.getElementById('aucNoResults');
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}
function aucResetFilter() {
    var allBtn = document.querySelector('.auc2-fchip[data-auc-filter="all"]');
    if (allBtn) aucFilter(allBtn, 'all');
}
</script>

<!-- Lightbox Overlay -->
<div id="auc2-lightbox" onclick="this.classList.remove('open')">
    <button id="auc2-lightbox-close" onclick="document.getElementById('auc2-lightbox').classList.remove('open')">
        <i class="fas fa-times"></i>
    </button>
    <img id="auc2-lightbox-img" src="" alt="Photo">
</div>

<script>
/* ─── Auction v2 JS ─── */

/* Tab switcher */
function auc2Tab(btn, paneId) {
    var card = btn.closest('.auc2-card');
    card.querySelectorAll('.auc2-tab-btn').forEach(function(b){ b.classList.remove('active'); });
    card.querySelectorAll('.auc2-tab-pane').forEach(function(p){ p.classList.remove('active'); });
    btn.classList.add('active');
    var pane = document.getElementById(paneId);
    if (pane) pane.classList.add('active');
}

/* Thumbnail → main image switch */
function auc2SetMain(aId, thumb, src) {
    var mainImg = document.getElementById('auc2-main-' + aId);
    if (mainImg) mainImg.src = src;
    var strip = document.getElementById('auc2-thumbs-' + aId);
    if (strip) strip.querySelectorAll('img').forEach(function(t){ t.classList.remove('active'); });
    thumb.classList.add('active');
}

/* Lightbox */
function auc2Lightbox(src) {
    document.getElementById('auc2-lightbox-img').src = src;
    document.getElementById('auc2-lightbox').classList.add('open');
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') document.getElementById('auc2-lightbox').classList.remove('open'); });

/* Countdown timers */
function auc2UpdateCountdowns() {
    document.querySelectorAll('[data-auc2-countdown]').forEach(function(el) {
        var ts = parseInt(el.getAttribute('data-auc2-countdown'), 10) * 1000;
        var diff = ts - Date.now();
        var aId = el.id.replace('auc2-cd-', '');
        if (diff <= 0) {
            ['d','h','m','s'].forEach(function(u){
                var e = document.getElementById('auc2-cd-'+u+'-'+aId);
                if(e) e.textContent='00';
            });
            return;
        }
        var d = Math.floor(diff/86400000);
        var h = Math.floor((diff%86400000)/3600000);
        var m = Math.floor((diff%3600000)/60000);
        var s = Math.floor((diff%60000)/1000);
        function pad(n){ return String(n).padStart(2,'0'); }
        var de = document.getElementById('auc2-cd-d-'+aId);
        var he = document.getElementById('auc2-cd-h-'+aId);
        var me = document.getElementById('auc2-cd-m-'+aId);
        var se = document.getElementById('auc2-cd-s-'+aId);
        if(de) de.textContent=pad(d);
        if(he) he.textContent=pad(h);
        if(me) me.textContent=pad(m);
        if(se) se.textContent=pad(s);
    });
}
auc2UpdateCountdowns();
setInterval(auc2UpdateCountdowns, 1000);

/* Bid form validation */
document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('.bid-modal-form').forEach(function(form) {
        var phoneInput  = form.querySelector('input[name="bidder_phone"]');
        var emailInput  = form.querySelector('input[name="bidder_email"]');
        var bidAmtInput = form.querySelector('input[name="bid_amount"]');

        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g,'').slice(0,10);
            });
            phoneInput.addEventListener('blur', function() {
                var v = this.value.trim();
                this.classList.toggle('is-invalid', v.length>0 && (v.length!==10||v[0]!=='9'));
                if (v.length===10 && v[0]==='9') this.classList.add('is-valid');
            });
        }

        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                var v = this.value.trim();
                if (v && !v.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                    if (v) this.classList.add('is-valid');
                }
            });
        }

        form.addEventListener('submit', function(e) {
            var ok = true;
            if (phoneInput && !phoneInput.value.trim().match(/^[9][0-9]{9}$/)) {
                phoneInput.classList.add('is-invalid'); ok=false;
            }
            if (emailInput && emailInput.value.trim() && !emailInput.value.trim().match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                emailInput.classList.add('is-invalid'); ok=false;
            }
            if (bidAmtInput) {
                var minV=parseFloat(bidAmtInput.min)||0, bidV=parseFloat(bidAmtInput.value)||0;
                if (bidV<minV) { bidAmtInput.classList.add('is-invalid'); ok=false; }
            }
            if (!ok) { e.preventDefault(); return false; }
            var btn=form.querySelector('.bid-submit-btn');
            if(btn){ btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i><?php echo isEnglish()?"Submitting...":"पेश गर्दै..."; ?>'; }
        });
    });

    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            var form=modal.querySelector('form');
            if(form){
                form.reset();
                form.querySelectorAll('.is-valid,.is-invalid').forEach(function(el){ el.classList.remove('is-valid','is-invalid'); });
                var btn=form.querySelector('.bid-submit-btn');
                if(btn){ btn.disabled=false; btn.innerHTML='<i class="fas fa-gavel me-1"></i><?php echo isEnglish()?"Submit Bid":"बोलपत्र पेश गर्नुहोस्"; ?>'; }
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
