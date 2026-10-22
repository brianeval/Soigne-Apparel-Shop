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
        "SELECT o.order_id, o.user_id, o.status, o.shipping_address, o.created_at, u.username
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
        $status = $_POST['status'] ?? '';
        $shipping_address = trim(is_string($_POST['shipping_address'] ?? null) ? $_POST['shipping_address'] : '');
        $allowed_statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed_statuses, true)) $errors[] = 'Choose a valid order status.';
        if ($shipping_address === '' || mb_strlen($shipping_address) > 255) $errors[] = 'Enter a shipping address up to 255 characters.';

        if (!$errors) {
            mysqli_begin_transaction($conn);
            try {
                $lock_stmt = mysqli_prepare($conn, "SELECT status FROM orders WHERE order_id = ? FOR UPDATE");
                mysqli_stmt_bind_param($lock_stmt, 'i', $order_id);
                mysqli_stmt_execute($lock_stmt);
                $locked_order = mysqli_fetch_assoc(mysqli_stmt_get_result($lock_stmt));
                if (!$locked_order) throw new RuntimeException('Order not found.');
                $old_status = $locked_order['status'];

                if ($old_status !== 'cancelled' && $status === 'cancelled') {
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
                } elseif ($old_status === 'cancelled' && $status !== 'cancelled') {
                    $items_stmt = mysqli_prepare($conn, "SELECT variant_id, quantity FROM order_items WHERE order_id = ? FOR UPDATE");
                    mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
                    mysqli_stmt_execute($items_stmt);
                    $items = mysqli_fetch_all(mysqli_stmt_get_result($items_stmt), MYSQLI_ASSOC);
                    $stock_stmt = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock - ? WHERE variant_id = ? AND stock >= ?");
                    foreach ($items as $item) {
                        $quantity = (int)$item['quantity'];
                        $variant_id = (int)$item['variant_id'];
                        mysqli_stmt_bind_param($stock_stmt, 'iii', $quantity, $variant_id, $quantity);
                        mysqli_stmt_execute($stock_stmt);
                        if (mysqli_stmt_affected_rows($stock_stmt) !== 1) {
                            throw new RuntimeException('There is not enough inventory to reopen this cancelled order.');
                        }
                    }
                }

                $update_stmt = mysqli_prepare($conn, "UPDATE orders SET status = ?, shipping_address = ? WHERE order_id = ?");
                mysqli_stmt_bind_param($update_stmt, 'ssi', $status, $shipping_address, $order_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_commit($conn);
                admin_set_flash('success', 'Order updated.');
                header('Location: ' . BASE_URL . 'admin/manage-orders/');
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
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">ORDER MANAGEMENT</p><h2>Update order</h2></div><span class="status-pill status-<?php echo admin_escape($order['status']); ?>"><?php echo admin_escape(ucfirst($order['status'])); ?></span></div>
  <form method="post" class="manage-form">
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <label>Order status<select name="status"><?php foreach (['pending', 'paid', 'shipped', 'delivered', 'cancelled'] as $status_option) { ?><option value="<?php echo $status_option; ?>" <?php echo ($order['status'] === $status_option) ? 'selected' : ''; ?>><?php echo ucfirst($status_option); ?></option><?php } ?></select></label>
    <label>Shipping address<textarea name="shipping_address" maxlength="255" rows="3" required><?php echo admin_escape($_POST['shipping_address'] ?? $order['shipping_address']); ?></textarea></label>
    <div class="manage-form-buttons"><button class="admin-button" type="submit">Save order changes</button><?php if (in_array($order['status'], ['pending', 'cancelled'], true)) { ?><a class="manage-small-button is-danger" href="<?php echo BASE_URL; ?>admin/manage-orders/">Delete is available in the order list</a><?php } ?></div>
  </form>
  <p class="manage-help">Cancelling an order returns its quantities to stock. Reopening a cancelled order reserves stock again.</p>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
