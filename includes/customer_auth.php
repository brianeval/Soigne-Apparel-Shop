<?php
function sync_customer_session(mysqli $conn): string
{
    if (!isset($_SESSION['user_id'])) {
        return 'guest';
    }
    if (($_SESSION['role'] ?? '') === 'admin') {
        return 'admin';
    }

    $user_id = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn,
        "SELECT role, status, vip_expires_at,
                (vip_expires_at IS NOT NULL AND vip_expires_at <= CURRENT_TIMESTAMP) AS vip_expired
         FROM users
         WHERE user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$account || $account['status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['vip_expires_at']);
        return 'inactive';
    }
    if ($account['role'] === 'vip' && (int)$account['vip_expired'] === 1) {
        $expire_stmt = mysqli_prepare($conn,
            "UPDATE users SET role = 'customer', vip_expires_at = NULL
             WHERE user_id = ? AND role = 'vip' AND vip_expires_at <= CURRENT_TIMESTAMP"
        );
        mysqli_stmt_bind_param($expire_stmt, 'i', $user_id);
        mysqli_stmt_execute($expire_stmt);
        $account['role'] = 'customer';
    }

    if (!in_array($account['role'], ['customer', 'vip'], true)) {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['vip_expires_at']);
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

function store_product_price_markup(float $price): string
{
    if (($_SESSION['role'] ?? '') !== 'vip') {
        return htmlspecialchars(peso($price), ENT_QUOTES, 'UTF-8');
    }

    $discounted_price = round($price * 0.90, 2);
    return '<span class="premium-product-price"><del>' . htmlspecialchars(peso($price), ENT_QUOTES, 'UTF-8')
        . '</del> <strong>' . htmlspecialchars(peso($discounted_price), ENT_QUOTES, 'UTF-8')
        . '</strong><small>VIP 10% off</small></span>';
}

function require_active_customer(mysqli $conn, string $redirect_path): void
{
    $role = sync_customer_session($conn);
    if (!in_array($role, ['customer', 'vip'], true)) {
        header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode($redirect_path));
        exit;
    }
}
