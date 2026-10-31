<?php
session_start();
include_once('../includes/config.php');
require_once('../includes/customer_auth.php');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if (($_SESSION['role'] ?? '') === 'admin') {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Administrators cannot add products to the cart.'];
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$variant_id = (int)($_POST['variant_id'] ?? 0);
$quantity = max(1, (int)($_POST['quantity'] ?? 1));

$variant = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT stock FROM product_variants WHERE variant_id = $variant_id"));

if (!$variant || (int)$variant['stock'] < 1) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Sorry, that size is sold out.'];
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

$stock = (int)$variant['stock'];
$quantity = min($quantity, $stock);

mysqli_query($conn,
    "INSERT INTO cart (user_id, variant_id, quantity)
     VALUES ($user_id, $variant_id, $quantity)
     ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + $quantity, $stock)");

$_SESSION['flash'] = ['type' => 'success', 'text' => 'Added to your cart.'];
header('Location: ' . BASE_URL . 'cart/cart.php');
exit;
