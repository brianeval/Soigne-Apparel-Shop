<?php
session_start();
include_once ('../includes/config.php');
require_once("../includes/customer_auth.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['role'] ?? '') === 'admin') {
    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $product_stmt = mysqli_prepare($conn,
        'SELECT p.product_id
         FROM product_variants v
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         JOIN products p ON p.product_id = pc.product_id
         WHERE v.variant_id = ?'
    );
    mysqli_stmt_bind_param($product_stmt, 'i', $variant_id);
    mysqli_stmt_execute($product_stmt);
    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($product_stmt));
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Administrators cannot add products to the cart.'];
    $location = $product
        ? BASE_URL . 'product.php?id=' . (int)$product['product_id']
        : BASE_URL . 'index.php';
    header('Location: ' . $location);
    exit;
}

// Customer access
require_active_customer($conn, BASE_URL . 'cart/cart.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
} else {
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

function flash_and_go($type, $text)
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];

// Check variant stock
$variant_stmt = mysqli_prepare($conn, "SELECT stock FROM product_variants WHERE variant_id = ?");
mysqli_stmt_bind_param($variant_stmt, 'i', $variant_id);
mysqli_stmt_execute($variant_stmt);
$variant = mysqli_fetch_assoc(mysqli_stmt_get_result($variant_stmt));

if (!$variant) {
    flash_and_go('error', 'Please choose a size before adding to cart.');
}

$stock = (int)$variant['stock'];
if ($stock < 1) {
    flash_and_go('error', 'Sorry, that size is sold out.');
}

$quantity = min($quantity, $stock);

// Add item to cart
$cart_insert_sql = "INSERT INTO cart (user_id, variant_id, quantity)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)";
$cart_insert_stmt = mysqli_prepare($conn, $cart_insert_sql);
mysqli_stmt_bind_param($cart_insert_stmt, 'iiii', $user_id, $variant_id, $quantity, $stock);
mysqli_stmt_execute($cart_insert_stmt);

flash_and_go('success', 'Added to your cart.');