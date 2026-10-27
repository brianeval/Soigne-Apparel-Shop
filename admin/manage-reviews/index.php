<?php
require_once("../includes/bootstrap.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        admin_set_flash('error', 'Your session expired. Please try again.');
    } elseif ($review_id === false || $review_id < 1 || $action !== 'delete') {
        admin_set_flash('error', 'That review action is invalid.');
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE product_reviews
             SET status = 'deleted', updated_at = CURRENT_TIMESTAMP
             WHERE review_id = ? AND status IN ('pending', 'published')"
        );
        mysqli_stmt_bind_param($stmt, 'i', $review_id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            admin_set_flash('error', 'The review could not be deleted.');
        } else {
            admin_set_flash('success', 'Review deleted.');
        }
    }

    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
    $page = $page === false ? 1 : max(1, $page);
    header('Location: ' . BASE_URL . 'admin/manage-reviews/?page=' . $page);
    exit;
}

$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM product_reviews WHERE status IN ('pending', 'published')");
mysqli_stmt_execute($count_stmt);
$review_total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
$pagination = admin_pagination_page($review_total, 20);

$review_sql =
     "SELECT r.review_id, r.rating, r.review_text, r.status, r.created_at, r.published_at,
             p.product_id, p.name AS product_name, u.username
      FROM product_reviews r
      JOIN products p ON p.product_id = r.product_id
      JOIN users u ON u.user_id = r.user_id
      WHERE r.status IN ('pending', 'published')
      ORDER BY (r.status = 'pending') DESC, r.created_at DESC, r.review_id DESC
      LIMIT ? OFFSET ?";
$reviews_stmt = mysqli_prepare($conn, $review_sql);
mysqli_stmt_bind_param($reviews_stmt, 'ii', $pagination['page_size'], $pagination['offset']);
mysqli_stmt_execute($reviews_stmt);
$reviews = mysqli_fetch_all(mysqli_stmt_get_result($reviews_stmt), MYSQLI_ASSOC);

$flash = admin_take_flash();
$admin_page_title = 'Manage Reviews';
$admin_active_page = 'reviews';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CUSTOMER FEEDBACK</p><h1>Manage Reviews</h1><p>Review customer feedback and remove reviews when needed.</p></div>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>

<section class="manage-panel manage-list-panel">
  <div class="manage-panel-heading">
    <div><p class="admin-eyebrow">PRODUCT REVIEWS</p><h2>Reviews <span class="manage-count"><?php echo $review_total; ?></span></h2></div>
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
                <form method="post" onsubmit="return confirm('Delete this review? It will no longer be visible to customers.');">
                  <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                  <input type="hidden" name="review_id" value="<?php echo (int)$review['review_id']; ?>">
                  <button class="manage-small-button is-danger" type="submit" name="action" value="delete">Delete</button>
                </form>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php admin_render_pagination($pagination['page'], $pagination['total_pages']); ?>
  <?php } else { ?>
    <p class="manage-empty">No reviews found.</p>
  <?php } ?>
</section>
<?php include("../includes/footer.php"); ?>
