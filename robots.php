<?php
/**
 * Dynamic robots.txt — Sitemap URL मा SITE_URL (subdir सहित) मिल्छ
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: text/plain; charset=UTF-8');

$sitemap = rtrim(SITE_URL, '/') . '/sitemap.php';

echo "# robots.txt (dynamic)\n";
echo "User-agent: *\n";
echo "Allow: /\n\n";
echo "Disallow: /admin/\n";
echo "Disallow: /member/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /database/\n";
echo "Disallow: /assets/uploads/kyc/\n";
echo "Disallow: /assets/uploads/loan/\n";
echo "Disallow: /assets/uploads/welfare_claims/\n";
echo "Disallow: /assets/uploads/digital_services/\n";
echo "Disallow: /verify-security.php\n\n";
echo 'Sitemap: ' . $sitemap . "\n";
