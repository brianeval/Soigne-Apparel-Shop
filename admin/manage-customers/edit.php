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
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $renew_vip = ($_POST['renew_vip'] ?? '') == '1';

    // Basic checks
    if ($customer_name == '') {
        $errors[] = 'Enter a customer name.';
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3–50 letters, numbers, or underscores.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($password != '' && strlen($password) < 8) {
        $errors[] = 'A new password must be at least 8 characters.';
    }
    if (!in_array($role, ['customer', 'vip'])) {
        $errors[] = 'Choose a valid customer role.';
    }

    if (!$errors) {
        // VIP lasts one month. It is (re)started when the customer becomes VIP,
        // when the membership already ended, or when "Renew" is ticked.
        $vip_is_active = $customer['role'] == 'vip' && $customer['vip_expires_at'] && strtotime($customer['vip_expires_at']) > time();
        if ($role == 'customer') {
            $vip_sql = "vip_expires_at = NULL";
        } elseif ($renew_vip || !$vip_is_active) {
            $vip_sql = "vip_expires_at = DATE_ADD(GREATEST(COALESCE(vip_expires_at, CURRENT_TIMESTAMP), CURRENT_TIMESTAMP), INTERVAL 1 MONTH)";
        } else {
            $vip_sql = "vip_expires_at = vip_expires_at";
        }

        try {
            mysqli_begin_transaction($conn);
            $user_result = mysqli_execute_query($conn, "UPDATE users SET username = ?, email = ?, role = ?, $vip_sql WHERE user_id = ?", [$username, $email, $role, $user_id]);

            $customer_result = mysqli_execute_query($conn, "INSERT INTO customers (user_id, customer_name, phone, address)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name), phone = VALUES(phone), address = VALUES(address)", [$user_id, $customer_name, $phone, $address]);

            if ($password != '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $password_result = mysqli_execute_query($conn, "UPDATE users SET password = ? WHERE user_id = ?", [$password_hash, $user_id]);
            }
            mysqli_commit($conn);

            if ($role == 'vip' && $renew_vip) {
                $text = 'Customer updated. Soigné Premium VIP has been renewed for one month.';
            } elseif ($role == 'vip' && $vip_is_active) {
                $text = 'Customer updated. VIP remains active until ' . date('M j, Y', strtotime($customer['vip_expires_at'])) . '.';
            } elseif ($role == 'vip') {
                $text = 'Customer updated. Soigné Premium VIP is active for one month.';
            } else {
                $text = 'Customer details updated.';
            }
            $_SESSION['success'] = $text;
            header('Location: ' . BASE_URL . 'admin/manage-customers/');
            exit;
        } catch (mysqli_sql_exception $exception) {
            mysqli_rollback($conn);
            if ($exception->getCode() == 1062) {
                $errors[] = 'That username or email is already in use.';
            } else {
                $errors[] = 'Could not update this customer.';
            }
        }
    }
}

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
