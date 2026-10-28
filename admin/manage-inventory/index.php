<?php
require_once("../includes/bootstrap.php");

const INVENTORY_MAX_STOCK = 2147483647;
const INVENTORY_MAX_TOTAL_CENTS = 9007199254740991;

function inventory_bind_params(mysqli_stmt $statement, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }

    $bindings = [$types];
    foreach ($params as &$param) {
        $bindings[] = &$param;
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$statement], $bindings));
}

function inventory_cost_to_cents($value): ?int
{
    if (!is_string($value) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) {
        return null;
    }

    $parts = explode('.', $value, 2);
    $fraction = str_pad($parts[1] ?? '', 2, '0');
    return ((int)$parts[0] * 100) + (int)$fraction;
}

function inventory_sorted_option_names(array $option_set): array
{
    $option_names = array_keys($option_set);
    natcasesort($option_names);
    return array_values($option_names);
}

$errors = [];
$post_action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$requested_inventory_view = is_string($_POST['inventory_view'] ?? null)
    ? $_POST['inventory_view']
    : (is_string($_GET['inventory_view'] ?? null) ? $_GET['inventory_view'] : '');
$inventory_view = in_array($requested_inventory_view, ['stock', 'history', 'restock'], true)
    ? $requested_inventory_view
    : (in_array($post_action, ['load_variants', 'add_line', 'create'], true) || isset($_POST['remove_line']) ? 'restock' : 'stock');
$posted_supplier_id = is_string($_POST['supplier_id'] ?? null) ? $_POST['supplier_id'] : '';
$posted_variant_ids = is_array($_POST['variant_ids'] ?? null) ? array_values($_POST['variant_ids']) : [];
$posted_quantities = is_array($_POST['quantities'] ?? null) ? array_values($_POST['quantities']) : [];
$posted_unit_costs = is_array($_POST['unit_costs'] ?? null) ? array_values($_POST['unit_costs']) : [];
$variant_supplier_id = is_string($_POST['variant_supplier_id'] ?? null) ? $_POST['variant_supplier_id'] : '';

if (!isset($_SESSION['inventory_restock_tokens']) || !is_array($_SESSION['inventory_restock_tokens'])) {
    $_SESSION['inventory_restock_tokens'] = [];
}
foreach ($_SESSION['inventory_restock_tokens'] as $token => $issued_at) {
    if (!is_int($issued_at) || $issued_at < time() - 3600) {
        unset($_SESSION['inventory_restock_tokens'][$token]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = $_POST['supplier_id'];

    mysqli_begin_transaction($conn);

    // 1. Create the restock record
    $restock_date = date('Y-m-d');
    $restock_stmt = mysqli_prepare($conn,
        'INSERT INTO restocks (batch_number, supplier_id, restock_date) VALUES (NULL, ?, ?)'
    );
    mysqli_stmt_bind_param($restock_stmt, 'is', $supplier_id, $restock_date);
    mysqli_stmt_execute($restock_stmt);
    $restock_id = mysqli_insert_id($conn);

    // 2. Generate the batch number
    $batch_number = sprintf('RS-%s-%05d', substr($restock_date, 0, 4), $restock_id);
    $batch_stmt = mysqli_prepare($conn, 'UPDATE restocks SET batch_number = ? WHERE restock_id = ?');
    mysqli_stmt_bind_param($batch_stmt, 'si', $batch_number, $restock_id);
    mysqli_stmt_execute($batch_stmt);

    // 3. Save each line and add to stock
    $item_stmt = mysqli_prepare($conn,
        'INSERT INTO restock_items (restock_id, variant_id, quantity, unit_cost) VALUES (?, ?, ?, ?)'
    );
    $stock_stmt = mysqli_prepare($conn,
        'UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?'
    );

    foreach ($posted_variant_ids as $index => $variant_id) {
        $quantity  = $posted_quantities[$index];
        $unit_cost = $posted_unit_costs[$index];

        mysqli_stmt_bind_param($item_stmt, 'iiis', $restock_id, $variant_id, $quantity, $unit_cost);
        mysqli_stmt_execute($item_stmt);

        mysqli_stmt_bind_param($stock_stmt, 'ii', $quantity, $variant_id);
        mysqli_stmt_execute($stock_stmt);
    }

    mysqli_commit($conn);
    admin_set_flash('success', 'Restock ' . $batch_number . ' saved and stock updated.');
    header('Location: ' . BASE_URL . 'admin/manage-inventory/');
    exit;
}

$restock_token = is_string($_POST['restock_token'] ?? null)
    && isset($_SESSION['inventory_restock_tokens'][$_POST['restock_token']])
        ? $_POST['restock_token']
        : bin2hex(random_bytes(32));
$_SESSION['inventory_restock_tokens'][$restock_token] = time();

$suppliers_result = mysqli_query($conn, 'SELECT supplier_id, name FROM suppliers ORDER BY name');
$suppliers = mysqli_fetch_all($suppliers_result, MYSQLI_ASSOC);
$categories_result = mysqli_query($conn, 'SELECT category_id, name FROM categories ORDER BY name');
$categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);

$variant_options_result = mysqli_query($conn,
    'SELECT v.variant_id, v.stock, v.size, pc.color, p.name AS product_name, p.supplier_id, p.department,
            s.name AS supplier_name
     FROM product_variants v
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id
     JOIN suppliers s ON s.supplier_id = p.supplier_id
     ORDER BY s.name, p.name, pc.color, v.size'
);
$variant_options = mysqli_fetch_all($variant_options_result, MYSQLI_ASSOC);
$supplier_variant_ids = [];
$color_options_by_department = ['men' => [], 'women' => [], 'kids' => []];
$size_options_by_department = ['men' => [], 'women' => [], 'kids' => []];
foreach ($variant_options as $variant_option) {
    $supplier_variant_ids[(int)$variant_option['supplier_id']] = true;
    $color_options_by_department[$variant_option['department']][$variant_option['color']] = true;
    $size_options_by_department[$variant_option['department']][$variant_option['size']] = true;
}
$all_color_options = array_fill_keys(array_merge(
    array_keys($color_options_by_department['men']),
    array_keys($color_options_by_department['women']),
    array_keys($color_options_by_department['kids'])
), true);
$color_options_by_department = array_map('inventory_sorted_option_names', $color_options_by_department);
$all_color_options = inventory_sorted_option_names($all_color_options);
$all_size_options = array_fill_keys(array_merge(
    array_keys($size_options_by_department['men']),
    array_keys($size_options_by_department['women']),
    array_keys($size_options_by_department['kids'])
), true);
$size_options_by_department = array_map('inventory_sorted_option_names', $size_options_by_department);
$all_size_options = inventory_sorted_option_names($all_size_options);

$search = trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : '');
$search = mb_substr($search, 0, 150);
$filter_department = $_GET['department'] ?? '';
if (!is_string($filter_department) || !in_array($filter_department, ['men', 'women', 'kids'], true)) {
    $filter_department = '';
}
$filter_supplier = filter_var($_GET['supplier_id'] ?? null, FILTER_VALIDATE_INT);
$filter_supplier = $filter_supplier !== false && $filter_supplier > 0 ? $filter_supplier : null;
$filter_category = filter_var($_GET['category_id'] ?? null, FILTER_VALIDATE_INT);
$filter_category = $filter_category !== false && $filter_category > 0 ? $filter_category : null;
$filter_color = mb_substr(trim(is_string($_GET['color'] ?? null) ? $_GET['color'] : ''), 0, 80);
$filter_size = mb_substr(trim(is_string($_GET['size'] ?? null) ? $_GET['size'] : ''), 0, 20);
$visible_size_options = $filter_department === ''
    ? $all_size_options
    : $size_options_by_department[$filter_department];
$visible_color_options = $filter_department === ''
    ? $all_color_options
    : $color_options_by_department[$filter_department];

$inventory_conditions = [];
$inventory_types = '';
$inventory_params = [];
if ($search !== '') {
    $inventory_conditions[] = 'p.name LIKE ?';
    $inventory_types .= 's';
    $inventory_params[] = '%' . $search . '%';
}
if ($filter_department !== '') {
    $inventory_conditions[] = 'p.department = ?';
    $inventory_types .= 's';
    $inventory_params[] = $filter_department;
}
if ($filter_supplier !== null) {
    $inventory_conditions[] = 'p.supplier_id = ?';
    $inventory_types .= 'i';
    $inventory_params[] = $filter_supplier;
}
if ($filter_category !== null) {
    $inventory_conditions[] = 'p.category_id = ?';
    $inventory_types .= 'i';
    $inventory_params[] = $filter_category;
}
if ($filter_color !== '') {
    $inventory_conditions[] = 'pc.color = ?';
    $inventory_types .= 's';
    $inventory_params[] = $filter_color;
}
if ($filter_size !== '') {
    $inventory_conditions[] = 'v.size = ?';
    $inventory_types .= 's';
    $inventory_params[] = $filter_size;
}
$inventory_where = $inventory_conditions ? ' WHERE ' . implode(' AND ', $inventory_conditions) : '';

$inventory_count_stmt = mysqli_prepare($conn,
    'SELECT COUNT(*)
     FROM product_variants v
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id' . $inventory_where
);
inventory_bind_params($inventory_count_stmt, $inventory_types, $inventory_params);
mysqli_stmt_execute($inventory_count_stmt);
$inventory_total = (int)mysqli_fetch_row(mysqli_stmt_get_result($inventory_count_stmt))[0];
$inventory_pagination = admin_pagination_page($inventory_total, 15, 'inventory_page');
$inventory_limit = $inventory_pagination['page_size'];
$inventory_offset = $inventory_pagination['offset'];
$inventory_list_params = $inventory_params;
$inventory_list_params[] = $inventory_limit;
$inventory_list_params[] = $inventory_offset;
$inventory_list_types = $inventory_types . 'ii';
$inventory_stmt = mysqli_prepare($conn,
    'SELECT v.variant_id, v.stock, v.size, pc.color, p.name AS product_name, p.department,
            COALESCE(c.name, \'Uncategorized\') AS category_name, s.name AS supplier_name
     FROM product_variants v
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id
     JOIN suppliers s ON s.supplier_id = p.supplier_id
     LEFT JOIN categories c ON c.category_id = p.category_id' . $inventory_where . '
     ORDER BY p.name, s.name, pc.color, v.size
     LIMIT ? OFFSET ?'
);
inventory_bind_params($inventory_stmt, $inventory_list_types, $inventory_list_params);
mysqli_stmt_execute($inventory_stmt);
$inventory_rows = mysqli_fetch_all(mysqli_stmt_get_result($inventory_stmt), MYSQLI_ASSOC);

$summary_result = mysqli_query($conn,
    'SELECT
        (SELECT COALESCE(SUM(stock), 0) FROM product_variants) AS total_stock,
        (SELECT COALESCE(SUM(ri.quantity * ri.unit_cost), 0)
         FROM restock_items ri
         JOIN restocks r ON r.restock_id = ri.restock_id) AS restock_expenses'
);
$summary = mysqli_fetch_assoc($summary_result);

$history_search = trim(is_string($_GET['history_search'] ?? null) ? $_GET['history_search'] : '');
$history_search = mb_substr($history_search, 0, 100);
$history_where = $history_search === '' ? '' : ' WHERE r.batch_number LIKE ? OR s.name LIKE ?';
$history_params = $history_search === '' ? [] : ['%' . $history_search . '%', '%' . $history_search . '%'];
$history_types = $history_search === '' ? '' : 'ss';
$history_count_stmt = mysqli_prepare($conn,
    'SELECT COUNT(*) FROM restocks r JOIN suppliers s ON s.supplier_id = r.supplier_id' . $history_where
);
inventory_bind_params($history_count_stmt, $history_types, $history_params);
mysqli_stmt_execute($history_count_stmt);
$history_total = (int)mysqli_fetch_row(mysqli_stmt_get_result($history_count_stmt))[0];
$history_pagination = admin_pagination_page($history_total, 10, 'history_page');
$history_list_params = $history_params;
$history_list_params[] = $history_pagination['page_size'];
$history_list_params[] = $history_pagination['offset'];
$history_stmt = mysqli_prepare($conn,
    'SELECT r.restock_id, r.batch_number, r.restock_date, s.name AS supplier_name,
            COUNT(ri.restock_item_id) AS line_count,
            COALESCE(SUM(ri.quantity), 0) AS total_units,
            COALESCE(SUM(ri.quantity * ri.unit_cost), 0) AS total_cost
     FROM restocks r
     JOIN suppliers s ON s.supplier_id = r.supplier_id
     LEFT JOIN restock_items ri ON ri.restock_id = r.restock_id' . $history_where . '
     GROUP BY r.restock_id, r.batch_number, r.restock_date, s.name
     ORDER BY r.restock_date DESC, r.restock_id DESC
     LIMIT ? OFFSET ?'
);
inventory_bind_params($history_stmt, $history_types . 'ii', $history_list_params);
mysqli_stmt_execute($history_stmt);
$history_rows = mysqli_fetch_all(mysqli_stmt_get_result($history_stmt), MYSQLI_ASSOC);

$flash = admin_take_flash();
$admin_page_title = 'Manage Inventory';
$admin_active_page = 'inventory';
include("../includes/header.php");
?>
<div class="inventory-page">
  <section class="admin-page-heading">
    <div><p class="admin-eyebrow">STOCK MANAGEMENT</p><h1>Manage Inventory</h1><p>Review variant stock levels and record supplier deliveries.</p></div>
  </section>

  <?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
  <?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>

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
      <label class="inventory-search">Product name<input type="search" name="search" value="<?php echo admin_escape($search); ?>" placeholder="Search product name"></label>
      <label>Department<select name="department"><option value="">All departments</option><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $department_value => $department_label) { ?><option value="<?php echo $department_value; ?>" <?php echo $filter_department === $department_value ? 'selected' : ''; ?>><?php echo $department_label; ?></option><?php } ?></select></label>
      <label>Brand<select name="supplier_id"><option value="">All brands</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo $filter_supplier === (int)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
      <label>Category<select name="category_id"><option value="">All categories</option><?php foreach ($categories as $category) { ?><option value="<?php echo (int)$category['category_id']; ?>" <?php echo $filter_category === (int)$category['category_id'] ? 'selected' : ''; ?>><?php echo admin_escape($category['name']); ?></option><?php } ?></select></label>
      <label>Color<select name="color"><option value="">All colors</option><?php foreach ($visible_color_options as $color) { ?><option value="<?php echo admin_escape($color); ?>" <?php echo $filter_color === $color ? 'selected' : ''; ?>><?php echo admin_escape($color); ?></option><?php } ?></select></label>
      <label>Size<select name="size"><option value="">All sizes</option><?php foreach ($visible_size_options as $size) { ?><option value="<?php echo admin_escape($size); ?>" <?php echo $filter_size === $size ? 'selected' : ''; ?>><?php echo admin_escape($size); ?></option><?php } ?></select></label>
      <input type="hidden" name="history_search" value="<?php echo admin_escape($history_search); ?>">
      <button class="admin-button" type="submit">Apply filters</button>
      <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-inventory/">Clear</a>
    </form>
    <?php if ($inventory_rows) { ?>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Product</th><th>Department</th><th>Brand / Supplier</th><th>Category</th><th>Color</th><th>Size</th><th>Stock quantity</th></tr></thead>
        <tbody><?php foreach ($inventory_rows as $inventory_row) { ?>
          <tr>
            <td class="order-number"><?php echo admin_escape($inventory_row['product_name']); ?></td>
            <td><?php echo admin_escape(ucfirst($inventory_row['department'])); ?></td>
            <td><?php echo admin_escape($inventory_row['supplier_name']); ?></td>
            <td><?php echo admin_escape($inventory_row['category_name']); ?></td>
            <td><?php echo admin_escape($inventory_row['color']); ?></td>
            <td><?php echo admin_escape($inventory_row['size']); ?></td>
            <td><?php echo number_format((int)$inventory_row['stock']); ?></td>
          </tr>
        <?php } ?></tbody>
      </table></div>
      <?php admin_render_pagination($inventory_pagination['page'], $inventory_pagination['total_pages'], 'inventory_page', ['inventory_view' => 'stock']); ?>
    <?php } else { ?><p class="manage-empty">No product variants match these filters.</p><?php } ?>
  </section>

  <section class="manage-panel manage-list-panel inventory-history-panel">
    <div class="manage-panel-heading">
      <div><p class="admin-eyebrow">SUPPLIER DELIVERIES</p><h2>Restock History <span class="manage-count"><?php echo number_format($history_total); ?></span></h2></div>
      <form method="get" class="inventory-history-search">
        <input type="hidden" name="inventory_view" value="history">
        <label class="visually-hidden" for="history-search">Search batch number or supplier</label>
        <input id="history-search" type="search" name="history_search" value="<?php echo admin_escape($history_search); ?>" placeholder="Search batch or supplier">
        <button class="admin-button admin-button-light" type="submit">Search</button>
      </form>
    </div>
    <?php if ($history_rows) { ?>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Batch number</th><th>Date</th><th>Supplier</th><th>Variant lines</th><th>Total units</th><th>Total cost</th><th>Details</th></tr></thead>
        <tbody><?php foreach ($history_rows as $restock_row) { ?>
          <tr>
            <td class="order-number"><?php echo admin_escape($restock_row['batch_number'] ?? ''); ?></td>
            <td><?php echo admin_escape($restock_row['restock_date']); ?></td>
            <td><?php echo admin_escape($restock_row['supplier_name']); ?></td>
            <td><?php echo number_format((int)$restock_row['line_count']); ?></td>
            <td><?php echo number_format((int)$restock_row['total_units']); ?></td>
            <td><?php echo peso((float)$restock_row['total_cost']); ?></td>
            <td><a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-inventory/view.php?id=<?php echo (int)$restock_row['restock_id']; ?>">View details</a></td>
          </tr>
        <?php } ?></tbody>
      </table></div>
      <?php admin_render_pagination($history_pagination['page'], $history_pagination['total_pages'], 'history_page', ['inventory_view' => 'history']); ?>
    <?php } else { ?><p class="manage-empty"><?php echo $history_search !== '' ? 'No restocks match this search.' : 'No restocks have been recorded.'; ?></p><?php } ?>
  </section>

  <section class="manage-panel inventory-restock-panel" aria-labelledby="restock-panel-title">
    <div class="manage-panel-heading">
      <div><p class="admin-eyebrow">SUPPLIER DELIVERY</p><h2 id="restock-panel-title">Restock Products</h2></div>
    </div>
    <form id="inventory-restock-form" method="post" class="manage-form inventory-restock-form" novalidate>
    <input type="hidden" name="inventory_view" value="restock">
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="restock_token" value="<?php echo admin_escape($restock_token); ?>">
    <input type="hidden" name="variant_supplier_id" value="<?php echo admin_escape($variant_supplier_id); ?>">
    <div class="inventory-restock-supplier">
      <label>Supplier<select name="supplier_id"><option value="">Choose a supplier</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo $posted_supplier_id === (string)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
      <button class="manage-small-button inventory-load-variants" type="submit" name="action" value="load_variants">Load supplier variants</button>
    </div>
    <div class="inventory-restock-lines">
      <?php if ($variant_supplier_id !== '' && !isset($supplier_variant_ids[(int)$variant_supplier_id])) { ?>
        <p class="manage-secondary">This supplier has no product variants available to restock.</p>
      <?php } ?>
      <?php
      $line_count = max(1, min(50, count($posted_variant_ids)));
      for ($line_index = 0; $line_index < $line_count; $line_index++) {
        $selected_variant_id = is_string($posted_variant_ids[$line_index] ?? null) ? $posted_variant_ids[$line_index] : '';
        $line_quantity = is_string($posted_quantities[$line_index] ?? null) ? $posted_quantities[$line_index] : '1';
        $line_cost = is_string($posted_unit_costs[$line_index] ?? null) ? $posted_unit_costs[$line_index] : '';
      ?>
      <div class="inventory-restock-line">
        <label>Product variant<select name="variant_ids[]" <?php echo $variant_supplier_id === '' ? 'disabled' : ''; ?>>
          <option value="">Choose a variant</option>
          <?php foreach ($variant_options as $variant_option) { ?>
            <?php if ((string)$variant_option['supplier_id'] !== $variant_supplier_id) { continue; } ?>
            <option value="<?php echo (int)$variant_option['variant_id']; ?>" <?php echo $selected_variant_id === (string)$variant_option['variant_id'] ? 'selected' : ''; ?>><?php echo admin_escape($variant_option['supplier_name'] . ' · ' . $variant_option['product_name'] . ' · ' . $variant_option['color'] . ' · ' . $variant_option['size'] . ' · Current stock: ' . number_format((int)$variant_option['stock'])); ?></option>
          <?php } ?>
        </select></label>
        <label>Quantity received<input type="number" name="quantities[]" value="<?php echo admin_escape($line_quantity); ?>"></label>
        <label>Unit purchase cost (₱)<input type="text" inputmode="decimal" name="unit_costs[]" value="<?php echo admin_escape($line_cost); ?>"></label>
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
