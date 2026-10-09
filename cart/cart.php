<?php
session_start();
include_once ('../includes/config.php');

// Cart is tied to an account
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode(BASE_URL . 'cart/cart.php'));
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// --- Update / remove (then redirect so a refresh doesn't repeat it) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart_id = (int)($_POST['cart_id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    $qty     = (int)($_POST['quantity'] ?? 1);

    if ($action === 'remove' || ($action === 'update' && $qty < 1)) {
        $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
        mysqli_stmt_execute($stmt);
    } elseif ($action === 'update') {
        // never above the stock, never below 1
        $q = "UPDATE cart c
              JOIN product_variants v ON v.variant_id = c.variant_id
              SET c.quantity = GREATEST(1, LEAST(?, v.stock))
              WHERE c.cart_id = ? AND c.user_id = ?";
        $stmt = mysqli_prepare($conn, $q);
        mysqli_stmt_bind_param($stmt, 'iii', $qty, $cart_id, $user_id);
        mysqli_stmt_execute($stmt);
    }
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

// --- Message from cart_add.php or other actions ---
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// --- Cart contents ---
$q = "SELECT c.cart_id, c.quantity,
             v.size, v.stock,
             pc.color, pc.image,
             p.product_id, p.name, p.price
      FROM cart c
      JOIN product_variants v ON v.variant_id = c.variant_id
      JOIN product_colors pc  ON pc.product_color_id = v.product_color_id
      JOIN products p         ON p.product_id = pc.product_id
      WHERE c.user_id = ?
      ORDER BY c.cart_id DESC";
$stmt = mysqli_prepare($conn, $q);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

$subtotal   = 0;
$item_count = 0;
$has_issue  = false;
foreach ($items as $it) {
    $subtotal   += $it['price'] * $it['quantity'];
    $item_count += $it['quantity'];
    if ($it['stock'] < $it['quantity']) { $has_issue = true; }
}

$page_title = "Your cart | Soigné";
$extra_css  = "cart.css";
include_once ('../includes/header.php');
?>

<main class="cart-page">
  <h1>Your cart</h1>

  <?php if ($flash) { ?>
    <p class="cart-flash <?php echo $flash['type'] === 'error' ? 'is-error' : 'is-success'; ?>" role="status">
      <?php echo htmlspecialchars($flash['text']); ?>
    </p>
  <?php } ?>

  <?php if (!$items) { ?>

    <div class="cart-empty">
      <p>Your cart is empty.</p>
      <a class="btn" href="<?php echo BASE_URL; ?>department.php?dept=men">Start shopping</a>
    </div>

  <?php } else { ?>

    <div class="cart-layout">
      <div class="cart-items">
        <?php foreach ($items as $it) { ?>
          <div class="cart-row">
            <a class="cart-img" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$it['product_id']; ?>">
              <?php if (!empty($it['image'])) { ?>
                <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($it['image']); ?>"
                     alt="<?php echo htmlspecialchars($it['name']); ?>">
              <?php } ?>
            </a>

            <div class="cart-info">
              <a class="cart-name" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$it['product_id']; ?>">
                <?php echo htmlspecialchars($it['name']); ?>
              </a>
              <p class="cart-variant">
                Color: <?php echo htmlspecialchars($it['color']); ?> &middot; Size: <?php echo htmlspecialchars($it['size']); ?>
              </p>
              <p class="cart-unit"><?php echo peso($it['price']); ?></p>

              <?php if ($it['stock'] < 1) { ?>
                <p class="cart-warn">This size is sold out. Please remove it.</p>
              <?php } elseif ($it['stock'] < $it['quantity']) { ?>
                <p class="cart-warn">Only <?php echo (int)$it['stock']; ?> left. Please lower the quantity.</p>
              <?php } ?>

              <div class="cart-actions">
                <form method="post" action="/soigne_apparel_shop/cart/cart.php" class="cart-qty">
                    <input type="hidden" name="cart_id" value="<?php echo (int)$it['cart_id']; ?>">
                    <input type="hidden" name="action" value="update">
                    <label>Qty
                    <input type="number" name="quantity" value="<?php echo (int)$it['quantity']; ?>"
                            min="1" max="<?php echo max(1, (int)$it['stock']); ?>">
                    </label>
                    <button type="submit" class="link-btn">Update</button>
                </form>

                <form method="post" action="/soigne_apparel_shop/cart/cart.php">
                    <input type="hidden" name="cart_id" value="<?php echo (int)$it['cart_id']; ?>">
                    <input type="hidden" name="action" value="remove">
                    <button type="submit" class="link-btn">Remove</button>
                </form>
                </div>
            </div>

            <p class="cart-line"><?php echo peso($it['price'] * $it['quantity']); ?></p>
          </div>
        <?php } ?>
      </div>

      <aside class="cart-summary">
        <h2>Order summary</h2>
        <p class="sum-row"><span>Items</span><span><?php echo (int)$item_count; ?></span></p>
        <p class="sum-row sum-total"><span>Subtotal</span><span><?php echo peso($subtotal); ?></span></p>
        <p class="sum-note">Shipping is calculated at checkout.</p>

        <?php if ($has_issue) { ?>
          <p class="cart-warn">Fix the items marked above before checking out.</p>
          <span class="btn is-disabled" aria-disabled="true">Checkout</span>
        <?php } else { ?>
          <a class="btn" href="<?php echo BASE_URL; ?>orders/checkout.php">Checkout</a>
        <?php } ?>
        <a class="sum-continue" href="<?php echo BASE_URL; ?>department.php?dept=men">Continue shopping</a>
      </aside>
    </div>

  <?php } ?>
</main>

<?php include_once ('../includes/footer.php'); ?>