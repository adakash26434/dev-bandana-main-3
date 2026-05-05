/* ============================================================
 * v9.6 — Mobile menu (clean, no chevron-button hack)
 * Public + Admin drawers. Plain delegation, no z-index wars.
 * Submenus toggle on parent-link tap (mobile only) when href === '#' or 'javascript:'
 * Otherwise the link navigates.
 * ============================================================ */
(function () {
  'use strict';

  /* ── Wrap wide tables for horizontal scroll ── */
  function wrapTables() {
    document.querySelectorAll('table').forEach(function (table) {
      if (table.closest('.v9-table-scroll, .dataTables_wrapper, .cke, .ndp-container, .id-card-wrap')) return;
      var firstRow = table.querySelector('tr');
      if (!firstRow) return;
      var cols = firstRow.querySelectorAll('th, td').length;
      if (cols < 3) return;
      var wrap = document.createElement('div');
      wrap.className = 'v9-table-scroll';
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    });
  }

  /* ── Public site drawer ── */
  function attachPublicDrawer(toggleId, navId, closeId) {
    var toggle = document.getElementById(toggleId);
    var nav    = document.getElementById(navId);
    if (!toggle || !nav) return;
    if (toggle.dataset.v96Bound === '1') return;

    /* Match admin behavior: replace nodes to remove any pre-existing listeners */
    var freshToggle = toggle.cloneNode(true);
    toggle.parentNode.replaceChild(freshToggle, toggle);
    toggle = freshToggle;
    toggle.dataset.v96Bound = '1';

    var closeBtn = document.getElementById(closeId);
    if (closeBtn && closeBtn.parentNode) {
      var freshClose = closeBtn.cloneNode(true);
      closeBtn.parentNode.replaceChild(freshClose, closeBtn);
      closeBtn = freshClose;
    }
    var overlays = ['menuOverlay', 'pflMobileBackdrop']
      .map(function (id) { return document.getElementById(id); })
      .filter(Boolean);

    function openNav() {
      nav.classList.add('nav-open', 'open', 'active');
      overlays.forEach(function (el) { el.classList.add('active'); });
      document.body.classList.add('mobile-nav-open');
      document.body.style.overflow = 'hidden';
      toggle.setAttribute('aria-expanded', 'true');
      nav.setAttribute('aria-hidden', 'false');
    }
    function closeNav() {
      nav.classList.remove('nav-open', 'open', 'active');
      overlays.forEach(function (el) { el.classList.remove('active'); });
      document.body.classList.remove('mobile-nav-open');
      document.body.style.overflow = '';
      toggle.setAttribute('aria-expanded', 'false');
      nav.setAttribute('aria-hidden', 'true');
      nav.querySelectorAll('.has-dropdown.open, .has-sub.open').forEach(function (el) {
        el.classList.remove('open');
      });
    }

    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (nav.classList.contains('nav-open') || nav.classList.contains('open') || nav.classList.contains('active')) {
        closeNav();
      } else {
        openNav();
      }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        closeNav();
      });
    }
    overlays.forEach(function (el) { el.addEventListener('click', closeNav); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeNav(); });

    /* ── Single delegated click handler on the nav ── */
    nav.addEventListener('click', function (e) {
      if (window.innerWidth >= 992) return;
      var link = e.target.closest('a');
      if (!link || !nav.contains(link)) return;
      var li      = link.parentElement;
      var hasSub  = li && (li.classList.contains('has-dropdown') || li.classList.contains('has-sub'));
      var href    = (link.getAttribute('href') || '').trim();
      var isToggle = !href || href === '#' || href.indexOf('javascript:') === 0;

      if (hasSub && isToggle) {
        /* Pure dropdown parent (no destination) → toggle submenu */
        e.preventDefault();
        e.stopPropagation();
        li.classList.toggle('open');
        return;
      }
      if (hasSub && !isToggle) {
        /* Parent with real destination — first tap opens submenu, second tap navigates */
        if (!li.classList.contains('open') && !link.dataset.tapped) {
          e.preventDefault();
          e.stopPropagation();
          li.classList.add('open');
          link.dataset.tapped = '1';
          setTimeout(function () { delete link.dataset.tapped; }, 4000);
          return;
        }
        /* Second tap → let it navigate, close drawer */
        setTimeout(closeNav, 50);
        return;
      }
      /* Leaf link → navigate, close drawer */
      setTimeout(closeNav, 50);
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 992) closeNav();
    });
  }

  /* ── Admin sidebar drawer ── */
  function setupAdminSidebar() {
    var sidebar = document.getElementById('sidebar');
    var toggle  = document.getElementById('sidebarToggle');
    if (!sidebar || !toggle) return;
    if (toggle.dataset.v96Bound === '1') return;

    /* Replace toggle node to drop ANY pre-existing click listeners (e.g. admin.js) */
    var fresh = toggle.cloneNode(true);
    toggle.parentNode.replaceChild(fresh, toggle);
    toggle = fresh;
    toggle.dataset.v96Bound = '1';

    var overlay = document.querySelector('.sidebar-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'sidebar-overlay';
      document.body.appendChild(overlay);
    }
    var closeBtn = document.getElementById('sidebarClose');

    function openSidebar() {
      sidebar.classList.add('active');
      overlay.classList.add('active');
      document.body.classList.add('admin-nav-open');
    }
    function closeSidebar() {
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
      document.body.classList.remove('admin-nav-open');
    }

    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      if (sidebar.classList.contains('active')) closeSidebar();
      else openSidebar();
    }, true); /* capture phase — fire before any bubbling listener */

    if (closeBtn) closeBtn.addEventListener('click', function(e){ e.preventDefault(); closeSidebar(); });
    overlay.addEventListener('click', closeSidebar);
    window.addEventListener('resize', function () {
      if (window.innerWidth > 991) closeSidebar();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeSidebar();
    });

    /* Sidebar internal collapsible groups */
    sidebar.addEventListener('click', function (e) {
      var trigger = e.target.closest('.has-submenu > a, [data-toggle-submenu]');
      if (!trigger) return;
      var parent = trigger.parentElement;
      if (!parent || (!parent.classList.contains('has-submenu') && !trigger.dataset.toggleSubmenu)) return;
      var href = (trigger.getAttribute('href') || '').trim();
      if (!href || href === '#' || href.indexOf('javascript:') === 0) {
        e.preventDefault();
        parent.classList.toggle('open');
      }
    });
  }

  function init() {
    wrapTables();
    if (document.body.classList.contains('header-v2')) {
      attachPublicDrawer('mobileMenuToggle2', 'mainNavV2', 'closeMenuV2');
    } else {
      attachPublicDrawer('mobileMenuToggle', 'mainNav', 'closeMenu');
    }
    setupAdminSidebar();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
