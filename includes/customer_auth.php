<?php
function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'users/login.php');
        exit;
    }
}
