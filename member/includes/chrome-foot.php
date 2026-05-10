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
<!-- Mobile bottom navigation — styled via member-portal-v2.css -->
<nav class="mp-bottom-nav">
  <a href="<?php echo SITE_URL; ?>member/"><i class="fas fa-house"></i><span><?php echo $_footT('गृह', 'Home'); ?></span></a>
  <a href="<?php echo SITE_URL; ?>member/attend.php"><i class="fas fa-calendar-check"></i><span><?php echo $_footT('उपस्थिति', 'Attend'); ?></span></a>
  <a href="<?php echo SITE_URL; ?>member/scan.php" class="mp-nav-scan" aria-label="<?php echo $_footT('QR स्क्यान', 'Scan QR'); ?>"><i class="fas fa-qrcode"></i><span><?php echo $_footT('स्क्यान', 'Scan'); ?></span></a>
  <a href="<?php echo SITE_URL; ?>member/id-card.php"><i class="fas fa-id-card"></i><span><?php echo $_footT('आईडी', 'ID Card'); ?></span></a>
  <a href="<?php echo SITE_URL; ?>member/profile.php"><i class="fas fa-user-circle"></i><span><?php echo $_footT('प्रोफाइल', 'Profile'); ?></span></a>
</nav>

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
