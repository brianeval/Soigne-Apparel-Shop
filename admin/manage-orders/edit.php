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
include('orders_update.php');

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
