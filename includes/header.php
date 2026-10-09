<?php
include ('config.php');
require_once __DIR__ . '/profile_image.php';
// Count the signed-in user's cart items
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity), 0) AS item_count FROM cart WHERE user_id = ?");
    $user_id = (int)$_SESSION['user_id'];
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $cart_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['item_count'];
}

// Use a default title if the page did not set one
if (!isset($page_title)) {
    $page_title = "Soigné";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo ($page_title); ?></title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>styles/homepage.css">
  <?php if (isset($extra_css)) { ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>styles/<?php echo $extra_css; ?>?v=<?php echo time(); ?>">
  <?php } ?>
</head>
<body>

<header class="site-header">
    <a href="<?php echo BASE_URL; ?>index.php" class="logo">Soigné</a>
    <nav class="main-nav" aria-label="Main">
    <a href="<?php echo BASE_URL; ?>department.php?dept=men"   class="<?php echo (($dept ?? '') === 'men')   ? 'active' : ''; ?>">Men</a>
    <a href="<?php echo BASE_URL; ?>department.php?dept=women" class="<?php echo (($dept ?? '') === 'women') ? 'active' : ''; ?>">Women</a>
    <a href="<?php echo BASE_URL; ?>department.php?dept=kids"  class="<?php echo (($dept ?? '') === 'kids')  ? 'active' : ''; ?>">Kids</a>
    <a href="<?php echo BASE_URL; ?>brands.php" class="<?php echo (($nav_active ?? '') === 'brands') ? 'active' : ''; ?>">Brands</a>
  </nav>
    <form class="search" role="search" onsubmit="return false">
      <input type="search" placeholder="Search products" aria-label="Search products">
    </form>
    <div class="icons">
      <a href="<?php echo BASE_URL; ?>cart/cart.php">🛒 Cart (<?php echo $cart_count; ?>)</a>
      <a href="#">Wishlist</a>

      <div class="account-menu" id="account-menu">
        <button type="button" class="account-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="account-dropdown">
          <?php if (isset($_SESSION['user_id'])) { ?>
            <img class="account-avatar" src="<?php echo htmlspecialchars(profile_image_url((int)$_SESSION['user_id'])); ?>"
                 alt="" width="28" height="28">
          <?php } ?>
          Account <span aria-hidden="true">&#9662;</span>
        </button>

        <div class="account-dropdown" id="account-dropdown" hidden>
          <?php if (isset($_SESSION['user_id'])) { ?>
            <p class="account-hello">Hi, <?php echo htmlspecialchars($_SESSION['username'] ?? 'there'); ?></p>
            <a href="<?php echo BASE_URL; ?>users/profile.php">My profile</a>
            <a href="<?php echo BASE_URL; ?>orders.php">My orders</a>
            <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
              <a href="<?php echo BASE_URL; ?>admin/index.php">Admin dashboard</a>
            <?php } ?>
            <a class="account-logout" href="<?php echo BASE_URL; ?>users/logout.php">Sign out</a>
          <?php } else { ?>
            <a href="<?php echo BASE_URL; ?>users/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Sign in</a>
            <a href="<?php echo BASE_URL; ?>users/register.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">Create account</a>
          <?php } ?>
        </div>
      </div>
    </div>

    <script>
    (function () {
      var menu  = document.getElementById('account-menu');
      var btn   = menu.querySelector('.account-toggle');
      var panel = document.getElementById('account-dropdown');

      function setOpen(open) {
        panel.hidden = !open;
        btn.setAttribute('aria-expanded', open);
      }

      btn.addEventListener('click', function (e) { e.stopPropagation(); setOpen(panel.hidden); });
      document.addEventListener('click', function (e) { if (!menu.contains(e.target)) setOpen(false); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); btn.focus(); } });
    })();
    </script>
  </header>

  <nav class="sub-nav" aria-label="Men categories">
    <a href="#">New arrivals</a>
    <a href="#">T-shirts</a>
    <a href="#">Shirts</a>
    <a href="#">Pants</a>
    <a href="#">Shorts</a>
    <a href="#">Outerwear</a>
    <a href="#">Innerwear</a>
    <a href="#">Accessories</a>
  </nav>
