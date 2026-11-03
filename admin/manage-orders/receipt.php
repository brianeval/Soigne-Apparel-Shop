<?php
require_once("../includes/bootstrap.php");

$order_id = (int)($_GET['id'] ?? 0);

$order_result = mysqli_query($conn,
    "SELECT o.order_id, o.fulfillment_status, o.payment_status, o.shipping_address,
            o.created_at, u.email,
            COALESCE(NULLIF(c.customer_name, ''), u.username) AS customer_name
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE o.order_id = $order_id"
);
$order = mysqli_fetch_assoc($order_result);


if (!$order || $order['fulfillment_status'] != 'delivered' || $order['payment_status'] != 'paid') {
    http_response_code(404);
    exit('Order receipt not found.');
}

$items_result = mysqli_query($conn,
    "SELECT product_name, color, size, quantity, unit_price AS price
     FROM order_transaction_details
     WHERE order_id = $order_id
     ORDER BY order_item_id"
);
$order['items'] = mysqli_fetch_all($items_result, MYSQLI_ASSOC);


$receipt_order = $order;
try {
    include("../includes/receipt_pdf.php");
} catch (Exception $e) {
    error_log('Could not generate receipt for order #' . $order_id . ': ' . $e->getMessage());
    http_response_code(500);
    exit('The receipt could not be generated. Please try again later.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="soigne-order-receipt-' . $order_id . '.pdf"');
header('Content-Length: ' . strlen($receipt_pdf));
echo $receipt_pdf;
