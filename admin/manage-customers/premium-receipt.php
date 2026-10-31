<?php
require_once "../includes/bootstrap.php";

$payment_id = (int)($_GET['id'] ?? 0);
if ($payment_id < 1) {
    http_response_code(404);
    exit('Receipt not found.');
}

$receipt = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT receipt_filename, receipt_mime FROM premium_payments WHERE payment_id = $payment_id"
));
if (!$receipt || !in_array($receipt['receipt_mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(404);
    exit('Receipt not found.');
}

$receipt_path = dirname(APP_ROOT) . '/soigne_private_uploads/premium-payments/' . basename($receipt['receipt_filename']);
if (!is_file($receipt_path)) {
    http_response_code(404);
    exit('Receipt file is unavailable.');
}

header('Content-Type: ' . $receipt['receipt_mime']);
header('Content-Length: ' . filesize($receipt_path));
header('Content-Disposition: inline; filename="premium-receipt-' . $payment_id . '"');
readfile($receipt_path);
