<?php
/**
 * Member chrome footer — closes container/body/html,
 * emits shared bell-toggle JS and mobile bottom-nav.
 */
?>
</div><!-- /.mem-container -->

<!-- Mobile bottom navigation (hidden on desktop ≥900px) -->
<nav class="mp-bottom-nav">
  <a href="<?php echo SITE_URL; ?>member/"><i class="fas fa-house"></i><span>गृह</span></a>
  <a href="<?php echo SITE_URL; ?>member/welfare.php"><i class="fas fa-heart-pulse"></i><span>कल्याण</span></a>
  <a href="<?php echo SITE_URL; ?>member/scan.php" class="mp-nav-scan" aria-label="कार्यक्रम QR स्क्यान"><i class="fas fa-qrcode"></i><span>स्क्यान</span></a>
  <a href="<?php echo SITE_URL; ?>member/attend.php"><i class="fas fa-calendar-check"></i><span>उपस्थिति</span></a>
  <a href="<?php echo SITE_URL; ?>member/profile.php"><i class="fas fa-user-circle"></i><span>प्रोफाइल</span></a>
</nav>

<style>
.mp-bottom-nav {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: #fff; border-top: 1px solid #e5e7eb;
    display: flex; justify-content: space-around;
    padding: 8px 0 calc(8px + env(safe-area-inset-bottom, 0px));
    box-shadow: 0 -2px 8px rgba(0,0,0,.06);
    z-index: 40;
}
.mp-bottom-nav a {
    flex: 1; text-decoration: none; color: #6b7280;
    display: flex; flex-direction: column; align-items: center; gap: 2px;
    font-size: 10px; font-weight: 600;
    transition: color .15s;
    min-width: 0;
}
.mp-bottom-nav a i { font-size: 17px; }
.mp-bottom-nav a.mp-nav-scan {
    color: var(--primary-color, #1a8754);
    position: relative;
}
.mp-bottom-nav a.mp-nav-scan i {
    width: 46px; height: 46px; margin-top: -20px; margin-bottom: 1px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color,#1a8754), #15803d);
    color: #fff !important;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    box-shadow: 0 4px 14px rgba(26,135,84,0.38);
}
.mp-bottom-nav a.mp-nav-scan span { font-size: 10px; font-weight: 800; }
.mp-bottom-nav a:hover,
.mp-bottom-nav a.active { color: var(--primary-color, #1a8754); }
.mp-bottom-nav a.mp-nav-scan.active i { box-shadow: 0 4px 16px rgba(26,135,84,0.5); }
body { padding-bottom: 76px; }
@media (min-width: 900px) { .mp-bottom-nav { display: none; } body { padding-bottom: 0; } }
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
