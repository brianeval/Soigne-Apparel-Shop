<?php
session_start();
include_once('../includes/config.php');
require_once __DIR__ . '/../includes/customer_auth.php';
require_once __DIR__ . '/../includes/order_email.php';

require_active_customer($conn, BASE_URL . 'orders/checkout.php');

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success_order_id = $_SESSION['checkout_success'] ?? null;
$checkout_email_sent = $_SESSION['checkout_email_sent'] ?? null;
unset($_SESSION['checkout_success']);
unset($_SESSION['checkout_email_sent']);
$checkout_cart_ids = $_SESSION['checkout_cart_ids'] ?? [];
if (!is_array($checkout_cart_ids)) {
    $checkout_cart_ids = [];
}
$checkout_cart_ids = array_values(array_unique(array_filter(
    array_map('intval', $checkout_cart_ids),
    function ($cart_id) {
        return $cart_id > 0;
    }
)));

if (!$checkout_cart_ids && !$success_order_id) {
    header('Location: ' . BASE_URL . 'cart/cart.php');
    exit;
}
$checkout_id_list = $checkout_cart_ids ? implode(',', $checkout_cart_ids) : '0';

if (!isset($_SESSION['checkout_csrf'])) {
    $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
}

$customer_name = '';
$phone = '';
$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_name = $_POST['customer_name'] ?? '';
    $posted_phone = $_POST['phone'] ?? '';
    $posted_address = $_POST['address'] ?? '';
    $customer_name = is_string($posted_name) ? trim($posted_name) : '';
    $phone = is_string($posted_phone) ? trim($posted_phone) : '';
    $address = is_string($posted_address) ? trim($posted_address) : '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!is_string($csrf_token) || !hash_equals($_SESSION['checkout_csrf'], $csrf_token)) {
        $errors[] = 'Your checkout session expired. Please try again.';
    }
    if ($customer_name === '' || mb_strlen($customer_name) > 120) {
        $errors[] = 'Please enter your name (up to 120 characters).';
    }
    if ($phone === '' || mb_strlen($phone) > 30) {
        $errors[] = 'Please enter your phone number (up to 30 characters).';
    }
    if ($address === '' || mb_strlen($address) > 255) {
        $errors[] = 'Please enter your shipping address (up to 255 characters).';
    }

    if (!$errors) {
        try {
            mysqli_begin_transaction($conn);

            // This query locks the selected cart items and their product details so checkout can safely confirm stock and create the order.
            $stmt = mysqli_prepare($conn, "SELECT c.cart_id, c.variant_id, c.quantity,
                                                   v.stock, v.size, pc.color,
                                                   (SELECT pci.image_path
                                                    FROM product_color_images AS pci
                                                    WHERE pci.product_color_id = pc.product_color_id
                                                    ORDER BY pci.sort_order, pci.image_id
                                                    LIMIT 1) AS image,
                                                   p.product_id, p.name, p.price
                                            FROM cart c
                                            JOIN product_variants v ON v.variant_id = c.variant_id
                                            JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                                            JOIN products p ON p.product_id = pc.product_id
                                            WHERE c.user_id = ? AND c.cart_id IN ($checkout_id_list)
                                            ORDER BY c.cart_id
                                            FOR UPDATE");
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            $items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

            if (!$items) {
                mysqli_rollback($conn);
                $errors[] = 'The selected cart items are no longer available. Please return to your cart and select items again.';
            } else {
                $out_of_stock = false;
                foreach ($items as $item) {
                    if ((int)$item['quantity'] < 1 || (int)$item['stock'] < (int)$item['quantity']) {
                        $out_of_stock = true;
                        break;
                    }
                }

                if ($out_of_stock) {
                    mysqli_rollback($conn);
                    $errors[] = 'One or more items are no longer available in the requested quantity. Please review your cart.';
                } else {
                    $payment_method = 'Cash on delivery (COD)';
                    $recipient_stmt = mysqli_prepare($conn, "SELECT email FROM users WHERE user_id = ?");
                    mysqli_stmt_bind_param($recipient_stmt, 'i', $user_id);
                    mysqli_stmt_execute($recipient_stmt);
                    $recipient = mysqli_fetch_assoc(mysqli_stmt_get_result($recipient_stmt));
                    if (!$recipient || !filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new mysqli_sql_exception('The customer email address is unavailable.');
                    }

                    // This query creates the main order record for the user and shipping address.
                    $stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, shipping_address) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt, 'is', $user_id, $address);
                    mysqli_stmt_execute($stmt);
                    $order_id = mysqli_insert_id($conn);

                    // This query adds each item to the order_items table and records the price at checkout time.
                    $item_stmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, variant_id, quantity, price)
                                                        VALUES (?, ?, ?, ?)");
                    // This query subtracts each ordered quantity from stock while making sure stock never goes below zero.
                    $stock_stmt = mysqli_prepare($conn, "UPDATE product_variants
                                                         SET stock = stock - ?
                                                         WHERE variant_id = ? AND stock >= ?");
                    $email_items = [];
                    $order_total = 0;
                    foreach ($items as $item) {
                        $variant_id = (int)$item['variant_id'];
                        $quantity = (int)$item['quantity'];
                        $price = round((float)$item['price'] * (($_SESSION['role'] ?? '') === 'vip' ? 0.90 : 1), 2);
                        $email_items[] = [
                            'name' => $item['name'],
                            'color' => $item['color'],
                            'size' => $item['size'],
                            'quantity' => $quantity,
                            'price' => $price,
                        ];
                        $order_total += $price * $quantity;

                        mysqli_stmt_bind_param($item_stmt, 'iiid', $order_id, $variant_id, $quantity, $price);
                        mysqli_stmt_execute($item_stmt);

                        mysqli_stmt_bind_param($stock_stmt, 'iii', $quantity, $variant_id, $quantity);
                        mysqli_stmt_execute($stock_stmt);
                        if (mysqli_stmt_affected_rows($stock_stmt) !== 1) {
                            throw new mysqli_sql_exception('Product stock changed during checkout.');
                        }
                    }

                    // This query clears the checked-out cart items for the signed-in user.
                    $delete_stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
                    foreach ($items as $item) {
                        $cart_id = (int)$item['cart_id'];
                        mysqli_stmt_bind_param($delete_stmt, 'ii', $cart_id, $user_id);
                        mysqli_stmt_execute($delete_stmt);
                    }

                    mysqli_commit($conn);
                    $email_sent = false;
                    try {
                        send_order_confirmation_email([
                            'email' => $recipient['email'],
                            'customer_name' => $customer_name,
                            'shipping_address' => $address,
                            'payment_method' => $payment_method,
                            'order_id' => $order_id,
                            'items' => $email_items,
                            'total' => round($order_total, 2),
                        ]);
                        $email_sent = true;
                    } catch (RuntimeException $e) {
                        $mail_error = $e->getMessage();
                        if ($e->getPrevious()) {
                            $mail_error .= ' Details: ' . $e->getPrevious()->getMessage();
                        }
                        error_log('Order confirmation email failed for order #' . $order_id . ': ' . $mail_error);
                    }
                    $_SESSION['checkout_success'] = $order_id;
                    $_SESSION['checkout_email_sent'] = $email_sent;
                    unset($_SESSION['checkout_cart_ids']);
                    unset($_SESSION['checkout_csrf']);
                    header('Location: ' . BASE_URL . 'orders/checkout.php');
                    exit;
                }
            }
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Could not place your order. Please try again.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // This query loads the saved shipping information for the logged-in user so checkout can be pre-filled.
    $stmt = mysqli_prepare($conn, "SELECT customer_name, phone, address FROM customers WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($customer) {
        $customer_name = $customer['customer_name'] ?? '';
        $phone = $customer['phone'] ?? '';
        $address = $customer['address'] ?? '';
    }
}

// This query loads the cart items again for the checkout summary so the user can review what they are ordering.
$stmt = mysqli_prepare($conn, "SELECT c.cart_id, c.quantity, v.variant_id, v.size, v.stock,
                                      pc.color,
                                      (SELECT pci.image_path
                                       FROM product_color_images AS pci
                                       WHERE pci.product_color_id = pc.product_color_id
                                       ORDER BY pci.sort_order, pci.image_id
                                       LIMIT 1) AS image,
                                      p.product_id, p.name, p.price
                               FROM cart c
                               JOIN product_variants v ON v.variant_id = c.variant_id
                               JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                               JOIN products p ON p.product_id = pc.product_id
                               WHERE c.user_id = ? AND c.cart_id IN ($checkout_id_list)
                               ORDER BY c.cart_id DESC");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

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

      <form class="checkout-confirmation" method="post" action="<?php echo BASE_URL; ?>orders/checkout.php">
        <h2>Confirm your order</h2>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['checkout_csrf']); ?>">

        <label for="customer_name">Name</label>
        <input type="text" id="customer_name" name="customer_name" maxlength="120"
               value="<?php echo htmlspecialchars($customer_name); ?>" required>

        <label for="address">Shipping address</label>
        <textarea id="address" name="address" maxlength="255" required><?php echo htmlspecialchars($address); ?></textarea>

        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" maxlength="30"
               value="<?php echo htmlspecialchars($phone); ?>" required>

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