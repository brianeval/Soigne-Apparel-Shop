<?php
$conn = mysqli_connect('localhost', 'root', '', 'soigne_apparel');
if (!$conn) {
    die('Database connection failed.');
}

define('BASE_URL', '/soigne_apparel_shop/');
define('APP_ROOT', dirname(__DIR__));

function peso($amount)
{
    return '₱' . number_format($amount, 2);
}

function price_html($price)
{
    if (($_SESSION['role'] ?? '') === 'vip') {
        return '<span class="premium-product-price"><del>' . peso($price) . '</del> '
            . '<strong>' . peso($price * 0.90) . '</strong><small>VIP 10% off</small></span>';
    }
    return peso($price);
}
