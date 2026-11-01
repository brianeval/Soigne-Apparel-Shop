<?php
// Looks up the pictures of product colors.
// Set $image_color_ids (a list of product_color_id numbers) before including this file.
// Result: $images_by_color[color_id] is a list of image paths in display order.
$images_by_color = [];
$image_color_ids = array_filter(array_map('intval', $image_color_ids));

if ($image_color_ids) {
    $image_id_list = implode(',', $image_color_ids);
    $image_result = mysqli_query($conn,
        "SELECT product_color_id, image_path
         FROM product_color_images
         WHERE product_color_id IN ($image_id_list)
         ORDER BY sort_order, image_id"
    );
    while ($image_row = mysqli_fetch_assoc($image_result)) {
        $images_by_color[$image_row['product_color_id']][] = $image_row['image_path'];
    }
}
