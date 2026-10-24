<?php
// Prints a grid of product cards.
// Expects $products (array). Optional: $empty_message.
require_once __DIR__ . '/product_images.php';
$empty_message = $empty_message ?? 'No products found.';
?>
<?php if (!$products) { ?>
  <p class="dept-empty"><?php echo htmlspecialchars($empty_message); ?></p>
<?php } else { ?>
  <?php
  $color_ids = array_map('intval', array_column($products, 'first_color_id'));
  $product_image_map = product_color_image_paths($conn, $color_ids);
  ?>
  <div class="dept-grid">
    <?php foreach ($products as $p) { ?>
      <?php $card_images = $product_image_map[(int)($p['first_color_id'] ?? 0)] ?? []; ?>
      <article class="dept-card">
        <div class="dept-img">
          <?php if ($card_images) { $image_path = $card_images[0]; ?>
            <a class="product-image-tile" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$p['product_id']; ?>"
               aria-label="View <?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">
              <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8'); ?>"
                   alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
            </a>
          <?php } ?>
        </div>
        <a class="dept-name" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$p['product_id']; ?>"><?php echo htmlspecialchars($p['name']); ?></a>
        <p class="dept-meta">
          <?php echo htmlspecialchars($p['category'] ?? ''); ?>
          <?php if ((int)$p['color_count'] > 1) { ?> · <?php echo (int)$p['color_count']; ?> colors<?php } ?>
        </p>
        <p class="dept-price"><?php echo store_product_price_markup((float)$p['price']); ?></p>
      </article>
    <?php } ?>
  </div>
<?php } ?>