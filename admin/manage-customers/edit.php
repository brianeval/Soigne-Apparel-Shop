<?php
require_once "../includes/bootstrap.php";

$user_id = (int)($_GET['id'] ?? 0);
if ($user_id < 1) {
    http_response_code(404);
    exit('Customer not found.');
}

$customer = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT u.user_id, u.username, u.email, u.role, u.status, u.vip_expires_at, c.customer_name, c.phone, c.address
     FROM users u
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE u.user_id = $user_id AND u.role IN ('customer', 'vip')"
));
if (!$customer) {
    http_response_code(404);
    exit('Customer not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';
    $renew_vip = ($_POST['renew_vip'] ?? '') === '1';

    if ($customer_name === '' || $username === '' || $email === '') {
        $errors[] = 'Enter the customer name, username, and email.';
    }
    if (!in_array($role, ['customer', 'vip'], true)) {
        $errors[] = 'Choose a valid customer role.';
    }

    if (!$errors) {
        $vip_expires_at = null;
        if ($role === 'vip') {
            $current_expiry = $customer['vip_expires_at'] ? strtotime($customer['vip_expires_at']) : 0;
            $extend = $renew_vip || $customer['role'] !== 'vip' || $current_expiry <= time();
            $base = $current_expiry > time() ? $current_expiry : time();
            $vip_expires_at = $extend ? date('Y-m-d H:i:s', strtotime('+1 month', $base)) : $customer['vip_expires_at'];
        }

        $name_sql = mysqli_real_escape_string($conn, $customer_name);
        $username_sql = mysqli_real_escape_string($conn, $username);
        $email_sql = mysqli_real_escape_string($conn, $email);
        $phone_sql = mysqli_real_escape_string($conn, $phone);
        $address_sql = mysqli_real_escape_string($conn, $address);
        $vip_sql = $vip_expires_at === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $vip_expires_at) . "'";

        try {
            mysqli_query($conn,
                "UPDATE users SET username = '$username_sql', email = '$email_sql',
                        role = '$role', vip_expires_at = $vip_sql
                 WHERE user_id = $user_id AND role IN ('customer', 'vip')"
            );
            mysqli_query($conn,
                "INSERT INTO customers (user_id, customer_name, phone, address)
                 VALUES ($user_id, '$name_sql', '$phone_sql', '$address_sql')
                 ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name),
                     phone = VALUES(phone), address = VALUES(address)"
            );
            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conn, "UPDATE users SET password = '$password_hash' WHERE user_id = $user_id");
            }

            if ($role === 'vip' && $renew_vip) {
                $message = 'Customer updated. Soigné Premium VIP has been renewed for one month.';
            } elseif ($role === 'vip' && $vip_expires_at) {
                $message = 'Customer updated. VIP is active until ' . date('M j, Y', strtotime($vip_expires_at)) . '.';
            } else {
                $message = 'Customer details updated.';
            }
            admin_set_flash('success', $message);
            header('Location: ' . BASE_URL . 'admin/manage-customers/');
            exit;
        } catch (mysqli_sql_exception $exception) {
            $errors[] = 'That username or email is already in use.';
        }
    }
}

$flash = admin_take_flash();
$admin_page_title = 'Edit Customer';
$admin_active_page = 'customers';
include "../includes/header.php";
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CUSTOMER DIRECTORY</p><h1>Edit Customer</h1><p>Update this account’s name, login details, and contact information.</p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-customers/">&larr; All customers</a>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>
<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">ACCOUNT #<?php echo (int)$user_id; ?></p><h2><?php echo admin_escape($customer['customer_name'] ?? $customer['username']); ?></h2></div></div>
  <form method="post" class="manage-form manage-product-create" novalidate>
    <label>Role
      <select name="role">
        <?php
        $selected_role = $_POST['role'] ?? $customer['role'];
        foreach (['customer' => 'Customer', 'vip' => 'VIP'] as $role_value => $role_label) {
        ?>
          <option value="<?php echo admin_escape($role_value); ?>" <?php echo $selected_role === $role_value ? 'selected' : ''; ?>><?php echo admin_escape($role_label); ?></option>
        <?php } ?>
      </select>
      <small class="manage-secondary">VIP includes 10% off products for one month after activation. Deactivate an account from the customer list to prevent sign-in.</small>
      <?php if ($customer['role'] === 'vip' && $customer['vip_expires_at']) { ?><small class="manage-secondary">VIP active until <?php echo admin_escape(date('M j, Y', strtotime($customer['vip_expires_at']))); ?>.</small><?php } ?>
    </label>
    <?php if ($customer['role'] === 'vip') { ?>
      <label class="manage-checkbox-label"><span><input type="checkbox" name="renew_vip" value="1" <?php echo ($_POST['renew_vip'] ?? '') === '1' ? 'checked' : ''; ?>> Renew VIP for one month (confirm new purchase)</span></label>
    <?php } ?>
    <label>Customer name<input name="customer_name" value="<?php echo admin_escape($_POST['customer_name'] ?? $customer['customer_name']); ?>"></label>
    <label>Username<input name="username" value="<?php echo admin_escape($_POST['username'] ?? $customer['username']); ?>"></label>
    <label>Email<input type="text" name="email" value="<?php echo admin_escape($_POST['email'] ?? $customer['email']); ?>"></label>
    <label>Phone<input name="phone" value="<?php echo admin_escape($_POST['phone'] ?? $customer['phone']); ?>"></label>
    <label>New password<input type="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password"></label>
    <label>Address<input name="address" value="<?php echo admin_escape($_POST['address'] ?? $customer['address']); ?>"></label>
    <div class="manage-form-wide manage-form-buttons"><button class="admin-button" type="submit">Save customer</button><a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-customers/">Cancel</a></div>
  </form>
</section>
<?php include "../includes/footer.php"; ?>
