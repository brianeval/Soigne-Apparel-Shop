<?php
// Count the items in the cart
$cart_count = 0;
if (isset($_SESSION['cart_products'])) {
    foreach ($_SESSION['cart_products'] as $item) {
        $cart_count = $cart_count + $item['item_qty'];
    }
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
  <link rel="stylesheet" href="styles/homepage.css">
  <?php if (isset($extra_css)) { ?>
    <link rel="stylesheet" href="styles/<?php echo $extra_css; ?>?v=<?php echo time(); ?>">
  <?php } ?>
</head>
<body>

  <header class="site-header">
    <a href="index.php" class="logo">Soigné</a>
    <nav class="main-nav" aria-label="Main">
      <a href="#" class="active">Men</a>
      <a href="#">Women</a>
      <a href="#">Kids</a>
      <a href="#">Sale</a>
    </nav>
    <form class="search" role="search" onsubmit="return false">
      <input type="search" placeholder="Search products" aria-label="Search products">
    </form>
    <div class="icons">
      <a href="#">Account</a>
      <a href="#">Wishlist</a>
      <a href="cart.php">Cart (<?php echo $cart_count; ?>)</a>
    </div>
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
