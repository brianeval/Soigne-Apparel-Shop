<?php
session_start();
require_once('../includes/config.php');
require_once('../includes/customer_auth.php');

require_login();

$redirect_url = BASE_URL . 'orders/orders.php';
$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_POST['order_id'] ?? 0);
$order_item_id = (int)($_POST['order_item_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$review_id = (int)($_POST['review_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($order_id < 1 || $order_item_id < 1 || $product_id < 1
    || !in_array($action, ['create', 'update', 'delete'], true)) {
    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'That review action is invalid.'];
    header('Location: ' . $redirect_url);
    exit;
}

$purchased = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT o.payment_status
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.order_id
     JOIN product_variants v ON v.variant_id = oi.variant_id
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     WHERE o.order_id = $order_id AND oi.order_item_id = $order_item_id
       AND o.user_id = $user_id AND pc.product_id = $product_id AND o.payment_status = 'paid'
     LIMIT 1"));

if (!$purchased) {
    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Only products in your paid orders can be reviewed.'];
    header('Location: ' . $redirect_url);
    exit;
}

if ($action !== 'create') {
    $existing = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT review_id FROM product_reviews
         WHERE review_id = $review_id AND product_id = $product_id AND user_id = $user_id
           AND status IN ('pending', 'published')"));
    if (!$existing) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Only your active review can be edited or deleted.'];
        header('Location: ' . $redirect_url);
        exit;
    }
}

if ($action === 'delete') {
    mysqli_query($conn,
        "UPDATE product_reviews SET status = 'deleted', updated_at = CURRENT_TIMESTAMP
         WHERE review_id = $review_id AND product_id = $product_id AND user_id = $user_id
           AND status IN ('pending', 'published')");
    $_SESSION['orders_flash'] = ['type' => 'success', 'text' => 'Your review was deleted. You can submit a new review for this product.'];
    header('Location: ' . $redirect_url);
    exit;
}

$rating = (int)($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');

if ($rating < 1 || $rating > 5 || $review_text === '') {
    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Choose a rating and write a review.'];
    header('Location: ' . $redirect_url);
    exit;
}

$review_text_sql = mysqli_real_escape_string($conn, $review_text);

if ($action === 'create') {
    $deleted = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT review_id FROM product_reviews
         WHERE product_id = $product_id AND user_id = $user_id AND status = 'deleted'"));
    if ($deleted) {
        $deleted_id = (int)$deleted['review_id'];
        mysqli_query($conn,
            "UPDATE product_reviews
             SET rating = $rating, review_text = '$review_text_sql', order_item_id = $order_item_id,
                 status = 'published', published_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE review_id = $deleted_id");
    } else {
        mysqli_query($conn,
            "INSERT INTO product_reviews (product_id, user_id, order_item_id, rating, review_text, status, published_at)
             VALUES ($product_id, $user_id, $order_item_id, $rating, '$review_text_sql', 'published', CURRENT_TIMESTAMP)");
    }
    $_SESSION['orders_flash'] = ['type' => 'success', 'text' => 'Thanks! Your review is now published.'];
} else {
    mysqli_query($conn,
        "UPDATE product_reviews
         SET rating = $rating, review_text = '$review_text_sql', order_item_id = $order_item_id,
             updated_at = CURRENT_TIMESTAMP
         WHERE review_id = $review_id AND product_id = $product_id AND user_id = $user_id
           AND status IN ('pending', 'published')");
    $_SESSION['orders_flash'] = ['type' => 'success', 'text' => 'Your review has been updated.'];
}

header('Location: ' . $redirect_url);
exit;
