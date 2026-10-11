<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $name = trim(is_string($_POST['customer_name'] ?? null) ? $_POST['customer_name'] : '');
        $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
        $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
        $phone = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
        $address = trim(is_string($_POST['address'] ?? null) ? $_POST['address'] : '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Enter a customer name up to 120 characters.';
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) $errors[] = 'Username must be 3–50 letters, numbers, or underscores.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $errors[] = 'Enter a valid email address.';
        if (mb_strlen($phone) > 30 || mb_strlen($address) > 255) $errors[] = 'Phone or address is longer than the allowed limit.';
        if (strlen($password) < 8 || strlen($password) > 72) $errors[] = 'Password must be 8–72 characters.';

        if (!$errors) {
            mysqli_begin_transaction($conn);
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $user_stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'customer', 'active')");
                mysqli_stmt_bind_param($user_stmt, 'sss', $username, $email, $password_hash);
                mysqli_stmt_execute($user_stmt);
                $new_user_id = mysqli_insert_id($conn);

                $customer_stmt = mysqli_prepare($conn, "INSERT INTO customers (user_id, customer_name, phone, address) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($customer_stmt, 'isss', $new_user_id, $name, $phone, $address);
                mysqli_stmt_execute($customer_stmt);
                mysqli_commit($conn);
                admin_set_flash('success', 'Customer account created.');
                header('Location: ' . BASE_URL . 'admin/manage-customers/');
                exit;
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                $errors[] = $exception->getCode() === 1062 ? 'That username or email is already in use.' : 'Could not create this customer account.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'toggle_status') {
        $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        $new_status = $_POST['new_status'] ?? '';
        if ($user_id === false || $user_id < 1 || !in_array($new_status, ['active', 'inactive'], true)) {
            $errors[] = 'Choose a valid customer account status.';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ? AND role = 'customer'");
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $user_id);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                $errors[] = 'Customer not found or already has that status.';
            } else {
                admin_set_flash('success', $new_status === 'active' ? 'Customer account reactivated.' : 'Customer account deactivated.');
                header('Location: ' . BASE_URL . 'admin/manage-customers/');
                exit;
            }
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($user_id === false || $user_id < 1) {
            $errors[] = 'Choose a valid customer.';
        } else {
            $orders_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS order_count FROM orders WHERE user_id = ?");
            mysqli_stmt_bind_param($orders_stmt, 'i', $user_id);
            mysqli_stmt_execute($orders_stmt);
            $order_count = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($orders_stmt))['order_count'];
            if ($order_count > 0) {
                $errors[] = 'This customer has order history. Deactivate the account instead of deleting it.';
            } else {
                mysqli_begin_transaction($conn);
                try {
                    $cart_stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
                    mysqli_stmt_bind_param($cart_stmt, 'i', $user_id);
                    mysqli_stmt_execute($cart_stmt);
                    $customer_stmt = mysqli_prepare($conn, "DELETE FROM customers WHERE user_id = ?");
                    mysqli_stmt_bind_param($customer_stmt, 'i', $user_id);
                    mysqli_stmt_execute($customer_stmt);
                    $user_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ? AND role = 'customer'");
                    mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
                    mysqli_stmt_execute($user_stmt);
                    if (mysqli_stmt_affected_rows($user_stmt) !== 1) {
                        throw new mysqli_sql_exception('Customer account was not found.');
                    }
                    mysqli_commit($conn);
                    admin_set_flash('success', 'Customer account permanently deleted.');
                    header('Location: ' . BASE_URL . 'admin/manage-customers/');
                    exit;
                } catch (mysqli_sql_exception $exception) {
                    mysqli_rollback($conn);
                    $errors[] = 'Could not delete this customer because the account is still referenced by store data.';
                }
            }
        }
    } else {
        $errors[] = 'That customer action is not valid.';
    }
}

$result = mysqli_query($conn,
    "SELECT u.user_id, u.username, u.email, u.status, c.customer_name, c.phone,
            COUNT(DISTINCT o.order_id) AS order_count
     FROM users u
     LEFT JOIN customers c ON c.user_id = u.user_id
     LEFT JOIN orders o ON o.user_id = u.user_id
     WHERE u.role = 'customer'
     GROUP BY u.user_id, u.username, u.email, u.status, c.customer_name, c.phone
     ORDER BY u.user_id DESC"
);
$customers = mysqli_fetch_all($result, MYSQLI_ASSOC);
$flash = admin_take_flash();
$admin_page_title = 'Manage Customers';
$admin_active_page = 'customers';
include __DIR__ . '/../includes/header.php';
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CUSTOMER ACCOUNTS</p><h1>Manage Customers</h1><p>Create accounts, edit contact details, and manage customer access.</p></div>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>

<section class="manage-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">NEW ACCOUNT</p><h2>Create a customer</h2></div></div>
  <form method="post" class="manage-form manage-product-create">
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="action" value="create">
    <label>Customer name<input name="customer_name" maxlength="120" required value="<?php echo admin_escape($_POST['customer_name'] ?? ''); ?>"></label>
    <label>Username<input name="username" maxlength="50" required value="<?php echo admin_escape($_POST['username'] ?? ''); ?>"></label>
    <label>Email<input type="email" name="email" maxlength="120" required value="<?php echo admin_escape($_POST['email'] ?? ''); ?>"></label>
    <label>Phone<input name="phone" maxlength="30" value="<?php echo admin_escape($_POST['phone'] ?? ''); ?>"></label>
    <label>Temporary password<input type="password" name="password" minlength="8" maxlength="72" required autocomplete="new-password"></label>
    <label>Address<input name="address" maxlength="255" value="<?php echo admin_escape($_POST['address'] ?? ''); ?>"></label>
    <div class="manage-form-wide"><button class="admin-button" type="submit">Create customer</button></div>
  </form>
</section>

<section class="manage-panel manage-list-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">CUSTOMER DIRECTORY</p><h2>Customers <span class="manage-count"><?php echo count($customers); ?></span></h2></div></div>
  <?php if ($customers) { ?>
    <div class="admin-table-wrap"><table class="admin-table">
      <thead><tr><th>Customer</th><th>Username</th><th>Email</th><th>Orders</th><th>Account</th><th>Actions</th></tr></thead>
      <tbody><?php foreach ($customers as $customer) { ?>
        <tr>
          <td class="order-number"><?php echo admin_escape($customer['customer_name'] ?? 'Customer'); ?><small class="manage-secondary"><?php echo admin_escape($customer['phone'] ?? ''); ?></small></td>
          <td><?php echo admin_escape($customer['username']); ?></td>
          <td><?php echo admin_escape($customer['email']); ?></td>
          <td><?php echo (int)$customer['order_count']; ?></td>
          <td><span class="manage-status-<?php echo admin_escape($customer['status']); ?>"><?php echo ucfirst(admin_escape($customer['status'])); ?></span></td>
          <td><div class="manage-row-actions">
            <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-customers/edit.php?id=<?php echo (int)$customer['user_id']; ?>">Edit</a>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?php echo (int)$customer['user_id']; ?>">
              <input type="hidden" name="new_status" value="<?php echo $customer['status'] === 'active' ? 'inactive' : 'active'; ?>">
              <button class="manage-small-button" type="submit"><?php echo $customer['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?></button>
            </form>
            <?php if ((int)$customer['order_count'] === 0) { ?>
              <form method="post" onsubmit="return confirm('Permanently delete this customer account? Their cart will also be removed.');">
                <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="<?php echo (int)$customer['user_id']; ?>">
                <button class="manage-small-button is-danger" type="submit">Delete</button>
              </form>
            <?php } ?>
          </div></td>
        </tr>
      <?php } ?></tbody>
    </table></div>
  <?php } else { ?><p class="manage-empty">No customer accounts found.</p><?php } ?>
</section>
<?php include __DIR__ . '/../includes/footer.php'; ?>
