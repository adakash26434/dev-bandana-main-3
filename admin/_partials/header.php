<?php
/**
 * Unified admin header — v10.0
 * Include at the TOP of every admin page (after _bootstrap.php).
 *
 * Usage:
 *   $page_title = 'सदस्य व्यवस्थापन';
 *   $page_icon  = 'fa-users';
 *   include __DIR__ . '/_partials/header.php';
 */
$page_title = $page_title ?? 'Admin';
$page_icon  = $page_icon  ?? 'fa-gauge';
$base       = '/admin';

// Dynamic logo — admin settings बाट
$_apLogoRaw = function_exists('getLocalizedLogoPath')
    ? trim((string) getLocalizedLogoPath(''))
    : (function_exists('getSetting')
        ? trim((string) getSetting('site_logo', getSetting('logo', '')))
        : '');
$_apLogoSrc = '';
if ($_apLogoRaw !== '') {
    $_apLogoSrc = (preg_match('#^https?://#i', $_apLogoRaw))
        ? $_apLogoRaw
        : rtrim(defined('SITE_URL') ? SITE_URL : '', '/') . '/' . ltrim($_apLogoRaw, '/');
}
$_apSiteName = function_exists('getSetting') ? getSetting('site_name', 'Admin Panel') : 'Admin Panel';
?>
<!DOCTYPE html>
<html lang="ne">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="var(--primary-dark)">
<title><?= htmlspecialchars($page_title) ?> · आकाश डिजिटल सहकारी</title>
<link rel="stylesheet" href="<?= $base ?>/assets/design-tokens.css?v=10.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- UI Uniformity Fix — Global consistency across all interfaces -->
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/ui-uniformity-fix.css?v=1">
</head>
<body class="admin-shell">

<header style="background:var(--brand-gradient);color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:var(--shadow-md);position:sticky;top:0;z-index:50;">
  <!-- LEFT: hamburger + logo + page title -->
  <div class="admin-flex" style="gap:12px;min-width:0;">
    <button onclick="document.getElementById('adminSidebar').classList.toggle('open')"
            style="background:rgba(255,255,255,.15);border:none;color:#fff;width:38px;height:38px;border-radius:var(--radius-sm);cursor:pointer;font-size:17px;flex-shrink:0;">
      <i class="fas fa-bars"></i>
    </button>
    <!-- Logo pill (admin मा logo देखाउने) -->
    <?php if ($_apLogoSrc !== ''): ?>
    <a href="<?= $base ?>/" style="flex-shrink:0;text-decoration:none;">
      <span style="background:#fff;border-radius:10px;padding:4px 12px;height:42px;display:inline-flex;align-items:center;box-shadow:0 4px 12px rgba(0,0,0,.18);border:1px solid rgba(255,255,255,.35);">
        <img src="<?= htmlspecialchars($_apLogoSrc, ENT_QUOTES, 'UTF-8') ?>"
             alt="<?= htmlspecialchars($_apSiteName, ENT_QUOTES, 'UTF-8') ?>"
             style="height:30px;width:auto;max-width:160px;object-fit:contain;display:block;"
             onerror="this.parentNode.innerHTML='<span style=&quot;color:var(--primary-dark);font-weight:800;font-size:13px;padding:0 4px;&quot;><?= htmlspecialchars(addslashes($_apSiteName)) ?></span>'">
      </span>
    </a>
    <?php endif; ?>
    <!-- Page title -->
    <div style="min-width:0;">
      <div style="font-weight:700;font-size:var(--fs-md);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
        <i class="fas <?= htmlspecialchars($page_icon) ?> me-1" style="font-size:.9em;opacity:.85;"></i>
        <?= htmlspecialchars($page_title) ?>
      </div>
      <div style="font-size:var(--fs-xs);opacity:.80;"><?= function_exists('nepaliDateNow') ? nepaliDateNow() : date('Y-m-d') ?></div>
    </div>
  </div>
  <!-- RIGHT: bell + admin name -->
  <div class="admin-flex" style="gap:10px;flex-shrink:0;">
    <a href="<?= $base ?>/notifications.php" style="color:#fff;font-size:18px;line-height:1;text-decoration:none;"><i class="fas fa-bell"></i></a>
    <span style="color:#fff;font-weight:600;font-size:var(--fs-sm);white-space:nowrap;">
      <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>
    </span>
  </div>
</header>

<div class="admin-page">
