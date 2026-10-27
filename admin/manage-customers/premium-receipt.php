<?php
require_once("../includes/bootstrap.php");

$payment_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($payment_id === false || $payment_id < 1) {
    http_response_code(404);
    exit('Receipt not found.');
}

$stmt = mysqli_prepare($conn,
    "SELECT receipt_filename, receipt_mime
     FROM premium_payments
     WHERE payment_id = ?"
);
mysqli_stmt_bind_param($stmt, 'i', $payment_id);
mysqli_stmt_execute($stmt);
$receipt = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
if (!$receipt || !in_array($receipt['receipt_mime'], $allowed_types, true)
    || !preg_match('/^[a-f0-9]{48}\\.(jpg|png|webp)$/', $receipt['receipt_filename'])) {
    http_response_code(404);
    exit('Receipt not found.');
}

$receipt_path = dirname(APP_ROOT) . DIRECTORY_SEPARATOR . 'soigne_private_uploads'
    . DIRECTORY_SEPARATOR . 'premium-payments' . DIRECTORY_SEPARATOR . $receipt['receipt_filename'];
if (!is_file($receipt_path)) {
    http_response_code(404);
    exit('Receipt file is unavailable.');
}

header('Content-Type: ' . $receipt['receipt_mime']);
header('Content-Length: ' . filesize($receipt_path));
header('Content-Disposition: inline; filename="premium-receipt-' . $payment_id . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($receipt_path);
