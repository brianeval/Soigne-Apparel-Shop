<?php



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
