<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/order_email.php';

$order_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($order_id === false || $order_id < 1) {
    http_response_code(404);
    exit('Order receipt not found.');
}

$order_stmt = mysqli_prepare($conn,
    "SELECT o.order_id, o.fulfillment_status, o.payment_status, o.shipping_address,
            o.created_at, u.email,
            COALESCE(NULLIF(c.customer_name, ''), u.username) AS customer_name
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE o.order_id = ?"
);
mysqli_stmt_bind_param($order_stmt, 'i', $order_id);
mysqli_stmt_execute($order_stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($order_stmt));

if (!$order
    || $order['fulfillment_status'] !== 'delivered'
    || $order['payment_status'] !== 'paid') {
    http_response_code(404);
    exit('Order receipt not found.');
}

$items_stmt = mysqli_prepare($conn,
    "SELECT product_name, color, size, quantity, unit_price AS price
     FROM order_transaction_details
     WHERE order_id = ?
     ORDER BY order_item_id"
);
mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
mysqli_stmt_execute($items_stmt);
$order['items'] = mysqli_fetch_all(mysqli_stmt_get_result($items_stmt), MYSQLI_ASSOC);

try {
    $pdf = build_order_receipt_pdf($order);
} catch (Throwable $exception) {
    error_log('Could not generate receipt for order #' . $order_id . ': ' . $exception->getMessage());
    http_response_code(500);
    exit('The receipt could not be generated. Please try again later.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="soigne-order-receipt-' . $order_id . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Content-Type-Options: nosniff');
echo $pdf;
