<?php
require_once __DIR__ . '/includes/bootstrap.php';

$stats_result = mysqli_query($conn,
    "SELECT
        (SELECT COUNT(*) FROM products) AS product_count,
        (SELECT COUNT(*) FROM orders) AS order_count,
        (SELECT COUNT(*) FROM users WHERE role = 'customer') AS customer_count,
        (SELECT COUNT(*) FROM orders WHERE status = 'pending') AS pending_count,
        (SELECT COALESCE(SUM(oi.quantity * oi.price), 0)
         FROM order_items oi
         JOIN orders o ON o.order_id = oi.order_id
         WHERE o.status = 'paid') AS sales_total"
);
$stats = mysqli_fetch_assoc($stats_result);

$recent_orders_result = mysqli_query($conn,
    "SELECT o.order_id, o.status, o.created_at, u.username,
            COALESCE(SUM(oi.quantity), 0) AS item_count,
            COALESCE(SUM(oi.quantity * oi.price), 0) AS order_total
     FROM orders o
     JOIN users u ON u.user_id = o.user_id
     LEFT JOIN order_items oi ON oi.order_id = o.order_id
     GROUP BY o.order_id, o.status, o.created_at, u.username
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
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/styles/admin.css">
</head>
<body class="admin-dashboard-body">
  <div class="admin-app">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="<?php echo BASE_URL; ?>admin/index.php">
        <span class="admin-brand-mark">S</span>
        <span>SOIGNÉ <small>ADMINISTRATION</small></span>
      </a>

      <p class="admin-nav-label">WORKSPACE</p>
      <nav class="admin-nav" aria-label="Admin navigation">
        <a class="active" href="<?php echo BASE_URL; ?>admin/index.php">
          <span class="nav-icon" aria-hidden="true">&#9635;</span> Overview
        </a>
        <a href="<?php echo BASE_URL; ?>admin/manage-products/">
          <span class="nav-icon" aria-hidden="true">&#9638;</span> Manage Products
        </a>
        <a href="<?php echo BASE_URL; ?>admin/manage-orders/">
          <span class="nav-icon" aria-hidden="true">&#9776;</span> Manage Orders
        </a>
        <a href="<?php echo BASE_URL; ?>admin/manage-customers/">
          <span class="nav-icon" aria-hidden="true">&#9787;</span> Manage Customers
        </a>
      </nav>

      <div class="admin-sidebar-bottom">
        <a href="<?php echo BASE_URL; ?>admin/storefront.php"><span class="nav-icon" aria-hidden="true">&larr;</span> View storefront</a>
        <a href="<?php echo BASE_URL; ?>admin/logout.php"><span class="nav-icon" aria-hidden="true">&#10162;</span> Sign out</a>
      </div>
    </aside>

    <main class="admin-main">
      <header class="admin-topbar">
        <div class="admin-breadcrumb">Workspace <span>/</span> Overview</div>
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
            <div class="stat-top"><span>Total products</span><span class="stat-icon icon-violet" aria-hidden="true">&#9638;</span></div>
            <p class="stat-value"><?php echo number_format((int)$stats['product_count']); ?></p>
            <p class="stat-foot">Products in your catalog</p>
          </article>
          <article class="admin-stat-card">
            <div class="stat-top"><span>Total orders</span><span class="stat-icon icon-blue" aria-hidden="true">&#9776;</span></div>
            <p class="stat-value"><?php echo number_format((int)$stats['order_count']); ?></p>
            <p class="stat-foot">All orders placed</p>
          </article>
          <article class="admin-stat-card">
            <div class="stat-top"><span>Customers</span><span class="stat-icon icon-green" aria-hidden="true">&#9787;</span></div>
            <p class="stat-value"><?php echo number_format((int)$stats['customer_count']); ?></p>
            <p class="stat-foot">Customer accounts</p>
          </article>
          <article class="admin-stat-card">
            <div class="stat-top"><span>Pending orders</span><span class="stat-icon icon-amber" aria-hidden="true">&#9719;</span></div>
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
            <a class="quick-link" href="<?php echo BASE_URL; ?>admin/manage-orders/"><span class="quick-link-icon">&#9776;</span><span><strong>Manage orders</strong><small>View and update customer orders</small></span><span class="quick-arrow">&rarr;</span></a>
            <a class="quick-link" href="<?php echo BASE_URL; ?>admin/manage-products/"><span class="quick-link-icon">&#9638;</span><span><strong>Manage products</strong><small>Update catalog, sizes, and stock</small></span><span class="quick-arrow">&rarr;</span></a>
            <a class="quick-link" href="<?php echo BASE_URL; ?>admin/manage-customers/"><span class="quick-link-icon">&#9787;</span><span><strong>Manage customers</strong><small>Update customer accounts</small></span><span class="quick-arrow">&rarr;</span></a>
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
                      <td><span class="status-pill status-<?php echo htmlspecialchars($order['status']); ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span></td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } else { ?>
            <div class="admin-empty"><span aria-hidden="true">&#9776;</span><strong>No orders yet</strong><p>New customer orders will appear here.</p></div>
          <?php } ?>
        </section>

        <footer class="admin-footer">Soigné administration <span>&middot;</span> Store management</footer>
      </div>
    </main>
  </div>
</body>
</html>
