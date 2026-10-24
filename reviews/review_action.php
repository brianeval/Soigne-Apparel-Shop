<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/customer_auth.php';

$order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
$order_item_id = filter_var($_POST['order_item_id'] ?? null, FILTER_VALIDATE_INT);
$product_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
$redirect_url = BASE_URL . 'orders/orders.php';
require_active_customer($conn, $redirect_url);

$user_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$csrf_token = $_POST['csrf_token'] ?? '';

if (!is_string($csrf_token) || empty($_SESSION['orders_csrf'])
    || !hash_equals($_SESSION['orders_csrf'], $csrf_token)) {
    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    header('Location: ' . $redirect_url);
    exit;
}

if ($order_id === false || $order_id < 1 || $order_item_id === false || $order_item_id < 1
    || $product_id === false || $product_id < 1
    || !in_array($action, ['create', 'update', 'delete'], true)) {
    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'That review action is invalid.'];
    header('Location: ' . $redirect_url);
    exit;
}

$stmt = mysqli_prepare($conn,
    "SELECT o.payment_status
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.order_id
     JOIN product_variants v ON v.variant_id = oi.variant_id
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     WHERE o.order_id = ? AND oi.order_item_id = ? AND o.user_id = ?
       AND pc.product_id = ? AND o.payment_status = 'paid'
     LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'iiii', $order_id, $order_item_id, $user_id, $product_id);
mysqli_stmt_execute($stmt);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Only products in your paid orders can be reviewed.'];
    header('Location: ' . $redirect_url);
    exit;
}

$review_id = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
if ($action !== 'create' && ($review_id === false || $review_id < 1)) {
    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'That review action is invalid.'];
    header('Location: ' . $redirect_url);
    exit;
}

if ($action !== 'create') {
    $stmt = mysqli_prepare($conn,
        "SELECT review_id
         FROM product_reviews
         WHERE review_id = ? AND product_id = ? AND user_id = ?
           AND status IN ('pending', 'published')"
    );
    mysqli_stmt_bind_param($stmt, 'iii', $review_id, $product_id, $user_id);
    mysqli_stmt_execute($stmt);
    if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Only your active review can be edited or deleted.'];
        header('Location: ' . $redirect_url);
        exit;
    }
}

if ($action === 'delete') {
    $stmt = mysqli_prepare($conn,
        "UPDATE product_reviews
         SET status = 'deleted', updated_at = CURRENT_TIMESTAMP
         WHERE review_id = ? AND product_id = ? AND user_id = ?
           AND status IN ('pending', 'published')"
    );
    mysqli_stmt_bind_param($stmt, 'iii', $review_id, $product_id, $user_id);
    mysqli_stmt_execute($stmt);
    $_SESSION['orders_flash'] = mysqli_stmt_affected_rows($stmt) === 1
        ? ['type' => 'success', 'text' => 'Your review was deleted. You can submit a new review for this product.']
        : ['type' => 'error', 'text' => 'Only your active review can be deleted.'];
    header('Location: ' . $redirect_url);
    exit;
}

$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$submitted_review_text = $_POST['review_text'] ?? '';
$review_text = is_string($submitted_review_text) ? trim($submitted_review_text) : '';
$review_length = preg_match_all('/./us', $review_text, $review_characters);
$normalized_review = strtolower(strtr($review_text, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']));
$vulgar_pattern = '/(?:putang\s*ina|tangina|tanga|gago|gaga|puta|fuck(?:er|ing|ed|s)?|shit(?:ty|ting|s)?|bitch(?:es|y)?|ass(?:hole)?s?|arse(?:hole)?s?|bastard(?:s)?|cunt(?:s)?|dick(?:s)?|cock(?:s|head)?|piss(?:ed|ing)?|damn(?:ed|ing)?|dammit|crap(?:py)?|twat(?:s)?|wanker(?:s)?|whore(?:s)?|slut(?:s)?|prick(?:s)?|douche(?:bag)?s?|bullshit|motherfucker(?:s)?)/i';
$tagalog_vulgar_pattern = '/(?<![a-z])(?:ulol|bobo|kupal|tarantado|bwisit|bwesit|leche|lintik|punyeta|punyemas|pakshet|pakshit|pakyu|taena|teena|putragis|ogag|hinayupak)(?![a-z])/i';
$vulgar_matches = [];
$contains_vulgar_language = $review_length === false
    ? false
    : preg_match_all($vulgar_pattern, $normalized_review, $vulgar_matches, PREG_OFFSET_CAPTURE);
$tagalog_vulgar_matches = [];
$contains_tagalog_vulgar_language = $review_length === false
    ? false
    : preg_match_all($tagalog_vulgar_pattern, $normalized_review, $tagalog_vulgar_matches, PREG_OFFSET_CAPTURE);

if ($rating === false || $rating < 1 || $rating > 5 || $review_length === false || $review_length < 3 || $review_length > 1000) {
    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Choose a rating and write a review between 3 and 1,000 characters.'];
    header('Location: ' . $redirect_url);
    exit;
}
if ($contains_vulgar_language === false || $contains_tagalog_vulgar_language === false) {
    error_log('Review vulgar-language pattern failed to compile.');
    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'The review could not be checked. Please try again later.'];
    header('Location: ' . $redirect_url);
    exit;
}
if ($contains_vulgar_language + $contains_tagalog_vulgar_language > 0) {
    $all_vulgar_matches = array_merge($vulgar_matches[0], $tagalog_vulgar_matches[0]);
    usort($all_vulgar_matches, function ($first, $second) {
        return $first[1] <=> $second[1];
    });
    foreach (array_reverse($all_vulgar_matches) as $vulgar_match) {
        $matched_length = strlen($vulgar_match[0]);
        if ($matched_length > 0) {
            $review_text = substr_replace(
                $review_text,
                str_repeat('*', $matched_length),
                $vulgar_match[1],
                $matched_length
            );
        }
    }
}

try {
    if ($action === 'create') {
        $stmt = mysqli_prepare($conn,
            "UPDATE product_reviews
             SET rating = ?, review_text = ?, order_item_id = ?, status = 'published',
                 published_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE product_id = ? AND user_id = ? AND status = 'deleted'"
        );
        mysqli_stmt_bind_param($stmt, 'isiii', $rating, $review_text, $order_item_id, $product_id, $user_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) === 0) {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO product_reviews (product_id, user_id, order_item_id, rating, review_text, status, published_at)
                 VALUES (?, ?, ?, ?, ?, 'published', CURRENT_TIMESTAMP)"
            );
            mysqli_stmt_bind_param($stmt, 'iiiis', $product_id, $user_id, $order_item_id, $rating, $review_text);
            mysqli_stmt_execute($stmt);
        }
        $_SESSION['orders_flash'] = ['type' => 'success', 'text' => 'Thanks! Your review is now published.'];
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE product_reviews
             SET rating = ?, review_text = ?, order_item_id = ?, updated_at = CURRENT_TIMESTAMP
             WHERE review_id = ? AND product_id = ? AND user_id = ?
               AND status IN ('pending', 'published')"
        );
        mysqli_stmt_bind_param($stmt, 'isiiii', $rating, $review_text, $order_item_id, $review_id, $product_id, $user_id);
        mysqli_stmt_execute($stmt);
        $updated = mysqli_stmt_affected_rows($stmt) === 1;
        if (!$updated) {
            $check_stmt = mysqli_prepare($conn,
                "SELECT 1 FROM product_reviews
                 WHERE review_id = ? AND product_id = ? AND user_id = ?
                   AND status IN ('pending', 'published')"
            );
            mysqli_stmt_bind_param($check_stmt, 'iii', $review_id, $product_id, $user_id);
            mysqli_stmt_execute($check_stmt);
            $updated = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));
        }
        if (!$updated) {
            $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Only your active review can be edited.'];
        } else {
            $_SESSION['orders_flash'] = ['type' => 'success', 'text' => 'Your review has been updated.'];
        }
    }
} catch (mysqli_sql_exception $exception) {
    error_log('Product review action failed: ' . $exception->getMessage());
    if ($action === 'create' && (int)$exception->getCode() === 1062) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'You have already submitted a review for this product.'];
    } else {
        $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'The review could not be saved. Please try again.'];
    }
}

header('Location: ' . $redirect_url);
exit;
