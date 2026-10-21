<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$errors = [];

function save_product_image_upload(array $upload): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The product image could not be uploaded. Please try again.');
    }
    if (!isset($upload['tmp_name'], $upload['size']) || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('Choose a valid product image.');
    }
    if ((int)$upload['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Product images must be 5 MB or smaller.');
    }

    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $file_info->file($upload['tmp_name']);
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $image_info = @getimagesize($upload['tmp_name']);
    if (!isset($allowed_types[$mime_type]) || !$image_info || $image_info['mime'] !== $mime_type) {
        throw new RuntimeException('Choose a JPEG, PNG, or WebP image for the product.');
    }

    $directory = __DIR__ . '/../../images/products';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The product image could not be saved. Please try again.');
    }

    $filename = 'product-' . bin2hex(random_bytes(12)) . '.' . $allowed_types[$mime_type];
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The product image could not be saved. Please try again.');
    }

    return 'products/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $supplier_id = filter_var($_POST['supplier_id'] ?? null, FILTER_VALIDATE_INT);
        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $category_id_input = $_POST['category_id'] ?? '';
        $category_id = $category_id_input === '' ? null : filter_var($category_id_input, FILTER_VALIDATE_INT);
        $department = $_POST['department'] ?? '';
        $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $description = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');
        $image_color = trim(is_string($_POST['image_color'] ?? null) ? $_POST['image_color'] : '');
        $image_upload = $_FILES['product_image'] ?? ['error' => UPLOAD_ERR_NO_FILE];
        $saved_image = null;

        if ($supplier_id === false || $supplier_id < 1) {
            $errors[] = 'Choose a valid brand.';
        }
        if ($name === '' || mb_strlen($name) > 150) {
            $errors[] = 'Enter a product name up to 150 characters.';
        }
        if ($category_id_input !== '' && ($category_id === false || $category_id < 1)) {
            $errors[] = 'Choose a valid category.';
        }
        if (!in_array($department, ['men', 'women', 'kids'], true)) {
            $errors[] = 'Choose a valid department.';
        }
        if ($price === false || $price < 0 || $price > 99999999.99) {
            $errors[] = 'Enter a valid product price.';
        }
        if (($image_upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            && ($image_color === '' || mb_strlen($image_color) > 80)) {
            $errors[] = 'Enter an image color name up to 80 characters when uploading an image.';
        }

        if (!$errors) {
            try {
                $saved_image = save_product_image_upload($image_upload);
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if (!$errors) {
            $category = null;
            if ($category_id !== null) {
                $category_stmt = mysqli_prepare($conn, "SELECT name FROM categories WHERE category_id = ?");
                mysqli_stmt_bind_param($category_stmt, 'i', $category_id);
                mysqli_stmt_execute($category_stmt);
                $category_row = mysqli_fetch_assoc(mysqli_stmt_get_result($category_stmt));
                if (!$category_row) {
                    $errors[] = 'Choose a valid category.';
                } else {
                    $category = $category_row['name'];
                }
            }
        }

        if (!$errors) {
            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, "INSERT INTO products (supplier_id, name, category_id, category, department, price, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'isissds', $supplier_id, $name, $category_id, $category, $department, $price, $description);
                mysqli_stmt_execute($stmt);
                $new_product_id = mysqli_insert_id($conn);

                if ($saved_image !== null) {
                    $color_stmt = mysqli_prepare($conn, "INSERT INTO product_colors (product_id, color, image) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($color_stmt, 'iss', $new_product_id, $image_color, $saved_image);
                    mysqli_stmt_execute($color_stmt);
                }

                mysqli_commit($conn);
                admin_set_flash('success', 'Product created. Add its colors and sizes below.');
                header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $new_product_id);
                exit;
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                if ($saved_image !== null) {
                    $image_path = __DIR__ . '/../../images/' . $saved_image;
                    if (is_file($image_path) && !unlink($image_path)) {
                        $errors[] = 'The product could not be created, and its uploaded image could not be removed.';
                    }
                }
                if (!$errors) {
                    $errors[] = 'The product could not be created. Please check the details and try again.';
                }
            }
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
$categories_result = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
$categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
$products_result = mysqli_query($conn,
    "SELECT p.product_id, p.name, COALESCE(c.name, p.category) AS category, p.department, p.price, s.name AS supplier_name,
            COUNT(DISTINCT pc.product_color_id) AS color_count,
            (SELECT COUNT(*)
             FROM order_items oi
             JOIN product_variants order_variant ON order_variant.variant_id = oi.variant_id
             JOIN product_colors order_color ON order_color.product_color_id = order_variant.product_color_id
             WHERE order_color.product_id = p.product_id) AS order_count,
            COALESCE(SUM(v.stock), 0) AS stock_count
     FROM products p
     JOIN suppliers s ON s.supplier_id = p.supplier_id
     LEFT JOIN categories c ON c.category_id = p.category_id
     LEFT JOIN product_colors pc ON pc.product_id = p.product_id
     LEFT JOIN product_variants v ON v.product_color_id = pc.product_color_id
     GROUP BY p.product_id, p.name, c.name, p.category, p.department, p.price, s.name
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
  <?php if (!$suppliers) { ?>
    <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Create a product</h2></div></div>
    <p class="manage-empty">Create a brand first before adding a product.</p>
  <?php } else { ?>
    <form method="post" enctype="multipart/form-data" class="manage-form manage-product-create">
      <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
      <input type="hidden" name="action" value="create">
      <div class="manage-product-top">
        <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Create a product</h2></div></div>
        <div class="product-image-picker">
          <div class="product-image-preview" data-product-image-preview>
            <span>Image preview</span>
            <img alt="Selected product image preview" hidden>
          </div>
          <div class="product-image-picker-actions">
            <input class="product-image-input" id="product-image-input" type="file" name="product_image" accept="image/jpeg,image/png,image/webp">
            <label class="admin-button admin-button-light product-image-choose" for="product-image-input">Choose image</label>
            <small class="manage-secondary" data-product-image-name>No image selected</small>
            <small class="manage-secondary">JPEG, PNG, or WebP · 5 MB max</small>
          </div>
        </div>
      </div>
      <div class="manage-product-fields">
        <label>Product name<input name="name" maxlength="150" required value="<?php echo admin_escape($_POST['name'] ?? ''); ?>"></label>
        <label>Brand<select name="supplier_id" required><option value="">Choose a brand</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo (string)($_POST['supplier_id'] ?? '') === (string)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
        <label>Category<select name="category_id"><option value="">Uncategorized</option><?php foreach ($categories as $category_option) { ?><option value="<?php echo (int)$category_option['category_id']; ?>" <?php echo (string)($_POST['category_id'] ?? '') === (string)$category_option['category_id'] ? 'selected' : ''; ?>><?php echo admin_escape($category_option['name']); ?></option><?php } ?></select></label>
        <label>Department<select name="department" required><option value="men">Men</option><option value="women">Women</option><option value="kids">Kids</option></select></label>
        <label>Price (₱)<input type="number" name="price" min="0" max="99999999.99" step="0.01" required value="<?php echo admin_escape($_POST['price'] ?? ''); ?>"></label>
        <label>Image color<input name="image_color" maxlength="80" value="<?php echo admin_escape($_POST['image_color'] ?? ''); ?>" aria-describedby="product-image-color-help"><small id="product-image-color-help" class="manage-secondary">Required when uploading an image. Add a matching color name.</small></label>
        <label class="manage-form-wide">Description<textarea name="description" rows="3"><?php echo admin_escape($_POST['description'] ?? ''); ?></textarea></label>
        <div class="manage-form-wide"><button class="admin-button" type="submit">Create product</button></div>
      </div>
    </form>
    <script>
    (function () {
      var input = document.getElementById('product-image-input');
      var preview = document.querySelector('[data-product-image-preview]');
      var image = preview.querySelector('img');
      var placeholder = preview.querySelector('span');
      var filename = document.querySelector('[data-product-image-name]');
      var previewUrl = null;

      input.addEventListener('change', function () {
        if (previewUrl) {
          URL.revokeObjectURL(previewUrl);
          previewUrl = null;
        }

        var file = input.files && input.files[0];
        if (!file) {
          image.hidden = true;
          image.removeAttribute('src');
          placeholder.hidden = false;
          filename.textContent = 'No image selected';
          return;
        }

        previewUrl = URL.createObjectURL(file);
        image.src = previewUrl;
        image.hidden = false;
        placeholder.hidden = true;
        filename.textContent = file.name;
      });
    })();
    </script>
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
