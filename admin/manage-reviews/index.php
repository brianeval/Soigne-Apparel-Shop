<?php
require_once "../includes/bootstrap.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = (int)($_POST['review_id'] ?? 0);
    if ($review_id < 1 || ($_POST['action'] ?? '') !== 'delete') {
        admin_set_flash('error', 'That review action is invalid.');
    } else {
        mysqli_query($conn,
            "UPDATE product_reviews SET status = 'deleted', updated_at = CURRENT_TIMESTAMP
             WHERE review_id = $review_id AND status IN ('pending', 'published')"
        );
        admin_set_flash('success', 'Review deleted.');
    }
    header('Location: ' . BASE_URL . 'admin/manage-reviews/');
    exit;
}

$product_search = trim($_GET['product_search'] ?? '');
$rating = (int)($_GET['rating'] ?? 0);
if ($rating < 1 || $rating > 5) {
    $rating = 0;
}

$where = ["r.status IN ('pending', 'published')"];
if ($product_search !== '') {
    $where[] = "p.name LIKE '%" . mysqli_real_escape_string($conn, $product_search) . "%'";
}
if ($rating > 0) {
    $where[] = "r.rating = $rating";
}
$reviews = mysqli_fetch_all(mysqli_query($conn,
    "SELECT r.review_id, r.rating, r.review_text, r.status, r.created_at, r.published_at,
            p.product_id, p.name AS product_name, u.username
     FROM product_reviews r
     JOIN products p ON p.product_id = r.product_id
     JOIN users u ON u.user_id = r.user_id
     WHERE " . implode(' AND ', $where) . "
     ORDER BY (r.status = 'pending') DESC, r.created_at DESC, r.review_id DESC"
), MYSQLI_ASSOC);
$review_total = count($reviews);

$flash = admin_take_flash();
$admin_page_title = 'Manage Reviews';
$admin_active_page = 'reviews';
include "../includes/header.php";
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CUSTOMER FEEDBACK</p><h1>Manage Reviews</h1><p>Review customer feedback and remove reviews when needed.</p></div>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>

<section class="manage-panel manage-list-panel">
  <div class="manage-panel-heading">
    <div><p class="admin-eyebrow">PRODUCT REVIEWS</p><h2>Reviews <span class="manage-count"><?php echo $review_total; ?></span></h2></div>
    <form method="get" class="inventory-history-search review-filters">
      <label class="visually-hidden" for="review-product-search">Search product name</label>
      <input id="review-product-search" type="search" name="product_search" value="<?php echo admin_escape($product_search); ?>" placeholder="Search product name">
      <label class="visually-hidden" for="review-rating-filter">Filter by rating</label>
      <select id="review-rating-filter" name="rating">
        <option value="">All ratings</option>
        <?php for ($star_rating = 5; $star_rating >= 1; $star_rating--) { ?>
          <option value="<?php echo $star_rating; ?>" <?php echo $rating === $star_rating ? 'selected' : ''; ?>><?php echo $star_rating; ?> star<?php echo $star_rating === 1 ? '' : 's'; ?></option>
        <?php } ?>
      </select>
      <button class="admin-button admin-button-light" type="submit">Apply</button>
    </form>
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
                <div class="manage-form-buttons">
                  <a class="manage-small-button" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$review['product_id']; ?>&amp;review_id=<?php echo (int)$review['review_id']; ?>#review-<?php echo (int)$review['review_id']; ?>">View review</a>
                  <form method="post">
                    <input type="hidden" name="review_id" value="<?php echo (int)$review['review_id']; ?>">
                    <button class="manage-small-button is-danger" type="submit" name="action" value="delete">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
  <?php } else { ?>
    <p class="manage-empty">No reviews found.</p>
  <?php } ?>
</section>
<?php include "../includes/footer.php"; ?>
