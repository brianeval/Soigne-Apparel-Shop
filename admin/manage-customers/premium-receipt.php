<?php
require_once("../includes/bootstrap.php");

$payment_id = (int)($_GET['id'] ?? 0);

$receipt_result = mysqli_query($conn, "SELECT receipt_filename, receipt_mime FROM premium_payments WHERE payment_id = $payment_id");
$receipt = mysqli_fetch_assoc($receipt_result);


$receipt_path = '';
if ($receipt) {
    $receipt_path = dirname(APP_ROOT) . '/soigne_private_uploads/premium-payments/' . basename($receipt['receipt_filename']);
}
if (!$receipt || !is_file($receipt_path)) {
    http_response_code(404);
    exit('Receipt not found.');
}

header('Content-Type: ' . $receipt['receipt_mime']);
header('Content-Length: ' . filesize($receipt_path));
readfile($receipt_path);
