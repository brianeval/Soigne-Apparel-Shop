<?php
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../vendor/autoload.php';

function send_order_email_message(string $email, string $customer_name, string $subject, string $html, string $text, array $attachments = []): void
{
    $username = getenv('MAILTRAP_USERNAME');
    $password = getenv('MAILTRAP_PASSWORD');
    $host = getenv('MAILTRAP_HOST') ?: 'sandbox.smtp.mailtrap.io';
    $port_value = getenv('MAILTRAP_PORT') ?: '2525';
    $from_email = getenv('MAILTRAP_FROM_EMAIL') ?: 'no-reply@soigne-apparel.test';
    $from_name = getenv('MAILTRAP_FROM_NAME') ?: 'Soigné';

    if (!$username || !$password) {
        throw new RuntimeException('Mailtrap SMTP credentials are not configured.');
    }
    if (!ctype_digit((string)$port_value) || (int)$port_value < 1 || (int)$port_value > 65535) {
        throw new RuntimeException('The Mailtrap SMTP port is invalid.');
    }

    try {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = (int)$port_value;
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->setFrom($from_email, $from_name);
        $mailer->addAddress($email, $customer_name);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $html;
        $mailer->AltBody = $text;
        foreach ($attachments as $attachment) {
            $mailer->addStringAttachment(
                $attachment['content'],
                $attachment['name'],
                PHPMailer::ENCODING_BASE64,
                $attachment['mime']
            );
        }
        $mailer->send();
    } catch (MailerException $exception) {
        $server_reply = trim($mailer->getSMTPInstance()->getLastReply());
        $error_message = 'Could not send the order email.';
        if ($server_reply !== '') {
            $error_message .= ' Mail server reply: ' . $server_reply;
        }
        throw new RuntimeException($error_message, 0, $exception);
    }
}

function build_order_receipt_pdf(array $order): string
{
    if (empty($order['items']) || !is_array($order['items'])) {
        throw new RuntimeException('Order receipt has no items.');
    }
    if (($order['fulfillment_status'] ?? null) !== 'delivered'
        || ($order['payment_status'] ?? null) !== 'paid') {
        throw new InvalidArgumentException('A receipt can only be generated for a delivered, paid order.');
    }

    $escape = static function ($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $money = static function (float $amount): string {
        return '&#8369;' . number_format($amount, 2);
    };

    $rows = '';
    $subtotal = 0.0;
    foreach ($order['items'] as $item) {
        $quantity = (int)($item['quantity'] ?? 0);
        $unit_price = (float)($item['price'] ?? $item['unit_price'] ?? 0);
        if ($quantity < 1 || $unit_price < 0) {
            throw new RuntimeException('Order receipt contains invalid item details.');
        }

        $line_total = $quantity * $unit_price;
        $subtotal += $line_total;
        $description = trim(
            (string)($item['product_name'] ?? $item['name'] ?? '')
            . ' | ' . (string)($item['color'] ?? '')
            . ' | Size ' . (string)($item['size'] ?? '')
        );
        $rows .= '<tr><td>' . $escape($description) . '</td>'
            . '<td class="number">' . $quantity . '</td>'
            . '<td class="number">' . $money($unit_price) . '</td>'
            . '<td class="number">' . $money($line_total) . '</td></tr>';
    }

    $order_id = (int)($order['order_id'] ?? 0);
    $customer_name = $escape($order['customer_name'] ?? '');
    $email = $escape($order['email'] ?? '');
    $shipping_address = nl2br($escape($order['shipping_address'] ?? ''));
    $created_at = $escape($order['created_at'] ?? '');
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
        . '<td class="receipt-title"><strong>RECEIPT</strong><div>Order #' . $order_id
        . '</div></td></tr></table>
        <table class="meta"><tr><td><div class="label">Receipt date</div><div class="value">' . $created_at
        . '</div></td><td><div class="label">Payment status</div><div class="value">Paid</div></td></tr>'
        . '<tr><td><div class="label">Billed to</div><div class="value">' . $customer_name
        . '<br>' . $email . '</div></td><td><div class="label">Delivered to</div><div class="value">' . $shipping_address
        . '</div></td></tr></table>
        <table class="items"><thead><tr><th>Item</th><th class="number">Qty</th>'
        . '<th class="number">Unit price</th><th class="number">Amount</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>
        <table class="summary"><tr class="grand-total"><td>Total</td><td>' . $money($subtotal)
        . '</td></tr></table>
        <div class="footer">Thank you for shopping with Soigné.</div></div></body></html>';

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

function send_order_confirmation_email(array $order): void
{
    $escape = static function ($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $money = static function ($amount): string {
        return '₱' . number_format((float)$amount, 2);
    };

    $rows = '';
    $text_rows = [];
    foreach ($order['items'] as $item) {
        $unit_price = (float)$item['price'];
        $quantity = (int)$item['quantity'];
        $line_total = $unit_price * $quantity;
        $description = $item['name'] . ' — ' . $item['color'] . ', Size ' . $item['size'];
        $rows .= '<tr>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e5e5;">' . $escape($description) . '</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e5e5;text-align:center;">' . $quantity . '</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e5e5;text-align:right;white-space:nowrap;">' . $escape($money($unit_price)) . '</td>'
            . '<td style="padding:14px 10px;border-bottom:1px solid #e5e5e5;text-align:right;white-space:nowrap;">' . $escape($money($line_total)) . '</td>'
            . '</tr>';
        $text_rows[] = $description . ' | Qty: ' . $quantity
            . ' | Unit price: ' . $money($unit_price)
            . ' | Line total: ' . $money($line_total);
    }

    $order_number = (int)$order['order_id'];
    $total = (float)$order['total'];
    $name = $escape($order['customer_name']);
    $address = nl2br($escape($order['shipping_address']));
    $payment_method = $escape($order['payment_method']);
    $html = '<!doctype html><html lang="en"><body style="margin:0;padding:32px 12px;background:#f7f6f3;color:#1c1c1c;font-family:Arial,sans-serif;line-height:1.5;">'
        . '<div style="max-width:680px;margin:0 auto;background:#fff;border:1px solid #e5e5e5;">'
        . '<div style="padding:24px 28px;background:#1c1c1c;color:#fff;"><p style="margin:0;font-size:12px;letter-spacing:3px;">SOIGNÉ</p>'
        . '<h1 style="margin:12px 0 0;font-size:24px;font-weight:500;">Thank you for your order</h1></div>'
        . '<div style="padding:24px 28px;"><p>Hi ' . $name . ',</p>'
        . '<p>Your order <strong>#' . $order_number . '</strong> has been placed.</p>'
        . '<h2 style="margin:26px 0 10px;font-size:17px;">Order summary</h2>'
        . '<table role="presentation" style="width:100%;border-collapse:collapse;font-size:13px;">'
        . '<thead><tr style="background:#f3f1ee;text-align:left;">'
        . '<th style="padding:11px 10px;">Product</th><th style="padding:11px 10px;text-align:center;">Qty</th>'
        . '<th style="padding:11px 10px;text-align:right;">Price</th><th style="padding:11px 10px;text-align:right;">Total</th>'
        . '</tr></thead><tbody>' . $rows . '</tbody></table>'
        . '<p style="margin:18px 0;text-align:right;font-size:17px;"><strong>Order total: ' . $escape($money($total)) . '</strong></p>'
        . '<div style="padding:16px;background:#f3f1ee;font-size:14px;">'
        . '<p style="margin:0 0 8px;"><strong>Shipping to</strong><br>' . $name . '<br>' . $address . '</p>'
        . '<p style="margin:0;"><strong>Payment method:</strong> ' . $payment_method . '</p></div>'
        . '<p style="margin:24px 0 0;color:#6b6b6b;font-size:13px;">We appreciate you shopping with Soigné.</p>'
        . '</div></div></body></html>';
    $text = "SOIGNÉ - Thank you for your order\n\n"
        . 'Hi ' . $order['customer_name'] . ",\n"
        . 'Your order #' . $order_number . " has been placed.\n\n"
        . "Order summary:\n" . implode("\n", $text_rows) . "\n"
        . 'Order total: ' . $money($total) . "\n\n"
        . "Shipping to:\n" . $order['customer_name'] . "\n"
        . $order['shipping_address'] . "\n"
        . 'Payment method: ' . $order['payment_method'] . "\n";

    send_order_email_message(
        $order['email'],
        $order['customer_name'],
        'Soigné order confirmation #' . $order_number,
        $html,
        $text
    );
}

function send_order_update_email(array $order, string $update_type): void
{
    $updates = [
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

    if (!isset($updates[$update_type])) {
        throw new InvalidArgumentException('Unsupported order update email type.');
    }
    $attachments = [];
    if ($update_type === 'mark_paid') {
        if (($order['payment_status'] ?? '') !== 'paid') {
            throw new InvalidArgumentException('A receipt can only be attached to a paid order email.');
        }
        $attachments[] = [
            'content' => build_order_receipt_pdf($order),
            'name' => 'soigne-order-receipt-' . (int)$order['order_id'] . '.pdf',
            'mime' => 'application/pdf',
        ];
    }

    $escape = static function ($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $update = $updates[$update_type];
    $order_number = (int)$order['order_id'];
    $name = $escape($order['customer_name']);
    $title = $escape($update['title']);
    $message = $escape($update['message']);
    $fulfillment = $escape(ucwords(str_replace('_', ' ', $order['fulfillment_status'])));
    $payment = $escape(ucfirst($order['payment_status']));
    $address_html = '';
    $address_text = '';

    if ($update_type === 'update_address') {
        $address_html = '<p style="margin:18px 0 0;padding:14px;background:#f3f1ee;"><strong>Updated shipping address</strong><br>'
            . nl2br($escape($order['shipping_address'])) . '</p>';
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
        . '<p style="margin:0;"><strong>Payment status:</strong> ' . $payment . '</p></div>'
        . $address_html
        . '<p style="margin:24px 0 0;color:#6b6b6b;font-size:13px;">Thank you for shopping with Soigné.</p>'
        . '</div></div></body></html>';
    $text = "SOIGNÉ - " . $update['title'] . "\n\n"
        . 'Hi ' . $order['customer_name'] . ",\n"
        . $update['message'] . "\n\n"
        . 'Order #' . $order_number . "\n"
        . 'Order status: ' . ucwords(str_replace('_', ' ', $order['fulfillment_status'])) . "\n"
        . 'Payment status: ' . ucfirst($order['payment_status']) . "\n"
        . $address_text;

    send_order_email_message(
        $order['email'],
        $order['customer_name'],
        $update['subject'] . ' (#' . $order_number . ')',
        $html,
        $text,
        $attachments
    );
}
