<?php
function profile_image_files(int $user_id): array
{
    $directory = '/../images/profiles';
    $files = [];

    foreach (['jpg', 'png', 'webp'] as $extension) {
        $matches = glob($directory . '/profile-' . $user_id . '-*.' . $extension);
        if ($matches) {
            $files = array_merge($files, $matches);
        }
    }

    return $files;
}

function profile_image_url(int $user_id): string
{
    $files = profile_image_files($user_id);
    if (!$files) {
        return BASE_URL . 'images/default-profile.jpg';
    }

    usort($files, static function ($left, $right) {
        return filemtime($right) <=> filemtime($left);
    });

    return BASE_URL . 'images/profiles/' . rawurlencode(basename($files[0]));
}

function save_profile_image_upload(array $upload, int $user_id): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The profile picture could not be uploaded. Please try again.');
    }
    if (!isset($upload['tmp_name'], $upload['size']) || !is_uploaded_file($upload['tmp_name'])) {
        throw new RuntimeException('Please choose a valid profile picture.');
    }
    if ((int)$upload['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Profile pictures must be 5 MB or smaller.');
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
        throw new RuntimeException('Choose a JPEG, PNG, or WebP image for your profile picture.');
    }

    $directory = '/../images/profiles';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The profile picture could not be saved. Please try again.');
    }

    $filename = 'profile-' . $user_id . '-' . bin2hex(random_bytes(8)) . '.' . $allowed_types[$mime_type];
    $destination = $directory . '/' . $filename;
    if (!move_uploaded_file($upload['tmp_name'], $destination)) {
        throw new RuntimeException('The profile picture could not be saved. Please try again.');
    }

    return 'images/profiles/' . $filename;
}

function remove_old_profile_images(int $user_id, ?string $keep_path = null): void
{
    foreach (profile_image_files($user_id) as $file) {
        $relative_path = 'images/profiles/' . basename($file);
        if ($relative_path !== $keep_path && !unlink($file)) {
            throw new RuntimeException('The previous profile picture could not be removed.');
        }
    }
}
