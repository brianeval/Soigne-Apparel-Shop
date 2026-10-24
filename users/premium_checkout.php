<?php
session_start();
include_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/customer_auth.php';

$role = sync_customer_session($conn);
if (!in_array($role, ['customer', 'vip'], true)) {
    if ($role === 'banned') {
        http_response_code(403);
        exit('Suspended accounts cannot purchase Soigné Premium.');
    }
    header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode(BASE_URL . 'users/premium_checkout.php'));
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$customer_stmt = mysqli_prepare($conn,
    "SELECT u.username, c.customer_name
     FROM users u
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE u.user_id = ? AND u.status = 'active' AND u.role IN ('customer', 'vip')"
);
mysqli_stmt_bind_param($customer_stmt, 'i', $user_id);
mysqli_stmt_execute($customer_stmt);
$customer = mysqli_fetch_assoc(mysqli_stmt_get_result($customer_stmt));
if (!$customer) {
    http_response_code(403);
    exit('This account cannot purchase Soigné Premium.');
}
$customer_name = trim((string)($customer['customer_name'] ?? '')) ?: $customer['username'];

$pending_stmt = mysqli_prepare($conn,
    "SELECT payment_id, submitted_at
     FROM premium_payments
     WHERE user_id = ? AND status = 'pending'
     ORDER BY payment_id DESC
     LIMIT 1"
);
mysqli_stmt_bind_param($pending_stmt, 'i', $user_id);
mysqli_stmt_execute($pending_stmt);
$pending_payment = mysqli_fetch_assoc(mysqli_stmt_get_result($pending_stmt));

$errors = [];
$success = $_SESSION['premium_payment_success'] ?? false;
unset($_SESSION['premium_payment_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!is_string($csrf_token) || !isset($_SESSION['premium_checkout_csrf'])
        || !hash_equals($_SESSION['premium_checkout_csrf'], $csrf_token)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    }
    if ($pending_payment) {
        $errors[] = 'You already have a Premium payment awaiting review.';
    }
    $upload = $_FILES['payment_receipt'] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Choose a GCash payment receipt to submit.';
    } elseif (($upload['size'] ?? 0) < 1 || $upload['size'] > 5 * 1024 * 1024) {
        $errors[] = 'The receipt must be no larger than 5 MB.';
    }

    $receipt_mime = '';
    $receipt_extension = '';
    if (!$errors && is_array($upload)) {
        $file_info = new finfo(FILEINFO_MIME_TYPE);
        $receipt_mime = $file_info->file($upload['tmp_name']);
        $allowed_receipts = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed_receipts[$receipt_mime]) || !@getimagesize($upload['tmp_name'])) {
            $errors[] = 'Upload a valid JPEG, PNG, or WebP receipt image.';
        } else {
            $receipt_extension = $allowed_receipts[$receipt_mime];
        }
    }

    if (!$errors && is_array($upload)) {
        $receipt_filename = bin2hex(random_bytes(24)) . '.' . $receipt_extension;
        $private_upload_dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
            . 'soigne_private_uploads' . DIRECTORY_SEPARATOR . 'premium-payments';
        if (!is_dir($private_upload_dir) && !mkdir($private_upload_dir, 0700, true) && !is_dir($private_upload_dir)) {
            $errors[] = 'Could not prepare secure receipt storage. Please try again later.';
        } else {
            $receipt_path = $private_upload_dir . DIRECTORY_SEPARATOR . $receipt_filename;
            if (!move_uploaded_file($upload['tmp_name'], $receipt_path)) {
                $errors[] = 'Could not save the receipt. Please try again.';
            } else {
                try {
                    mysqli_begin_transaction($conn);
                    $account_lock = mysqli_prepare($conn,
                        "SELECT user_id FROM users
                         WHERE user_id = ? AND status = 'active' AND role IN ('customer', 'vip')
                         FOR UPDATE"
                    );
                    mysqli_stmt_bind_param($account_lock, 'i', $user_id);
                    mysqli_stmt_execute($account_lock);
                    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($account_lock))) {
                        throw new RuntimeException('This account cannot purchase Soigné Premium.');
                    }

                    $pending_lock = mysqli_prepare($conn,
                        "SELECT payment_id FROM premium_payments
                         WHERE user_id = ? AND status = 'pending'
                         LIMIT 1 FOR UPDATE"
                    );
                    mysqli_stmt_bind_param($pending_lock, 'i', $user_id);
                    mysqli_stmt_execute($pending_lock);
                    if (mysqli_fetch_assoc(mysqli_stmt_get_result($pending_lock))) {
                        throw new RuntimeException('You already have a Premium payment awaiting review.');
                    }

                    $amount = 1000.00;
                    $payment_stmt = mysqli_prepare($conn,
                        "INSERT INTO premium_payments (user_id, amount, receipt_filename, receipt_mime)
                         VALUES (?, ?, ?, ?)"
                    );
                    mysqli_stmt_bind_param($payment_stmt, 'idss', $user_id, $amount, $receipt_filename, $receipt_mime);
                    mysqli_stmt_execute($payment_stmt);
                    mysqli_commit($conn);
                    $_SESSION['premium_payment_success'] = true;
                    unset($_SESSION['premium_checkout_csrf']);
                    header('Location: ' . BASE_URL . 'users/premium_checkout.php');
                    exit;
                } catch (Throwable $exception) {
                    mysqli_rollback($conn);
                    if (is_file($receipt_path)) {
                        unlink($receipt_path);
                    }
                    $errors[] = $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'Could not submit your Premium payment. Please try again.';
                }
            }
        }
    }
}

if (!isset($_SESSION['premium_checkout_csrf'])) {
    $_SESSION['premium_checkout_csrf'] = bin2hex(random_bytes(32));
}

$page_title = 'Soigné Premium payment | Soigné';
include_once __DIR__ . '/../includes/header.php';
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
        <?php foreach ($errors as $error) { ?><p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php } ?>
      </div>
    <?php } ?>
    <?php if ($pending_payment) { ?>
      <div class="premium-checkout-message is-pending" role="status">
        Your GCash receipt is awaiting review. Submitted <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($pending_payment['submitted_at'])), ENT_QUOTES, 'UTF-8'); ?>.
      </div>
    <?php } ?>

    <dl class="premium-payment-summary">
      <div><dt>Customer</dt><dd><?php echo htmlspecialchars($customer_name, ENT_QUOTES, 'UTF-8'); ?></dd></div>
      <div><dt>Membership</dt><dd>Soigné Premium · 1 month</dd></div>
      <div><dt>Total amount</dt><dd>₱1,000.00</dd></div>
      <div><dt>Pay via GCash</dt><dd class="premium-gcash-number">09123456789</dd></div>
    </dl>

    <?php if (!$pending_payment) { ?>
      <form method="post" enctype="multipart/form-data" class="premium-payment-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['premium_checkout_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
        <label for="payment-receipt">Upload GCash receipt (JPEG, PNG, or WebP; max 5 MB)</label>
        <input type="file" id="payment-receipt" name="payment_receipt" accept="image/jpeg,image/png,image/webp" required>
        <button class="btn" type="submit">Submit receipt for review</button>
      </form>
    <?php } ?>
    <a class="premium-checkout-back" href="<?php echo BASE_URL; ?>index.php">Continue shopping</a>
  </section>
</main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
