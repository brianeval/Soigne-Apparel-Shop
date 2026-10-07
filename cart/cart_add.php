<?php
    session_start();
    $logged_in = isset($_SESSION['user_id']);
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php');
        exit;
    }
?>