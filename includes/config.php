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
    define('APP_ROOT', rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/' . trim(BASE_URL, '/'));
}
