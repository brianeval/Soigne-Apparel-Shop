<?php
    session_start();
    include_once("includes/config.php");

    $products_sql = "SELECT p.*, pc.product_color_id AS first_color_id
      FROM products p
      LEFT JOIN product_colors pc
        ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
      ORDER BY p.product_id DESC";
    $products = mysqli_fetch_all(mysqli_query($conn, $products_sql), MYSQLI_ASSOC);

    // Pictures for the product cards
    $image_color_ids = array_column($products, 'first_color_id');
    include("includes/color_images.php");

    include_once("includes/header.php");
?>
    <section class="section">
        <div class="section-head">
            <h2>All Products</h2>
        </div>
        <div class="product-row">
            <?php foreach ($products as $p) {
            $card_images = $images_by_color[(int)($p['first_color_id'] ?? 0)] ?? [];
            ?>

            <article class="product">
                <div class="thumb">
                <?php if ($card_images) { $image_path = $card_images[0]; ?>
                    <a class="product-image-tile" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$p['product_id']; ?>"
                       aria-label="View <?php echo htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8'); ?>">
                      <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($image_path, ENT_QUOTES, 'UTF-8'); ?>"
                           alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
                    </a>
                <?php } ?>
                </div>
                <a class="product-card-link" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$p['product_id']; ?>">
                  <p class="name"><?php echo htmlspecialchars($p['name']); ?></p>
                  <p class="price"><?php if (($_SESSION['role'] ?? '') == 'vip') { ?><span class="premium-product-price"><del>₱<?php echo number_format($p['price'], 2); ?></del> <strong>₱<?php echo number_format($p['price'] * 0.90, 2); ?></strong><small>VIP 10% off</small></span><?php } else { echo '₱' . number_format($p['price'], 2); } ?></p>
                </a>
            </article>

            <?php } ?>
        </div>
    </section>
<?php include_once('includes/footer.php'); ?>