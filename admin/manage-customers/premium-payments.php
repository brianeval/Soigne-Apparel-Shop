<?php
require_once("../includes/bootstrap.php");

$errors = [];
include(__DIR__ . '/premium_payments_update.php');

$payments_result = mysqli_query($conn,
    "SELECT pp.payment_id, pp.user_id, pp.amount, pp.receipt_filename, pp.receipt_mime,
            pp.status, pp.submitted_at, pp.reviewed_at,
            u.username, c.customer_name
     FROM premium_payments pp
     JOIN users u ON u.user_id = pp.user_id
     LEFT JOIN customers c ON c.user_id = pp.user_id
     ORDER BY (pp.status = 'pending') DESC, pp.payment_id DESC
     LIMIT 100"
);
$payments = mysqli_fetch_all($payments_result, MYSQLI_ASSOC);

$admin_page_title = 'Premium Payments';
$admin_active_page = 'customers';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">SOIGNÉ PREMIUM</p><h1>Premium Payments</h1><p>Review GCash receipts before activating or renewing VIP.</p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-customers/">&larr; Manage customers</a>
</section>
<?php include("../../includes/alert.php"); ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error); ?></li><?php } ?></ul></div><?php } ?>
<section class="manage-panel manage-list-panel">
  <?php if ($payments) { ?>
    <div class="admin-table-wrap"><table class="admin-table">
      <thead><tr><th>Customer</th><th>Amount</th><th>Receipt</th><th>Submitted</th><th>Status</th><th>Review</th></tr></thead>
      <tbody><?php foreach ($payments as $payment) { ?>
        <tr>
          <td class="order-number"><?php echo htmlspecialchars($payment['customer_name'] ?: $payment['username']); ?><small class="manage-secondary"><?php echo htmlspecialchars($payment['username']); ?> · #<?php echo (int)$payment['user_id']; ?></small></td>
          <td><?php echo '₱' . number_format($payment['amount'], 2); ?></td>
          <td><a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-customers/premium-receipt.php?id=<?php echo (int)$payment['payment_id']; ?>" target="_blank" rel="noopener">View receipt</a></td>
          <td><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($payment['submitted_at']))); ?></td>
          <td><span class="manage-status-<?php echo htmlspecialchars($payment['status']); ?>"><?php echo htmlspecialchars(ucfirst($payment['status'])); ?></span></td>
          <td>
            <?php if ($payment['status'] === 'pending') { ?>
              <div class="manage-row-actions">
                <?php foreach (['approve' => 'Approve and activate VIP', 'reject' => 'Reject'] as $action => $label) { ?>
                  <form method="post">
                    <input type="hidden" name="payment_id" value="<?php echo (int)$payment['payment_id']; ?>">
                    <button class="manage-small-button<?php echo $action === 'reject' ? ' is-danger' : ''; ?>" type="submit" name="action" value="<?php echo htmlspecialchars($action); ?>"><?php echo htmlspecialchars($label); ?></button>
                  </form>
                <?php } ?>
              </div>
            <?php } else { ?>
              <?php echo $payment['reviewed_at'] ? htmlspecialchars(date('M j, Y g:i A', strtotime($payment['reviewed_at']))) : ''; ?>
            <?php } ?>
          </td>
        </tr>
      <?php } ?></tbody>
    </table></div>
  <?php } else { ?>
    <p class="manage-empty">No Premium payment requests have been submitted.</p>
  <?php } ?>
</section>
<?php include("../includes/footer.php"); ?>
