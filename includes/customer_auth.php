<?php



$customer_role = 'guest';

if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') == 'admin') {
        $customer_role = 'admin';
    } else {
        $auth_user_id = (int)$_SESSION['user_id'];
        $auth_result = mysqli_execute_query($conn, "SELECT role, status, vip_expires_at,
                    (vip_expires_at IS NOT NULL AND vip_expires_at <= CURRENT_TIMESTAMP) AS vip_expired
             FROM users
             WHERE user_id = ?", [$auth_user_id]);
        $auth_account = mysqli_fetch_assoc($auth_result);

        if (!$auth_account || $auth_account['status'] != 'active' || !in_array($auth_account['role'], ['customer', 'vip'])) {

            unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['vip_expires_at']);
            $customer_role = 'inactive';
        } else {

            if ($auth_account['role'] == 'vip' && $auth_account['vip_expired'] == 1) {
                mysqli_query($conn, "UPDATE users SET role = 'customer', vip_expires_at = NULL WHERE user_id = $auth_user_id");
                $auth_account['role'] = 'customer';
            }

            $_SESSION['role'] = $auth_account['role'];
            if ($auth_account['role'] == 'vip') {
                $_SESSION['vip_expires_at'] = $auth_account['vip_expires_at'];
            } else {
                unset($_SESSION['vip_expires_at']);
            }
            $customer_role = $auth_account['role'];
        }
    }
}
