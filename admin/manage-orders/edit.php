<?php
require_once("../includes/bootstrap.php");

$order_id = (int)($_GET['id'] ?? 0);

// Load the order and its customer
$order_result = mysqli_query($conn,
    "SELECT o.order_id, o.user_id, o.fulfillment_status, o.payment_status, o.shipping_address,
            o.created_at, u.username, u.email,
            COALESCE(NULLIF(c.customer_name, ''), u.username) AS customer_name
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE o.order_id = $order_id"
);
$order = mysqli_fetch_assoc($order_result);
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $send_email = false;   // becomes true when the customer should be told about the change

    if ($action == 'update_address') {
        // Change the shipping address (pending orders only)
        $shipping_address = trim($_POST['shipping_address'] ?? '');
        if ($shipping_address == '') {
            $errors[] = 'Enter a shipping address.';
        } elseif ($order['fulfillment_status'] != 'pending') {
            $errors[] = 'Only pending orders can have their shipping address changed.';
        } else {
            $address_result = mysqli_execute_query($conn, "UPDATE orders SET shipping_address = ? WHERE order_id = ?", [$shipping_address, $order_id]);
            $order['shipping_address'] = $shipping_address;
            $send_email = true;
            $success_text = 'Shipping address updated.';
        }
    } elseif (in_array($action, ['mark_shipped', 'mark_delivered', 'mark_failed_delivery', 'mark_paid', 'cancel'])) {
        // action => [status the order must have now, status it gets]
        $steps = [
            'mark_shipped' => ['pending', 'shipped'],
            'mark_delivered' => ['shipped', 'delivered'],
            'mark_failed_delivery' => ['shipped', 'failed_delivery'],
            'cancel' => ['pending', 'cancelled'],
        ];
        $success_messages = [
            'mark_shipped' => 'Order marked as shipped.',
            'mark_delivered' => 'Order marked as delivered.',
            'mark_failed_delivery' => 'Delivery marked as failed. Returned stock has been restored.',
            'mark_paid' => 'Payment marked as paid.',
            'cancel' => 'Order cancelled. Reserved stock has been restored.',
        ];

        try {
            mysqli_begin_transaction($conn);

            // Read the current status again
            $status_result = mysqli_query($conn, "SELECT fulfillment_status, payment_status FROM orders WHERE order_id = $order_id");
            $current = mysqli_fetch_assoc($status_result);

            if ($action == 'mark_paid' && $current['fulfillment_status'] == 'delivered' && $current['payment_status'] == 'unpaid') {
                mysqli_query($conn, "UPDATE orders SET payment_status = 'paid' WHERE order_id = $order_id");
                $order['payment_status'] = 'paid';
                $allowed = true;
            } elseif ($action != 'mark_paid' && $current['payment_status'] == 'unpaid' && $current['fulfillment_status'] == $steps[$action][0]) {
                $new_status = $steps[$action][1];

                // Failed delivery and cancelled orders give their stock back
                if ($new_status == 'failed_delivery' || $new_status == 'cancelled') {
                    $items_result = mysqli_query($conn, "SELECT variant_id, quantity FROM order_items WHERE order_id = $order_id");
                    foreach (mysqli_fetch_all($items_result, MYSQLI_ASSOC) as $item) {
                        mysqli_query($conn, "UPDATE product_variants SET stock = stock + " . (int)$item['quantity'] . " WHERE variant_id = " . (int)$item['variant_id']);
                    }
                }
                mysqli_query($conn, "UPDATE orders SET fulfillment_status = '$new_status' WHERE order_id = $order_id");
                $order['fulfillment_status'] = $new_status;
                $allowed = true;
            } else {
                $allowed = false;
            }

            if ($allowed) {
                mysqli_commit($conn);
                $send_email = true;
                $success_text = $success_messages[$action];
            } else {
                mysqli_rollback($conn);
                $errors[] = 'That action is not available for this order status.';
            }
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            $errors[] = 'Could not update this order.';
        }
    } else {
        $errors[] = 'Choose a valid order action.';
    }

    // Tell the customer about the change by email
    if ($send_email) {
        $email_type = $action;
        $email_order = $order;
        if ($action == 'mark_paid') {
            // The paid email carries a PDF receipt, so it needs the order items
            $receipt_items_result = mysqli_query($conn,
                "SELECT product_name, color, size, quantity, unit_price AS price
                 FROM order_transaction_details WHERE order_id = $order_id ORDER BY order_item_id"
            );
            $email_order['items'] = mysqli_fetch_all($receipt_items_result, MYSQLI_ASSOC);
        }

        $mail_error = '';
        try {
            include("../../includes/email_order_update.php");
        } catch (Exception $e) {
            $mail_error = $e->getMessage();
        }
        if ($mail_error != '') {
            error_log('Order update email failed for order #' . $order_id . ': ' . $mail_error);
            $success_text .= ' The customer email could not be sent.';
        } else {
            $success_text .= ' A confirmation email was sent to the customer.';
        }

        $_SESSION['success'] = $success_text;
        header('Location: ' . BASE_URL . 'admin/manage-orders/edit.php?id=' . $order_id);
        exit;
    }
}

$items_result = mysqli_execute_query($conn, "SELECT quantity, unit_price AS price, product_name, color, size
     FROM order_transaction_details
     WHERE order_id = ?
     ORDER BY order_item_id", [$order_id]);
$items = mysqli_fetch_all($items_result, MYSQLI_ASSOC);

$admin_page_title = 'Order #' . $order_id;
$admin_active_page = 'orders';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">ORDER #<?php echo (int)$order_id; ?></p><h1>Order details</h1><p>Customer: <?php echo htmlspecialchars($order['username']); ?> &middot; Placed <?php echo htmlspecialchars(date('M j, Y', strtotime($order['created_at']))); ?></p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-orders/">&larr; All orders</a>
</section>
<?php include("../../includes/alert.php"); ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error); ?></li><?php } ?></ul></div><?php } ?>
<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">ORDER ITEMS</p><h2>Items in this order</h2></div></div>
  <?php if ($items) { ?><div class="manage-order-view"><?php foreach ($items as $item) { ?><div class="manage-order-item"><strong><?php echo htmlspecialchars($item['product_name']); ?></strong><small><?php echo htmlspecialchars($item['color'] . ' · ' . $item['size']); ?> &middot; Qty <?php echo (int)$item['quantity']; ?> &middot; <?php echo '₱' . number_format($item['price'], 2); ?> each</small></div><?php } ?></div><?php } else { ?><p class="manage-empty">This order has no items.</p><?php } ?>
</section>
<section class="manage-panel">
  <div class="manage-panel-heading">
    <div><p class="admin-eyebrow">ORDER MANAGEMENT</p><h2>Update order</h2></div>
    <div class="manage-order-statuses" aria-label="Order status">
      <span class="status-pill status-<?php echo htmlspecialchars($order['fulfillment_status']); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['fulfillment_status']))); ?></span>
      <span class="status-pill status-<?php echo htmlspecialchars($order['payment_status']); ?>"><?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?></span>
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
      <label>Shipping address<textarea name="shipping_address" rows="3"><?php echo htmlspecialchars($_POST['shipping_address'] ?? $order['shipping_address']); ?></textarea></label>
      <div class="manage-form-buttons"><button class="admin-button" type="submit">Save shipping address</button></div>
    </form>
  <?php } ?>
  <?php if (!in_array($order['fulfillment_status'], ['failed_delivery', 'cancelled'], true)
      && !($order['fulfillment_status'] === 'delivered' && $order['payment_status'] === 'paid')) { ?>
    <p class="manage-help">For COD orders: mark shipped, then delivered, then mark paid after the courier collects payment. Cancelling is only available before shipping. Failed delivery is final and restores returned items to stock.</p>
  <?php } ?>
</section>
<?php include("../includes/footer.php"); ?>
