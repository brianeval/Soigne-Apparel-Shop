<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$user_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($user_id === false || $user_id < 1) {
    http_response_code(404);
    exit('Customer not found.');
}

$stmt = mysqli_prepare($conn,
    "SELECT u.user_id, u.username, u.email, u.role, u.status, u.vip_expires_at, c.customer_name, c.phone, c.address
     FROM users u
     LEFT JOIN customers c ON c.user_id = u.user_id
     WHERE u.user_id = ? AND u.role IN ('customer', 'vip', 'banned')"
);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$customer) {
    http_response_code(404);
    exit('Customer not found.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        $name = trim(is_string($_POST['customer_name'] ?? null) ? $_POST['customer_name'] : '');
        $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
        $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
        $phone = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
        $address = trim(is_string($_POST['address'] ?? null) ? $_POST['address'] : '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $role = is_string($_POST['role'] ?? null) ? $_POST['role'] : '';
        $renew_vip = ($_POST['renew_vip'] ?? '') === '1';

        if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Enter a customer name up to 120 characters.';
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) $errors[] = 'Username must be 3–50 letters, numbers, or underscores.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $errors[] = 'Enter a valid email address.';
        if (mb_strlen($phone) > 30 || mb_strlen($address) > 255) $errors[] = 'Phone or address is longer than the allowed limit.';
        if ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)) $errors[] = 'A new password must be 8–72 characters.';
        if (!in_array($role, ['customer', 'vip', 'banned'], true)) $errors[] = 'Choose a valid customer role.';

        if (!$errors) {
            mysqli_begin_transaction($conn);
            try {
                $user_stmt = mysqli_prepare($conn,
                    "UPDATE users
                     SET username = ?, email = ?,
                         vip_expires_at = CASE
                           WHEN ? = 'vip' AND (? = 1 OR role <> 'vip' OR vip_expires_at IS NULL OR vip_expires_at <= CURRENT_TIMESTAMP)
                             THEN DATE_ADD(GREATEST(COALESCE(vip_expires_at, CURRENT_TIMESTAMP), CURRENT_TIMESTAMP), INTERVAL 1 MONTH)
                           WHEN ? = 'vip' THEN vip_expires_at
                           ELSE NULL
                         END,
                         role = ?
                     WHERE user_id = ? AND role IN ('customer', 'vip', 'banned')"
                );
                mysqli_stmt_bind_param($user_stmt, 'sssissi', $username, $email, $role, $renew_vip, $role, $role, $user_id);
                mysqli_stmt_execute($user_stmt);
                $customer_stmt = mysqli_prepare($conn,
                    "INSERT INTO customers (user_id, customer_name, phone, address)
                     VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name), phone = VALUES(phone), address = VALUES(address)"
                );
                mysqli_stmt_bind_param($customer_stmt, 'isss', $user_id, $name, $phone, $address);
                mysqli_stmt_execute($customer_stmt);
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $password_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ? AND role IN ('customer', 'vip', 'banned')");
                    mysqli_stmt_bind_param($password_stmt, 'si', $password_hash, $user_id);
                    mysqli_stmt_execute($password_stmt);
                }
                mysqli_commit($conn);
                if ($role === 'vip' && $renew_vip) {
                    $message = 'Customer updated. Soigné Premium VIP has been renewed for one month.';
                } elseif ($role === 'vip' && $customer['role'] === 'vip' && $customer['vip_expires_at'] && strtotime($customer['vip_expires_at']) > time()) {
                    $message = 'Customer updated. VIP remains active until ' . date('M j, Y', strtotime($customer['vip_expires_at'])) . '.';
                } elseif ($role === 'vip') {
                    $message = 'Customer updated. Soigné Premium VIP is active for one month.';
                } elseif ($role === 'banned') {
                    $message = 'Customer updated. This account is suspended and cannot place orders.';
                } else {
                    $message = 'Customer details updated.';
                }
                admin_set_flash('success', $message);
                header('Location: ' . BASE_URL . 'admin/manage-customers/');
                exit;
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                $errors[] = $exception->getCode() === 1062 ? 'That username or email is already in use.' : 'Could not update this customer.';
            }
        }
    }
}

$flash = admin_take_flash();
$admin_page_title = 'Edit Customer';
$admin_active_page = 'customers';
include __DIR__ . '/../includes/header.php';
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CUSTOMER DIRECTORY</p><h1>Edit Customer</h1><p>Update this account’s name, login details, and contact information.</p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-customers/">&larr; All customers</a>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>
<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">ACCOUNT #<?php echo (int)$user_id; ?></p><h2><?php echo admin_escape($customer['customer_name'] ?? $customer['username']); ?></h2></div></div>
  <form method="post" class="manage-form manage-product-create">
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <label>Role
      <select name="role" required>
        <?php
        $selected_role = $_POST['role'] ?? $customer['role'];
        foreach (['customer' => 'Customer', 'vip' => 'VIP', 'banned' => 'Suspended'] as $role_value => $role_label) {
        ?>
          <option value="<?php echo admin_escape($role_value); ?>" <?php echo $selected_role === $role_value ? 'selected' : ''; ?>><?php echo admin_escape($role_label); ?></option>
        <?php } ?>
      </select>
      <small class="manage-secondary">VIP includes 10% off products for one month after activation. Suspended customers can browse and add to cart, but cannot place orders.</small>
      <?php if ($customer['role'] === 'vip' && $customer['vip_expires_at']) { ?><small class="manage-secondary">VIP active until <?php echo admin_escape(date('M j, Y', strtotime($customer['vip_expires_at']))); ?>.</small><?php } ?>
    </label>
    <?php if ($customer['role'] === 'vip') { ?>
      <label class="manage-checkbox-label"><span><input type="checkbox" name="renew_vip" value="1" <?php echo ($_POST['renew_vip'] ?? '') === '1' ? 'checked' : ''; ?>> Renew VIP for one month (confirm new purchase)</span></label>
    <?php } ?>
    <label>Customer name<input name="customer_name" maxlength="120" required value="<?php echo admin_escape($_POST['customer_name'] ?? $customer['customer_name']); ?>"></label>
    <label>Username<input name="username" maxlength="50" required value="<?php echo admin_escape($_POST['username'] ?? $customer['username']); ?>"></label>
    <label>Email<input type="email" name="email" maxlength="120" required value="<?php echo admin_escape($_POST['email'] ?? $customer['email']); ?>"></label>
    <label>Phone<input name="phone" maxlength="30" value="<?php echo admin_escape($_POST['phone'] ?? $customer['phone']); ?>"></label>
    <label>New password<input type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="Leave blank to keep current password"></label>
    <label>Address<input name="address" maxlength="255" value="<?php echo admin_escape($_POST['address'] ?? $customer['address']); ?>"></label>
    <div class="manage-form-wide manage-form-buttons"><button class="admin-button" type="submit">Save customer</button><a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-customers/">Cancel</a></div>
  </form>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
