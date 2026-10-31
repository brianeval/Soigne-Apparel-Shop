<?php
session_start();

$page_title = 'Soigné Premium | Soigné';
include_once('includes/header.php');

$role = $_SESSION['role'] ?? 'guest';
?>
<main class="premium-page">
  <section class="premium-page-card">
    <p class="premium-eyebrow">SOIGNÉ PREMIUM</p>
    <h1>A little more Soigné</h1>
    <p class="premium-price">₱1,000 <span>/ 1 month</span></p>
    <p>Enjoy 10% off every product while your Soigné Premium VIP membership is active.</p>
    <p class="premium-activation-note">Pay through GCash and submit your receipt. An administrator will review the payment and activate VIP after approval.</p>
    <?php if ($role === 'vip') { ?>
      <p class="premium-active-note">Your VIP membership is active. Thank you for purchasing Soigné Premium.</p>
    <?php } elseif ($role === 'customer' || $role === 'guest') { ?>
      <a class="btn premium-buy-now" href="<?php echo BASE_URL; ?>users/premium_checkout.php">Buy Now</a>
    <?php } ?>
  </section>
</main>
<?php include_once('includes/footer.php'); ?>
