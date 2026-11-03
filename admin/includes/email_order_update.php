<?php






$email_updates = [
    'mark_shipped' => [
        'subject' => 'Your Soigné order has shipped',
        'title' => 'Your order is on its way',
        'message' => 'Your order has been handed off for delivery. We will update you when it has been delivered.',
    ],
    'mark_delivered' => [
        'subject' => 'Your Soigné order was delivered',
        'title' => 'Your order was delivered',
        'message' => 'Your order has been marked as delivered. If you paid cash on delivery, please note that payment may still be awaiting confirmation.',
    ],
    'mark_failed_delivery' => [
        'subject' => 'We could not deliver your Soigné order',
        'title' => 'Delivery was unsuccessful',
        'message' => 'The courier could not complete delivery. The order is now closed and its items have been returned to store inventory. Please contact us if you need help.',
    ],
    'mark_paid' => [
        'subject' => 'Payment received for your Soigné order',
        'title' => 'Payment confirmed',
        'message' => 'We have confirmed that payment for your delivered order has been received. Your PDF receipt is attached. Thank you for shopping with Soigné.',
    ],
    'cancel' => [
        'subject' => 'Your Soigné order was cancelled',
        'title' => 'Your order was cancelled',
        'message' => 'Your order has been cancelled, and the reserved items have been returned to store inventory. Please contact us if you believe this was a mistake.',
    ],
    'update_address' => [
        'subject' => 'Shipping address updated for your Soigné order',
        'title' => 'Shipping address updated',
        'message' => 'The shipping address for your pending order has been updated. Please review the new address below.',
    ],
];
$email_update = $email_updates[$email_type];


$mail_attachments = [];
if ($email_type == 'mark_paid') {
    $receipt_order = $email_order;
    include('../includes/receipt_pdf.php');
    $mail_attachments[] = [
        'content' => $receipt_pdf,
        'name' => 'soigne-order-receipt-' . (int)$email_order['order_id'] . '.pdf',
        'mime' => 'application/pdf',
    ];
}

$update_fulfillment = ucwords(str_replace('_', ' ', $email_order['fulfillment_status']));
$update_payment = ucfirst($email_order['payment_status']);
$update_address_html = '';
$update_address_text = '';
if ($email_type == 'update_address') {
    $update_address_html = '<p style="margin:18px 0 0;padding:14px;background:#f3f1ee;"><strong>Updated shipping address</strong><br>'
        . nl2br(htmlspecialchars($email_order['shipping_address'])) . '</p>';
    $update_address_text = "\nUpdated shipping address:\n" . $email_order['shipping_address'] . "\n";
}

$mail_html = '<!doctype html><html lang="en"><body style="margin:0;padding:32px 12px;background:#f7f6f3;color:#1c1c1c;font-family:Arial,sans-serif;line-height:1.5;">'
    . '<div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #e5e5e5;">'
    . '<div style="padding:24px 28px;background:#1c1c1c;color:#fff;"><p style="margin:0;font-size:12px;letter-spacing:3px;">SOIGNÉ</p>'
    . '<h1 style="margin:12px 0 0;font-size:24px;font-weight:500;">' . htmlspecialchars($email_update['title']) . '</h1></div>'
    . '<div style="padding:24px 28px;"><p>Hi ' . htmlspecialchars($email_order['customer_name']) . ',</p><p>' . htmlspecialchars($email_update['message']) . '</p>'
    . '<p><strong>Order #' . (int)$email_order['order_id'] . '</strong></p>'
    . '<div style="padding:14px;background:#f3f1ee;font-size:14px;">'
    . '<p style="margin:0 0 8px;"><strong>Order status:</strong> ' . htmlspecialchars($update_fulfillment) . '</p>'
    . '<p style="margin:0;"><strong>Payment status:</strong> ' . htmlspecialchars($update_payment) . '</p></div>'
    . $update_address_html
    . '<p style="margin:24px 0 0;color:#6b6b6b;font-size:13px;">Thank you for shopping with Soigné.</p>'
    . '</div></div></body></html>';

$mail_text = 'SOIGNÉ - ' . $email_update['title'] . "\n\n"
    . 'Hi ' . $email_order['customer_name'] . ",\n"
    . $email_update['message'] . "\n\n"
    . 'Order #' . $email_order['order_id'] . "\n"
    . 'Order status: ' . $update_fulfillment . "\n"
    . 'Payment status: ' . $update_payment . "\n"
    . $update_address_text;

$mail_to = $email_order['email'];
$mail_name = $email_order['customer_name'];
$mail_subject = $email_update['subject'] . ' (#' . (int)$email_order['order_id'] . ')';
include('../../includes/send_email.php');
