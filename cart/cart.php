<?php
session_start();
include_once('../includes/config.php');
require_once('../includes/customer_auth.php');

require_login();
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $selected_ids = array_values(array_filter(array_map('intval', (array)($_POST['selected_cart_ids'] ?? []))));

    if ($action === 'checkout_selected') {
        if ($selected_ids) {
            $id_list = implode(',', $selected_ids);
            $owned = mysqli_fetch_all(mysqli_query($conn,
                "SELECT cart_id FROM cart WHERE user_id = $user_id AND cart_id IN ($id_list)"), MYSQLI_ASSOC);
            if (count($owned) === count($selected_ids)) {
                $_SESSION['checkout_cart_ids'] = array_column($owned, 'cart_id');
                header('Location: ' . BASE_URL . 'orders/checkout.php');
                exit;
            }
        }
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Select at least one item in your cart to check out.'];
    } elseif ($action === 'remove_selected') {
        if ($selected_ids) {
            mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id AND cart_id IN (" . implode(',', $selected_ids) . ")");
        }
    } elseif ($action === 'remove') {
        $cart_id = (int)($_POST['cart_id'] ?? 0);
        mysqli_query($conn, "DELETE FROM cart WHERE cart_id = $cart_id AND user_id = $user_id");
    }
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$items = mysqli_fetch_all(mysqli_query($conn,
    "SELECT c.cart_id, c.quantity, v.size, v.stock, pc.color,
            (SELECT pci.image_path FROM product_color_images AS pci
             WHERE pci.product_color_id = pc.product_color_id
             ORDER BY pci.sort_order, pci.image_id LIMIT 1) AS image,
            p.product_id, p.name, p.price
     FROM cart c
     JOIN product_variants v ON v.variant_id = c.variant_id
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id
     WHERE c.user_id = $user_id
     ORDER BY c.cart_id DESC"), MYSQLI_ASSOC);

$vip_discount_rate = ($_SESSION['role'] ?? '') === 'vip' ? 0.10 : 0;
$subtotal = 0;
$original_subtotal = 0;
$item_count = 0;
$has_issue = false;
foreach ($items as &$item) {
    $item['discounted_price'] = round((float)$item['price'] * (1 - $vip_discount_rate), 2);
    $original_subtotal += (float)$item['price'] * (int)$item['quantity'];
    $subtotal += $item['discounted_price'] * (int)$item['quantity'];
    $item_count += (int)$item['quantity'];
    if ($item['stock'] < $item['quantity']) {
        $has_issue = true;
    }
}
unset($item);

$page_title = 'Your cart | Soigné';
$extra_css = 'cart.css';
include_once('../includes/header.php');
?>

<main class="cart-page">
  <?php if ($items) { ?>
    <div class="cart-page-heading">
      <div class="cart-page-heading-main">
        <h1>Your cart</h1>
        <form method="post" action="<?php echo BASE_URL; ?>cart/cart.php" class="cart-select-all-form" id="cart-bulk-actions">
          <button type="submit" name="action" value="remove_selected" class="cart-remove-all">Remove selected</button>
          <span>Select items below to remove or check out.</span>
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
        <?php foreach ($items as $item) { ?>
          <div class="cart-row">
            <label class="cart-item-select" aria-label="Select <?php echo htmlspecialchars($item['name']); ?>">
              <input type="checkbox" class="cart-item-checkbox" name="selected_cart_ids[]" value="<?php echo (int)$item['cart_id']; ?>" form="cart-bulk-actions">
            </label>
            <a class="cart-img" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$item['product_id']; ?>">
              <?php if (!empty($item['image'])) { ?>
                <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($item['image']); ?>"
                     alt="<?php echo htmlspecialchars($item['name']); ?>">
              <?php } ?>
            </a>

            <div class="cart-info">
              <a class="cart-name" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$item['product_id']; ?>">
                <?php echo htmlspecialchars($item['name']); ?>
              </a>
              <p class="cart-variant">
                Color: <?php echo htmlspecialchars($item['color']); ?> &middot; Size: <?php echo htmlspecialchars($item['size']); ?> &middot; Qty: <?php echo (int)$item['quantity']; ?>
              </p>
              <p class="cart-unit">
                <?php if ($vip_discount_rate > 0) { ?><del><?php echo peso($item['price']); ?></del> <?php } ?>
                <?php echo peso($item['discounted_price']); ?> each
              </p>

              <?php if ($item['stock'] < 1) { ?>
                <p class="cart-warn">This size is sold out. Please remove it.</p>
              <?php } elseif ($item['stock'] < $item['quantity']) { ?>
                <p class="cart-warn">Only <?php echo (int)$item['stock']; ?> left. Please lower the quantity.</p>
              <?php } ?>

              <div class="cart-actions">
                <a class="link-btn" href="<?php echo BASE_URL; ?>product.php?id=<?php echo (int)$item['product_id']; ?>&cart_id=<?php echo (int)$item['cart_id']; ?>">Update</a>
              </div>
            </div>

            <p class="cart-line"><?php echo peso($item['discounted_price'] * $item['quantity']); ?></p>

            <form method="post" action="<?php echo BASE_URL; ?>cart/cart.php" class="cart-remove">
              <input type="hidden" name="cart_id" value="<?php echo (int)$item['cart_id']; ?>">
              <input type="hidden" name="action" value="remove">
              <button type="submit" class="cart-remove-button" aria-label="Remove <?php echo htmlspecialchars($item['name']); ?> from cart" title="Remove item">&times;</button>
            </form>
          </div>
        <?php } ?>
      </div>

      <aside class="cart-summary">
        <h2>Order summary</h2>
        <p class="sum-row"><span>Items</span><span><?php echo (int)$item_count; ?></span></p>
        <?php if ($vip_discount_rate > 0) { ?>
          <p class="sum-row"><span>VIP discount (10%)</span><span>-<?php echo peso($original_subtotal - $subtotal); ?></span></p>
        <?php } ?>
        <p class="sum-row sum-total"><span>Subtotal</span><span><?php echo peso($subtotal); ?></span></p>
        <?php if ($vip_discount_rate > 0) { ?><p class="sum-note">Soigné Premium discount applied.</p><?php } ?>
        <p class="sum-note">Shipping is calculated at checkout.</p>

        <?php if ($has_issue) { ?>
          <p class="cart-warn">Some items in your cart have stock issues. Select only available items to check out.</p>
        <?php } ?>
        <button class="btn" type="submit" name="action" value="checkout_selected" form="cart-bulk-actions">Checkout selected</button>
        <a class="sum-continue" href="<?php echo BASE_URL; ?>department.php?dept=men">Continue shopping</a>
      </aside>
    </div>

  <?php } ?>
</main>

<?php include_once('../includes/footer.php'); ?>
