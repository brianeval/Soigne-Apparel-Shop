<?php
// Use this on pages that need a signed-in customer.
// Set $login_redirect (the page to come back to after signing in) before including this file.
include_once(__DIR__ . '/customer_auth.php');

if ($customer_role != 'customer' && $customer_role != 'vip') {
    header('Location: ' . BASE_URL . 'users/login.php?redirect=' . urlencode($login_redirect));
    exit;
}
