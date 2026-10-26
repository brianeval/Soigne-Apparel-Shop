<?php
require_once __DIR__ . '/includes/bootstrap.php';

$stats_result = mysqli_query($conn,
    "SELECT
        (SELECT COUNT(*) FROM products) AS product_count,
        (SELECT COUNT(*) FROM orders) AS order_count,
        (SELECT COUNT(*) FROM users WHERE role IN ('customer', 'vip')) AS customer_count,
        (SELECT COUNT(*) FROM orders WHERE fulfillment_status = 'pending' AND payment_status = 'unpaid') AS pending_count,
        (SELECT COALESCE(SUM(details.line_subtotal), 0)
         FROM order_transaction_details AS details
         JOIN orders o ON o.order_id = details.order_id
         WHERE o.payment_status = 'paid') AS sales_total"
);
$stats = mysqli_fetch_assoc($stats_result);

$recent_orders_result = mysqli_query($conn,
    "SELECT o.order_id, o.fulfillment_status, o.payment_status, o.created_at, u.username,
            COALESCE(SUM(details.quantity), 0) AS item_count,
            COALESCE(SUM(details.line_subtotal), 0) AS order_total
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN order_transaction_details AS details ON details.order_id = o.order_id
     GROUP BY o.order_id, o.fulfillment_status, o.payment_status, o.created_at, u.username
     ORDER BY o.created_at DESC, o.order_id DESC
     LIMIT 6"
);
$recent_orders = mysqli_fetch_all($recent_orders_result, MYSQLI_ASSOC);

$admin_name = $_SESSION['username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | Soigné Admin</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/styles/admin.css?v=<?php echo filemtime(__DIR__ . '/styles/admin.css'); ?>">
</head>
<body class="admin-dashboard-body">
  <div class="admin-app">
    <aside class="admin-sidebar" id="admin-sidebar">
      <a class="admin-brand" href="<?php echo BASE_URL; ?>admin/index.php">
        <span class="admin-brand-mark">S</span>
        <span class="admin-brand-copy">SOIGNÉ <small>ADMINISTRATION</small></span>
      </a>

      <p class="admin-nav-label">WORKSPACE</p>
      <nav class="admin-nav" aria-label="Admin navigation">
        <a class="active" href="<?php echo BASE_URL; ?>admin/index.php">
          <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"></rect><rect x="14" y="4" width="6" height="6" rx="1"></rect><rect x="4" y="14" width="6" height="6" rx="1"></rect><rect x="14" y="14" width="6" height="6" rx="1"></rect></svg><span class="nav-label-text">Overview</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/manage-products/">
          <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m4 8 8-4 8 4v9l-8 4-8-4z"></path><path d="m4 8 8 4 8-4M12 12v9"></path></svg><span class="nav-label-text">Manage Products</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/manage-inventory/">
          <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM8 7V4h8v3M8 11h8M8 15h5"></path></svg><span class="nav-label-text">Manage Inventory</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/manage-orders/">
          <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"></path><path d="M4 6h.01M4 12h.01M4 18h.01"></path></svg><span class="nav-label-text">Manage Orders</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/manage-reviews/">
          <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 14.7 9l6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"></path></svg><span class="nav-label-text">Manage Reviews</span>
        </a>
        <a href="<?php echo BASE_URL; ?>admin/manage-customers/">
          <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"></circle><path d="M3.5 20v-1.5a5.5 5.5 0 0 1 11 0V20M16 5.5a3 3 0 0 1 0 5.8M17 14a4.5 4.5 0 0 1 3.5 4.4V20"></path></svg><span class="nav-label-text">Manage Customers</span>
        </a>
      </nav>

      <div class="admin-sidebar-bottom">
        <a href="<?php echo BASE_URL; ?>admin/storefront.php" aria-label="View storefront"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m10 7-5 5 5 5M5 12h14"></path></svg><span class="nav-label-text">View storefront</span></a>
        <a href="<?php echo BASE_URL; ?>admin/logout.php" aria-label="Sign out"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5v16h5M14 8l4 4-4 4M8 12h10"></path></svg><span class="nav-label-text">Sign out</span></a>
      </div>
    </aside>

    <main class="admin-main">
      <header class="admin-topbar">
        <div class="admin-topbar-start">
          <button class="admin-sidebar-toggle" type="button" aria-label="Collapse sidebar" aria-controls="admin-sidebar" aria-expanded="true">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>
          </button>
          <div class="admin-breadcrumb">Workspace <span>/</span> Overview</div>
        </div>
        <div class="admin-user">
          <span class="admin-avatar" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($admin_name, 0, 1))); ?></span>
          <span><strong><?php echo htmlspecialchars($admin_name); ?></strong><small>Administrator</small></span>
        </div>
      </header>

      <div class="admin-content">
        <section class="admin-page-heading">
          <div>
            <p class="admin-eyebrow">STORE OVERVIEW</p>
            <h1>Good day, <?php echo htmlspecialchars($admin_name); ?></h1>
            <p>Here’s what’s happening with your store.</p>
          </div>
          <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/storefront.php">View storefront <span aria-hidden="true">&nearr;</span></a>
        </section>

        <section class="admin-stat-grid" aria-label="Store statistics">
          <article class="admin-stat-card">
            <div class="stat-top"><span>Total products</span><span class="stat-icon icon-violet" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m4 8 8-4 8 4v9l-8 4-8-4z"></path><path d="m4 8 8 4 8-4M12 12v9"></path></svg></span></div>
            <p class="stat-value"><?php echo number_format((int)$stats['product_count']); ?></p>
            <p class="stat-foot">Products in your catalog</p>
          </article>
          <article class="admin-stat-card">
            <div class="stat-top"><span>Total orders</span><span class="stat-icon icon-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"></path></svg></span></div>
            <p class="stat-value"><?php echo number_format((int)$stats['order_count']); ?></p>
            <p class="stat-foot">All orders placed</p>
          </article>
          <article class="admin-stat-card">
            <div class="stat-top"><span>Customers</span><span class="stat-icon icon-green" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3.5 20v-1.5a5.5 5.5 0 0 1 11 0V20M16 5.5a3 3 0 0 1 0 5.8M17 14a4.5 4.5 0 0 1 3.5 4.4V20"></path></svg></span></div>
            <p class="stat-value"><?php echo number_format((int)$stats['customer_count']); ?></p>
            <p class="stat-foot">Customer accounts</p>
          </article>
          <article class="admin-stat-card">
            <div class="stat-top"><span>Pending orders</span><span class="stat-icon icon-amber" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"></circle><path d="M12 7v5l3 2"></path></svg></span></div>
            <p class="stat-value"><?php echo number_format((int)$stats['pending_count']); ?></p>
            <p class="stat-foot">Orders awaiting action</p>
          </article>
        </section>

        <section class="admin-overview-grid">
          <article class="admin-panel sales-panel">
            <div class="admin-panel-heading">
              <div><p class="admin-eyebrow">STORE PERFORMANCE</p><h2>Sales overview</h2></div>
              <span class="panel-period">All time</span>
            </div>
            <p class="sales-total"><?php echo peso((float)$stats['sales_total']); ?></p>
            <p class="sales-caption">Total value of paid orders</p>
            <div class="sales-visual" aria-label="Sales total displayed as a summary">
              <div class="sales-visual-label"><span>Recorded order value</span><strong><?php echo peso((float)$stats['sales_total']); ?></strong></div>
              <div class="sales-bar"><span></span></div>
              <div class="sales-visual-note">This total is based on item prices saved with each order.</div>
            </div>
          </article>

          <article class="admin-panel quick-panel">
            <div class="admin-panel-heading"><div><p class="admin-eyebrow">SHORTCUTS</p><h2>Quick access</h2></div></div>
            <a class="quick-link" href="<?php echo BASE_URL; ?>admin/manage-orders/"><span class="quick-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"></path></svg></span><span><strong>Manage orders</strong><small>View and update customer orders</small></span><span class="quick-arrow">&rarr;</span></a>
            <a class="quick-link" href="<?php echo BASE_URL; ?>admin/manage-products/"><span class="quick-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m4 8 8-4 8 4v9l-8 4-8-4z"></path><path d="m4 8 8 4 8-4M12 12v9"></path></svg></span><span><strong>Manage products</strong><small>Update catalog, sizes, and stock</small></span><span class="quick-arrow">&rarr;</span></a>
            <a class="quick-link" href="<?php echo BASE_URL; ?>admin/manage-customers/"><span class="quick-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"></circle><path d="M3.5 20v-1.5a5.5 5.5 0 0 1 11 0V20M16 5.5a3 3 0 0 1 0 5.8M17 14a4.5 4.5 0 0 1 3.5 4.4V20"></path></svg></span><span><strong>Manage customers</strong><small>Update customer accounts</small></span><span class="quick-arrow">&rarr;</span></a>
          </article>
        </section>

        <section class="admin-panel recent-panel" id="orders">
          <div class="admin-panel-heading recent-heading">
            <div><p class="admin-eyebrow">LATEST ACTIVITY</p><h2>Recent orders</h2></div>
            <span class="panel-period">Latest 6</span>
          </div>
          <?php if ($recent_orders) { ?>
            <div class="admin-table-wrap">
              <table class="admin-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recent_orders as $order) { ?>
                    <tr>
                      <td class="order-number">#<?php echo (int)$order['order_id']; ?></td>
                      <td><?php echo htmlspecialchars($order['username']); ?></td>
                      <td><?php echo htmlspecialchars(date('M j, Y', strtotime($order['created_at']))); ?></td>
                      <td><?php echo (int)$order['item_count']; ?></td>
                      <td><?php echo peso((float)$order['order_total']); ?></td>
                      <td><span class="status-pill status-<?php echo htmlspecialchars($order['fulfillment_status']); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order['fulfillment_status']))); ?></span> <span class="status-pill status-<?php echo htmlspecialchars($order['payment_status']); ?>"><?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?></span></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <div class="admin-empty"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"></path></svg><strong>No orders yet</strong><p>New customer orders will appear here.</p></div>
          <?php } ?>
        </section>

        <footer class="admin-footer">Soigné administration <span>&middot;</span> Store management</footer>
      </div>
    </main>
  </div>
  <script src="<?php echo BASE_URL; ?>admin/admin.js?v=<?php echo filemtime(__DIR__ . '/admin.js'); ?>" defer></script>
</body>
</html>
