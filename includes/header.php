<?php
include_once("config.php");
include_once("customer_auth.php");

// Cart badge
$cart_count = 0;
if ($customer_role == 'customer' || $customer_role == 'vip') {
    $cart_user_id = (int)$_SESSION['user_id'];
    $cart_result = mysqli_query($conn, "SELECT COALESCE(SUM(quantity), 0) AS item_count FROM cart WHERE user_id = $cart_user_id");
    $cart_count = (int)mysqli_fetch_assoc($cart_result)['item_count'];
}

// Profile picture of the signed-in user
if (isset($_SESSION['user_id'])) {
    $image_user_id = (int)$_SESSION['user_id'];
    include(__DIR__ . "/profile_image.php");
}

// Default page title
if (!isset($page_title)) {
    $page_title = 'Soigné';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo ($page_title); ?></title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>styles/homepage.css?v=<?php echo filemtime(APP_ROOT . '/styles/homepage.css'); ?>">
  <?php if (isset($extra_css)) { ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>styles/<?php echo $extra_css; ?>?v=<?php echo time(); ?>">
  <?php } ?>
</head>
<body>

<header class="site-header">
    <a href="<?php echo BASE_URL; ?>index.php" class="logo">Soigné</a>
    <nav class="main-nav" aria-label="Main">
    <a href="<?php echo BASE_URL; ?>department.php?dept=men" class="<?php echo (($dept ?? '') === 'men') ? 'active' : ''; ?>">Men</a>
    <a href="<?php echo BASE_URL; ?>department.php?dept=women" class="<?php echo (($dept ?? '') === 'women') ? 'active' : ''; ?>">Women</a>
    <a href="<?php echo BASE_URL; ?>department.php?dept=kids"  class="<?php echo (($dept ?? '') === 'kids')  ? 'active' : ''; ?>">Kids</a>
    <a href="<?php echo BASE_URL; ?>brands.php" class="<?php echo (($nav_active ?? '') === 'brands') ? 'active' : ''; ?>">Brands</a>
  </nav>
    <form class="search" role="search" method="get" action="<?php echo BASE_URL; ?>search.php">
      <input type="search" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" placeholder="Search products/brands" aria-label="Search products or brands">
    </form>
    <div class="icons">
      <?php if (($_SESSION['role'] ?? '') !== 'admin') { ?>
        <a href="<?php echo BASE_URL; ?>cart/cart.php">🛒 Cart (<?php echo $cart_count; ?>)</a>
      <?php } ?>

      <details class="account-menu">
        <summary class="account-toggle">
          <?php if (isset($_SESSION['user_id'])) { ?>
            <img class="account-avatar" src="<?php echo $profile_image_url; ?>"
                 alt="" width="28" height="28">
          <?php } ?>
          Account <span aria-hidden="true">&#9662;</span>
        </summary>

        <div class="account-dropdown">
          <?php if (isset($_SESSION['user_id'])) { ?>
            <p class="account-hello">Hi, <?php echo htmlspecialchars($_SESSION['username'] ?? 'there'); ?></p>
            <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
              <a href="<?php echo BASE_URL; ?>admin/manage-products/">Back to admin page</a>
            <?php } else { ?>
              <a href="<?php echo BASE_URL; ?>users/profile.php">My profile</a>
              <a href="<?php echo BASE_URL; ?>orders/orders.php">My orders</a>
            <?php } ?>
            <a class="account-logout" href="<?php echo BASE_URL; ?>users/logout.php">Sign out</a>
          <?php } else { ?>
            <a href="<?php echo BASE_URL; ?>users/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Sign in</a>
            <a href="<?php echo BASE_URL; ?>users/register.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Create account</a>
          <?php } ?>
        </div>
      </details>
    </div>
  </header>

  <nav class="sub-nav" aria-label="Men categories">

    <a href="<?php echo BASE_URL; ?>category.php?category=t-shirts">T-shirts</a>
    <a href="<?php echo BASE_URL; ?>category.php?category=shirts">Shirts</a>
    <a href="<?php echo BASE_URL; ?>category.php?category=pants">Pants</a>
    <a href="<?php echo BASE_URL; ?>category.php?category=shorts">Shorts</a>
    <a href="<?php echo BASE_URL; ?>category.php?category=outerwear">Outerwear</a>
    <a href="<?php echo BASE_URL; ?>category.php?category=sweaters">Sweaters</a>
    <a href="<?php echo BASE_URL; ?>category.php?category=hoodies">Hoodies</a>
    <a href="<?php echo BASE_URL; ?>category.php?category=chinos">Chinos</a>
    <a href="<?php echo BASE_URL; ?>category.php?category=jeans">Jeans</a>
    <a href="<?php echo BASE_URL; ?>category.php?category=polos">Polos</a>
    <a href="<?php echo BASE_URL; ?>premium.php">Soigné Premium</a>

  
  </nav>
  <?php if ($customer_role === 'vip' && !empty($_SESSION['vip_expires_at'])) { ?>
    <p class="premium-member-notice" role="status">
      Soigné Premium is active through <?php echo htmlspecialchars(date('F j, Y', strtotime($_SESSION['vip_expires_at']))); ?>.
      Thank you for purchasing VIP — your 10% product discount is applied automatically.
    </p>
  <?php } ?>
