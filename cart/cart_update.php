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
$cart_id = (int)($_POST['cart_id'] ?? 0);
$variant_id = (int)($_POST['variant_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);

$cart_item = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT c.cart_id, pc.product_id
     FROM cart c
     JOIN product_variants v ON v.variant_id = c.variant_id
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     WHERE c.cart_id = $cart_id AND c.user_id = $user_id"));

if (!$cart_item || $variant_id < 1 || $quantity < 1) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Choose a valid size and quantity before updating your item.'];
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

$product_id = (int)$cart_item['product_id'];

$variant = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT v.stock
     FROM product_variants v
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     WHERE v.variant_id = $variant_id AND pc.product_id = $product_id"));

if (!$variant || (int)$variant['stock'] < 1) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'That size is unavailable. Choose another size.'];
    header('Location: ' . BASE_URL . 'product.php?id=' . $product_id . '&cart_id=' . $cart_id);
    exit;
}

$stock = (int)$variant['stock'];
if ($quantity > $stock) {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'The selected quantity is greater than the available stock.'];
    header('Location: ' . BASE_URL . 'product.php?id=' . $product_id . '&cart_id=' . $cart_id);
    exit;
}

$duplicate = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT cart_id, quantity FROM cart
     WHERE user_id = $user_id AND variant_id = $variant_id AND cart_id <> $cart_id"));

if ($duplicate) {
    $merged_quantity = min((int)$duplicate['quantity'] + $quantity, $stock);
    $duplicate_cart_id = (int)$duplicate['cart_id'];
    mysqli_query($conn, "UPDATE cart SET quantity = $merged_quantity WHERE cart_id = $duplicate_cart_id AND user_id = $user_id");
    mysqli_query($conn, "DELETE FROM cart WHERE cart_id = $cart_id AND user_id = $user_id");
} else {
    mysqli_query($conn, "UPDATE cart SET variant_id = $variant_id, quantity = $quantity WHERE cart_id = $cart_id AND user_id = $user_id");
}

$_SESSION['flash'] = ['type' => 'success', 'text' => 'Your cart item has been updated.'];
header('Location: ' . BASE_URL . 'cart/cart.php');
exit;
