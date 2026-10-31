<?php
session_start();
include_once("../includes/config.php");
require_once("../includes/customer_auth.php");

require_active_customer($conn, BASE_URL . 'orders/orders.php');

$user_id = (int)$_SESSION['user_id'];
$orders = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);

    if ($order_id === false || $order_id < 1 || !in_array($action, ['edit_order', 'cancel_order'], true)) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'That order action is invalid.'];
    } else {
        try {
            mysqli_begin_transaction($conn);

            // Lock order
            $locked_order_stmt = mysqli_prepare($conn, "SELECT order_id, fulfillment_status FROM orders WHERE order_id = ? AND user_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($locked_order_stmt, 'ii', $order_id, $user_id);
            mysqli_stmt_execute($locked_order_stmt);
            $locked_order = mysqli_fetch_assoc(mysqli_stmt_get_result($locked_order_stmt));

            if (!$locked_order || $locked_order['fulfillment_status'] !== 'pending') {
                mysqli_rollback($conn);
                $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Only pending orders can be changed or cancelled.'];
            } elseif ($action === 'cancel_order') {
                // Load order items
                $order_items_stmt = mysqli_prepare($conn, "SELECT variant_id, quantity FROM order_items WHERE order_id = ? FOR UPDATE");
                mysqli_stmt_bind_param($order_items_stmt, 'i', $order_id);
                mysqli_stmt_execute($order_items_stmt);
                $order_items = mysqli_fetch_all(mysqli_stmt_get_result($order_items_stmt), MYSQLI_ASSOC);

                // Restore stock
                $stock_stmt = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?");
                foreach ($order_items as $item) {
                    $quantity = (int)$item['quantity'];
                    $variant_id = (int)$item['variant_id'];
                    mysqli_stmt_bind_param($stock_stmt, 'ii', $quantity, $variant_id);
                    mysqli_stmt_execute($stock_stmt);
                }

                // Cancel order
                $cancel_order_stmt = mysqli_prepare($conn, "UPDATE orders SET fulfillment_status = 'cancelled' WHERE order_id = ? AND user_id = ? AND fulfillment_status = 'pending' AND payment_status = 'unpaid'");
                mysqli_stmt_bind_param($cancel_order_stmt, 'ii', $order_id, $user_id);
                mysqli_stmt_execute($cancel_order_stmt);
                if (mysqli_stmt_affected_rows($cancel_order_stmt) !== 1) {
                    throw new mysqli_sql_exception('Order status changed during cancellation.');
                }

                mysqli_commit($conn);
                $_SESSION['orders_flash'] = ['type' => 'success', 'text' => 'Your order has been cancelled.'];
            } else {
                $submitted_items = $_POST['items'] ?? null;
                if (!is_array($submitted_items)) {
                    mysqli_rollback($conn);
                    $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Order changes were invalid. Please try again.'];
                } else {
                    // Load current order items
                    $current_items_stmt = mysqli_prepare($conn,
                        "SELECT oi.order_item_id, oi.variant_id, oi.quantity, pc.product_id
                         FROM order_items oi
                         JOIN product_variants v ON v.variant_id = oi.variant_id
                         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                         WHERE oi.order_id = ?
                         FOR UPDATE"
                    );
                    mysqli_stmt_bind_param($current_items_stmt, 'i', $order_id);
                    mysqli_stmt_execute($current_items_stmt);
                    $current_items = mysqli_fetch_all(mysqli_stmt_get_result($current_items_stmt), MYSQLI_ASSOC);

                    $current_by_id = [];
                    foreach ($current_items as $current_item) {
                        $current_by_id[(int)$current_item['order_item_id']] = $current_item;
                    }

                    $changes = [];
                    $seen_variants = [];
                    $valid = count($submitted_items) === count($current_by_id);
                    foreach ($submitted_items as $item_id => $values) {
                        $item_id = filter_var($item_id, FILTER_VALIDATE_INT);
                        if ($item_id === false || !isset($current_by_id[$item_id]) || !is_array($values)) {
                            $valid = false;
                            break;
                        }

                        $requested_variant = filter_var($values['variant_id'] ?? null, FILTER_VALIDATE_INT);
                        $requested_quantity = filter_var($values['quantity'] ?? null, FILTER_VALIDATE_INT);
                        if ($requested_variant === false || $requested_variant < 1
                            || $requested_quantity === false || $requested_quantity < 1 || $requested_quantity > 1000
                            || isset($seen_variants[$requested_variant])) {
                            $valid = false;
                            break;
                        }
                        $seen_variants[$requested_variant] = true;

                        $product_id = (int)$current_by_id[$item_id]['product_id'];
                        // Validate requested variant
                        $variant_stmt = mysqli_prepare($conn,
                            "SELECT v.variant_id
                             FROM product_variants v
                             JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                             WHERE v.variant_id = ? AND pc.product_id = ?"
                        );
                        mysqli_stmt_bind_param($variant_stmt, 'ii', $requested_variant, $product_id);
                        mysqli_stmt_execute($variant_stmt);
                        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($variant_stmt))) {
                            $valid = false;
                            break;
                        }

                        $changes[] = [
                            'order_item_id' => (int)$item_id,
                            'old_variant_id' => (int)$current_by_id[$item_id]['variant_id'],
                            'old_quantity' => (int)$current_by_id[$item_id]['quantity'],
                            'variant_id' => (int)$requested_variant,
                            'quantity' => (int)$requested_quantity,
                        ];
                    }

                    if (!$valid) {
                        mysqli_rollback($conn);
                        $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Choose a valid size and quantity for every order item.'];
                    } else {
                        // Restore previous stock
                        $stock_add_stmt = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?");
                        foreach ($changes as $change) {
                            $old_quantity = $change['old_quantity'];
                            $old_variant_id = $change['old_variant_id'];
                            mysqli_stmt_bind_param($stock_add_stmt, 'ii', $old_quantity, $old_variant_id);
                            mysqli_stmt_execute($stock_add_stmt);
                        }

                        // Reserve replacement stock
                        $stock_take_stmt = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock - ? WHERE variant_id = ? AND stock >= ?");
                        $item_update_stmt = mysqli_prepare($conn, "UPDATE order_items SET variant_id = ?, quantity = ? WHERE order_item_id = ? AND order_id = ?");
                        $inventory_ok = true;
                        foreach ($changes as $change) {
                            $quantity = $change['quantity'];
                            $variant_id = $change['variant_id'];
                            mysqli_stmt_bind_param($stock_take_stmt, 'iii', $quantity, $variant_id, $quantity);
                            mysqli_stmt_execute($stock_take_stmt);
                            if (mysqli_stmt_affected_rows($stock_take_stmt) !== 1) {
                                $inventory_ok = false;
                                break;
                            }
                        }

                        if (!$inventory_ok) {
                            mysqli_rollback($conn);
                            $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'The requested quantity is no longer in stock. Your order was not changed.'];
                        } else {
                            foreach ($changes as $change) {
                                $variant_id = $change['variant_id'];
                                $quantity = $change['quantity'];
                                $item_id = $change['order_item_id'];
                                mysqli_stmt_bind_param($item_update_stmt, 'iiii', $variant_id, $quantity, $item_id, $order_id);
                                mysqli_stmt_execute($item_update_stmt);
                            }

                            mysqli_commit($conn);
                            $_SESSION['orders_flash'] = ['type' => 'success', 'text' => 'Your order has been updated.'];
                        }
                    }
                }
            }
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Could not process that order change. Please try again.'];
        }
    }

    header('Location: ' . BASE_URL . 'orders/orders.php');
    exit;
}

$flash = $_SESSION['orders_flash'] ?? null;
unset($_SESSION['orders_flash']);

// Customer orders
$orders_stmt = mysqli_prepare($conn,
    "SELECT o.order_id, o.fulfillment_status, o.payment_status, o.shipping_address, o.created_at,
            COALESCE(SUM(details.line_subtotal), 0) AS order_total,
            COALESCE(SUM(details.quantity), 0) AS item_count
     FROM orders o
     LEFT JOIN order_transaction_details details ON details.order_id = o.order_id
     WHERE o.user_id = ?
     GROUP BY o.order_id, o.fulfillment_status, o.payment_status, o.shipping_address, o.created_at
     ORDER BY o.order_id DESC"
);
mysqli_stmt_bind_param($orders_stmt, 'i', $user_id);
mysqli_stmt_execute($orders_stmt);
$orders = mysqli_fetch_all(mysqli_stmt_get_result($orders_stmt), MYSQLI_ASSOC);

foreach ($orders as &$order) {
    $order_id = (int)$order['order_id'];

    // Order item details
    $item_stmt = mysqli_prepare($conn,
        "SELECT details.order_item_id, details.variant_id, details.quantity,
                details.unit_price AS price, details.product_id, details.product_name,
                (SELECT pci.image_path
                 FROM product_color_images AS pci
                 WHERE pci.product_color_id = details.product_color_id
                 ORDER BY pci.sort_order, pci.image_id
                 LIMIT 1) AS image,
                details.size, details.color,
                r.review_id, r.rating AS review_rating, r.review_text, r.status AS review_status
         FROM order_transaction_details AS details
         LEFT JOIN product_reviews r ON r.product_id = details.product_id
                                   AND r.user_id = ?
         WHERE details.order_id = ?
           AND details.user_id = ?
         ORDER BY details.order_item_id ASC"
    );
    mysqli_stmt_bind_param($item_stmt, 'iii', $user_id, $order_id, $user_id);
    mysqli_stmt_execute($item_stmt);
    $order['items'] = mysqli_fetch_all(mysqli_stmt_get_result($item_stmt), MYSQLI_ASSOC);

    foreach ($order['items'] as &$item) {
        $product_id = (int)$item['product_id'];
        $current_variant_id = (int)$item['variant_id'];
        $current_quantity = (int)$item['quantity'];
        $variant_stmt = mysqli_prepare($conn,
            "SELECT v.variant_id, v.size, v.stock, pc.color
             FROM product_variants v
             JOIN product_colors pc ON pc.product_color_id = v.product_color_id
             WHERE pc.product_id = ?
             ORDER BY pc.color, v.size"
        );
        mysqli_stmt_bind_param($variant_stmt, 'i', $product_id);
        mysqli_stmt_execute($variant_stmt);
        $variants = mysqli_fetch_all(mysqli_stmt_get_result($variant_stmt), MYSQLI_ASSOC);
        foreach ($variants as &$variant) {
            $variant['available_stock'] = (int)$variant['stock'];
            if ((int)$variant['variant_id'] === $current_variant_id) {
                $variant['available_stock'] += $current_quantity;
            }
        }
        unset($variant);
        $item['variants'] = $variants;
    }
    unset($item);

    $order['order_total'] = (float)$order['order_total'];
    $order['item_count'] = (int)$order['item_count'];
}
unset($order);

$order_tabs = [
    [
        'id' => 'to-pack',
        'label' => 'To Pack',
        'statuses' => ['pending'],
        'empty_message' => 'No pending orders right now.',
    ],
    [
        'id' => 'shipped',
        'label' => 'Shipped',
        'statuses' => ['shipped'],
        'empty_message' => 'No shipped orders right now.',
    ],
    [
        'id' => 'received',
        'label' => 'Received',
        'statuses' => ['delivered'],
        'empty_message' => 'No received orders right now.',
    ],
    [
        'id' => 'failed-delivery',
        'label' => 'Failed Delivery',
        'statuses' => ['failed_delivery'],
        'empty_message' => 'No failed deliveries.',
    ],
    [
        'id' => 'review-me',
        'label' => 'Review Me',
        'statuses' => ['paid'],
        'empty_message' => 'No orders waiting for review right now.',
    ],
    [
        'id' => 'cancellations',
        'label' => 'Cancellations',
        'statuses' => ['cancelled'],
        'empty_message' => 'No cancelled orders.',
    ],
];

$page_title = 'My Orders | Soigné';
$extra_css = 'orders.css';
include_once("../includes/header.php");
?>

<main class="orders-page">
  <?php if ($flash) { ?>
    <p class="orders-message is-<?php echo $flash['type'] === 'error' ? 'error' : 'success'; ?>" role="status">
      <?php echo htmlspecialchars($flash['text']); ?>
    </p>
  <?php } ?>

  <div class="orders-layout">
    <section class="orders-main" aria-labelledby="orders-title">
      <div class="orders-header">
        <h1 id="orders-title">My Orders</h1>
      </div>

      <nav class="orders-tabs" aria-label="Order status">
        <?php foreach ($order_tabs as $tab) { ?>
          <a class="orders-tab"
                  href="#orders-panel-<?php echo htmlspecialchars($tab['id']); ?>"
                  id="orders-tab-<?php echo htmlspecialchars($tab['id']); ?>"
                  >
            <?php echo htmlspecialchars($tab['label']); ?>
          </a>
        <?php } ?>
      </nav>

      <?php foreach ($order_tabs as $tab) { ?>
        <?php
          $tab_orders = [];
          foreach ($orders as $order) {
              $fulfillment_status = strtolower((string)($order['fulfillment_status'] ?? 'pending'));
              $payment_status = strtolower((string)($order['payment_status'] ?? 'unpaid'));
              if ($tab['id'] === 'review-me') {
                  $show_order = $payment_status === 'paid';
              } elseif ($tab['id'] === 'cancellations') {
                  $show_order = $fulfillment_status === 'cancelled';
              } else {
                  $show_order = $payment_status !== 'paid'
                      && in_array($fulfillment_status, $tab['statuses'], true);
              }
              if ($show_order) {
                  $tab_orders[] = $order;
              }
          }
        ?>
        <section class="orders-tab-panel" role="tabpanel"
                 id="orders-panel-<?php echo htmlspecialchars($tab['id']); ?>"
                 aria-labelledby="orders-tab-<?php echo htmlspecialchars($tab['id']); ?>"
                 tabindex="0">
          <?php if (!$tab_orders) { ?>
            <div class="orders-empty">
              <p><?php echo htmlspecialchars(!$orders ? 'You have not placed any orders yet.' : $tab['empty_message']); ?></p>
              <?php if (!$orders) { ?>
                <a class="btn" href="<?php echo BASE_URL; ?>department.php?dept=men">Continue shopping</a>
              <?php } ?>
            </div>
          <?php } else { ?>
        <div class="orders-list">
          <?php foreach ($tab_orders as $order) { ?>
            <?php
              $status = strtolower((string)($order['fulfillment_status'] ?? 'pending'));
              $payment_status = strtolower((string)($order['payment_status'] ?? 'unpaid'));
              $status_label = ucwords(str_replace('_', ' ', $status));
              $created = date('M j, Y', strtotime($order['created_at']));
            ?>
            <article class="order-card">
          <div class="order-header">
            <div>
              <p class="order-label">Order #<?php echo (int)$order['order_id']; ?></p>
              <p class="order-date">Placed on <?php echo htmlspecialchars($created); ?></p>
            </div>
            <div class="order-statuses">
              <span class="order-status status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_label); ?></span>
              <span class="order-status status-<?php echo htmlspecialchars($payment_status); ?>"><?php echo htmlspecialchars(ucfirst($payment_status)); ?></span>
            </div>
          </div>

          <div class="order-summary">
            <div>
              <p class="summary-label">Shipping address</p>
              <p><?php echo nl2br(htmlspecialchars((string)$order['shipping_address'])); ?></p>
            </div>
            <div>
              <p class="summary-label">Items</p>
              <p><?php echo (int)$order['item_count']; ?> item<?php echo (int)$order['item_count'] === 1 ? '' : 's'; ?></p>
            </div>
            <div class="summary-total-wrap">
              <p class="summary-label">Total</p>
              <p class="summary-total"><?php echo htmlspecialchars(peso((float)$order['order_total'])); ?></p>
            </div>
          </div>

          <div class="order-actions">
            <details class="order-details">
              <summary>View details</summary>
              <div class="order-details-content">
                <?php if ($status === 'pending') { ?>
                  <form method="post" action="<?php echo BASE_URL; ?>orders/orders.php"
                        class="order-edit-form" id="edit-order-<?php echo (int)$order['order_id']; ?>" novalidate>
                    <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                    <input type="hidden" name="action" value="edit_order">
                <?php } ?>
                <div class="order-items">
                  <?php foreach ($order['items'] as $item) { ?>
                    <div class="order-item">
                      <div class="order-item-image">
                        <?php if (!empty($item['image'])) { ?>
                          <img src="<?php echo BASE_URL; ?>images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" loading="lazy">
                        <?php } else { ?>
                          <div class="order-item-placeholder">No image</div>
                        <?php } ?>
                      </div>

                      <div class="order-item-info">
                        <h2><?php echo htmlspecialchars($item['product_name']); ?></h2>
                        <?php if ($status === 'pending') { ?>
                          <label class="order-edit-field">
                            <span>Color and size</span>
                            <select name="items[<?php echo (int)$item['order_item_id']; ?>][variant_id]">
                              <?php foreach ($item['variants'] as $variant) { ?>
                                <option value="<?php echo (int)$variant['variant_id']; ?>"
                                        <?php echo (int)$variant['variant_id'] === (int)$item['variant_id'] ? 'selected' : ''; ?>
                                        <?php echo (int)$variant['available_stock'] < 1 ? 'disabled' : ''; ?>>
                                  <?php echo htmlspecialchars($variant['color'] . ' - ' . $variant['size']); ?>
                                  <?php echo (int)$variant['available_stock'] > 0 ? ' (' . (int)$variant['available_stock'] . ' available)' : ' (sold out)'; ?>
                                </option>
                              <?php } ?>
                            </select>
                          </label>
                          <label class="order-edit-field order-quantity-field">
                            <span>Quantity</span>
                            <input type="number" name="items[<?php echo (int)$item['order_item_id']; ?>][quantity]"
                                   value="<?php echo (int)$item['quantity']; ?>">
                          </label>
                        <?php } else { ?>
                          <p><?php echo htmlspecialchars($item['color']); ?> · Size <?php echo htmlspecialchars($item['size']); ?></p>
                          <p>Qty: <?php echo (int)$item['quantity']; ?> · <?php echo htmlspecialchars(peso((float)$item['price'])); ?> each</p>
                        <?php } ?>
                      </div>
                      <?php if ($payment_status === 'paid') { ?>
                        <?php $has_review = in_array(($item['review_status'] ?? ''), ['pending', 'published'], true); ?>
                        <details class="rate-review-disclosure">
                          <summary><?php echo $has_review ? 'Edit review' : 'Rate this product'; ?></summary>
                          <form method="post" action="<?php echo BASE_URL; ?>reviews/review_action.php" class="rate-review-form" novalidate>
                            <p class="rate-review-eyebrow">YOUR PURCHASE</p>
                            <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                            <p>Quantity bought: <?php echo (int)$item['quantity']; ?></p>
                            <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                            <input type="hidden" name="order_item_id" value="<?php echo (int)$item['order_item_id']; ?>">
                            <input type="hidden" name="product_id" value="<?php echo (int)$item['product_id']; ?>">
                            <?php if ($has_review) { ?>
                              <input type="hidden" name="review_id" value="<?php echo (int)$item['review_id']; ?>">
                            <?php } ?>
                            <input type="hidden" name="action" value="<?php echo $has_review ? 'update' : 'create'; ?>">
                            <fieldset class="rate-review-stars">
                              <legend>Your rating</legend>
                              <?php for ($star = 1; $star <= 5; $star++) { ?>
                                <label>
                                  <input type="radio" name="rating" value="<?php echo $star; ?>" <?php echo $has_review && (int)$item['review_rating'] === $star ? 'checked' : ''; ?>>
                                  <span aria-hidden="true">&#9733;</span>
                                  <span class="rate-review-sr-only"><?php echo $star; ?> stars</span>
                                </label>
                              <?php } ?>
                            </fieldset>
                            <label class="rate-review-comment-label">Your comment
                              <textarea name="review_text" rows="5" placeholder="Share your experience with this product"><?php echo $has_review ? htmlspecialchars((string)$item['review_text'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                            </label>
                            <p class="rate-review-hint">Please keep your review respectful and helpful.</p>
                            <button class="btn rate-review-submit" type="submit"><?php echo $has_review ? 'Save changes' : 'Submit review'; ?></button>
                          </form>
                          <?php if ($has_review) { ?>
                            <form method="post" action="<?php echo BASE_URL; ?>reviews/review_action.php">
                              <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                              <input type="hidden" name="order_item_id" value="<?php echo (int)$item['order_item_id']; ?>">
                              <input type="hidden" name="product_id" value="<?php echo (int)$item['product_id']; ?>">
                              <input type="hidden" name="review_id" value="<?php echo (int)$item['review_id']; ?>">
                              <input type="hidden" name="action" value="delete">
                              <button class="rate-product-button" type="submit">Delete review</button>
                            </form>
                          <?php } ?>
                        </details>
                      <?php } ?>
                    </div>
                  <?php } ?>
                </div>
                <?php if ($status === 'pending') { ?>
                    <div class="order-action-buttons">
                      <button class="btn order-save-button" type="submit">Save changes</button>
                    </div>
                  </form>
                  <form method="post" action="<?php echo BASE_URL; ?>orders/orders.php"
                        class="order-cancel-form order-cancel-form-expanded">
                    <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                    <input type="hidden" name="action" value="cancel_order">
                    <button class="order-cancel-button" type="submit">Cancel order</button>
                  </form>
                <?php } ?>
              </div>
            </details>
            <?php if ($status === 'pending') { ?>
              <form method="post" action="<?php echo BASE_URL; ?>orders/orders.php"
                    class="order-cancel-form order-cancel-form-collapsed">
                <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                <input type="hidden" name="action" value="cancel_order">
                <button class="order-cancel-button" type="submit">Cancel order</button>
              </form>
            <?php } ?>
          </div>
            </article>
          <?php } ?>
        </div>
          <?php } ?>
        </section>
      <?php } ?>
    </section>
  </div>

</main>

<?php include_once("../includes/footer.php"); ?>
