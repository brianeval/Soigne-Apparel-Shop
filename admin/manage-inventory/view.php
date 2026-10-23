<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$restock_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($restock_id === false || $restock_id < 1) {
    http_response_code(400);
    exit('Choose a valid restock.');
}

$restock_stmt = mysqli_prepare($conn,
    'SELECT r.restock_id, r.batch_number, r.restock_date, s.name AS supplier_name
     FROM restocks r
     JOIN suppliers s ON s.supplier_id = r.supplier_id
     WHERE r.restock_id = ?'
);
mysqli_stmt_bind_param($restock_stmt, 'i', $restock_id);
mysqli_stmt_execute($restock_stmt);
$restock = mysqli_fetch_assoc(mysqli_stmt_get_result($restock_stmt));
if (!$restock) {
    http_response_code(404);
    exit('Restock not found.');
}

$items_stmt = mysqli_prepare($conn,
    'SELECT p.name AS product_name, COALESCE(c.name, p.category, \'Uncategorized\') AS category_name,
            pc.color, v.size, ri.quantity, ri.unit_cost,
            ri.quantity * ri.unit_cost AS line_total
     FROM restock_items ri
     JOIN product_variants v ON v.variant_id = ri.variant_id
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id
     LEFT JOIN categories c ON c.category_id = p.category_id
     WHERE ri.restock_id = ?
     ORDER BY p.name, pc.color, v.size'
);
mysqli_stmt_bind_param($items_stmt, 'i', $restock_id);
mysqli_stmt_execute($items_stmt);
$items = mysqli_fetch_all(mysqli_stmt_get_result($items_stmt), MYSQLI_ASSOC);
$total_cost = 0.0;
$total_units = 0;
foreach ($items as $item) {
    $total_cost += (float)$item['line_total'];
    $total_units += (int)$item['quantity'];
}

$admin_page_title = 'Restock ' . ($restock['batch_number'] ?? '');
$admin_active_page = 'inventory';
include __DIR__ . '/../includes/header.php';
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">RESTOCK HISTORY</p><h1><?php echo admin_escape($restock['batch_number'] ?? 'Restock details'); ?></h1><p><?php echo admin_escape($restock['supplier_name']); ?> · <?php echo admin_escape($restock['restock_date']); ?></p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-inventory/">Back to inventory</a>
</section>
<section class="manage-panel inventory-detail-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">DELIVERY CONTENTS</p><h2>Received variants</h2></div></div>
  <?php if ($items) { ?>
    <div class="admin-table-wrap"><table class="admin-table">
      <thead><tr><th>Product</th><th>Category</th><th>Color</th><th>Size</th><th>Quantity received</th><th>Unit cost</th><th>Line total</th></tr></thead>
      <tbody><?php foreach ($items as $item) { ?>
        <tr>
          <td class="order-number"><?php echo admin_escape($item['product_name']); ?></td>
          <td><?php echo admin_escape($item['category_name']); ?></td>
          <td><?php echo admin_escape($item['color']); ?></td>
          <td><?php echo admin_escape($item['size']); ?></td>
          <td><?php echo number_format((int)$item['quantity']); ?></td>
          <td><?php echo peso((float)$item['unit_cost']); ?></td>
          <td><?php echo peso((float)$item['line_total']); ?></td>
        </tr>
      <?php } ?></tbody>
      <tfoot><tr><th colspan="4">Totals</th><th><?php echo number_format($total_units); ?></th><th></th><th><?php echo peso($total_cost); ?></th></tr></tfoot>
    </table></div>
  <?php } else { ?><p class="manage-empty">This restock has no recorded variant lines.</p><?php } ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
