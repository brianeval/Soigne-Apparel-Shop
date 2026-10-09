<?php
session_start();
include_once ('../includes/config.php');

// Must be signed in (the pop-up on product.php is only a convenience)
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'users/login.php');
    exit;
}

// Only accept the Add to cart form
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

function flash_and_go($type, $text) {
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$variant_id = (int)($_POST['variant_id'] ?? 0);
$quantity   = max(1, (int)($_POST['quantity'] ?? 1));

// Does this size/color exist, and how many are in stock?
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

// New row, or add to the existing one (never above the stock)
$q = "INSERT INTO cart (user_id, variant_id, quantity) VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)";
$stmt = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($stmt, 'iiii', $user_id, $variant_id, $quantity, $stock);
mysqli_stmt_execute($stmt);

flash_and_go('success', 'Added to your cart.');
print_r($_SESSION['flash']);