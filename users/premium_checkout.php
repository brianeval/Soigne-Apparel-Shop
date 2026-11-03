<?php
session_start();
include_once("../includes/config.php");

$login_redirect = BASE_URL . 'users/premium_checkout.php';
include("../includes/require_customer.php");

$user_id = (int)$_SESSION['user_id'];
$customer_result = mysqli_execute_query($conn, "SELECT u.username, c.customer_name
     FROM users u
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE u.user_id = ?", [$user_id]);
$customer = mysqli_fetch_assoc($customer_result);

$customer_name = $customer['customer_name'] ?: $customer['username'];


$pending_result = mysqli_query($conn,
    "SELECT payment_id, submitted_at
     FROM premium_payments
     WHERE user_id = $user_id AND status = 'pending'
     ORDER BY payment_id DESC
     LIMIT 1"
);
$pending_payment = mysqli_fetch_assoc($pending_result);

$errors = [];
$success = $_SESSION['premium_payment_success'] ?? false;
unset($_SESSION['premium_payment_success']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $upload = $_FILES['payment_receipt'] ?? null;

    if ($pending_payment) {
        $errors[] = 'You already have a Premium payment awaiting review.';
    }
    if (!$upload || $upload['error'] != UPLOAD_ERR_OK) {
        $errors[] = 'Choose a GCash payment receipt to submit.';
    } elseif ($upload['size'] > 5 * 1024 * 1024) {
        $errors[] = 'The receipt must be no larger than 5 MB.';
    } else {

        $receipt_types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        $receipt_extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        if (!isset($receipt_types[$receipt_extension])) {
            $errors[] = 'Upload a valid JPEG, PNG, or WebP receipt image.';
        }
    }

    if (!$errors) {

        $receipt_filename = uniqid('receipt-', true) . '.' . $receipt_extension;
        $receipt_mime = $receipt_types[$receipt_extension];
        $private_upload_dir = dirname(APP_ROOT) . '/soigne_private_uploads/premium-payments';
        if (!is_dir($private_upload_dir)) {
            mkdir($private_upload_dir, 0700, true);
        }

        if (!move_uploaded_file($upload['tmp_name'], $private_upload_dir . '/' . $receipt_filename)) {
            $errors[] = 'Could not save the receipt. Please try again.';
        } else {
            try {
                $amount = 1000.00;
                $payment_result = mysqli_execute_query($conn, "INSERT INTO premium_payments (user_id, amount, receipt_filename, receipt_mime)
                     VALUES (?, ?, ?, ?)", [$user_id, $amount, $receipt_filename, $receipt_mime]);

                $_SESSION['premium_payment_success'] = true;
                header('Location: ' . BASE_URL . 'users/premium_checkout.php');
                exit;
            } catch (mysqli_sql_exception $e) {
                unlink($private_upload_dir . '/' . $receipt_filename);
                $errors[] = 'Could not submit your Premium payment. Please try again.';
            }
        }
    }
}

$page_title = 'Soigné Premium payment | Soigné';
include_once("../includes/header.php");
?>
<main class="premium-checkout-page">
  <section class="premium-checkout-card">
    <p class="premium-eyebrow">SOIGNÉ PREMIUM</p>
    <h1>Complete your payment</h1>
    <p>Pay online through GCash only. Your VIP benefits begin after an administrator verifies your receipt.</p>

    <?php if ($success) { ?>
      <p class="premium-checkout-message is-success" role="status">Your receipt was submitted. We’ll review your payment before activating VIP.</p>
    <?php } ?>
    <?php if ($errors) { ?>
      <div class="premium-checkout-message is-error" role="alert">
        <?php foreach ($errors as $error) { ?><p><?php echo htmlspecialchars($error); ?></p><?php } ?>
      </div>
    <?php } ?>
    <?php if ($pending_payment) { ?>
      <div class="premium-checkout-message is-pending" role="status">
        Your GCash receipt is awaiting review. Submitted <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($pending_payment['submitted_at']))); ?>.
      </div>
    <?php } ?>

    <dl class="premium-payment-summary">
      <div><dt>Customer</dt><dd><?php echo htmlspecialchars($customer_name); ?></dd></div>
      <div><dt>Membership</dt><dd>Soigné Premium · 1 month</dd></div>
      <div><dt>Total amount</dt><dd>₱1,000.00</dd></div>
      <div><dt>Pay via GCash</dt><dd class="premium-gcash-number">09123456789</dd></div>
    </dl>

    <?php if (!$pending_payment) { ?>
      <form method="post" enctype="multipart/form-data" class="premium-payment-form">
        <label for="payment-receipt">Upload GCash receipt (JPEG, PNG, or WebP; max 5 MB)</label>
        <input type="file" id="payment-receipt" name="payment_receipt" accept="image/jpeg,image/png,image/webp" required>
        <button class="btn" type="submit">Submit receipt for review</button>
      </form>
    <?php } ?>
    <a class="premium-checkout-back" href="<?php echo BASE_URL; ?>index.php">Continue shopping</a>
  </section>
</main>
<?php include_once("../includes/footer.php"); ?>
