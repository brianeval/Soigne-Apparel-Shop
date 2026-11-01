<?php
require_once("../includes/bootstrap.php");

$errors = [];
$post_action = $_POST['action'] ?? '';
$inventory_view = $_POST['inventory_view'] ?? $_GET['inventory_view'] ?? 'stock';
if (!in_array($inventory_view, ['stock', 'history', 'restock'])) {
    $inventory_view = 'stock';
}

// Values of the restock form (the form is sent again every time a button is pressed)
$posted_supplier_id = $_POST['supplier_id'] ?? '';
$posted_variant_ids = array_values($_POST['variant_ids'] ?? []);
$posted_quantities = array_values($_POST['quantities'] ?? []);
$posted_unit_costs = array_values($_POST['unit_costs'] ?? []);
$variant_supplier_id = $_POST['variant_supplier_id'] ?? '';
if ($post_action == 'load_variants') {
    $variant_supplier_id = $posted_supplier_id;
}

// The "x" button removes one line of the restock form
if (isset($_POST['remove_line'])) {
    $remove_line_index = (int)$_POST['remove_line'];
    array_splice($posted_variant_ids, $remove_line_index, 1);
    array_splice($posted_quantities, $remove_line_index, 1);
    array_splice($posted_unit_costs, $remove_line_index, 1);
}

include(__DIR__ . '/inventory_create.php');

$suppliers = mysqli_fetch_all(mysqli_query($conn, "SELECT supplier_id, name FROM suppliers ORDER BY name"), MYSQLI_ASSOC);
$categories = mysqli_fetch_all(mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name"), MYSQLI_ASSOC);

// Variants of the supplier chosen in the restock form
$variant_options = [];
if ($variant_supplier_id != '') {
    $variant_options = mysqli_fetch_all(mysqli_query($conn,
        "SELECT v.variant_id, v.stock, v.size, pc.color, p.name AS product_name, s.name AS supplier_name
         FROM product_variants v
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         JOIN products p ON p.product_id = pc.product_id
         JOIN suppliers s ON s.supplier_id = p.supplier_id
         WHERE p.supplier_id = " . (int)$variant_supplier_id . "
         ORDER BY p.name, pc.color, v.size"
    ), MYSQLI_ASSOC);
}

// Filters of the current stock list
$search = trim($_GET['search'] ?? '');
$filter_department = $_GET['department'] ?? '';
if (!in_array($filter_department, ['men', 'women', 'kids'])) {
    $filter_department = '';
}
$filter_supplier = (int)($_GET['supplier_id'] ?? 0);
$filter_category = (int)($_GET['category_id'] ?? 0);
$filter_color = trim($_GET['color'] ?? '');
$filter_size = trim($_GET['size'] ?? '');

// Colors and sizes offered in the filters (only those of the chosen department)
$option_where = '';
if ($filter_department != '') {
    $option_where = "WHERE p.department = '$filter_department'";
}
$option_from = "FROM product_variants v
                JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                JOIN products p ON p.product_id = pc.product_id $option_where";
$visible_color_options = array_column(mysqli_fetch_all(mysqli_query($conn, "SELECT DISTINCT pc.color $option_from ORDER BY pc.color"), MYSQLI_ASSOC), 'color');
$visible_size_options = array_column(mysqli_fetch_all(mysqli_query($conn, "SELECT DISTINCT v.size $option_from ORDER BY v.size"), MYSQLI_ASSOC), 'size');

$inventory_where = "WHERE 1 = 1";
if ($search != '') {
    $inventory_where .= " AND p.name LIKE '%" . mysqli_real_escape_string($conn, $search) . "%'";
}
if ($filter_department != '') {
    $inventory_where .= " AND p.department = '$filter_department'";
}
if ($filter_supplier > 0) {
    $inventory_where .= " AND p.supplier_id = $filter_supplier";
}
if ($filter_category > 0) {
    $inventory_where .= " AND p.category_id = $filter_category";
}
if ($filter_color != '') {
    $inventory_where .= " AND pc.color = '" . mysqli_real_escape_string($conn, $filter_color) . "'";
}
if ($filter_size != '') {
    $inventory_where .= " AND v.size = '" . mysqli_real_escape_string($conn, $filter_size) . "'";
}

$inventory_rows = mysqli_fetch_all(mysqli_query($conn,
    "SELECT v.variant_id, v.stock, v.size, pc.color, p.name AS product_name, p.department,
            COALESCE(c.name, 'Uncategorized') AS category_name, s.name AS supplier_name
     FROM product_variants v
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id
     JOIN suppliers s ON s.supplier_id = p.supplier_id
     LEFT JOIN categories c ON c.category_id = p.category_id
     $inventory_where
     ORDER BY p.name, s.name, pc.color, v.size"
), MYSQLI_ASSOC);
$inventory_total = count($inventory_rows);

// Restock history (with the optional search)
$history_search = trim($_GET['history_search'] ?? '');
$history_where = '';
if ($history_search != '') {
    $history_text = mysqli_real_escape_string($conn, $history_search);
    $history_where = "WHERE r.batch_number LIKE '%$history_text%' OR s.name LIKE '%$history_text%'";
}
$history_rows = mysqli_fetch_all(mysqli_query($conn,
    "SELECT r.restock_id, r.batch_number, r.restock_date, s.name AS supplier_name,
            COUNT(ri.restock_item_id) AS line_count,
            COALESCE(SUM(ri.quantity), 0) AS total_units,
            COALESCE(SUM(ri.quantity * ri.unit_cost), 0) AS total_cost
     FROM restocks r
     JOIN suppliers s ON s.supplier_id = r.supplier_id
     LEFT JOIN restock_items ri ON ri.restock_id = r.restock_id
     $history_where
     GROUP BY r.restock_id, r.batch_number, r.restock_date, s.name
     ORDER BY r.restock_date DESC, r.restock_id DESC"
), MYSQLI_ASSOC);
$history_total = count($history_rows);

$admin_page_title = 'Manage Inventory';
$admin_active_page = 'inventory';
include("../includes/header.php");
?>
<div class="inventory-page">
  <section class="admin-page-heading">
    <div><p class="admin-eyebrow">STOCK MANAGEMENT</p><h1>Manage Inventory</h1><p>Review variant stock levels and record supplier deliveries.</p></div>
  </section>

  <?php include("../../includes/alert.php"); ?>
  <?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error); ?></li><?php } ?></ul></div><?php } ?>

  <div class="admin-panel-tabs inventory-tabs" role="radiogroup" aria-label="Inventory panels">
    <input type="radio" name="inventory_view" id="inventory-view-stock" value="stock" <?php echo $inventory_view === 'stock' ? 'checked' : ''; ?>>
    <label for="inventory-view-stock">Current Stock</label>
    <input type="radio" name="inventory_view" id="inventory-view-history" value="history" <?php echo $inventory_view === 'history' ? 'checked' : ''; ?>>
    <label for="inventory-view-history">Restock History</label>
    <input type="radio" name="inventory_view" id="inventory-view-restock" value="restock" <?php echo $inventory_view === 'restock' ? 'checked' : ''; ?>>
    <label for="inventory-view-restock">Restock Products</label>

  <section class="manage-panel inventory-stock-panel" id="inventory-stock">
    <div class="manage-panel-heading">
      <div><p class="admin-eyebrow">CURRENT STOCK</p><h2>Product variants <span class="manage-count"><?php echo number_format($inventory_total); ?></span></h2></div>
    </div>
    <form method="get" class="inventory-filters" aria-label="Filter inventory">
      <input type="hidden" name="inventory_view" value="stock">
      <label class="inventory-search">Product name<input type="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search product name"></label>
      <label>Department<select name="department"><option value="">All departments</option><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $department_value => $department_label) { ?><option value="<?php echo $department_value; ?>" <?php echo $filter_department === $department_value ? 'selected' : ''; ?>><?php echo $department_label; ?></option><?php } ?></select></label>
      <label>Brand<select name="supplier_id"><option value="">All brands</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo $filter_supplier === (int)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($supplier['name']); ?></option><?php } ?></select></label>
      <label>Category<select name="category_id"><option value="">All categories</option><?php foreach ($categories as $category) { ?><option value="<?php echo (int)$category['category_id']; ?>" <?php echo $filter_category === (int)$category['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option><?php } ?></select></label>
      <label>Color<select name="color"><option value="">All colors</option><?php foreach ($visible_color_options as $color) { ?><option value="<?php echo htmlspecialchars($color); ?>" <?php echo $filter_color === $color ? 'selected' : ''; ?>><?php echo htmlspecialchars($color); ?></option><?php } ?></select></label>
      <label>Size<select name="size"><option value="">All sizes</option><?php foreach ($visible_size_options as $size) { ?><option value="<?php echo htmlspecialchars($size); ?>" <?php echo $filter_size === $size ? 'selected' : ''; ?>><?php echo htmlspecialchars($size); ?></option><?php } ?></select></label>
      <input type="hidden" name="history_search" value="<?php echo htmlspecialchars($history_search); ?>">
      <button class="admin-button" type="submit">Apply filters</button>
      <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-inventory/">Clear</a>
    </form>
    <?php if ($inventory_rows) { ?>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Product</th><th>Department</th><th>Brand / Supplier</th><th>Category</th><th>Color</th><th>Size</th><th>Stock quantity</th></tr></thead>
        <tbody><?php foreach ($inventory_rows as $inventory_row) { ?>
          <tr>
            <td class="order-number"><?php echo htmlspecialchars($inventory_row['product_name']); ?></td>
            <td><?php echo htmlspecialchars(ucfirst($inventory_row['department'])); ?></td>
            <td><?php echo htmlspecialchars($inventory_row['supplier_name']); ?></td>
            <td><?php echo htmlspecialchars($inventory_row['category_name']); ?></td>
            <td><?php echo htmlspecialchars($inventory_row['color']); ?></td>
            <td><?php echo htmlspecialchars($inventory_row['size']); ?></td>
            <td><?php echo number_format((int)$inventory_row['stock']); ?></td>
          </tr>
        <?php } ?></tbody>
      </table></div>
    <?php } else { ?><p class="manage-empty">No product variants match these filters.</p><?php } ?>
  </section>

  <section class="manage-panel manage-list-panel inventory-history-panel">
    <div class="manage-panel-heading">
      <div><p class="admin-eyebrow">SUPPLIER DELIVERIES</p><h2>Restock History <span class="manage-count"><?php echo number_format($history_total); ?></span></h2></div>
      <form method="get" class="inventory-history-search">
        <input type="hidden" name="inventory_view" value="history">
        <label class="visually-hidden" for="history-search">Search batch number or supplier</label>
        <input id="history-search" type="search" name="history_search" value="<?php echo htmlspecialchars($history_search); ?>" placeholder="Search batch or supplier">
        <button class="admin-button admin-button-light" type="submit">Search</button>
      </form>
    </div>
    <?php if ($history_rows) { ?>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Batch number</th><th>Date</th><th>Supplier</th><th>Variant lines</th><th>Total units</th><th>Total cost</th><th>Details</th></tr></thead>
        <tbody><?php foreach ($history_rows as $restock_row) { ?>
          <tr>
            <td class="order-number"><?php echo htmlspecialchars($restock_row['batch_number'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($restock_row['restock_date']); ?></td>
            <td><?php echo htmlspecialchars($restock_row['supplier_name']); ?></td>
            <td><?php echo number_format((int)$restock_row['line_count']); ?></td>
            <td><?php echo number_format((int)$restock_row['total_units']); ?></td>
            <td><?php echo '₱' . number_format($restock_row['total_cost'], 2); ?></td>
            <td><a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-inventory/view.php?id=<?php echo (int)$restock_row['restock_id']; ?>">View details</a></td>
          </tr>
        <?php } ?></tbody>
      </table></div>
    <?php } else { ?><p class="manage-empty"><?php echo $history_search !== '' ? 'No restocks match this search.' : 'No restocks have been recorded.'; ?></p><?php } ?>
  </section>

  <section class="manage-panel inventory-restock-panel" aria-labelledby="restock-panel-title">
    <div class="manage-panel-heading">
      <div><p class="admin-eyebrow">SUPPLIER DELIVERY</p><h2 id="restock-panel-title">Restock Products</h2></div>
    </div>
    <form id="inventory-restock-form" method="post" class="manage-form inventory-restock-form" novalidate>
    <input type="hidden" name="inventory_view" value="restock">
    <input type="hidden" name="variant_supplier_id" value="<?php echo htmlspecialchars($variant_supplier_id); ?>">
    <div class="inventory-restock-supplier">
      <label>Supplier<select name="supplier_id"><option value="">Choose a supplier</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo $posted_supplier_id === (string)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($supplier['name']); ?></option><?php } ?></select></label>
      <button class="manage-small-button inventory-load-variants" type="submit" name="action" value="load_variants">Load supplier variants</button>
    </div>
    <div class="inventory-restock-lines">
      <?php if ($variant_supplier_id !== '' && !$variant_options) { ?>
        <p class="manage-secondary">This supplier has no product variants available to restock.</p>
      <?php } ?>
      <?php
      $line_count = max(1, min(50, count($posted_variant_ids) + ($post_action === 'add_line' ? 1 : 0)));
      for ($line_index = 0; $line_index < $line_count; $line_index++) {
        $selected_variant_id = is_string($posted_variant_ids[$line_index] ?? null) ? $posted_variant_ids[$line_index] : '';
        $line_quantity = is_string($posted_quantities[$line_index] ?? null) ? $posted_quantities[$line_index] : '1';
        $line_cost = is_string($posted_unit_costs[$line_index] ?? null) ? $posted_unit_costs[$line_index] : '';
      ?>
      <div class="inventory-restock-line">
        <label>Product variant<select name="variant_ids[]" <?php echo $variant_supplier_id === '' ? 'disabled' : ''; ?>>
          <option value="">Choose a variant</option>
          <?php foreach ($variant_options as $variant_option) { ?>
            <option value="<?php echo (int)$variant_option['variant_id']; ?>" <?php echo $selected_variant_id === (string)$variant_option['variant_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($variant_option['supplier_name'] . ' · ' . $variant_option['product_name'] . ' · ' . $variant_option['color'] . ' · ' . $variant_option['size'] . ' · Current stock: ' . number_format((int)$variant_option['stock'])); ?></option>
          <?php } ?>
        </select></label>
        <label>Quantity received<input type="number" name="quantities[]" value="<?php echo htmlspecialchars($line_quantity); ?>"></label>
        <label>Unit purchase cost (₱)<input type="text" inputmode="decimal" name="unit_costs[]" value="<?php echo htmlspecialchars($line_cost); ?>"></label>
        <button class="inventory-restock-line-remove" type="submit" name="remove_line" value="<?php echo $line_index; ?>" aria-label="Remove product variant line <?php echo $line_index + 1; ?>" title="Remove product variant">×</button>
      </div>
      <?php } ?>
    </div>
    <button class="manage-small-button" type="submit" name="action" value="add_line" <?php echo $variant_supplier_id === '' ? 'disabled' : ''; ?>>Add another product variant</button>
    <label class="inventory-restock-confirm">
      <input type="checkbox" name="confirmed" value="1" <?php echo ($_POST['confirmed'] ?? '') === '1' ? 'checked' : ''; ?>>
      I have reviewed the selected variant, received quantity, and unit cost.
    </label>
    <div class="manage-form-buttons">
      <button class="admin-button" type="submit" name="action" value="create">Confirm and save restock</button>
    </div>
    </form>
  </section>
</div>
<?php include("../includes/footer.php"); ?>
