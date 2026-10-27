<?php
$conn = mysqli_connect('localhost', 'root', '', 'soigne_apparel');
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');

if (!defined('BASE_URL')) {
    define('BASE_URL', '/soigne_apparel_shop/');
}

if (!defined('APP_ROOT')) {
    $document_root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $app_relative_path = trim(str_replace('/', DIRECTORY_SEPARATOR, BASE_URL), '/\\');
    define('APP_ROOT', $document_root . DIRECTORY_SEPARATOR . $app_relative_path);
}

if (!function_exists('peso')) {
    function peso($amount) {
        return '₱' . number_format($amount, 2);  
    }
}