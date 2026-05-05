<?php
/**
 * Member Registration — Live availability check (AJAX)
 * Returns JSON: { available: bool, message?: string }
 *
 * Issue #4: Inline duplicate validation for email / phone / sadasyata_number
 *
 * SECURITY:
 * - Read-only query, no auth required (it only confirms existence — no PII leaked).
 * - Per-IP rate limit (60 req / minute) to prevent enumeration.
 * - Input strictly whitelisted (field name) and length-capped.
 */
require_once __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

/* ── Simple rate limit (file-based, per IP) ── */
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlKey = 'memchk_' . preg_replace('/[^0-9a-f.:]/i', '', $ip);
if (function_exists('checkRateLimit')) {
    if (!checkRateLimit($rlKey, 60, 60)) {
        echo json_encode(['available' => true, 'message' => '']); // fail-open, don't block UX
        exit;
    }
}

$field = trim((string)($_GET['field'] ?? ''));
$value = trim((string)($_GET['value'] ?? ''));

if (mb_strlen($value) > 100) $value = mb_substr($value, 0, 100);

$allowed = [
    'email'            => 'email',
    'phone'            => 'phone',
    'sadasyata_number' => 'sadasyata_number',
];
if (!isset($allowed[$field]) || $value === '') {
    echo json_encode(['available' => true]);
    exit;
}
$col = $allowed[$field];

/* Input format guards */
if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['available' => false, 'message' => 'मान्य इमेल राख्नुहोस्']);
    exit;
}
if ($field === 'phone' && !preg_match('/^[0-9]{7,15}$/', $value)) {
    echo json_encode(['available' => false, 'message' => 'मान्य मोबाइल नम्बर राख्नुहोस्']);
    exit;
}

try {
    $db = getDB();
    $st = $db->prepare("SELECT id FROM members WHERE $col = ? LIMIT 1");
    $st->execute([$field === 'email' ? strtolower($value) : $value]);
    $exists = (bool)$st->fetch(PDO::FETCH_ASSOC);
    echo json_encode([
        'available' => !$exists,
        'message'   => $exists ? 'पहिले नै दर्ता भएको छ' : '',
    ]);
} catch (Throwable $e) {
    error_log('check-availability: ' . $e->getMessage());
    /* Fail-open so that user can still try; server-side will catch dup on submit. */
    echo json_encode(['available' => true]);
}
