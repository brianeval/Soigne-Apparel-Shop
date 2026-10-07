<?php
// Prints a grid of product cards.
// Expects $products (array). Optional: $empty_message.
$empty_message = $empty_message ?? 'No products found.';
?>
<?php if (!$products) { ?>
  <p class="dept-empty"><?php echo htmlspecialchars($empty_message); ?></p>
<?php } else { ?>
  <div class="dept-grid">
    <?php foreach ($products as $p) { ?>
      <a class="dept-card" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$p['product_id']; ?>">
        <div class="dept-img">
          <?php if (!empty($p['image'])) { ?>
            <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($p['image']); ?>"
                 alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
          <?php } ?>
        </div>
        <p class="dept-name"><?php echo htmlspecialchars($p['name']); ?></p>
        <p class="dept-meta">
          <?php echo htmlspecialchars($p['category'] ?? ''); ?>
          <?php if ((int)$p['color_count'] > 1) { ?> · <?php echo (int)$p['color_count']; ?> colors<?php } ?>
        </p>
        <p class="dept-price"><?php echo peso($p['price']); ?></p>
      </a>
    <?php } ?>
  </div>
<?php } ?>