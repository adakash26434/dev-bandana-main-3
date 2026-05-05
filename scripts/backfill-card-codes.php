<?php
/**
 * ════════════════════════════════════════════════════════════
 * BACKFILL CARD VERIFICATION CODES — v10.2
 * ────────────────────────────────────────────────────────────
 * पहिले देखि बनेका member_id_cards मा verification_code + cvv
 * खाली छ — यो script ले सबैलाई fill गर्छ।
 *
 * कसरी run गर्ने?
 *   Browser:  https://yourdomain.com/scripts/backfill-card-codes.php
 *   CLI:      php scripts/backfill-card-codes.php
 *
 * सुरक्षा: यो script एक पटक मात्र चाहिन्छ — चलाएपछि DELETE गर्नुहोस्!
 * ════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/card-verify-helpers.php';

// ── Browser बाट access गरे admin login मात्र allow गर्ने ──
$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    if (function_exists('isAdminLoggedIn') && !isAdminLoggedIn()) {
        http_response_code(403);
        exit('यो script चलाउन admin login आवश्यक छ।');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$pdo = getDB();

echo "═══════════════════════════════════════════════════\n";
echo "Card Verification Code Backfill — v10.2\n";
echo "═══════════════════════════════════════════════════\n\n";

$rows = $pdo->query("SELECT id, member_id, card_no FROM member_id_cards
                     WHERE verification_code IS NULL OR verification_code = ''
                     ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "✓ कुनै पनि card backfill चाहिएको छैन। सबै update भइसकेको।\n";
    exit;
}

echo "→ " . count($rows) . " वटा cards मा code/CVV थप्नु पर्ने छ।\n\n";

$upd = $pdo->prepare("UPDATE member_id_cards
                      SET verification_code = :code, cvv = :cvv
                      WHERE id = :id");

$ok = 0; $fail = 0;
foreach ($rows as $r) {
    try {
        [$code, $cvv] = generateCardVerification($pdo);
        $upd->execute([':code' => $code, ':cvv' => $cvv, ':id' => $r['id']]);
        $ok++;
        printf("  ✓ %-12s  %-22s  →  %s  CVV:%s\n",
               $r['member_id'], $r['card_no'], $code, $cvv);
    } catch (Throwable $e) {
        $fail++;
        printf("  ✗ %-12s  ERROR: %s\n", $r['member_id'], $e->getMessage());
    }
}

echo "\n═══════════════════════════════════════════════════\n";
echo "✓ Success: $ok   ✗ Failed: $fail\n";
echo "═══════════════════════════════════════════════════\n";
echo "\n⚠️  यो script अब DELETE गर्नुहोस् (security):\n";
echo "    /scripts/backfill-card-codes.php\n";
