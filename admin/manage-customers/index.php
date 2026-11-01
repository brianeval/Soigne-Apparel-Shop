<?php
require_once("../includes/bootstrap.php");

$errors = [];
$customer_view = $_POST['customer_view'] ?? $_GET['customer_view'] ?? 'directory';
if (!in_array($customer_view, ['directory', 'create'])) {
    $customer_view = 'directory';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == 'create') {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';

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
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if (!$errors) {
            try {
                mysqli_begin_transaction($conn);
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $user_result = mysqli_execute_query($conn, "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'customer', 'active')", [$username, $email, $password_hash]);
                $new_user_id = mysqli_insert_id($conn);

                $customer_result = mysqli_execute_query($conn, "INSERT INTO customers (user_id, customer_name, phone, address) VALUES (?, ?, ?, ?)", [$new_user_id, $customer_name, $phone, $address]);
                mysqli_commit($conn);

                $_SESSION['success'] = 'Customer account created.';
                header('Location: ' . BASE_URL . 'admin/manage-customers/');
                exit;
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                if ($exception->getCode() == 1062) {
                    $errors[] = 'That username or email is already in use.';
                } else {
                    $errors[] = 'Could not create this customer account.';
                }
            }
        }
    } elseif ($action == 'toggle_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        if ($user_id < 1 || !in_array($new_status, ['active', 'inactive'])) {
            $errors[] = 'Choose a valid customer account status.';
        } else {
            $status_result = mysqli_execute_query($conn, "UPDATE users SET status = ? WHERE user_id = ? AND role IN ('customer', 'vip')", [$new_status, $user_id]);
            if (mysqli_affected_rows($conn) != 1) {
                $errors[] = 'Customer not found or already has that status.';
            } else {
                if ($new_status == 'active') {
                    $text = 'Customer account reactivated.';
                } else {
                    $text = 'Customer account deactivated.';
                }
                $_SESSION['success'] = $text;
                header('Location: ' . BASE_URL . 'admin/manage-customers/');
                exit;
            }
        }
    } elseif ($action == 'delete') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $order_result = mysqli_query($conn, "SELECT COUNT(*) AS order_count FROM orders WHERE user_id = $user_id");
        $order_count = (int)mysqli_fetch_assoc($order_result)['order_count'];
        if ($user_id < 1) {
            $errors[] = 'Choose a valid customer.';
        } elseif ($order_count > 0) {
            $errors[] = 'This customer has order history. Deactivate the account instead of deleting it.';
        } else {
            try {
                mysqli_begin_transaction($conn);
                mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");
                mysqli_query($conn, "DELETE FROM customers WHERE user_id = $user_id");
                mysqli_query($conn, "DELETE FROM users WHERE user_id = $user_id AND role IN ('customer', 'vip')");
                mysqli_commit($conn);
                $_SESSION['success'] = 'Customer account permanently deleted.';
                header('Location: ' . BASE_URL . 'admin/manage-customers/');
                exit;
            } catch (mysqli_sql_exception $exception) {
                mysqli_rollback($conn);
                $errors[] = 'Could not delete this customer because the account is still referenced by store data.';
            }
        }
    } else {
        $errors[] = 'That customer action is not valid.';
    }
}

// Expired VIP memberships go back to normal customers
mysqli_query($conn, "UPDATE users SET role = 'customer', vip_expires_at = NULL
                     WHERE role = 'vip' AND vip_expires_at IS NOT NULL AND vip_expires_at <= CURRENT_TIMESTAMP");

// Customer list (with the optional search)
$customer_search = trim($_GET['search'] ?? '');
$search_text = mysqli_real_escape_string($conn, $customer_search);
$customer_sql = "SELECT u.user_id, u.username, u.email, u.role, u.status, u.vip_expires_at, c.customer_name, c.phone,
                        COUNT(DISTINCT o.order_id) AS order_count
                 FROM users u
                 LEFT JOIN customers c ON c.user_id = u.user_id
                 LEFT JOIN orders o ON o.user_id = u.user_id
                 WHERE u.role IN ('customer', 'vip')";
if ($customer_search != '') {
    $customer_sql .= " AND (c.customer_name LIKE '%$search_text%' OR u.username LIKE '%$search_text%'
                            OR u.email LIKE '%$search_text%' OR c.phone LIKE '%$search_text%')";
}
$customer_sql .= " GROUP BY u.user_id, u.username, u.email, u.role, u.status, u.vip_expires_at, c.customer_name, c.phone
                   ORDER BY u.user_id DESC";
$customers = mysqli_fetch_all(mysqli_query($conn, $customer_sql), MYSQLI_ASSOC);
$customer_total = count($customers);

$admin_page_title = 'Manage Customers';
$admin_active_page = 'customers';
include("../includes/header.php");
?>
<section class="admin-page-heading">
  <div><p class="admin-eyebrow">CUSTOMER ACCOUNTS</p><h1>Manage Customers</h1><p>Create accounts, edit contact details, and manage customer access.</p></div>
  <a class="admin-button admin-button-light" href="<?php echo BASE_URL; ?>admin/manage-customers/premium-payments.php">Review Premium payments</a>
</section>
<?php include("../../includes/alert.php"); ?>
<?php if ($errors) { ?><div class="manage-alert is-error" role="alert"><ul><?php foreach ($errors as $error) { ?><li><?php echo htmlspecialchars($error); ?></li><?php } ?></ul></div><?php } ?>

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
    <label>Customer name<input name="customer_name" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>"></label>
    <label>Username<input name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"></label>
    <label>Email<input type="text" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></label>
    <label>Phone<input name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"></label>
    <label>Temporary password<input type="password" name="password" autocomplete="new-password"></label>
    <label>Address<input name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>"></label>
    <div class="manage-form-wide"><button class="admin-button" type="submit">Create customer</button></div>
  </form>
</section>

<section class="manage-panel manage-list-panel customer-directory-panel">
  <div class="manage-panel-heading">
    <div><p class="admin-eyebrow">CUSTOMER DIRECTORY</p><h2>Customers <span class="manage-count"><?php echo $customer_total; ?></span></h2></div>
    <form method="get" class="inventory-history-search">
      <input type="hidden" name="customer_view" value="directory">
      <label class="visually-hidden" for="customer-search">Search customer</label>
      <input id="customer-search" type="search" name="search" value="<?php echo htmlspecialchars($customer_search); ?>" placeholder="Search customer">
      <button class="admin-button admin-button-light" type="submit">Search</button>
    </form>
  </div>
  <?php if ($customers) { ?>
    <div class="admin-table-wrap"><table class="admin-table">
      <thead><tr><th>Customer</th><th>Username</th><th>Email</th><th>Orders</th><th>Role</th><th>Account</th><th>Actions</th></tr></thead>
      <tbody><?php foreach ($customers as $customer) { ?>
        <tr>
          <td class="order-number"><?php echo htmlspecialchars($customer['customer_name'] ?? 'Customer'); ?><small class="manage-secondary"><?php echo htmlspecialchars($customer['phone'] ?? ''); ?></small></td>
          <td><?php echo htmlspecialchars($customer['username']); ?></td>
          <td><?php echo htmlspecialchars($customer['email']); ?></td>
          <td><?php echo (int)$customer['order_count']; ?></td>
          <td><?php echo htmlspecialchars(['customer' => 'Customer', 'vip' => 'VIP'][$customer['role']] ?? ucfirst($customer['role'])); ?>
            <?php if ($customer['role'] === 'vip' && $customer['vip_expires_at']) { ?><small class="manage-secondary">Until <?php echo htmlspecialchars(date('M j, Y', strtotime($customer['vip_expires_at']))); ?></small><?php } ?>
          </td>
          <td><span class="manage-status-<?php echo htmlspecialchars($customer['status']); ?>"><?php echo ucfirst(htmlspecialchars($customer['status'])); ?></span></td>
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
<?php include("../includes/footer.php"); ?>
