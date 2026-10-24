<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$product_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($product_id === false || $product_id < 1) {
    http_response_code(404);
    exit('Product not found.');
}

$product_stmt = mysqli_prepare($conn,
    "SELECT p.*, s.name AS supplier_name
     FROM products p
     JOIN suppliers s ON s.supplier_id = p.supplier_id
     WHERE p.product_id = ?"
);
mysqli_stmt_bind_param($product_stmt, 'i', $product_id);
mysqli_stmt_execute($product_stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($product_stmt));
if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_product') {
            $supplier_id = filter_var($_POST['supplier_id'] ?? null, FILTER_VALIDATE_INT);
            $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
            $category_id_input = $_POST['category_id'] ?? '';
            $category_id = $category_id_input === '' ? null : filter_var($category_id_input, FILTER_VALIDATE_INT);
            $department = $_POST['department'] ?? '';
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            $description = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');

            if ($supplier_id === false || $supplier_id < 1) $errors[] = 'Choose a valid brand.';
            if ($name === '' || mb_strlen($name) > 150) $errors[] = 'Enter a product name up to 150 characters.';
            if ($category_id_input !== '' && ($category_id === false || $category_id < 1)) $errors[] = 'Choose a valid category.';
            if (!in_array($department, ['men', 'women', 'kids'], true)) $errors[] = 'Choose a valid department.';
            if ($price === false || $price < 0 || $price > 99999999.99) $errors[] = 'Enter a valid price.';

            if (!$errors) {
                if ($category_id !== null) {
                    $category_stmt = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE category_id = ?");
                    mysqli_stmt_bind_param($category_stmt, 'i', $category_id);
                    mysqli_stmt_execute($category_stmt);
                    $category_row = mysqli_fetch_assoc(mysqli_stmt_get_result($category_stmt));
                    if (!$category_row) {
                        $errors[] = 'Choose a valid category.';
                    }
                }
            }

            if (!$errors) {
                $stmt = mysqli_prepare($conn, "UPDATE products SET supplier_id = ?, name = ?, category_id = ?, department = ?, price = ?, description = ? WHERE product_id = ?");
                mysqli_stmt_bind_param($stmt, 'isisdsi', $supplier_id, $name, $category_id, $department, $price, $description, $product_id);
                mysqli_stmt_execute($stmt);
                admin_set_flash('success', 'Product details updated.');
                header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                exit;
            }
        } elseif ($action === 'add_color') {
            $color = trim(is_string($_POST['color'] ?? null) ? $_POST['color'] : '');
            $image = trim(is_string($_POST['image'] ?? null) ? $_POST['image'] : '');
            if ($color === '' || mb_strlen($color) > 80) $errors[] = 'Enter a color name up to 80 characters.';
            if (mb_strlen($image) > 255) $errors[] = 'Image path must be 255 characters or fewer.';
            if (!$errors) {
                $stmt = mysqli_prepare($conn, "INSERT INTO product_colors (product_id, color, image) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'iss', $product_id, $color, $image);
                try {
                    mysqli_stmt_execute($stmt);
                    admin_set_flash('success', 'Color added.');
                    header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    $errors[] = $exception->getCode() === 1062 ? 'This product already has that color.' : 'Could not add the color.';
                }
            }
        } elseif ($action === 'update_color') {
            $color_id = filter_var($_POST['product_color_id'] ?? null, FILTER_VALIDATE_INT);
            $color = trim(is_string($_POST['color'] ?? null) ? $_POST['color'] : '');
            $image = trim(is_string($_POST['image'] ?? null) ? $_POST['image'] : '');
            if ($color_id === false || $color_id < 1 || $color === '' || mb_strlen($color) > 80 || mb_strlen($image) > 255) {
                $errors[] = 'Enter a valid color name and image path.';
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE product_colors SET color = ?, image = ? WHERE product_color_id = ? AND product_id = ?");
                mysqli_stmt_bind_param($stmt, 'ssii', $color, $image, $color_id, $product_id);
                try {
                    mysqli_stmt_execute($stmt);
                    admin_set_flash('success', 'Color updated.');
                    header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    $errors[] = $exception->getCode() === 1062 ? 'This product already has that color.' : 'Could not update the color.';
                }
            }
        } elseif ($action === 'delete_color') {
            $color_id = filter_var($_POST['product_color_id'] ?? null, FILTER_VALIDATE_INT);
            if ($color_id === false || $color_id < 1) {
                $errors[] = 'Choose a valid color.';
            } else {
                $usage_stmt = mysqli_prepare($conn,
                    "SELECT
                        (SELECT COUNT(*) FROM cart c JOIN product_variants v ON v.variant_id = c.variant_id WHERE v.product_color_id = pc.product_color_id) AS cart_count,
                        (SELECT COUNT(*) FROM order_items oi JOIN product_variants v ON v.variant_id = oi.variant_id WHERE v.product_color_id = pc.product_color_id) AS order_count,
                        (SELECT COUNT(*) FROM restock_items ri JOIN product_variants v ON v.variant_id = ri.variant_id WHERE v.product_color_id = pc.product_color_id) AS restock_count
                     FROM product_colors pc WHERE pc.product_color_id = ? AND pc.product_id = ?"
                );
                mysqli_stmt_bind_param($usage_stmt, 'ii', $color_id, $product_id);
                mysqli_stmt_execute($usage_stmt);
                $usage = mysqli_fetch_assoc(mysqli_stmt_get_result($usage_stmt));
                if (!$usage) {
                    $errors[] = 'Color not found.';
                } elseif ((int)$usage['cart_count'] || (int)$usage['order_count'] || (int)$usage['restock_count']) {
                    $errors[] = 'This color has cart, order, or restock history and cannot be deleted.';
                } else {
                    mysqli_begin_transaction($conn);
                    try {
                        $delete_variants = mysqli_prepare($conn, "DELETE FROM product_variants WHERE product_color_id = ?");
                        mysqli_stmt_bind_param($delete_variants, 'i', $color_id);
                        mysqli_stmt_execute($delete_variants);
                        $delete_color = mysqli_prepare($conn, "DELETE FROM product_colors WHERE product_color_id = ? AND product_id = ?");
                        mysqli_stmt_bind_param($delete_color, 'ii', $color_id, $product_id);
                        mysqli_stmt_execute($delete_color);
                        mysqli_commit($conn);
                        admin_set_flash('success', 'Color and its unused sizes deleted.');
                        header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                        exit;
                    } catch (mysqli_sql_exception $exception) {
                        mysqli_rollback($conn);
                        $errors[] = 'Could not delete this color because it is referenced by store data.';
                    }
                }
            }
        } elseif ($action === 'add_variant') {
            $color_id = filter_var($_POST['product_color_id'] ?? null, FILTER_VALIDATE_INT);
            $size = trim(is_string($_POST['size'] ?? null) ? $_POST['size'] : '');
            $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
            if ($color_id === false || $color_id < 1 || $size === '' || mb_strlen($size) > 20 || $stock === false || $stock < 0) {
                $errors[] = 'Enter a valid color, size, and non-negative stock amount.';
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO product_variants (product_color_id, size, stock) SELECT product_color_id, ?, ? FROM product_colors WHERE product_color_id = ? AND product_id = ?");
                mysqli_stmt_bind_param($stmt, 'siii', $size, $stock, $color_id, $product_id);
                try {
                    mysqli_stmt_execute($stmt);
                    if (mysqli_stmt_affected_rows($stmt) !== 1) {
                        $errors[] = 'That color does not belong to this product.';
                    } else {
                        admin_set_flash('success', 'Size variant added.');
                        header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                        exit;
                    }
                } catch (mysqli_sql_exception $exception) {
                    $errors[] = $exception->getCode() === 1062 ? 'That size already exists for this color.' : 'Could not add the size variant.';
                }
            }
        } elseif ($action === 'update_variant') {
            $variant_id = filter_var($_POST['variant_id'] ?? null, FILTER_VALIDATE_INT);
            $size = trim(is_string($_POST['size'] ?? null) ? $_POST['size'] : '');
            $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
            if ($variant_id === false || $variant_id < 1 || $size === '' || mb_strlen($size) > 20 || $stock === false || $stock < 0) {
                $errors[] = 'Enter a valid size and non-negative stock amount.';
            } else {
                $usage_stmt = mysqli_prepare($conn,
                    "SELECT v.size,
                        (SELECT COUNT(*) FROM cart c WHERE c.variant_id = v.variant_id) +
                        (SELECT COUNT(*) FROM order_items oi WHERE oi.variant_id = v.variant_id) +
                        (SELECT COUNT(*) FROM restock_items ri WHERE ri.variant_id = v.variant_id) AS usage_count
                     FROM product_variants v
                     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                     WHERE v.variant_id = ? AND pc.product_id = ?"
                );
                mysqli_stmt_bind_param($usage_stmt, 'ii', $variant_id, $product_id);
                mysqli_stmt_execute($usage_stmt);
                $variant_usage = mysqli_fetch_assoc(mysqli_stmt_get_result($usage_stmt));
                if (!$variant_usage) {
                    $errors[] = 'Size variant not found.';
                } elseif ($size !== $variant_usage['size'] && (int)$variant_usage['usage_count'] > 0) {
                    $errors[] = 'The size cannot be renamed while it is referenced by a cart, order, or restock record.';
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE product_variants v JOIN product_colors pc ON pc.product_color_id = v.product_color_id SET v.size = ?, v.stock = ? WHERE v.variant_id = ? AND pc.product_id = ?");
                    mysqli_stmt_bind_param($stmt, 'siii', $size, $stock, $variant_id, $product_id);
                    try {
                        mysqli_stmt_execute($stmt);
                        admin_set_flash('success', 'Size and stock updated.');
                        header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                        exit;
                    } catch (mysqli_sql_exception $exception) {
                        $errors[] = $exception->getCode() === 1062 ? 'That size already exists for this color.' : 'Could not update the size variant.';
                    }
                }
            }
        } elseif ($action === 'delete_variant') {
            $variant_id = filter_var($_POST['variant_id'] ?? null, FILTER_VALIDATE_INT);
            if ($variant_id === false || $variant_id < 1) {
                $errors[] = 'Choose a valid size variant.';
            } else {
                $usage_stmt = mysqli_prepare($conn,
                    "SELECT
                        (SELECT COUNT(*) FROM cart WHERE variant_id = v.variant_id) AS cart_count,
                        (SELECT COUNT(*) FROM order_items WHERE variant_id = v.variant_id) AS order_count,
                        (SELECT COUNT(*) FROM restock_items WHERE variant_id = v.variant_id) AS restock_count
                     FROM product_variants v
                     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                     WHERE v.variant_id = ? AND pc.product_id = ?"
                );
                mysqli_stmt_bind_param($usage_stmt, 'ii', $variant_id, $product_id);
                mysqli_stmt_execute($usage_stmt);
                $usage = mysqli_fetch_assoc(mysqli_stmt_get_result($usage_stmt));
                if (!$usage) {
                    $errors[] = 'Size variant not found.';
                } elseif ((int)$usage['cart_count'] || (int)$usage['order_count'] || (int)$usage['restock_count']) {
                    $errors[] = 'This size has cart, order, or restock history and cannot be deleted.';
                } else {
                    $stmt = mysqli_prepare($conn, "DELETE v FROM product_variants v JOIN product_colors pc ON pc.product_color_id = v.product_color_id WHERE v.variant_id = ? AND pc.product_id = ?");
                    mysqli_stmt_bind_param($stmt, 'ii', $variant_id, $product_id);
                    mysqli_stmt_execute($stmt);
                    admin_set_flash('success', 'Size variant deleted.');
                    header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                    exit;
                }
            }
        } else {
            $errors[] = 'That product action is not valid.';
        }
    }
}

$suppliers = mysqli_fetch_all(mysqli_query($conn, "SELECT supplier_id, name FROM suppliers ORDER BY name"), MYSQLI_ASSOC);
$categories = mysqli_fetch_all(mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name"), MYSQLI_ASSOC);
$colors_stmt = mysqli_prepare($conn,
    "SELECT pc.product_color_id, pc.color, pc.image, v.variant_id, v.size, v.stock
     FROM product_colors pc
     LEFT JOIN product_variants v ON v.product_color_id = pc.product_color_id
     WHERE pc.product_id = ?
     ORDER BY pc.product_color_id, v.variant_id"
);
mysqli_stmt_bind_param($colors_stmt, 'i', $product_id);
mysqli_stmt_execute($colors_stmt);
$color_rows = mysqli_fetch_all(mysqli_stmt_get_result($colors_stmt), MYSQLI_ASSOC);
$colors = [];
foreach ($color_rows as $row) {
    $color_id = (int)$row['product_color_id'];
    if (!isset($colors[$color_id])) {
        $colors[$color_id] = ['product_color_id' => $color_id, 'color' => $row['color'], 'image' => $row['image'], 'variants' => []];
    }
    if ($row['variant_id'] !== null) {
        $colors[$color_id]['variants'][] = ['variant_id' => (int)$row['variant_id'], 'size' => $row['size'], 'stock' => (int)$row['stock']];
    }
}
$flash = admin_take_flash();
$admin_page_title = 'Edit Product';
$admin_active_page = 'products';
include __DIR__ . '/../includes/header.php';
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">PRODUCT CATALOG</p><h1>Edit Product</h1><p>Update product details, colors, sizes, and stock.</p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-products/">&larr; All products</a>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>

<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">PRODUCT DETAILS</p><h2><?php echo admin_escape($product['name']); ?></h2></div></div>
  <form method="post" class="manage-form manage-product-create">
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="action" value="update_product">
    <label>Product name<input name="name" maxlength="150" required value="<?php echo admin_escape($_POST['name'] ?? $product['name']); ?>"></label>
    <label>Brand<select name="supplier_id" required><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo (int)$supplier['supplier_id'] === (int)$product['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
    <label>Category<select name="category_id"><option value="">Uncategorized</option><?php foreach ($categories as $category_option) { $selected_category_id = $_POST['category_id'] ?? $product['category_id']; ?><option value="<?php echo (int)$category_option['category_id']; ?>" <?php echo (string)$selected_category_id === (string)$category_option['category_id'] ? 'selected' : ''; ?>><?php echo admin_escape($category_option['name']); ?></option><?php } ?></select></label>
    <label>Department<select name="department"><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $key => $label) { ?><option value="<?php echo $key; ?>" <?php echo $product['department'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></label>
    <label>Price (₱)<input type="number" name="price" min="0" max="99999999.99" step="0.01" required value="<?php echo admin_escape($_POST['price'] ?? $product['price']); ?>"></label>
    <label class="manage-form-wide">Description<textarea name="description" rows="3"><?php echo admin_escape($_POST['description'] ?? $product['description']); ?></textarea></label>
    <div class="manage-form-wide"><button class="admin-button" type="submit">Save product details</button></div>
  </form>
</section>

<section class="manage-panel manage-list-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">PRODUCT OPTIONS</p><h2>Colors, sizes &amp; stock</h2></div></div>
  <form method="post" class="manage-form manage-add-color">
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="action" value="add_color">
    <label>New color<input name="color" maxlength="80" required placeholder="e.g. Navy"></label>
    <label>Image filename<input name="image" maxlength="255" placeholder="e.g. shirt-navy.jpg"></label>
    <div><button class="admin-button" type="submit">Add color</button></div>
  </form>
  <?php if (!$colors) { ?><p class="manage-empty">No colors added for this product yet.</p><?php } ?>
  <?php foreach ($colors as $color) {
    $variant_page_key = 'variants_' . (int)$color['product_color_id'] . '_page';
    $variant_pagination = admin_pagination_page(count($color['variants']), 10, $variant_page_key);
    $visible_variants = array_slice($color['variants'], $variant_pagination['offset'], $variant_pagination['page_size']);
  ?>
    <div class="manage-color-block">
      <form method="post" class="manage-form manage-color-edit">
        <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="update_color">
        <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <label>Color<input name="color" maxlength="80" required value="<?php echo admin_escape($color['color']); ?>"></label>
        <label>Image filename<input name="image" maxlength="255" value="<?php echo admin_escape($color['image']); ?>"></label>
        <div class="manage-row-actions"><button class="manage-small-button" type="submit">Save color</button></div>
      </form>
      <div class="manage-variant-heading"><strong>Sizes and stock (<?php echo count($color['variants']); ?>)</strong>
        <form method="post" class="manage-inline-form">
          <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
          <input type="hidden" name="action" value="add_variant">
          <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
          <input name="size" maxlength="20" required placeholder="Size" aria-label="New size">
          <input type="number" name="stock" min="0" required value="0" aria-label="Starting stock">
          <button class="manage-small-button" type="submit">Add size</button>
        </form>
      </div>
      <?php if ($color['variants']) { ?>
        <div class="admin-table-wrap"><table class="admin-table manage-variant-table">
          <thead><tr><th>Size</th><th>Stock</th><th>Actions</th></tr></thead>
          <tbody><?php foreach ($visible_variants as $variant) { $variant_form_id = 'update-variant-' . $variant['variant_id']; ?>
            <tr>
              <td>
                <form id="<?php echo $variant_form_id; ?>" method="post"></form>
                <input form="<?php echo $variant_form_id; ?>" type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                <input form="<?php echo $variant_form_id; ?>" type="hidden" name="action" value="update_variant">
                <input form="<?php echo $variant_form_id; ?>" type="hidden" name="variant_id" value="<?php echo $variant['variant_id']; ?>">
                <input form="<?php echo $variant_form_id; ?>" name="size" maxlength="20" required value="<?php echo admin_escape($variant['size']); ?>" aria-label="Size">
              </td>
              <td><input form="<?php echo $variant_form_id; ?>" type="number" name="stock" min="0" required value="<?php echo $variant['stock']; ?>" aria-label="Stock"></td>
              <td><button form="<?php echo $variant_form_id; ?>" class="manage-small-button" type="submit">Save</button>
                <form method="post" class="manage-inline-form" onsubmit="return confirm('Delete this size variant? Variants with cart, order, or restock history cannot be deleted.');">
                  <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                  <input type="hidden" name="action" value="delete_variant">
                  <input type="hidden" name="variant_id" value="<?php echo $variant['variant_id']; ?>">
                  <button class="manage-small-button is-danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php } ?></tbody>
        </table></div>
        <?php admin_render_pagination($variant_pagination['page'], $variant_pagination['total_pages'], $variant_page_key); ?>
      <?php } else { ?><p class="manage-empty">No sizes added for this color.</p><?php } ?>
      <form method="post" class="manage-delete-color" onsubmit="return confirm('Delete this color and its unused sizes?');">
        <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="delete_color">
        <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <button class="manage-small-button is-danger" type="submit">Delete color</button>
      </form>
    </div>
  <?php } ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
