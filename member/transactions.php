<?php
/**
 * Member Portal — कारोबार विवरण (Transactions)
 */
require_once __DIR__ . '/_bootstrap.php';
requireMemberLogin();
memberSecurityHeaders();

$member   = getLoggedInMemberProfile();
$memberId = $_SESSION['member_id'];
$db       = getDB();

// Pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;
$filter  = in_array($_GET['type'] ?? '', ['credit','debit','']) ? ($_GET['type'] ?? '') : '';

$pageTitle = isEnglish() ? 'Transaction History' : 'कारोबार विवरण';
?>
<?php require __DIR__ . '/includes/chrome.php'; ?>

<main class="mp-main">
<div class="mp-container">

  <!-- Page header -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
    <h1 style="font-size:1.3rem;font-weight:700;color:var(--primary-color);margin:0;">
      <i class="fas fa-money-bill-transfer" style="margin-right:8px;"></i>
      <?php echo isEnglish() ? 'Transaction History' : 'कारोबार विवरण'; ?>
    </h1>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="?type=" class="btn btn-sm <?php echo $filter==='' ? 'btn-success' : 'btn-outline-secondary'; ?>">सबै</a>
      <a href="?type=credit" class="btn btn-sm <?php echo $filter==='credit' ? 'btn-success' : 'btn-outline-secondary'; ?>">
        <i class="fas fa-arrow-down me-1"></i>जम्मा
      </a>
      <a href="?type=debit" class="btn btn-sm <?php echo $filter==='debit' ? 'btn-danger' : 'btn-outline-secondary'; ?>">
        <i class="fas fa-arrow-up me-1"></i>झिकेको
      </a>
    </div>
  </div>

  <?php
  $transactions = [];
  $totalCount   = 0;
  $totalCredit  = 0;
  $totalDebit   = 0;
  try {
      // Try member_transactions table first
      $whereFilter = $filter ? "AND transaction_type = ?" : '';
      $filterParams = $filter ? [$memberId, $filter] : [$memberId];

      $countSt = $db->prepare("SELECT COUNT(*), SUM(CASE WHEN transaction_type='credit' THEN amount ELSE 0 END), SUM(CASE WHEN transaction_type='debit' THEN amount ELSE 0 END) FROM member_transactions WHERE member_id=? $whereFilter");
      $countSt->execute($filterParams);
      [$totalCount, $totalCredit, $totalDebit] = $countSt->fetch(\PDO::FETCH_NUM);

      $st = $db->prepare("SELECT * FROM member_transactions WHERE member_id=? $whereFilter ORDER BY created_at DESC LIMIT ? OFFSET ?");
      $st->execute(array_merge($filterParams, [$perPage, $offset]));
      $transactions = $st->fetchAll(\PDO::FETCH_ASSOC);
  } catch (\Exception $e) {
      // Table may not exist yet
      $transactions = [];
      $totalCount   = 0;
  }
  $totalPages = $totalCount > 0 ? ceil($totalCount / $perPage) : 1;
  ?>

  <!-- Summary Cards -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px;">
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:16px;text-align:center;">
      <div style="font-size:1.1rem;font-weight:700;color:#16a34a;"><?php echo formatNepaliCurrency((float)$totalCredit); ?></div>
      <div style="font-size:12px;color:#15803d;margin-top:4px;"><i class="fas fa-arrow-down me-1"></i>जम्मा</div>
    </div>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;text-align:center;">
      <div style="font-size:1.1rem;font-weight:700;color:#dc2626;"><?php echo formatNepaliCurrency((float)$totalDebit); ?></div>
      <div style="font-size:12px;color:#b91c1c;margin-top:4px;"><i class="fas fa-arrow-up me-1"></i>झिकेको</div>
    </div>
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;text-align:center;">
      <div style="font-size:1.1rem;font-weight:700;color:#2563eb;"><?php echo formatNepaliCurrency((float)$totalCredit - (float)$totalDebit); ?></div>
      <div style="font-size:12px;color:#1d4ed8;margin-top:4px;"><i class="fas fa-wallet me-1"></i>ब्यालेन्स</div>
    </div>
    <div style="background:#fafafa;border:1px solid #e5e7eb;border-radius:10px;padding:16px;text-align:center;">
      <div style="font-size:1.1rem;font-weight:700;color:#374151;"><?php echo toNepaliNumeral($totalCount); ?></div>
      <div style="font-size:12px;color:#6b7280;margin-top:4px;"><i class="fas fa-list me-1"></i>जम्मा कारोबार</div>
    </div>
  </div>

  <!-- Transaction List -->
  <div style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;">
    <?php if (empty($transactions)): ?>
    <div style="text-align:center;padding:48px 24px;">
      <i class="fas fa-receipt" style="font-size:2.5rem;color:#d1d5db;margin-bottom:12px;display:block;"></i>
      <p style="color:#6b7280;margin:0;">
        <?php echo isEnglish() ? 'No transactions found.' : 'कुनै कारोबार फेला परेन।'; ?>
      </p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb;">
            <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#374151;white-space:nowrap;">मिति</th>
            <th style="padding:12px 16px;text-align:left;font-size:12px;font-weight:600;color:#374151;">विवरण</th>
            <th style="padding:12px 16px;text-align:right;font-size:12px;font-weight:600;color:#374151;white-space:nowrap;">रकम (रु.)</th>
            <th style="padding:12px 8px;text-align:center;font-size:12px;font-weight:600;color:#374151;">प्रकार</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $tx): ?>
          <?php $isCredit = ($tx['transaction_type'] ?? '') === 'credit'; ?>
          <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:12px 16px;font-size:13px;color:#6b7280;white-space:nowrap;">
              <?php echo htmlspecialchars(date('Y-m-d', strtotime($tx['created_at']))); ?>
            </td>
            <td style="padding:12px 16px;font-size:13px;color:#111827;">
              <?php echo htmlspecialchars($tx['description'] ?? $tx['remarks'] ?? '—'); ?>
              <?php if (!empty($tx['reference_no'])): ?>
              <br><small style="color:#9ca3af;">Ref: <?php echo htmlspecialchars($tx['reference_no']); ?></small>
              <?php endif; ?>
            </td>
            <td style="padding:12px 16px;text-align:right;font-size:14px;font-weight:700;white-space:nowrap;
                       color:<?php echo $isCredit ? '#16a34a' : '#dc2626'; ?>;">
              <?php echo ($isCredit ? '+' : '−') . formatNepaliCurrency((float)($tx['amount'] ?? 0)); ?>
            </td>
            <td style="padding:12px 8px;text-align:center;">
              <?php if ($isCredit): ?>
              <span style="display:inline-flex;align-items:center;gap:4px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;">
                <i class="fas fa-arrow-down" style="font-size:9px;"></i> जम्मा
              </span>
              <?php else: ?>
              <span style="display:inline-flex;align-items:center;gap:4px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;">
                <i class="fas fa-arrow-up" style="font-size:9px;"></i> झिकेको
              </span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex;justify-content:center;gap:8px;padding:16px;border-top:1px solid #f3f4f6;flex-wrap:wrap;">
      <?php if ($page > 1): ?>
      <a href="?page=<?php echo $page-1; ?>&type=<?php echo $filter; ?>" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;color:#374151;text-decoration:none;">‹</a>
      <?php endif; ?>
      <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
      <a href="?page=<?php echo $i; ?>&type=<?php echo $filter; ?>"
         style="padding:6px 12px;border-radius:6px;font-size:13px;text-decoration:none;
                <?php echo $i===$page ? 'background:var(--primary-color);color:#fff;border:1px solid var(--primary-color);' : 'border:1px solid #d1d5db;color:#374151;'; ?>">
        <?php echo $i; ?>
      </a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
      <a href="?page=<?php echo $page+1; ?>&type=<?php echo $filter; ?>" style="padding:6px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;color:#374151;text-decoration:none;">›</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

</div>
</main>

<?php require __DIR__ . '/includes/chrome-foot.php'; ?>
