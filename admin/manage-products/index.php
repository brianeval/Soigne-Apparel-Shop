<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $supplier_id = filter_var($_POST['supplier_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $category = trim(is_string($_POST['category'] ?? null) ? $_POST['category'] : '');
        $department = $_POST['department'] ?? '';
        $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $description = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');

        if ($supplier_id === false || $supplier_id < 1) {
            $errors[] = 'Choose a valid brand.';
        }
        if ($name === '' || mb_strlen($name) > 150) {
            $errors[] = 'Enter a product name up to 150 characters.';
        }
        if (mb_strlen($category) > 50) {
            $errors[] = 'Category must be 50 characters or fewer.';
        }
        if (!in_array($department, ['men', 'women', 'kids'], true)) {
            $errors[] = 'Choose a valid department.';
        }
        if ($price === false || $price < 0 || $price > 99999999.99) {
            $errors[] = 'Enter a valid product price.';
        }

        if (!$errors) {
            $stmt = mysqli_prepare($conn, "INSERT INTO products (supplier_id, name, category, department, price, description) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'isssds', $supplier_id, $name, $category, $department, $price, $description);
            mysqli_stmt_execute($stmt);
            $new_product_id = mysqli_insert_id($conn);
            admin_set_flash('success', 'Product created. Add its colors and sizes below.');
            header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $new_product_id);
            exit;
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($product_id === false || $product_id < 1) {
            $errors[] = 'Choose a valid product.';
        } else {
            $usage_stmt = mysqli_prepare($conn,
                "SELECT
                    (SELECT COUNT(*) FROM cart c JOIN product_variants v ON v.variant_id = c.variant_id JOIN product_colors pc ON pc.product_color_id = v.product_color_id WHERE pc.product_id = p.product_id) AS cart_count,
                    (SELECT COUNT(*) FROM order_items oi JOIN product_variants v ON v.variant_id = oi.variant_id JOIN product_colors pc ON pc.product_color_id = v.product_color_id WHERE pc.product_id = p.product_id) AS order_count,
                    (SELECT COUNT(*) FROM restock_items ri JOIN product_variants v ON v.variant_id = ri.variant_id JOIN product_colors pc ON pc.product_color_id = v.product_color_id WHERE pc.product_id = p.product_id) AS restock_count
                 FROM products p WHERE p.product_id = ?"
            );
            mysqli_stmt_bind_param($usage_stmt, 'i', $product_id);
            mysqli_stmt_execute($usage_stmt);
            $usage = mysqli_fetch_assoc(mysqli_stmt_get_result($usage_stmt));

            if (!$usage) {
                $errors[] = 'That product could not be found.';
            } elseif ((int)$usage['order_count']) {
                $errors[] = 'This product is included in an order and cannot be deleted. You can edit its details instead.';
            } elseif ((int)$usage['cart_count'] || (int)$usage['restock_count']) {
                $errors[] = 'This product is referenced by a cart or restock record and cannot be deleted. You can edit its details instead.';
            } else {
                mysqli_begin_transaction($conn);
                try {
                    $variants_stmt = mysqli_prepare($conn,
                        "DELETE v FROM product_variants v
                         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                         WHERE pc.product_id = ?"
                    );
                    mysqli_stmt_bind_param($variants_stmt, 'i', $product_id);
                    mysqli_stmt_execute($variants_stmt);

                    $colors_stmt = mysqli_prepare($conn, "DELETE FROM product_colors WHERE product_id = ?");
                    mysqli_stmt_bind_param($colors_stmt, 'i', $product_id);
                    mysqli_stmt_execute($colors_stmt);

                    $product_stmt = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
                    mysqli_stmt_bind_param($product_stmt, 'i', $product_id);
                    mysqli_stmt_execute($product_stmt);
                    mysqli_commit($conn);
                    admin_set_flash('success', 'Product deleted.');
                    header('Location: ' . BASE_URL . 'admin/manage-products/');
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    mysqli_rollback($conn);
                    $errors[] = 'Could not delete this product because it is referenced by other store data.';
                }
            }
        }
    } else {
        $errors[] = 'That product action is not valid.';
    }
}

$suppliers_result = mysqli_query($conn, "SELECT supplier_id, name FROM suppliers ORDER BY name");
$suppliers = mysqli_fetch_all($suppliers_result, MYSQLI_ASSOC);
$products_result = mysqli_query($conn,
    "SELECT p.product_id, p.name, p.category, p.department, p.price, s.name AS supplier_name,
            COUNT(DISTINCT pc.product_color_id) AS color_count,
            (SELECT COUNT(*)
             FROM order_items oi
             JOIN product_variants order_variant ON order_variant.variant_id = oi.variant_id
             JOIN product_colors order_color ON order_color.product_color_id = order_variant.product_color_id
             WHERE order_color.product_id = p.product_id) AS order_count,
            COALESCE(SUM(v.stock), 0) AS stock_count
     FROM products p
     JOIN suppliers s ON s.supplier_id = p.supplier_id
     LEFT JOIN product_colors pc ON pc.product_id = p.product_id
     LEFT JOIN product_variants v ON v.product_color_id = pc.product_color_id
     GROUP BY p.product_id, p.name, p.category, p.department, p.price, s.name
     ORDER BY p.product_id DESC"
);
$products = mysqli_fetch_all($products_result, MYSQLI_ASSOC);
$flash = admin_take_flash();
$admin_page_title = 'Manage Products';
$admin_active_page = 'products';
include __DIR__ . '/../includes/header.php';
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CATALOG</p><h1>Manage Products</h1><p>Create products, update their details, and manage available variants.</p></div>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>

<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Create a product</h2></div></div>
  <?php if (!$suppliers) { ?>
    <p class="manage-empty">Create a brand first before adding a product.</p>
  <?php } else { ?>
    <form method="post" class="manage-form manage-product-create">
      <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
      <input type="hidden" name="action" value="create">
      <label>Product name<input name="name" maxlength="150" required value="<?php echo admin_escape($_POST['name'] ?? ''); ?>"></label>
      <label>Brand<select name="supplier_id" required><option value="">Choose a brand</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo (string)($_POST['supplier_id'] ?? '') === (string)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
      <label>Category<input name="category" maxlength="50" value="<?php echo admin_escape($_POST['category'] ?? ''); ?>"></label>
      <label>Department<select name="department" required><option value="men">Men</option><option value="women">Women</option><option value="kids">Kids</option></select></label>
      <label>Price (₱)<input type="number" name="price" min="0" max="99999999.99" step="0.01" required value="<?php echo admin_escape($_POST['price'] ?? ''); ?>"></label>
      <label class="manage-form-wide">Description<textarea name="description" rows="3"><?php echo admin_escape($_POST['description'] ?? ''); ?></textarea></label>
      <div class="manage-form-wide"><button class="admin-button" type="submit">Create product</button></div>
    </form>
  <?php } ?>
</section>

<section class="manage-panel manage-list-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">PRODUCT CATALOG</p><h2>Products <span class="manage-count"><?php echo count($products); ?></span></h2></div></div>
  <?php if ($products) { ?>
    <div class="admin-table-wrap"><table class="admin-table">
      <thead><tr><th>Product</th><th>Brand</th><th>Department</th><th>Price</th><th>Colors</th><th>Stock</th><th>Actions</th></tr></thead>
      <tbody><?php foreach ($products as $product) { ?>
        <tr>
          <td class="order-number"><?php echo admin_escape($product['name']); ?><small class="manage-secondary"><?php echo admin_escape($product['category'] ?? 'Uncategorized'); ?></small></td>
          <td><?php echo admin_escape($product['supplier_name']); ?></td>
          <td><?php echo admin_escape(ucfirst($product['department'])); ?></td>
          <td><?php echo peso((float)$product['price']); ?></td>
          <td><?php echo (int)$product['color_count']; ?></td>
          <td><?php echo (int)$product['stock_count']; ?></td>
          <td><div class="manage-row-actions">
            <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-products/edit.php?id=<?php echo (int)$product['product_id']; ?>">Edit</a>
            <?php if ((int)$product['order_count'] === 0) { ?>
              <form method="post" onsubmit="return confirm('Delete this product? Products included in orders cannot be deleted.');">
                <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">
                <button class="manage-small-button is-danger" type="submit">Delete</button>
              </form>
            <?php } else { ?>
              <span class="manage-secondary">Has orders</span>
            <?php } ?>
          </div></td>
        </tr>
      <?php } ?></tbody>
    </table></div>
  <?php } else { ?><p class="manage-empty">No products yet. Use the form above to add your first product.</p><?php } ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
