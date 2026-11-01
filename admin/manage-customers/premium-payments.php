<?php
require_once("../includes/bootstrap.php");

$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($payment_id < 1 || !in_array($action, ['approve', 'reject'])) {
        $errors[] = 'Choose a valid payment request action.';
    } else {
        try {
            mysqli_begin_transaction($conn);

            $payment_result = mysqli_query($conn, "SELECT user_id, status FROM premium_payments WHERE payment_id = $payment_id");
            $payment = mysqli_fetch_assoc($payment_result);

            if (!$payment || $payment['status'] != 'pending') {
                mysqli_rollback($conn);
                $errors[] = 'This Premium payment is no longer awaiting review.';
            } else {
                $payment_user_id = (int)$payment['user_id'];
                $admin_id = (int)$_SESSION['user_id'];
                $problem = '';

                // Approving gives the customer one more month of VIP
                if ($action == 'approve') {
                    mysqli_query($conn,
                        "UPDATE users
                         SET vip_expires_at = DATE_ADD(GREATEST(COALESCE(vip_expires_at, CURRENT_TIMESTAMP), CURRENT_TIMESTAMP), INTERVAL 1 MONTH),
                             role = 'vip'
                         WHERE user_id = $payment_user_id AND status = 'active' AND role IN ('customer', 'vip')"
                    );
                    if (mysqli_affected_rows($conn) != 1) {
                        $problem = 'The customer account is not active and eligible for VIP activation.';
                    }
                }

                if ($problem == '') {
                    $new_status = ($action == 'approve') ? 'approved' : 'rejected';
                    mysqli_query($conn,
                        "UPDATE premium_payments
                         SET status = '$new_status', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = $admin_id
                         WHERE payment_id = $payment_id"
                    );
                    mysqli_commit($conn);

                    if ($action == 'approve') {
                        $text = 'Payment approved. VIP has been activated for one month.';
                    } else {
                        $text = 'Payment receipt rejected.';
                    }
                    $_SESSION['success'] = $text;
                    header('Location: ' . BASE_URL . 'admin/manage-customers/premium-payments.php');
                    exit;
                }

                mysqli_rollback($conn);
                $errors[] = $problem;
            }
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $errors[] = 'Could not review this Premium payment.';
        }
    }
}

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
