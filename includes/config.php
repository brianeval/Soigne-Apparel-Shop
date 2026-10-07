<?php
$conn = mysqli_connect('localhost', 'root', '', 'soigne_apparel');
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');

if (!defined('BASE_URL')) {
    define('BASE_URL', '/soigne_apparel_shop/');
}

if (!function_exists('peso')) {
    function peso($amount) {
        return '₱' . number_format($amount, 2);  
    }
}