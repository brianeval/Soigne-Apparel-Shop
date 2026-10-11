<?php
function require_active_customer(mysqli $conn, string $redirect_path): void
{
    if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
        header('Location: ' . BASE_URL . 'admin/index.php');
        exit;
    }

    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
        header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode($redirect_path));
        exit;
    }

    $stmt = mysqli_prepare($conn, "SELECT status FROM users WHERE user_id = ? AND role = 'customer'");
    $user_id = (int)$_SESSION['user_id'];
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$account || $account['status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
        header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode($redirect_path));
        exit;
    }
}
