<?php
session_start();
include_once('includes/config.php');


// STEP 1: Get the product id from the URL (product.php?id=4)
$id = (int)($_GET['id'] ?? 0);

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

$edit_cart_id = (int)($_GET['cart_id'] ?? 0);
$editing_cart_item = null;
if ($edit_cart_id > 0 && isset($_SESSION['user_id'])) {
    $stmt = mysqli_prepare($conn,
        "SELECT c.cart_id, c.quantity, v.variant_id, pc.product_color_id
         FROM cart c
         JOIN product_variants v ON v.variant_id = c.variant_id
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         WHERE c.cart_id = ? AND c.user_id = ? AND pc.product_id = ?"
    );
    $user_id = (int)$_SESSION['user_id'];
    mysqli_stmt_bind_param($stmt, 'iii', $edit_cart_id, $user_id, $id);
    mysqli_stmt_execute($stmt);
    $editing_cart_item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$editing_cart_item) {
        header('Location: ' . BASE_URL . 'cart/cart.php');
        exit;
    }
}

// STEP 3: Get all colors of this product
$q = "SELECT * FROM product_colors WHERE product_id = ?";
$stmt = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$colors = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

// STEP 4: Decide which color to show (default is the first one)
$selected = null;
if (isset($_GET['color'])) {
    foreach ($colors as $c) {
        if ((int)$c['product_color_id'] === (int)$_GET['color']) {
            $selected = $c;
            break;
        }
    }
}

if (!$selected && $editing_cart_item) {
    foreach ($colors as $c) {
        if ((int)$c['product_color_id'] === (int)$editing_cart_item['product_color_id']) {
            $selected = $c;
            break;
        }
    }
}
$selected = $selected ?? ($colors[0] ?? null);

// STEP 5: Get the sizes and stock of the selected color
$variants = [];
if ($selected) {
    $q = "SELECT * FROM product_variants WHERE product_color_id = ?";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, 'i', $selected['product_color_id']);
    mysqli_stmt_execute($stmt);
    $variants = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
}

$original_variant_id = $editing_cart_item ? (int)$editing_cart_item['variant_id'] : 0;
$selected_variant_id = 0;
$selected_quantity = $editing_cart_item ? (int)$editing_cart_item['quantity'] : 1;
$selected_stock = 1;
foreach ($variants as $variant) {
    if ((int)$variant['variant_id'] === $original_variant_id) {
        $selected_variant_id = $original_variant_id;
        $selected_stock = (int)$variant['stock'];
        break;
    }
}
$product_flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Page title, then the header
$page_title = $product['name'] . " | Soigné";
$extra_css = "product.css";
include_once('includes/header.php');
?>

<div class="product-page">

  <div class="product-photo">
    <img src="images/<?php echo htmlspecialchars($selected['image'] ?? ''); ?>" alt="">
  </div>

  <div class="product-info">
    <h1><?php echo ($product['name']); ?></h1>
    <p class="price"><?php echo peso($product['price']); ?></p>
    <p class="desc"><?php echo ($product['description']); ?></p>
    <?php if ($product_flash) { ?>
      <p class="product-flash <?php echo $product_flash['type'] === 'error' ? 'is-error' : 'is-success'; ?>" role="status">
        <?php echo htmlspecialchars($product_flash['text']); ?>
      </p>
    <?php } ?>

    <!-- Color choices -->
    <h3>Color: <?php echo htmlspecialchars($selected['color'] ?? ''); ?></h3>
    <div class="color-options">
      <?php foreach ($colors as $c) { ?>
        <?php
        $class = "";
        if ($selected && $c['product_color_id'] == $selected['product_color_id']) {
            $class = "active";
        }
        ?>
        <a class="<?php echo $class; ?>"
           href="product.php?id=<?php echo $id; ?>&color=<?php echo (int)$c['product_color_id']; ?><?php echo $editing_cart_item ? '&cart_id=' . (int)$edit_cart_id : ''; ?>">
          <?php echo htmlspecialchars($c['color']); ?>
        </a>
      <?php } ?>
    </div>

    <!-- Sizes and cart action -->
    <form method="post" action="<?php echo $editing_cart_item ? 'cart/cart_update.php' : 'cart/cart_add.php'; ?>">
      <?php if ($editing_cart_item) { ?>
        <input type="hidden" name="cart_id" value="<?php echo (int)$edit_cart_id; ?>">
      <?php } ?>
      <h3>Size</h3>
      <div class="size-options">
        <?php foreach ($variants as $v) { ?>
          <?php if ($v['stock'] > 0) { ?>
            <label>
              <input type="radio" name="variant_id" value="<?php echo (int)$v['variant_id']; ?>" data-stock="<?php echo (int)$v['stock']; ?>" <?php echo ((int)$v['variant_id'] === $selected_variant_id) ? 'checked' : ''; ?> required>
              <?php echo htmlspecialchars($v['size']); ?>
            </label>
          <?php } else { ?>
            <label>
              <input type="radio" name="variant_id" value="<?php echo (int)$v['variant_id']; ?>" data-stock="0" <?php echo ((int)$v['variant_id'] === $selected_variant_id) ? 'checked' : ''; ?> disabled>
              <?php echo htmlspecialchars($v['size']); ?> (sold out)
            </label>
          <?php } ?>
        <?php } ?>
      </div>

      <p class="qty">Quantity: <input type="number" id="product-quantity" name="quantity" value="<?php echo $selected_quantity; ?>" min="1" <?php echo $selected_variant_id ? 'max="' . max(1, $selected_stock) . '"' : ''; ?> required></p>
      <?php if ($editing_cart_item) { ?>
        <div class="product-actions">
          <button class="btn" type="submit">Update item</button>
          <a class="btn btn-outline product-cancel" href="<?php echo BASE_URL; ?>cart/cart.php">Cancel</a>
        </div>
      <?php } else { ?>
        <button class="btn" type="submit">Add to cart</button>
      <?php } ?>
    </form>
  </div>

</div>

<?php if (!isset($_SESSION['user_id'])) { ?>
  <dialog id="login-modal" class="modal">
    <button type="button" class="modal-close" aria-label="Close">&times;</button>
    <h2>Sign in to continue</h2>
    <p>Please sign in or create an account to add items to your cart.</p>
    <div class="modal-actions">
      <a class="btn" href="users/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Sign in</a>
      <a class="btn btn-outline" href="users/register.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Create account</a>
    </div>
  </dialog>

  <script>
    const modal = document.getElementById('login-modal');
    const cartForm = document.querySelector('form[action="cart/cart_add.php"]');

    // The submit event only fires after the "pick a size" check passes
    if (cartForm) {
      cartForm.addEventListener('submit', function (e) {
        e.preventDefault();
        modal.showModal();
      });
    }

    modal.querySelector('.modal-close').addEventListener('click', () => modal.close());
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.close(); }); // click outside
  </script>
<?php } ?>
<script>
(function () {
  var quantityInput = document.getElementById('product-quantity');
  var sizeOptions = document.querySelectorAll('.size-options input[name="variant_id"]');
  if (!quantityInput) return;

  sizeOptions.forEach(function (option) {
    option.addEventListener('change', function () {
      quantityInput.max = option.getAttribute('data-stock');
    });
  });
})();
</script>
<?php include_once('includes/footer.php'); ?>