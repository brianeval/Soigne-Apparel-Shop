<?php
// Delete a review
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $review_id = (int)($_POST['review_id'] ?? 0);

    if ($review_id < 1 || ($_POST['action'] ?? '') != 'delete') {
        $_SESSION['message'] = 'That review action is invalid.';
    } else {
        mysqli_query($conn, "UPDATE product_reviews SET status = 'deleted', updated_at = CURRENT_TIMESTAMP
                             WHERE review_id = $review_id AND status IN ('pending', 'published')");
        if (mysqli_affected_rows($conn) != 1) {
            $_SESSION['message'] = 'The review could not be deleted.';
        } else {
            $_SESSION['success'] = 'Review deleted.';
        }
    }

    // Go back to the list with the same filters
    $review_filters = [
        'product_search' => $_GET['product_search'] ?? '',
        'rating' => $_GET['rating'] ?? '',
    ];
    header('Location: ' . BASE_URL . 'admin/manage-reviews/?' . http_build_query($review_filters));
    exit;
}
