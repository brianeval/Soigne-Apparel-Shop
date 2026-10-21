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
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/styles/admin.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>admin/styles/manage.css?v=<?php echo filemtime(__DIR__ . '/../styles/manage.css'); ?>">
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
        <a class="<?php echo $admin_active_page === 'overview' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/index.php"><span class="nav-icon" aria-hidden="true">&#9635;</span> Overview</a>
        <a class="<?php echo $admin_active_page === 'products' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage-products/"><span class="nav-icon" aria-hidden="true">&#9638;</span> Manage Products</a>
        <a class="<?php echo $admin_active_page === 'orders' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage-orders/"><span class="nav-icon" aria-hidden="true">&#9776;</span> Manage Orders</a>
        <a class="<?php echo $admin_active_page === 'customers' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>admin/manage-customers/"><span class="nav-icon" aria-hidden="true">&#9787;</span> Manage Customers</a>
      </nav>
      <div class="admin-sidebar-bottom">
        <a href="<?php echo BASE_URL; ?>admin/storefront.php"><span class="nav-icon" aria-hidden="true">&larr;</span> View storefront</a>
        <a href="<?php echo BASE_URL; ?>admin/logout.php"><span class="nav-icon" aria-hidden="true">&#10162;</span> Sign out</a>
      </div>
    </aside>
    <main class="admin-main">
      <header class="admin-topbar">
        <div class="admin-breadcrumb">Workspace <span>/</span> <?php echo admin_escape($admin_page_title); ?></div>
        <div class="admin-user">
          <span class="admin-avatar" aria-hidden="true"><?php echo admin_escape(strtoupper(substr($admin_header_name, 0, 1))); ?></span>
          <span><strong><?php echo admin_escape($admin_header_name); ?></strong><small>Administrator</small></span>
        </div>
      </header>
      <div class="admin-content">
