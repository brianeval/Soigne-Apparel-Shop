<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$order_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($order_id === false || $order_id < 1) {
    http_response_code(404);
    exit('Order not found.');
}

function load_admin_order(mysqli $conn, int $order_id): ?array
{
    $stmt = mysqli_prepare($conn,
        "SELECT o.order_id, o.user_id, o.fulfillment_status, o.payment_status, o.shipping_address, o.created_at, u.username
         FROM orders o JOIN users u ON u.user_id = o.user_id WHERE o.order_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $order ?: null;
}

$order = load_admin_order($conn, $order_id);
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_address') {
            $shipping_address = trim(is_string($_POST['shipping_address'] ?? null) ? $_POST['shipping_address'] : '');
            if ($shipping_address === '' || mb_strlen($shipping_address) > 255) {
                $errors[] = 'Enter a shipping address up to 255 characters.';
            } else {
                $stmt = mysqli_prepare($conn,
                    "UPDATE orders
                     SET shipping_address = ?
                     WHERE order_id = ? AND fulfillment_status = 'pending'"
                );
                mysqli_stmt_bind_param($stmt, 'si', $shipping_address, $order_id);
                mysqli_stmt_execute($stmt);
                if (mysqli_stmt_affected_rows($stmt) !== 1) {
                    $current_order = load_admin_order($conn, $order_id);
                    if (!$current_order || $current_order['fulfillment_status'] !== 'pending') {
                        $errors[] = 'Only pending orders can have their shipping address changed.';
                    } else {
                        admin_set_flash('success', 'Shipping address updated.');
                        header('Location: ' . BASE_URL . 'admin/manage-orders/edit.php?id=' . $order_id);
                        exit;
                    }
                } else {
                    admin_set_flash('success', 'Shipping address updated.');
                    header('Location: ' . BASE_URL . 'admin/manage-orders/edit.php?id=' . $order_id);
                    exit;
                }
            }
        } else {
            $allowed_actions = ['mark_shipped', 'mark_delivered', 'mark_failed_delivery', 'mark_paid', 'cancel'];
            if (!in_array($action, $allowed_actions, true)) {
                $errors[] = 'Choose a valid order action.';
            }
        }

        if (!$errors && $action !== 'update_address') {
            mysqli_begin_transaction($conn);
            try {
                $lock_stmt = mysqli_prepare($conn,
                    "SELECT fulfillment_status, payment_status
                     FROM orders WHERE order_id = ? FOR UPDATE"
                );
                mysqli_stmt_bind_param($lock_stmt, 'i', $order_id);
                mysqli_stmt_execute($lock_stmt);
                $locked_order = mysqli_fetch_assoc(mysqli_stmt_get_result($lock_stmt));
                if (!$locked_order) throw new RuntimeException('Order not found.');
                $old_fulfillment = $locked_order['fulfillment_status'];
                $old_payment = $locked_order['payment_status'];
                $target_fulfillment = null;
                $return_stock = false;

                if ($action === 'mark_shipped' && $old_fulfillment === 'pending' && $old_payment === 'unpaid') {
                    $target_fulfillment = 'shipped';
                } elseif ($action === 'mark_delivered' && $old_fulfillment === 'shipped' && $old_payment === 'unpaid') {
                    $target_fulfillment = 'delivered';
                } elseif ($action === 'mark_failed_delivery' && $old_fulfillment === 'shipped' && $old_payment === 'unpaid') {
                    $target_fulfillment = 'failed_delivery';
                    $return_stock = true;
                } elseif ($action === 'cancel' && $old_fulfillment === 'pending' && $old_payment === 'unpaid') {
                    $target_fulfillment = 'cancelled';
                    $return_stock = true;
                } elseif ($action === 'mark_paid' && $old_fulfillment === 'delivered' && $old_payment === 'unpaid') {
                    $payment_stmt = mysqli_prepare($conn,
                        "UPDATE orders SET payment_status = 'paid'
                         WHERE order_id = ? AND fulfillment_status = 'delivered' AND payment_status = 'unpaid'"
                    );
                    mysqli_stmt_bind_param($payment_stmt, 'i', $order_id);
                    mysqli_stmt_execute($payment_stmt);
                    if (mysqli_stmt_affected_rows($payment_stmt) !== 1) {
                        throw new RuntimeException('The payment status changed. Refresh and try again.');
                    }
                } else {
                    throw new RuntimeException('That action is not available for this order status.');
                }

                if ($return_stock) {
                    $items_stmt = mysqli_prepare($conn, "SELECT variant_id, quantity FROM order_items WHERE order_id = ? FOR UPDATE");
                    mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
                    mysqli_stmt_execute($items_stmt);
                    $items = mysqli_fetch_all(mysqli_stmt_get_result($items_stmt), MYSQLI_ASSOC);
                    $stock_stmt = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?");
                    foreach ($items as $item) {
                        $quantity = (int)$item['quantity'];
                        $variant_id = (int)$item['variant_id'];
                        mysqli_stmt_bind_param($stock_stmt, 'ii', $quantity, $variant_id);
                        mysqli_stmt_execute($stock_stmt);
                    }
                }

                if ($target_fulfillment !== null) {
                    $update_stmt = mysqli_prepare($conn,
                        "UPDATE orders
                         SET fulfillment_status = ?
                         WHERE order_id = ? AND fulfillment_status = ? AND payment_status = 'unpaid'"
                    );
                    mysqli_stmt_bind_param($update_stmt, 'sis', $target_fulfillment, $order_id, $old_fulfillment);
                    mysqli_stmt_execute($update_stmt);
                    if (mysqli_stmt_affected_rows($update_stmt) !== 1) {
                        throw new RuntimeException('The order status changed. Refresh and try again.');
                    }
                }

                mysqli_commit($conn);
                $success_messages = [
                    'mark_shipped' => 'Order marked as shipped.',
                    'mark_delivered' => 'Order marked as delivered.',
                    'mark_failed_delivery' => 'Delivery marked as failed. Returned stock has been restored.',
                    'mark_paid' => 'Payment marked as paid.',
                    'cancel' => 'Order cancelled. Reserved stock has been restored.',
                ];
                admin_set_flash('success', $success_messages[$action]);
                header('Location: ' . BASE_URL . 'admin/manage-orders/edit.php?id=' . $order_id);
                exit;
            } catch (RuntimeException $exception) {
                mysqli_rollback($conn);
                $errors[] = $exception->getMessage();
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                $errors[] = 'Could not update this order.';
            }
        }
    }
}

$items_stmt = mysqli_prepare($conn,
    "SELECT oi.quantity, oi.price, p.name AS product_name, pc.color, v.size
     FROM order_items oi
     JOIN product_variants v ON v.variant_id = oi.variant_id
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id
     WHERE oi.order_id = ?
     ORDER BY oi.order_item_id"
);
mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
mysqli_stmt_execute($items_stmt);
$items = mysqli_fetch_all(mysqli_stmt_get_result($items_stmt), MYSQLI_ASSOC);
$flash = admin_take_flash();
$admin_page_title = 'Order #' . $order_id;
$admin_active_page = 'orders';
include __DIR__ . '/../includes/header.php';
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
      <form method="post"><input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>"><button class="manage-small-button" type="submit" name="action" value="mark_shipped">Mark as Shipped</button></form>
      <form method="post" onsubmit="return confirm('Cancel this pending order and return its reserved items to stock?');"><input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>"><button class="manage-small-button is-danger" type="submit" name="action" value="cancel">Mark as Cancelled</button></form>
    <?php } elseif ($order['fulfillment_status'] === 'shipped' && $order['payment_status'] === 'unpaid') { ?>
      <form method="post"><input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>"><button class="manage-small-button" type="submit" name="action" value="mark_delivered">Mark as Delivered</button></form>
      <form method="post" onsubmit="return confirm('Confirm delivery failed and the returned items back in stock?');"><input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>"><button class="manage-small-button is-danger" type="submit" name="action" value="mark_failed_delivery">Mark Failed Delivery</button></form>
    <?php } elseif ($order['fulfillment_status'] === 'delivered' && $order['payment_status'] === 'unpaid') { ?>
      <form method="post" onsubmit="return confirm('Confirm payment was collected for this delivered COD order?');"><input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>"><button class="manage-small-button" type="submit" name="action" value="mark_paid">Mark as Paid</button></form>
    <?php } elseif ($order['fulfillment_status'] === 'failed_delivery') { ?>
      <p class="manage-order-final">Failed delivery is final. Returned items have been restored to stock.</p>
    <?php } elseif ($order['fulfillment_status'] === 'cancelled') { ?>
      <p class="manage-order-final">This order was cancelled.</p>
    <?php } else { ?>
      <p class="manage-order-final manage-order-complete">Fulfillment and payment are complete.</p>
    <?php } ?>
  </div>
  <?php if ($order['fulfillment_status'] === 'pending') { ?>
    <form method="post" class="manage-form">
      <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
      <input type="hidden" name="action" value="update_address">
      <label>Shipping address<textarea name="shipping_address" maxlength="255" rows="3" required><?php echo admin_escape($_POST['shipping_address'] ?? $order['shipping_address']); ?></textarea></label>
      <div class="manage-form-buttons"><button class="admin-button" type="submit">Save shipping address</button></div>
    </form>
  <?php } ?>
  <?php if (!in_array($order['fulfillment_status'], ['failed_delivery', 'cancelled'], true)
      && !($order['fulfillment_status'] === 'delivered' && $order['payment_status'] === 'paid')) { ?>
    <p class="manage-help">For COD orders: mark shipped, then delivered, then mark paid after the courier collects payment. Cancelling is only available before shipping. Failed delivery is final and restores returned items to stock.</p>
  <?php } ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
