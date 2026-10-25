<?php
include ('config.php');
require_once ('profile_image.php');
require_once __DIR__ . '/customer_auth.php';

$customer_role = sync_customer_session($conn);

// Count the signed-in user's cart items.
// This query totals the quantity of all items in the current user's cart for the cart badge.
$cart_count = 0;
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['customer', 'vip'], true)) {
    $stmt = mysqli_prepare($conn,
        "SELECT COALESCE(SUM(c.quantity), 0) AS item_count
         FROM cart c
         JOIN users u ON u.user_id = c.user_id
         WHERE c.user_id = ? AND u.role IN ('customer', 'vip') AND u.status = 'active'"
    );
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
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>styles/homepage.css?v=<?php echo filemtime(__DIR__ . '/../styles/homepage.css'); ?>">
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
    <form class="search" role="search" method="get" action="<?php echo BASE_URL; ?>search.php">
      <input type="search" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" placeholder="Search products/brands" aria-label="Search products or brands">
    </form>
    <div class="icons">
      <a href="<?php echo BASE_URL; ?>cart/cart.php">🛒 Cart (<?php echo $cart_count; ?>)</a>

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
            <a href="<?php echo BASE_URL; ?>orders/orders.php">My orders</a>
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
    <button type="button" class="premium-nav-trigger" data-premium-open>Soigné Premium</button>

  
  </nav>
  <?php if ($customer_role === 'vip' && !empty($_SESSION['vip_expires_at'])) { ?>
    <p class="premium-member-notice" role="status">
      Soigné Premium is active through <?php echo htmlspecialchars(date('F j, Y', strtotime($_SESSION['vip_expires_at']))); ?>.
      Thank you for purchasing VIP — your 10% product discount is applied automatically.
    </p>
  <?php } ?>
  <dialog class="premium-dialog" id="premium-dialog" aria-labelledby="premium-dialog-title">
    <button type="button" class="premium-dialog-close" data-premium-close aria-label="Close Soigné Premium details">&times;</button>
    <p class="premium-eyebrow">SOIGNÉ PREMIUM</p>
    <h2 id="premium-dialog-title">A little more Soigné</h2>
    <p class="premium-price">₱1,000 <span>/ 1 month</span></p>
    <p>Enjoy 10% off every product while your Soigné Premium VIP membership is active.</p>
    <p class="premium-activation-note">Pay through GCash and submit your receipt. An administrator will review the payment and activate VIP after approval.</p>
    <?php if ($customer_role === 'vip') { ?>
      <p class="premium-active-note">Your VIP membership is active. Thank you for purchasing Soigné Premium.</p>
    <?php } elseif (in_array($customer_role, ['customer', 'guest'], true)) { ?>
      <a class="btn premium-buy-now" href="<?php echo BASE_URL; ?>users/premium_checkout.php">Buy Now</a>
    <?php } ?>
  </dialog>
  <script>
  (function () {
    var dialog = document.getElementById('premium-dialog');
    var openButton = document.querySelector('[data-premium-open]');
    var closeButton = document.querySelector('[data-premium-close]');
    if (!dialog || !openButton || !closeButton) return;
    openButton.addEventListener('click', function () { dialog.showModal(); });
    closeButton.addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close();
    });
  })();
  </script>
