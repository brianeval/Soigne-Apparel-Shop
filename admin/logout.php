<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: /soigne_apparel_shop/admin/login.php');
exit;
