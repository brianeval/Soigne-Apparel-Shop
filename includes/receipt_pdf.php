<?php
// Builds the PDF receipt of a delivered and paid order.
// Set $receipt_order before including this file. It needs these keys:
//   order_id, customer_name, email, shipping_address, created_at and items
//   (each item has product_name, color, size, quantity and price)
// Result: $receipt_pdf holds the PDF file content.
use Dompdf\Dompdf;

require_once(APP_ROOT . '/vendor/autoload.php');

$receipt_rows = '';
$receipt_total = 0;
foreach ($receipt_order['items'] as $receipt_item) {
    $line_total = $receipt_item['quantity'] * $receipt_item['price'];
    $receipt_total += $line_total;
    $description = $receipt_item['product_name'] . ' | ' . $receipt_item['color'] . ' | Size ' . $receipt_item['size'];
    $receipt_rows .= '<tr><td>' . htmlspecialchars($description) . '</td>'
        . '<td class="number">' . (int)$receipt_item['quantity'] . '</td>'
        . '<td class="number">&#8369;' . number_format($receipt_item['price'], 2) . '</td>'
        . '<td class="number">&#8369;' . number_format($line_total, 2) . '</td></tr>';
}

$receipt_html = '<!doctype html><html lang="en"><head><meta charset="UTF-8"><style>
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
    table.items{width:100%;border-collapse:collapse}
    .items th{padding:9px 8px;background:#f2f1ee;border-top:1px solid #d8d5ce;border-bottom:1px solid #d8d5ce;font-size:9px;text-transform:uppercase;text-align:left}
    .items td{padding:11px 8px;border-bottom:1px solid #e5e3de}
    .items .number{text-align:right;white-space:nowrap}
    .summary{width:100%;margin-top:16px}
    .summary td{padding:5px 8px;text-align:right;border-top:1px solid #292821;font-size:14px;font-weight:bold}
    .footer{margin-top:28px;padding-top:12px;border-top:1px solid #d8d5ce;color:#777;text-align:center;font-size:9px}
    </style></head><body><div class="receipt">
    <table class="masthead"><tr><td><div class="brand">SOIGNÉ</div><div class="store-note">APPAREL SHOP</div></td>
    <td class="receipt-title"><strong>RECEIPT</strong><div>Order #' . (int)$receipt_order['order_id'] . '</div></td></tr></table>
    <table class="meta"><tr><td><div class="label">Receipt date</div>' . htmlspecialchars($receipt_order['created_at']) . '</td>
    <td><div class="label">Payment status</div>Paid</td></tr>
    <tr><td><div class="label">Billed to</div>' . htmlspecialchars($receipt_order['customer_name']) . '<br>' . htmlspecialchars($receipt_order['email']) . '</td>
    <td><div class="label">Delivered to</div>' . nl2br(htmlspecialchars($receipt_order['shipping_address'])) . '</td></tr></table>
    <table class="items"><thead><tr><th>Item</th><th class="number">Qty</th><th class="number">Unit price</th><th class="number">Amount</th></tr></thead>
    <tbody>' . $receipt_rows . '</tbody></table>
    <table class="summary"><tr><td>Total &#8369;' . number_format($receipt_total, 2) . '</td></tr></table>
    <div class="footer">Thank you for shopping with Soigné.</div></div></body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($receipt_html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$receipt_pdf = $dompdf->output();
