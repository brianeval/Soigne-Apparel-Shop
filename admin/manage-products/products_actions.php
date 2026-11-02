<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if (isset($_POST['remove_color'])) {
        // The "x" button removes one color row from the Add Product form
        $posted_colors = $_POST['colors'] ?? [];
        array_splice($posted_colors, (int)$_POST['remove_color'], 1);
        $_POST['colors'] = $posted_colors;
    } elseif ($action == 'add_category') {
        $category_name = trim($_POST['category_name'] ?? '');
        if ($category_name == '') {
            $errors[] = 'Enter a category name.';
        } else {
            try {
                $category_result = mysqli_execute_query($conn, "INSERT INTO categories (name) VALUES (?)", [$category_name]);
                $_SESSION['success'] = 'Category added to the catalog.';
                header('Location: ' . BASE_URL . 'admin/manage-products/');
                exit;
            } catch (mysqli_sql_exception $exception) {
                if ($exception->getCode() == 1062) {
                    $errors[] = 'A category with that name already exists.';
                } else {
                    $errors[] = 'Could not add this category. Please try again.';
                }
            }
        }
    } elseif ($action == 'add_brand') {
        $brand_name = trim($_POST['brand_name'] ?? '');
        if ($brand_name == '') {
            $errors[] = 'Enter a brand name.';
        } else {
            // Save the logo (optional)
            $upload_error = '';
            $saved_image = null;
            if (isset($_FILES['brand_logo'])) {
                $upload = $_FILES['brand_logo'];
                $upload_folder = 'brands';
                $upload_prefix = 'brand-';
                include("../../includes/upload_image.php");
            }

            if ($upload_error != '') {
                $errors[] = $upload_error;
            } else {
                try {
                    $brand_result = mysqli_execute_query($conn, "INSERT INTO suppliers (name, image) VALUES (?, ?)", [$brand_name, $saved_image]);
                    $_SESSION['success'] = 'Brand added to the catalog.';
                    header('Location: ' . BASE_URL . 'admin/manage-products/');
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    // The brand was not saved, so remove its logo again
                    if ($saved_image) {
                        unlink(APP_ROOT . '/images/brands/' . $saved_image);
                    }
                    if ($exception->getCode() == 1062) {
                        $errors[] = 'A brand with that name already exists.';
                    } else {
                        $errors[] = 'Could not add this brand. Please try again.';
                    }
                }
            }
        }
    } elseif ($action == 'refresh_size_options') {
        // Nothing to save: the form is shown again with the sizes of the chosen department and category
    } elseif ($action == 'create') {
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category_id = $selected_category_id;
        $department = $selected_department;
        $price = $_POST['price'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $posted_colors = $_POST['colors'] ?? [];
        $colors_to_create = [];

        // Basic checks
        if ($supplier_id < 1) {
            $errors[] = 'Choose a brand.';
        }
        if ($name == '') {
            $errors[] = 'Enter a product name.';
        }
        if (!is_numeric($price) || $price < 0) {
            $errors[] = 'Enter a valid product price.';
        }

        // Colors: each one needs a name and at least one size. Pictures are optional.
        foreach ($posted_colors as $color_index => $posted_color) {
            $color_name = trim($posted_color['name'] ?? '');
            $selected_sizes = $posted_color['sizes'] ?? [];

            // The pictures chosen for this color
            $color_uploads = [];
            foreach ($_FILES['color_images']['name'][$color_index] ?? [] as $file_index => $file_name) {
                if ($_FILES['color_images']['error'][$color_index][$file_index] != UPLOAD_ERR_NO_FILE) {
                    $color_uploads[] = [
                        'name' => $file_name,
                        'tmp_name' => $_FILES['color_images']['tmp_name'][$color_index][$file_index],
                        'error' => $_FILES['color_images']['error'][$color_index][$file_index],
                        'size' => $_FILES['color_images']['size'][$color_index][$file_index],
                    ];
                }
            }

            // Skip empty color rows
            if ($color_name == '' && !$selected_sizes && !$color_uploads) {
                continue;
            }
            if ($color_name == '' || !$selected_sizes) {
                $errors[] = 'Enter a color name and select at least one size for each color.';
                continue;
            }
            if (count($color_uploads) > 10) {
                $errors[] = 'Choose no more than 10 images for each color.';
                continue;
            }
            foreach ($selected_sizes as $size) {
                if (!in_array($size, $visible_size_options)) {
                    $errors[] = 'One or more selected sizes are not available for this department and category.';
                    break;
                }
            }

            $colors_to_create[] = ['name' => $color_name, 'sizes' => $selected_sizes, 'uploads' => $color_uploads];
        }
        if (!$colors_to_create && !$errors) {
            $errors[] = 'Add at least one color and select at least one size.';
        }

        // Save the pictures
        $saved_images = [];
        if (!$errors) {
            foreach ($colors_to_create as $index => $color) {
                $colors_to_create[$index]['images'] = [];
                foreach ($color['uploads'] as $upload) {
                    $upload_folder = 'products';
                    $upload_prefix = 'product-';
                    include("../../includes/upload_image.php");
                    if ($upload_error != '') {
                        $errors[] = $upload_error;
                    } elseif ($saved_image) {
                        $colors_to_create[$index]['images'][] = 'products/' . $saved_image;
                        $saved_images[] = 'products/' . $saved_image;
                    }
                }
            }
        }

        // Something went wrong: remove the pictures that were already saved
        if ($errors) {
            foreach ($saved_images as $saved_path) {
                unlink(APP_ROOT . '/images/' . $saved_path);
            }
        } else {
            try {
                mysqli_begin_transaction($conn);
                $product_result = mysqli_execute_query($conn, "INSERT INTO products (supplier_id, name, category_id, department, price, description) VALUES (?, ?, ?, ?, ?, ?)", [$supplier_id, $name, $category_id, $department, $price, $description]);
                $new_product_id = mysqli_insert_id($conn);

                foreach ($colors_to_create as $color) {
                    mysqli_execute_query($conn, "INSERT INTO product_colors (product_id, color) VALUES (?, ?)", [$new_product_id, $color['name']]);
                    $new_color_id = mysqli_insert_id($conn);

                    foreach ($color['images'] as $sort_order => $image_path) {
                        mysqli_execute_query($conn, "INSERT INTO product_color_images (product_color_id, image_path, sort_order) VALUES (?, ?, ?)", [$new_color_id, $image_path, $sort_order]);
                    }
                    foreach ($color['sizes'] as $size) {
                        mysqli_execute_query($conn, "INSERT INTO product_variants (product_color_id, size, stock) VALUES (?, ?, 0)", [$new_color_id, $size]);
                    }
                }
                mysqli_commit($conn);

                $_SESSION['success'] = 'Product created with its selected colors and sizes.';
                header('Location: ' . BASE_URL . 'admin/manage-products/edit.php?id=' . $new_product_id);
                exit;
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                foreach ($saved_images as $saved_path) {
                    unlink(APP_ROOT . '/images/' . $saved_path);
                }
                $errors[] = 'The product could not be created. Please check the details and try again.';
            }
        }
    } elseif ($action == 'delete') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        try {
            mysqli_begin_transaction($conn);
            mysqli_execute_query($conn, "DELETE v FROM product_variants v
                                 JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                                 WHERE pc.product_id = ?", [$product_id]);
            mysqli_execute_query($conn, "DELETE FROM product_colors WHERE product_id = ?", [$product_id]);
            mysqli_execute_query($conn, "DELETE FROM products WHERE product_id = ?", [$product_id]);
            mysqli_commit($conn);
            $_SESSION['success'] = 'Product deleted.';
            header('Location: ' . BASE_URL . 'admin/manage-products/');
            exit;
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $errors[] = 'Could not delete this product because it is used by a cart, an order or a restock record.';
        }
    } else {
        $errors[] = 'That product action is not valid.';
    }
}
