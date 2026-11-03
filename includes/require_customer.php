<?php


include_once('../includes/customer_auth.php');

if ($customer_role != 'customer' && $customer_role != 'vip') {
    header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode($login_redirect));
    exit;
}
