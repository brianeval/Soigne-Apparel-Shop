<?php
session_start();
include_once('includes/config.php');
require_once("includes/product_images.php");


// Product details
$product_id = (int)($_GET['id'] ?? 0);

$product_sql = "SELECT p.*, c.name AS size_guide_category
                 FROM products p
                 LEFT JOIN categories c ON c.category_id = p.category_id
                 WHERE p.product_id = ?";
$product_stmt = mysqli_prepare($conn, $product_sql);
mysqli_stmt_bind_param($product_stmt, 'i', $product_id);
mysqli_stmt_execute($product_stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($product_stmt));

if (!$product) {
    echo "Product not found.";
    exit;
}

$reviews_stmt = mysqli_prepare($conn,
    "SELECT r.review_id, r.user_id, u.username, r.rating, r.review_text, r.status, r.created_at, r.updated_at,
            pc.color AS purchased_color, v.size AS purchased_size
     FROM product_reviews r
     JOIN users u ON u.user_id = r.user_id
     LEFT JOIN order_items oi ON oi.order_item_id = r.order_item_id
     LEFT JOIN product_variants v ON v.variant_id = oi.variant_id
     LEFT JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     WHERE r.product_id = ? AND r.status = 'published'
     ORDER BY r.created_at DESC, r.review_id DESC"
);
mysqli_stmt_bind_param($reviews_stmt, 'i', $product_id);
mysqli_stmt_execute($reviews_stmt);
$published_reviews = mysqli_fetch_all(mysqli_stmt_get_result($reviews_stmt), MYSQLI_ASSOC);
$admin_pending_review = null;
if (($_SESSION['role'] ?? '') === 'admin' && isset($_GET['review_id'])) {
    $admin_review_id = (int)$_GET['review_id'];
    if ($admin_review_id > 0) {
        $pending_review_stmt = mysqli_prepare($conn,
            "SELECT r.review_id, r.user_id, u.username, r.rating, r.review_text, r.created_at,
                    pc.color AS purchased_color, v.size AS purchased_size
             FROM product_reviews r
             JOIN users u ON u.user_id = r.user_id
             LEFT JOIN order_items oi ON oi.order_item_id = r.order_item_id
             LEFT JOIN product_variants v ON v.variant_id = oi.variant_id
             LEFT JOIN product_colors pc ON pc.product_color_id = v.product_color_id
             WHERE r.review_id = ? AND r.product_id = ? AND r.status = 'pending'"
        );
        mysqli_stmt_bind_param($pending_review_stmt, 'ii', $admin_review_id, $product_id);
        mysqli_stmt_execute($pending_review_stmt);
        $admin_pending_review = mysqli_fetch_assoc(mysqli_stmt_get_result($pending_review_stmt)) ?: null;
    }
}
$review_colors = [];
$review_sizes = [];
foreach ($published_reviews as $review) {
    if ($review['purchased_color'] !== null && $review['purchased_color'] !== '') {
        $review_colors[] = (string)$review['purchased_color'];
    }
    if ($review['purchased_size'] !== null && $review['purchased_size'] !== '') {
        $review_sizes[] = (string)$review['purchased_size'];
    }
}
$review_colors = array_values(array_unique($review_colors));
$review_sizes = array_values(array_unique($review_sizes));
sort($review_colors, SORT_NATURAL | SORT_FLAG_CASE);
sort($review_sizes, SORT_NATURAL | SORT_FLAG_CASE);
$review_order = $_GET['review_order'] ?? 'newest';
if (!in_array($review_order, ['newest', 'oldest', 'highest-rated', 'lowest-rated'], true)) {
    $review_order = 'newest';
}
$review_rating = filter_var($_GET['review_rating'] ?? '', FILTER_VALIDATE_INT);
$review_rating = $review_rating !== false && $review_rating >= 1 && $review_rating <= 5 ? (string)$review_rating : '';
$review_color = is_string($_GET['review_color'] ?? null) && in_array($_GET['review_color'], $review_colors, true)
    ? $_GET['review_color']
    : '';
$review_size = is_string($_GET['review_size'] ?? null) && in_array($_GET['review_size'], $review_sizes, true)
    ? $_GET['review_size']
    : '';
$all_published_review_count = count($published_reviews);
$filtered_reviews = [];
foreach ($published_reviews as $review) {
    if (($review_rating === '' || (string)$review['rating'] === $review_rating)
        && ($review_color === '' || $review['purchased_color'] === $review_color)
        && ($review_size === '' || $review['purchased_size'] === $review_size)) {
        $filtered_reviews[] = $review;
    }
}
$published_reviews = $filtered_reviews;
usort($published_reviews, static function (array $first, array $second) use ($review_order): int {
    $date_order = strcmp((string)$first['created_at'], (string)$second['created_at']);
    $rating_order = (int)$first['rating'] <=> (int)$second['rating'];
    if ($review_order === 'oldest') {
        return $date_order;
    }
    if ($review_order === 'highest-rated') {
        return $rating_order !== 0 ? -$rating_order : -$date_order;
    }
    if ($review_order === 'lowest-rated') {
        return $rating_order !== 0 ? $rating_order : -$date_order;
    }
    return -$date_order;
});

$product_size_guide = require("includes/product_size_guide.php");
$size_guide_department = strtolower(trim($product['department'] ?? 'men'));
if (!in_array($size_guide_department, ['men', 'women', 'kids'], true)) {
    $size_guide_department = 'men';
}
$category_slug = strtolower(trim($product['size_guide_category'] ?? ''));
$size_guide_type = $product_size_guide['category_groups'][$category_slug] ?? 'top';

$edit_cart_id = (int)($_GET['cart_id'] ?? 0);
$editing_cart_item = null;
if ($edit_cart_id > 0 && isset($_SESSION['user_id'])) {
    // Cart item lookup
    $cart_item_stmt = mysqli_prepare($conn,
        "SELECT c.cart_id, c.quantity, v.variant_id, pc.product_color_id
         FROM cart c
         JOIN product_variants v ON v.variant_id = c.variant_id
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         WHERE c.cart_id = ? AND c.user_id = ? AND pc.product_id = ?"
    );
    $user_id = (int)$_SESSION['user_id'];
    mysqli_stmt_bind_param($cart_item_stmt, 'iii', $edit_cart_id, $user_id, $product_id);
    mysqli_stmt_execute($cart_item_stmt);
    $editing_cart_item = mysqli_fetch_assoc(mysqli_stmt_get_result($cart_item_stmt));
    if (!$editing_cart_item) {
        header('Location: ' . BASE_URL . 'cart/cart.php');
        exit;
    }
}

$colors_sql = "SELECT * FROM product_colors WHERE product_id = ?";
$colors_stmt = mysqli_prepare($conn, $colors_sql);
mysqli_stmt_bind_param($colors_stmt, 'i', $product_id);
mysqli_stmt_execute($colors_stmt);
$colors = mysqli_fetch_all(mysqli_stmt_get_result($colors_stmt), MYSQLI_ASSOC);
$product_color_ids = [];
foreach ($colors as $color) {
    if (array_key_exists('product_color_id', $color)) {
        $product_color_ids[] = (int)$color['product_color_id'];
    }
}
$color_image_map = product_color_image_paths(
    $conn,
    $product_color_ids
);

$selected = null;
if (isset($_GET['color'])) {
    foreach ($colors as $c) {
        if ((int)$c['product_color_id'] === (int)$_GET['color']) {
            $selected = $c;
            break;
        }
    }
}

if (!$selected && $editing_cart_item) {
    foreach ($colors as $c) {
        if ((int)$c['product_color_id'] === (int)$editing_cart_item['product_color_id']) {
            $selected = $c;
            break;
        }
    }
}
$selected = $selected ?? ($colors[0] ?? null);
$selected_images = $selected
    ? ($color_image_map[(int)$selected['product_color_id']] ?? [])
    : [];

$variants = [];
if ($selected) {
    $variants_sql = "SELECT * FROM product_variants WHERE product_color_id = ?";
    $variants_stmt = mysqli_prepare($conn, $variants_sql);
    mysqli_stmt_bind_param($variants_stmt, 'i', $selected['product_color_id']);
    mysqli_stmt_execute($variants_stmt);
    $variants = mysqli_fetch_all(mysqli_stmt_get_result($variants_stmt), MYSQLI_ASSOC);
}

$original_variant_id = $editing_cart_item ? (int)$editing_cart_item['variant_id'] : 0;
$selected_variant_id = 0;
$selected_quantity = $editing_cart_item ? (int)$editing_cart_item['quantity'] : 1;
$selected_stock = 1;
foreach ($variants as $variant) {
    if ((int)$variant['variant_id'] === $original_variant_id) {
        $selected_variant_id = $original_variant_id;
        $selected_stock = (int)$variant['stock'];
        break;
    }
}
$product_flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

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
             href="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8'); ?>"
             target="_blank" rel="noopener"
             aria-label="Open image <?php echo $image_index + 1; ?> in a new tab">
            <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="" loading="lazy">
          </a>
        <?php } ?>
      </div>
    <?php } ?>
    <div class="product-photo">
      <?php if ($selected_images) { ?>
        <?php $main_image = $selected_images[0]; ?>
        <a class="product-main-image"
           href="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($main_image, ENT_QUOTES, 'UTF-8'); ?>"
           target="_blank" rel="noopener" aria-label="Open full-size product image in a new tab">
          <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($main_image, ENT_QUOTES, 'UTF-8'); ?>"
               alt="<?php echo htmlspecialchars($product['name'] . ' - ' . ($selected['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </a>
      <?php } ?>
    </div>
  </div>

  <div class="product-info">
    <h1><?php echo ($product['name']); ?></h1>
    <p class="price"><?php echo store_product_price_markup((float)$product['price']); ?></p>
    <p class="desc"><?php echo ($product['description']); ?></p>
    <?php if ($product_flash) { ?>
      <p class="product-flash <?php echo $product_flash['type'] === 'error' ? 'is-error' : 'is-success'; ?>" role="status">
        <?php echo htmlspecialchars($product_flash['text']); ?>
      </p>
    <?php } ?>

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
                <option value="<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $review_color === $color ? 'selected' : ''; ?>><?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?></option>
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
                <option value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $review_size === $size ? 'selected' : ''; ?>><?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?></option>
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
            <img src="<?php echo htmlspecialchars(profile_image_url((int)$review['user_id']), ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
            <span class="review-rating" aria-label="<?php echo (int)$review['rating']; ?> out of 5 stars">
              <?php for ($star = 1; $star <= 5; $star++) { ?><?php echo $star <= (int)$review['rating'] ? '&#9733;' : '&#9734;'; ?><?php } ?>
            </span>
            <?php if ($review['purchased_color'] !== null && $review['purchased_size'] !== null) { ?>
              <span class="review-variant"><?php echo htmlspecialchars($review['purchased_color'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($review['purchased_size'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php } ?>
          </div>
          <p class="review-copy"><?php echo nl2br(htmlspecialchars($review['review_text'], ENT_QUOTES, 'UTF-8')); ?></p>
          <p class="review-author"><?php echo htmlspecialchars($review['username'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="review-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($review['created_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
        </article>
      <?php } ?>
      <?php if ($admin_pending_review) { ?>
        <article class="review-card" id="review-<?php echo (int)$admin_pending_review['review_id']; ?>">
          <div class="review-card-top">
            <img src="<?php echo htmlspecialchars(profile_image_url((int)$admin_pending_review['user_id']), ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
            <span class="review-rating" aria-label="<?php echo (int)$admin_pending_review['rating']; ?> out of 5 stars">
              <?php for ($star = 1; $star <= 5; $star++) { ?><?php echo $star <= (int)$admin_pending_review['rating'] ? '&#9733;' : '&#9734;'; ?><?php } ?>
            </span>
            <span class="review-variant">Pending review</span>
            <?php if ($admin_pending_review['purchased_color'] !== null && $admin_pending_review['purchased_size'] !== null) { ?>
              <span class="review-variant"><?php echo htmlspecialchars($admin_pending_review['purchased_color'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($admin_pending_review['purchased_size'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php } ?>
          </div>
          <p class="review-copy"><?php echo nl2br(htmlspecialchars($admin_pending_review['review_text'], ENT_QUOTES, 'UTF-8')); ?></p>
          <p class="review-author"><?php echo htmlspecialchars($admin_pending_review['username'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="review-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($admin_pending_review['created_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
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