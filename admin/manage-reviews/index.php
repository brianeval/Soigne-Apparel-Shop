<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$allowed_filters = ['pending', 'published', 'all'];
$status_filter = $_GET['status'] ?? 'pending';
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_set_flash('error', 'Your session expired. Please try again.');
    } elseif ($review_id === false || $review_id < 1 || !in_array($action, ['publish', 'unpublish'], true)) {
        admin_set_flash('error', 'That review action is invalid.');
    } else {
        $new_status = $action === 'publish' ? 'published' : 'pending';
        $expected_status = $action === 'publish' ? 'pending' : 'published';
        $stmt = mysqli_prepare($conn,
            "UPDATE product_reviews
             SET status = ?, published_at = CASE WHEN ? = 'published' THEN CURRENT_TIMESTAMP ELSE NULL END
             WHERE review_id = ? AND status = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ssis', $new_status, $new_status, $review_id, $expected_status);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            admin_set_flash('error', 'The review could not be updated.');
        } else {
            admin_set_flash('success', $new_status === 'published' ? 'Review published.' : 'Review unpublished.');
        }
    }

    header('Location: ' . BASE_URL . 'admin/manage-reviews/?status=' . rawurlencode($status_filter));
    exit;
}

$filter_sql = $status_filter === 'all' ? " WHERE r.status <> 'deleted'" : ' WHERE r.status = ?';
$count_stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM product_reviews r' . $filter_sql);
if ($status_filter !== 'all') {
    mysqli_stmt_bind_param($count_stmt, 's', $status_filter);
}
mysqli_stmt_execute($count_stmt);
$review_total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$pagination = admin_pagination_page($review_total, 20);

$review_sql =
    "SELECT r.review_id, r.rating, r.review_text, r.status, r.created_at, r.published_at,
            p.product_id, p.name AS product_name, u.username
     FROM product_reviews r
     JOIN products p ON p.product_id = r.product_id
     JOIN users u ON u.user_id = r.user_id" .
     ($status_filter === 'all' ? " WHERE r.status <> 'deleted'" : ' WHERE r.status = ?') .
     " ORDER BY (r.status = 'pending') DESC, r.created_at DESC, r.review_id DESC
       LIMIT ? OFFSET ?";
$reviews_stmt = mysqli_prepare($conn, $review_sql);
if ($status_filter === 'all') {
    mysqli_stmt_bind_param($reviews_stmt, 'ii', $pagination['page_size'], $pagination['offset']);
} else {
    mysqli_stmt_bind_param($reviews_stmt, 'sii', $status_filter, $pagination['page_size'], $pagination['offset']);
}
mysqli_stmt_execute($reviews_stmt);
$reviews = mysqli_fetch_all(mysqli_stmt_get_result($reviews_stmt), MYSQLI_ASSOC);

$flash = admin_take_flash();
$admin_page_title = 'Manage Reviews';
$admin_active_page = 'reviews';
include __DIR__ . '/../includes/header.php';
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CUSTOMER FEEDBACK</p><h1>Manage Reviews</h1><p>Customer reviews are published immediately. You can unpublish a review here if needed.</p></div>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>

<section class="manage-panel manage-list-panel">
  <div class="manage-panel-heading">
    <div><p class="admin-eyebrow">PRODUCT REVIEWS</p><h2><?php echo ucfirst($status_filter); ?> <span class="manage-count"><?php echo $review_total; ?></span></h2></div>
    <nav class="review-admin-filters" aria-label="Review status">
      <?php foreach ($allowed_filters as $filter) { ?>
        <a class="manage-small-button <?php echo $status_filter === $filter ? 'is-active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage-reviews/?status=<?php echo rawurlencode($filter); ?>"><?php echo ucfirst($filter); ?></a>
      <?php } ?>
    </nav>
  </div>
  <?php if ($reviews) { ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead><tr><th>Product</th><th>Customer review</th><th>Rating</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($reviews as $review) { ?>
            <tr>
              <td><a href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$review['product_id']; ?>#reviews"><?php echo admin_escape($review['product_name']); ?></a></td>
              <td><strong><?php echo admin_escape($review['username']); ?></strong><span class="manage-secondary"><?php echo nl2br(admin_escape($review['review_text'])); ?></span></td>
              <td><?php echo str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']); ?></td>
              <td><span class="review-admin-status is-<?php echo admin_escape($review['status']); ?>"><?php echo ucfirst(admin_escape($review['status'])); ?></span></td>
              <td><?php echo admin_escape(date('M j, Y', strtotime($review['created_at']))); ?></td>
              <td>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                  <input type="hidden" name="review_id" value="<?php echo (int)$review['review_id']; ?>">
                  <?php if ($review['status'] === 'pending') { ?>
                    <button class="manage-small-button" type="submit" name="action" value="publish">Publish</button>
                  <?php } else { ?>
                    <button class="manage-small-button" type="submit" name="action" value="unpublish">Unpublish</button>
                  <?php } ?>
                </form>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php admin_render_pagination($pagination['page'], $pagination['total_pages']); ?>
  <?php } else { ?>
    <p class="manage-empty">No <?php echo $status_filter === 'all' ? '' : admin_escape($status_filter) . ' '; ?>reviews found.</p>
  <?php } ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
