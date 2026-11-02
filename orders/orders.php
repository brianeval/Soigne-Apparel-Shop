<?php
session_start();
include_once("../includes/config.php");

$login_redirect = BASE_URL . 'orders/orders.php';
include("../includes/require_customer.php");

$user_id = (int)$_SESSION['user_id'];

// Customer actions: change an order or cancel it
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($order_id < 1 || !in_array($action, ['edit_order', 'cancel_order'])) {
        $_SESSION['message'] = 'That order action is invalid.';
    } else {
        try {
            mysqli_begin_transaction($conn);

            // Only the customer's own pending orders can be changed
            $order_result = mysqli_execute_query($conn, "SELECT order_id, fulfillment_status FROM orders WHERE order_id = ? AND user_id = ?", [$order_id, $user_id]);
            $order = mysqli_fetch_assoc($order_result);

            // Items of this order
            $current_result = mysqli_execute_query($conn,
                "SELECT oi.order_item_id, oi.variant_id, oi.quantity, pc.product_id
                 FROM order_items oi
                 JOIN product_variants v ON v.variant_id = oi.variant_id
                 JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                 WHERE oi.order_id = ?",
                [$order_id]
            );
            $current_items = mysqli_fetch_all($current_result, MYSQLI_ASSOC);

            if (!$order || $order['fulfillment_status'] != 'pending') {
                mysqli_rollback($conn);
                $_SESSION['message'] = 'Only pending orders can be changed or cancelled.';
            } elseif ($action == 'cancel_order') {
                // Give the stock back
                foreach ($current_items as $item) {
                    mysqli_execute_query($conn, "UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?", [(int)$item['quantity'], (int)$item['variant_id']]);
                }
                // Cancel order
                mysqli_execute_query($conn, "UPDATE orders SET fulfillment_status = 'cancelled' WHERE order_id = ? AND user_id = ?", [$order_id, $user_id]);
                mysqli_commit($conn);
                $_SESSION['success'] = 'Your order has been cancelled.';
            } else {
                // Edit order: read the new size and quantity of every item
                $valid = true;
                $new_variant_ids = [];
                $new_quantities = [];
                foreach ($current_items as $item) {
                    $item_id = $item['order_item_id'];
                    $new_variant_ids[$item_id] = (int)($_POST['items'][$item_id]['variant_id'] ?? 0);
                    $new_quantities[$item_id] = (int)($_POST['items'][$item_id]['quantity'] ?? 0);

                    // The new size must belong to the same product
                    $variant_result = mysqli_execute_query($conn,
                        "SELECT v.variant_id
                         FROM product_variants v
                         JOIN product_colors pc ON pc.product_color_id = v.product_color_id
                         WHERE v.variant_id = ? AND pc.product_id = ?",
                        [$new_variant_ids[$item_id], (int)$item['product_id']]
                    );
                    if ($new_quantities[$item_id] < 1 || mysqli_num_rows($variant_result) == 0) {
                        $valid = false;
                    }
                }

                if (!$valid) {
                    mysqli_rollback($conn);
                    $_SESSION['message'] = 'Choose a valid size and quantity for every order item.';
                } else {
                    // Give back the old stock, then take the new stock
                    foreach ($current_items as $item) {
                        mysqli_execute_query($conn, "UPDATE product_variants SET stock = stock + ? WHERE variant_id = ?", [(int)$item['quantity'], (int)$item['variant_id']]);
                    }
                    $stock_ok = true;
                    foreach ($current_items as $item) {
                        $item_id = $item['order_item_id'];
                        $new_variant_id = $new_variant_ids[$item_id];
                        $new_quantity = $new_quantities[$item_id];
                        mysqli_execute_query($conn, "UPDATE product_variants SET stock = stock - ? WHERE variant_id = ? AND stock >= ?", [$new_quantity, $new_variant_id, $new_quantity]);
                        if (mysqli_affected_rows($conn) != 1) {
                            $stock_ok = false;
                        }
                        mysqli_execute_query($conn, "UPDATE order_items SET variant_id = ?, quantity = ? WHERE order_item_id = ? AND order_id = ?", [$new_variant_id, $new_quantity, (int)$item_id, $order_id]);
                    }

                    if ($stock_ok) {
                        mysqli_commit($conn);
                        $_SESSION['success'] = 'Your order has been updated.';
                    } else {
                        mysqli_rollback($conn);
                        $_SESSION['message'] = 'The requested quantity is no longer in stock. Your order was not changed.';
                    }
                }
            }
        } catch (mysqli_sql_exception $e) {
            mysqli_rollback($conn);
            $_SESSION['message'] = 'Could not process that order change. Please try again.';
        }
    }

    header('Location: ' . BASE_URL . 'orders/orders.php');
    exit;
}

// Customer orders
$orders_result = mysqli_execute_query($conn, "SELECT o.order_id, o.fulfillment_status, o.payment_status, o.shipping_address, o.created_at,
            COALESCE(SUM(details.line_subtotal), 0) AS order_total,
            COALESCE(SUM(details.quantity), 0) AS item_count
     FROM orders o
     LEFT JOIN order_transaction_details details ON details.order_id = o.order_id
     WHERE o.user_id = ?
     GROUP BY o.order_id, o.fulfillment_status, o.payment_status, o.shipping_address, o.created_at
     ORDER BY o.order_id DESC", [$user_id]);
$orders = mysqli_fetch_all($orders_result, MYSQLI_ASSOC);

foreach ($orders as $order_key => $order) {
    $order_id = (int)$order['order_id'];

    // Order item details
    $item_result = mysqli_execute_query($conn, "SELECT details.order_item_id, details.variant_id, details.quantity,
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
         ORDER BY details.order_item_id ASC", [$user_id, $order_id, $user_id]);
    $order_items = mysqli_fetch_all($item_result, MYSQLI_ASSOC);

    // Every size the customer can switch to (used when the order is still pending)
    foreach ($order_items as $item_key => $item) {
        $variants_result = mysqli_query($conn,
            "SELECT v.variant_id, v.size, v.stock, pc.color
             FROM product_variants v
             JOIN product_colors pc ON pc.product_color_id = v.product_color_id
             WHERE pc.product_id = " . (int)$item['product_id'] . "
             ORDER BY pc.color, v.size"
        );
        $variants = mysqli_fetch_all($variants_result, MYSQLI_ASSOC);
        foreach ($variants as $variant_key => $variant) {
            // The stock already reserved by this order counts as available
            $variants[$variant_key]['available_stock'] = (int)$variant['stock'];
            if ($variant['variant_id'] == $item['variant_id']) {
                $variants[$variant_key]['available_stock'] += (int)$item['quantity'];
            }
        }
        $order_items[$item_key]['variants'] = $variants;
    }

    $orders[$order_key]['items'] = $order_items;
}

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
  <?php include("../includes/alert.php"); ?>

  <div class="orders-layout">
    <section class="orders-main" aria-labelledby="orders-title">
      <div class="orders-header">
        <h1 id="orders-title">My Orders</h1>
      </div>

      <div class="orders-tab-set" role="radiogroup" aria-label="Order status">
        <?php foreach ($order_tabs as $tab_index => $tab) { ?>
          <input class="orders-tab-input" type="radio" name="order_status_tab"
                 id="orders-tab-input-<?php echo htmlspecialchars($tab['id']); ?>"
                 <?php echo $tab_index === 0 ? 'checked' : ''; ?>>
        <?php } ?>
        <nav class="orders-tabs" aria-label="Order status">
          <?php foreach ($order_tabs as $tab) { ?>
            <label class="orders-tab"
                   for="orders-tab-input-<?php echo htmlspecialchars($tab['id']); ?>"
                   id="orders-tab-<?php echo htmlspecialchars($tab['id']); ?>">
              <?php echo htmlspecialchars($tab['label']); ?>
            </label>
          <?php } ?>
        </nav>

        <?php foreach ($order_tabs as $tab) { ?>
        <?php
          // Orders that belong in this tab
          $tab_orders = [];
          foreach ($orders as $order) {
              $fulfillment_status = $order['fulfillment_status'];
              $payment_status = $order['payment_status'];
              if ($tab['id'] == 'review-me' && $payment_status == 'paid') {
                  $tab_orders[] = $order;
              } elseif ($tab['id'] == 'cancellations' && $fulfillment_status == 'cancelled') {
                  $tab_orders[] = $order;
              } elseif ($tab['id'] != 'review-me' && $tab['id'] != 'cancellations'
                        && $payment_status != 'paid' && in_array($fulfillment_status, $tab['statuses'])) {
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
              $status = $order['fulfillment_status'];
              $payment_status = $order['payment_status'];
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
              <p class="summary-total"><?php echo '₱' . number_format($order['order_total'], 2); ?></p>
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
                          <p>Qty: <?php echo (int)$item['quantity']; ?> · <?php echo '₱' . number_format($item['price'], 2); ?> each</p>
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
                              <?php for ($star = 5; $star >= 1; $star--) { ?>
                                <input id="rating-<?php echo (int)$item['order_item_id']; ?>-<?php echo $star; ?>" type="radio" name="rating" value="<?php echo $star; ?>" <?php echo $has_review && (int)$item['review_rating'] === $star ? 'checked' : ''; ?>>
                                <label for="rating-<?php echo (int)$item['order_item_id']; ?>-<?php echo $star; ?>">
                                  <span aria-hidden="true">&#9733;</span>
                                  <span class="rate-review-sr-only"><?php echo $star; ?> stars</span>
                                </label>
                              <?php } ?>

                            </fieldset>
                            <label class="rate-review-comment-label">Your comment
                              <textarea name="review_text" rows="5" placeholder="Share your experience with this product"> <?php echo $has_review ? htmlspecialchars((string)$item['review_text']) : ''; ?></textarea>
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
