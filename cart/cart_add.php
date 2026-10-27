<?php
session_start();
include_once ('../includes/config.php');
require_once("../includes/customer_auth.php");

// Keep an administrator's add-to-cart request while they sign in with a customer account.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_SESSION['role'] ?? '') === 'admin') {
    $_SESSION['pending_cart_add'] = [
        'variant_id' => (int)($_POST['variant_id'] ?? 0),
        'quantity' => max(1, (int)($_POST['quantity'] ?? 1)),
    ];
    $resume_path = BASE_URL . 'cart/cart_add.php?resume=1';
    header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode($resume_path));
    exit;
}

$is_resume = $_SERVER['REQUEST_METHOD'] === 'GET'
    && ($_GET['resume'] ?? '') === '1'
    && isset($_SESSION['pending_cart_add'])
    && is_array($_SESSION['pending_cart_add']);
$redirect_path = $is_resume
    ? BASE_URL . 'cart/cart_add.php?resume=1'
    : BASE_URL . 'cart/cart.php';

// Only active customer accounts can add products to a cart.
require_active_customer($conn, $redirect_path);

if ($is_resume) {
    $pending_add = $_SESSION['pending_cart_add'];
    unset($_SESSION['pending_cart_add']);
    $variant_id = (int)($pending_add['variant_id'] ?? 0);
    $quantity = max(1, (int)($pending_add['quantity'] ?? 1));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
} else {
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

function flash_and_go($type, $text) {
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];

// This query checks whether the chosen size exists and how many items are left in stock.
$stmt = mysqli_prepare($conn, "SELECT stock FROM product_variants WHERE variant_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $variant_id);
mysqli_stmt_execute($stmt);
$variant = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$variant) {
    flash_and_go('error', 'Please choose a size before adding to cart.');
}

$stock = (int)$variant['stock'];
if ($stock < 1) {
    flash_and_go('error', 'Sorry, that size is sold out.');
}

$quantity = min($quantity, $stock);

// This query adds the item to the cart. If the same size is already there, it increases the quantity without going over stock.
$q = "INSERT INTO cart (user_id, variant_id, quantity) VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)";
$stmt = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($stmt, 'iiii', $user_id, $variant_id, $quantity, $stock);
mysqli_stmt_execute($stmt);

flash_and_go('success', 'Added to your cart.');
print_r($_SESSION['flash']);