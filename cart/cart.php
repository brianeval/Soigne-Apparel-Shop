<?php
session_start();
include_once __DIR__ . '/../includes/config.php';

// Cart is tied to an account
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode(BASE_URL . 'cart/cart.php'));
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// --- Update / remove (then redirect so a refresh doesn't repeat it) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'checkout_selected') {
        $selected_ids = $_POST['selected_cart_ids'] ?? [];
        $valid_ids = [];
        if (is_array($selected_ids)) {
            foreach ($selected_ids as $selected_id) {
                if (is_string($selected_id) || is_int($selected_id)) {
                    $cart_id = filter_var($selected_id, FILTER_VALIDATE_INT);
                    if ($cart_id !== false && $cart_id > 0) {
                        $valid_ids[] = (int)$cart_id;
                    }
                }
            }
        }
        $valid_ids = array_values(array_unique($valid_ids));

        if ($valid_ids) {
            $id_list = implode(',', $valid_ids);
            $result = mysqli_query($conn, "SELECT cart_id FROM cart WHERE user_id = $user_id AND cart_id IN ($id_list)");
            $owned_ids = array_map('intval', array_column(mysqli_fetch_all($result, MYSQLI_ASSOC), 'cart_id'));
            if (count($owned_ids) === count($valid_ids)) {
                $_SESSION['checkout_cart_ids'] = $owned_ids;
                header('Location: ' . BASE_URL . 'orders/checkout.php');
                exit;
            }
        }
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Select at least one item in your cart to check out.'];
    } elseif ($action === 'remove_selected') {
        $selected_ids = $_POST['selected_cart_ids'] ?? [];
        if (is_array($selected_ids)) {
            $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
            foreach ($selected_ids as $selected_id) {
                if (!is_string($selected_id) && !is_int($selected_id)) {
                    continue;
                }
                $cart_id = filter_var($selected_id, FILTER_VALIDATE_INT);
                if ($cart_id !== false && $cart_id > 0) {
                    mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
                    mysqli_stmt_execute($stmt);
                }
            }
        }
    } elseif ($action === 'remove') {
        $cart_id = (int)($_POST['cart_id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
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
  <?php if ($items) { ?>
    <div class="cart-page-heading">
      <div class="cart-page-heading-main">
        <h1>Your cart</h1>
        <form method="post" action="<?php echo BASE_URL; ?>cart/cart.php" class="cart-select-all-form" id="cart-bulk-actions">
          <button type="submit" name="action" value="remove_selected" class="cart-remove-all" id="cart-remove-selected" disabled>Remove selected</button>
          <label class="cart-select-all">
            <input type="checkbox" id="cart-select-all">
            <span>Select all</span>
          </label>
        </form>
      </div>
    </div>
  <?php } else { ?>
    <h1>Your cart</h1>
  <?php } ?>

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
            <label class="cart-item-select" aria-label="Select <?php echo htmlspecialchars($it['name']); ?>">
              <input type="checkbox" class="cart-item-checkbox" name="selected_cart_ids[]" value="<?php echo (int)$it['cart_id']; ?>" form="cart-bulk-actions">
            </label>
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
                Color: <?php echo htmlspecialchars($it['color']); ?> &middot; Size: <?php echo htmlspecialchars($it['size']); ?> &middot; Qty: <?php echo (int)$it['quantity']; ?>
              </p>
              <p class="cart-unit"><?php echo peso($it['price']); ?></p>

              <?php if ($it['stock'] < 1) { ?>
                <p class="cart-warn">This size is sold out. Please remove it.</p>
              <?php } elseif ($it['stock'] < $it['quantity']) { ?>
                <p class="cart-warn">Only <?php echo (int)$it['stock']; ?> left. Please lower the quantity.</p>
              <?php } ?>

              <div class="cart-actions">
                <a class="link-btn" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$it['product_id']; ?>&cart_id=<?php echo (int)$it['cart_id']; ?>">Update</a>
              </div>
            </div>

            <p class="cart-line"><?php echo peso($it['price'] * $it['quantity']); ?></p>

            <form method="post" action="<?php echo BASE_URL; ?>cart/cart.php" class="cart-remove">
              <input type="hidden" name="cart_id" value="<?php echo (int)$it['cart_id']; ?>">
              <input type="hidden" name="action" value="remove">
              <button type="submit" class="cart-remove-button" aria-label="Remove <?php echo htmlspecialchars($it['name']); ?> from cart" title="Remove item">&times;</button>
            </form>
          </div>
        <?php } ?>
      </div>

      <aside class="cart-summary">
        <h2>Order summary</h2>
        <p class="sum-row"><span>Items</span><span><?php echo (int)$item_count; ?></span></p>
        <p class="sum-row sum-total"><span>Subtotal</span><span><?php echo peso($subtotal); ?></span></p>
        <p class="sum-note">Shipping is calculated at checkout.</p>

        <?php if ($has_issue) { ?>
          <p class="cart-warn">Some items in your cart have stock issues. Select only available items to check out.</p>
        <?php } ?>
        <button class="btn is-disabled" type="submit" name="action" value="checkout_selected" form="cart-bulk-actions" id="cart-checkout-selected" disabled>Checkout</button>
        <a class="sum-continue" href="<?php echo BASE_URL; ?>department.php?dept=men">Continue shopping</a>
      </aside>
    </div>

  <?php } ?>
</main>

<script>
(function () {
  var selectAll = document.getElementById('cart-select-all');
  var removeSelected = document.getElementById('cart-remove-selected');
  var checkoutSelected = document.getElementById('cart-checkout-selected');
  var itemCheckboxes = Array.prototype.slice.call(document.querySelectorAll('.cart-item-checkbox'));
  if (!selectAll || !removeSelected || !checkoutSelected || !itemCheckboxes.length) return;

  function syncSelection() {
    var selectedCount = 0;
    itemCheckboxes.forEach(function (checkbox) {
      var selected = checkbox.checked;
      checkbox.closest('.cart-row').classList.toggle('is-selected', selected);
      if (selected) selectedCount++;
    });
    selectAll.checked = selectedCount === itemCheckboxes.length;
    selectAll.indeterminate = selectedCount > 0 && selectedCount < itemCheckboxes.length;
    removeSelected.disabled = selectedCount === 0;
    checkoutSelected.disabled = selectedCount === 0;
    checkoutSelected.classList.toggle('is-disabled', selectedCount === 0);
  }

  selectAll.addEventListener('change', function () {
    itemCheckboxes.forEach(function (checkbox) {
      checkbox.checked = selectAll.checked;
    });
    syncSelection();
  });

  itemCheckboxes.forEach(function (checkbox) {
    checkbox.addEventListener('change', syncSelection);
  });
})();
</script>

<?php include_once ('../includes/footer.php'); ?>