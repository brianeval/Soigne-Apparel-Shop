<?php
session_start();
include_once('includes/config.php');
require_once('includes/product_images.php');

$product_id = (int)($_GET['id'] ?? 0);

$product = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT p.*, c.name AS size_guide_category
     FROM products p
     LEFT JOIN categories c ON c.category_id = p.category_id
     WHERE p.product_id = $product_id"));

if (!$product) {
    echo 'Product not found.';
    exit;
}

$reviews = mysqli_fetch_all(mysqli_query($conn,
    "SELECT r.review_id, r.user_id, u.username, r.rating, r.review_text, r.created_at,
            pc.color AS purchased_color, v.size AS purchased_size
     FROM product_reviews r
     JOIN users u ON u.user_id = r.user_id
     LEFT JOIN order_items oi ON oi.order_item_id = r.order_item_id
     LEFT JOIN product_variants v ON v.variant_id = oi.variant_id
     LEFT JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     WHERE r.product_id = $product_id AND r.status = 'published'
     ORDER BY r.created_at DESC, r.review_id DESC"), MYSQLI_ASSOC);

$pending_review = null;
if (($_SESSION['role'] ?? '') === 'admin' && !empty($_GET['review_id'])) {
    $review_id = (int)$_GET['review_id'];
    $pending_review = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT r.review_id, r.user_id, u.username, r.rating, r.review_text, r.created_at,
                pc.color AS purchased_color, v.size AS purchased_size
         FROM product_reviews r
         JOIN users u ON u.user_id = r.user_id
         LEFT JOIN order_items oi ON oi.order_item_id = r.order_item_id
         LEFT JOIN product_variants v ON v.variant_id = oi.variant_id
         LEFT JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         WHERE r.review_id = $review_id AND r.product_id = $product_id AND r.status = 'pending'"));
}

$product_size_guide = require('includes/product_size_guide.php');
$size_guide_department = strtolower(trim($product['department'] ?? 'men'));
if (!in_array($size_guide_department, ['men', 'women', 'kids'], true)) {
    $size_guide_department = 'men';
}
$category_slug = strtolower(trim($product['size_guide_category'] ?? ''));
$size_guide_type = $product_size_guide['category_groups'][$category_slug] ?? 'top';

$edit_cart_id = (int)($_GET['cart_id'] ?? 0);
$editing_cart_item = null;
if ($edit_cart_id > 0 && isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $editing_cart_item = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT c.cart_id, c.quantity, v.variant_id, pc.product_color_id
         FROM cart c
         JOIN product_variants v ON v.variant_id = c.variant_id
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         WHERE c.cart_id = $edit_cart_id AND c.user_id = $user_id AND pc.product_id = $product_id"));
    if (!$editing_cart_item) {
        header('Location: ' . BASE_URL . 'cart/cart.php');
        exit;
    }
}

$colors = mysqli_fetch_all(mysqli_query($conn,
    "SELECT * FROM product_colors WHERE product_id = $product_id"), MYSQLI_ASSOC);
$color_image_map = product_images($conn, array_column($colors, 'product_color_id'));

$selected = $colors[0] ?? null;
if (!empty($_GET['color'])) {
    foreach ($colors as $c) {
        if ((int)$c['product_color_id'] === (int)$_GET['color']) {
            $selected = $c;
        }
    }
}
if ($editing_cart_item) {
    foreach ($colors as $c) {
        if ((int)$c['product_color_id'] === (int)$editing_cart_item['product_color_id']) {
            $selected = $c;
        }
    }
}

$selected_images = $selected ? ($color_image_map[(int)$selected['product_color_id']] ?? []) : [];

$variants = [];
if ($selected) {
    $color_id = (int)$selected['product_color_id'];
    $variants = mysqli_fetch_all(mysqli_query($conn,
        "SELECT * FROM product_variants WHERE product_color_id = $color_id"), MYSQLI_ASSOC);
}

$original_variant_id = $editing_cart_item ? (int)$editing_cart_item['variant_id'] : 0;
$selected_variant_id = 0;
$selected_variant_stock = 0;
$selected_quantity = $editing_cart_item ? (int)$editing_cart_item['quantity'] : 1;
foreach ($variants as $variant) {
    if ((int)$variant['variant_id'] === $original_variant_id) {
        $selected_variant_id = $original_variant_id;
        $selected_variant_stock = (int)$variant['stock'];
        break;
    }
}
if ($selected_variant_stock < 1) {
    foreach ($variants as $variant) {
        $selected_variant_stock = max($selected_variant_stock, (int)$variant['stock']);
    }
}

$product_flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$page_title = $product['name'] . ' | Soigné';
$extra_css = 'product.css';
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
        <a class="product-main-image"
           href="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($selected_images[0], ENT_QUOTES, 'UTF-8'); ?>"
           target="_blank" rel="noopener" aria-label="Open full-size product image in a new tab">
          <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($selected_images[0], ENT_QUOTES, 'UTF-8'); ?>"
               alt="<?php echo htmlspecialchars($product['name'] . ' - ' . ($selected['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </a>
      <?php } ?>
    </div>
  </div>

  <div class="product-info">
    <h1><?php echo $product['name']; ?></h1>
    <p class="price"><?php echo price_html((float)$product['price']); ?></p>
    <p class="desc"><?php echo $product['description']; ?></p>
    <?php if ($product_flash) { ?>
      <p class="product-flash <?php echo $product_flash['type'] === 'error' ? 'is-error' : 'is-success'; ?>" role="status">
        <?php echo htmlspecialchars($product_flash['text']); ?>
      </p>
    <?php } ?>

    <h3>Color: <?php echo htmlspecialchars($selected['color'] ?? ''); ?></h3>
    <div class="color-options">
      <?php foreach ($colors as $c) { ?>
        <a class="<?php echo ($selected && $c['product_color_id'] == $selected['product_color_id']) ? 'active' : ''; ?>"
           href="product.php?id=<?php echo $product_id; ?>&color=<?php echo (int)$c['product_color_id']; ?><?php echo $editing_cart_item ? '&cart_id=' . (int)$edit_cart_id : ''; ?>">
          <?php echo htmlspecialchars($c['color']); ?>
        </a>
      <?php } ?>
    </div>

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
          <label>
            <input type="radio" name="variant_id" value="<?php echo (int)$v['variant_id']; ?>" data-stock="<?php echo (int)$v['stock']; ?>" <?php echo ((int)$v['variant_id'] === $selected_variant_id) ? 'checked' : ''; ?> <?php echo $v['stock'] > 0 ? '' : 'disabled'; ?>>
            <?php echo htmlspecialchars($v['size']); ?><?php echo $v['stock'] > 0 ? '' : ' (sold out)'; ?>
          </label>
        <?php } ?>
      </div>

      <p class="qty">Quantity: <input type="number" id="product-quantity" name="quantity" min="1" max="<?php echo max(1, $selected_variant_stock); ?>" value="<?php echo $selected_quantity; ?>"></p>
      <?php if ($editing_cart_item) { ?>
        <div class="product-actions">
          <button class="btn" type="submit">Update item</button>
          <a class="btn btn-outline product-cancel" href="<?php echo BASE_URL; ?>cart/cart.php">Cancel</a>
        </div>
      <?php } else { ?>
        <button class="btn" type="submit">Add to cart</button>
      <?php } ?>
    </form>
    <script>
      (function () {
        const form = document.querySelector('.product-info form');
        const quantityInput = document.getElementById('product-quantity');
        if (!form || !quantityInput) {
          return;
        }
        const sizeInputs = form.querySelectorAll('input[name="variant_id"]');
        function updateQuantityLimit() {
          let limit = 0;
          sizeInputs.forEach(function (input) {
            if (input.checked) {
              limit = parseInt(input.dataset.stock || '0', 10);
            }
          });
          if (!limit) {
            sizeInputs.forEach(function (input) {
              limit = Math.max(limit, parseInt(input.dataset.stock || '0', 10));
            });
          }
          limit = Math.max(1, limit);
          quantityInput.max = limit;
          if (parseInt(quantityInput.value || '1', 10) > limit) {
            quantityInput.value = limit;
          }
        }
        sizeInputs.forEach(function (input) {
          input.addEventListener('change', updateQuantityLimit);
        });
        updateQuantityLimit();
      })();
    </script>
    <?php } ?>
  </div>

</div>

<section class="reviews-section" id="reviews">
  <div class="reviews-heading">
    <div>
      <p class="reviews-eyebrow">CUSTOMER FEEDBACK</p>
      <h2>REVIEWS</h2>
      <p id="review-count"><?php echo count($reviews); ?> published <?php echo count($reviews) === 1 ? 'review' : 'reviews'; ?></p>
    </div>
  </div>

  <?php if ($reviews || $pending_review) { ?>
    <div class="review-cards">
      <?php
      $all_reviews = $reviews;
      if ($pending_review) {
          $all_reviews[] = $pending_review;
      }
      foreach ($all_reviews as $review) {
          $review_avatar = BASE_URL . 'images/default-profile.jpg';
          foreach (glob(APP_ROOT . '/images/profiles/profile-' . (int)$review['user_id'] . '-*') as $file) {
              $review_avatar = BASE_URL . 'images/profiles/' . basename($file);
          }
          ?>
        <article class="review-card" id="review-<?php echo (int)$review['review_id']; ?>">
          <div class="review-card-top">
            <img src="<?php echo htmlspecialchars($review_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
            <span class="review-rating" aria-label="<?php echo (int)$review['rating']; ?> out of 5 stars">
              <?php for ($star = 1; $star <= 5; $star++) { ?><?php echo $star <= (int)$review['rating'] ? '&#9733;' : '&#9734;'; ?><?php } ?>
            </span>
            <?php if (!$pending_review || $review !== $pending_review) { ?>
              <?php if ($review['purchased_color'] !== null && $review['purchased_size'] !== null) { ?>
                <span class="review-variant"><?php echo htmlspecialchars($review['purchased_color'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars($review['purchased_size'], ENT_QUOTES, 'UTF-8'); ?></span>
              <?php } ?>
            <?php } else { ?>
              <span class="review-variant">Pending review</span>
            <?php } ?>
          </div>
          <p class="review-copy"><?php echo nl2br(htmlspecialchars($review['review_text'], ENT_QUOTES, 'UTF-8')); ?></p>
          <p class="review-author"><?php echo htmlspecialchars($review['username'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="review-date"><?php echo htmlspecialchars(date('M j, Y', strtotime($review['created_at'])), ENT_QUOTES, 'UTF-8'); ?></p>
        </article>
      <?php } ?>
    </div>
  <?php } else { ?>
    <p class="reviews-empty">No reviews yet.</p>
  <?php } ?>
</section>

<?php include_once('includes/footer.php'); ?>
