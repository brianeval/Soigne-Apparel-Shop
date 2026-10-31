<?php
require_once "../includes/bootstrap.php";

$errors = [];
$order_view = $_POST['order_view'] ?? $_GET['order_view'] ?? 'ongoing';
if (!in_array($order_view, ['ongoing', 'history'], true)) {
    $order_view = 'ongoing';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $order = $order_id > 0 ? mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT fulfillment_status, payment_status FROM orders WHERE order_id = $order_id"
    )) : null;

    if (!$order) {
        $errors[] = 'Order not found.';
    } elseif ($order['payment_status'] !== 'unpaid'
        || !in_array($order['fulfillment_status'], ['pending', 'cancelled', 'failed_delivery'], true)) {
        $errors[] = 'Only unpaid pending, cancelled, or failed-delivery orders can be deleted.';
    } else {
        try {
            if ($order['fulfillment_status'] === 'pending') {
                $items = mysqli_fetch_all(mysqli_query($conn,
                    "SELECT variant_id, quantity FROM order_items WHERE order_id = $order_id"
                ), MYSQLI_ASSOC);
                foreach ($items as $item) {
                    $quantity = (int)$item['quantity'];
                    $variant_id = (int)$item['variant_id'];
                    mysqli_query($conn, "UPDATE product_variants SET stock = stock + $quantity WHERE variant_id = $variant_id");
                }
            }
            mysqli_query($conn, "DELETE FROM order_items WHERE order_id = $order_id");
            mysqli_query($conn, "DELETE FROM orders WHERE order_id = $order_id");
            admin_set_flash('success', 'Order deleted.');
            header('Location: ' . BASE_URL . 'admin/manage-orders/?order_view=' . $order_view);
            exit;
        } catch (mysqli_sql_exception $exception) {
            $errors[] = 'Could not delete this order because it is still referenced by store data.';
        }
    }
}

$ongoing_order_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM orders
     WHERE payment_status = 'unpaid' AND fulfillment_status IN ('pending', 'shipped')"
))['total'];
$history_order_count = (int)mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM orders
     WHERE payment_status = 'paid' OR fulfillment_status IN ('delivered', 'cancelled', 'failed_delivery')"
))['total'];

$ongoing_orders = mysqli_fetch_all(mysqli_query($conn,
    "SELECT o.order_id, o.fulfillment_status, o.payment_status, o.created_at, o.shipping_address, u.username,
            COALESCE(SUM(details.quantity), 0) AS item_count,
            COALESCE(SUM(details.line_subtotal), 0) AS order_total
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN order_transaction_details AS details ON details.order_id = o.order_id
     WHERE o.payment_status = 'unpaid' AND o.fulfillment_status IN ('pending', 'shipped')
     GROUP BY o.order_id, o.fulfillment_status, o.payment_status, o.created_at, o.shipping_address, u.username
     ORDER BY o.created_at DESC, o.order_id DESC"
), MYSQLI_ASSOC);

$history_search = trim($_GET['history_search'] ?? '');
$history_where = '';
if ($history_search !== '') {
    $q = mysqli_real_escape_string($conn, $history_search);
    $history_where = " AND (u.username LIKE '%$q%' OR CAST(o.order_id AS CHAR) LIKE '%$q%')";
}
$history_orders = mysqli_fetch_all(mysqli_query($conn,
    "SELECT o.order_id, o.fulfillment_status, o.payment_status, o.created_at, o.shipping_address, u.username,
            COALESCE(SUM(details.quantity), 0) AS item_count,
            COALESCE(SUM(details.line_subtotal), 0) AS order_total
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN order_transaction_details AS details ON details.order_id = o.order_id
     WHERE (o.payment_status = 'paid' OR o.fulfillment_status IN ('delivered', 'cancelled', 'failed_delivery'))
       $history_where
     GROUP BY o.order_id, o.fulfillment_status, o.payment_status, o.created_at, o.shipping_address, u.username
     ORDER BY o.created_at DESC, o.order_id DESC"
), MYSQLI_ASSOC);

$flash = admin_take_flash();
$admin_page_title = 'Manage Orders';
$admin_active_page = 'orders';
include "../includes/header.php";
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">SALES</p><h1>Manage Orders</h1><p>Review ongoing orders, manage fulfillment, and view order history.</p></div>
</section>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>

<div class="admin-panel-tabs" role="radiogroup" aria-label="Order panels">
  <input type="radio" name="order_view" id="order-view-ongoing" value="ongoing" <?php echo $order_view === 'ongoing' ? 'checked' : ''; ?>>
  <label for="order-view-ongoing">Ongoing Orders</label>
  <input type="radio" name="order_view" id="order-view-history" value="history" <?php echo $order_view === 'history' ? 'checked' : ''; ?>>
  <label for="order-view-history">Order History</label>

<section class="manage-panel manage-list-panel order-ongoing-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">ACTIVE FULFILLMENT</p><h2>Ongoing orders <span class="manage-count"><?php echo $ongoing_order_count; ?></span></h2></div></div>
  <?php if ($ongoing_orders) { ?><div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody><?php foreach ($ongoing_orders as $order) { ?><tr>
      <td class="order-number">#<?php echo (int)$order['order_id']; ?></td>
      <td><?php echo admin_escape($order['username']); ?></td>
      <td><?php echo admin_escape(date('M j, Y', strtotime($order['created_at']))); ?></td>
      <td><?php echo (int)$order['item_count']; ?></td>
      <td><?php echo peso((float)$order['order_total']); ?></td>
      <td><span class="status-pill status-<?php echo admin_escape($order['fulfillment_status']); ?>"><?php echo admin_escape(ucwords(str_replace('_', ' ', $order['fulfillment_status']))); ?></span> <span class="status-pill status-<?php echo admin_escape($order['payment_status']); ?>"><?php echo admin_escape(ucfirst($order['payment_status'])); ?></span></td>
      <td><div class="manage-row-actions">
        <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-orders/edit.php?id=<?php echo (int)$order['order_id']; ?>">View / Edit</a>
        <?php if (in_array($order['fulfillment_status'], ['pending', 'cancelled'], true)) { ?>
          <form method="post">
            <input type="hidden" name="order_view" value="ongoing">
            <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
            <button class="manage-small-button is-danger" type="submit">Delete</button>
          </form>
        <?php } ?>
      </div></td>
    </tr><?php } ?></tbody>
  </table></div><?php } else { ?><p class="manage-empty">No ongoing orders.</p><?php } ?>
</section>

<section class="manage-panel manage-list-panel order-history-panel">
  <div class="manage-panel-heading">
    <div><p class="admin-eyebrow">COMPLETED &amp; CANCELLED</p><h2>Order history <span class="manage-count"><?php echo $history_order_count; ?></span></h2></div>
    <form method="get" class="inventory-history-search">
      <input type="hidden" name="order_view" value="history">
      <label class="visually-hidden" for="order-history-search">Search customer or order ID</label>
      <input id="order-history-search" type="search" name="history_search" value="<?php echo admin_escape($history_search); ?>" placeholder="Search customer or order ID">
      <button class="admin-button admin-button-light" type="submit">Search</button>
    </form>
  </div>
  <?php if ($history_orders) { ?><div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody><?php foreach ($history_orders as $order) { ?><tr>
      <td class="order-number">#<?php echo (int)$order['order_id']; ?></td>
      <td><?php echo admin_escape($order['username']); ?></td>
      <td><?php echo admin_escape(date('M j, Y', strtotime($order['created_at']))); ?></td>
      <td><?php echo (int)$order['item_count']; ?></td>
      <td><?php echo peso((float)$order['order_total']); ?></td>
      <td><span class="status-pill status-<?php echo admin_escape($order['fulfillment_status']); ?>"><?php echo admin_escape(ucwords(str_replace('_', ' ', $order['fulfillment_status']))); ?></span> <span class="status-pill status-<?php echo admin_escape($order['payment_status']); ?>"><?php echo admin_escape(ucfirst($order['payment_status'])); ?></span></td>
      <td><div class="manage-row-actions">
        <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-orders/edit.php?id=<?php echo (int)$order['order_id']; ?>">View</a>
        <?php if ($order['fulfillment_status'] === 'delivered' && $order['payment_status'] === 'paid') { ?>
          <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-orders/receipt.php?id=<?php echo (int)$order['order_id']; ?>" target="_blank" rel="noopener">View receipt</a>
        <?php } ?>
        <?php if (in_array($order['fulfillment_status'], ['cancelled', 'failed_delivery'], true)) { ?>
          <form method="post">
            <input type="hidden" name="order_view" value="history">
            <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
            <button class="manage-small-button is-danger" type="submit">Delete</button>
          </form>
        <?php } ?>
      </div></td>
    </tr><?php } ?></tbody>
  </table></div><?php } else { ?><p class="manage-empty">No orders in history.</p><?php } ?>
</section>
</div>
<?php include "../includes/footer.php"; ?>
