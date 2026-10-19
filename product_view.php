<?php
    session_start();
    include_once("includes/header.php");
    include_once("includes/config.php");

    $q = "SELECT p.*
      FROM products p
      ORDER BY p.product_id DESC";
    $result = mysqli_query($conn, $q);

?>
    <section class="section">
        <div class="section-head">
            <h2>All Products</h2>
        </div>
        <div class="product-row">
            <?php while ($p = mysqli_fetch_assoc($result)) { 


            // This query picks the first available color image for the product so the product card has a picture.
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
</body>
</html>