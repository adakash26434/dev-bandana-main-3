/* ════════════════════════════════════════════════════════════
   v10.6 — Mobile UX helpers (Issue #17)
   ────────────────────────────────────────────────────────────
   New file. Include AFTER jQuery/Bootstrap in includes/footer.php
   and admin/_partials/footer.php just before </body>:

       <script src="<?= url('assets/js/v10.6-mobile-helpers.js') ?>" defer></script>
   ════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  // ── 1. Auto-wrap any unwrapped <table> in v9-table-scroll for safety
  function wrapTables() {
    document.querySelectorAll('main table, .container table, article table').forEach(function (t) {
      if (t.classList.contains('no-mobile-scroll')) return;
      if (t.parentElement && t.parentElement.classList.contains('v9-table-scroll')) return;
      var w = document.createElement('div');
      w.className = 'v9-table-scroll';
      t.parentNode.insertBefore(w, t);
      w.appendChild(t);
    });
  }

  // ── 2. Close mobile dropdowns on outside tap
  function bindOutsideClose() {
    document.addEventListener('click', function (e) {
      // Login dropdown (public header)
      var ld = document.querySelector('.pfl-login-drop-wrap.open');
      if (ld && !ld.contains(e.target)) ld.classList.remove('open');

      // Sidebar drawer (member/admin)
      var sb = document.querySelector('.sidebar.open, .mem-sidebar.open');
      if (sb && !sb.contains(e.target)
          && !e.target.closest('.sidebar-toggle, .mobile-menu-toggle')) {
        sb.classList.remove('open');
        document.querySelector('.sidebar-backdrop')?.classList.remove('show');
      }
    }, { passive: true });
  }

  // ── 3. Add backdrop element if missing (for sidebar drawers)
  function ensureBackdrop() {
    if (document.querySelector('.sidebar-backdrop')) return;
    var b = document.createElement('div');
    b.className = 'sidebar-backdrop';
    document.body.appendChild(b);
    b.addEventListener('click', function () {
      document.querySelectorAll('.sidebar.open, .mem-sidebar.open').forEach(function (s) {
        s.classList.remove('open');
      });
      b.classList.remove('show');
    });
  }

  // ── 4. Toggle backdrop when sidebar opens
  function watchSidebarToggle() {
    document.addEventListener('click', function (e) {
      var t = e.target.closest('.sidebar-toggle, .mobile-menu-toggle');
      if (!t) return;
      setTimeout(function () {
        var open = !!document.querySelector('.sidebar.open, .mem-sidebar.open');
        document.querySelector('.sidebar-backdrop')?.classList.toggle('show', open);
      }, 30);
    });
  }

  // ── 5. Prevent body scroll when modal is open on iOS
  function lockBodyOnModal() {
    document.addEventListener('shown.bs.modal', function () {
      document.body.style.overflow = 'hidden';
      document.body.style.position = 'fixed';
      document.body.style.width = '100%';
    });
    document.addEventListener('hidden.bs.modal', function () {
      document.body.style.overflow = '';
      document.body.style.position = '';
      document.body.style.width = '';
    });
  }

  // ── 6. Smooth-scroll for in-page anchors (e.g., satisfaction widget questions)
  function smoothAnchors() {
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        var id = a.getAttribute('href');
        if (id.length < 2) return;
        var t = document.querySelector(id);
        if (!t) return;
        e.preventDefault();
        t.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  // ── Init when DOM ready
  function init() {
    try { wrapTables(); } catch (e) { console.warn('[v10.6] wrapTables', e); }
    try { ensureBackdrop(); } catch (e) {}
    try { bindOutsideClose(); } catch (e) {}
    try { watchSidebarToggle(); } catch (e) {}
    try { lockBodyOnModal(); } catch (e) {}
    try { smoothAnchors(); } catch (e) {}
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
