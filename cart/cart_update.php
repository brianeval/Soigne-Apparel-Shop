<?php
session_start();
include_once("../includes/config.php");

$login_redirect = BASE_URL . 'cart/cart.php';
include("../includes/require_customer.php");

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$cart_id = (int)($_POST['cart_id'] ?? 0);
$variant_id = (int)($_POST['variant_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 0);

$error = '';
$back_to_product = 0;

if ($cart_id < 1 || $variant_id < 1 || $quantity < 1) {
    $error = 'Choose a valid size and quantity before updating your item.';
} else {
    try {
        mysqli_begin_transaction($conn);


        $cart_item_result = mysqli_execute_query($conn, "SELECT c.cart_id, pc.product_id
             FROM cart c
             JOIN product_variants v ON v.variant_id = c.variant_id
             JOIN product_colors pc ON pc.product_color_id = v.product_color_id
             WHERE c.cart_id = ? AND c.user_id = ?", [$cart_id, $user_id]);
        $cart_item = mysqli_fetch_assoc($cart_item_result);

        if (!$cart_item) {
            $error = 'That cart item could not be found.';
        } else {
            $product_id = (int)$cart_item['product_id'];


            $variant_result = mysqli_execute_query($conn, "SELECT v.stock
                 FROM product_variants v
                 JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                 WHERE v.variant_id = ? AND pc.product_id = ?", [$variant_id, $product_id]);
            $variant = mysqli_fetch_assoc($variant_result);


            $duplicate_result = mysqli_execute_query($conn, "SELECT cart_id, quantity FROM cart WHERE user_id = ? AND variant_id = ? AND cart_id <> ?", [$user_id, $variant_id, $cart_id]);
            $duplicate = mysqli_fetch_assoc($duplicate_result);

            if (!$variant || $variant['stock'] < 1) {
                $error = 'That size is unavailable. Choose another size.';
                $back_to_product = $product_id;
            } elseif ($quantity > $variant['stock']) {
                $error = 'The selected quantity is greater than the available stock.';
                $back_to_product = $product_id;
            } elseif ($duplicate && $duplicate['quantity'] + $quantity > $variant['stock']) {
                $error = 'That size is already in your cart, and the combined quantity exceeds available stock.';
                $back_to_product = $product_id;
            } elseif ($duplicate) {

                $merged_quantity = $duplicate['quantity'] + $quantity;
                $duplicate_cart_id = (int)$duplicate['cart_id'];
                mysqli_execute_query($conn, "UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?", [$merged_quantity, $duplicate_cart_id, $user_id]);
                mysqli_execute_query($conn, "DELETE FROM cart WHERE cart_id = ? AND user_id = ?", [$cart_id, $user_id]);
            } else {

                mysqli_execute_query($conn, "UPDATE cart SET variant_id = ?, quantity = ? WHERE cart_id = ? AND user_id = ?", [$variant_id, $quantity, $cart_id, $user_id]);
            }
        }

        if ($error == '') {
            mysqli_commit($conn);
            $_SESSION['success'] = 'Your cart item has been updated.';
        } else {
            mysqli_rollback($conn);
        }
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        $error = 'Could not update your cart item. Please try again.';
        $back_to_product = 0;
    }
}

if ($error != '') {
    $_SESSION['message'] = $error;
}

if ($back_to_product > 0) {
    header('Location: ' . BASE_URL . 'product.php?id=' . $back_to_product . '&cart_id=' . $cart_id);
} else {
    header('Location: ' . BASE_URL . 'cart/cart.php');
}
exit;
