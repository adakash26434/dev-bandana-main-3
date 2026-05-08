<?php
/**
 * Member Portal Header — v10.4 (Issue #5, #14, #21)
 * Banner-style logo (longer/wider), no duplicate "सहकारी" text,
 * dropdown menu repositioned so it never overlays the logo or
 * the internet-banking icon on mobile.
 */
$memberLang = function_exists('getCurrentLang') ? getCurrentLang() : 'np';
$memberIsEnglish = ($memberLang === 'en');
$memberT = static function (string $np, string $en) use ($memberIsEnglish): string {
  return $memberIsEnglish ? $en : $np;
};
$page_title = $page_title ?? $memberT('सदस्य पोर्टल', 'Member Portal');
$siteName   = function_exists('getSetting') ? getSetting('site_name', 'सहकारी') : 'सहकारी';
$memberLangQuery = $_GET;
$memberLangQuery['lang'] = $memberIsEnglish ? 'np' : 'en';
$memberLangToggleUrl = strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($memberLangQuery);
$memberLangBadge = $memberIsEnglish ? 'EN' : 'ने';

// Dynamic logo — admin settings बाट
$_mpLogoRaw = function_exists('getLocalizedLogoPath')
    ? trim((string) getLocalizedLogoPath(''))
    : (function_exists('getSetting')
        ? trim((string) getSetting('site_logo', getSetting('logo', '')))
        : '');
$_mpLogoSrc = '';
if ($_mpLogoRaw !== '') {
    $_mpLogoSrc = (preg_match('#^https?://#i', $_mpLogoRaw))
        ? $_mpLogoRaw
        : rtrim(defined('SITE_URL') ? SITE_URL : '/', '/') . '/' . ltrim($_mpLogoRaw, '/');
}

// Header member photo: KYC photo -> member avatar -> fallback
$memberPhotoUrl = '';
try {
  $raw = trim((string)($_SESSION['member_avatar'] ?? ''));
  if ($raw === '' && !empty($_SESSION['member_id']) && function_exists('getDB')) {
    $db = getDB();
    $mid = (int)$_SESSION['member_id'];
    $q = $db->prepare("
      SELECT COALESCE(NULLIF(k.photo,''), NULLIF(m.avatar_url,'')) AS photo_path
      FROM members m
      LEFT JOIN kyc_applications k ON k.id = m.kyc_application_id
      WHERE m.id = ?
      LIMIT 1
    ");
    $q->execute([$mid]);
    $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
    $raw = trim((string)($row['photo_path'] ?? ''));
    if ($raw !== '') $_SESSION['member_avatar'] = $raw;
  }
  if ($raw !== '') {
    if (preg_match('#^https?://#i', $raw)) {
      $memberPhotoUrl = $raw;
    } else {
      $memberPhotoUrl = (defined('SITE_URL') ? SITE_URL : '/') . ltrim($raw, '/');
    }
  }
} catch (Throwable $e) {
  $memberPhotoUrl = '';
}
?>
<!DOCTYPE html>
<html lang="<?= $memberIsEnglish ? 'en' : 'ne' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="var(--primary-dark)">
<title><?= htmlspecialchars($page_title) ?> · <?= htmlspecialchars($siteName) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Mukta','Noto Sans Devanagari',sans-serif; background: #f7f9f8; color: #111827; }
  .mp-header {
    background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color, #1a8754) 55%, var(--primary-light, #3aa76b) 100%);
    color: var(--text-on-primary,#fff); padding: 10px 16px;
    box-shadow: 0 8px 18px rgba(13,92,46,.22);
    border-bottom: 1px solid rgba(255,255,255,.16);
    position: sticky; top: 0; z-index: 50;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px;
  }
  .mp-brand { display: flex; align-items: center; min-width: 0; text-decoration:none; color:var(--text-on-primary,#fff); }
  .mp-logo-wrap {
    background: #fff; border-radius: 12px; padding: 5px 14px;
    height: 50px; display: flex; align-items: center;
    box-shadow: 0 8px 16px rgba(0,0,0,.16);
    border: 1px solid rgba(255,255,255,.35);
    flex-shrink: 0;
  }
  .mp-logo-wrap img {
    height: 38px; width: auto; max-width: 220px; min-width: 80px;
    display: block; object-fit: contain;
  }
  .mp-actions { display: flex; align-items: center; gap: 9px; }
  .mp-action-btn {
    width: 40px; height: 40px; border-radius: 11px; border: 1px solid rgba(255,255,255,.28);
    background: rgba(255,255,255,.16); color: var(--text-on-primary,#fff); cursor: pointer; font-size: 16px;
    display: grid; place-items: center; transition: all .15s ease; text-decoration: none;
  }
  .mp-action-btn:hover { background: rgba(255,255,255,.30); color: var(--text-on-primary,#fff); transform: translateY(-1px); }
  .mp-user-chip {
    background: rgba(255,255,255,.15); padding: 7px 13px; border-radius: 999px;
    border: 1px solid rgba(255,255,255,.24);
    font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 6px;
  }
  .mp-user-avatar {
    width: 26px; height: 26px; border-radius: 50%;
    object-fit: cover; border: 1px solid rgba(255,255,255,.45);
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
    background: rgba(255,255,255,.2);
    flex-shrink: 0;
  }
  @media (max-width: 480px) {
    .mp-user-chip { display: none; }
    .mp-logo-wrap { height: 42px; padding: 3px 10px; }
    .mp-logo-wrap img { height: 32px; max-width: 150px; }
    .mp-action-btn { width: 36px; height: 36px; font-size: 15px; }
  }
</style>
</head>
<body>

<header class="mp-header">
  <a href="/member/index.php" class="mp-brand">
    <span class="mp-logo-wrap">
      <?php if ($_mpLogoSrc !== ''): ?>
      <img src="<?= htmlspecialchars($_mpLogoSrc, ENT_QUOTES, 'UTF-8') ?>"
           alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>"
           onerror="this.style.display='none';var fb=document.createElement('span');fb.style.cssText='color:var(--primary-dark);font-weight:800;padding:0 6px;font-size:14px;';fb.textContent=<?= json_encode($siteName) ?>;this.parentNode.appendChild(fb);">
      <?php else: ?>
      <span style="color:var(--primary-dark);font-weight:800;padding:0 6px;font-size:14px;line-height:1.2;"><?= htmlspecialchars($siteName) ?></span>
      <?php endif; ?>
    </span>
  </a>
  <div class="mp-actions">
    <a href="<?= htmlspecialchars($memberLangToggleUrl, ENT_QUOTES, 'UTF-8') ?>" class="mp-action-btn" aria-label="<?= htmlspecialchars($memberT('भाषा परिवर्तन', 'Switch Language')) ?>" title="<?= htmlspecialchars($memberT('भाषा परिवर्तन', 'Switch Language')) ?>">
      <small style="font-size:11px;font-weight:800;line-height:1;"><?= htmlspecialchars($memberLangBadge) ?></small>
    </a>
    <a href="/member/notifications.php" class="mp-action-btn" aria-label="<?= htmlspecialchars($memberT('सूचना', 'Notifications')) ?>"><i class="fas fa-bell"></i></a>
    <span class="mp-user-chip">
      <?php if ($memberPhotoUrl !== ''): ?>
        <img src="<?= htmlspecialchars($memberPhotoUrl) ?>" class="mp-user-avatar" alt="Member"
             onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
        <i class="fas fa-user-circle" style="display:none;"></i>
      <?php else: ?>
        <i class="fas fa-user-circle"></i>
      <?php endif; ?>
      <?= htmlspecialchars($_SESSION['member_name'] ?? $memberT('सदस्य', 'Member')) ?>
    </span>
    <a href="/member/logout.php" class="mp-action-btn" aria-label="<?= htmlspecialchars($memberT('लगआउट', 'Logout')) ?>"><i class="fas fa-right-from-bracket"></i></a>
  </div>
</header>

<main>
