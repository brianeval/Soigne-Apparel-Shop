<?php
$empty_message = $empty_message ?? 'No products found.';

if (empty($products)) { ?>
  <p class="dept-empty"><?php echo htmlspecialchars($empty_message); ?></p>
<?php } else {
    $image_map = product_images($conn, array_column($products, 'first_color_id')); ?>
  <div class="dept-grid">
    <?php foreach ($products as $product) {
        $card_images = $image_map[(int)($product['first_color_id'] ?? 0)] ?? []; ?>
      <article class="dept-card">
        <div class="dept-img">
          <?php if ($card_images) { ?>
            <a class="product-image-tile" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$product['product_id']; ?>">
              <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($card_images[0]); ?>"
                   alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
            </a>
          <?php } ?>
        </div>
        <a class="dept-name" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$product['product_id']; ?>"><?php echo htmlspecialchars($product['name']); ?></a>
        <p class="dept-meta">
          <?php echo htmlspecialchars($product['category'] ?? ''); ?>
          <?php if ((int)$product['color_count'] > 1) { ?> · <?php echo (int)$product['color_count']; ?> colors<?php } ?>
        </p>
        <p class="dept-price"><?php echo price_html((float)$product['price']); ?></p>
      </article>
    <?php } ?>
  </div>
<?php } ?>
