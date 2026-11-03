<?php


$profile_image_url = BASE_URL . 'images/default-profile.jpg';
$profile_files = glob(APP_ROOT . '/images/profiles/profile-' . $image_user_id . '-*');
if ($profile_files) {
    $profile_image_url = BASE_URL . 'images/profiles/' . rawurlencode(basename($profile_files[0]));
}
