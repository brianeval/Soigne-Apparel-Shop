<?php
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Dompdf\Dompdf;
use Dompdf\Options;

require_once APP_ROOT . '/vendor/autoload.php';

function send_order_email_message($email, $customer_name, $subject, $html, $text, $attachments = [])
{
    $username = getenv('MAILTRAP_USERNAME');
    $password = getenv('MAILTRAP_PASSWORD');
    if (!$username || !$password) {
        throw new RuntimeException('Mailtrap SMTP credentials are not configured.');
    }

    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = getenv('MAILTRAP_HOST') ?: 'sandbox.smtp.mailtrap.io';
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = (int)(getenv('MAILTRAP_PORT') ?: 2525);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom(getenv('MAILTRAP_FROM_EMAIL') ?: 'no-reply@soigne-apparel.test', getenv('MAILTRAP_FROM_NAME') ?: 'Soigné');
        $mailer->addAddress($email, $customer_name);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $html;
        $mailer->AltBody = $text;
        foreach ($attachments as $attachment) {
            $mailer->addStringAttachment($attachment['content'], $attachment['name'], PHPMailer::ENCODING_BASE64, $attachment['mime']);
        }
        $mailer->send();
    } catch (MailerException $exception) {
        throw new RuntimeException('Could not send the order email: ' . $exception->getMessage(), 0, $exception);
    }
}

function build_order_receipt_pdf(array $order)
{
    $rows = '';
    $subtotal = 0.0;
    foreach ($order['items'] as $item) {
        $quantity = (int)$item['quantity'];
        $unit_price = (float)($item['price'] ?? $item['unit_price'] ?? 0);
        $line_total = $quantity * $unit_price;
        $subtotal += $line_total;
        $description = $item['product_name'] . ' | ' . $item['color'] . ' | Size ' . $item['size'];
        $rows .= '<tr><td>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="number">' . $quantity . '</td>'
            . '<td class="number">&#8369;' . number_format($unit_price, 2) . '</td>'
            . '<td class="number">&#8369;' . number_format($line_total, 2) . '</td></tr>';
    }

    $order_id = (int)$order['order_id'];
    $customer_name = htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($order['email'], ENT_QUOTES, 'UTF-8');
    $shipping_address = nl2br(htmlspecialchars($order['shipping_address'], ENT_QUOTES, 'UTF-8'));
    $created_at = htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8');
    $html = '<!doctype html><html lang="en"><head><meta charset="UTF-8"><style>
        @page{margin:34px}
        body{font-family:DejaVu Sans,sans-serif;color:#282722;font-size:11px;line-height:1.5}
        .receipt{border:1px solid #d8d5ce;padding:30px 34px}
        .masthead{width:100%;border-bottom:2px solid #292821;padding-bottom:18px}
        .brand{font-size:17px;font-weight:bold;letter-spacing:3px}
        .store-note{color:#777;font-size:9px;letter-spacing:1.5px}
        .receipt-title{text-align:right;vertical-align:top}
        .receipt-title strong{font-size:19px;letter-spacing:1px}
        .receipt-title div{color:#777;font-size:10px}
        .meta{width:100%;margin:20px 0 22px}
        .meta td{width:50%;padding:4px 0;vertical-align:top}
        .label{color:#777;font-size:9px;text-transform:uppercase;letter-spacing:.8px}
        .value{font-size:11px}
        table.items{width:100%;border-collapse:collapse}
        .items th{padding:9px 8px;background:#f2f1ee;border-top:1px solid #d8d5ce;border-bottom:1px solid #d8d5ce;font-size:9px;text-transform:uppercase;letter-spacing:.5px;text-align:left}
        .items td{padding:11px 8px;border-bottom:1px solid #e5e3de}
        .items .number{text-align:right;white-space:nowrap}
        .summary{width:100%;margin-top:16px}
        .summary td{padding:5px 8px;text-align:right}
        .summary .grand-total td{border-top:1px solid #292821;padding-top:10px;font-size:14px;font-weight:bold}
        .footer{margin-top:28px;padding-top:12px;border-top:1px solid #d8d5ce;color:#777;text-align:center;font-size:9px}
        </style></head><body><div class="receipt">
        <table class="masthead"><tr><td><div class="brand">SOIGNÉ</div><div class="store-note">APPAREL SHOP</div></td>'
        . '<td class="receipt-title"><strong>RECEIPT</strong><div>Order #' . $order_id . '</div></td></tr></table>'
        . '<table class="meta"><tr><td><div class="label">Receipt date</div><div class="value">' . $created_at
        . '</div></td><td><div class="label">Payment status</div><div class="value">Paid</div></td></tr>'
        . '<tr><td><div class="label">Billed to</div><div class="value">' . $customer_name . '<br>' . $email
        . '</div></td><td><div class="label">Delivered to</div><div class="value">' . $shipping_address
        . '</div></td></tr></table>'
        . '<table class="items"><thead><tr><th>Item</th><th class="number">Qty</th>'
        . '<th class="number">Unit price</th><th class="number">Amount</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>'
        . '<table class="summary"><tr class="grand-total"><td>Total</td><td>&#8369;' . number_format($subtotal, 2)
        . '</td></tr></table><div class="footer">Thank you for shopping with Soigné.</div></div></body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function send_order_confirmation_email(array $order)
{
    $rows = '';
    $text_rows = [];
    foreach ($order['items'] as $item) {
        $unit_price = (float)$item['price'];
        $quantity = (int)$item['quantity'];
        $line_total = $unit_price * $quantity;
        $description = $item['name'] . ' — ' . $item['color'] . ', Size ' . $item['size'];
        $rows .= '<tr><td style="padding:14px 10px;border-bottom:1px solid #e5e5e5;">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e5e5;text-align:center;">' . $quantity . '</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e5e5;text-align:right;white-space:nowrap;">₱' . number_format($unit_price, 2) . '</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e5e5;text-align:right;white-space:nowrap;">₱' . number_format($line_total, 2) . '</td></tr>';
        $text_rows[] = $description . ' | Qty: ' . $quantity . ' | Unit price: ₱' . number_format($unit_price, 2) . ' | Line total: ₱' . number_format($line_total, 2);
    }

    $order_number = (int)$order['order_id'];
    $total = (float)$order['total'];
    $name = htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8');
    $address = nl2br(htmlspecialchars($order['shipping_address'], ENT_QUOTES, 'UTF-8'));
    $payment_method = htmlspecialchars($order['payment_method'], ENT_QUOTES, 'UTF-8');
    $html = '<!doctype html><html lang="en"><body style="margin:0;padding:32px 12px;background:#f7f6f3;color:#1c1c1c;font-family:Arial,sans-serif;line-height:1.5;">'
        . '<div style="max-width:680px;margin:0 auto;background:#fff;border:1px solid #e5e5e5;">'
        . '<div style="padding:24px 28px;background:#1c1c1c;color:#fff;"><p style="margin:0;font-size:12px;letter-spacing:3px;">SOIGNÉ</p>'
        . '<h1 style="margin:12px 0 0;font-size:24px;font-weight:500;">Thank you for your order</h1></div>'
        . '<div style="padding:24px 28px;"><p>Hi ' . $name . ',</p>'
        . '<p>Your order <strong>#' . $order_number . '</strong> has been placed.</p>'
        . '<h2 style="margin:26px 0 10px;font-size:17px;">Order summary</h2>'
        . '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px;">'
        . '<thead><tr style="background:#f3f1ee;text-align:left;"><th style="padding:11px 10px;">Product</th>'
        . '<th style="padding:11px 10px;text-align:center;">Qty</th><th style="padding:11px 10px;text-align:right;">Price</th>'
        . '<th style="padding:11px 10px;text-align:right;">Total</th></tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<p style="margin:18px 0;text-align:right;font-size:17px;"><strong>Order total: ₱' . number_format($total, 2) . '</strong></p>'
        . '<div style="padding:16px;background:#f3f1ee;font-size:14px;"><p style="margin:0 0 8px;"><strong>Shipping to</strong><br>'
        . $name . '<br>' . $address . '</p><p style="margin:0;"><strong>Payment method:</strong> ' . $payment_method . '</p></div>'
        . '<p style="margin:24px 0 0;color:#6b6b6b;font-size:13px;">We appreciate you shopping with Soigné.</p></div></div></body></html>';
    $text = "SOIGNÉ - Thank you for your order\n\nHi " . $order['customer_name'] . ",\n"
        . 'Your order #' . $order_number . " has been placed.\n\nOrder summary:\n" . implode("\n", $text_rows)
        . "\nOrder total: ₱" . number_format($total, 2) . "\n\nShipping to:\n" . $order['customer_name']
        . "\n" . $order['shipping_address'] . "\nPayment method: " . $order['payment_method'] . "\n";

    send_order_email_message($order['email'], $order['customer_name'], 'Soigné order confirmation #' . $order_number, $html, $text);
}

function send_order_update_email(array $order, string $update_type)
{
    $updates = [
        'mark_shipped' => ['subject' => 'Your Soigné order has shipped', 'title' => 'Your order is on its way', 'message' => 'Your order has been handed off for delivery. We will update you when it has been delivered.'],
        'mark_delivered' => ['subject' => 'Your Soigné order was delivered', 'title' => 'Your order was delivered', 'message' => 'Your order has been marked as delivered. If you paid cash on delivery, please note that payment may still be awaiting confirmation.'],
        'mark_failed_delivery' => ['subject' => 'We could not deliver your Soigné order', 'title' => 'Delivery was unsuccessful', 'message' => 'The courier could not complete delivery. The order is now closed and its items have been returned to store inventory. Please contact us if you need help.'],
        'mark_paid' => ['subject' => 'Payment received for your Soigné order', 'title' => 'Payment confirmed', 'message' => 'We have confirmed that payment for your delivered order has been received. Your PDF receipt is attached. Thank you for shopping with Soigné.'],
        'cancel' => ['subject' => 'Your Soigné order was cancelled', 'title' => 'Your order was cancelled', 'message' => 'Your order has been cancelled, and the reserved items have been returned to store inventory. Please contact us if you believe this was a mistake.'],
        'update_address' => ['subject' => 'Shipping address updated for your Soigné order', 'title' => 'Shipping address updated', 'message' => 'The shipping address for your pending order has been updated. Please review the new address below.'],
    ];
    if (!isset($updates[$update_type])) {
        throw new RuntimeException('Unsupported order update email type.');
    }

    $attachments = [];
    if ($update_type === 'mark_paid') {
        $attachments[] = [
            'content' => build_order_receipt_pdf($order),
            'name' => 'soigne-order-receipt-' . (int)$order['order_id'] . '.pdf',
            'mime' => 'application/pdf',
        ];
    }

    $update = $updates[$update_type];
    $order_number = (int)$order['order_id'];
    $name = htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($update['message'], ENT_QUOTES, 'UTF-8');
    $fulfillment = htmlspecialchars(ucwords(str_replace('_', ' ', $order['fulfillment_status'])), ENT_QUOTES, 'UTF-8');
    $payment = htmlspecialchars(ucfirst($order['payment_status']), ENT_QUOTES, 'UTF-8');
    $address_html = '';
    $address_text = '';

    if ($update_type === 'update_address') {
        $address_html = '<p style="margin:18px 0 0;padding:14px;background:#f3f1ee;"><strong>Updated shipping address</strong><br>'
            . nl2br(htmlspecialchars($order['shipping_address'], ENT_QUOTES, 'UTF-8')) . '</p>';
        $address_text = "\nUpdated shipping address:\n" . $order['shipping_address'] . "\n";
    }

    $html = '<!doctype html><html lang="en"><body style="margin:0;padding:32px 12px;background:#f7f6f3;color:#1c1c1c;font-family:Arial,sans-serif;line-height:1.5;">'
        . '<div style="max-width:620px;margin:0 auto;background:#fff;border:1px solid #e5e5e5;">'
        . '<div style="padding:24px 28px;background:#1c1c1c;color:#fff;"><p style="margin:0;font-size:12px;letter-spacing:3px;">SOIGNÉ</p>'
        . '<h1 style="margin:12px 0 0;font-size:24px;font-weight:500;">' . $title . '</h1></div>'
        . '<div style="padding:24px 28px;"><p>Hi ' . $name . ',</p><p>' . $message . '</p>'
        . '<p><strong>Order #' . $order_number . '</strong></p>'
        . '<div style="padding:14px;background:#f3f1ee;font-size:14px;">'
        . '<p style="margin:0 0 8px;"><strong>Order status:</strong> ' . $fulfillment . '</p>'
        . '<p style="margin:0;"><strong>Payment status:</strong> ' . $payment . '</p></div>' . $address_html
        . '<p style="margin:24px 0 0;color:#6b6b6b;font-size:13px;">Thank you for shopping with Soigné.</p>'
        . '</div></div></body></html>';
    $text = "SOIGNÉ - " . $update['title'] . "\n\nHi " . $order['customer_name'] . ",\n" . $update['message']
        . "\n\nOrder #" . $order_number . "\nOrder status: " . ucwords(str_replace('_', ' ', $order['fulfillment_status']))
        . "\nPayment status: " . ucfirst($order['payment_status']) . "\n" . $address_text;

    send_order_email_message($order['email'], $order['customer_name'], $update['subject'] . ' (#' . $order_number . ')', $html, $text, $attachments);
}
