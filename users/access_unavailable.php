<?php
session_start();
include_once __DIR__ . '/../includes/config.php';
$page_title = 'Account Suspended | Soigné';
include_once __DIR__ . '/../includes/header.php';
?>
<main class="access-unavailable">
  <h1>Account Suspended</h1>
  <p>This account is suspended. You can still browse products and add them to your cart, but you cannot place an order.</p>
  <a class="btn" href="<?php echo BASE_URL; ?>index.php">Return to home</a>
</main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
