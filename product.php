<?php
session_start();
include_once('includes/config.php');
require_once("includes/product_images.php");


// STEP 1: Get the product id from the URL (product.php?id=4)
$id = (int)($_GET['id'] ?? 0);

// STEP 2: Find the product in the database.
// This query asks: "give me the row from products where product_id matches the URL id".
$productQuery = "SELECT p.*, c.name AS size_guide_category
                 FROM products p
                 LEFT JOIN categories c ON c.category_id = p.category_id
                 WHERE p.product_id = ?";
$stmt = mysqli_prepare($conn, $productQuery);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$product) {
    echo "Product not found.";
    exit;
}

$stmt = mysqli_prepare($conn,
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
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$published_reviews = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
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
    // This query checks the item the user is editing in the cart.
    // It joins the cart table to the variant and color tables so we can match the correct product.
    $stmt = mysqli_prepare($conn,
        "SELECT c.cart_id, c.quantity, v.variant_id, pc.product_color_id
         FROM cart c
         JOIN product_variants v ON v.variant_id = c.variant_id
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         WHERE c.cart_id = ? AND c.user_id = ? AND pc.product_id = ?"
    );
    $user_id = (int)$_SESSION['user_id'];
    mysqli_stmt_bind_param($stmt, 'iii', $edit_cart_id, $user_id, $id);
    mysqli_stmt_execute($stmt);
    $editing_cart_item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$editing_cart_item) {
        header('Location: ' . BASE_URL . 'cart/cart.php');
        exit;
    }
}

// STEP 3: Get all available colors for this product.
// This query asks: "list every color row that belongs to this product".
$colorQuery = "SELECT * FROM product_colors WHERE product_id = ?";
$stmt = mysqli_prepare($conn, $colorQuery);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$colors = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
$color_image_map = product_color_image_paths(
    $conn,
    array_map('intval', array_column($colors, 'product_color_id'))
);

// STEP 4: Decide which color to show (default is the first one)
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

// STEP 5: Get the sizes and stock of the selected color.
// This query asks: "show every size variant for this color, including how many are in stock".
$variants = [];
if ($selected) {
    $variantQuery = "SELECT * FROM product_variants WHERE product_color_id = ?";
    $stmt = mysqli_prepare($conn, $variantQuery);
    mysqli_stmt_bind_param($stmt, 'i', $selected['product_color_id']);
    mysqli_stmt_execute($stmt);
    $variants = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
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

// Page title, then the header
$page_title = $product['name'] . " | Soigné";
$extra_css = "product.css";
include_once('includes/header.php');
?>

<div class="product-page">

  <div class="product-gallery<?php echo count($selected_images) > 1 ? ' has-thumbnails' : ''; ?>">
    <?php if (count($selected_images) > 1) { ?>
      <div class="product-thumbnails" role="group" aria-label="<?php echo htmlspecialchars($selected['color'] ?? 'Product'); ?> product images">
        <?php foreach ($selected_images as $image_index => $image_path) { ?>
          <button class="product-thumbnail<?php echo $image_index === 0 ? ' is-active' : ''; ?>"
                  type="button"
                  data-product-thumbnail
                  data-image-src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8'); ?>"
                  data-image-alt="<?php echo htmlspecialchars($product['name'] . ' - ' . ($selected['color'] ?? '') . ' image ' . ($image_index + 1), ENT_QUOTES, 'UTF-8'); ?>"
                  aria-label="Show image <?php echo $image_index + 1; ?>"
                  aria-pressed="<?php echo $image_index === 0 ? 'true' : 'false'; ?>">
            <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8'); ?>"
                 alt="" loading="lazy">
          </button>
        <?php } ?>
      </div>
    <?php } ?>
    <div class="product-photo">
      <?php if ($selected_images) { ?>
        <?php $main_image = $selected_images[0]; ?>
        <button class="product-main-image" type="button"
                data-image-viewer
                data-image-src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($main_image, ENT_QUOTES, 'UTF-8'); ?>"
                data-image-alt="<?php echo htmlspecialchars($product['name'] . ' - ' . ($selected['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="Enlarge product image">
          <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($main_image, ENT_QUOTES, 'UTF-8'); ?>"
               alt="<?php echo htmlspecialchars($product['name'] . ' - ' . ($selected['color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </button>
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
           href="product.php?id=<?php echo $id; ?>&color=<?php echo (int)$c['product_color_id']; ?><?php echo $editing_cart_item ? '&cart_id=' . (int)$edit_cart_id : ''; ?>">
          <?php echo htmlspecialchars($c['color']); ?>
        </a>
      <?php } ?>
    </div>

    <!-- Sizes and cart action -->
    <form method="post" action="<?php echo $editing_cart_item ? 'cart/cart_update.php' : 'cart/cart_add.php'; ?>">
      <?php if ($editing_cart_item) { ?>
        <input type="hidden" name="cart_id" value="<?php echo (int)$edit_cart_id; ?>">
      <?php } ?>
      <div class="size-heading">
        <h3>Size</h3>
        <a class="size-guide-link" href="<?php echo BASE_URL; ?>size_guide.php?dept=<?php echo rawurlencode($size_guide_department); ?>&type=<?php echo rawurlencode($size_guide_type); ?>">Size guide</a>
      </div>
      <div class="size-options">
        <?php foreach ($variants as $v) { ?>
          <?php if ($v['stock'] > 0) { ?>
            <label>
              <input type="radio" name="variant_id" value="<?php echo (int)$v['variant_id']; ?>" data-stock="<?php echo (int)$v['stock']; ?>" <?php echo ((int)$v['variant_id'] === $selected_variant_id) ? 'checked' : ''; ?> required>
              <?php echo htmlspecialchars($v['size']); ?>
            </label>
          <?php } else { ?>
            <label>
              <input type="radio" name="variant_id" value="<?php echo (int)$v['variant_id']; ?>" data-stock="0" <?php echo ((int)$v['variant_id'] === $selected_variant_id) ? 'checked' : ''; ?> disabled>
              <?php echo htmlspecialchars($v['size']); ?> (sold out)
            </label>
          <?php } ?>
        <?php } ?>
      </div>

      <p class="qty">Quantity: <input type="number" id="product-quantity" name="quantity" value="<?php echo $selected_quantity; ?>" min="1" <?php echo $selected_variant_id ? 'max="' . max(1, $selected_stock) . '"' : ''; ?> required></p>
      <?php if ($editing_cart_item) { ?>
        <div class="product-actions">
          <button class="btn" type="submit">Update item</button>
          <a class="btn btn-outline product-cancel" href="<?php echo BASE_URL; ?>cart/cart.php">Cancel</a>
        </div>
      <?php } else { ?>
        <button class="btn" type="submit">Add to cart</button>
      <?php } ?>
    </form>
  </div>

</div>

<section class="reviews-section" id="reviews">
  <div class="reviews-heading">
    <div>
      <p class="reviews-eyebrow">CUSTOMER FEEDBACK</p>
      <h2>REVIEWS</h2>
      <p id="review-count"><?php echo count($published_reviews); ?> published <?php echo count($published_reviews) === 1 ? 'review' : 'reviews'; ?></p>
    </div>
    <?php if ($published_reviews) { ?>
      <div class="review-filters" aria-label="Filter reviews">
        <label>
          <span>Order by</span>
          <select id="review-order-filter">
            <option value="newest">Newest</option>
            <option value="oldest">Oldest</option>
            <option value="highest-rated">Highest rated</option>
            <option value="lowest-rated">Lowest rated</option>
          </select>
        </label>
        <label>
          <span>Stars</span>
          <select id="review-rating-filter">
            <option value="">All ratings</option>
            <?php for ($rating_filter = 5; $rating_filter >= 1; $rating_filter--) { ?>
              <option value="<?php echo $rating_filter; ?>" aria-label="<?php echo $rating_filter; ?> <?php echo $rating_filter === 1 ? 'star' : 'stars'; ?>"><?php for ($star = 1; $star <= 5; $star++) { ?><?php echo $star <= $rating_filter ? '&#9733;' : '&#9734;'; ?><?php } ?></option>
            <?php } ?>
          </select>
        </label>
        <?php if ($review_colors) { ?>
          <label>
            <span>Color</span>
            <select id="review-color-filter">
              <option value="">All colors</option>
              <?php foreach ($review_colors as $color) { ?>
                <option value="<?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($color, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php } ?>
            </select>
          </label>
        <?php } ?>
        <?php if ($review_sizes) { ?>
          <label>
            <span>Size</span>
            <select id="review-size-filter">
              <option value="">All sizes</option>
              <?php foreach ($review_sizes as $size) { ?>
                <option value="<?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($size, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php } ?>
            </select>
          </label>
        <?php } ?>
      </div>
    <?php } ?>
  </div>

  <?php if ($published_reviews) { ?>
    <div class="review-cards">
      <?php foreach ($published_reviews as $review) { ?>
        <article class="review-card"
                 data-review-color="<?php echo htmlspecialchars((string)($review['purchased_color'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                 data-review-size="<?php echo htmlspecialchars((string)($review['purchased_size'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                 data-review-rating="<?php echo (int)$review['rating']; ?>"
                 data-review-date="<?php echo (int)strtotime($review['created_at']); ?>">
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
    </div>
    <p class="reviews-empty" id="reviews-filter-empty" hidden>No reviews match those filters.</p>
  <?php } else { ?>
    <p class="reviews-empty">No reviews yet.</p>
  <?php } ?>
</section>

<?php if (!isset($_SESSION['user_id'])) { ?>
  <dialog id="login-modal" class="modal">
    <button type="button" class="modal-close" aria-label="Close">&times;</button>
    <h2>Sign in to continue</h2>
    <p>Please sign in or create an account to add items to your cart.</p>
    <div class="modal-actions">
      <a class="btn" href="users/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Sign in</a>
      <a class="btn btn-outline" href="users/register.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Create account</a>
    </div>
  </dialog>

  <script>
    const modal = document.getElementById('login-modal');
    const cartForm = document.querySelector('form[action="cart/cart_add.php"]');

    // The submit event only fires after the "pick a size" check passes
    if (cartForm) {
      cartForm.addEventListener('submit', function (e) {
        e.preventDefault();
        modal.showModal();
      });
    }

    modal.querySelector('.modal-close').addEventListener('click', () => modal.close());
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.close(); }); // click outside
  </script>
<?php } ?>
<script>
(function () {
  var reviewCards = Array.prototype.slice.call(document.querySelectorAll('.review-card'));
  var colorFilter = document.getElementById('review-color-filter');
  var sizeFilter = document.getElementById('review-size-filter');
  var ratingFilter = document.getElementById('review-rating-filter');
  var orderFilter = document.getElementById('review-order-filter');
  var reviewCardContainer = document.querySelector('.review-cards');
  var reviewCount = document.getElementById('review-count');
  var noMatchingReviews = document.getElementById('reviews-filter-empty');

  if (reviewCards.length && reviewCount && noMatchingReviews) {
    function filterReviews() {
      var selectedColor = colorFilter ? colorFilter.value : '';
      var selectedSize = sizeFilter ? sizeFilter.value : '';
      var selectedRating = ratingFilter ? ratingFilter.value : '';
      var orderBy = orderFilter ? orderFilter.value : 'newest';
      var visibleCount = 0;
      var matchingCards = [];

      reviewCards.forEach(function (card) {
        var matches = (!selectedColor || card.dataset.reviewColor === selectedColor)
          && (!selectedSize || card.dataset.reviewSize === selectedSize)
          && (!selectedRating || card.dataset.reviewRating === selectedRating);
        card.hidden = !matches;
        if (matches) {
          visibleCount++;
          matchingCards.push(card);
        }
      });

      matchingCards.sort(function (first, second) {
        if (orderBy === 'oldest') {
          return Number(first.dataset.reviewDate) - Number(second.dataset.reviewDate);
        }
        if (orderBy === 'highest-rated') {
          return Number(second.dataset.reviewRating) - Number(first.dataset.reviewRating)
            || Number(second.dataset.reviewDate) - Number(first.dataset.reviewDate);
        }
        if (orderBy === 'lowest-rated') {
          return Number(first.dataset.reviewRating) - Number(second.dataset.reviewRating)
            || Number(second.dataset.reviewDate) - Number(first.dataset.reviewDate);
        }
        return Number(second.dataset.reviewDate) - Number(first.dataset.reviewDate);
      });
      matchingCards.forEach(function (card) {
        reviewCardContainer.appendChild(card);
      });

      reviewCount.textContent = visibleCount + ' published ' + (visibleCount === 1 ? 'review' : 'reviews');
      noMatchingReviews.hidden = visibleCount > 0;
    }

    if (colorFilter) colorFilter.addEventListener('change', filterReviews);
    if (sizeFilter) sizeFilter.addEventListener('change', filterReviews);
    if (ratingFilter) ratingFilter.addEventListener('change', filterReviews);
    if (orderFilter) orderFilter.addEventListener('change', filterReviews);
  }

  var quantityInput = document.getElementById('product-quantity');
  var sizeOptions = document.querySelectorAll('.size-options input[name="variant_id"]');
  if (!quantityInput) return;

  sizeOptions.forEach(function (option) {
    option.addEventListener('change', function () {
      quantityInput.max = option.getAttribute('data-stock');
    });
  });
})();
</script>
<script>
(function () {
  var mainButton = document.querySelector('.product-main-image');
  var mainImage = mainButton ? mainButton.querySelector('img') : null;
  var thumbnails = document.querySelectorAll('[data-product-thumbnail]');
  if (!mainButton || !mainImage) return;

  thumbnails.forEach(function (thumbnail) {
    thumbnail.addEventListener('click', function () {
      var source = thumbnail.dataset.imageSrc;
      var alt = thumbnail.dataset.imageAlt;
      mainImage.src = source;
      mainImage.alt = alt;
      mainButton.dataset.imageSrc = source;
      mainButton.dataset.imageAlt = alt;
      thumbnails.forEach(function (item) {
        var isActive = item === thumbnail;
        item.classList.toggle('is-active', isActive);
        item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
    });
  });
})();
</script>
<?php include_once('includes/footer.php'); ?>