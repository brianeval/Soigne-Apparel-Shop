<?php
$admin_page_title = $admin_page_title ?? 'Admin';
$admin_active_page = $admin_active_page ?? '';
$admin_header_name = $_SESSION['username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo admin_escape($admin_page_title); ?> | Soigné Admin</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/styles/admin.css?v=<?php echo filemtime(__DIR__ . '/../styles/admin.css'); ?>">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/styles/manage.css?v=<?php echo filemtime(__DIR__ . '/../styles/manage.css'); ?>">
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
        <a class="<?php echo $admin_active_page === 'overview' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/index.php"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"></rect><rect x="14" y="4" width="6" height="6" rx="1"></rect><rect x="4" y="14" width="6" height="6" rx="1"></rect><rect x="14" y="14" width="6" height="6" rx="1"></rect></svg><span class="nav-label-text">Overview</span></a>
        <a class="<?php echo $admin_active_page === 'products' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage-products/"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m4 8 8-4 8 4v9l-8 4-8-4z"></path><path d="m4 8 8 4 8-4M12 12v9"></path></svg><span class="nav-label-text">Manage Products</span></a>
        <a class="<?php echo $admin_active_page === 'inventory' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage-inventory/"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM8 7V4h8v3M8 11h8M8 15h5"></path></svg><span class="nav-label-text">Manage Inventory</span></a>
        <a class="<?php echo $admin_active_page === 'orders' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage-orders/"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"></path><path d="M4 6h.01M4 12h.01M4 18h.01"></path></svg><span class="nav-label-text">Manage Orders</span></a>
        <a class="<?php echo $admin_active_page === 'reviews' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage-reviews/"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 14.7 9l6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"></path></svg><span class="nav-label-text">Manage Reviews</span></a>
        <a class="<?php echo $admin_active_page === 'customers' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage-customers/"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"></circle><path d="M3.5 20v-1.5a5.5 5.5 0 0 1 11 0V20M16 5.5a3 3 0 0 1 0 5.8M17 14a4.5 4.5 0 0 1 3.5 4.4V20"></path></svg><span class="nav-label-text">Manage Customers</span></a>
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
          <div class="admin-breadcrumb">Workspace <span>/</span> <?php echo admin_escape($admin_page_title); ?></div>
        </div>
        <div class="admin-user">
          <span class="admin-avatar" aria-hidden="true"><?php echo admin_escape(strtoupper(substr($admin_header_name, 0, 1))); ?></span>
          <span><strong><?php echo admin_escape($admin_header_name); ?></strong><small>Administrator</small></span>
        </div>
      </header>
      <div class="admin-content">
