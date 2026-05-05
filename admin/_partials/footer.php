</div><!-- /.admin-page -->

<nav class="admin-bottom-nav" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid var(--border);display:flex;justify-content:space-around;padding:8px 0;z-index:40;box-shadow:0 -2px 8px rgba(0,0,0,.06);">
  <a href="/admin/" class="admin-nav-item"><i class="fas fa-gauge"></i><span>ड्यासबोर्ड</span></a>
  <a href="/admin/notices/" class="admin-nav-item"><i class="fas fa-bullhorn"></i><span>सूचना</span></a>
  <a href="/admin/members/" class="admin-nav-item"><i class="fas fa-users"></i><span>सदस्य</span></a>
  <a href="/admin/settings.php" class="admin-nav-item"><i class="fas fa-cog"></i><span>सेटिङ</span></a>
  <a href="/admin/logout.php" class="admin-nav-item"><i class="fas fa-right-from-bracket"></i><span>लगआउट</span></a>
</nav>

<style>
  .admin-bottom-nav .admin-nav-item {
    display:flex;flex-direction:column;align-items:center;gap:2px;
    color:var(--text-muted);text-decoration:none;font-size:11px;font-weight:600;
    padding:4px 8px;border-radius:var(--radius-sm);transition:color .15s;
  }
  .admin-bottom-nav .admin-nav-item:hover { color:var(--brand-primary); }
  .admin-bottom-nav .admin-nav-item i { font-size:18px; }
  body.admin-shell { padding-bottom: 70px; }
  @media (min-width: 900px) { .admin-bottom-nav { display:none; } body.admin-shell { padding-bottom:0; } }
</style>
</body></html>
