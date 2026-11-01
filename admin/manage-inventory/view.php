<?php
require_once("../includes/bootstrap.php");

$restock_id = (int)($_GET['id'] ?? 0);

$restock_result = mysqli_query($conn,
    "SELECT r.restock_id, r.batch_number, r.restock_date, s.name AS supplier_name
     FROM restocks r
     JOIN suppliers s ON s.supplier_id = r.supplier_id
     WHERE r.restock_id = $restock_id"
);
$restock = mysqli_fetch_assoc($restock_result);
if (!$restock) {
    http_response_code(404);
    exit('Restock not found.');
}

$items_result = mysqli_query($conn,
    "SELECT p.name AS product_name, COALESCE(c.name, 'Uncategorized') AS category_name,
            pc.color, v.size, ri.quantity, ri.unit_cost,
            ri.quantity * ri.unit_cost AS line_total
     FROM restock_items ri
     JOIN product_variants v ON v.variant_id = ri.variant_id
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id
     LEFT JOIN categories c ON c.category_id = p.category_id
     WHERE ri.restock_id = $restock_id
     ORDER BY p.name, pc.color, v.size"
);
$items = mysqli_fetch_all($items_result, MYSQLI_ASSOC);
$total_cost = 0.0;
$total_units = 0;
foreach ($items as $item) {
    $total_cost += (float)$item['line_total'];
    $total_units += (int)$item['quantity'];
}

$admin_page_title = 'Restock ' . ($restock['batch_number'] ?? '');
$admin_active_page = 'inventory';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">RESTOCK HISTORY</p><h1><?php echo htmlspecialchars($restock['batch_number'] ?? 'Restock details'); ?></h1><p><?php echo htmlspecialchars($restock['supplier_name']); ?> · <?php echo htmlspecialchars($restock['restock_date']); ?></p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-inventory/">Back to inventory</a>
</section>
<section class="manage-panel inventory-detail-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">DELIVERY CONTENTS</p><h2>Received variants</h2></div></div>
  <?php if ($items) { ?>
    <div class="admin-table-wrap"><table class="admin-table">
      <thead><tr><th>Product</th><th>Category</th><th>Color</th><th>Size</th><th>Quantity received</th><th>Unit cost</th><th>Line total</th></tr></thead>
      <tbody><?php foreach ($items as $item) { ?>
        <tr>
          <td class="order-number"><?php echo htmlspecialchars($item['product_name']); ?></td>
          <td><?php echo htmlspecialchars($item['category_name']); ?></td>
          <td><?php echo htmlspecialchars($item['color']); ?></td>
          <td><?php echo htmlspecialchars($item['size']); ?></td>
          <td><?php echo number_format((int)$item['quantity']); ?></td>
          <td><?php echo '₱' . number_format($item['unit_cost'], 2); ?></td>
          <td><?php echo '₱' . number_format($item['line_total'], 2); ?></td>
        </tr>
      <?php } ?></tbody>
      <tfoot><tr><th colspan="4">Totals</th><th><?php echo number_format($total_units); ?></th><th></th><th><?php echo '₱' . number_format($total_cost, 2); ?></th></tr></tfoot>
    </table></div>
  <?php } else { ?><p class="manage-empty">This restock has no recorded variant lines.</p><?php } ?>
</section>
<?php include("../includes/footer.php"); ?>
