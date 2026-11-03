<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($payment_id < 1 || !in_array($action, ['approve', 'reject'])) {
        $errors[] = 'Choose a valid payment request action.';
    } else {
        try {
            mysqli_begin_transaction($conn);

            $payment_result = mysqli_execute_query($conn, "SELECT user_id, status FROM premium_payments WHERE payment_id = ?", [$payment_id]);
            $payment = mysqli_fetch_assoc($payment_result);

            if (!$payment || $payment['status'] != 'pending') {
                mysqli_rollback($conn);
                $errors[] = 'This Premium payment is no longer awaiting review.';
            } else {
                $payment_user_id = (int)$payment['user_id'];
                $admin_id = (int)$_SESSION['user_id'];
                $problem = '';


                if ($action == 'approve') {
                    mysqli_execute_query($conn,
                        "UPDATE users
                         SET vip_expires_at = DATE_ADD(GREATEST(COALESCE(vip_expires_at, CURRENT_TIMESTAMP), CURRENT_TIMESTAMP), INTERVAL 1 MONTH),
                             role = 'vip'
                         WHERE user_id = ? AND status = 'active' AND role IN ('customer', 'vip')",
                        [$payment_user_id]
                    );
                    if (mysqli_affected_rows($conn) != 1) {
                        $problem = 'The customer account is not active and eligible for VIP activation.';
                    }
                }

                if ($problem == '') {
                    $new_status = ($action == 'approve') ? 'approved' : 'rejected';
                    mysqli_execute_query($conn,
                        "UPDATE premium_payments
                         SET status = ?, reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ?
                         WHERE payment_id = ?",
                        [$new_status, $admin_id, $payment_id]
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
