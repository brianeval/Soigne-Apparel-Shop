<?php
session_start();
include_once('includes/config.php');

// Product details
$product_id = (int)($_GET['id'] ?? 0);

$product_sql = "SELECT p.*, c.name AS size_guide_category
                 FROM products p
                 LEFT JOIN categories c ON c.category_id = p.category_id
                 WHERE p.product_id = ?";
$product_result = mysqli_execute_query($conn, $product_sql, [$product_id]);
$product = mysqli_fetch_assoc($product_result);

if (!$product) {
    echo "Product not found.";
    exit;
}

// Reviews: first read every published review to get the count and the filter choices
$shared_sql = "FROM product_reviews r
     JOIN users u ON u.user_id = r.user_id
     LEFT JOIN order_items oi ON oi.order_item_id = r.order_item_id
     LEFT JOIN product_variants v ON v.variant_id = oi.variant_id
     LEFT JOIN product_colors pc ON pc.product_color_id = v.product_color_id";
$all_reviews_result = mysqli_query($conn,
    "SELECT pc.color AS purchased_color, v.size AS purchased_size
     $shared_sql
     WHERE r.product_id = $product_id AND r.status = 'published'"
);
$all_published_review_count = 0;
$review_colors = [];
$review_sizes = [];
while ($row = mysqli_fetch_assoc($all_reviews_result)) {
    $all_published_review_count++;
    if ($row['purchased_color'] != '' && !in_array($row['purchased_color'], $review_colors)) {
        $review_colors[] = $row['purchased_color'];
    }
    if ($row['purchased_size'] != '' && !in_array($row['purchased_size'], $review_sizes)) {
        $review_sizes[] = $row['purchased_size'];
    }
}
sort($review_colors, SORT_NATURAL | SORT_FLAG_CASE);
sort($review_sizes, SORT_NATURAL | SORT_FLAG_CASE);

// Review filters chosen by the visitor
$review_order_sql = [
    'newest' => 'r.created_at DESC, r.review_id DESC',
    'oldest' => 'r.created_at ASC, r.review_id ASC',
    'highest-rated' => 'r.rating DESC, r.created_at DESC',
    'lowest-rated' => 'r.rating ASC, r.created_at DESC',
];
$review_order = $_GET['review_order'] ?? 'newest';
if (!isset($review_order_sql[$review_order])) {
    $review_order = 'newest';
}
$review_rating = $_GET['review_rating'] ?? '';
if (!in_array($review_rating, ['1', '2', '3', '4', '5'], true)) {
    $review_rating = '';
}
$review_color = $_GET['review_color'] ?? '';
if (!in_array($review_color, $review_colors)) {
    $review_color = '';
}
$review_size = $_GET['review_size'] ?? '';
if (!in_array($review_size, $review_sizes)) {
    $review_size = '';
}

// Published reviews that match the filters
$reviews_sql = "SELECT r.review_id, r.user_id, u.username, r.rating, r.review_text, r.created_at,
                       pc.color AS purchased_color, v.size AS purchased_size
                $shared_sql
                WHERE r.product_id = $product_id AND r.status = 'published'";
if ($review_rating != '') {
    $reviews_sql .= " AND r.rating = " . (int)$review_rating;
}
if ($review_color != '') {
    $reviews_sql .= " AND pc.color = '" . mysqli_real_escape_string($conn, $review_color) . "'";
}
if ($review_size != '') {
    $reviews_sql .= " AND v.size = '" . mysqli_real_escape_string($conn, $review_size) . "'";
}
$reviews_sql .= " ORDER BY " . $review_order_sql[$review_order];
$published_reviews = mysqli_fetch_all(mysqli_query($conn, $reviews_sql), MYSQLI_ASSOC);

// An administrator can also open one pending review from the review list
$admin_pending_review = null;
if (($_SESSION['role'] ?? '') == 'admin' && isset($_GET['review_id'])) {
    $admin_review_id = (int)$_GET['review_id'];
    $pending_review_result = mysqli_query($conn,
        "SELECT r.review_id, r.user_id, u.username, r.rating, r.review_text, r.created_at,
                pc.color AS purchased_color, v.size AS purchased_size
         $shared_sql
         WHERE r.review_id = $admin_review_id AND r.product_id = $product_id AND r.status = 'pending'"
    );
    $admin_pending_review = mysqli_fetch_assoc($pending_review_result);
}

// Size guide link: pants, jeans, chinos and shorts use the bottom sizes
$size_guide_department = strtolower($product['department'] ?? 'men');
if (!in_array($size_guide_department, ['men', 'women', 'kids'])) {
    $size_guide_department = 'men';
}
$category_slug = strtolower($product['size_guide_category'] ?? '');
$size_guide_type = in_array($category_slug, ['pants', 'jeans', 'chinos', 'shorts']) ? 'bottom' : 'top';

// Cart item being edited (when the customer clicked "Update" in the cart)
$edit_cart_id = (int)($_GET['cart_id'] ?? 0);
$editing_cart_item = null;
if ($edit_cart_id > 0 && isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $cart_item_result = mysqli_execute_query($conn,
        "SELECT c.cart_id, c.quantity, v.variant_id, pc.product_color_id
         FROM cart c
         JOIN product_variants v ON v.variant_id = c.variant_id
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         WHERE c.cart_id = ? AND c.user_id = ? AND pc.product_id = ?",
        [$edit_cart_id, $user_id, $product_id]
    );
    $editing_cart_item = mysqli_fetch_assoc($cart_item_result);
    if (!$editing_cart_item) {
        header('Location: ' . BASE_URL . 'cart/cart.php');
        exit;
    }
}

// Colors and their pictures
$colors_result = mysqli_execute_query($conn, "SELECT * FROM product_colors WHERE product_id = ?", [$product_id]);
$colors = mysqli_fetch_all($colors_result, MYSQLI_ASSOC);

$image_color_ids = array_column($colors, 'product_color_id');
include('includes/color_images.php');

// Selected color: the one in the address, else the color of the cart item, else the first one
$selected = $colors[0] ?? null;
$wanted_color_id = (int)($_GET['color'] ?? 0);
if ($wanted_color_id == 0 && $editing_cart_item) {
    $wanted_color_id = (int)$editing_cart_item['product_color_id'];
}
foreach ($colors as $c) {
    if ($c['product_color_id'] == $wanted_color_id) {
        $selected = $c;
    }
}
$selected_images = $selected ? ($images_by_color[$selected['product_color_id']] ?? []) : [];

// Sizes of the selected color
$variants = [];
if ($selected) {
    $variants_result = mysqli_execute_query($conn, "SELECT * FROM product_variants WHERE product_color_id = ?", [$selected['product_color_id']]);
    $variants = mysqli_fetch_all($variants_result, MYSQLI_ASSOC);
}

// When editing a cart item, pre-select its size and quantity
$selected_variant_id = 0;
$selected_quantity = 1;
if ($editing_cart_item) {
    $selected_variant_id = (int)$editing_cart_item['variant_id'];
    $selected_quantity = (int)$editing_cart_item['quantity'];
}

// Page header
$page_title = $product['name'] . " | Soigné";
$extra_css = "product.css";
include_once('includes/header.php');
?>

<div class="product-page">

  <div class="product-gallery<?php echo count($selected_images) > 1 ? ' has-thumbnails' : ''; ?>">
    <?php if (count($selected_images) > 1) { ?>
      <div class="product-thumbnails" role="group" aria-label="<?php echo htmlspecialchars($selected['color'] ?? 'Product'); ?> product images">
        <?php foreach ($selected_images as $image_index => $image_path) { ?>
          <a class="product-thumbnail<?php echo $image_index === 0 ? ' is-active' : ''; ?>"
             href="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path); ?>"
             target="_blank" rel="noopener"
             aria-label="Open image <?php echo $image_index + 1; ?> in a new tab">
            <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path); ?>"
                 alt="" loading="lazy">
          </a>
        <?php } ?>
      </div>
    <?php } ?>
    <div class="product-photo">
      <?php if ($selected_images) { ?>
        <?php $main_image = $selected_images[0]; ?>
        <a class="product-main-image"
           href="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($main_image); ?>"
           target="_blank" rel="noopener" aria-label="Open full-size product image in a new tab">
          <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($main_image); ?>"
               alt="<?php echo htmlspecialchars($product['name'] . ' - ' . ($selected['color'] ?? '')); ?>">
        </a>
      <?php } ?>
    </div>
  </div>

  <div class="product-info">
    <h1><?php echo ($product['name']); ?></h1>
    <p class="price"><?php if (($_SESSION['role'] ?? '') == 'vip') { ?><span class="premium-product-price"><del>₱<?php echo number_format($product['price'], 2); ?></del> <strong>₱<?php echo number_format($product['price'] * 0.90, 2); ?></strong><small>VIP 10% off</small></span><?php } else { echo '₱' . number_format($product['price'], 2); } ?></p>
    <p class="desc"><?php echo ($product['description']); ?></p>
    <?php include("includes/alert.php"); ?>

    <!-- Color choices -->
    <h3>Color: <?php echo htmlspecialchars($selected['color'] ?? ''); ?></h3>
    <div class="color-options">
      <?php foreach ($colors as $c) { ?>
        <?php
        $class = "";
        if ($selected && $c['product_color_id'] == $selected['product_color_id']) {
            $class = "active";
        }
        ?>
        <a class="<?php echo $class; ?>"
           href="product.php?id=<?php echo $product_id; ?>&color=<?php echo (int)$c['product_color_id']; ?><?php echo $editing_cart_item ? '&cart_id=' . (int)$edit_cart_id : ''; ?>">
          <?php echo htmlspecialchars($c['color']); ?>
        </a>
      <?php } ?>
    </div>

    <!-- Sizes and cart action -->
    <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
      <p class="product-flash">Administrators can browse products but cannot add them to the cart.</p>
    <?php } else { ?>
    <form method="post" action="<?php echo $editing_cart_item ? 'cart/cart_update.php' : 'cart/cart_add.php'; ?>" novalidate>
      <?php if ($editing_cart_item) { ?>
        <input type="hidden" name="cart_id" value="<?php echo (int)$edit_cart_id; ?>">
      <?php } ?>
      <div class="size-heading">
        <h3>Size</h3>
        <a class="size-guide-link" href="<?php echo BASE_URL; ?>size_guide.php?dept=<?php echo rawurlencode($size_guide_department); ?>&type=<?php echo rawurlencode($size_guide_type); ?>#<?php echo rawurlencode($size_guide_department); ?>-size-guide">Size guide</a>
      </div>
      <div class="size-options">
        <?php foreach ($variants as $v) { ?>
          <?php if ($v['stock'] > 0) { ?>
            <label>
              <input type="radio" name="variant_id" value="<?php echo (int)$v['variant_id']; ?>" <?php echo ((int)$v['variant_id'] === $selected_variant_id) ? 'checked' : ''; ?>>
              <?php echo htmlspecialchars($v['size']); ?>
            </label>
          <?php } else { ?>
            <label>
              <input type="radio" name="variant_id" value="<?php echo (int)$v['variant_id']; ?>" <?php echo ((int)$v['variant_id'] === $selected_variant_id) ? 'checked' : ''; ?> disabled>
              <?php echo htmlspecialchars($v['size']); ?> (sold out)
            </label>
          <?php } ?>
        <?php } ?>
      </div>

      <p class="qty">Quantity: <input type="number" id="product-quantity" name="quantity" value="<?php echo $selected_quantity; ?>"></p>
      <?php if ($editing_cart_item) { ?>
        <div class="product-actions">
          <button class="btn" type="submit">Update item</button>
          <a class="btn btn-outline product-cancel" href="<?php echo BASE_URL; ?>cart/cart.php">Cancel</a>
        </div>
      <?php } else { ?>
        <button class="btn" type="submit">Add to cart</button>
      <?php } ?>
    </form>
    <?php } ?>
  </div>

</div>

<section class="reviews-section" id="reviews">
  <div class="reviews-heading">
    <div>
      <p class="reviews-eyebrow">CUSTOMER FEEDBACK</p>
      <h2>REVIEWS</h2>
      <p id="review-count"><?php echo count($published_reviews); ?> published <?php echo count($published_reviews) === 1 ? 'review' : 'reviews'; ?></p>
    </div>
    <?php if ($all_published_review_count > 0) { ?>
      <form class="review-filters" method="get" action="<?php echo BASE_URL; ?>product.php#reviews" aria-label="Filter reviews">
        <input type="hidden" name="id" value="<?php echo $product_id; ?>">
        <label>
          <span>Order by</span>
          <select name="review_order">
            <option value="newest" <?php echo $review_order === 'newest' ? 'selected' : ''; ?>>Newest</option>
            <option value="oldest" <?php echo $review_order === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
            <option value="highest-rated" <?php echo $review_order === 'highest-rated' ? 'selected' : ''; ?>>Highest rated</option>
            <option value="lowest-rated" <?php echo $review_order === 'lowest-rated' ? 'selected' : ''; ?>>Lowest rated</option>
          </select>
        </label>
        <label>
          <span>Stars</span>
          <select name="review_rating">
            <option value="">All ratings</option>
            <?php for ($rating_filter = 5; $rating_filter >= 1; $rating_filter--) { ?>
              <option value="<?php echo $rating_filter; ?>" <?php echo $review_rating === (string)$rating_filter ? 'selected' : ''; ?> aria-label="<?php echo $rating_filter; ?> <?php echo $rating_filter === 1 ? 'star' : 'stars'; ?>"><?php for ($star = 1; $star <= 5; $star++) { ?><?php echo $star <= $rating_filter ? '&#9733;' : '&#9734;'; ?><?php } ?></option>
            <?php } ?>
          </select>
        </label>
        <?php if ($review_colors) { ?>
          <label>
            <span>Color</span>
            <select name="review_color">
              <option value="">All colors</option>
              <?php foreach ($review_colors as $color) { ?>
                <option value="<?php echo htmlspecialchars($color); ?>" <?php echo $review_color === $color ? 'selected' : ''; ?>><?php echo htmlspecialchars($color); ?></option>
              <?php } ?>
            </select>
          </label>
        <?php } ?>
        <?php if ($review_sizes) { ?>
          <label>
            <span>Size</span>
            <select name="review_size">
              <option value="">All sizes</option>
              <?php foreach ($review_sizes as $size) { ?>
                <option value="<?php echo htmlspecialchars($size); ?>" <?php echo $review_size === $size ? 'selected' : ''; ?>><?php echo htmlspecialchars($size); ?></option>
              <?php } ?>
            </select>
          </label>
        <?php } ?>
        <button class="btn" type="submit">Apply filters</button>
      </form>
    <?php } ?>
  </div>

  <?php if ($published_reviews || $admin_pending_review) { ?>
    <div class="review-cards">
      <?php foreach ($published_reviews as $review) { ?>
        <article class="review-card" id="review-<?php echo (int)$review['review_id']; ?>">
          <div class="review-card-top">
            <?php $image_user_id = $review['user_id']; include('includes/profile_image.php'); ?>
            <img src="<?php echo $profile_image_url; ?>" alt="" loading="lazy">
            <span class="review-rating" aria-label="<?php echo (int)$review['rating']; ?> out of 5 stars">
              <?php for ($star = 1; $star <= 5; $star++) { ?><?php echo $star <= (int)$review['rating'] ? '&#9733;' : '&#9734;'; ?><?php } ?>
            </span>
            <?php if ($review['purchased_color'] !== null && $review['purchased_size'] !== null) { ?>
              <span class="review-variant"><?php echo htmlspecialchars($review['purchased_color']); ?> | <?php echo htmlspecialchars($review['purchased_size']); ?></span>
            <?php } ?>
          </div>
          <p class="review-copy"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
          <p class="review-author"><?php echo htmlspecialchars($review['username']); ?></p>
          <p class="review-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($review['created_at']))); ?></p>
        </article>
      <?php } ?>
      <?php if ($admin_pending_review) { ?>
        <article class="review-card" id="review-<?php echo (int)$admin_pending_review['review_id']; ?>">
          <div class="review-card-top">
            <?php $image_user_id = $admin_pending_review['user_id']; include('includes/profile_image.php'); ?>
            <img src="<?php echo $profile_image_url; ?>" alt="" loading="lazy">
            <span class="review-rating" aria-label="<?php echo (int)$admin_pending_review['rating']; ?> out of 5 stars">
              <?php for ($star = 1; $star <= 5; $star++) { ?><?php echo $star <= (int)$admin_pending_review['rating'] ? '&#9733;' : '&#9734;'; ?><?php } ?>
            </span>
            <span class="review-variant">Pending review</span>
            <?php if ($admin_pending_review['purchased_color'] !== null && $admin_pending_review['purchased_size'] !== null) { ?>
              <span class="review-variant"><?php echo htmlspecialchars($admin_pending_review['purchased_color']); ?> | <?php echo htmlspecialchars($admin_pending_review['purchased_size']); ?></span>
            <?php } ?>
          </div>
          <p class="review-copy"><?php echo nl2br(htmlspecialchars($admin_pending_review['review_text'])); ?></p>
          <p class="review-author"><?php echo htmlspecialchars($admin_pending_review['username']); ?></p>
          <p class="review-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($admin_pending_review['created_at']))); ?></p>
        </article>
      <?php } ?>
    </div>
  <?php } elseif ($all_published_review_count > 0) { ?>
    <p class="reviews-empty">No reviews match those filters.</p>
  <?php } else { ?>
    <p class="reviews-empty">No reviews yet.</p>
  <?php } ?>
</section>

<?php include_once('includes/footer.php'); ?>