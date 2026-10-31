<?php
session_start();
include_once("../includes/config.php");

header('Location: ' . BASE_URL . 'index.php');
exit;
