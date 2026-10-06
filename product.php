<?php
session_start();
include('includes/config.php');

// STEP 1: Get the product id from the URL (product.php?id=4)
$id = $_GET['id'];

// STEP 2: Find the product
$q = "SELECT * FROM products WHERE product_id = ?";
$stmt = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$product) {
    echo "Product not found.";
    exit;
}

// STEP 3: Get all colors of this product
$q = "SELECT * FROM product_colors WHERE product_id = ?";
$stmt = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$colors = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// STEP 4: Decide which color to show (default is the first one)
$selected = $colors[0];

if (isset($_GET['color'])) {
    foreach ($colors as $c) {
        if ($c['product_color_id'] == $_GET['color']) {
            $selected = $c;
        }
    }
}

// STEP 5: Get the sizes and stock of the selected color
$q = "SELECT * FROM product_variants WHERE product_color_id = ?";
$stmt = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($stmt, 'i', $selected['product_color_id']);
mysqli_stmt_execute($stmt);
$variants = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// Page title, then the header
$page_title = $product['name'] . " | Soigné";
$extra_css = "product.css";
include('includes/header.php');
?>

<div class="product-page">

  <div class="product-photo">
    <img src="images/<?php echo ($selected['image']); ?>" alt="">
  </div>

  <div class="product-info">
    <h1><?php echo ($product['name']); ?></h1>
    <p class="price"><?php echo peso($product['price']); ?></p>
    <p class="desc"><?php echo ($product['description']); ?></p>

    <!-- Color choices -->
    <h3>Color: <?php echo ($selected['color']); ?></h3>
    <div class="color-options">
      <?php foreach ($colors as $c) { ?>
        <?php
        $class = "";
        if ($c['product_color_id'] == $selected['product_color_id']) {
            $class = "active";
        }
        ?>
        <a class="<?php echo $class; ?>"
           href="product.php?id=<?php echo $id; ?>&color=<?php echo $c['product_color_id']; ?>">
          <?php echo ($c['color']); ?>
        </a>
      <?php } ?>
    </div>

    <!-- Sizes and Add to cart -->
    <form method="post" action="cart_add.php">
      <h3>Size</h3>
      <div class="size-options">
        <?php foreach ($variants as $v) { ?>
          <?php if ($v['stock'] > 0) { ?>
            <label>
              <input type="radio" name="variant_id" value="<?php echo $v['variant_id']; ?>" required>
              <?php echo ($v['size']); ?>
            </label>
          <?php } else { ?>
            <label>
              <input type="radio" disabled>
              <?php echo ($v['size']); ?> (sold out)
            </label>
          <?php } ?>
        <?php } ?>
      </div>

      <p class="qty">Quantity: <input type="number" name="quantity" value="1" min="1"></p>
      <button class="btn" type="submit">Add to cart</button>
    </form>
  </div>

</div>

<?php include('includes/footer.php'); ?>