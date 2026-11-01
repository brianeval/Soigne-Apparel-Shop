<?php
require_once("../includes/bootstrap.php");

$user_id = (int)($_GET['id'] ?? 0);

$customer_result = mysqli_execute_query($conn,
    "SELECT u.user_id, u.username, u.email, u.role, u.status, u.vip_expires_at, c.customer_name, c.phone, c.address
     FROM users u
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE u.user_id = ? AND u.role IN ('customer', 'vip')",
    [$user_id]
);
$customer = mysqli_fetch_assoc($customer_result);
if (!$customer) {
    http_response_code(404);
    exit('Customer not found.');
}

$errors = [];
include(__DIR__ . '/customers_update.php');

$admin_page_title = 'Edit Customer';
$admin_active_page = 'customers';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CUSTOMER DIRECTORY</p><h1>Edit Customer</h1><p>Update this account’s name, login details, and contact information.</p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-customers/">&larr; All customers</a>
</section>
<?php include("../../includes/alert.php"); ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error); ?></li><?php } ?></ul></div><?php } ?>
<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">ACCOUNT #<?php echo (int)$user_id; ?></p><h2><?php echo htmlspecialchars($customer['customer_name'] ?? $customer['username']); ?></h2></div></div>
  <form method="post" class="manage-form manage-product-create" novalidate>
    <label>Role
      <select name="role">
        <?php
        $selected_role = $_POST['role'] ?? $customer['role'];
        foreach (['customer' => 'Customer', 'vip' => 'VIP'] as $role_value => $role_label) {
        ?>
          <option value="<?php echo htmlspecialchars($role_value); ?>" <?php echo $selected_role === $role_value ? 'selected' : ''; ?>><?php echo htmlspecialchars($role_label); ?></option>
        <?php } ?>
      </select>
      <small class="manage-secondary">VIP includes 10% off products for one month after activation. Deactivate an account from the customer list to prevent sign-in.</small>
      <?php if ($customer['role'] === 'vip' && $customer['vip_expires_at']) { ?><small class="manage-secondary">VIP active until <?php echo htmlspecialchars(date('M j, Y', strtotime($customer['vip_expires_at']))); ?>.</small><?php } ?>
    </label>
    <?php if ($customer['role'] === 'vip') { ?>
      <label class="manage-checkbox-label"><span><input type="checkbox" name="renew_vip" value="1" <?php echo ($_POST['renew_vip'] ?? '') === '1' ? 'checked' : ''; ?>> Renew VIP for one month (confirm new purchase)</span></label>
    <?php } ?>
    <label>Customer name<input name="customer_name" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? $customer['customer_name'] ?? ''); ?>"></label>
    <label>Username<input name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? $customer['username']); ?>"></label>
    <label>Email<input type="text" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $customer['email']); ?>"></label>
    <label>Phone<input name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? $customer['phone'] ?? ''); ?>"></label>
    <label>New password<input type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password"></label>
    <label>Address<input name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? $customer['address'] ?? ''); ?>"></label>
    <div class="manage-form-wide manage-form-buttons"><button class="admin-button" type="submit">Save customer</button><a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-customers/">Cancel</a></div>
  </form>
</section>
<?php include("../includes/footer.php"); ?>
