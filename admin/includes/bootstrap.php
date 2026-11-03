<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once(__DIR__ . "/../../includes/config.php");


$admin_account = null;

if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') == 'admin') {
    $admin_user_id = (int)$_SESSION['user_id'];
    $admin_result = mysqli_query($conn, "SELECT status FROM users WHERE user_id = $admin_user_id AND role = 'admin'");
    $admin_account = mysqli_fetch_assoc($admin_result);
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') != 'admin') {
    $_SESSION['message'] = 'Please sign in with an active administrator account to access that page.';
    header('Location: ' . BASE_URL . 'admin/login.php');
    exit;
}

