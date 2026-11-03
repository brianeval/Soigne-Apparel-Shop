<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $renew_vip = ($_POST['renew_vip'] ?? '') == '1';


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
