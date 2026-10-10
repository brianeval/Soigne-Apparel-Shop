<?php
  session_start();
  include_once("includes/header.php");
  include_once("includes/config.php");

  $q = "SELECT DISTINCT p.*
      FROM products p
      JOIN product_colors pc ON pc.product_id = p.product_id
      JOIN product_variants v ON v.product_color_id = pc.product_color_id
      WHERE v.stock > 0";
  $result = mysqli_query($conn, $q);
?>
  <main>
    <section class="hero">
      <div class="hero-text">
        <h1>Clothes made to be worn every day</h1>
        <p>Clean cuts and breathable fabrics for the Manila heat. The new season is here.</p>
        <a href="#" class="btn">Shop new arrivals</a>
      </div>
      <div class="hero-img" role="img" aria-label="Model wearing a linen shirt"></div>
    </section>

    <section class="section">
      <h2>Shop by category</h2>
      <div class="cat-grid">
        <a href="#" class="cat c1"><img src="<?php echo BASE_URL; ?>images/whiteshirt.jpg" alt=""><span>T-shirts</span></a>
        <a href="#" class="cat c2"><img src="<?php echo BASE_URL; ?>images/shirt-category-removebg-preview.png" alt="White button-up shirt"><span>Shirts</span></a>
        <a href="#" class="cat c3"><img src="<?php echo BASE_URL; ?>images/polo-removebg-preview.png" alt="Polo shirt"><span>Polos</span></a>
        <a href="#" class="cat c4"><img src="<?php echo BASE_URL; ?>images/hoodies-removebg-preview.png" alt="Hoodie"><span>Hoodies</span></a>
        <a href="#" class="cat c5"><img src="<?php echo BASE_URL; ?>images/sweatshirt-removebg-preview.png" alt="Sweater"><span>Sweaters</span></a>
        <a href="#" class="cat c6"><img src="<?php echo BASE_URL; ?>images/pants-removebg-preview.png" alt="Pants"><span>Pants</span></a>
        <a href="#" class="cat c7"><img src="<?php echo BASE_URL; ?>images/jeans-removebg-preview.png" alt="Jeans"><span>Jeans</span></a>
        <a href="#" class="cat c8"><img src="<?php echo BASE_URL; ?>images/chinos-removebg-preview.png" alt="Chinos"><span>Chinos</span></a>
        <a href="#" class="cat c9"><img src="<?php echo BASE_URL; ?>images/shorts-removebg-preview.png" alt="Khaki shorts"><span>Shorts</span></a>
        <a href="#" class="cat c10"><img src="<?php echo BASE_URL; ?>images/outerwear-removebg-preview.png" alt="Outerwear jacket"><span>Outerwear</span></a>
      </div>
    </section>

    <section class="section">
      <div class="section-head">
        <h2>All Products</h2>
        <a href="#">View all</a>
      </div>
      <div class="product-row">
        <?php while ($p = mysqli_fetch_assoc($result)) { 


          // Get the first color's image of this product
          $q2 = "SELECT image FROM product_colors WHERE product_id = ? LIMIT 1";
          $stmt = mysqli_prepare($conn, $q2);
          mysqli_stmt_bind_param($stmt, 'i', $p['product_id']);
          mysqli_stmt_execute($stmt);
          $color = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
          ?>

          <a href="product.php?id=<?php echo $p['product_id']; ?>" class="product">
            <div class="thumb">
              <?php if (!empty($color['image'])) { ?>
                <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($color['image']); ?>"
                     alt="<?php echo htmlspecialchars($p['name']); ?>" loading="lazy">
              <?php } ?>
            </div>
            <p class="name"><?php echo ($p['name']); ?></p>
            <p class="price"><?php echo peso($p['price']); ?></p>
          </a>

        <?php } ?>
      </div>
    </section>

    <section class="feature">
      <div class="feature-img" role="img" aria-label="Folded basics"></div>
      <div class="feature-text">
        <h2>Basics that last</h2>
        <p>Plain colors, honest fabrics, and fits that work with everything already in your closet.</p>
        <a href="#" class="btn btn-outline">Shop the basics</a>
      </div>
    </section>

  </main>

<?php
  include_once("includes/footer.php");
?>