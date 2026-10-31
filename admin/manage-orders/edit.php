<?php
require_once("../includes/bootstrap.php");
require_once("../../includes/order_email.php");

$order_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($order_id === false || $order_id < 1) {
    http_response_code(404);
    exit('Order not found.');
}

function load_admin_order(mysqli $conn, int $order_id)
{
    $order_lookup_statement = mysqli_prepare($conn,
        "SELECT o.order_id, o.user_id, o.fulfillment_status, o.payment_status, o.shipping_address,
                o.created_at, u.username, u.email,
                COALESCE(NULLIF(c.customer_name, ''), u.username) AS customer_name
         FROM orders o
         JOIN users u ON u.user_id = o.user_id
         LEFT JOIN customers c ON c.user_id = u.user_id
         WHERE o.order_id = ?"
    );
    mysqli_stmt_bind_param($order_lookup_statement, 'i', $order_id);
    mysqli_stmt_execute($order_lookup_statement);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($order_lookup_statement));
    return $order ?: null;
}

function admin_send_order_update_notification(mysqli $conn, array $order, string $action)
{
    try {
        if ($action === 'mark_paid') {
            $items_stmt = mysqli_prepare($conn,
                "SELECT product_name, color, size, quantity, unit_price AS price
                 FROM order_transaction_details
                 WHERE order_id = ?
                 ORDER BY order_item_id"
            );
            $order_id = (int)$order['order_id'];
            mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
            mysqli_stmt_execute($items_stmt);
            $order['items'] = mysqli_fetch_all(mysqli_stmt_get_result($items_stmt), MYSQLI_ASSOC);
        }
        send_order_update_email($order, $action);
        return true;
    } catch (mysqli_sql_exception $exception) {
        error_log('Could not load order receipt items for order #' . (int)$order['order_id'] . ': ' . $exception->getMessage());
        return false;
    } catch (RuntimeException $exception) {
        $details = $exception->getMessage();
        if ($exception->getPrevious()) {
            $details .= ' Details: ' . $exception->getPrevious()->getMessage();
        }
        error_log('Order update email failed for order #' . (int)$order['order_id'] . ': ' . $details);
        return false;
    }
}

$order = load_admin_order($conn, $order_id);
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_address') {
            $shipping_address = trim(is_string($_POST['shipping_address'] ?? null) ? $_POST['shipping_address'] : '');
            if ($shipping_address === '' || mb_strlen($shipping_address) > 255) {
                $errors[] = 'Enter a shipping address up to 255 characters.';
            } else {
                $address_update_statement = mysqli_prepare($conn,
                    "UPDATE orders
                     SET shipping_address = ?
                     WHERE order_id = ? AND fulfillment_status = 'pending'"
                );
                mysqli_stmt_bind_param($address_update_statement, 'si', $shipping_address, $order_id);
                mysqli_stmt_execute($address_update_statement);
                if (mysqli_stmt_affected_rows($address_update_statement) !== 1) {
                    $current_order = load_admin_order($conn, $order_id);
                    if (!$current_order || $current_order['fulfillment_status'] !== 'pending') {
                        $errors[] = 'Only pending orders can have their shipping address changed.';
                    } else {
                        admin_set_flash('success', 'Shipping address is unchanged.');
                        header('Location: ' . BASE_URL . 'admin/manage-orders/edit.php?id=' . $order_id);
                        exit;
                    }
                } else {
                    $notification_order = $order;
                    $notification_order['shipping_address'] = $shipping_address;
                    $email_sent = admin_send_order_update_notification($notification_order, 'update_address');
                    admin_set_flash(
                        'success',
                        $email_sent
                            ? 'Shipping address updated. A confirmation email was sent to the customer.'
                            : 'Shipping address updated, but the customer email could not be sent.'
                    );
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
                if (!$locked_order) {
                    throw new RuntimeException('Order not found.');
                }
                $old_fulfillment_status = $locked_order['fulfillment_status'];
                $old_payment_status = $locked_order['payment_status'];
                $target_fulfillment_status = null;
                $return_stock = false;

                if ($action === 'mark_shipped' && $old_fulfillment_status === 'pending' && $old_payment_status === 'unpaid') {
                    $target_fulfillment_status = 'shipped';
                } elseif ($action === 'mark_delivered' && $old_fulfillment_status === 'shipped' && $old_payment_status === 'unpaid') {
                    $target_fulfillment_status = 'delivered';
                } elseif ($action === 'mark_failed_delivery' && $old_fulfillment_status === 'shipped' && $old_payment_status === 'unpaid') {
                    $target_fulfillment_status = 'failed_delivery';
                    $return_stock = true;
                } elseif ($action === 'cancel' && $old_fulfillment_status === 'pending' && $old_payment_status === 'unpaid') {
                    $target_fulfillment_status = 'cancelled';
                    $return_stock = true;
                } elseif ($action === 'mark_paid' && $old_fulfillment_status === 'delivered' && $old_payment_status === 'unpaid') {
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

                if ($target_fulfillment_status !== null) {
                    $update_stmt = mysqli_prepare($conn,
                        "UPDATE orders
                         SET fulfillment_status = ?
                         WHERE order_id = ? AND fulfillment_status = ? AND payment_status = 'unpaid'"
                    );
                    mysqli_stmt_bind_param($update_stmt, 'sis', $target_fulfillment_status, $order_id, $old_fulfillment_status);
                    mysqli_stmt_execute($update_stmt);
                    if (mysqli_stmt_affected_rows($update_stmt) !== 1) {
                        throw new RuntimeException('The order status changed. Refresh and try again.');
                    }
                }

                mysqli_commit($conn);
                $notification_order = $order;
                if ($target_fulfillment_status !== null) {
                    $notification_order['fulfillment_status'] = $target_fulfillment_status;
                }
                if ($action === 'mark_paid') {
                    $notification_order['payment_status'] = 'paid';
                }
                $email_sent = admin_send_order_update_notification($conn, $notification_order, $action);
                $success_messages = [
                    'mark_shipped' => 'Order marked as shipped.',
                    'mark_delivered' => 'Order marked as delivered.',
                    'mark_failed_delivery' => 'Delivery marked as failed. Returned stock has been restored.',
                    'mark_paid' => 'Payment marked as paid.',
                    'cancel' => 'Order cancelled. Reserved stock has been restored.',
                ];
                admin_set_flash(
                    'success',
                    $success_messages[$action] . ($email_sent
                        ? ' A confirmation email was sent to the customer.'
                        : ' The customer email could not be sent.')
                );
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

$items_stmt = mysqli_prepare($conn,
    "SELECT quantity, unit_price AS price, product_name, color, size
     FROM order_transaction_details
     WHERE order_id = ?
     ORDER BY order_item_id"
);
mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
mysqli_stmt_execute($items_stmt);
$items = mysqli_fetch_all(mysqli_stmt_get_result($items_stmt), MYSQLI_ASSOC);
$flash = admin_take_flash();
$admin_page_title = 'Order #' . $order_id;
$admin_active_page = 'orders';
include("../includes/header.php");
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
<?php include("../includes/footer.php"); ?>
