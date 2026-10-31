<?php
session_start();
include_once('../includes/config.php');
require_once('../includes/customer_auth.php');
require_once('../includes/order_email.php');

require_login();

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success_order_id = $_SESSION['checkout_success'] ?? null;
$checkout_email_sent = $_SESSION['checkout_email_sent'] ?? null;
unset($_SESSION['checkout_success'], $_SESSION['checkout_email_sent']);

$checkout_cart_ids = array_values(array_filter(array_map('intval', (array)($_SESSION['checkout_cart_ids'] ?? []))));
if (!$checkout_cart_ids && !$success_order_id) {
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}
$checkout_id_list = $checkout_cart_ids ? implode(',', $checkout_cart_ids) : '0';

$customer_name = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($customer_name === '') {
        $errors[] = 'Please enter your name.';
    }
    if ($phone === '') {
        $errors[] = 'Please enter your phone number.';
    }
    if ($address === '') {
        $errors[] = 'Please enter your shipping address.';
    }

    if (!$errors) {
        $items = mysqli_fetch_all(mysqli_query($conn,
            "SELECT c.cart_id, c.variant_id, c.quantity, v.stock, v.size, pc.color,
                    (SELECT pci.image_path FROM product_color_images AS pci
                     WHERE pci.product_color_id = pc.product_color_id
                     ORDER BY pci.sort_order, pci.image_id LIMIT 1) AS image,
                    p.product_id, p.name, p.price
             FROM cart c
             JOIN product_variants v ON v.variant_id = c.variant_id
             JOIN product_colors pc ON pc.product_color_id = v.product_color_id
             JOIN products p ON p.product_id = pc.product_id
             WHERE c.user_id = $user_id AND c.cart_id IN ($checkout_id_list)
             ORDER BY c.cart_id"), MYSQLI_ASSOC);

        if (!$items) {
            $errors[] = 'The selected cart items are no longer available. Please return to your cart and select items again.';
        } else {
            foreach ($items as $item) {
                if ((int)$item['stock'] < (int)$item['quantity']) {
                    $errors[] = 'One or more items are no longer available in the requested quantity. Please review your cart.';
                    break;
                }
            }
        }

        if (!$errors) {
            $recipient = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT email FROM users WHERE user_id = $user_id"));
            $email_items = [];
            $order_total = 0;
            $discount = ($_SESSION['role'] ?? '') === 'vip' ? 0.90 : 1;

            $address_sql = mysqli_real_escape_string($conn, $address);
            mysqli_query($conn, "INSERT INTO orders (user_id, shipping_address) VALUES ($user_id, '$address_sql')");
            $order_id = mysqli_insert_id($conn);

            foreach ($items as $item) {
                $variant_id = (int)$item['variant_id'];
                $quantity = (int)$item['quantity'];
                $price = round((float)$item['price'] * $discount, 2);
                $order_total += $price * $quantity;

                $email_items[] = [
                    'name' => $item['name'],
                    'color' => $item['color'],
                    'size' => $item['size'],
                    'quantity' => $quantity,
                    'price' => $price,
                ];

                mysqli_query($conn,
                    "INSERT INTO order_items (order_id, variant_id, quantity, price)
                     VALUES ($order_id, $variant_id, $quantity, $price)");
                mysqli_query($conn,
                    "UPDATE product_variants SET stock = stock - $quantity WHERE variant_id = $variant_id");
            }

            mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id AND cart_id IN ($checkout_id_list)");

            $email_sent = false;
            try {
                send_order_confirmation_email([
                    'email' => $recipient['email'],
                    'customer_name' => $customer_name,
                    'shipping_address' => $address,
                    'payment_method' => 'Cash on delivery (COD)',
                    'order_id' => $order_id,
                    'items' => $email_items,
                    'total' => round($order_total, 2),
                ]);
                $email_sent = true;
            } catch (RuntimeException $e) {
                error_log('Order confirmation email failed for order #' . $order_id . ': ' . $e->getMessage());
            }

            $_SESSION['checkout_success'] = $order_id;
            $_SESSION['checkout_email_sent'] = $email_sent;
            unset($_SESSION['checkout_cart_ids']);
            header('Location: ' . BASE_URL . 'orders/checkout.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $customer = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT customer_name, phone, address FROM customers WHERE user_id = $user_id"));
    if ($customer) {
        $customer_name = $customer['customer_name'] ?? '';
        $phone = $customer['phone'] ?? '';
        $address = $customer['address'] ?? '';
    }
}

$items = mysqli_fetch_all(mysqli_query($conn,
    "SELECT c.cart_id, c.quantity, v.variant_id, v.size, v.stock, pc.color,
            (SELECT pci.image_path FROM product_color_images AS pci
             WHERE pci.product_color_id = pc.product_color_id
             ORDER BY pci.sort_order, pci.image_id LIMIT 1) AS image,
            p.product_id, p.name, p.price
     FROM cart c
     JOIN product_variants v ON v.variant_id = c.variant_id
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id
     WHERE c.user_id = $user_id AND c.cart_id IN ($checkout_id_list)
     ORDER BY c.cart_id DESC"), MYSQLI_ASSOC);

$vip_discount_rate = ($_SESSION['role'] ?? '') === 'vip' ? 0.10 : 0;
$item_count = 0;
$subtotal = 0;
$original_subtotal = 0;
foreach ($items as &$item) {
    $item['discounted_price'] = round((float)$item['price'] * (1 - $vip_discount_rate), 2);
    $original_subtotal += (float)$item['price'] * (int)$item['quantity'];
    $subtotal += $item['discounted_price'] * (int)$item['quantity'];
    $item_count += (int)$item['quantity'];
}
unset($item);

$page_title = 'Checkout | Soigné';
$extra_css = 'checkout.css';
include_once('../includes/header.php');
?>

<main class="checkout-page">
  <h1>Checkout</h1>

  <?php if ($success_order_id) { ?>
    <div class="checkout-notice is-success" role="status">
      <h2>Order placed successfully</h2>
      <p>Your order #<?php echo (int)$success_order_id; ?> is confirmed. Payment is due by cash on delivery.</p>
      <?php if ($checkout_email_sent) { ?>
        <p>A confirmation email with your order details has been sent to your account email address.</p>
      <?php } else { ?>
        <p>Your order is confirmed, but we could not send the confirmation email. Please contact us if you need a copy of your order details.</p>
      <?php } ?>
    </div>
  <?php } ?>

  <?php if ($errors) { ?>
    <div class="checkout-notice is-error" role="alert">
      <ul>
        <?php foreach ($errors as $error) { ?>
          <li><?php echo htmlspecialchars($error); ?></li>
        <?php } ?>
      </ul>
    </div>
  <?php } ?>

  <?php if (!$items) { ?>
    <div class="checkout-empty">
      <?php if (!$success_order_id) { ?><p>The selected cart items are no longer available. Please return to your cart and select items to check out.</p><?php } ?>
      <a class="btn" href="<?php echo BASE_URL; ?>cart/cart.php">Back to cart</a>
    </div>
  <?php } else { ?>
    <div class="checkout-layout">
      <section class="checkout-products" aria-labelledby="checkout-products-title">
        <h2 id="checkout-products-title">Your items (<?php echo (int)$item_count; ?>)</h2>
        <div class="checkout-product-list">
          <?php foreach ($items as $item) { ?>
            <article class="checkout-product">
              <div class="checkout-product-image">
                <?php if (!empty($item['image'])) { ?>
                  <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($item['image']); ?>"
                       alt="<?php echo htmlspecialchars($item['name']); ?>">
                <?php } ?>
              </div>
              <div class="checkout-product-info">
                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                <p><?php echo htmlspecialchars($item['color']); ?> &middot; Size <?php echo htmlspecialchars($item['size']); ?></p>
                <p>Qty: <?php echo (int)$item['quantity']; ?></p>
              </div>
              <p class="checkout-product-total">
                <?php if ($vip_discount_rate > 0) { ?><del><?php echo peso((float)$item['price'] * (int)$item['quantity']); ?></del><?php } ?>
                <?php echo peso((float)$item['discounted_price'] * (int)$item['quantity']); ?>
              </p>
            </article>
          <?php } ?>
        </div>
        <?php if ($vip_discount_rate > 0) { ?><p class="checkout-subtotal"><span>VIP discount (10%)</span><strong>-<?php echo peso($original_subtotal - $subtotal); ?></strong></p><?php } ?>
        <p class="checkout-subtotal"><span>Subtotal</span><strong><?php echo peso($subtotal); ?></strong></p>
      </section>

      <form class="checkout-confirmation" method="post" action="<?php echo BASE_URL; ?>orders/checkout.php" novalidate>
        <h2>Confirm your order</h2>

        <label for="customer_name">Name</label>
        <input type="text" id="customer_name" name="customer_name"
               value="<?php echo htmlspecialchars($customer_name); ?>">

        <label for="address">Shipping address</label>
        <textarea id="address" name="address"><?php echo htmlspecialchars($address); ?></textarea>

        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone"
               value="<?php echo htmlspecialchars($phone); ?>">

        <div class="checkout-payment">
          <span>Payment method</span>
          <strong>Cash on delivery (COD)</strong>
        </div>

        <?php if ($vip_discount_rate > 0) { ?><p class="checkout-discount-note">Your Soigné Premium 10% discount has been applied.</p><?php } ?>
        <p class="checkout-total"><span>Order total</span><strong><?php echo peso($subtotal); ?></strong></p>
        <button class="btn" type="submit">Place order</button>
        <a class="checkout-back" href="<?php echo BASE_URL; ?>cart/cart.php">Back to cart</a>
      </form>
    </div>
  <?php } ?>
</main>

<?php include_once('../includes/footer.php'); ?>
