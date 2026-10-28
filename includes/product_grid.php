<?php
// Product grid
require_once("product_images.php");
$empty_message = $empty_message ?? 'No products found.';
?>
<?php if (empty($products)) { ?>
  <p class="dept-empty"><?php echo htmlspecialchars($empty_message); ?></p>
<?php } else { ?>
  <?php
  $product_color_ids = array_map('intval', array_column($products, 'first_color_id'));
  $product_images_by_color = product_color_image_paths($conn, $product_color_ids);
  ?>
  <div class="dept-grid">
    <?php foreach ($products as $product) { ?>
      <?php $card_images = $product_images_by_color[(int)($product['first_color_id'] ?? 0)] ?? []; ?>
      <article class="dept-card">
        <div class="dept-img">
          <?php if ($card_images) { ?>
            <?php $image_path = $card_images[0]; ?>
            <a class="product-image-tile" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$product['product_id']; ?>"
               aria-label="View <?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
              <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8'); ?>"
                   alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
            </a>
          <?php } ?>
        </div>
        <a class="dept-name" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a>
        <p class="dept-meta">
          <?php echo htmlspecialchars($product['category'] ?? ''); ?>
          <?php if ((int)$product['color_count'] > 1) { ?> · <?php echo (int)$product['color_count']; ?> colors<?php } ?>
        </p>
        <p class="dept-price"><?php echo store_product_price_markup((float)$product['price']); ?></p>
      </article>
    <?php } ?>
  </div>
<?php } ?>