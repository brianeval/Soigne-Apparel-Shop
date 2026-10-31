<?php
function product_images($conn, $color_ids)
{
    $ids = array_filter(array_map('intval', $color_ids));
    if (!$ids) {
        return [];
    }

    $result = mysqli_query($conn,
        "SELECT product_color_id, image_path FROM product_color_images
         WHERE product_color_id IN (" . implode(',', $ids) . ")
         ORDER BY sort_order, image_id");

    $images = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $images[(int)$row['product_color_id']][] = $row['image_path'];
    }
    return $images;
}

function save_image_upload($file, $folder, $prefix)
{
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $prefix . '-' . uniqid() . '.' . $extension;
    move_uploaded_file($file['tmp_name'], APP_ROOT . '/images/' . $folder . '/' . $filename);
    return $folder . '/' . $filename;
}
