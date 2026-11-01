<?php
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
