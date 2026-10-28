<?php
require_once("../includes/bootstrap.php");
require_once("../../includes/product_images.php");
require_once("../../includes/product_validation.php");

$product_id = product_positive_integer($_GET['id'] ?? null);
if ($product_id === null) {
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
        if ($action === 'add_images') {
            $color_id = product_positive_integer($_POST['product_color_id'] ?? null);
            $uploads = product_file_uploads(is_array($_FILES['images'] ?? null) ? $_FILES['images'] : []);
            $saved_images = [];
            if ($color_id === null) {
                $errors[] = 'Choose a valid color.';
            } elseif (!$uploads || count($uploads) > 10) {
                $errors[] = 'Choose between 1 and 10 images to upload.';
            } else {
                $color_stmt = mysqli_prepare($conn,
                    "SELECT product_color_id FROM product_colors WHERE product_color_id = ? AND product_id = ?"
                );
                mysqli_stmt_bind_param($color_stmt, 'ii', $color_id, $product_id);
                mysqli_stmt_execute($color_stmt);
                $color = mysqli_fetch_assoc(mysqli_stmt_get_result($color_stmt));
                if (!$color) {
                    $errors[] = 'Color not found for this product.';
                } else {
                    $transaction_started = false;
                    try {
                        foreach ($uploads as $upload) {
                            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                                throw new RuntimeException('One or more images could not be uploaded. Please try again.');
                            }
                            $image_path = save_product_image_upload($upload);
                            if ($image_path === null) {
                                throw new RuntimeException('Choose at least one product image.');
                            }
                            $saved_images[] = $image_path;
                        }

                        mysqli_begin_transaction($conn);
                        $transaction_started = true;
                        $count_stmt = mysqli_prepare($conn,
                            "SELECT COALESCE(MAX(sort_order) + 1, 0) AS next_order
                             FROM product_color_images WHERE product_color_id = ?"
                        );
                        mysqli_stmt_bind_param($count_stmt, 'i', $color_id);
                        mysqli_stmt_execute($count_stmt);
                        $next_order = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['next_order'];
                        $image_stmt = mysqli_prepare($conn,
                            "INSERT INTO product_color_images (product_color_id, image_path, sort_order)
                             VALUES (?, ?, ?)"
                        );
                        foreach ($saved_images as $image_path) {
                            mysqli_stmt_bind_param($image_stmt, 'isi', $color_id, $image_path, $next_order);
                            mysqli_stmt_execute($image_stmt);
                            $next_order++;
                        }
                        mysqli_commit($conn);
                        $transaction_started = false;
                        admin_set_flash('success', count($saved_images) . ' image' . (count($saved_images) === 1 ? '' : 's') . ' added.');
                        header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                        exit;
                    } catch (RuntimeException $exception) {
                        if ($transaction_started) {
                            mysqli_rollback($conn);
                        }
                        foreach ($saved_images as $image_path) {
                            $file_path = APP_ROOT . '/images/' . $image_path;
                            if (is_file($file_path) && !unlink($file_path)) {
                                $errors[] = 'An uploaded image could not be removed after the upload failed.';
                            }
                        }
                        $errors[] = $exception->getMessage();
                    } catch (mysqli_sql_exception $exception) {
                        if ($transaction_started) {
                            mysqli_rollback($conn);
                        }
                        foreach ($saved_images as $image_path) {
                            $file_path = APP_ROOT . '/images/' . $image_path;
                            if (is_file($file_path) && !unlink($file_path)) {
                                $errors[] = 'An uploaded image could not be removed after the save failed.';
                            }
                        }
                        $errors[] = 'Could not add product images. Please try again.';
                    }
                }
            }
        } elseif ($action === 'update_product') {
            $supplier_id = product_positive_integer($_POST['supplier_id'] ?? null);
            $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
            $category_id_value = $_POST['category_id'] ?? '';
            $category_id_input = is_string($category_id_value) ? $category_id_value : '';
            $category_id = $category_id_input === '' ? null : product_positive_integer($category_id_input);
            $department = is_string($_POST['department'] ?? null) ? $_POST['department'] : '';
            $price = product_price_value($_POST['price'] ?? null);
            $description = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');

            if ($supplier_id === null) {
                $errors[] = 'Choose a valid brand.';
            }
            if ($name === '' || mb_strlen($name) > 150) {
                $errors[] = 'Enter a product name up to 150 characters.';
            }
            if ($category_id_value !== '' && !is_string($category_id_value)) {
                $errors[] = 'Choose a valid category.';
            }
            if ($category_id_input !== '' && $category_id === null) {
                $errors[] = 'Choose a valid category.';
            }
            if (!in_array($department, ['men', 'women', 'kids'], true)) {
                $errors[] = 'Choose a valid department.';
            }
            if ($price === null) {
                $errors[] = 'Enter a valid non-negative price with no more than two decimal places.';
            }

            if (!$errors) {
                $supplier_stmt = mysqli_prepare($conn, "SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
                mysqli_stmt_bind_param($supplier_stmt, 'i', $supplier_id);
                mysqli_stmt_execute($supplier_stmt);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($supplier_stmt))) {
                    $errors[] = 'Choose a valid brand.';
                }
            }
            if (!$errors && $category_id !== null) {
                $category_stmt = mysqli_prepare($conn, "SELECT category_id FROM categories WHERE category_id = ?");
                mysqli_stmt_bind_param($category_stmt, 'i', $category_id);
                mysqli_stmt_execute($category_stmt);
                $category_row = mysqli_fetch_assoc(mysqli_stmt_get_result($category_stmt));
                if (!$category_row) {
                    $errors[] = 'Choose a valid category.';
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
            if ($color === '' || mb_strlen($color) > 80) {
                $errors[] = 'Enter a color name up to 80 characters.';
            }
            if (mb_strlen($image) > 255) {
                $errors[] = 'Image path must be 255 characters or fewer.';
            }
            if (!$errors) {
                try {
                    mysqli_begin_transaction($conn);
                    $stmt = mysqli_prepare($conn, "INSERT INTO product_colors (product_id, color) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt, 'is', $product_id, $color);
                    mysqli_stmt_execute($stmt);
                    $color_id = mysqli_insert_id($conn);
                    if ($image !== '') {
                        $image_stmt = mysqli_prepare($conn,
                            "INSERT INTO product_color_images (product_color_id, image_path, sort_order)
                             VALUES (?, ?, 0)"
                        );
                        mysqli_stmt_bind_param($image_stmt, 'is', $color_id, $image);
                        mysqli_stmt_execute($image_stmt);
                    }
                    mysqli_commit($conn);
                    admin_set_flash('success', 'Color added.');
                    header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    mysqli_rollback($conn);
                    $errors[] = $exception->getCode() === 1062 ? 'This product already has that color.' : 'Could not add the color.';
                }
            }
        } elseif ($action === 'update_color') {
            $color_id = product_positive_integer($_POST['product_color_id'] ?? null);
            $color = trim(is_string($_POST['color'] ?? null) ? $_POST['color'] : '');
            $image = trim(is_string($_POST['image'] ?? null) ? $_POST['image'] : '');
            if ($color_id === null || $color === '' || mb_strlen($color) > 80 || mb_strlen($image) > 255) {
                $errors[] = 'Enter a valid color name and image path.';
            } else {
                try {
                    mysqli_begin_transaction($conn);
                    $stmt = mysqli_prepare($conn,
                        "UPDATE product_colors
                         SET color = ?
                         WHERE product_color_id = ? AND product_id = ?"
                    );
                    mysqli_stmt_bind_param($stmt, 'sii', $color, $color_id, $product_id);
                    mysqli_stmt_execute($stmt);
                    $image_stmt = mysqli_prepare($conn,
                        "SELECT image_id
                         FROM product_color_images
                         WHERE product_color_id = ?
                         ORDER BY sort_order, image_id
                         LIMIT 1
                         FOR UPDATE"
                    );
                    mysqli_stmt_bind_param($image_stmt, 'i', $color_id);
                    mysqli_stmt_execute($image_stmt);
                    $primary_image = mysqli_fetch_assoc(mysqli_stmt_get_result($image_stmt));
                    if ($primary_image && $image !== '') {
                        $primary_image_id = (int)$primary_image['image_id'];
                        $save_image_stmt = mysqli_prepare($conn,
                            "UPDATE product_color_images
                             SET image_path = ?
                             WHERE image_id = ? AND product_color_id = ?"
                        );
                        mysqli_stmt_bind_param($save_image_stmt, 'sii', $image, $primary_image_id, $color_id);
                        mysqli_stmt_execute($save_image_stmt);
                    } elseif ($primary_image) {
                        $primary_image_id = (int)$primary_image['image_id'];
                        $delete_image_stmt = mysqli_prepare($conn,
                            "DELETE FROM product_color_images
                             WHERE image_id = ? AND product_color_id = ?"
                        );
                        mysqli_stmt_bind_param($delete_image_stmt, 'ii', $primary_image_id, $color_id);
                        mysqli_stmt_execute($delete_image_stmt);
                    } elseif ($image !== '') {
                        $insert_image_stmt = mysqli_prepare($conn,
                            "INSERT INTO product_color_images (product_color_id, image_path, sort_order)
                             VALUES (?, ?, 0)"
                        );
                        mysqli_stmt_bind_param($insert_image_stmt, 'is', $color_id, $image);
                        mysqli_stmt_execute($insert_image_stmt);
                    }
                    mysqli_commit($conn);
                    admin_set_flash('success', 'Color updated.');
                    header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id);
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    mysqli_rollback($conn);
                    $errors[] = $exception->getCode() === 1062 ? 'This product already has that color.' : 'Could not update the color.';
                }
            }
        } elseif ($action === 'delete_color') {
            $color_id = product_positive_integer($_POST['product_color_id'] ?? null);
            if ($color_id === null) {
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
            $color_id = product_positive_integer($_POST['product_color_id'] ?? null);
            $size = trim(is_string($_POST['size'] ?? null) ? $_POST['size'] : '');
            $stock = product_nonnegative_integer($_POST['stock'] ?? null);
            if ($color_id === null || $size === '' || mb_strlen($size) > 20 || $stock === null) {
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
            $variant_id = product_positive_integer($_POST['variant_id'] ?? null);
            $size = trim(is_string($_POST['size'] ?? null) ? $_POST['size'] : '');
            $stock = product_nonnegative_integer($_POST['stock'] ?? null);
            if ($variant_id === null || $size === '' || mb_strlen($size) > 20 || $stock === null) {
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
            $variant_id = product_positive_integer($_POST['variant_id'] ?? null);
            if ($variant_id === null) {
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
    "SELECT pc.product_color_id, pc.color, v.variant_id, v.size, v.stock
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
        $colors[$color_id] = ['product_color_id' => $color_id, 'color' => $row['color'], 'image' => '', 'variants' => []];
    }
    if ($row['variant_id'] !== null) {
        $colors[$color_id]['variants'][] = ['variant_id' => (int)$row['variant_id'], 'size' => $row['size'], 'stock' => (int)$row['stock']];
    }
}
$color_images = product_color_image_paths($conn, array_keys($colors));
foreach ($colors as $color_id => &$color) {
    $color['image'] = $color_images[$color_id][0] ?? '';
}
unset($color);
$flash = admin_take_flash();
$admin_page_title = 'Edit Product';
$admin_active_page = 'products';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">PRODUCT CATALOG</p><h1>Edit Product</h1><p>Update product details, colors, sizes, and stock.</p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-products/">&larr; All products</a>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>

<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">PRODUCT DETAILS</p><h2><?php echo admin_escape($product['name']); ?></h2></div></div>
  <form method="post" class="manage-form manage-product-create" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="action" value="update_product">
    <label>Product name<input name="name" value="<?php echo admin_escape(is_string($_POST['name'] ?? null) ? $_POST['name'] : $product['name']); ?>"></label>
    <label>Brand<select name="supplier_id"><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo (int)$supplier['supplier_id'] === (int)$product['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
    <label>Category<select name="category_id"><option value="">Uncategorized</option><?php foreach ($categories as $category_option) { $selected_category_id = is_string($_POST['category_id'] ?? null) ? $_POST['category_id'] : (string)$product['category_id']; ?><option value="<?php echo (int)$category_option['category_id']; ?>" <?php echo $selected_category_id === (string)$category_option['category_id'] ? 'selected' : ''; ?>><?php echo admin_escape($category_option['name']); ?></option><?php } ?></select></label>
    <label>Department<select name="department"><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $key => $label) { ?><option value="<?php echo $key; ?>" <?php echo $product['department'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></label>
    <label>Price (₱)<input type="text" inputmode="decimal" name="price" value="<?php echo admin_escape(is_string($_POST['price'] ?? null) ? $_POST['price'] : $product['price']); ?>"></label>
    <label class="manage-form-wide">Description<textarea name="description" rows="3"><?php echo admin_escape(is_string($_POST['description'] ?? null) ? $_POST['description'] : $product['description']); ?></textarea></label>
    <div class="manage-form-wide"><button class="admin-button" type="submit">Save product details</button></div>
  </form>
</section>

<section class="manage-panel manage-list-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">PRODUCT OPTIONS</p><h2>Colors, sizes &amp; stock</h2></div></div>
  <div class="manage-color-gallery-action">
    <details class="catalog-disclosure catalog-image-disclosure">
      <summary class="admin-button admin-button-light"><span class="summary-open-label">View All Images</span><span class="summary-close-label">Close</span></summary>
      <div class="catalog-modal">
        <section class="catalog-dialog catalog-product-dialog" role="dialog" aria-modal="true" aria-labelledby="product-images-title">
          <div class="catalog-dialog-heading">
            <div><p class="admin-eyebrow">PRODUCT GALLERY</p><h2 id="product-images-title">All images — <?php echo admin_escape($product['name']); ?></h2></div>
          </div>
          <?php $has_product_images = false; foreach ($colors as $color) { $images_for_color = $color_images[(int)$color['product_color_id']] ?? []; if ($images_for_color) { $has_product_images = true; ?>
            <section class="product-admin-image-group">
              <h3><?php echo admin_escape($color['color']); ?></h3>
              <div class="product-admin-image-grid">
                <?php foreach ($images_for_color as $image_path) { ?>
                  <figure><img src="<?php echo BASE_URL; ?>images/<?php echo admin_escape($image_path); ?>" alt="<?php echo admin_escape($product['name'] . ' - ' . $color['color']); ?>" loading="lazy"></figure>
                <?php } ?>
              </div>
            </section>
          <?php } } ?>
          <?php if (!$has_product_images) { ?><p class="manage-empty">No images have been added to this product yet.</p><?php } ?>
        </section>
      </div>
    </details>
  </div>
  <form method="post" class="manage-form manage-add-color" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="action" value="add_color">
    <label>New color<input name="color" placeholder="e.g. Navy"></label>
    <label>Image filename<input name="image" placeholder="e.g. shirt-navy.jpg"></label>
    <div><button class="admin-button" type="submit">Add color</button></div>
  </form>
  <?php if (!$colors) { ?><p class="manage-empty">No colors added for this product yet.</p><?php } ?>
  <?php foreach ($colors as $color) {
    $variant_page_key = 'variants_' . (int)$color['product_color_id'] . '_page';
    $variant_pagination = admin_pagination_page(count($color['variants']), 10, $variant_page_key);
    $visible_variants = array_slice($color['variants'], $variant_pagination['offset'], $variant_pagination['page_size']);
  ?>
    <div class="manage-color-block">
      <form method="post" class="manage-form manage-color-edit" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="update_color">
        <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <label>Color<input name="color" value="<?php echo admin_escape($color['color']); ?>"></label>
        <label>Image filename<input name="image" value="<?php echo admin_escape($color['image']); ?>"></label>
        <div class="manage-row-actions"><button class="manage-small-button" type="submit">Save color</button></div>
      </form>
      <form method="post" enctype="multipart/form-data" class="manage-form manage-color-images" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="add_images">
        <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <label>Add images for <?php echo admin_escape($color['color']); ?><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><small class="manage-secondary">Select up to 10 JPEG, PNG, or WebP files at once · 5 MB each.</small></label>
        <button class="manage-small-button" type="submit">Upload images</button>
      </form>
      <div class="manage-variant-heading"><strong>Sizes and stock (<?php echo count($color['variants']); ?>)</strong>
        <form method="post" class="manage-inline-form" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
          <input type="hidden" name="action" value="add_variant">
          <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
          <input name="size" placeholder="Size" aria-label="New size">
          <input type="text" inputmode="numeric" name="stock" value="0" aria-label="Starting stock">
          <button class="manage-small-button" type="submit">Add size</button>
        </form>
      </div>
      <?php if ($color['variants']) { ?>
        <div class="admin-table-wrap"><table class="admin-table manage-variant-table">
          <thead><tr><th>Size</th><th>Stock</th><th>Actions</th></tr></thead>
          <tbody><?php foreach ($visible_variants as $variant) { $variant_form_id = 'update-variant-' . $variant['variant_id']; ?>
            <tr>
              <td>
                <form id="<?php echo $variant_form_id; ?>" method="post" novalidate></form>
                <input form="<?php echo $variant_form_id; ?>" type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                <input form="<?php echo $variant_form_id; ?>" type="hidden" name="action" value="update_variant">
                <input form="<?php echo $variant_form_id; ?>" type="hidden" name="variant_id" value="<?php echo $variant['variant_id']; ?>">
                <input form="<?php echo $variant_form_id; ?>" name="size" value="<?php echo admin_escape($variant['size']); ?>" aria-label="Size">
              </td>
              <td><input form="<?php echo $variant_form_id; ?>" type="text" inputmode="numeric" name="stock" value="<?php echo $variant['stock']; ?>" aria-label="Stock"></td>
              <td><button form="<?php echo $variant_form_id; ?>" class="manage-small-button" type="submit">Save</button>
                <form method="post" class="manage-inline-form" novalidate>
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
      <form method="post" class="manage-delete-color" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
        <input type="hidden" name="action" value="delete_color">
        <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <button class="manage-small-button is-danger" type="submit">Delete color</button>
      </form>
    </div>
  <?php } ?>
</section>
<?php include("../includes/footer.php"); ?>
