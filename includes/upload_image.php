<?php







$saved_image = null;
$upload_error = '';

if ($upload['error'] == UPLOAD_ERR_NO_FILE) {

} elseif ($upload['error'] != UPLOAD_ERR_OK) {
    $upload_error = 'The image could not be uploaded. Please try again.';
} elseif ($upload['size'] > 5 * 1024 * 1024) {
    $upload_error = 'Images must be 5 MB or smaller.';
} else {
    $upload_extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
    if (!in_array($upload_extension, ['jpg', 'jpeg', 'png', 'webp'])) {
        $upload_error = 'Choose a JPEG, PNG, or WebP image.';
    } else {
        $upload_directory = APP_ROOT . '/images/' . $upload_folder;
        if (!is_dir($upload_directory)) {
            mkdir($upload_directory, 0755, true);
        }
        $upload_file_name = $upload_prefix . uniqid() . '.' . $upload_extension;
        if (move_uploaded_file($upload['tmp_name'], $upload_directory . '/' . $upload_file_name)) {
            $saved_image = $upload_file_name;
        } else {
            $upload_error = 'The image could not be saved. Please try again.';
        }
    }
}
