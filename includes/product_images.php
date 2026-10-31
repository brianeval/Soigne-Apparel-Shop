<?php

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

    $directory = APP_ROOT . '/images/products';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The product image could not be saved. Please try again.');
    }

    $filename = 'product-' . bin2hex(random_bytes(12)) . '.' . $allowed_types[$mime_type];
    if (!move_uploaded_file($upload['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The product image could not be saved. Please try again.');
    }

    return 'products/' . $filename;
}

function product_color_image_paths(mysqli $conn, array $product_color_ids): array
{
    $unique_color_ids = [];
    foreach ($product_color_ids as $product_color_id) {
        $color_id = (int)$product_color_id;
        if ($color_id > 0) {
            $unique_color_ids[$color_id] = $color_id;
        }
    }
    $color_ids = array_values($unique_color_ids);
    if (!$color_ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($color_ids), '?'));
    $types = str_repeat('i', count($color_ids));
    $stmt = mysqli_prepare($conn,
        "SELECT product_color_id, image_path
         FROM product_color_images
         WHERE product_color_id IN ($placeholders)
         ORDER BY sort_order, image_id"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$color_ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $images = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $color_id = (int)$row['product_color_id'];
        $image_path = (string)$row['image_path'];
        $images[$color_id][] = $image_path;
    }

    return $images;
}

function product_color_uploads(array $upload, int $color_index): array
{
    $uploads = [];
    $names = $upload['name'][$color_index] ?? [];
    $is_multiple = is_array($names);
    $indexes = $is_multiple ? array_keys($names) : [null];

    foreach ($indexes as $index) {
        $file = [];
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $field) {
            $values = $upload[$field][$color_index] ?? null;
            $file[$field] = $is_multiple
                ? (is_array($values) ? ($values[$index] ?? null) : null)
                : $values;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploads[] = $file;
        }
    }

    return $uploads;
}

function product_file_uploads(array $upload): array
{
    $files = [];
    if (!is_array($upload['name'] ?? null)) {
        return $upload ? [$upload] : [];
    }

    foreach (array_keys($upload['name']) as $index) {
        $file = [];
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $field) {
            $file[$field] = $upload[$field][$index] ?? null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $files[] = $file;
        }
    }

    return $files;
}
