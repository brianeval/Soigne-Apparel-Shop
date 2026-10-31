<?php
session_start();
include_once __DIR__ . '/../includes/config.php';
session_destroy();
header('Location: ' . BASE_URL . 'admin/login.php');
exit;
