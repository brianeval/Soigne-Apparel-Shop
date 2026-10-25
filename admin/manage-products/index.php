<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/product_images.php';
require_once __DIR__ . '/../../includes/product_validation.php';

$errors = [];
$product_size_guide = require __DIR__ . '/../../includes/product_size_guide.php';

function product_size_options(array $guide, string $department, ?string $category): array
{
    $category_group = $category === null
        ? null
        : ($guide['category_groups'][strtolower(trim($category))] ?? null);

    if ($category_group !== null) {
        return $guide['sizes'][$department][$category_group] ?? [];
    }

    return array_values(array_unique(array_merge(
        $guide['sizes'][$department]['top'] ?? [],
        $guide['sizes'][$department]['bottom'] ?? []
    )));
}

function save_brand_logo_upload(array $upload): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The brand logo could not be uploaded. Please try again.');
    }
    if (!isset($upload['tmp_name'], $upload['size']) || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('Choose a valid brand logo image.');
    }
    if ((int)$upload['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Brand logos must be 5 MB or smaller.');
    }

    $mime_type = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $image_info = @getimagesize($upload['tmp_name']);
    if (!isset($allowed_types[$mime_type]) || !$image_info || $image_info['mime'] !== $mime_type) {
        throw new RuntimeException('Choose a JPEG, PNG, or WebP image for the brand logo.');
    }

    $directory = __DIR__ . '/../../images/brands';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The brand logo could not be saved. Please try again.');
    }

    $filename = 'brand-' . bin2hex(random_bytes(12)) . '.' . $allowed_types[$mime_type];
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The brand logo could not be saved. Please try again.');
    }

    return $filename;
}

$categories_result = mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name");
$categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);
$category_names = [];
foreach ($categories as $category_row) {
    $category_names[(int)$category_row['category_id']] = $category_row['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'add_category') {
        $category_name = trim(is_string($_POST['category_name'] ?? null) ? $_POST['category_name'] : '');
        if ($category_name === '' || mb_strlen($category_name) > 50) {
            $errors[] = 'Enter a category name up to 50 characters.';
        } else {
            try {
                $category_stmt = mysqli_prepare($conn, "INSERT INTO categories (name) VALUES (?)");
                mysqli_stmt_bind_param($category_stmt, 's', $category_name);
                mysqli_stmt_execute($category_stmt);
                admin_set_flash('success', 'Category added to the catalog.');
                header('Location: ' . BASE_URL . 'admin/manage-products/');
                exit;
            } catch (mysqli_sql_exception $exception) {
                $errors[] = $exception->getCode() === 1062
                    ? 'A category with that name already exists.'
                    : 'Could not add this category. Please try again.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'add_brand') {
        $brand_name = trim(is_string($_POST['brand_name'] ?? null) ? $_POST['brand_name'] : '');
        $brand_logo = null;
        if ($brand_name === '' || mb_strlen($brand_name) > 120) {
            $errors[] = 'Enter a brand name up to 120 characters.';
        } else {
            try {
                $brand_upload = is_array($_FILES['brand_logo'] ?? null) ? $_FILES['brand_logo'] : [];
                $brand_logo = save_brand_logo_upload($brand_upload);
                $brand_stmt = mysqli_prepare($conn, "INSERT INTO suppliers (name, image) VALUES (?, ?)");
                mysqli_stmt_bind_param($brand_stmt, 'ss', $brand_name, $brand_logo);
                mysqli_stmt_execute($brand_stmt);
                admin_set_flash('success', 'Brand added to the catalog.');
                header('Location: ' . BASE_URL . 'admin/manage-products/');
                exit;
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            } catch (mysqli_sql_exception $exception) {
                if ($brand_logo !== null) {
                    $logo_path = __DIR__ . '/../../images/brands/' . $brand_logo;
                    if (is_file($logo_path) && !unlink($logo_path)) {
                        $errors[] = 'The brand already exists, and its uploaded logo could not be removed.';
                    }
                }
                if (!$errors) {
                    $errors[] = $exception->getCode() === 1062
                        ? 'A brand with that name already exists.'
                        : 'Could not add this brand. Please try again.';
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'create') {
        $supplier_id = product_positive_integer($_POST['supplier_id'] ?? null);
        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $category_id_value = $_POST['category_id'] ?? '';
        $category_id_input = is_string($category_id_value) ? $category_id_value : '';
        $category_id = $category_id_input === '' ? null : product_positive_integer($category_id_input);
        $category_name = null;
        $department = is_string($_POST['department'] ?? null) ? $_POST['department'] : '';
        $price = product_price_value($_POST['price'] ?? null);
        $description = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');
        $posted_colors = $_POST['colors'] ?? [];
        $colors_to_create = [];
        $saved_images = [];

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
        } elseif ($category_id !== null) {
            $category_name = $category_names[$category_id] ?? null;
            if ($category_name === null) {
                $errors[] = 'Choose a valid category.';
            }
        }
        if (!in_array($department, ['men', 'women', 'kids'], true)) {
            $errors[] = 'Choose a valid department.';
        }
        if ($price === null) {
            $errors[] = 'Enter a valid non-negative product price with no more than two decimal places.';
        }
        if (!$errors) {
            $supplier_stmt = mysqli_prepare($conn, "SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
            mysqli_stmt_bind_param($supplier_stmt, 'i', $supplier_id);
            mysqli_stmt_execute($supplier_stmt);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($supplier_stmt))) {
                $errors[] = 'Choose a valid brand.';
            }
        }
        $allowed_sizes = in_array($department, ['men', 'women', 'kids'], true)
            ? product_size_options($product_size_guide, $department, $category_name)
            : [];
        if (!is_array($posted_colors) || count($posted_colors) > 20) {
            $errors[] = 'Add no more than 20 product colors.';
        } else {
            foreach ($posted_colors as $color_index => $posted_color) {
                if (!is_array($posted_color)) {
                    $errors[] = 'Enter a valid color and select at least one size for each color.';
                    break;
                }

                $color_name = trim(is_string($posted_color['name'] ?? null) ? $posted_color['name'] : '');
                $selected_sizes = $posted_color['sizes'] ?? [];
                $posted_stocks = is_array($posted_color['stocks'] ?? null) ? $posted_color['stocks'] : [];
                $upload = is_array($_FILES['color_images'] ?? null) ? $_FILES['color_images'] : [];
                $color_uploads = product_color_uploads($upload, (int)$color_index);
                $has_image = (bool)$color_uploads;

                if ($color_name === '' && !$selected_sizes && !$has_image) {
                    continue;
                }
                if ($color_name === '' || mb_strlen($color_name) > 80
                    || !is_array($selected_sizes) || !$selected_sizes) {
                    $errors[] = 'Enter a color name up to 80 characters and select at least one size for each color.';
                    continue;
                }
                if (count($color_uploads) > 10) {
                    $errors[] = 'Choose no more than 10 images for each color.';
                    continue;
                }
                $valid_uploads = [];
                foreach ($color_uploads as $color_upload) {
                    if (($color_upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        $errors[] = 'A color image could not be uploaded. Please try again.';
                        continue 2;
                    }
                    $valid_uploads[] = $color_upload;
                }

                $valid_sizes = [];
                $size_stocks = [];
                $invalid_size = false;
                foreach ($selected_sizes as $size) {
                    if (!is_string($size) || !in_array($size, $allowed_sizes, true)) {
                        $errors[] = 'One or more selected sizes are not available for this department and category.';
                        $invalid_size = true;
                        break;
                    }
                    $valid_sizes[] = $size;
                    $raw_stock = $posted_stocks[$size] ?? null;
                    $stock_input = product_nonnegative_integer($raw_stock);
                    if ($stock_input === null || $stock_input > 1000) {
                        $errors[] = 'Enter a valid stock amount between 0 and 1,000 for each selected size.';
                        $invalid_size = true;
                        break;
                    }
                    $size_stocks[$size] = $stock_input;
                }
                $valid_sizes = array_values(array_unique($valid_sizes));
                if ($invalid_size) {
                    continue;
                }
                if (!$valid_sizes) {
                    $errors[] = 'Select at least one valid size for each color.';
                    continue;
                }

                $colors_to_create[] = [
                    'name' => $color_name,
                    'sizes' => $valid_sizes,
                    'stocks' => $size_stocks,
                    'uploads' => $valid_uploads,
                ];
            }
            if (!$colors_to_create && !$errors) {
                $errors[] = 'Add at least one color and select at least one size.';
            }
        }

        if (!$errors) {
            foreach ($colors_to_create as $index => $color) {
                $colors_to_create[$index]['images'] = [];
                foreach ($color['uploads'] as $upload) {
                    try {
                        $image_path = save_product_image_upload($upload);
                        if ($image_path !== null) {
                            $colors_to_create[$index]['images'][] = $image_path;
                            $saved_images[] = $image_path;
                        }
                    } catch (RuntimeException $exception) {
                        $errors[] = $exception->getMessage();
                        break 2;
                    }
                }
            }
        }

        if ($errors && $saved_images) {
            foreach ($saved_images as $saved_image) {
                $image_path = __DIR__ . '/../../images/' . $saved_image;
                if (is_file($image_path) && !unlink($image_path)) {
                    $errors[] = 'An uploaded image could not be removed after validation failed.';
                }
            }
            $saved_images = [];
        }

        if (!$errors) {
            mysqli_begin_transaction($conn);
            try {
                $stmt = mysqli_prepare($conn, "INSERT INTO products (supplier_id, name, category_id, department, price, description) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'isisds', $supplier_id, $name, $category_id, $department, $price, $description);
                mysqli_stmt_execute($stmt);
                $new_product_id = mysqli_insert_id($conn);

                $color_stmt = mysqli_prepare($conn, "INSERT INTO product_colors (product_id, color, image) VALUES (?, ?, ?)");
                $image_stmt = mysqli_prepare($conn, "INSERT INTO product_color_images (product_color_id, image_path, sort_order) VALUES (?, ?, ?)");
                $variant_stmt = mysqli_prepare($conn, "INSERT INTO product_variants (product_color_id, size, stock) VALUES (?, ?, ?)");
                foreach ($colors_to_create as $color) {
                    $image_path = $color['images'][0] ?? null;
                    mysqli_stmt_bind_param($color_stmt, 'iss', $new_product_id, $color['name'], $image_path);
                    mysqli_stmt_execute($color_stmt);
                    $new_color_id = mysqli_insert_id($conn);

                    foreach ($color['images'] as $sort_order => $image_path) {
                        mysqli_stmt_bind_param($image_stmt, 'isi', $new_color_id, $image_path, $sort_order);
                        mysqli_stmt_execute($image_stmt);
                    }
                    foreach ($color['sizes'] as $size) {
                        $stock = $color['stocks'][$size];
                        mysqli_stmt_bind_param($variant_stmt, 'isi', $new_color_id, $size, $stock);
                        mysqli_stmt_execute($variant_stmt);
                    }
                }

                mysqli_commit($conn);
                admin_set_flash('success', 'Product created with its selected colors and sizes.');
                header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $new_product_id);
                exit;
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                foreach ($saved_images as $saved_image) {
                    $image_path = __DIR__ . '/../../images/' . $saved_image;
                    if (is_file($image_path) && !unlink($image_path)) {
                        $errors[] = 'The product could not be created, and an uploaded image could not be removed.';
                    }
                }
                if (!$errors) {
                    $errors[] = 'The product could not be created. Please check the details and try again.';
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $product_id = product_positive_integer($_POST['product_id'] ?? null);
        if ($product_id === null) {
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
$product_total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products"))['total'];
$product_pagination = admin_pagination_page($product_total);
$product_limit = $product_pagination['page_size'];
$product_offset = $product_pagination['offset'];
$products_result = mysqli_query($conn,
    "SELECT p.product_id, p.name, c.name AS category, p.department, p.price, s.name AS supplier_name,
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
     GROUP BY p.product_id, p.name, c.name, p.department, p.price, s.name
     ORDER BY p.product_id DESC
     LIMIT $product_limit OFFSET $product_offset"
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

<div class="catalog-actions" aria-label="Catalog actions">
  <button class="admin-button" type="button" data-open-dialog="add-product-dialog">Add Product</button>
  <button class="admin-button admin-button-light" type="button" data-open-dialog="add-category-dialog">Add Category</button>
  <button class="admin-button admin-button-light" type="button" data-open-dialog="add-brand-dialog">Add Brand</button>
</div>

<dialog class="catalog-dialog catalog-form-dialog" id="add-category-dialog" aria-labelledby="add-category-title"<?php echo $errors && ($_POST['action'] ?? '') === 'add_category' ? ' data-open-on-load' : ''; ?>>
  <div class="catalog-dialog-heading">
    <div><p class="admin-eyebrow">ADD TO CATALOG</p><h2 id="add-category-title">Add Category</h2></div>
    <button class="catalog-dialog-close" type="button" aria-label="Close dialog" data-close-dialog>Close</button>
  </div>
  <form method="post" class="manage-form catalog-simple-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="action" value="add_category">
    <label>Category name<input name="category_name" value="<?php echo admin_escape(($_POST['action'] ?? '') === 'add_category' && is_string($_POST['category_name'] ?? null) ? $_POST['category_name'] : ''); ?>"></label>
    <div class="manage-form-buttons">
      <button class="admin-button" type="submit">Add Category</button>
      <button class="manage-small-button" type="button" data-close-dialog>Cancel</button>
    </div>
  </form>
</dialog>

<dialog class="catalog-dialog catalog-form-dialog" id="add-brand-dialog" aria-labelledby="add-brand-title"<?php echo $errors && ($_POST['action'] ?? '') === 'add_brand' ? ' data-open-on-load' : ''; ?>>
  <div class="catalog-dialog-heading">
    <div><p class="admin-eyebrow">ADD TO CATALOG</p><h2 id="add-brand-title">Add Brand</h2></div>
    <button class="catalog-dialog-close" type="button" aria-label="Close dialog" data-close-dialog>Close</button>
  </div>
  <form method="post" enctype="multipart/form-data" class="manage-form catalog-simple-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="action" value="add_brand">
    <label>Brand name<input name="brand_name" value="<?php echo admin_escape(($_POST['action'] ?? '') === 'add_brand' && is_string($_POST['brand_name'] ?? null) ? $_POST['brand_name'] : ''); ?>"></label>
    <label>Brand logo (optional)<input type="file" name="brand_logo" accept="image/jpeg,image/png,image/webp"><small class="manage-secondary">JPEG, PNG, or WebP · 5 MB max</small></label>
    <div class="manage-form-buttons">
      <button class="admin-button" type="submit">Add Brand</button>
      <button class="manage-small-button" type="button" data-close-dialog>Cancel</button>
    </div>
  </form>
</dialog>

<dialog class="catalog-dialog catalog-product-dialog" id="add-product-dialog" aria-labelledby="add-product-title"<?php echo $errors && ($_POST['action'] ?? '') === 'create' ? ' data-open-on-load' : ''; ?>>
  <div class="catalog-dialog-heading">
    <div><p class="admin-eyebrow">ADD TO CATALOG</p><h2 id="add-product-title">Create a product</h2></div>
    <button class="catalog-dialog-close" type="button" aria-label="Close dialog" data-close-dialog>Close</button>
  </div>
<?php if (!$suppliers) { ?>
  <section class="manage-panel product-create-panel">
    <p class="manage-empty">Create a brand first before adding a product.</p>
  </section>
<?php } else { ?>
  <?php
  $form_colors = $_POST['colors'] ?? [0 => ['name' => '', 'sizes' => [], 'stocks' => []]];
  if (!is_array($form_colors) || !$form_colors) {
      $form_colors = [0 => ['name' => '', 'sizes' => [], 'stocks' => []]];
  }
  $form_colors = array_slice($form_colors, 0, 20, true);
  $all_size_options = [];
  foreach ($product_size_guide['sizes'] as $department_sizes) {
      foreach ($department_sizes as $group_sizes) {
          $all_size_options = array_merge($all_size_options, $group_sizes);
      }
  }
  $all_size_options = array_values(array_unique($all_size_options));
  $selected_department = is_string($_POST['department'] ?? null) ? $_POST['department'] : 'men';
  $selected_category_id = product_positive_integer($_POST['category_id'] ?? null);
  $selected_category_name = $selected_category_id === null ? null : ($category_names[$selected_category_id] ?? null);
  $selected_size_options = product_size_options($product_size_guide, $selected_department, $selected_category_name);
  ?>
  <form method="post" enctype="multipart/form-data" class="manage-form manage-product-create create-product-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="action" value="create">

    <section class="manage-panel product-create-panel">
      <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Product details</h2></div></div>
      <div class="manage-product-fields">
        <label class="product-field-wide">Product name<input name="name" value="<?php echo admin_escape(is_string($_POST['name'] ?? null) ? $_POST['name'] : ''); ?>"></label>
        <label>Price (₱)<input type="text" inputmode="decimal" name="price" value="<?php echo admin_escape(is_string($_POST['price'] ?? null) ? $_POST['price'] : ''); ?>"></label>
        <label>Department<select id="product-department" name="department"><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $department_value => $department_label) { ?><option value="<?php echo $department_value; ?>" <?php echo $selected_department === $department_value ? 'selected' : ''; ?>><?php echo $department_label; ?></option><?php } ?></select></label>
        <label>Brand<select name="supplier_id"><option value="">Choose a brand</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo product_positive_integer($_POST['supplier_id'] ?? null) === (int)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
        <label>Category<select id="product-category" name="category_id"><option value="">Uncategorized</option><?php foreach ($categories as $category_option) { $category_group = $product_size_guide['category_groups'][strtolower(trim($category_option['name']))] ?? ''; ?><option value="<?php echo (int)$category_option['category_id']; ?>" data-size-group="<?php echo admin_escape($category_group); ?>" <?php echo $selected_category_id === (int)$category_option['category_id'] ? 'selected' : ''; ?>><?php echo admin_escape($category_option['name']); ?></option><?php } ?></select></label>
        <label class="product-field-wide">Description<textarea name="description" rows="4"><?php echo admin_escape(is_string($_POST['description'] ?? null) ? $_POST['description'] : ''); ?></textarea></label>
      </div>
    </section>

    <section class="manage-panel product-create-panel product-options-panel">
      <div class="manage-panel-heading">
        <div><p class="admin-eyebrow">PRODUCT OPTIONS</p><h2>Colors, images &amp; sizes</h2></div>
      </div>
      <div class="product-color-list" id="product-color-list">
        <?php $color_row_number = 0; foreach ($form_colors as $posted_color) { $posted_color = is_array($posted_color) ? $posted_color : []; $color_index = $color_row_number++; $color_name_value = is_string($posted_color['name'] ?? null) ? $posted_color['name'] : ''; $selected_sizes = is_array($posted_color['sizes'] ?? null) ? $posted_color['sizes'] : []; $selected_stocks = is_array($posted_color['stocks'] ?? null) ? $posted_color['stocks'] : []; ?>
          <div class="product-color-row" data-color-row data-color-index="<?php echo $color_index; ?>">
            <div class="product-color-row-heading"><strong>Color option</strong><button class="manage-small-button is-danger product-color-remove" type="button" aria-label="Remove color">Remove</button></div>
            <label>Color name<input data-color-name name="colors[<?php echo $color_index; ?>][name]" placeholder="e.g. Navy" value="<?php echo admin_escape($color_name_value); ?>"></label>
            <div class="product-color-controls">
              <div class="product-size-stock">
                <details class="product-size-picker">
                  <summary data-size-summary>Choose sizes</summary>
                  <div class="product-size-options" role="group" aria-label="Available sizes">
                    <?php foreach ($all_size_options as $size_option) { ?>
                      <?php
                      $raw_posted_stock = $selected_stocks[$size_option] ?? 0;
                      $posted_stock = is_string($raw_posted_stock) || is_int($raw_posted_stock)
                          ? filter_var($raw_posted_stock, FILTER_VALIDATE_INT)
                          : false;
                      $stock_value = $posted_stock !== false && $posted_stock >= 0 && $posted_stock <= 1000 ? $posted_stock : 0;
                      ?>
                      <label data-size-label <?php echo in_array($size_option, $selected_size_options, true) ? '' : 'hidden'; ?>><input type="checkbox" data-size-option data-stock="<?php echo $stock_value; ?>" name="colors[<?php echo $color_index; ?>][sizes][]" value="<?php echo admin_escape($size_option); ?>" <?php echo in_array($size_option, $selected_sizes, true) && in_array($size_option, $selected_size_options, true) ? 'checked' : ''; ?>><span><?php echo admin_escape($size_option); ?></span></label>
                    <?php } ?>
                  </div>
                </details>
                <button class="manage-small-button product-stock-toggle" type="button" data-stock-toggle aria-expanded="false">Add stock</button>
                <div class="product-stock-panel" data-stock-panel hidden>
                  <div class="product-stock-list" data-stock-list></div>
                </div>
              </div>
              <div class="product-color-image">
                <div class="product-image-preview" data-product-image-preview><span>Image preview</span><div class="product-image-preview-list" data-product-image-list></div></div>
                <div class="product-image-picker-actions">
                  <input class="product-image-input" data-color-image id="color-image-<?php echo $color_index; ?>" type="file" name="color_images[<?php echo $color_index; ?>][]" accept="image/jpeg,image/png,image/webp" multiple>
                  <label class="admin-button admin-button-light product-image-choose" for="color-image-<?php echo $color_index; ?>">Choose images</label>
                  <small class="manage-secondary" data-product-image-name>No images selected</small>
                  <small class="manage-secondary">Up to 10 JPEG, PNG, or WebP images · 5 MB each. Choose more than once to add files.</small>
                </div>
              </div>
            </div>
          </div>
        <?php } ?>
      </div>
      <div class="product-options-actions">
        <button class="manage-small-button" id="product-add-color" type="button">Add new color</button>
        <button class="admin-button" type="submit">Create product</button>
      </div>
    </section>
  </form>
  <script type="application/json" id="product-size-guide-data"><?php
  echo json_encode($product_size_guide['sizes'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
  ?></script>
  <script>
    (function () {
      var colorList = document.getElementById('product-color-list');
      var addColorButton = document.getElementById('product-add-color');
      var departmentSelect = document.getElementById('product-department');
      var categorySelect = document.getElementById('product-category');
      var sizeGuideSizes = JSON.parse(document.getElementById('product-size-guide-data').textContent);
      var nextColorIndex = colorList.children.length;
      var previewUrls = new WeakMap();
      var selectedImageFiles = new WeakMap();

      function updateAvailableSizes() {
        var selectedCategory = categorySelect.options[categorySelect.selectedIndex];
        var categoryGroup = selectedCategory ? selectedCategory.dataset.sizeGroup : '';
        var departmentSizes = sizeGuideSizes[departmentSelect.value] || {};
        var availableSizes = categoryGroup && departmentSizes[categoryGroup]
          ? departmentSizes[categoryGroup]
          : [].concat(departmentSizes.top || [], departmentSizes.bottom || []);

        colorList.querySelectorAll('[data-size-label]').forEach(function (label) {
          var checkbox = label.querySelector('[data-size-option]');
          var available = availableSizes.includes(checkbox.value);
          label.hidden = !available;
          if (!available) checkbox.checked = false;
        });
        colorList.querySelectorAll('[data-color-row]').forEach(function (row) {
          updateSizeSummary(row);
          updateStockPanel(row);
        });
      }

      function updateRemoveButtons() {
        var rows = colorList.querySelectorAll('[data-color-row]');
        rows.forEach(function (row) {
          row.querySelector('.product-color-remove').disabled = rows.length === 1;
        });
        addColorButton.disabled = rows.length >= 20;
      }

      function updateSizeSummary(row) {
        var selected = row.querySelectorAll('[data-size-option]:checked').length;
        row.querySelector('[data-size-summary]').textContent = selected
          ? selected + ' size' + (selected === 1 ? '' : 's') + ' selected'
          : 'Choose sizes';
      }

      function updateStockPanel(row) {
        var stockList = row.querySelector('[data-stock-list]');
        var selectedCheckboxes = row.querySelectorAll('[data-size-option]:checked');
        stockList.replaceChildren();

        if (!selectedCheckboxes.length) {
          var emptyMessage = document.createElement('p');
          emptyMessage.className = 'manage-secondary';
          emptyMessage.textContent = 'Select sizes first to set stock for each size.';
          stockList.appendChild(emptyMessage);
          return;
        }

        selectedCheckboxes.forEach(function (checkbox) {
          var stockRow = document.createElement('label');
          stockRow.className = 'product-stock-row';
          var sizeName = document.createElement('span');
          sizeName.textContent = checkbox.value;
          var stockInput = document.createElement('input');
          stockInput.type = 'text';
          stockInput.inputMode = 'numeric';
          stockInput.value = checkbox.dataset.stock || '0';
          stockInput.name = 'colors[' + row.dataset.colorIndex + '][stocks][' + checkbox.value + ']';
          stockInput.setAttribute('aria-label', 'Stock for size ' + checkbox.value);
          stockInput.addEventListener('input', function () {
            checkbox.dataset.stock = stockInput.value;
          });
          stockRow.append(sizeName, stockInput);
          stockList.appendChild(stockRow);
        });

        setStockPanelPlacement(row);
      }

      function setStockPanelPlacement(row) {
        var toggle = row.querySelector('[data-stock-toggle]');
        var panel = row.querySelector('[data-stock-panel]');
        panel.classList.remove('is-right', 'is-below');
        panel.style.removeProperty('width');
        if (panel.hidden || toggle.getAttribute('aria-expanded') !== 'true') return;

        var controls = row.querySelector('.product-color-controls');
        var sizeStock = row.querySelector('.product-size-stock');
        var availableRight = controls.getBoundingClientRect().right
          - sizeStock.getBoundingClientRect().right - 12;
        if (availableRight >= 250 && window.matchMedia('(min-width: 601px)').matches) {
          panel.classList.add('is-right');
          panel.style.width = Math.min(360, availableRight) + 'px';
        } else {
          panel.classList.add('is-below');
        }
      }

      colorList.addEventListener('change', function (event) {
        var row = event.target.closest('[data-color-row]');
        if (!row) return;

        if (event.target.matches('[data-size-option]')) {
          updateSizeSummary(row);
          updateStockPanel(row);
        } else if (event.target.matches('[data-color-image]')) {
          var preview = row.querySelector('[data-product-image-preview]');
          var imageList = preview.querySelector('[data-product-image-list]');
          var placeholder = preview.querySelector('span');
          var filename = row.querySelector('[data-product-image-name]');
          (previewUrls.get(event.target) || []).forEach(URL.revokeObjectURL);
          imageList.replaceChildren();
          var previousFiles = selectedImageFiles.get(event.target) || [];
          var files = previousFiles.slice();
          var exceededLimit = false;
          Array.prototype.forEach.call(event.target.files || [], function (file) {
            var alreadySelected = files.some(function (selectedFile) {
              return selectedFile.name === file.name
                && selectedFile.size === file.size
                && selectedFile.lastModified === file.lastModified;
            });
            if (!alreadySelected) files.push(file);
          });
          if (files.length > 10) {
            files = previousFiles;
            exceededLimit = true;
            filename.textContent = 'Choose up to 10 images; previous selection kept';
          }
          var dataTransfer = new DataTransfer();
          files.forEach(function (file) { dataTransfer.items.add(file); });
          event.target.files = dataTransfer.files;
          if (!files.length) {
            previewUrls.delete(event.target);
            selectedImageFiles.delete(event.target);
            placeholder.hidden = false;
            if (!exceededLimit) filename.textContent = 'No images selected';
            return;
          }

          var objectUrls = [];
          files.forEach(function (file) {
            var previewUrl = URL.createObjectURL(file);
            objectUrls.push(previewUrl);
            var image = document.createElement('img');
            image.src = previewUrl;
            image.alt = '';
            imageList.appendChild(image);
          });
          previewUrls.set(event.target, objectUrls);
          selectedImageFiles.set(event.target, files);
          placeholder.hidden = true;
          if (!exceededLimit) {
            filename.textContent = files.length + (files.length === 1 ? ' image selected' : ' images selected');
          }
        }
      });

      addColorButton.addEventListener('click', function () {
        var row = colorList.querySelector('[data-color-row]').cloneNode(true);
        row.dataset.colorIndex = nextColorIndex;
        row.querySelector('[data-color-name]').name = 'colors[' + nextColorIndex + '][name]';
        row.querySelectorAll('[data-size-option]').forEach(function (checkbox) {
          checkbox.checked = false;
          checkbox.dataset.stock = '0';
          checkbox.name = 'colors[' + nextColorIndex + '][sizes][]';
        });
        var imageInput = row.querySelector('[data-color-image]');
        var imageId = 'color-image-' + nextColorIndex++;
        imageInput.name = 'color_images[' + (nextColorIndex - 1) + '][]';
        imageInput.id = imageId;
        row.querySelector('.product-image-choose').htmlFor = imageId;
        row.querySelector('[data-color-name]').value = '';
        row.querySelector('[data-size-summary]').textContent = 'Choose sizes';
        row.querySelector('[data-stock-toggle]').setAttribute('aria-expanded', 'false');
        row.querySelector('[data-stock-panel]').hidden = true;
        row.querySelector('[data-product-image-name]').textContent = 'No images selected';
        row.querySelector('[data-product-image-preview] [data-product-image-list]').replaceChildren();
        row.querySelector('[data-product-image-preview] span').hidden = false;
        row.querySelector('.product-size-picker').open = false;
        imageInput.value = '';
        selectedImageFiles.delete(imageInput);
        previewUrls.delete(imageInput);
        colorList.appendChild(row);
        updateRemoveButtons();
        updateAvailableSizes();
      });

      departmentSelect.addEventListener('change', updateAvailableSizes);
      categorySelect.addEventListener('change', updateAvailableSizes);
      window.addEventListener('resize', function () {
        colorList.querySelectorAll('[data-color-row]').forEach(setStockPanelPlacement);
      });

      colorList.addEventListener('click', function (event) {
        var stockToggle = event.target.closest('[data-stock-toggle]');
        if (stockToggle) {
          var stockRow = stockToggle.closest('[data-color-row]');
          var stockPanel = stockRow.querySelector('[data-stock-panel]');
          var isExpanded = stockToggle.getAttribute('aria-expanded') === 'true';
          stockToggle.setAttribute('aria-expanded', String(!isExpanded));
          stockPanel.hidden = isExpanded;
          setStockPanelPlacement(stockRow);
          return;
        }

        var removeButton = event.target.closest('.product-color-remove');
        if (!removeButton || removeButton.disabled) return;
        var row = removeButton.closest('[data-color-row]');
        var imageInput = row.querySelector('[data-color-image]');
        (previewUrls.get(imageInput) || []).forEach(URL.revokeObjectURL);
        previewUrls.delete(imageInput);
        selectedImageFiles.delete(imageInput);
        row.remove();
        updateRemoveButtons();
      });

      colorList.querySelectorAll('[data-color-row]').forEach(updateSizeSummary);
      updateRemoveButtons();
      updateAvailableSizes();
    })();
  </script>
<?php } ?>
</dialog>

<section class="manage-panel manage-list-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">PRODUCT CATALOG</p><h2>Products <span class="manage-count"><?php echo $product_total; ?></span></h2></div></div>
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
              <form method="post" novalidate onsubmit="return confirm('Delete this product? Products included in orders cannot be deleted.');">
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
    <?php admin_render_pagination($product_pagination['page'], $product_pagination['total_pages']); ?>
  <?php } else { ?><p class="manage-empty">No products yet. Use Add Product above to add your first product.</p><?php } ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
