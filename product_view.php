<?php
    session_start();
    include_once("includes/header.php");
    include_once("includes/config.php");
    require_once("includes/product_images.php");

    $products_sql = "SELECT p.*, pc.product_color_id AS first_color_id
      FROM products p
      LEFT JOIN product_colors pc
        ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
      ORDER BY p.product_id DESC";
    $products = mysqli_fetch_all(mysqli_query($conn, $products_sql), MYSQLI_ASSOC);
    $product_image_map = product_color_image_paths($conn, array_map('intval', array_column($products, 'first_color_id')));

?>
    <section class="section">
        <div class="section-head">
            <h2>All Products</h2>
        </div>
        <div class="product-row">
            <?php foreach ($products as $p) {
            $card_images = $product_image_map[(int)($p['first_color_id'] ?? 0)] ?? [];
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
                  <p class="price"><?php echo store_product_price_markup((float)$p['price']); ?></p>
                </a>
            </article>

            <?php } ?>
        </div>
    </section>
<?php include_once('includes/footer.php'); ?>