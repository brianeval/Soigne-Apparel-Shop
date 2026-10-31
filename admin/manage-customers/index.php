<?php
require_once "../includes/bootstrap.php";

$errors = [];
$customer_view = $_POST['customer_view'] ?? $_GET['customer_view'] ?? 'directory';
if (!in_array($customer_view, ['directory', 'create'], true)) {
    $customer_view = 'directory';
}
if (($_POST['action'] ?? '') === 'create') {
    $customer_view = 'create';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($customer_name === '' || $username === '' || $email === '' || $password === '') {
            $errors[] = 'Enter the customer name, username, email, and password.';
        } else {
            try {
                $name_sql = mysqli_real_escape_string($conn, $customer_name);
                $username_sql = mysqli_real_escape_string($conn, $username);
                $email_sql = mysqli_real_escape_string($conn, $email);
                $phone_sql = mysqli_real_escape_string($conn, $phone);
                $address_sql = mysqli_real_escape_string($conn, $address);
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                mysqli_query($conn,
                    "INSERT INTO users (username, email, password, role, status)
                     VALUES ('$username_sql', '$email_sql', '$password_hash', 'customer', 'active')"
                );
                $new_user_id = mysqli_insert_id($conn);
                mysqli_query($conn,
                    "INSERT INTO customers (user_id, customer_name, phone, address)
                     VALUES ($new_user_id, '$name_sql', '$phone_sql', '$address_sql')"
                );
                admin_set_flash('success', 'Customer account created.');
                header('Location: ' . BASE_URL . 'admin/manage-customers/');
                exit;
            } catch (mysqli_sql_exception $exception) {
                $errors[] = 'That username or email is already in use.';
            }
        }
    } elseif ($action === 'toggle_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        if ($user_id < 1 || !in_array($new_status, ['active', 'inactive'], true)) {
            $errors[] = 'Choose a valid customer account status.';
        } else {
            mysqli_query($conn, "UPDATE users SET status = '$new_status' WHERE user_id = $user_id AND role IN ('customer', 'vip')");
            admin_set_flash('success', $new_status === 'active' ? 'Customer account reactivated.' : 'Customer account deactivated.');
            header('Location: ' . BASE_URL . 'admin/manage-customers/');
            exit;
        }
    } elseif ($action === 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $order_count = $user_id > 0 ? (int)mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS order_count FROM orders WHERE user_id = $user_id"
        ))['order_count'] : 0;

        if ($user_id < 1) {
            $errors[] = 'Choose a valid customer.';
        } elseif ($order_count > 0) {
            $errors[] = 'This customer has order history. Deactivate the account instead of deleting it.';
        } else {
            try {
                mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");
                mysqli_query($conn, "DELETE FROM customers WHERE user_id = $user_id");
                mysqli_query($conn, "DELETE FROM users WHERE user_id = $user_id AND role IN ('customer', 'vip')");
                admin_set_flash('success', 'Customer account permanently deleted.');
                header('Location: ' . BASE_URL . 'admin/manage-customers/');
                exit;
            } catch (mysqli_sql_exception $exception) {
                $errors[] = 'Could not delete this customer because the account is still referenced by store data.';
            }
        }
    } else {
        $errors[] = 'That customer action is not valid.';
    }
}

mysqli_query($conn,
    "UPDATE users SET role = 'customer', vip_expires_at = NULL
     WHERE role = 'vip' AND vip_expires_at IS NOT NULL AND vip_expires_at <= CURRENT_TIMESTAMP"
);

$customer_search = trim($_GET['search'] ?? '');
$customer_where = '';
if ($customer_search !== '') {
    $q = mysqli_real_escape_string($conn, $customer_search);
    $customer_where = " AND (c.customer_name LIKE '%$q%' OR u.username LIKE '%$q%'
        OR u.email LIKE '%$q%' OR c.phone LIKE '%$q%')";
}
$customers = mysqli_fetch_all(mysqli_query($conn,
    "SELECT u.user_id, u.username, u.email, u.role, u.status, u.vip_expires_at, c.customer_name, c.phone,
            COUNT(DISTINCT o.order_id) AS order_count
     FROM users u
     LEFT JOIN customers c ON c.user_id = u.user_id
     LEFT JOIN orders o ON o.user_id = u.user_id
     WHERE u.role IN ('customer', 'vip') $customer_where
     GROUP BY u.user_id, u.username, u.email, u.role, u.status, u.vip_expires_at, c.customer_name, c.phone
     ORDER BY u.user_id DESC"
), MYSQLI_ASSOC);
$customer_total = count($customers);
$flash = admin_take_flash();
$admin_page_title = 'Manage Customers';
$admin_active_page = 'customers';
include "../includes/header.php";
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
              <input type="hidden" name="customer_view" value="directory">
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="user_id" value="<?php echo (int)$customer['user_id']; ?>">
              <input type="hidden" name="new_status" value="<?php echo $customer['status'] === 'active' ? 'inactive' : 'active'; ?>">
              <button class="manage-small-button" type="submit"><?php echo $customer['status'] === 'active' ? 'Deactivate' : 'Reactivate'; ?></button>
            </form>
            <?php if ((int)$customer['order_count'] === 0) { ?>
              <form method="post">
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
<?php include "../includes/footer.php"; ?>
