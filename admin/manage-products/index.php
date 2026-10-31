<?php
require_once("../includes/bootstrap.php");
require_once("../../includes/product_images.php");
require_once("../../includes/product_validation.php");

$errors = [];
$posted_catalog_view = '';
if (isset($_POST['catalog_view']) && is_string($_POST['catalog_view'])) {
    $posted_catalog_view = $_POST['catalog_view'];
} elseif (isset($_GET['catalog_view']) && is_string($_GET['catalog_view'])) {
    $posted_catalog_view = $_GET['catalog_view'];
}

if (in_array($posted_catalog_view, ['products', 'create', 'category', 'brand'], true)) {
    $catalog_view = $posted_catalog_view;
} elseif (isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $catalog_view = 'category';
} elseif (isset($_POST['action']) && $_POST['action'] === 'add_brand') {
    $catalog_view = 'brand';
} elseif ((isset($_POST['action']) && in_array($_POST['action'], ['create', 'refresh_size_options'], true))
    || isset($_POST['remove_color'])) {
    $catalog_view = 'create';
} else {
    $catalog_view = 'products';
}
$product_size_guide = require("../../includes/product_size_guide.php");

function product_size_options(array $guide, string $department, ?string $category)
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

function save_brand_logo_upload(array $upload)
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

    $directory = APP_ROOT . '/images/brands';
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
$product_search = mb_substr(trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : ''), 0, 100);
$product_department = is_string($_GET['department'] ?? null) && in_array($_GET['department'], ['men', 'women', 'kids'], true)
    ? $_GET['department']
    : '';
$product_supplier_id = product_positive_integer($_GET['supplier_id'] ?? null);
$category_names = [];
foreach ($categories as $category_row) {
    $category_names[(int)$category_row['category_id']] = $category_row['name'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_color'])) {
        $posted_colors = $_POST['colors'] ?? null;
        $remove_color_index = product_nonnegative_integer($_POST['remove_color']);
        if (!is_array($posted_colors) || $remove_color_index === null || !array_key_exists($remove_color_index, $posted_colors)) {
            $errors[] = 'Choose a valid color option to remove.';
        } else {
            $posted_colors = array_values($posted_colors);
            array_splice($posted_colors, $remove_color_index, 1);
            $_POST['colors'] = $posted_colors ?: [['name' => '', 'sizes' => []]];
        }
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
                    $logo_path = APP_ROOT . '/images/brands/' . $brand_logo;
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
    } elseif (($_POST['action'] ?? '') === 'refresh_size_options') {
        // The form is re-rendered with size options for the submitted department and category.
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
                $invalid_size = false;
                foreach ($selected_sizes as $size) {
                    if (!is_string($size) || !in_array($size, $allowed_sizes, true)) {
                        $errors[] = 'One or more selected sizes are not available for this department and category.';
                        $invalid_size = true;
                        break;
                    }
                    $valid_sizes[] = $size;
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
                $image_path = APP_ROOT . '/images/' . $saved_image;
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

                $color_stmt = mysqli_prepare($conn, "INSERT INTO product_colors (product_id, color) VALUES (?, ?)");
                $image_stmt = mysqli_prepare($conn, "INSERT INTO product_color_images (product_color_id, image_path, sort_order) VALUES (?, ?, ?)");
                $variant_stmt = mysqli_prepare($conn, "INSERT INTO product_variants (product_color_id, size, stock) VALUES (?, ?, 0)");
                foreach ($colors_to_create as $color) {
                    mysqli_stmt_bind_param($color_stmt, 'is', $new_product_id, $color['name']);
                    mysqli_stmt_execute($color_stmt);
                    $new_color_id = mysqli_insert_id($conn);

                    foreach ($color['images'] as $sort_order => $image_path) {
                        mysqli_stmt_bind_param($image_stmt, 'isi', $new_color_id, $image_path, $sort_order);
                        mysqli_stmt_execute($image_stmt);
                    }
                    foreach ($color['sizes'] as $size) {
                        mysqli_stmt_bind_param($variant_stmt, 'is', $new_color_id, $size);
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
                    $image_path = APP_ROOT . '/images/' . $saved_image;
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
$product_search_pattern = '%' . $product_search . '%';
$product_supplier_filter = $product_supplier_id ?? 0;
$products_stmt = mysqli_prepare($conn,
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
     WHERE (? = '' OR p.name LIKE ?)
       AND (? = '' OR p.department = ?)
       AND (? = 0 OR p.supplier_id = ?)
     GROUP BY p.product_id, p.name, c.name, p.department, p.price, s.name
     ORDER BY p.product_id DESC"
);
mysqli_stmt_bind_param(
    $products_stmt,
    'ssssii',
    $product_search,
    $product_search_pattern,
    $product_department,
    $product_department,
    $product_supplier_filter,
    $product_supplier_filter
);
mysqli_stmt_execute($products_stmt);
$products = mysqli_fetch_all(mysqli_stmt_get_result($products_stmt), MYSQLI_ASSOC);
$product_total = count($products);
$flash = admin_take_flash();
$admin_page_title = 'Manage Products';
$admin_active_page = 'products';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CATALOG</p><h1>Manage Products</h1><p>Create products, update their details, and manage available variants.</p></div>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>

<div class="admin-panel-tabs catalog-tabs" role="radiogroup" aria-label="Product panels">
  <input type="radio" name="catalog_view" id="catalog-view-products" value="products" <?php echo $catalog_view === 'products' ? 'checked' : ''; ?>>
  <label for="catalog-view-products">Product Catalog</label>
  <input type="radio" name="catalog_view" id="catalog-view-create" value="create" <?php echo $catalog_view === 'create' ? 'checked' : ''; ?>>
  <label for="catalog-view-create">Add Product</label>
  <input type="radio" name="catalog_view" id="catalog-view-category" value="category" <?php echo $catalog_view === 'category' ? 'checked' : ''; ?>>
  <label for="catalog-view-category">Add Category</label>
  <input type="radio" name="catalog_view" id="catalog-view-brand" value="brand" <?php echo $catalog_view === 'brand' ? 'checked' : ''; ?>>
  <label for="catalog-view-brand">Add Brand</label>

  <section class="manage-panel catalog-category-panel">
    <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Add Category</h2></div></div>
    <form method="post" class="manage-form catalog-simple-form" novalidate>
      <input type="hidden" name="catalog_view" value="category">
      <input type="hidden" name="action" value="add_category">
      <label>Category name<input name="category_name" value="<?php echo admin_escape(($_POST['action'] ?? '') === 'add_category' && is_string($_POST['category_name'] ?? null) ? $_POST['category_name'] : ''); ?>"></label>
      <div class="manage-form-buttons"><button class="admin-button" type="submit">Add Category</button></div>
    </form>
  </section>

  <section class="manage-panel catalog-brand-panel">
    <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Add Brand</h2></div></div>
    <form method="post" enctype="multipart/form-data" class="manage-form catalog-simple-form" novalidate>
      <input type="hidden" name="catalog_view" value="brand">
      <input type="hidden" name="action" value="add_brand">
      <label>Brand name<input name="brand_name" value="<?php echo admin_escape(($_POST['action'] ?? '') === 'add_brand' && is_string($_POST['brand_name'] ?? null) ? $_POST['brand_name'] : ''); ?>"></label>
      <label>Brand logo (optional)<input type="file" name="brand_logo" accept="image/jpeg,image/png,image/webp"><small class="manage-secondary">JPEG, PNG, or WebP · 5 MB max</small></label>
      <div class="manage-form-buttons"><button class="admin-button" type="submit">Add Brand</button></div>
    </form>
  </section>

  <section class="manage-panel catalog-create-panel" aria-labelledby="add-product-title">
    <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2 id="add-product-title">Create a product</h2></div></div>
<?php if (!$suppliers) { ?>
  <section class="manage-panel product-create-panel">
    <p class="manage-empty">Create a brand first before adding a product.</p>
  </section>
<?php } else { ?>
  <?php
  $form_colors = $_POST['colors'] ?? [0 => ['name' => '', 'sizes' => []]];
  if (!is_array($form_colors) || !$form_colors) {
      $form_colors = [0 => ['name' => '', 'sizes' => []]];
  }
  $form_colors = array_slice(array_values($form_colors), 0, 20);
  if (!$form_colors) {
      $form_colors = [['name' => '', 'sizes' => []]];
  }
  $form_color_count = 1;
  foreach ($form_colors as $color_index => $form_color) {
      if (is_array($form_color)
          && (trim(is_string($form_color['name'] ?? null) ? $form_color['name'] : '') !== ''
              || !empty($form_color['sizes']))) {
          $form_color_count = $color_index + 1;
      }
  }
  $selected_department = is_string($_POST['department'] ?? null) ? $_POST['department'] : 'men';
  $selected_category_id = product_positive_integer($_POST['category_id'] ?? null);
  $selected_category_name = $selected_category_id === null ? null : ($category_names[$selected_category_id] ?? null);
  $visible_size_options = in_array($selected_department, ['men', 'women', 'kids'], true)
      ? product_size_options($product_size_guide, $selected_department, $selected_category_name)
      : [];
  ?>
  <form method="post" enctype="multipart/form-data" class="manage-form manage-product-create create-product-form" novalidate>
    <input type="hidden" name="catalog_view" value="create">
    <section class="manage-panel product-create-panel">
      <div class="manage-panel-heading"><div><p class="admin-eyebrow">ADD TO CATALOG</p><h2>Product details</h2></div></div>
      <div class="manage-product-fields">
        <label class="product-field-wide">Product name<input name="name" value="<?php echo admin_escape(is_string($_POST['name'] ?? null) ? $_POST['name'] : ''); ?>"></label>
        <label>Price (₱)<input type="text" inputmode="decimal" name="price" value="<?php echo admin_escape(is_string($_POST['price'] ?? null) ? $_POST['price'] : ''); ?>"></label>
        <label>Department<select id="product-department" name="department"><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $department_value => $department_label) { ?><option value="<?php echo $department_value; ?>" <?php echo $selected_department === $department_value ? 'selected' : ''; ?>><?php echo $department_label; ?></option><?php } ?></select></label>
        <label>Brand<select name="supplier_id"><option value="">Choose a brand</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo product_positive_integer($_POST['supplier_id'] ?? null) === (int)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
        <label>Category<select id="product-category" name="category_id"><?php foreach ($categories as $category_option) { ?><option value="<?php echo (int)$category_option['category_id']; ?>" <?php echo $selected_category_id === (int)$category_option['category_id'] ? 'selected' : ''; ?>><?php echo admin_escape($category_option['name']); ?></option><?php } ?></select></label>
        <label class="product-field-wide">Description<textarea name="description" rows="4"><?php echo admin_escape(is_string($_POST['description'] ?? null) ? $_POST['description'] : ''); ?></textarea></label>
      </div>
    </section>

    <section class="manage-panel product-create-panel product-options-panel">
      <div class="manage-panel-heading">
        <div><p class="admin-eyebrow">PRODUCT OPTIONS</p><h2>Colors, images &amp; sizes</h2></div>
        <button class="product-size-refresh-button" type="submit" name="action" value="refresh_size_options" aria-label="Refresh available sizes" title="Refresh available sizes">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5"></path><path d="M5.6 9a7 7 0 0 1 11.6-2L20 12M4 12l2.8 5a7 7 0 0 0 11.6-2"></path></svg>
        </button>
      </div>
      <p class="manage-secondary">Select department and category, then refresh sizes here. Refreshing or removing a color clears selected images, so choose images after setting up the colors.</p>
      <div class="product-color-list" id="product-color-list">
        <?php for ($color_index = 0; $color_index < 20; $color_index++) {
          $posted_color = $form_colors[$color_index] ?? [];
          $posted_color = is_array($posted_color) ? $posted_color : [];
          $color_name_value = is_string($posted_color['name'] ?? null) ? $posted_color['name'] : '';
          $selected_sizes = [];
          if (is_array($posted_color['sizes'] ?? null)) {
              foreach ($posted_color['sizes'] as $posted_size) {
                  if (is_string($posted_size) && in_array($posted_size, $visible_size_options, true)) {
                      $selected_sizes[] = $posted_size;
                  }
              }
          }
          if ($color_index > 0) { ?>
            <details class="product-color-disclosure"<?php echo $color_index < $form_color_count ? ' open' : ''; ?>>
              <summary><span class="add-color-label">Add another color</span><span class="existing-color-label">Color option <?php echo $color_index + 1; ?></span></summary>
          <?php } ?>
          <div class="product-color-row">
            <div class="product-color-row-heading">
              <strong>Color option <?php echo $color_index + 1; ?></strong>
              <button class="product-color-remove" type="submit" name="remove_color" value="<?php echo $color_index; ?>" aria-label="Remove color option <?php echo $color_index + 1; ?>" title="Remove color option">×</button>
            </div>
            <label>Color name<input name="colors[<?php echo $color_index; ?>][name]" placeholder="e.g. Navy" value="<?php echo admin_escape($color_name_value); ?>"></label>
            <div class="product-color-controls">
              <div class="product-size-picker">
                <details>
                  <summary>Select sizes</summary>
                  <fieldset class="product-size-options">
                    <legend>Sizes for <?php echo admin_escape($selected_department); ?> / <?php echo admin_escape($selected_category_name ?? 'uncategorized'); ?></legend>
                  <?php if (!$visible_size_options) { ?>
                    <p class="manage-secondary">No sizes available for this department and category.</p>
                  <?php } ?>
                  <?php foreach ($visible_size_options as $size_option) { ?>
                    <label><input type="checkbox" name="colors[<?php echo $color_index; ?>][sizes][]" value="<?php echo admin_escape($size_option); ?>" <?php echo in_array($size_option, $selected_sizes, true) ? 'checked' : ''; ?>> <?php echo admin_escape($size_option); ?></label>
                  <?php } ?>
                  </fieldset>
                </details>
              </div>
              <div class="product-color-image">
                <div class="product-image-picker-actions">
                  <label>Choose images<input id="color-image-<?php echo $color_index; ?>" type="file" name="color_images[<?php echo $color_index; ?>][]" accept="image/jpeg,image/png,image/webp" multiple></label>
                  <small class="manage-secondary">Select up to 10 JPEG, PNG, or WebP images at once · 5 MB each.</small>
                </div>
              </div>
            </div>
          </div>
        <?php } ?>
        <?php for ($close_color_index = 1; $close_color_index < 20; $close_color_index++) { ?></details><?php } ?>
      </div>
      <p class="manage-secondary" id="product-size-help">Choose one or more sizes from the dropdown for each color.</p>
      <div class="product-options-actions">
        <button class="admin-button" type="submit" name="action" value="create">Create product</button>
      </div>
    </section>
  </form>
<?php } ?>
  </section>

<section class="manage-panel manage-list-panel catalog-products-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">PRODUCT CATALOG</p><h2>Products <span class="manage-count"><?php echo $product_total; ?></span></h2></div></div>
  <form method="get" class="inventory-filters product-catalog-filters" aria-label="Filter product catalog">
    <input type="hidden" name="catalog_view" value="products">
    <label class="inventory-search">Product name<input type="search" name="search" value="<?php echo admin_escape($product_search); ?>" placeholder="Search product name"></label>
    <label>Department<select name="department"><option value="">All departments</option><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $department_value => $department_label) { ?><option value="<?php echo $department_value; ?>" <?php echo $product_department === $department_value ? 'selected' : ''; ?>><?php echo $department_label; ?></option><?php } ?></select></label>
    <label>Brand<select name="supplier_id"><option value="">All brands</option><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo $product_supplier_id === (int)$supplier['supplier_id'] ? 'selected' : ''; ?>><?php echo admin_escape($supplier['name']); ?></option><?php } ?></select></label>
    <button class="admin-button" type="submit">Apply filters</button>
    <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-products/?catalog_view=products">Clear</a>
  </form>
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
              <form method="post" novalidate>
                <input type="hidden" name="catalog_view" value="products">
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
  <?php } else { ?><p class="manage-empty">No products yet. Use the Add Product panel to add your first product.</p><?php } ?>
</section>
</div>
<?php include("../includes/footer.php"); ?>
