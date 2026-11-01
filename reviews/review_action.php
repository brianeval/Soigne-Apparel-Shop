<?php
session_start();
include_once("../includes/config.php");

$redirect_url = BASE_URL . 'orders/orders.php';
$login_redirect = $redirect_url;
include("../includes/require_customer.php");

$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$order_id = (int)($_POST['order_id'] ?? 0);
$order_item_id = (int)($_POST['order_item_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$review_id = (int)($_POST['review_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');

// Basic checks
$error = '';
if ($order_id < 1 || $order_item_id < 1 || $product_id < 1 || !in_array($action, ['create', 'update', 'delete'])) {
    $error = 'That review action is invalid.';
} elseif ($action != 'create' && $review_id < 1) {
    $error = 'That review action is invalid.';
} elseif ($action != 'delete' && ($rating < 1 || $rating > 5 || strlen($review_text) < 3 || strlen($review_text) > 1000)) {
    $error = 'Choose a rating and write a review between 3 and 1,000 characters.';
}

// Only products from this customer's paid orders can be reviewed
if ($error == '') {
    $purchased_result = mysqli_query($conn,
        "SELECT o.order_id
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.order_id
         JOIN product_variants v ON v.variant_id = oi.variant_id
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         WHERE o.order_id = $order_id AND oi.order_item_id = $order_item_id AND o.user_id = $user_id
           AND pc.product_id = $product_id AND o.payment_status = 'paid'"
    );
    if (mysqli_num_rows($purchased_result) == 0) {
        $error = 'Only products in your paid orders can be reviewed.';
    }
}

// Editing or deleting needs one of the customer's own active reviews
if ($error == '' && $action != 'create') {
    $existing_result = mysqli_query($conn,
        "SELECT review_id FROM product_reviews
         WHERE review_id = $review_id AND product_id = $product_id AND user_id = $user_id
           AND status IN ('pending', 'published')"
    );
    if (mysqli_num_rows($existing_result) == 0) {
        $error = 'Only your active review can be edited or deleted.';
    }
}

if ($error != '') {
    $_SESSION['message'] = $error;
    header('Location: ' . $redirect_url);
    exit;
}

// Hide bad words without masking the rest of the word they appear in
$bad_words = ['putang ina', 'tangina', 'putragis', 'tarantado', 'punyemas', 'punyeta', 'hinayupak',
              'motherfucker', 'bullshit', 'asshole', 'bastard', 'fuck', 'shit', 'bitch', 'cunt', 'dick',
              'cock', 'piss', 'whore', 'slut', 'wanker', 'twat', 'prick', 'douche', 'crap', 'damn',
              'gago', 'gaga', 'puta', 'tanga', 'ulol', 'bobo', 'kupal', 'bwisit', 'bwesit', 'leche',
              'lintik', 'pakshet', 'pakshit', 'pakyu', 'taena', 'teena', 'ogag'];
foreach ($bad_words as $bad_word) {
    $review_text = preg_replace('/' . $bad_word . '/i', str_repeat('*', strlen($bad_word)), $review_text);
}

try {
    if ($action == 'delete') {
        $delete_result = mysqli_execute_query($conn, "UPDATE product_reviews SET status = 'deleted', updated_at = CURRENT_TIMESTAMP
             WHERE review_id = ? AND product_id = ? AND user_id = ?", [$review_id, $product_id, $user_id]);
        $message = 'Your review was deleted. You can submit a new review for this product.';
    } elseif ($action == 'create') {
        // A deleted review of the same product is brought back, otherwise a new one is added
        $restore_result = mysqli_execute_query($conn, "UPDATE product_reviews
             SET rating = ?, review_text = ?, order_item_id = ?, status = 'published',
                 published_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE product_id = ? AND user_id = ? AND status = 'deleted'", [$rating, $review_text, $order_item_id, $product_id, $user_id]);
        if (mysqli_affected_rows($conn) == 0) {
            $create_result = mysqli_execute_query($conn, "INSERT INTO product_reviews (product_id, user_id, order_item_id, rating, review_text, status, published_at)
                 VALUES (?, ?, ?, ?, ?, 'published', CURRENT_TIMESTAMP)", [$product_id, $user_id, $order_item_id, $rating, $review_text]);
        }
        $message = 'Thanks! Your review is now published.';
    } else {
        $update_result = mysqli_execute_query($conn, "UPDATE product_reviews
             SET rating = ?, review_text = ?, order_item_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE review_id = ? AND product_id = ? AND user_id = ?", [$rating, $review_text, $order_item_id, $review_id, $product_id, $user_id]);
        $message = 'Your review has been updated.';
    }
    $_SESSION['success'] = $message;
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        $_SESSION['message'] = 'You have already submitted a review for this product.';
    } else {
        $_SESSION['message'] = 'The review could not be saved. Please try again.';
    }
}

header('Location: ' . $redirect_url);
exit;
