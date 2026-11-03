<?php
session_start();
include_once('../includes/config.php');


if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_SESSION['role'] ?? '') == 'admin') {
    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $product_result = mysqli_query($conn,
        "SELECT p.product_id
         FROM product_variants v
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         JOIN products p ON p.product_id = pc.product_id
         WHERE v.variant_id = $variant_id"
    );
    $product = mysqli_fetch_assoc($product_result);
    $_SESSION['message'] = 'Administrators cannot add products to the cart.';
    if ($product) {
        header('Location: ' . BASE_URL . 'product.php?id=' . $product['product_id']);
    } else {
        header('Location: ' . BASE_URL . 'index.php');
    }
    exit;
}


$login_redirect = BASE_URL . 'cart/cart.php';
include("../includes/require_customer.php");

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$variant_id = (int)($_POST['variant_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));


$variant_result = mysqli_execute_query($conn, "SELECT stock FROM product_variants WHERE variant_id = ?", [$variant_id]);
$variant = mysqli_fetch_assoc($variant_result);

if (!$variant) {
    $_SESSION['message'] = 'Please choose a size before adding to cart.';
} elseif ($variant['stock'] < 1) {
    $_SESSION['message'] = 'Sorry, that size is sold out.';
} else {

    $stock = (int)$variant['stock'];
    $quantity = min($quantity, $stock);
    $cart_insert_sql = "INSERT INTO cart (user_id, variant_id, quantity)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)";
    $cart_insert_result = mysqli_execute_query($conn, $cart_insert_sql, [$user_id, $variant_id, $quantity, $stock]);
    $_SESSION['success'] = 'Added to your cart.';
}

header('Location: ' . BASE_URL . 'cart/cart.php');
exit;
