<?php
  session_start();
  include_once("includes/header.php");
  include_once("includes/config.php");
  require_once("includes/product_images.php");

  // Featured products
  $products_sql = "SELECT p.*, pc.product_color_id AS first_color_id
      FROM products p
      LEFT JOIN product_colors pc
        ON pc.product_color_id = (SELECT MIN(product_color_id) FROM product_colors WHERE product_id = p.product_id)
      ORDER BY p.product_id DESC
      LIMIT 5";
  $products = mysqli_fetch_all(mysqli_query($conn, $products_sql), MYSQLI_ASSOC);
  $product_image_map = product_color_image_paths($conn, array_map('intval', array_column($products, 'first_color_id')));
?>
  <main>
    <section class="hero" aria-label="Featured collections">
      <div class="hero-slide is-active">
        <div class="hero-text">
          <h1>Clothes made to be worn every day</h1>
          <p>Clean cuts and breathable fabrics for the Manila heat. The new season is here.</p>
          <a href="product_view.php" class="btn">Shop new arrivals</a>
        </div>
        <div class="hero-img" role="img" aria-label="Model wearing a linen shirt"></div>
      </div>
    </section>

    <section class="section">
      <h2>Shop by category</h2>
      <div class="cat-grid">
        <a href="<?php echo BASE_URL; ?>category.php?category=t-shirts" class="cat c1"><img src="<?php echo BASE_URL; ?>images/whiteshirt.jpg" alt=""><span>T-shirts</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=shirts" class="cat c2"><img src="<?php echo BASE_URL; ?>images/shirt-category-removebg-preview.png" alt="White button-up shirt"><span>Shirts</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=polos" class="cat c3"><img src="<?php echo BASE_URL; ?>images/polo-removebg-preview.png" alt="Polo shirt"><span>Polos</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=hoodies" class="cat c4"><img src="<?php echo BASE_URL; ?>images/hoodies-removebg-preview.png" alt="Hoodie"><span>Hoodies</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=sweaters" class="cat c5"><img src="<?php echo BASE_URL; ?>images/sweatshirt-removebg-preview.png" alt="Sweater"><span>Sweaters</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=pants" class="cat c6"><img src="<?php echo BASE_URL; ?>images/pants-removebg-preview.png" alt="Pants"><span>Pants</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=jeans" class="cat c7"><img src="<?php echo BASE_URL; ?>images/jeans-removebg-preview.png" alt="Jeans"><span>Jeans</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=chinos" class="cat c8"><img src="<?php echo BASE_URL; ?>images/chinos-removebg-preview.png" alt="Chinos"><span>Chinos</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=shorts" class="cat c9"><img src="<?php echo BASE_URL; ?>images/shorts-removebg-preview.png" alt="Khaki shorts"><span>Shorts</span></a>
        <a href="<?php echo BASE_URL; ?>category.php?category=outerwear" class="cat c10"><img src="<?php echo BASE_URL; ?>images/outerwear-removebg-preview.png" alt="Outerwear jacket"><span>Outerwear</span></a>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <h2>All Products</h2>
        <a href="product_view.php">View all</a>
      </div>
      <div class="product-row">
        <?php foreach ($products as $p) {
          $card_images = $product_image_map[(int)($p['first_color_id'] ?? 0)] ?? [];
          ?>

          <article class="product">
            <div class="thumb">
              <?php if ($card_images) { $image_path = $card_images[0]; ?>
                <a class="product-image-tile" href="<?php echo BASE_URL; ?>product.php?id= <?php echo (int)$p['product_id']; ?>"
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

    <section class="feature">
    </section>

  </main>

<?php
  include_once("includes/footer.php");
?>