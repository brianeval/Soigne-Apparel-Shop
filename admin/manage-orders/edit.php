<?php
require_once "../includes/bootstrap.php";
require_once "../../includes/order_email.php";

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id < 1) {
    http_response_code(404);
    exit('Order not found.');
}

$order = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT o.order_id, o.user_id, o.fulfillment_status, o.payment_status, o.shipping_address,
            o.created_at, u.username, u.email,
            COALESCE(NULLIF(c.customer_name, ''), u.username) AS customer_name
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE o.order_id = $order_id"
));
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_address') {
        $shipping_address = trim($_POST['shipping_address'] ?? '');
        if ($shipping_address === '') {
            $errors[] = 'Enter a shipping address.';
        } elseif ($order['fulfillment_status'] !== 'pending') {
            $errors[] = 'Only pending orders can have their shipping address changed.';
        } else {
            $address_sql = mysqli_real_escape_string($conn, $shipping_address);
            mysqli_query($conn, "UPDATE orders SET shipping_address = '$address_sql' WHERE order_id = $order_id");
            $order['shipping_address'] = $shipping_address;
            $message = 'Shipping address updated.';
            try {
                send_order_update_email($order, 'update_address');
                $message .= ' A confirmation email was sent to the customer.';
            } catch (Throwable $exception) {
                $message .= ' The customer email could not be sent.';
            }
            admin_set_flash('success', $message);
            header('Location: ' . BASE_URL . 'admin/manage-orders/edit.php?id=' . $order_id);
            exit;
        }
    } elseif (in_array($action, ['mark_shipped', 'mark_delivered', 'mark_failed_delivery', 'mark_paid', 'cancel'], true)) {
        $old_fulfillment = $order['fulfillment_status'];
        $old_payment = $order['payment_status'];
        $target = null;
        $return_stock = false;
        $payment_updated = false;

        if ($action === 'mark_shipped' && $old_fulfillment === 'pending' && $old_payment === 'unpaid') {
            $target = 'shipped';
        } elseif ($action === 'mark_delivered' && $old_fulfillment === 'shipped' && $old_payment === 'unpaid') {
            $target = 'delivered';
        } elseif ($action === 'mark_failed_delivery' && $old_fulfillment === 'shipped' && $old_payment === 'unpaid') {
            $target = 'failed_delivery';
            $return_stock = true;
        } elseif ($action === 'cancel' && $old_fulfillment === 'pending' && $old_payment === 'unpaid') {
            $target = 'cancelled';
            $return_stock = true;
        } elseif ($action === 'mark_paid' && $old_fulfillment === 'delivered' && $old_payment === 'unpaid') {
            mysqli_query($conn, "UPDATE orders SET payment_status = 'paid' WHERE order_id = $order_id");
            $order['payment_status'] = 'paid';
            $payment_updated = true;
        }

        if ($target === null && !$payment_updated) {
            $errors[] = 'That action is not available for this order status.';
        } else {
            if ($target !== null) {
                mysqli_query($conn, "UPDATE orders SET fulfillment_status = '$target' WHERE order_id = $order_id");
                $order['fulfillment_status'] = $target;
            }
            if ($return_stock) {
                $items = mysqli_fetch_all(mysqli_query($conn,
                    "SELECT variant_id, quantity FROM order_items WHERE order_id = $order_id"
                ), MYSQLI_ASSOC);
                foreach ($items as $item) {
                    $quantity = (int)$item['quantity'];
                    $variant_id = (int)$item['variant_id'];
                    mysqli_query($conn, "UPDATE product_variants SET stock = stock + $quantity WHERE variant_id = $variant_id");
                }
            }

            if ($action === 'mark_paid') {
                $order['items'] = mysqli_fetch_all(mysqli_query($conn,
                    "SELECT product_name, color, size, quantity, unit_price AS price
                     FROM order_transaction_details
                     WHERE order_id = $order_id
                     ORDER BY order_item_id"
                ), MYSQLI_ASSOC);
            }

            $messages = [
                'mark_shipped' => 'Order marked as shipped.',
                'mark_delivered' => 'Order marked as delivered.',
                'mark_failed_delivery' => 'Delivery marked as failed. Returned stock has been restored.',
                'mark_paid' => 'Payment marked as paid.',
                'cancel' => 'Order cancelled. Reserved stock has been restored.',
            ];
            $message = $messages[$action];
            try {
                send_order_update_email($order, $action);
                $message .= ' A confirmation email was sent to the customer.';
            } catch (Throwable $exception) {
                $message .= ' The customer email could not be sent.';
            }
            admin_set_flash('success', $message);
            header('Location: ' . BASE_URL . 'admin/manage-orders/edit.php?id=' . $order_id);
            exit;
        }
    } else {
        $errors[] = 'Choose a valid order action.';
    }
}

$items = mysqli_fetch_all(mysqli_query($conn,
    "SELECT quantity, unit_price AS price, product_name, color, size
     FROM order_transaction_details
     WHERE order_id = $order_id
     ORDER BY order_item_id"
), MYSQLI_ASSOC);
$flash = admin_take_flash();
$admin_page_title = 'Order #' . $order_id;
$admin_active_page = 'orders';
include "../includes/header.php";
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">ORDER #<?php echo (int)$order_id; ?></p><h1>Order details</h1><p>Customer: <?php echo admin_escape($order['username']); ?> &middot; Placed <?php echo admin_escape(date('M j, Y', strtotime($order['created_at']))); ?></p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-orders/">&larr; All orders</a>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>
<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">ORDER ITEMS</p><h2>Items in this order</h2></div></div>
  <?php if ($items) { ?><div class="manage-order-view"><?php foreach ($items as $item) { ?><div class="manage-order-item"><strong><?php echo admin_escape($item['product_name']); ?></strong><small><?php echo admin_escape($item['color'] . ' · ' . $item['size']); ?> &middot; Qty <?php echo (int)$item['quantity']; ?> &middot; <?php echo peso((float)$item['price']); ?> each</small></div><?php } ?></div><?php } else { ?><p class="manage-empty">This order has no items.</p><?php } ?>
</section>
<section class="manage-panel">
  <div class="manage-panel-heading">
    <div><p class="admin-eyebrow">ORDER MANAGEMENT</p><h2>Update order</h2></div>
    <div class="manage-order-statuses" aria-label="Order status">
      <span class="status-pill status-<?php echo admin_escape($order['fulfillment_status']); ?>"><?php echo admin_escape(ucwords(str_replace('_', ' ', $order['fulfillment_status']))); ?></span>
      <span class="status-pill status-<?php echo admin_escape($order['payment_status']); ?>"><?php echo admin_escape(ucfirst($order['payment_status'])); ?></span>
    </div>
  </div>
  <div class="manage-row-actions manage-order-status-actions">
    <?php if ($order['fulfillment_status'] === 'pending' && $order['payment_status'] === 'unpaid') { ?>
      <form method="post"><button class="manage-small-button" type="submit" name="action" value="mark_shipped">Mark as Shipped</button></form>
      <form method="post"><button class="manage-small-button is-danger" type="submit" name="action" value="cancel">Mark as Cancelled</button></form>
    <?php } elseif ($order['fulfillment_status'] === 'shipped' && $order['payment_status'] === 'unpaid') { ?>
      <form method="post"><button class="manage-small-button" type="submit" name="action" value="mark_delivered">Mark as Delivered</button></form>
      <form method="post"><button class="manage-small-button is-danger" type="submit" name="action" value="mark_failed_delivery">Mark Failed Delivery</button></form>
    <?php } elseif ($order['fulfillment_status'] === 'delivered' && $order['payment_status'] === 'unpaid') { ?>
      <form method="post"><button class="manage-small-button" type="submit" name="action" value="mark_paid">Mark as Paid</button></form>
    <?php } elseif ($order['fulfillment_status'] === 'failed_delivery') { ?>
      <p class="manage-order-final">Failed delivery is final. Returned items have been restored to stock.</p>
    <?php } elseif ($order['fulfillment_status'] === 'cancelled') { ?>
      <p class="manage-order-final">This order was cancelled.</p>
    <?php } else { ?>
      <p class="manage-order-final manage-order-complete">Fulfillment and payment are complete.</p>
    <?php } ?>
  </div>
  <?php if ($order['fulfillment_status'] === 'pending') { ?>
    <form method="post" class="manage-form" novalidate>
      <input type="hidden" name="action" value="update_address">
      <label>Shipping address<textarea name="shipping_address" rows="3"><?php echo admin_escape($_POST['shipping_address'] ?? $order['shipping_address']); ?></textarea></label>
      <div class="manage-form-buttons"><button class="admin-button" type="submit">Save shipping address</button></div>
    </form>
  <?php } ?>
  <?php if (!in_array($order['fulfillment_status'], ['failed_delivery', 'cancelled'], true)
      && !($order['fulfillment_status'] === 'delivered' && $order['payment_status'] === 'paid')) { ?>
    <p class="manage-help">For COD orders: mark shipped, then delivered, then mark paid after the courier collects payment. Cancelling is only available before shipping. Failed delivery is final and restores returned items to stock.</p>
  <?php } ?>
</section>
<?php include "../includes/footer.php"; ?>
