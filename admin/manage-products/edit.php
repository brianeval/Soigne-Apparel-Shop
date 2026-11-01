<?php
require_once("../includes/bootstrap.php");

$product_id = (int)($_GET['id'] ?? 0);

$product_result = mysqli_execute_query($conn, "SELECT p.*, s.name AS supplier_name
     FROM products p
     JOIN suppliers s ON s.supplier_id = p.supplier_id
     WHERE p.product_id = ?", [$product_id]);
$product = mysqli_fetch_assoc($product_result);
if (!$product) {
    http_response_code(404);
    exit('Product not found.');
}

// Address of this page (used to come back after saving)
$edit_url = BASE_URL . 'admin/manage-products/edit.php?id=' . $product_id;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'add_images') {
        // Upload pictures for one color
        $color_id = (int)($_POST['product_color_id'] ?? 0);

        $uploads = [];
        foreach ($_FILES['images']['name'] ?? [] as $file_index => $file_name) {
            if ($_FILES['images']['error'][$file_index] != UPLOAD_ERR_NO_FILE) {
                $uploads[] = [
                    'name' => $file_name,
                    'tmp_name' => $_FILES['images']['tmp_name'][$file_index],
                    'error' => $_FILES['images']['error'][$file_index],
                    'size' => $_FILES['images']['size'][$file_index],
                ];
            }
        }

        $color_result = mysqli_query($conn, "SELECT product_color_id FROM product_colors WHERE product_color_id = $color_id AND product_id = $product_id");
        if (mysqli_num_rows($color_result) == 0) {
            $errors[] = 'Color not found for this product.';
        } elseif (!$uploads || count($uploads) > 10) {
            $errors[] = 'Choose between 1 and 10 images to upload.';
        } else {
            // Save the files
            $saved_images = [];
            foreach ($uploads as $upload) {
                $upload_folder = 'products';
                $upload_prefix = 'product-';
                include("../../includes/upload_image.php");
                if ($upload_error != '') {
                    $errors[] = $upload_error;
                } elseif ($saved_image) {
                    $saved_images[] = 'products/' . $saved_image;
                }
            }

            if (!$errors) {
                try {
                    // New pictures go after the existing ones
                    $order_result = mysqli_query($conn, "SELECT COALESCE(MAX(sort_order) + 1, 0) AS next_order FROM product_color_images WHERE product_color_id = $color_id");
                    $next_order = (int)mysqli_fetch_assoc($order_result)['next_order'];
                    foreach ($saved_images as $image_path) {
                        mysqli_execute_query($conn, "INSERT INTO product_color_images (product_color_id, image_path, sort_order) VALUES (?, ?, ?)", [$color_id, $image_path, $next_order]);
                        $next_order++;
                    }
                    $_SESSION['success'] = count($saved_images) . ' image(s) added.';
                    header('Location: ' . $edit_url . '&panel=options&color_panel_id=' . $color_id);
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    $errors[] = 'Could not add product images. Please try again.';
                }
            }

            // Something went wrong: remove the files that were saved
            foreach ($saved_images as $image_path) {
                unlink(APP_ROOT . '/images/' . $image_path);
            }
        }
    } elseif ($action == 'remove_images') {
        // Remove the ticked pictures
        $image_ids = $_POST['image_ids'] ?? [];
        if (!$image_ids) {
            $errors[] = 'Select at least one image to remove.';
        } else {
            foreach ($image_ids as $image_id) {
                $image_id = (int)$image_id;
                $image_result = mysqli_query($conn,
                    "SELECT pci.image_path
                     FROM product_color_images pci
                     JOIN product_colors pc ON pc.product_color_id = pci.product_color_id
                     WHERE pci.image_id = $image_id AND pc.product_id = $product_id"
                );
                $image_row = mysqli_fetch_assoc($image_result);
                if ($image_row) {
                    mysqli_query($conn, "DELETE FROM product_color_images WHERE image_id = $image_id");

                    // Delete the file too, unless another color still uses it
                    $image_path = $image_row['image_path'];
                    $path_safe = mysqli_real_escape_string($conn, $image_path);
                    $use_result = mysqli_query($conn, "SELECT COUNT(*) AS image_count FROM product_color_images WHERE image_path = '$path_safe'");
                    $file_path = APP_ROOT . '/images/' . $image_path;
                    if (mysqli_fetch_assoc($use_result)['image_count'] == 0 && strpos($image_path, '..') === false && is_file($file_path)) {
                        unlink($file_path);
                    }
                }
            }
            $_SESSION['success'] = 'Selected images removed.';
            header('Location: ' . $edit_url . '&panel=options');
            exit;
        }
    } elseif ($action == 'update_product') {
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        if ($category_id == 0) {
            $category_id = null;   // uncategorized
        }
        $department = $_POST['department'] ?? '';
        $price = $_POST['price'] ?? '';
        $description = trim($_POST['description'] ?? '');

        // Basic checks
        if ($supplier_id < 1) {
            $errors[] = 'Choose a brand.';
        }
        if ($name == '') {
            $errors[] = 'Enter a product name.';
        }
        if (!in_array($department, ['men', 'women', 'kids'])) {
            $errors[] = 'Choose a valid department.';
        }
        if (!is_numeric($price) || $price < 0) {
            $errors[] = 'Enter a valid price.';
        }

        if (!$errors) {
            mysqli_execute_query($conn, "UPDATE products SET supplier_id = ?, name = ?, category_id = ?, department = ?, price = ?, description = ? WHERE product_id = ?",
                [$supplier_id, $name, $category_id, $department, $price, $description, $product_id]);
            $_SESSION['success'] = 'Product details updated.';
            header('Location: ' . $edit_url);
            exit;
        }
    } elseif ($action == 'add_color') {
        $color = trim($_POST['color'] ?? '');
        $image = trim($_POST['image'] ?? '');
        if ($color == '') {
            $errors[] = 'Enter a color name.';
        } else {
            try {
                mysqli_begin_transaction($conn);
                mysqli_execute_query($conn, "INSERT INTO product_colors (product_id, color) VALUES (?, ?)", [$product_id, $color]);
                $color_id = mysqli_insert_id($conn);
                if ($image != '') {
                    mysqli_execute_query($conn, "INSERT INTO product_color_images (product_color_id, image_path, sort_order) VALUES (?, ?, 0)", [$color_id, $image]);
                }
                mysqli_commit($conn);
                $_SESSION['success'] = 'Color added.';
                header('Location: ' . $edit_url . '&panel=options&color_panel_id=' . $color_id);
                exit;
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                if ($exception->getCode() == 1062) {
                    $errors[] = 'This product already has that color.';
                } else {
                    $errors[] = 'Could not add the color.';
                }
            }
        }
    } elseif ($action == 'update_color') {
        $color_id = (int)($_POST['product_color_id'] ?? 0);
        $color = trim($_POST['color'] ?? '');
        if ($color_id < 1 || $color == '') {
            $errors[] = 'Enter a valid color name.';
        } else {
            try {
                $color_result = mysqli_execute_query($conn, "UPDATE product_colors SET color = ? WHERE product_color_id = ? AND product_id = ?", [$color, $color_id, $product_id]);
                $_SESSION['success'] = 'Color updated.';
                header('Location: ' . $edit_url . '&panel=options&color_panel_id=' . $color_id);
                exit;
            } catch (mysqli_sql_exception $exception) {
                if ($exception->getCode() == 1062) {
                    $errors[] = 'This product already has that color.';
                } else {
                    $errors[] = 'Could not update the color.';
                }
            }
        }
    } elseif ($action == 'delete_color') {
        $color_id = (int)($_POST['product_color_id'] ?? 0);
        try {
            mysqli_begin_transaction($conn);
            mysqli_query($conn, "DELETE FROM product_variants WHERE product_color_id = $color_id");
            mysqli_query($conn, "DELETE FROM product_colors WHERE product_color_id = $color_id AND product_id = $product_id");
            mysqli_commit($conn);
            $_SESSION['success'] = 'Color and its unused sizes deleted.';
            header('Location: ' . $edit_url . '&panel=options');
            exit;
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $errors[] = 'This color has cart, order, or restock history and cannot be deleted.';
        }
    } elseif ($action == 'add_variant') {
        $color_id = (int)($_POST['product_color_id'] ?? 0);
        $size = trim($_POST['size'] ?? '');
        if ($color_id < 1 || $size == '') {
            $errors[] = 'Enter a valid color and size.';
        } else {
            try {
                // The color must belong to this product
                $variant_result = mysqli_execute_query($conn, "INSERT INTO product_variants (product_color_id, size, stock) SELECT product_color_id, ?, 0 FROM product_colors WHERE product_color_id = ? AND product_id = ?", [$size, $color_id, $product_id]);
                if (mysqli_affected_rows($conn) != 1) {
                    $errors[] = 'That color does not belong to this product.';
                } else {
                    $_SESSION['success'] = 'Size variant added.';
                    header('Location: ' . $edit_url . '&panel=options&color_panel_id=' . $color_id);
                    exit;
                }
            } catch (mysqli_sql_exception $exception) {
                if ($exception->getCode() == 1062) {
                    $errors[] = 'That size already exists for this color.';
                } else {
                    $errors[] = 'Could not add the size variant.';
                }
            }
        }
    } elseif ($action == 'update_variant') {
        $variant_id = (int)($_POST['variant_id'] ?? 0);
        $size = trim($_POST['size'] ?? '');
        if ($variant_id < 1 || $size == '') {
            $errors[] = 'Enter a valid size.';
        } else {
            // A size that is already used by a cart, order or restock cannot be renamed
            $usage_result = mysqli_query($conn,
                "SELECT v.size,
                    (SELECT COUNT(*) FROM cart c WHERE c.variant_id = v.variant_id) +
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.variant_id = v.variant_id) +
                    (SELECT COUNT(*) FROM restock_items ri WHERE ri.variant_id = v.variant_id) AS usage_count
                 FROM product_variants v
                 JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                 WHERE v.variant_id = $variant_id AND pc.product_id = $product_id"
            );
            $variant_usage = mysqli_fetch_assoc($usage_result);
            if (!$variant_usage) {
                $errors[] = 'Size variant not found.';
            } elseif ($size != $variant_usage['size'] && $variant_usage['usage_count'] > 0) {
                $errors[] = 'The size cannot be renamed while it is referenced by a cart, order, or restock record.';
            } else {
                try {
                    $size_result = mysqli_execute_query($conn, "UPDATE product_variants SET size = ? WHERE variant_id = ?", [$size, $variant_id]);
                    $_SESSION['success'] = 'Size updated.';
                    header('Location: ' . $edit_url . '&panel=options&color_panel_id=' . (int)($_POST['color_panel_id'] ?? 0));
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    if ($exception->getCode() == 1062) {
                        $errors[] = 'That size already exists for this color.';
                    } else {
                        $errors[] = 'Could not update the size variant.';
                    }
                }
            }
        }
    } elseif ($action == 'delete_variant') {
        $variant_id = (int)($_POST['variant_id'] ?? 0);
        try {
            mysqli_query($conn, "DELETE v FROM product_variants v
                                 JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                                 WHERE v.variant_id = $variant_id AND pc.product_id = $product_id");
            $_SESSION['success'] = 'Size variant deleted.';
            header('Location: ' . $edit_url . '&panel=options&color_panel_id=' . (int)($_POST['color_panel_id'] ?? 0));
            exit;
        } catch (mysqli_sql_exception $exception) {
            $errors[] = 'This size has cart, order, or restock history and cannot be deleted.';
        }
    } else {
        $errors[] = 'That product action is not valid.';
    }
}

$suppliers = mysqli_fetch_all(mysqli_query($conn, "SELECT supplier_id, name FROM suppliers ORDER BY name"), MYSQLI_ASSOC);
$categories = mysqli_fetch_all(mysqli_query($conn, "SELECT category_id, name FROM categories ORDER BY name"), MYSQLI_ASSOC);

// Colors with their sizes
$color_rows = mysqli_fetch_all(mysqli_query($conn,
    "SELECT pc.product_color_id, pc.color, v.variant_id, v.size
     FROM product_colors pc
     LEFT JOIN product_variants v ON v.product_color_id = pc.product_color_id
     WHERE pc.product_id = $product_id
     ORDER BY pc.product_color_id, v.variant_id"
), MYSQLI_ASSOC);
$colors = [];
foreach ($color_rows as $row) {
    $color_id = (int)$row['product_color_id'];
    if (!isset($colors[$color_id])) {
        $colors[$color_id] = ['product_color_id' => $color_id, 'color' => $row['color'], 'variants' => []];
    }
    if ($row['variant_id'] != null) {
        $colors[$color_id]['variants'][] = ['variant_id' => (int)$row['variant_id'], 'size' => $row['size']];
    }
}

// Pictures of every color: the file paths and (in the same order) their ids
$color_images = [];
$color_image_ids = [];
$image_rows = mysqli_fetch_all(mysqli_query($conn,
    "SELECT pci.product_color_id, pci.image_id, pci.image_path
     FROM product_color_images pci
     JOIN product_colors pc ON pc.product_color_id = pci.product_color_id
     WHERE pc.product_id = $product_id
     ORDER BY pc.product_color_id, pci.sort_order, pci.image_id"
), MYSQLI_ASSOC);
foreach ($image_rows as $image_row) {
    $color_images[(int)$image_row['product_color_id']][] = $image_row['image_path'];
    $color_image_ids[(int)$image_row['product_color_id']][] = (int)$image_row['image_id'];
}

// Which color tab is open
$selected_color_panel_id = (int)($_POST['color_panel_id'] ?? $_GET['color_panel_id'] ?? $_POST['product_color_id'] ?? 0);
if (!isset($colors[$selected_color_panel_id])) {
    $selected_color_panel_id = $colors ? (int)array_key_first($colors) : 0;
}

// Which panel is open: product details or product options
$option_actions = ['add_images', 'remove_images', 'add_color', 'update_color', 'delete_color', 'add_variant', 'update_variant', 'delete_variant'];
$posted_action = $_POST['action'] ?? '';
if (in_array($posted_action, $option_actions) || ($_GET['panel'] ?? '') == 'options') {
    $active_edit_panel = 'options';
} else {
    $active_edit_panel = 'details';
}
$admin_page_title = 'Edit Product';
$admin_active_page = 'products';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">PRODUCT CATALOG</p><h1>Edit Product</h1><p>Update product details, colors, and sizes.</p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-products/">&larr; All products</a>
</section>
<?php include("../../includes/alert.php"); ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error); ?></li><?php } ?></ul></div><?php } ?>

<div class="admin-panel-tabs product-edit-tabs" role="radiogroup" aria-label="Product edit panels">
  <input type="radio" name="product_edit_view" id="product-edit-view-details" value="details" <?php echo $active_edit_panel === 'details' ? 'checked' : ''; ?>>
  <label for="product-edit-view-details">Product Details</label>
  <input type="radio" name="product_edit_view" id="product-edit-view-options" value="options" <?php echo $active_edit_panel === 'options' ? 'checked' : ''; ?>>
  <label for="product-edit-view-options">Product Options</label>

<section class="manage-panel product-edit-details-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">PRODUCT DETAILS</p><h2><?php echo htmlspecialchars($product['name']); ?></h2></div></div>
  <form method="post" class="manage-form manage-product-create" novalidate>
    <input type="hidden" name="action" value="update_product">
    <label>Product name<input name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? $product['name']); ?>"></label>
    <label>Brand<select name="supplier_id"><?php foreach ($suppliers as $supplier) { ?><option value="<?php echo (int)$supplier['supplier_id']; ?>" <?php echo (int)$supplier['supplier_id'] === (int)$product['supplier_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($supplier['name']); ?></option><?php } ?></select></label>
    <label>Category<select name="category_id"><option value="">Uncategorized</option><?php foreach ($categories as $category_option) { $selected_category_id = $_POST['category_id'] ?? (string)$product['category_id']; ?><option value="<?php echo (int)$category_option['category_id']; ?>" <?php echo $selected_category_id === (string)$category_option['category_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category_option['name']); ?></option><?php } ?></select></label>
    <label>Department<select name="department"><?php foreach (['men' => 'Men', 'women' => 'Women', 'kids' => 'Kids'] as $key => $label) { ?><option value="<?php echo $key; ?>" <?php echo $product['department'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></label>
    <label>Price (₱)<input type="text" inputmode="decimal" name="price" value="<?php echo htmlspecialchars($_POST['price'] ?? $product['price']); ?>"></label>
    <label class="manage-form-wide">Description<textarea name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? $product['description'] ?? ''); ?></textarea></label>
    <div class="manage-form-wide"><button class="admin-button" type="submit">Save product details</button></div>
  </form>
</section>

<section class="manage-panel manage-list-panel product-edit-options-panel">
  <div class="manage-panel-heading product-edit-options-heading">
    <div><p class="admin-eyebrow">PRODUCT OPTIONS</p><h2>Colors &amp; sizes</h2></div>
    <div class="manage-color-gallery-action">
    <details class="catalog-disclosure catalog-image-disclosure">
      <summary class="admin-button admin-button-light"><span class="summary-open-label">View All Images</span><span class="summary-close-label">Close</span></summary>
      <div class="catalog-modal">
        <section class="catalog-dialog catalog-product-dialog" role="dialog" aria-modal="true" aria-labelledby="product-images-title">
          <form method="post" class="product-image-remove-form">
          <input type="hidden" name="action" value="remove_images">
          <input type="hidden" name="color_panel_id" value="<?php echo (int)$selected_color_panel_id; ?>">
          <div class="catalog-dialog-heading">
            <div><p class="admin-eyebrow">PRODUCT GALLERY</p><h2 id="product-images-title">All images — <?php echo htmlspecialchars($product['name']); ?></h2></div>
            <button class="manage-small-button is-danger" type="submit">Remove selected</button>
          </div>
          <?php $has_product_images = false; foreach ($colors as $color) { $images_for_color = $color_images[(int)$color['product_color_id']] ?? []; if ($images_for_color) { $has_product_images = true; ?>
            <section class="product-admin-image-group">
              <h3><?php echo htmlspecialchars($color['color']); ?></h3>
              <div class="product-admin-image-grid">
                <?php foreach ($images_for_color as $image_index => $image_path) { $image_id = (int)($color_image_ids[(int)$color['product_color_id']][$image_index] ?? 0); ?>
                  <figure>
                    <label class="product-admin-image-select">
                      <input type="checkbox" name="image_ids[]" value="<?php echo $image_id; ?>" aria-label="Select image <?php echo $image_index + 1; ?> for <?php echo htmlspecialchars($color['color']); ?>">
                      <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo htmlspecialchars($product['name'] . ' - ' . $color['color']); ?>" loading="lazy">
                    </label>
                  </figure>
                <?php } ?>
              </div>
            </section>
          <?php } } ?>
          <?php if (!$has_product_images) { ?><p class="manage-empty">No images have been added to this product yet.</p><?php } ?>
          </form>
        </section>
      </div>
    </details>
    </div>
  </div>
  <form method="post" class="manage-form manage-add-color" novalidate>
    <input type="hidden" name="action" value="add_color">
    <label>New color<input name="color" placeholder="e.g. Navy"></label>
    <label>Image filename<input name="image" placeholder="e.g. shirt-navy.jpg"></label>
    <div><button class="admin-button" type="submit">Add color</button></div>
  </form>
  <?php if (!$colors) { ?><p class="manage-empty">No colors added for this product yet.</p><?php } ?>
  <div class="product-color-tabs" role="radiogroup" aria-label="Product color panels">
  <?php foreach ($colors as $color) {
  ?>
    <div class="product-color-tab">
      <input class="product-color-tab-input" type="radio" name="color_panel" id="color-panel-<?php echo (int)$color['product_color_id']; ?>" <?php echo $selected_color_panel_id === (int)$color['product_color_id'] ? 'checked' : ''; ?>>
      <label class="product-color-tab-label" for="color-panel-<?php echo (int)$color['product_color_id']; ?>"><?php echo htmlspecialchars($color['color']); ?></label>
    <div class="manage-color-panel">
      <div class="manage-color-layout">
      <div class="manage-color-details-tile">
      <form id="update-color-<?php echo (int)$color['product_color_id']; ?>" method="post" class="manage-form manage-color-edit" novalidate>
        <input type="hidden" name="action" value="update_color">
        <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <input type="hidden" name="color_panel_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <label>Color<input name="color" value="<?php echo htmlspecialchars($color['color']); ?>"></label>
      </form>
      <form method="post" enctype="multipart/form-data" class="manage-form manage-color-images" novalidate>
        <input type="hidden" name="action" value="add_images">
        <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <input type="hidden" name="color_panel_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <label>Add images for <?php echo htmlspecialchars($color['color']); ?><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><small class="manage-secondary">Select up to 10 JPEG, PNG, or WebP files at once · 5 MB each.</small></label>
        <button class="manage-small-button" type="submit">Upload images</button>
      </form>
      <div class="manage-color-actions">
        <button class="manage-small-button" type="submit" form="update-color-<?php echo (int)$color['product_color_id']; ?>">Save color</button>
        <form method="post" class="manage-delete-color" novalidate>
          <input type="hidden" name="action" value="delete_color">
          <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
          <input type="hidden" name="color_panel_id" value="<?php echo (int)$color['product_color_id']; ?>">
          <button class="manage-small-button is-danger" type="submit">Delete color</button>
        </form>
      </div>
      </div>
      <div class="manage-color-sizes-tile">
      <div class="manage-variant-heading"><strong>Sizes (<?php echo count($color['variants']); ?>)</strong>
        <form method="post" class="manage-inline-form" novalidate>
        <input type="hidden" name="action" value="add_variant">
        <input type="hidden" name="product_color_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <input type="hidden" name="color_panel_id" value="<?php echo (int)$color['product_color_id']; ?>">
        <input name="size" placeholder="Size" aria-label="New size">
        <button class="manage-small-button" type="submit">Add size</button>
        </form>
      </div>
      <?php if ($color['variants']) { ?>
        <div class="admin-table-wrap"><table class="admin-table manage-variant-table">
          <thead><tr><th>Size</th><th>Actions</th></tr></thead>
          <tbody><?php foreach ($color['variants'] as $variant) { $variant_form_id = 'update-variant-' . $variant['variant_id']; ?>
            <tr>
              <td>
                <form id="<?php echo $variant_form_id; ?>" method="post" novalidate></form>
                <input form="<?php echo $variant_form_id; ?>" type="hidden" name="action" value="update_variant">
                <input form="<?php echo $variant_form_id; ?>" type="hidden" name="variant_id" value="<?php echo $variant['variant_id']; ?>">
                <input form="<?php echo $variant_form_id; ?>" type="hidden" name="color_panel_id" value="<?php echo (int)$color['product_color_id']; ?>">
                <input form="<?php echo $variant_form_id; ?>" name="size" value="<?php echo htmlspecialchars($variant['size']); ?>" aria-label="Size">
              </td>
              <td><button form="<?php echo $variant_form_id; ?>" class="manage-small-button" type="submit">Save</button>
                <form method="post" class="manage-inline-form" novalidate>
                  <input type="hidden" name="action" value="delete_variant">
                  <input type="hidden" name="variant_id" value="<?php echo $variant['variant_id']; ?>">
                  <input type="hidden" name="color_panel_id" value="<?php echo (int)$color['product_color_id']; ?>">
                  <button class="manage-small-button is-danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php } ?></tbody>
        </table></div>
      <?php } else { ?><p class="manage-empty">No sizes added for this color.</p><?php } ?>
      </div>
      </div>
    </div>
    </div>
  <?php } ?>
  </div>
</section>
</div>
<?php include("../includes/footer.php"); ?>
