<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'add_images') {

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


            foreach ($saved_images as $image_path) {
                unlink(APP_ROOT . '/images/' . $image_path);
            }
        }
    } elseif ($action == 'remove_images') {

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
            $category_id = null;
        }
        $department = $_POST['department'] ?? '';
        $price = $_POST['price'] ?? '';
        $description = trim($_POST['description'] ?? '');


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
            mysqli_execute_query($conn, "DELETE FROM product_variants WHERE product_color_id = ?", [$color_id]);
            mysqli_execute_query($conn, "DELETE FROM product_colors WHERE product_color_id = ? AND product_id = ?", [$color_id, $product_id]);
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
