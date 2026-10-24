<?php
session_start();
include_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/customer_auth.php';

require_active_customer($conn, BASE_URL . 'orders/orders.php');

$user_id = (int)$_SESSION['user_id'];
$orders = [];

if (!isset($_SESSION['orders_csrf'])) {
    $_SESSION['orders_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!is_string($csrf_token) || !hash_equals($_SESSION['orders_csrf'], $csrf_token)) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Your session expired. Please try again.'];
    } elseif ($order_id === false || $order_id < 1 || !in_array($action, ['edit_order', 'cancel_order'], true)) {
        $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'That order action is invalid.'];
    } else {
        try {
            mysqli_begin_transaction($conn);

            // This query locks the order row so the app can safely check if it is still pending before making changes.
            $stmt = mysqli_prepare($conn, "SELECT order_id, status FROM orders WHERE order_id = ? AND user_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, 'ii', $order_id, $user_id);
            mysqli_stmt_execute($stmt);
            $locked_order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$locked_order || $locked_order['status'] !== 'pending') {
                mysqli_rollback($conn);
                $_SESSION['orders_flash'] = ['type' => 'error', 'text' => 'Only pending orders can be changed or cancelled.'];
            } elseif ($action === 'cancel_order') {
                // This query loads the order items so the stock can be returned before cancelling the order.
                $stmt = mysqli_prepare($conn, "SELECT variant_id, quantity FROM order_items WHERE order_id = ? FOR UPDATE");
                mysqli_stmt_bind_param($stmt, 'i', $order_id);
                mysqli_stmt_execute($stmt);
                $order_items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

                // This query returns the cancelled items back into stock for each variant.
                $stock_stmt = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?");
                foreach ($order_items as $item) {
                    $quantity = (int)$item['quantity'];
                    $variant_id = (int)$item['variant_id'];
                    mysqli_stmt_bind_param($stock_stmt, 'ii', $quantity, $variant_id);
                    mysqli_stmt_execute($stock_stmt);
                }

                // This query marks the order as cancelled only if it is still pending and still belongs to this user.
                $stmt = mysqli_prepare($conn, "UPDATE orders SET status = 'cancelled' WHERE order_id = ? AND user_id = ? AND status = 'pending'");
                mysqli_stmt_bind_param($stmt, 'ii', $order_id, $user_id);
                mysqli_stmt_execute($stmt);
                if (mysqli_stmt_affected_rows($stmt) !== 1) {
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
                    // This query loads the order items and their products so the app can validate each change before updating the order.
                    $stmt = mysqli_prepare($conn,
                        "SELECT oi.order_item_id, oi.variant_id, oi.quantity, pc.product_id
                         FROM order_items oi
                         JOIN product_variants v ON v.variant_id = oi.variant_id
                         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                         WHERE oi.order_id = ?
                         FOR UPDATE"
                    );
                    mysqli_stmt_bind_param($stmt, 'i', $order_id);
                    mysqli_stmt_execute($stmt);
                    $current_items = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

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
                        // This query checks that the requested size belongs to the same product before the change is accepted.
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
                        // This query restores the old stock before the replacement item is checked against current inventory.
                        $stock_add_stmt = mysqli_prepare($conn, "UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?");
                        foreach ($changes as $change) {
                            $old_quantity = $change['old_quantity'];
                            $old_variant_id = $change['old_variant_id'];
                            mysqli_stmt_bind_param($stock_add_stmt, 'ii', $old_quantity, $old_variant_id);
                            mysqli_stmt_execute($stock_add_stmt);
                        }

                        // This query reduces stock for the new size while making sure there is enough available.
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

// This query loads all of the current user's orders and totals for the order list page.
$stmt = mysqli_prepare($conn,
    "SELECT o.order_id, o.status, o.shipping_address, o.created_at,
            COALESCE(SUM(oi.quantity * oi.price), 0) AS order_total,
            COALESCE(SUM(oi.quantity), 0) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.order_id
     WHERE o.user_id = ?
     GROUP BY o.order_id, o.status, o.shipping_address, o.created_at
     ORDER BY o.order_id DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$orders = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

foreach ($orders as &$order) {
    $order_id = (int)$order['order_id'];

    // This query loads the items within one order so each product can be shown in the details section.
    $item_stmt = mysqli_prepare($conn,
        "SELECT oi.order_item_id, oi.variant_id, oi.quantity, oi.price,
                p.product_id, p.name AS product_name,
                pc.image, v.size, pc.color,
                r.review_id, r.rating AS review_rating, r.review_text, r.status AS review_status
         FROM order_items oi
         JOIN product_variants v ON v.variant_id = oi.variant_id
         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
         JOIN products p ON p.product_id = pc.product_id
         LEFT JOIN product_reviews r ON r.product_id = p.product_id AND r.user_id = ?
         WHERE oi.order_id = ?
         ORDER BY oi.order_item_id ASC"
    );
    mysqli_stmt_bind_param($item_stmt, 'ii', $user_id, $order_id);
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
        'statuses' => ['delivered', 'received'],
        'empty_message' => 'No received orders right now.',
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
include_once __DIR__ . '/../includes/header.php';
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

      <div class="orders-tabs" role="tablist" aria-label="Order status">
        <?php foreach ($order_tabs as $tab_index => $tab) { ?>
          <button class="orders-tab" type="button" role="tab"
                  id="orders-tab-<?php echo htmlspecialchars($tab['id']); ?>"
                  aria-controls="orders-panel-<?php echo htmlspecialchars($tab['id']); ?>"
                  aria-selected="<?php echo $tab_index === 0 ? 'true' : 'false'; ?>"
                  tabindex="<?php echo $tab_index === 0 ? '0' : '-1'; ?>">
            <?php echo htmlspecialchars($tab['label']); ?>
          </button>
        <?php } ?>
      </div>

      <?php foreach ($order_tabs as $tab_index => $tab) { ?>
        <?php
          $tab_orders = array_filter($orders, function ($order) use ($tab) {
              return in_array(strtolower((string)($order['status'] ?? '')), $tab['statuses'], true);
          });
        ?>
        <section class="orders-tab-panel" role="tabpanel"
                 id="orders-panel-<?php echo htmlspecialchars($tab['id']); ?>"
                 aria-labelledby="orders-tab-<?php echo htmlspecialchars($tab['id']); ?>"
                 tabindex="0"
                 <?php echo $tab_index === 0 ? '' : 'hidden'; ?>>
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
              $status = strtolower((string)($order['status'] ?? 'pending'));
              $status_label = ucfirst($status);
              $created = date('M j, Y', strtotime($order['created_at']));
            ?>
            <article class="order-card">
          <div class="order-header">
            <div>
              <p class="order-label">Order #<?php echo (int)$order['order_id']; ?></p>
              <p class="order-date">Placed on <?php echo htmlspecialchars($created); ?></p>
            </div>
            <span class="order-status status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_label); ?></span>
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
                        class="order-edit-form" id="edit-order-<?php echo (int)$order['order_id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['orders_csrf']); ?>">
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
                                   value="<?php echo (int)$item['quantity']; ?>" min="1" max="1000" required>
                          </label>
                        <?php } else { ?>
                          <p><?php echo htmlspecialchars($item['color']); ?> · Size <?php echo htmlspecialchars($item['size']); ?></p>
                          <p>Qty: <?php echo (int)$item['quantity']; ?> · <?php echo htmlspecialchars(peso((float)$item['price'])); ?> each</p>
                        <?php } ?>
                      </div>
                      <?php if ($status === 'paid') { ?>
                        <?php if (in_array(($item['review_status'] ?? ''), ['pending', 'published'], true)) { ?>
                          <button class="rate-product-button" type="button"
                                  data-review-action="update"
                                  data-review-id="<?php echo (int)$item['review_id']; ?>"
                                  data-rating="<?php echo (int)$item['review_rating']; ?>"
                                  data-review-text="<?php echo htmlspecialchars((string)$item['review_text'], ENT_QUOTES, 'UTF-8'); ?>"
                                  data-order-id="<?php echo (int)$order['order_id']; ?>"
                                  data-order-item-id="<?php echo (int)$item['order_item_id']; ?>"
                                  data-product-id="<?php echo (int)$item['product_id']; ?>"
                                  data-product-name="<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                  data-quantity="<?php echo (int)$item['quantity']; ?>">Edit review</button>
                        <?php } else { ?>
                          <button class="rate-product-button" type="button"
                                  data-review-action="create"
                                  data-order-id="<?php echo (int)$order['order_id']; ?>"
                                  data-order-item-id="<?php echo (int)$item['order_item_id']; ?>"
                                  data-product-id="<?php echo (int)$item['product_id']; ?>"
                                  data-product-name="<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                  data-quantity="<?php echo (int)$item['quantity']; ?>">Rate</button>
                        <?php } ?>
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
                        class="order-cancel-form order-cancel-form-expanded"
                        onsubmit="return confirm('Cancel this order?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['orders_csrf']); ?>">
                    <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                    <input type="hidden" name="action" value="cancel_order">
                    <button class="order-cancel-button" type="submit">Cancel order</button>
                  </form>
                <?php } ?>
              </div>
            </details>
            <?php if ($status === 'pending') { ?>
              <form method="post" action="<?php echo BASE_URL; ?>orders/orders.php"
                    class="order-cancel-form order-cancel-form-collapsed"
                    onsubmit="return confirm('Cancel this order?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['orders_csrf']); ?>">
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

  <dialog class="rate-review-dialog" id="rate-review-dialog" aria-labelledby="rate-review-title">
    <form method="post" action="<?php echo BASE_URL; ?>reviews/review_action.php" class="rate-review-form">
      <button class="rate-review-close" type="button" aria-label="Close review panel">&times;</button>
      <p class="rate-review-eyebrow">YOUR PURCHASE</p>
      <h2 id="rate-review-title">Rate this product</h2>
      <p class="rate-review-product" id="rate-review-product-name"></p>
      <p class="rate-review-quantity" id="rate-review-quantity"></p>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['orders_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
      <input type="hidden" name="order_id" id="rate-review-order-id">
      <input type="hidden" name="order_item_id" id="rate-review-order-item-id">
      <input type="hidden" name="product_id" id="rate-review-product-id">
      <input type="hidden" name="review_id" id="rate-review-review-id">
      <input type="hidden" name="action" id="rate-review-action">
      <fieldset class="rate-review-stars">
        <legend>Your rating</legend>
        <?php for ($star = 1; $star <= 5; $star++) { ?>
          <label>
            <input type="radio" name="rating" value="<?php echo $star; ?>" required>
            <span aria-hidden="true">&#9733;</span>
            <span class="rate-review-sr-only"><?php echo $star; ?> stars</span>
          </label>
        <?php } ?>
      </fieldset>
      <label class="rate-review-comment-label" for="rate-review-comment">Your comment</label>
      <textarea id="rate-review-comment" name="review_text" rows="5" maxlength="1000" placeholder="Share your experience with this product" required></textarea>
      <p class="rate-review-warning" id="rate-review-warning" role="alert" hidden>Your review contains inappropriate word/s, would you still want to publish?</p>
      <p class="rate-review-hint">Please keep your review respectful and helpful.</p>
      <div class="rate-review-actions">
        <button class="btn rate-review-submit" type="submit" id="rate-review-submit">Submit review</button>
        <button class="btn rate-review-delete" type="submit" id="rate-review-delete" formnovalidate hidden>Delete review</button>
      </div>
    </form>
  </dialog>
</main>

<script>
(function () {
  var orderTabs = Array.prototype.slice.call(document.querySelectorAll('.orders-tab'));

  function activateOrderTab(tab, shouldFocus) {
    orderTabs.forEach(function (currentTab) {
      var isSelected = currentTab === tab;
      currentTab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
      currentTab.tabIndex = isSelected ? 0 : -1;
      document.getElementById(currentTab.getAttribute('aria-controls')).hidden = !isSelected;
    });
    if (shouldFocus) tab.focus();
  }

  orderTabs.forEach(function (tab, index) {
    tab.addEventListener('click', function () {
      activateOrderTab(tab, false);
    });
    tab.addEventListener('keydown', function (event) {
      var nextIndex = index;
      if (event.key === 'ArrowRight') {
        nextIndex = (index + 1) % orderTabs.length;
      } else if (event.key === 'ArrowLeft') {
        nextIndex = (index - 1 + orderTabs.length) % orderTabs.length;
      } else if (event.key === 'Home') {
        nextIndex = 0;
      } else if (event.key === 'End') {
        nextIndex = orderTabs.length - 1;
      } else {
        return;
      }
      event.preventDefault();
      activateOrderTab(orderTabs[nextIndex], true);
    });
  });

  var dialog = document.getElementById('rate-review-dialog');
  var form = dialog.querySelector('form');
  var ratingInputs = form.querySelectorAll('input[name="rating"]');
  var comment = document.getElementById('rate-review-comment');
  var vulgarWarning = document.getElementById('rate-review-warning');
  var actionInput = document.getElementById('rate-review-action');
  var submitButton = document.getElementById('rate-review-submit');
  var deleteButton = document.getElementById('rate-review-delete');
  var vulgarPattern = /(?:putang\s*ina|tangina|tanga|gago|gaga|puta|fuck(?:er|ing|ed|s)?|shit(?:ty|ting|s)?|bitch(?:es|y)?|ass(?:hole)?s?|arse(?:hole)?s?|bastard(?:s)?|cunt(?:s)?|dick(?:s)?|cock(?:s|head)?|piss(?:ed|ing)?|damn(?:ed|ing)?|dammit|crap(?:py)?|twat(?:s)?|wanker(?:s)?|whore(?:s)?|slut(?:s)?|prick(?:s)?|douche(?:bag)?s?|bullshit|motherfucker(?:s)?)/gi;
  var confirmedVulgarReview = null;

  function containsVulgarLanguage(text) {
    vulgarPattern.lastIndex = 0;
    var containsVulgar = vulgarPattern.test(text);
    vulgarPattern.lastIndex = 0;
    return containsVulgar;
  }

  function paintRating(rating) {
    ratingInputs.forEach(function (input) {
      input.closest('label').classList.toggle('is-selected', Number(input.value) <= Number(rating));
    });
  }

  ratingInputs.forEach(function (input) {
    input.addEventListener('change', function () {
      paintRating(input.value);
    });
  });

  document.querySelectorAll('.rate-product-button').forEach(function (button) {
    button.addEventListener('click', function () {
      var isUpdate = button.dataset.reviewAction === 'update';
      document.getElementById('rate-review-product-name').textContent = button.dataset.productName;
      document.getElementById('rate-review-quantity').textContent = 'Quantity bought: ' + button.dataset.quantity;
      document.getElementById('rate-review-order-id').value = button.dataset.orderId;
      document.getElementById('rate-review-order-item-id').value = button.dataset.orderItemId;
      document.getElementById('rate-review-product-id').value = button.dataset.productId;
      document.getElementById('rate-review-review-id').value = button.dataset.reviewId || '';
      actionInput.value = button.dataset.reviewAction;
      submitButton.textContent = isUpdate ? 'Save changes' : 'Submit review';
      deleteButton.hidden = !isUpdate;
      comment.value = button.dataset.reviewText || '';
      confirmedVulgarReview = null;
      vulgarWarning.hidden = true;
      ratingInputs.forEach(function (input) {
        input.checked = input.value === (button.dataset.rating || '');
      });
      paintRating(button.dataset.rating || 0);
      dialog.showModal();
    });
  });

  dialog.querySelector('.rate-review-close').addEventListener('click', function () {
    dialog.close();
  });
  dialog.addEventListener('click', function (event) {
    if (event.target === dialog) dialog.close();
  });
  deleteButton.addEventListener('click', function () {
    actionInput.value = 'delete';
    vulgarWarning.hidden = true;
  });
  comment.addEventListener('input', function () {
    confirmedVulgarReview = null;
    vulgarWarning.hidden = true;
  });
  form.addEventListener('submit', function (event) {
    if (actionInput.value === 'delete') return;

    if (containsVulgarLanguage(comment.value) && confirmedVulgarReview !== comment.value) {
      event.preventDefault();
      confirmedVulgarReview = comment.value;
      vulgarWarning.hidden = false;
    }
  });
})();
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
