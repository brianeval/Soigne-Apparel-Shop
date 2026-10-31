<?php
require_once("../includes/bootstrap.php");

$errors = [];
$requested_order_view = is_string($_POST['order_view'] ?? null)
    ? $_POST['order_view']
    : (is_string($_GET['order_view'] ?? null) ? $_GET['order_view'] : '');
$order_view = in_array($requested_order_view, ['ongoing', 'history'], true)
    ? $requested_order_view
    : (($_POST['action'] ?? '') === 'delete' && is_string($_POST['order_view'] ?? null) ? $_POST['order_view'] : 'ongoing');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $product_ids = $_POST['product_ids'] ?? [];
        $variant_ids = $_POST['variant_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $shipping_address = trim(is_string($_POST['shipping_address'] ?? null) ? $_POST['shipping_address'] : '');

        if ($user_id === false || $user_id < 1) {
            $errors[] = 'Choose a customer.';
        }
        if (!is_array($product_ids) || !is_array($variant_ids) || !is_array($quantities)
            || !$product_ids || count($product_ids) !== count($variant_ids)
            || count($variant_ids) !== count($quantities)) {
            $errors[] = 'Choose at least one product and enter its quantity.';
        }
        if ($shipping_address === '' || mb_strlen($shipping_address) > 255) {
            $errors[] = 'Enter a shipping address up to 255 characters.';
        }

        $order_items = [];
        $selected_products = [];
        if (!$errors) {
            foreach ($variant_ids as $index => $raw_variant_id) {
                $product_id = filter_var($product_ids[$index] ?? null, FILTER_VALIDATE_INT);
                $variant_id = filter_var($raw_variant_id, FILTER_VALIDATE_INT);
                $quantity = filter_var($quantities[$index] ?? null, FILTER_VALIDATE_INT);
                if ($product_id === false || $product_id < 1 || $variant_id === false || $variant_id < 1) {
                    $errors[] = 'Choose a product and size for every item.';
                    break;
                }
                if ($quantity === false || $quantity < 1 || $quantity > 1000) {
                    $errors[] = 'Each quantity must be from 1 to 1,000.';
                    break;
                }
                if (isset($selected_products[$variant_id]) && $selected_products[$variant_id] !== $product_id) {
                    $errors[] = 'Each product size must match its selected product.';
                    break;
                }
                $order_items[$variant_id] = ($order_items[$variant_id] ?? 0) + $quantity;
                $selected_products[$variant_id] = $product_id;
                if ($order_items[$variant_id] > 1000) {
                    $errors[] = 'The total quantity for each product size cannot exceed 1,000.';
                    break;
                }
            }
            ksort($order_items, SORT_NUMERIC);
        }

        if (!$errors) {
            mysqli_begin_transaction($conn);
            try {
                $customer_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id = ? AND role IN ('customer', 'vip') AND status = 'active' FOR UPDATE");
                mysqli_stmt_bind_param($customer_stmt, 'i', $user_id);
                mysqli_stmt_execute($customer_stmt);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($customer_stmt))) {
                    throw new RuntimeException('Choose an active customer account.');
                }

                $variant_stmt = mysqli_prepare($conn,
                    "SELECT v.stock, p.price, p.product_id
                     FROM product_variants v
                     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                     JOIN products p ON p.product_id = pc.product_id
                     WHERE v.variant_id = ?
                     FOR UPDATE"
                );
                $locked_items = [];
                foreach ($order_items as $variant_id => $quantity) {
                    mysqli_stmt_bind_param($variant_stmt, 'i', $variant_id);
                    mysqli_stmt_execute($variant_stmt);
                    $variant = mysqli_fetch_assoc(mysqli_stmt_get_result($variant_stmt));
                    if (!$variant || (int)$variant['product_id'] !== $selected_products[$variant_id]) {
                        throw new RuntimeException('The selected size does not belong to the chosen product.');
                    }
                    if ((int)$variant['stock'] < $quantity) {
                        throw new RuntimeException('A selected size is unavailable in the requested quantity.');
                    }
                    $locked_items[] = [
                        'variant_id' => (int)$variant_id,
                        'quantity' => $quantity,
                        'price' => (float)$variant['price']
                    ];
                }

                $order_stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, shipping_address) VALUES (?, ?)");
                mysqli_stmt_bind_param($order_stmt, 'is', $user_id, $shipping_address);
                mysqli_stmt_execute($order_stmt);
                $order_id = mysqli_insert_id($conn);

                $stock_stmt = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock - ? WHERE variant_id = ? AND stock >= ?");
                $item_stmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, variant_id, quantity, price) VALUES (?, ?, ?, ?)");
                foreach ($locked_items as $item) {
                    $variant_id = $item['variant_id'];
                    $quantity = $item['quantity'];
                    $price = $item['price'];
                    mysqli_stmt_bind_param($item_stmt, 'iiid', $order_id, $variant_id, $quantity, $price);
                    mysqli_stmt_execute($item_stmt);

                    mysqli_stmt_bind_param($stock_stmt, 'iii', $quantity, $variant_id, $quantity);
                    mysqli_stmt_execute($stock_stmt);
                    if (mysqli_stmt_affected_rows($stock_stmt) !== 1) {
                        throw new RuntimeException('Stock changed before the order could be created.');
                    }
                }
                mysqli_commit($conn);
                admin_set_flash('success', 'Order created with all selected products and inventory updated.');
                header('Location: ' . BASE_URL . 'admin/manage-orders/edit.php?id=' . $order_id);
                exit;
            } catch (RuntimeException $exception) {
                mysqli_rollback($conn);
                $errors[] = $exception->getMessage();
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                $errors[] = 'Could not create the order. Please check the details and try again.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        if ($order_id === false || $order_id < 1) {
            $errors[] = 'Choose a valid order.';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $status_stmt = mysqli_prepare($conn, "SELECT fulfillment_status, payment_status FROM orders WHERE order_id = ? FOR UPDATE");
                mysqli_stmt_bind_param($status_stmt, 'i', $order_id);
                mysqli_stmt_execute($status_stmt);
                $order = mysqli_fetch_assoc(mysqli_stmt_get_result($status_stmt));
                if (!$order) {
                    throw new RuntimeException('Order not found.');
                }
                if ($order['payment_status'] !== 'unpaid'
                    || !in_array($order['fulfillment_status'], ['pending', 'cancelled', 'failed_delivery'], true)) {
                    throw new RuntimeException('Only unpaid pending, cancelled, or failed-delivery orders can be deleted.');
                }

                if ($order['fulfillment_status'] === 'pending') {
                    $order_items_stmt = mysqli_prepare($conn, "SELECT variant_id, quantity FROM order_items WHERE order_id = ? FOR UPDATE");
                    mysqli_stmt_bind_param($order_items_stmt, 'i', $order_id);
                    mysqli_stmt_execute($order_items_stmt);
                    $order_items = mysqli_fetch_all(mysqli_stmt_get_result($order_items_stmt), MYSQLI_ASSOC);
                    $restore_stock_stmt = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?");
                    foreach ($order_items as $item) {
                        $quantity = (int)$item['quantity'];
                        $variant_id = (int)$item['variant_id'];
                        mysqli_stmt_bind_param($restore_stock_stmt, 'ii', $quantity, $variant_id);
                        mysqli_stmt_execute($restore_stock_stmt);
                    }
                }

                $items_stmt = mysqli_prepare($conn, "DELETE FROM order_items WHERE order_id = ?");
                mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
                mysqli_stmt_execute($items_stmt);
                $delete_stmt = mysqli_prepare($conn, "DELETE FROM orders WHERE order_id = ? AND fulfillment_status IN ('pending', 'cancelled', 'failed_delivery') AND payment_status = 'unpaid'");
                mysqli_stmt_bind_param($delete_stmt, 'i', $order_id);
                mysqli_stmt_execute($delete_stmt);
                if (mysqli_stmt_affected_rows($delete_stmt) !== 1) {
                    throw new RuntimeException('Order status changed. Only unpaid pending, cancelled, or failed-delivery orders can be deleted.');
                }
                mysqli_commit($conn);
                admin_set_flash('success', 'Order deleted.');
                header('Location: ' . BASE_URL . 'admin/manage-orders/?order_view=' . $order_view);
                exit;
            } catch (RuntimeException $exception) {
                mysqli_rollback($conn);
                $errors[] = $exception->getMessage();
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                $errors[] = 'Could not delete this order because it is still referenced by store data.';
            }
        }
    } else {
        $errors[] = 'That order action is not valid.';
    }
}

$ongoing_order_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE payment_status = 'unpaid' AND fulfillment_status IN ('pending', 'shipped')"))['total'];
$history_order_count = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders WHERE payment_status = 'paid' OR fulfillment_status IN ('delivered', 'cancelled', 'failed_delivery')"))['total'];
$history_search = mb_substr(trim(is_string($_GET['history_search'] ?? null) ? $_GET['history_search'] : ''), 0, 100);
$ongoing_orders_result = mysqli_query($conn,
    "SELECT o.order_id, o.fulfillment_status, o.payment_status, o.created_at, o.shipping_address, u.username,
            COALESCE(SUM(details.quantity), 0) AS item_count,
            COALESCE(SUM(details.line_subtotal), 0) AS order_total
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN order_transaction_details AS details ON details.order_id = o.order_id
     WHERE o.payment_status = 'unpaid' AND o.fulfillment_status IN ('pending', 'shipped')
     GROUP BY o.order_id, o.fulfillment_status, o.payment_status, o.created_at, o.shipping_address, u.username
     ORDER BY o.created_at DESC, o.order_id DESC"
);
$ongoing_orders = mysqli_fetch_all($ongoing_orders_result, MYSQLI_ASSOC);
$history_sql =
    "SELECT o.order_id, o.fulfillment_status, o.payment_status, o.created_at, o.shipping_address, u.username,
            COALESCE(SUM(details.quantity), 0) AS item_count,
            COALESCE(SUM(details.line_subtotal), 0) AS order_total
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN order_transaction_details AS details ON details.order_id = o.order_id
     WHERE (o.payment_status = 'paid' OR o.fulfillment_status IN ('delivered', 'cancelled', 'failed_delivery'))";
if ($history_search !== '') {
    $history_sql .= ' AND (u.username LIKE ? OR CAST(o.order_id AS CHAR) LIKE ?)';
}
$history_sql .=
    " GROUP BY o.order_id, o.fulfillment_status, o.payment_status, o.created_at, o.shipping_address, u.username
     ORDER BY o.created_at DESC, o.order_id DESC";
$history_orders_stmt = mysqli_prepare($conn, $history_sql);
if ($history_search !== '') {
    $history_search_value = '%' . $history_search . '%';
    mysqli_stmt_bind_param($history_orders_stmt, 'ss', $history_search_value, $history_search_value);
}
mysqli_stmt_execute($history_orders_stmt);
$history_orders = mysqli_fetch_all(mysqli_stmt_get_result($history_orders_stmt), MYSQLI_ASSOC);
$flash = admin_take_flash();
$admin_page_title = 'Manage Orders';
$admin_active_page = 'orders';
include("../includes/header.php");
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
            <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="order_view" value="ongoing">
            <input type="hidden" name="action" value="delete">
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
            <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="order_view" value="history">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
            <button class="manage-small-button is-danger" type="submit">Delete</button>
          </form>
        <?php } ?>
      </div></td>
    </tr><?php } ?></tbody>
  </table></div><?php } else { ?><p class="manage-empty">No orders in history.</p><?php } ?>
</section>
</div>
<?php include("../includes/footer.php"); ?>
