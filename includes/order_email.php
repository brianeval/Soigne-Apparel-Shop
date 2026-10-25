<?php
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

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

    $to_pdf_text = static function (string $text): string {
        if (!function_exists('iconv')) {
            throw new RuntimeException('The iconv extension is required to generate order receipts.');
        }
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($converted === false) {
            throw new RuntimeException('Order receipt text could not be converted for PDF output.');
        }
        return str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", ' ', ' '], $converted);
    };
    $money = static function (float $amount): string {
        return 'PHP ' . number_format($amount, 2);
    };

    $order_id = (int)($order['order_id'] ?? 0);
    $items = $order['items'];
    $lines = [
        'SOIGNE APPAREL SHOP',
        'ORDER RECEIPT',
        '',
        'Order number: #' . $order_id,
        'Date: ' . (string)($order['created_at'] ?? ''),
        'Customer: ' . (string)($order['customer_name'] ?? ''),
        'Email: ' . (string)($order['email'] ?? ''),
        'Shipping address: ' . (string)($order['shipping_address'] ?? ''),
        'Payment status: Paid',
        '',
        'ORDER ITEMS',
        str_repeat('-', 76),
    ];
    $subtotal = 0.0;
    foreach ($items as $item) {
        $quantity = (int)($item['quantity'] ?? 0);
        $unit_price = (float)($item['price'] ?? 0);
        if ($quantity < 1 || $unit_price < 0) {
            throw new RuntimeException('Order receipt contains invalid item details.');
        }
        $line_total = $unit_price * $quantity;
        $subtotal += $line_total;
        $description = trim(
            (string)($item['product_name'] ?? $item['name'] ?? '')
            . ' | ' . (string)($item['color'] ?? '')
            . ' | Size ' . (string)($item['size'] ?? '')
        );
        foreach (explode("\n", wordwrap($description, 76, "\n", true)) as $description_line) {
            $lines[] = $description_line;
        }
        $lines[] = 'Qty: ' . $quantity . ' | Unit price: ' . $money($unit_price)
            . ' | Line subtotal: ' . $money($line_total);
        $lines[] = '';
    }
    $lines[] = str_repeat('-', 76);
    $lines[] = 'Subtotal: ' . $money($subtotal);
    $lines[] = 'Grand total: ' . $money($subtotal);

    $wrapped_lines = [];
    foreach ($lines as $line) {
        foreach (explode("\n", wordwrap($line, 86, "\n", true)) as $wrapped_line) {
            $wrapped_lines[] = $wrapped_line;
        }
    }
    $page_lines = array_chunk($wrapped_lines, 48);
    $objects = [];
    $page_references = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

    foreach ($page_lines as $page_index => $current_lines) {
        $page_object_id = 4 + ($page_index * 2);
        $content_object_id = $page_object_id + 1;
        $page_references[] = $page_object_id . ' 0 R';
        $content = '';
        foreach ($current_lines as $line_index => $line) {
            $y = 748 - ($line_index * 15);
            $font_size = $page_index === 0 && $line_index < 2 ? 16 : 10;
            $content .= 'BT /F1 ' . $font_size . ' Tf 48 ' . $y . ' Td (' . $to_pdf_text($line) . ") Tj ET\n";
        }
        $objects[$page_object_id] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
            . '/Resources << /Font << /F1 3 0 R >> >> /Contents ' . $content_object_id . ' 0 R >>';
        $objects[$content_object_id] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
    }

    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $page_references) . '] /Count ' . count($page_references) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $object_id => $object_body) {
        $offsets[$object_id] = strlen($pdf);
        $pdf .= $object_id . " 0 obj\n" . $object_body . "\nendobj\n";
    }

    $xref_offset = strlen($pdf);
    $object_count = max(array_keys($objects)) + 1;
    $pdf .= "xref\n0 " . $object_count . "\n0000000000 65535 f \n";
    for ($object_id = 1; $object_id < $object_count; $object_id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$object_id] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . $object_count . " /Root 1 0 R >>\nstartxref\n"
        . $xref_offset . "\n%%EOF";

    return $pdf;
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
