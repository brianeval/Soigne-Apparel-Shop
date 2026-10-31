<?php
require_once("../includes/bootstrap.php");

$errors = [];
$requested_customer_view = is_string($_POST['customer_view'] ?? null)
    ? $_POST['customer_view']
    : (is_string($_GET['customer_view'] ?? null) ? $_GET['customer_view'] : '');
$customer_view = in_array($requested_customer_view, ['directory', 'create'], true)
    ? $requested_customer_view
    : (($_POST['action'] ?? '') === 'create' ? 'create' : 'directory');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_verify_csrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $customer_name = trim(is_string($_POST['customer_name'] ?? null) ? $_POST['customer_name'] : '');
        $username = trim(is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
        $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
        $phone = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
        $address = trim(is_string($_POST['address'] ?? null) ? $_POST['address'] : '');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($customer_name === '' || mb_strlen($customer_name) > 120) {
            $errors[] = 'Enter a customer name up to 120 characters.';
        }
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3–50 letters, numbers, or underscores.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            $errors[] = 'Enter a valid email address.';
        }
        if (mb_strlen($phone) > 30 || mb_strlen($address) > 255) {
            $errors[] = 'Phone or address is longer than the allowed limit.';
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors[] = 'Password must be 8–72 characters.';
        }

        if (!$errors) {
            mysqli_begin_transaction($conn);
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $user_stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'customer', 'active')");
                mysqli_stmt_bind_param($user_stmt, 'sss', $username, $email, $password_hash);
                mysqli_stmt_execute($user_stmt);
                $new_user_id = mysqli_insert_id($conn);

                $customer_stmt = mysqli_prepare($conn, "INSERT INTO customers (user_id, customer_name, phone, address) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($customer_stmt, 'isss', $new_user_id, $customer_name, $phone, $address);
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
            $status_update_statement = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE user_id = ? AND role IN ('customer', 'vip')");
            mysqli_stmt_bind_param($status_update_statement, 'si', $new_status, $user_id);
            mysqli_stmt_execute($status_update_statement);
            if (mysqli_stmt_affected_rows($status_update_statement) !== 1) {
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
                    $user_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE user_id = ? AND role IN ('customer', 'vip')");
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

$expire_vip_stmt = mysqli_prepare($conn,
    "UPDATE users SET role = 'customer', vip_expires_at = NULL
     WHERE role = 'vip' AND vip_expires_at IS NOT NULL AND vip_expires_at <= CURRENT_TIMESTAMP"
);
mysqli_stmt_execute($expire_vip_stmt);
$customer_search = mb_substr(trim(is_string($_GET['search'] ?? null) ? $_GET['search'] : ''), 0, 100);
$customer_sql =
    "SELECT u.user_id, u.username, u.email, u.role, u.status, u.vip_expires_at, c.customer_name, c.phone,
            COUNT(DISTINCT o.order_id) AS order_count
     FROM users u
     LEFT JOIN customers c ON c.user_id = u.user_id
     LEFT JOIN orders o ON o.user_id = u.user_id
     WHERE u.role IN ('customer', 'vip')";
if ($customer_search !== '') {
    $customer_sql .= ' AND (c.customer_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR c.phone LIKE ?)';
}
$customer_sql .=
    " GROUP BY u.user_id, u.username, u.email, u.role, u.status, u.vip_expires_at, c.customer_name, c.phone
     ORDER BY u.user_id DESC";
$customer_stmt = mysqli_prepare($conn, $customer_sql);
if ($customer_search !== '') {
    $customer_search_value = '%' . $customer_search . '%';
    mysqli_stmt_bind_param($customer_stmt, 'ssss', $customer_search_value, $customer_search_value, $customer_search_value, $customer_search_value);
}
mysqli_stmt_execute($customer_stmt);
$customers = mysqli_fetch_all(mysqli_stmt_get_result($customer_stmt), MYSQLI_ASSOC);
$customer_total = count($customers);
$flash = admin_take_flash();
$admin_page_title = 'Manage Customers';
$admin_active_page = 'customers';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CUSTOMER ACCOUNTS</p><h1>Manage Customers</h1><p>Create accounts, edit contact details, and manage customer access.</p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-customers/premium-payments.php">Review Premium payments</a>
</section>
<?php if ($flash) { ?><p class="manage-alert is-<?php echo admin_escape($flash['type']); ?>" role="status"><?php echo admin_escape($flash['message']); ?></p><?php } ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo admin_escape($error); ?></li><?php } ?></ul></div><?php } ?>

<div class="admin-panel-tabs" role="radiogroup" aria-label="Customer panels">
  <input type="radio" name="customer_view" id="customer-view-directory" value="directory" <?php echo $customer_view === 'directory' ? 'checked' : ''; ?>>
  <label for="customer-view-directory">Customer Directory</label>
  <input type="radio" name="customer_view" id="customer-view-create" value="create" <?php echo $customer_view === 'create' ? 'checked' : ''; ?>>
  <label for="customer-view-create">Create Customer</label>

<section class="manage-panel customer-create-panel">
  <div class="manage-panel-heading"><div><p class="admin-eyebrow">NEW ACCOUNT</p><h2>Create a customer</h2></div></div>
  <form method="post" class="manage-form manage-product-create" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
    <input type="hidden" name="customer_view" value="create">
    <input type="hidden" name="action" value="create">
    <label>Customer name<input name="customer_name" value="<?php echo admin_escape($_POST['customer_name'] ?? ''); ?>"></label>
    <label>Username<input name="username" value="<?php echo admin_escape($_POST['username'] ?? ''); ?>"></label>
    <label>Email<input type="text" name="email" value="<?php echo admin_escape($_POST['email'] ?? ''); ?>"></label>
    <label>Phone<input name="phone" value="<?php echo admin_escape($_POST['phone'] ?? ''); ?>"></label>
    <label>Temporary password<input type="password" name="password" autocomplete="new-password"></label>
    <label>Address<input name="address" value="<?php echo admin_escape($_POST['address'] ?? ''); ?>"></label>
    <div class="manage-form-wide"><button class="admin-button" type="submit">Create customer</button></div>
  </form>
</section>

<section class="manage-panel manage-list-panel customer-directory-panel">
  <div class="manage-panel-heading">
    <div><p class="admin-eyebrow">CUSTOMER DIRECTORY</p><h2>Customers <span class="manage-count"><?php echo $customer_total; ?></span></h2></div>
    <form method="get" class="inventory-history-search">
      <input type="hidden" name="customer_view" value="directory">
      <label class="visually-hidden" for="customer-search">Search customer</label>
      <input id="customer-search" type="search" name="search" value="<?php echo admin_escape($customer_search); ?>" placeholder="Search customer">
      <button class="admin-button admin-button-light" type="submit">Search</button>
    </form>
  </div>
  <?php if ($customers) { ?>
    <div class="admin-table-wrap"><table class="admin-table">
      <thead><tr><th>Customer</th><th>Username</th><th>Email</th><th>Orders</th><th>Role</th><th>Account</th><th>Actions</th></tr></thead>
      <tbody><?php foreach ($customers as $customer) { ?>
        <tr>
          <td class="order-number"><?php echo admin_escape($customer['customer_name'] ?? 'Customer'); ?><small class="manage-secondary"><?php echo admin_escape($customer['phone'] ?? ''); ?></small></td>
          <td><?php echo admin_escape($customer['username']); ?></td>
          <td><?php echo admin_escape($customer['email']); ?></td>
          <td><?php echo (int)$customer['order_count']; ?></td>
          <td><?php echo admin_escape(['customer' => 'Customer', 'vip' => 'VIP'][$customer['role']] ?? ucfirst($customer['role'])); ?>
            <?php if ($customer['role'] === 'vip' && $customer['vip_expires_at']) { ?><small class="manage-secondary">Until <?php echo admin_escape(date('M j, Y', strtotime($customer['vip_expires_at']))); ?></small><?php } ?>
          </td>
          <td><span class="manage-status-<?php echo admin_escape($customer['status']); ?>"><?php echo ucfirst(admin_escape($customer['status'])); ?></span></td>
          <td><div class="manage-row-actions">
            <a class="manage-small-button" href="<?php echo BASE_URL; ?>admin/manage-customers/edit.php?id=<?php echo (int)$customer['user_id']; ?>">Edit</a>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
              <input type="hidden" name="customer_view" value="directory">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?php echo (int)$customer['user_id']; ?>">
              <input type="hidden" name="new_status" value="<?php echo $customer['status'] === 'active' ? 'inactive' : 'active'; ?>">
              <button class="manage-small-button" type="submit"><?php echo $customer['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?></button>
            </form>
            <?php if ((int)$customer['order_count'] === 0) { ?>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo admin_escape(admin_csrf_token()); ?>">
                <input type="hidden" name="customer_view" value="directory">
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
</div>
<?php include("../includes/footer.php"); ?>
