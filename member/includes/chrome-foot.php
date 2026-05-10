<?php
/**
 * Member chrome footer — closes container/body/html,
 * emits shared bell-toggle JS and mobile bottom-nav.
 */
?>
</div><!-- /.mem-container -->
<?php
$_footLang = function_exists('getCurrentLang') ? getCurrentLang() : 'np';
$_footIsEn = ($_footLang === 'en');
$_footT = static function (string $np, string $en) use ($_footIsEn): string {
    return $_footIsEn ? $en : $np;
};
?>

<?php
$_footLangQuery = $_GET ?? [];
$_footLangQuery['lang'] = $_footIsEn ? 'np' : 'en';
$_footLangToggleUrl = strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($_footLangQuery);
$_footLangBadge = $_footIsEn ? 'नेपाली' : 'EN';
?>
<!-- Mobile bottom navigation (hidden on desktop ≥900px) -->
<nav class="mp-bottom-nav">
  <a href="<?php echo SITE_URL; ?>member/"><i class="fas fa-house"></i><span><?php echo $_footT('गृह', 'Home'); ?></span></a>
  <a href="<?php echo SITE_URL; ?>member/attend.php"><i class="fas fa-calendar-check"></i><span><?php echo $_footT('उपस्थिति', 'Attend'); ?></span></a>
  <a href="<?php echo SITE_URL; ?>member/scan.php" class="mp-nav-scan" aria-label="<?php echo $_footT('QR स्क्यान', 'Scan QR'); ?>"><i class="fas fa-qrcode"></i><span><?php echo $_footT('स्क्यान', 'Scan'); ?></span></a>
  <a href="<?php echo SITE_URL; ?>member/id-card.php"><i class="fas fa-id-card"></i><span><?php echo $_footT('आईडी', 'ID Card'); ?></span></a>
  <a href="<?php echo SITE_URL; ?>member/profile.php"><i class="fas fa-user-circle"></i><span><?php echo $_footT('प्रोफाइल', 'Profile'); ?></span></a>
</nav>

<style>
.mp-bottom-nav {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: linear-gradient(135deg, #ffffff, #f8f9fa); border-top: 2px solid var(--primary-color);
    display: flex; justify-content: space-around;
    padding: 8px 0 calc(8px + env(safe-area-inset-bottom, 0px));
    box-shadow: 0 -4px 16px rgba(var(--primary-rgb), .15);
    z-index: 40;
    backdrop-filter: blur(10px);
}
.mp-bottom-nav a {
    flex: 1; text-decoration: none; color: var(--text-muted);
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    font-size: 11px; font-weight: 600;
    transition: all .3s cubic-bezier(.4,0,.2,1);
    min-width: 0;
    padding: 8px 4px;
    border-radius: 12px;
    position: relative;
    overflow: hidden;
}
.mp-bottom-nav a::before {
    content: '';
    position: absolute; top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--primary-color) 15%, white), transparent);
    transition: left .5s;
}
.mp-bottom-nav a:hover::before {
    left: 100%;
}
.mp-bottom-nav a i { 
    font-size: 18px; 
    transition: all .3s; 
    position: relative; z-index: 1;
}
.mp-bottom-nav a:hover i {
    transform: scale(1.1) rotate(5deg);
}
.mp-bottom-nav a:hover,
.mp-bottom-nav a.active { 
    color: var(--primary-color); 
    background: color-mix(in srgb, var(--primary-color) 10%, white);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), .2);
}
body { padding-bottom: 80px; }
@media (min-width: 900px) { .mp-bottom-nav { display: none; } body { padding-bottom: 0; } }

/* Language toggle item — subtle distinction */
.mp-nav-lang {
    border-left: 1px solid color-mix(in srgb, var(--primary-color) 20%, #e5e7eb);
    opacity: 0.85;
}
.mp-nav-lang i { color: var(--primary-color); }

/* Mobile optimizations */
@media (max-width: 480px) {
    .mp-bottom-nav a {
        font-size: 10px;
        padding: 6px 2px;
        gap: 3px;
    }
    .mp-bottom-nav a i {
        font-size: 16px;
    }
    body { padding-bottom: 70px; }
}
</style>

<script src="<?php echo SITE_URL; ?>assets/js/v9-mobile-fix.js?v=9.7" defer></script>
<script>
/* Bell dropdown toggle (works on any member page that includes chrome.php) */
(function(){
    var btn = document.getElementById('bellBtn');
    var dd  = document.getElementById('bellDropdown');
    if (!btn || !dd) return;
    btn.addEventListener('click', function(e){
        e.stopPropagation();
        dd.classList.toggle('open');
    });
    document.addEventListener('click', function(e){
        if (!dd.contains(e.target) && e.target !== btn) dd.classList.remove('open');
    });
})();

/* Highlight active bottom-nav link */
(function(){
    var path = window.location.pathname;
    document.querySelectorAll('.mp-bottom-nav a').forEach(function(a){
        var href = a.getAttribute('href') || '';
        var page = href.split('/').pop().split('?')[0];
        var cur  = path.split('/').pop().split('?')[0];
        if (page && cur && (page === cur || (page === '' && (cur === '' || cur === 'index.php')))) {
            a.classList.add('active');
        }
    });
})();
</script>

</body>
</html>
