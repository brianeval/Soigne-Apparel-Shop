<?php
function clear_customer_session()
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['role'],
        $_SESSION['vip_expires_at']
    );
}

function sync_customer_session(mysqli $conn)
{
    if (!isset($_SESSION['user_id'])) {
        return 'guest';
    }
    if (($_SESSION['role'] ?? '') === 'admin') {
        return 'admin';
    }

    $user_id = (int)$_SESSION['user_id'];
    $account_statement = mysqli_prepare($conn,
        "SELECT role, status, vip_expires_at,
                (vip_expires_at IS NOT NULL AND vip_expires_at <= CURRENT_TIMESTAMP) AS vip_expired
         FROM users
         WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($account_statement, 'i', $user_id);
    mysqli_stmt_execute($account_statement);
    $account = mysqli_fetch_assoc(mysqli_stmt_get_result($account_statement));

    if (!$account || $account['status'] !== 'active') {
        clear_customer_session();
        return 'inactive';
    }

    if ($account['role'] === 'vip' && (int)$account['vip_expired'] === 1) {
        $expire_membership_statement = mysqli_prepare($conn,
            "UPDATE users SET role = 'customer', vip_expires_at = NULL
             WHERE user_id = ? AND role = 'vip' AND vip_expires_at <= CURRENT_TIMESTAMP"
        );
        mysqli_stmt_bind_param($expire_membership_statement, 'i', $user_id);
        mysqli_stmt_execute($expire_membership_statement);
        $account['role'] = 'customer';
    }

    if (!in_array($account['role'], ['customer', 'vip'], true)) {
        clear_customer_session();
        return 'invalid';
    }

    $_SESSION['role'] = $account['role'];
    if ($account['role'] === 'vip') {
        $_SESSION['vip_expires_at'] = $account['vip_expires_at'];
    } else {
        unset($_SESSION['vip_expires_at']);
    }

    return $account['role'];
}

function store_product_price_markup(float $price)
{
    if (($_SESSION['role'] ?? '') !== 'vip') {
        return htmlspecialchars(peso($price), ENT_QUOTES, 'UTF-8');
    }

    $discounted_price = round($price * 0.90, 2);
    return '<span class="premium-product-price"><del>' . htmlspecialchars(peso($price), ENT_QUOTES, 'UTF-8')
        . '</del> <strong>' . htmlspecialchars(peso($discounted_price), ENT_QUOTES, 'UTF-8')
        . '</strong><small>VIP 10% off</small></span>';
}

function require_active_customer(mysqli $conn, string $redirect_path)
{
    $customer_role = sync_customer_session($conn);
    if (in_array($customer_role, ['customer', 'vip'], true)) {
        return;
    }

    header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode($redirect_path));
    exit;
}
