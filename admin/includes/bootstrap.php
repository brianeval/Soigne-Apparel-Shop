<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once(__DIR__ . '/../../includes/config.php');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['admin_login_notice'] = 'Please sign in with an administrator account to access that page.';
    $return_path = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($return_path, BASE_URL . 'admin/') !== 0) {
        $return_path = BASE_URL . 'admin/manage-products/';
    }
    header('Location: ' . BASE_URL . 'admin/login.php?redirect=' . urlencode($return_path));
    exit;
}

function admin_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function admin_set_flash($type, $message)
{
    $_SESSION['admin_flash'] = ['type' => $type, 'message' => $message];
}

function admin_take_flash()
{
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    return is_array($flash) ? $flash : null;
}
