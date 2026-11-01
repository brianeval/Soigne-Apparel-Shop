<?php
// Emails the order confirmation to the customer.
// Set $email_order before including this file. It needs these keys:
//   email, customer_name, shipping_address, payment_method, order_id, total
//   and items (each item has name, color, size, quantity and price)
// Result: $mail_error is empty when the email was sent.
$confirm_rows = '';
$confirm_text_rows = [];
foreach ($email_order['items'] as $confirm_item) {
    $line_total = $confirm_item['price'] * $confirm_item['quantity'];
    $description = $confirm_item['name'] . ' — ' . $confirm_item['color'] . ', Size ' . $confirm_item['size'];
    $cell = 'padding:14px 10px;border-bottom:1px solid #e5e5e5;';
    $confirm_rows .= '<tr><td style="' . $cell . '">' . htmlspecialchars($description) . '</td>'
        . '<td style="' . $cell . 'text-align:center;">' . (int)$confirm_item['quantity'] . '</td>'
        . '<td style="' . $cell . 'text-align:right;white-space:nowrap;">₱' . number_format($confirm_item['price'], 2) . '</td>'
        . '<td style="' . $cell . 'text-align:right;white-space:nowrap;">₱' . number_format($line_total, 2) . '</td></tr>';
    $confirm_text_rows[] = $description . ' | Qty: ' . $confirm_item['quantity']
        . ' | Unit price: ₱' . number_format($confirm_item['price'], 2)
        . ' | Line total: ₱' . number_format($line_total, 2);
}

$confirm_name = htmlspecialchars($email_order['customer_name']);
$confirm_address = nl2br(htmlspecialchars($email_order['shipping_address']));

$mail_html = '<!doctype html><html lang="en"><body style="margin:0;padding:32px 12px;background:#f7f6f3;color:#1c1c1c;font-family:Arial,sans-serif;line-height:1.5;">'
    . '<div style="max-width:680px;margin:0 auto;background:#fff;border:1px solid #e5e5e5;">'
    . '<div style="padding:24px 28px;background:#1c1c1c;color:#fff;"><p style="margin:0;font-size:12px;letter-spacing:3px;">SOIGNÉ</p>'
    . '<h1 style="margin:12px 0 0;font-size:24px;font-weight:500;">Thank you for your order</h1></div>'
    . '<div style="padding:24px 28px;"><p>Hi ' . $confirm_name . ',</p>'
    . '<p>Your order <strong>#' . (int)$email_order['order_id'] . '</strong> has been placed.</p>'
    . '<h2 style="margin:26px 0 10px;font-size:17px;">Order summary</h2>'
    . '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px;">'
    . '<thead><tr style="background:#f3f1ee;text-align:left;">'
    . '<th style="padding:11px 10px;">Product</th><th style="padding:11px 10px;text-align:center;">Qty</th>'
    . '<th style="padding:11px 10px;text-align:right;">Price</th><th style="padding:11px 10px;text-align:right;">Total</th>'
    . '</tr></thead><tbody>' . $confirm_rows . '</tbody></table>'
    . '<p style="margin:18px 0;text-align:right;font-size:17px;"><strong>Order total: ₱' . number_format($email_order['total'], 2) . '</strong></p>'
    . '<div style="padding:16px;background:#f3f1ee;font-size:14px;">'
    . '<p style="margin:0 0 8px;"><strong>Shipping to</strong><br>' . $confirm_name . '<br>' . $confirm_address . '</p>'
    . '<p style="margin:0;"><strong>Payment method:</strong> ' . htmlspecialchars($email_order['payment_method']) . '</p></div>'
    . '<p style="margin:24px 0 0;color:#6b6b6b;font-size:13px;">We appreciate you shopping with Soigné.</p>'
    . '</div></div></body></html>';

$mail_text = "SOIGNÉ - Thank you for your order\n\n"
    . 'Hi ' . $email_order['customer_name'] . ",\n"
    . 'Your order #' . $email_order['order_id'] . " has been placed.\n\n"
    . "Order summary:\n" . implode("\n", $confirm_text_rows) . "\n"
    . 'Order total: ₱' . number_format($email_order['total'], 2) . "\n\n"
    . "Shipping to:\n" . $email_order['customer_name'] . "\n"
    . $email_order['shipping_address'] . "\n"
    . 'Payment method: ' . $email_order['payment_method'] . "\n";

$mail_to = $email_order['email'];
$mail_name = $email_order['customer_name'];
$mail_subject = 'Soigné order confirmation #' . (int)$email_order['order_id'];
$mail_attachments = [];
include('../includes/send_email.php');
