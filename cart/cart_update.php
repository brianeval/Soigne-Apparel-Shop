<?php
session_start();
include_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode(BASE_URL . 'cart/cart.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

function update_flash_and_go(string $type, string $text, ?int $product_id = null, ?int $cart_id = null): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
    if ($product_id && $cart_id) {
        header('Location: ' . BASE_URL . 'product.php?id=' . $product_id . '&cart_id=' . $cart_id);
    } else {
        header('Location: ' . BASE_URL . 'cart/cart.php');
    }
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$posted_cart_id = $_POST['cart_id'] ?? null;
$posted_variant_id = $_POST['variant_id'] ?? null;
$posted_quantity = $_POST['quantity'] ?? null;
$cart_id = is_scalar($posted_cart_id) ? (int)$posted_cart_id : 0;
$variant_id = is_scalar($posted_variant_id) ? (int)$posted_variant_id : 0;
$quantity = is_string($posted_quantity) || is_int($posted_quantity)
    ? filter_var($posted_quantity, FILTER_VALIDATE_INT)
    : false;

if ($cart_id < 1 || $variant_id < 1 || $quantity === false || $quantity === null || $quantity < 1) {
    update_flash_and_go('error', 'Choose a valid size and quantity before updating your item.');
}

try {
    mysqli_begin_transaction($conn);

    $stmt = mysqli_prepare($conn,
        "SELECT c.cart_id, pc.product_id
         FROM cart c
         JOIN product_variants v ON v.variant_id = c.variant_id
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         WHERE c.cart_id = ? AND c.user_id = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
    mysqli_stmt_execute($stmt);
    $cart_item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$cart_item) {
        mysqli_rollback($conn);
        update_flash_and_go('error', 'That cart item could not be found.');
    }

    $product_id = (int)$cart_item['product_id'];
    $stmt = mysqli_prepare($conn,
        "SELECT v.stock
         FROM product_variants v
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         WHERE v.variant_id = ? AND pc.product_id = ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $variant_id, $product_id);
    mysqli_stmt_execute($stmt);
    $variant = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$variant || (int)$variant['stock'] < 1) {
        mysqli_rollback($conn);
        update_flash_and_go('error', 'That size is unavailable. Choose another size.', $product_id, $cart_id);
    }

    $stock = (int)$variant['stock'];
    if ($quantity > $stock) {
        mysqli_rollback($conn);
        update_flash_and_go('error', 'The selected quantity is greater than the available stock.', $product_id, $cart_id);
    }

    $stmt = mysqli_prepare($conn,
        "SELECT cart_id, quantity
         FROM cart
         WHERE user_id = ? AND variant_id = ? AND cart_id <> ?
         FOR UPDATE"
    );
    mysqli_stmt_bind_param($stmt, 'iii', $user_id, $variant_id, $cart_id);
    mysqli_stmt_execute($stmt);
    $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($duplicate) {
        $merged_quantity = (int)$duplicate['quantity'] + $quantity;
        if ($merged_quantity > $stock) {
            mysqli_rollback($conn);
            update_flash_and_go('error', 'That size is already in your cart, and the combined quantity exceeds available stock.', $product_id, $cart_id);
        }

        $duplicate_cart_id = (int)$duplicate['cart_id'];
        $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $merged_quantity, $duplicate_cart_id, $user_id);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
        mysqli_stmt_execute($stmt);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE cart SET variant_id = ?, quantity = ? WHERE cart_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, 'iiii', $variant_id, $quantity, $cart_id, $user_id);
        mysqli_stmt_execute($stmt);
    }

    mysqli_commit($conn);
    $_SESSION['flash'] = ['type' => 'success', 'text' => 'Your cart item has been updated.'];
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($conn);
    update_flash_and_go('error', 'Could not update your cart item. Please try again.');
}
