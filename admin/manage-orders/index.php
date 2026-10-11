<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $product_ids = $_POST['product_ids'] ?? [];
        $variant_ids = $_POST['variant_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $shipping_address = trim(is_string($_POST['shipping_address'] ?? null) ? $_POST['shipping_address'] : '');

        if ($user_id === false || $user_id < 1) $errors[] = 'Choose a customer.';
        if (!is_array($product_ids) || !is_array($variant_ids) || !is_array($quantities)
            || !$product_ids || count($product_ids) !== count($variant_ids)
            || count($variant_ids) !== count($quantities)) {
            $errors[] = 'Choose at least one product and enter its quantity.';
        }
        if ($shipping_address === '' || mb_strlen($shipping_address) > 255) $errors[] = 'Enter a shipping address up to 255 characters.';

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
                $customer_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE user_id = ? AND role = 'customer' AND status = 'active' FOR UPDATE");
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
                $status_stmt = mysqli_prepare($conn, "SELECT status FROM orders WHERE order_id = ? FOR UPDATE");
                mysqli_stmt_bind_param($status_stmt, 'i', $order_id);
                mysqli_stmt_execute($status_stmt);
                $order = mysqli_fetch_assoc(mysqli_stmt_get_result($status_stmt));
                if (!$order) {
                    throw new RuntimeException('Order not found.');
                }
                if (!in_array($order['status'], ['pending', 'cancelled'], true)) {
                    throw new RuntimeException('Only pending or cancelled orders can be deleted.');
                }

                if ($order['status'] === 'pending') {
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
                $delete_stmt = mysqli_prepare($conn, "DELETE FROM orders WHERE order_id = ? AND status IN ('pending', 'cancelled')");
                mysqli_stmt_bind_param($delete_stmt, 'i', $order_id);
                mysqli_stmt_execute($delete_stmt);
                if (mysqli_stmt_affected_rows($delete_stmt) !== 1) {
                    throw new RuntimeException('Order status changed. Only pending or cancelled orders can be deleted.');
                }
                mysqli_commit($conn);
                admin_set_flash('success', 'Order deleted.');
                header('Location: ' . BASE_URL . 'admin/manage-orders/');
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

$customers = mysqli_fetch_all(mysqli_query($conn, "SELECT user_id, username FROM users WHERE role = 'customer' AND status = 'active' ORDER BY username"), MYSQLI_ASSOC);
$variants = mysqli_fetch_all(mysqli_query($conn,
    "SELECT v.variant_id, v.size, v.stock, p.product_id, p.name AS product_name, pc.color
     FROM product_variants v
     JOIN product_colors pc ON pc.product_color_id = v.product_color_id
     JOIN products p ON p.product_id = pc.product_id
     ORDER BY p.name, pc.color, v.size"
), MYSQLI_ASSOC);
$products = [];
foreach ($variants as $variant) {
    $products[(int)$variant['product_id']] = $variant['product_name'];
}
$orders_result = mysqli_query($conn,
    "SELECT o.order_id, o.status, o.created_at, o.shipping_address, u.username,
            COALESCE(SUM(oi.quantity), 0) AS item_count,
            COALESCE(SUM(oi.quantity * oi.price), 0) AS order_total
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN order_items oi ON oi.order_id = o.order_id
     GROUP BY o.order_id, o.status, o.created_at, o.shipping_address, u.username
     ORDER BY o.created_at DESC, o.order_id DESC"
);
$orders = mysqli_fetch_all($orders_result, MYSQLI_ASSOC);
$flash = admin_take_flash();
$admin_page_title = 'Manage Orders';
$admin_active_page = 'orders';
include __DIR__ . '/../includes/header.php';
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">SALES</p><h1>Manage Orders</h1><p>Create orders, review details, and maintain order status.</p></div>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>

<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">MANUAL ORDER</p><h2>Create an order</h2></div></div>
  <?php if (!$customers || !$variants) { ?><p class="manage-empty">An active customer and at least one product size are required to create an order.</p><?php } else { ?>
    <form method="post" class="manage-form manage-product-create">
      <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
      <input type="hidden" name="action" value="create">
      <div class="order-customer-details">
        <label>Customer<select name="user_id" required><option value="">Choose a customer</option><?php foreach ($customers as $customer) { ?><option value="<?php echo (int)$customer['user_id']; ?>"><?php echo admin_escape($customer['username']); ?></option><?php } ?></select></label>
        <label>Shipping address<textarea name="shipping_address" maxlength="255" rows="3" required></textarea></label>
      </div>
      <div class="manage-form-wide">
        <strong>Products</strong>
        <div id="order-product-items">
          <div class="manage-form order-product-row">
            <label>Product<select name="product_ids[]" required><option value="">Choose a product</option><?php foreach ($products as $available_product_id => $product_name) { ?><option value="<?php echo $available_product_id; ?>"><?php echo admin_escape($product_name); ?></option><?php } ?></select></label>
            <label>Color / size<select name="variant_ids[]" required disabled><option value="">Choose a product first</option><?php foreach ($variants as $variant) { ?><option value="<?php echo (int)$variant['variant_id']; ?>" data-product-id="<?php echo (int)$variant['product_id']; ?>"><?php echo admin_escape($variant['color'] . ' · ' . $variant['size'] . ' (' . $variant['stock'] . ' in stock)'); ?></option><?php } ?></select></label>
            <label>Quantity<input type="number" name="quantities[]" min="1" max="1000" required value="1"></label>
            <button class="manage-small-button is-danger order-remove-item" type="button" aria-label="Remove product">Remove</button>
          </div>
        </div>
      </div>
      <div class="manage-form-wide order-create-actions">
        <button class="admin-button" type="submit">Create pending order</button>
        <button class="manage-small-button" id="order-add-item" type="button">Add another product</button>
      </div>
    </form>
    <template id="order-product-template">
      <div class="manage-form order-product-row">
        <label>Product<select name="product_ids[]" required><option value="">Choose a product</option><?php foreach ($products as $available_product_id => $product_name) { ?><option value="<?php echo $available_product_id; ?>"><?php echo admin_escape($product_name); ?></option><?php } ?></select></label>
        <label>Color / size<select name="variant_ids[]" required disabled><option value="">Choose a product first</option><?php foreach ($variants as $variant) { ?><option value="<?php echo (int)$variant['variant_id']; ?>" data-product-id="<?php echo (int)$variant['product_id']; ?>"><?php echo admin_escape($variant['color'] . ' · ' . $variant['size'] . ' (' . $variant['stock'] . ' in stock)'); ?></option><?php } ?></select></label>
        <label>Quantity<input type="number" name="quantities[]" min="1" max="1000" required value="1"></label>
        <button class="manage-small-button is-danger order-remove-item" type="button" aria-label="Remove product">Remove</button>
      </div>
    </template>
    <script>
      const orderProductItems = document.getElementById('order-product-items');
      const orderProductTemplate = document.getElementById('order-product-template');
      const updateAvailableSizes = (row) => {
        const productSelect = row.querySelector('[name="product_ids[]"]');
        const sizeSelect = row.querySelector('[name="variant_ids[]"]');
        sizeSelect.value = '';
        sizeSelect.disabled = !productSelect.value;
        sizeSelect.options[0].textContent = productSelect.value ? 'Choose a color and size' : 'Choose a product first';
        Array.from(sizeSelect.options).slice(1).forEach((option) => {
          const matchesProduct = option.dataset.productId === productSelect.value;
          option.hidden = !matchesProduct;
          option.disabled = !matchesProduct;
        });
      };
      document.getElementById('order-add-item').addEventListener('click', () => {
        orderProductItems.append(orderProductTemplate.content.cloneNode(true));
      });
      orderProductItems.addEventListener('change', (event) => {
        if (event.target.matches('[name="product_ids[]"]')) {
          updateAvailableSizes(event.target.closest('.order-product-row'));
        }
      });
      orderProductItems.addEventListener('click', (event) => {
        if (event.target.closest('.order-remove-item') && orderProductItems.children.length > 1) {
          event.target.closest('.order-product-row').remove();
        }
      });
    </script>
  <?php } ?>
</section>

<section class="manage-panel manage-list-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">ORDER HISTORY</p><h2>All orders <span class="manage-count"><?php echo count($orders); ?></span></h2></div></div>
  <?php if ($orders) { ?><div class="admin-table-wrap"><table class="admin-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody><?php foreach ($orders as $order) { ?><tr>
      <td class="order-number">#<?php echo (int)$order['order_id']; ?></td>
      <td><?php echo admin_escape($order['username']); ?></td>
      <td><?php echo admin_escape(date('M j, Y', strtotime($order['created_at']))); ?></td>
      <td><?php echo (int)$order['item_count']; ?></td>
      <td><?php echo peso((float)$order['order_total']); ?></td>
      <td><span class="status-pill status-<?php echo admin_escape($order['status']); ?>"><?php echo admin_escape(ucfirst($order['status'])); ?></span></td>
      <td><div class="manage-row-actions">
        <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-orders/edit.php?id=<?php echo (int)$order['order_id']; ?>">View / Edit</a>
        <?php if (in_array($order['status'], ['pending', 'cancelled'], true)) { ?>
          <form method="post" onsubmit="return confirm('Permanently delete order #<?php echo (int)$order['order_id']; ?>?<?php echo $order['status'] === 'pending' ? ' Its reserved stock will be returned.' : ''; ?>');">
            <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
            <button class="manage-small-button is-danger" type="submit">Delete</button>
          </form>
        <?php } ?>
      </div></td>
    </tr><?php } ?></tbody>
  </table></div><?php } else { ?><p class="manage-empty">No orders yet.</p><?php } ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
