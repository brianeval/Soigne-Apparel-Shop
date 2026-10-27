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

$errors = [];
$post_action = $_POST['action'] ?? '';
$posted_supplier_id = is_string($_POST['supplier_id'] ?? null) ? $_POST['supplier_id'] : '';
$posted_variant_ids = is_array($_POST['variant_ids'] ?? null) ? array_values($_POST['variant_ids']) : [];
$posted_quantities = is_array($_POST['quantities'] ?? null) ? array_values($_POST['quantities']) : [];
$posted_unit_costs = is_array($_POST['unit_costs'] ?? null) ? array_values($_POST['unit_costs']) : [];

if (!isset($_SESSION['inventory_restock_tokens']) || !is_array($_SESSION['inventory_restock_tokens'])) {
    $_SESSION['inventory_restock_tokens'] = [];
}
foreach ($_SESSION['inventory_restock_tokens'] as $token => $issued_at) {
    if (!is_int($issued_at) || $issued_at < time() - 3600) {
        unset($_SESSION['inventory_restock_tokens'][$token]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif ($post_action !== 'create') {
        $errors[] = 'That inventory action is not valid.';
    } else {
        $supplier_id = filter_var($_POST['supplier_id'] ?? null, FILTER_VALIDATE_INT);
        $restock_token = $_POST['restock_token'] ?? null;
        if ($supplier_id === false || $supplier_id < 1) {
            $errors[] = 'Choose a supplier for this restock.';
        }
        if (!is_string($restock_token)
            || !isset($_SESSION['inventory_restock_tokens'][$restock_token])) {
            $errors[] = 'This restock form was already submitted or expired. Refresh the page and try again.';
        }
        if (!$posted_variant_ids || count($posted_variant_ids) > 50
            || count($posted_variant_ids) !== count($posted_quantities)
            || count($posted_variant_ids) !== count($posted_unit_costs)) {
            $errors[] = 'Add between 1 and 50 complete variant lines.';
        }
        if (($_POST['confirmed'] ?? '') !== '1') {
            $errors[] = 'Review and confirm the restock before saving it.';
        }

        $restock_lines = [];
        $seen_variants = [];
        $total_cents = 0;
        if (!$errors) {
            foreach ($posted_variant_ids as $index => $raw_variant_id) {
                $variant_id = filter_var($raw_variant_id, FILTER_VALIDATE_INT);
                $quantity = filter_var($posted_quantities[$index] ?? null, FILTER_VALIDATE_INT);
                $unit_cost_cents = inventory_cost_to_cents($posted_unit_costs[$index] ?? null);
                if ($variant_id === false || $variant_id < 1) {
                    $errors[] = 'Choose a valid product variant for every line.';
                    break;
                }
                if ($quantity === false || $quantity < 1 || $quantity > INVENTORY_MAX_STOCK) {
                    $errors[] = 'Each received quantity must be a positive whole number.';
                    break;
                }
                if ($unit_cost_cents === null || $unit_cost_cents < 1) {
                    $errors[] = 'Enter a valid unit purchase cost greater than ₱0.00 for every line.';
                    break;
                }
                if (isset($seen_variants[$variant_id])) {
                    $errors[] = 'Each variant can only appear once in a restock. Combine its quantity into one line.';
                    break;
                }
                if ($unit_cost_cents > intdiv(INVENTORY_MAX_TOTAL_CENTS, $quantity)) {
                    $errors[] = 'A restock line total is too large to calculate safely.';
                    break;
                }

                $line_total_cents = $unit_cost_cents * $quantity;
                if ($total_cents > INVENTORY_MAX_TOTAL_CENTS - $line_total_cents) {
                    $errors[] = 'The restock total is too large to calculate safely.';
                    break;
                }
                $seen_variants[$variant_id] = true;
                $total_cents += $line_total_cents;
                $restock_lines[] = [
                    'variant_id' => $variant_id,
                    'quantity' => $quantity,
                    'unit_cost' => intdiv($unit_cost_cents, 100) . '.' . str_pad((string)($unit_cost_cents % 100), 2, '0', STR_PAD_LEFT),
                ];
            }
        }

        if (!$errors) {
            unset($_SESSION['inventory_restock_tokens'][$restock_token]);
            mysqli_begin_transaction($conn);
            try {
                $supplier_stmt = mysqli_prepare($conn, 'SELECT supplier_id FROM suppliers WHERE supplier_id = ? FOR UPDATE');
                mysqli_stmt_bind_param($supplier_stmt, 'i', $supplier_id);
                mysqli_stmt_execute($supplier_stmt);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($supplier_stmt))) {
                    throw new RuntimeException('The selected supplier could not be found.');
                }

                usort($restock_lines, static function (array $left, array $right): int {
                    return $left['variant_id'] <=> $right['variant_id'];
                });
                $variant_stmt = mysqli_prepare($conn,
                    'SELECT v.stock, p.supplier_id
                     FROM product_variants v
                     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                     JOIN products p ON p.product_id = pc.product_id
                     WHERE v.variant_id = ?
                     FOR UPDATE'
                );
                $locked_lines = [];
                foreach ($restock_lines as $line) {
                    $variant_id = $line['variant_id'];
                    mysqli_stmt_bind_param($variant_stmt, 'i', $variant_id);
                    mysqli_stmt_execute($variant_stmt);
                    $variant = mysqli_fetch_assoc(mysqli_stmt_get_result($variant_stmt));
                    if (!$variant || (int)$variant['supplier_id'] !== $supplier_id) {
                        throw new RuntimeException('Every selected variant must belong to the chosen supplier.');
                    }
                    if ((int)$variant['stock'] > INVENTORY_MAX_STOCK - $line['quantity']) {
                        throw new RuntimeException('The received quantity would exceed the maximum stock value for a variant.');
                    }
                    $locked_lines[] = $line;
                }

                $restock_date = date('Y-m-d');
                $restock_stmt = mysqli_prepare($conn,
                    'INSERT INTO restocks (batch_number, supplier_id, restock_date) VALUES (NULL, ?, ?)'
                );
                mysqli_stmt_bind_param($restock_stmt, 'is', $supplier_id, $restock_date);
                mysqli_stmt_execute($restock_stmt);
                $restock_id = mysqli_insert_id($conn);
                $batch_number = sprintf('RS-%s-%05d', substr($restock_date, 0, 4), $restock_id);
                $batch_stmt = mysqli_prepare($conn, 'UPDATE restocks SET batch_number = ? WHERE restock_id = ?');
                mysqli_stmt_bind_param($batch_stmt, 'si', $batch_number, $restock_id);
                mysqli_stmt_execute($batch_stmt);

                $item_stmt = mysqli_prepare($conn,
                    'INSERT INTO restock_items (restock_id, variant_id, quantity, unit_cost) VALUES (?, ?, ?, ?)'
                );
                $stock_stmt = mysqli_prepare($conn,
                    'UPDATE product_variants SET stock = stock + ? WHERE variant_id = ? AND stock <= ?'
                );
                foreach ($locked_lines as $line) {
                    $variant_id = $line['variant_id'];
                    $quantity = $line['quantity'];
                    $unit_cost = $line['unit_cost'];
                    mysqli_stmt_bind_param($item_stmt, 'iiis', $restock_id, $variant_id, $quantity, $unit_cost);
                    mysqli_stmt_execute($item_stmt);

                    $maximum_previous_stock = INVENTORY_MAX_STOCK - $quantity;
                    mysqli_stmt_bind_param($stock_stmt, 'iii', $quantity, $variant_id, $maximum_previous_stock);
                    mysqli_stmt_execute($stock_stmt);
                    if (mysqli_stmt_affected_rows($stock_stmt) !== 1) {
                        throw new RuntimeException('Stock changed before the restock could be completed.');
                    }
                }

                mysqli_commit($conn);
                admin_set_flash('success', 'Restock ' . $batch_number . ' saved and stock updated.');
                header('Location: ' . BASE_URL . 'admin/manage-inventory/');
                exit;
            } catch (RuntimeException $exception) {
                mysqli_rollback($conn);
                $errors[] = $exception->getMessage();
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                $errors[] = 'Could not save this restock. No stock changes were kept. Please try again.';
            }
        }
    }
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
$color_options_by_department = ['men' => [], 'women' => [], 'kids' => []];
$size_options_by_department = ['men' => [], 'women' => [], 'kids' => []];
foreach ($variant_options as $variant_option) {
    $color_options_by_department[$variant_option['department']][$variant_option['color']] = true;
    $size_options_by_department[$variant_option['department']][$variant_option['size']] = true;
}
$all_color_options = array_fill_keys(array_merge(
    array_keys($color_options_by_department['men']),
    array_keys($color_options_by_department['women']),
    array_keys($color_options_by_department['kids'])
), true);
$color_options_by_department = array_map(static function (array $colors): array {
    $color_names = array_keys($colors);
    natcasesort($color_names);
    return array_values($color_names);
}, $color_options_by_department);
$all_color_options = array_keys($all_color_options);
natcasesort($all_color_options);
$all_color_options = array_values($all_color_options);
$all_size_options = array_fill_keys(array_merge(
    array_keys($size_options_by_department['men']),
    array_keys($size_options_by_department['women']),
    array_keys($size_options_by_department['kids'])
), true);
$size_options_by_department = array_map(static function (array $sizes): array {
    $size_names = array_keys($sizes);
    natcasesort($size_names);
    return array_values($size_names);
}, $size_options_by_department);
$all_size_options = array_keys($all_size_options);
natcasesort($all_size_options);
$all_size_options = array_values($all_size_options);

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
    <button class="admin-button" type="button" data-open-dialog="restock-dialog">Restock Products</button>
  </section>

  <?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
  <?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>

  <section class="admin-stat-grid inventory-stat-grid" aria-label="Inventory summary">
    <article class="admin-stat-card">
      <div class="stat-top"><span>Total Stock Units</span><span class="stat-icon icon-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16v13H4zM8 7V4h8v3M8 11h8M8 15h5"></path></svg></span></div>
      <p class="stat-value"><?php echo number_format((int)$summary['total_stock']); ?></p>
      <p class="stat-foot">Units across all product variants</p>
    </article>
    <article class="admin-stat-card">
      <div class="stat-top"><span>Restock Expenses</span><span class="stat-icon icon-amber" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3v18M17 7.5c0-1.7-1.8-3-4.5-3S8 5.8 8 7.5 9.7 10 12.5 10s4.5 1.3 4.5 3-1.8 3.5-4.5 3.5S8 15 8 13"></path></svg></span></div>
      <p class="stat-value"><?php echo peso((float)$summary['restock_expenses']); ?></p>
      <p class="stat-foot">Recorded purchase costs to date</p>
    </article>
  </section>

  <section class="manage-panel inventory-stock-panel">
    <div class="manage-panel-heading">
      <div><p class="admin-eyebrow">CURRENT STOCK</p><h2>Product variants <span class="manage-count"><?php echo number_format($inventory_total); ?></span></h2></div>
    </div>
    <form method="get" class="inventory-filters" aria-label="Filter inventory">
      <label class="inventory-search">Product name<input type="search" name="search" value="<?php echo admin_escape($search); ?>" placeholder="Search product name"></label>
      <label>Department<select name="department" data-inventory-department><option value="">All departments</option><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $department_value => $department_label) { ?><option value="<?php echo $department_value; ?>" <?php echo $filter_department === $department_value ? 'selected' : ''; ?>><?php echo $department_label; ?></option><?php } ?></select></label>
      <label>Brand<select name="supplier_id"><option value="">All brands</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo $filter_supplier === (int)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
      <label>Category<select name="category_id"><option value="">All categories</option><?php foreach ($categories as $category) { ?><option value="<?php echo (int)$category['category_id']; ?>" <?php echo $filter_category === (int)$category['category_id'] ? 'selected' : ''; ?>><?php echo admin_escape($category['name']); ?></option><?php } ?></select></label>
      <label>Color<select name="color" data-inventory-color data-color-options="<?php echo admin_escape(json_encode($color_options_by_department, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)); ?>"><option value="">All colors</option><?php foreach ($visible_color_options as $color) { ?><option value="<?php echo admin_escape($color); ?>" <?php echo $filter_color === $color ? 'selected' : ''; ?>><?php echo admin_escape($color); ?></option><?php } ?></select></label>
      <label>Size<select name="size" data-inventory-size data-size-options="<?php echo admin_escape(json_encode($size_options_by_department, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG)); ?>"><option value="">All sizes</option><?php foreach ($visible_size_options as $size) { ?><option value="<?php echo admin_escape($size); ?>" <?php echo $filter_size === $size ? 'selected' : ''; ?>><?php echo admin_escape($size); ?></option><?php } ?></select></label>
      <input type="hidden" name="history_search" value="<?php echo admin_escape($history_search); ?>">
      <button class="admin-button" type="submit">Apply filters</button>
      <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-inventory/">Clear</a>
    </form>
    <?php if ($inventory_rows) { ?>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Product</th><th>Department</th><th>Brand / Supplier</th><th>Category</th><th>Color</th><th>Size</th><th>Stock quantity</th></tr></thead>
        <tbody><?php foreach ($inventory_rows as $row) { ?>
          <tr>
            <td class="order-number"><?php echo admin_escape($row['product_name']); ?></td>
            <td><?php echo admin_escape(ucfirst($row['department'])); ?></td>
            <td><?php echo admin_escape($row['supplier_name']); ?></td>
            <td><?php echo admin_escape($row['category_name']); ?></td>
            <td><?php echo admin_escape($row['color']); ?></td>
            <td><?php echo admin_escape($row['size']); ?></td>
            <td><?php echo number_format((int)$row['stock']); ?></td>
          </tr>
        <?php } ?></tbody>
      </table></div>
      <?php admin_render_pagination($inventory_pagination['page'], $inventory_pagination['total_pages'], 'inventory_page'); ?>
    <?php } else { ?><p class="manage-empty">No product variants match these filters.</p><?php } ?>
  </section>

  <section class="manage-panel manage-list-panel inventory-history-panel">
    <div class="manage-panel-heading">
      <div><p class="admin-eyebrow">SUPPLIER DELIVERIES</p><h2>Restock History <span class="manage-count"><?php echo number_format($history_total); ?></span></h2></div>
      <form method="get" class="inventory-history-search">
        <label class="visually-hidden" for="history-search">Search batch number or supplier</label>
        <input id="history-search" type="search" name="history_search" value="<?php echo admin_escape($history_search); ?>" placeholder="Search batch or supplier">
        <button class="admin-button admin-button-light" type="submit">Search</button>
      </form>
    </div>
    <?php if ($history_rows) { ?>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Batch number</th><th>Date</th><th>Supplier</th><th>Variant lines</th><th>Total units</th><th>Total cost</th><th>Details</th></tr></thead>
        <tbody><?php foreach ($history_rows as $row) { ?>
          <tr>
            <td class="order-number"><?php echo admin_escape($row['batch_number'] ?? ''); ?></td>
            <td><?php echo admin_escape($row['restock_date']); ?></td>
            <td><?php echo admin_escape($row['supplier_name']); ?></td>
            <td><?php echo number_format((int)$row['line_count']); ?></td>
            <td><?php echo number_format((int)$row['total_units']); ?></td>
            <td><?php echo peso((float)$row['total_cost']); ?></td>
            <td><a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-inventory/view.php?id=<?php echo (int)$row['restock_id']; ?>">View details</a></td>
          </tr>
        <?php } ?></tbody>
      </table></div>
      <?php admin_render_pagination($history_pagination['page'], $history_pagination['total_pages'], 'history_page'); ?>
    <?php } else { ?><p class="manage-empty"><?php echo $history_search !== '' ? 'No restocks match this search.' : 'No restocks have been recorded.'; ?></p><?php } ?>
  </section>
</div>

<dialog class="catalog-dialog inventory-restock-dialog" id="restock-dialog" aria-labelledby="restock-dialog-title"<?php echo $errors && $post_action === 'create' ? ' data-open-on-load' : ''; ?>>
  <div class="catalog-dialog-heading">
    <div><p class="admin-eyebrow">SUPPLIER DELIVERY</p><h2 id="restock-dialog-title">Restock Products</h2></div>
    <button class="catalog-dialog-close" type="button" aria-label="Close dialog" data-close-dialog>Close</button>
  </div>
  <form method="post" class="manage-form inventory-restock-form" data-restock-form novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="restock_token" value="<?php echo admin_escape($restock_token); ?>">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="confirmed" value="0" data-restock-confirmed>
    <div data-restock-edit>
      <label>Supplier<select name="supplier_id" required data-restock-supplier><option value="">Choose a supplier</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo $posted_supplier_id === (string)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
      <div class="inventory-restock-lines-heading"><h3>Variant lines</h3><button class="manage-small-button" type="button" data-add-restock-line>Add variant</button></div>
      <div class="inventory-restock-lines" data-restock-lines>
        <?php
        $line_count = max(1, min(count($posted_variant_ids), 50));
        for ($line_index = 0; $line_index < $line_count; $line_index++) {
            $selected_variant_id = (string)($posted_variant_ids[$line_index] ?? '');
            $line_quantity = (string)($posted_quantities[$line_index] ?? '');
            $line_cost = (string)($posted_unit_costs[$line_index] ?? '');
        ?>
          <div class="inventory-restock-line" data-restock-line>
            <label>Product variant<select name="variant_ids[]" required data-restock-variant>
              <option value="">Choose a variant</option>
              <?php foreach ($variant_options as $variant_option) { ?>
                <option value="<?php echo (int)$variant_option['variant_id']; ?>" data-supplier-id="<?php echo (int)$variant_option['supplier_id']; ?>" data-stock="<?php echo (int)$variant_option['stock']; ?>" <?php echo $selected_variant_id === (string)$variant_option['variant_id'] ? 'selected' : ''; ?>><?php echo admin_escape($variant_option['product_name'] . ' · ' . $variant_option['color'] . ' · ' . $variant_option['size'] . ' · Current stock: ' . number_format((int)$variant_option['stock'])); ?></option>
              <?php } ?>
            </select><small class="manage-secondary" data-current-stock>Current stock: —</small></label>
            <label>Quantity received<input type="number" name="quantities[]" min="1" max="<?php echo INVENTORY_MAX_STOCK; ?>" step="1" required value="<?php echo admin_escape($line_quantity); ?>" data-restock-quantity></label>
            <label>Unit purchase cost (₱)<input type="number" name="unit_costs[]" min="0.01" max="99999999.99" step="0.01" required value="<?php echo admin_escape($line_cost); ?>" data-restock-cost></label>
            <div class="inventory-restock-line-total"><span>Line total</span><strong data-line-total>₱0.00</strong></div>
            <button class="manage-small-button is-danger" type="button" data-remove-restock-line aria-label="Remove variant line">Remove</button>
          </div>
        <?php } ?>
      </div>
      <div class="inventory-restock-total"><span>Restock total</span><strong data-restock-total>₱0.00</strong></div>
      <p class="inventory-review-error" role="alert" data-review-error hidden></p>
      <div class="manage-form-buttons">
        <button class="admin-button" type="button" data-review-restock>Review restock</button>
        <button class="manage-small-button" type="button" data-close-dialog>Cancel</button>
      </div>
    </div>
    <section class="inventory-restock-review" data-restock-review hidden>
      <div class="inventory-restock-review-heading"><p class="admin-eyebrow">CONFIRM DELIVERY</p><h3>Review restock transaction</h3><p>Check the supplier, quantities, and purchase costs before saving.</p></div>
      <div class="inventory-review-supplier" data-review-supplier></div>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Product variant</th><th>Current stock</th><th>Received</th><th>Unit cost</th><th>Line total</th></tr></thead>
        <tbody data-review-lines></tbody>
      </table></div>
      <div class="inventory-restock-total"><span>Confirmed restock total</span><strong data-review-total>₱0.00</strong></div>
      <div class="manage-form-buttons">
        <button class="admin-button" type="submit" data-confirm-restock>Confirm and save restock</button>
        <button class="manage-small-button" type="button" data-edit-restock>Edit lines</button>
      </div>
    </section>
  </form>
</dialog>
<template id="restock-variant-options">
  <option value="">Choose a variant</option>
  <?php foreach ($variant_options as $variant_option) { ?>
    <option value="<?php echo (int)$variant_option['variant_id']; ?>" data-supplier-id="<?php echo (int)$variant_option['supplier_id']; ?>" data-stock="<?php echo (int)$variant_option['stock']; ?>"><?php echo admin_escape($variant_option['product_name'] . ' · ' . $variant_option['color'] . ' · ' . $variant_option['size'] . ' · Current stock: ' . number_format((int)$variant_option['stock'])); ?></option>
  <?php } ?>
</template>
<template id="restock-line-template">
  <div class="inventory-restock-line" data-restock-line>
    <label>Product variant<select name="variant_ids[]" required data-restock-variant><option value="">Choose a variant</option></select><small class="manage-secondary" data-current-stock>Current stock: —</small></label>
    <label>Quantity received<input type="number" name="quantities[]" min="1" max="<?php echo INVENTORY_MAX_STOCK; ?>" step="1" required data-restock-quantity></label>
    <label>Unit purchase cost (₱)<input type="number" name="unit_costs[]" min="0.01" max="99999999.99" step="0.01" required data-restock-cost></label>
    <div class="inventory-restock-line-total"><span>Line total</span><strong data-line-total>₱0.00</strong></div>
    <button class="manage-small-button is-danger" type="button" data-remove-restock-line aria-label="Remove variant line">Remove</button>
  </div>
</template>
<script src="<?php echo BASE_URL; ?>admin/inventory.js?v=<?php echo filemtime(APP_ROOT . '/admin/inventory.js'); ?>" defer></script>
<?php include("../includes/footer.php"); ?>
